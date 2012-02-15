<?php

namespace Phin;

use Phin\Config,
    Phin\Request\StandardParser,
    Phin\Socket,
    Phin\InvalidArgumentException,
    Phin\UnexpectedValueException,
    Monolog\Logger,
    Monolog\Handler\StreamHandler,
    Monolog\Formatter\LineFormatter;

class HttpServer
{
    const VERSION = "0.4.0";

    # Public: An EventEmitter, for binding handlers to
    # Phin's events.
    var $events;

    # Bound Socket.
    var $socket;

    var $parser;

    var $log;

    # Used to write messages back from the child to the parent.
    protected $selfPipe = array();

    # Server Config
    protected $config;

    # Server Callback
    protected $handler;

    # Internal: PIDs of all Workers
    protected $workers;

    protected $leftOver = '';

    # Initializes the server with the config.
    #
    # config - Array of Options or instance of \Phin\Server\Config
    function __construct($config = array())
    {
        if ($config instanceof Config) {
            $this->config = $config;

        } else if (is_array($config)) {
            $this->config = new Config($config);

        } else {
            throw new \InvalidArgumentException(
                "Config must be either an array of options or "
                . "an instance of \\Phin\\Server\\Config"
            );
        }

        $this->log = new Logger('phin');

        $stderrHandler = new StreamHandler(STDERR);
        $stderrHandler->setFormatter(new LineFormatter(
            "[%datetime%] %channel% (%level_name%): %message%\n"
        ));

        $this->log->pushHandler($stderrHandler);
    }

    # Public: Sets a handler to run on each request.
    #
    # handler - Callback to run on each request. The handler receives
    #           and IO instance and should close the connection after he
    #           has finished writing.
    function run($handler)
    {
        $this->handler = $handler;
        return $this;
    }

    function listen()
    {
        if (file_exists($this->config->pidFile)) {
            throw new UnexpectedValueException(sprintf(
                "Existing PID file found in %s. Maybe the server is already running. Delete this file,
                or make sure the server does not run.",
                $this->config->pidFile
            ));
        }

        $config = $this->config;
        $log    = $this->log;

        $context = stream_context_create(array("socket" => array(
            "backlog" => 5
        )));

        $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;

        if ($config->socket) {
            $this->socket = stream_socket_server("udg://{$config->socket}", $errorCode, $errorMessage, $flags, $context);
        } else {
            $this->socket = stream_socket_server("tcp://{$config->host}:{$config->port}", $errorCode, $errorMessage, $flags, $context);
        }

        if (false === $this->socket) {
            if ($errorCode === 0) {
                $errorMessage = "Failed binding to socket";
            }
            throw new UnexpectedValueException("Server startup failed: ".$errorMessage);
        }

        register_shutdown_function(function($socket, $config) {
            fclose($socket);
            $config->socket and @unlink($config->socket);
            @unlink($config->pidFile);
        }, $this->socket, $config);

        stream_set_blocking($this->socket, 0);

        if (false === @file_put_contents($config->pidFile, posix_getpid())) {
            throw new UnexpectedValueException(sprintf(
                'Could not create %s. Maybe you have no permission to write it.',
                $config->pidFile
            ));
        }

        $this->selfPipe = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        for ($i = 1; $i <= $config->workerPoolSize; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                # Error while forking:

            } else if ($pid) {
                # Parent:
                $this->workers[] = $pid;
            } else {
                # Child:
                $this->workerProcess();
                exit();
            }
        }

        $log->info("Started ".count($this->workers)." workers");

        fclose($this->selfPipe[1]);

        pcntl_signal(SIGCHLD, function() use ($log) {
            $log->warn("Child terminated!\n");
            # Decrement actual worker count and respawn
            # the worker process.
        });

        pcntl_signal(SIGINT, function() {
            exit();
        });

        pcntl_signal(SIGUSR2, function() use ($log) {
            $log->info("I'm a Teapot!");
        });

        for (;;) {
            pcntl_signal_dispatch();
            usleep(100000);
        }
    }

    protected function workerProcess()
    {
        pcntl_signal(SIGINT, function() {
            exit();
        });

        fclose($this->selfPipe[0]);

        for (;;) {
            $this->workerLoop();
            pcntl_signal_dispatch();
        }
    }

    protected function workerLoop()
    {
        $read  = array($this->socket);
        $write = null;
        $exception = array($this->selfPipe[1]);

        # Wait for data ready to be read, or look for exceptions
        $readySize = @stream_select($read, $write, $exception, 30);

        if ($readySize === false) {
            # Error happened
        } else if ($readySize > 0) {
            # Let the child kill itself when the parent closed the pipe
            if ($exception) {
                exit();
            } else if ($read) {
                # Handle Request
                $this->handleRequest();
            }
        }
    }

    protected function handleRequest()
    {
        $client = @stream_socket_accept($this->socket, 0, $peer);

        if (!$client) {
            usleep(100);
            return;
        }

        $this->log->info("$peer");

        call_user_func($this->handler, $client);
        unset($client);
    }

    protected function createEnvironment()
    {
        $config = $this->config;

        $env = new Environment;
        $env->set("SERVER_NAME", $config->host);
        $env->set("SERVER_PORT", $config->port);
        $env->set("DOCUMENT_ROOT", $config->documentRoot);
        $env->set("TMP", $config->tempDir);

        return $env;
    }

    function stopListening()
    {
    }

    # Returns the config instance.
    function getConfig()
    {
        return $this->config;
    }
}

