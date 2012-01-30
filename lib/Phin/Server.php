<?php

namespace Phin;

use Phin\Server\Config,
    Phin\Server\InvalidArgumentException,
    Phin\Server\UnexpectedValueException,
    Evenement\EventEmitter;

class Server
{
    const VERSION = "0.4.0";

    # Public: An EventEmitter, for binding handlers to
    # Phin's events.
    var $events;

    # Bound Socket.
    var $socket;

    # Used to write messages back from the child to the parent.
    protected $selfPipe = array();

    # Server Config
    protected $config;

    # Server Callback
    protected $handler;

    # Internal: PIDs of all Workers
    protected $workers;

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

        $this->events = new EventEmitter;
    }

    # Public: Sets a handler to run on each request.
    #
    # handler - Callback to run on each request.
    function run($handler)
    {
        $this->events->on('handle', $handler);
        return $this;
    }

    function listen()
    {
        $config = $this->config;
        $this->events->emit('bootstrap', array($this));

        if ($config->socket) {
            $this->socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
        } else {
            $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        }

        socket_set_nonblock($this->socket);

        if (false === $this->socket) {
            throw new UnexpectedValueException(sprintf(
                "Error while creating Socket: %s", socket_strerror(socket_last_error())
            ));
        }

        # Use an Unix Socket
        if ($unixSocket = $config->socket) {
            if (false === @socket_bind($this->socket, $unixSocket)) {
                throw new UnexpectedValueException(sprintf(
                    "Unable to bind to Unix Socket %s: %s", 
                    $unixSocket, socket_strerror(socket_last_error($this->socket))
                ));
            }
        # Use an AF_INET Socket
        } else {
            $host = $config->host;
            $port = $config->port;

            if (false === @socket_bind($this->socket, $host, $port)) {
                throw new UnexpectedValueException(sprintf(
                    "Unable to bind to %s:%d: %s",
                    $host, $port, socket_strerror(socket_last_error($this->socket))
                ));
            }
        }

        if (false === @socket_listen($this->socket, 10)) {
            throw new UnexpectedValueException(sprintf(
                "Unable to listen on socket: %s",
                socket_strerror(socket_last_error($this->socket))
            ));
        }

        socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $this->selfPipe);

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

        socket_close($this->selfPipe[1]);

        register_shutdown_function(function() use ($config) {
            socket_close($this->socket);
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

        socket_close($this->selfPipe[0]);

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
        $client = @socket_accept($this->socket);

        if (!$client) {
            usleep(10000);
            return;
        }

        $request = '';

        while ($chunk = @socket_read($client, 16384)) {
            if (false === $chunk) {
                throw new UnexpectedValueException(sprintf(
                    "Unable to read from client: %s",
                    socket_strerror(socket_last_error($client))
                ));
            }
            $request .= $chunk;
        }

        $pid = posix_getpid();

        echo "------ Worker #$pid received Request: ------\n";
        echo $request;

        socket_close($client);
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

