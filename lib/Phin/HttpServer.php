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

    protected $workerPoolSize = 0;

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

        $this->workerPoolSize = $this->config->workerPoolSize;

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
            $serverPid = trim(file_get_contents($this->config->pidFile));

            if (!posix_kill($serverPid, 0)) {
                unlink($this->config->pidFile);
            } else {
                throw new UnexpectedValueException("Server is already running with PID $serverPid.");
            }
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
                $errorMessage = "Failed binding to socket.";
            }
            throw new UnexpectedValueException("Server startup failed: ".$errorMessage);
        }

        # Make the server socket non-blocking.
        stream_set_blocking($this->socket, 0);

        if (false === @file_put_contents($config->pidFile, posix_getpid())) {
            throw new UnexpectedValueException(sprintf(
                'Could not create %s. Maybe you have no permission to write it.',
                $config->pidFile
            ));
        }

        $this->selfPipe = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        # Spawn up the initial worker pool.
        $this->spawnWorkers();

        register_shutdown_function(array($this, "stopListening"));

        pcntl_signal(SIGTTIN, array($this, "incrementWorkerCount"));
        pcntl_signal(SIGTTOU, array($this, "decrementWorkerCount"));

        pcntl_signal(SIGINT, function() {
            exit();
        });

        pcntl_signal(SIGUSR2, function() use ($log) {
            $log->info("I'm a Teapot!");
        });

        $this->log->info(sprintf(
            "Listening on %s",
            $config->socket ? $config->socket : $config->host.":".$config->port
        ));

        # Monitor the child processes.
        for (;;) {
            pcntl_signal_dispatch();

            $read      = array($this->selfPipe[1]);
            $write     = null;
            $exception = null;
            $readySize = @stream_select($read, $write, $exception, 10);

            # Handle the heartbeat sent by a worker.
            if ($readySize > 0 and $read) {
                $childPid = trim(fgets($read[0]));
                $this->workers[$childPid]["heartbeat"] = time();
                $this->log->info("Child [$childPid] is alive.");
            }

            $this->removeStaleWorkers();

            # Maintain a stable worker count. Compares the actual worker count
            # to the configured worker count and spawns workers when necessary.
            $this->spawnWorkers();
        }
    }

    function incrementWorkerCount()
    {
        ++$this->workerPoolSize;
        $this->spawnWorkers();
    }

    function decrementWorkerCount()
    {
        --$this->workerPoolSize;

        $workersToKill = count($this->workers) - $this->workerPoolSize;

        foreach (array_slice($this->workers, 0, $workersToKill) as $pid => $info) {
            posix_kill($pid, SIGKILL);
        }
    }

    function stopListening()
    {
        @fclose($this->socket);
        @fclose($this->selfPipe[0]);
        @fclose($this->selfPipe[1]);

        if ($this->config->socket) unlink($this->config->socket);

        unlink($this->config->pidFile);
    }

    protected function removeStaleWorkers()
    {
        $now = time();

        # Go through all workers and kill those who did not
        # made a heartbeat within the timeout period.
        foreach ($this->workers as $pid => $info) {
            if ($pid === pcntl_waitpid($pid, $s, WNOHANG)) {
                unset($this->workers[$pid]);
            } else if ($now - $info["heartbeat"] > $this->config->workerTimeout) {
                # Kill the worker and remove it from the workers array.
                posix_kill($pid, SIGKILL);
                unset($this->workers[$pid]);
            }
        }
    }

    protected function spawnWorkers()
    {
        $workersToSpawn = $this->workerPoolSize - count($this->workers);

        if ($workersToSpawn == 0) {
            return 0;
        }

        for ($i = 0; $i < $workersToSpawn; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                # Error while forking.
                exit();

            } else if ($pid) {
                # Parent, save the worker's process ID.
                $this->workers[$pid] = array(
                    "heartbeat" => time()
                );
            } else {
                # Child:
                $this->workerProcess();
                exit();
            }
        }

        $this->log->info("Spawned $i Workers");

        return $i;
    }

    protected function workerProcess()
    {
        pcntl_signal(SIGINT, function() {
            exit();
        });

        fclose($this->selfPipe[1]);

        for (;;) {
            pcntl_signal_dispatch();
            $this->workerExecute();
        }
    }

    protected function workerExecute()
    {
        $read      = array($this->socket);
        $write     = null;
        $exception = array($this->selfPipe[0]);

        # Wait for data ready to be read, or look for exceptions
        $readySize = @stream_select($read, $write, $exception, 5);

        if ($readySize === false) {
            # Error happened
            exit(1);

        } else if ($readySize > 0) {
            # Let the child kill itself when the parent closed the pipe 
            # (parent was killed)
            if ($exception) {
                exit();
            }

            # Something is available to read on the bound socket.
            if ($read) {
                # Handle Request
                $this->handleRequest();
            }
        # Send the heartbeat to the parent process everytime stream_select() times out.
        } else {
            $msg = posix_getpid()."\n";

            # Exit when the fwrite() fails (when parent died and stream_select()
            # did not catch this).
            if (@fwrite($this->selfPipe[0], $msg) < strlen($msg)) exit();
        }
    }

    protected function handleRequest()
    {
        $client = @stream_socket_accept($this->socket, 0, $peer);

        if (!$client) {
            return;
        }

        $this->log->info("Connection from $peer");

        call_user_func($this->handler, $client);
        unset($client);
    }

    # Returns the config instance.
    function getConfig()
    {
        return $this->config;
    }
}

