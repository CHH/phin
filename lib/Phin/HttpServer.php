<?php

namespace Phin;

use Phin\Config,
    Phin\Request\StandardParser,
    Phin\Socket,
    Phin\InvalidArgumentException,
    Phin\UnexpectedValueException,
    Evenement\EventEmitter;

class HttpServer
{
    const VERSION = "0.4.0";

    # Public: An EventEmitter, for binding handlers to
    # Phin's events.
    var $events;

    # Bound Socket.
    var $socket;

    var $parser;

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

        $this->parser = new StandardParser;
        $this->events = new EventEmitter;
    }

    # Public: Sets a handler to run on each request.
    #
    # handler - Callback to run on each request.
    function run($handler)
    {
        $this->handler = $handler;
        return $this;
    }

    function listen()
    {
        $config = $this->config;
        $this->events->emit('bootstrap', array($this));

        if ($config->socket) {
            $this->socket = Socket::unix();
        } else {
            $this->socket = Socket::inet();
        }

        $this->socket->setNonBlock();

        if ($unixSocket = $config->socket) {
            # Use an Unix Socket
            $this->socket->bind($unixSocket);
        } else {
            $this->socket->bind($config->host, $config->port);
        }

        $this->socket->listen(5);

        $this->selfPipe = Socket::createPair(AF_UNIX, SOCK_STREAM, 0);

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

        fwrite(STDERR, "Started all workers\n");

        $this->selfPipe[1]->close();

        register_shutdown_function(function() use ($config) {
            $this->socket->close();
            $config->socket and unlink($config->socket);
        });

        pcntl_signal(SIGCHLD, function() {
            fwrite(STDERR, "Child terminated!\n");
            # Decrement actual worker count and respawn
            # the worker process.
        });

        pcntl_signal(SIGINT, function() {
            exit();
        });

        pcntl_signal(SIGUSR2, function() {
            fwrite(STDERR, "I'm a Teapot!");
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

        $this->selfPipe[0]->close();

        for (;;) {
            $this->workerLoop();
            pcntl_signal_dispatch();
        }
    }

    protected function workerLoop()
    {
        $read  = array($this->socket->handle);
        $write = null;
        $exception = array($this->selfPipe[1]->handle);

        # Wait for data ready to be read, or look for exceptions
        $readySize = @socket_select($read, $write, $exception, 30);

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
        $client = $this->socket->accept();

        if (!$client) {
            usleep(10000);
            return;
        }

        $io = new Socket\SocketIO($client);
        $env = $this->createEnvironment();

        $message = '';

        while ($chunk = $io->read(16384)) {
            if (!$chunk) {
                break;
            }
            $message .= $chunk;
        }

        try {
            $this->parser->parse($message, $env);

        } catch (UnexpectedValueException $e) {
            fwrite(STDERR, (string) $e);
            $client->close();
            return;
        }

        $resp = call_user_func($this->handler, $env);
        $resp = Response::fromArray($resp);

        fwrite(STDERR, $resp->headersToString());

        $io->write($resp->toString());
        $client->close();
    }

    protected function sendResponse($client, $response)
    {
        $client->write($response);
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

