<?php

namespace Fab;

# Implements a simple preforking echo server with a fixed
# size worker pool, which responds to requests
# on the Unix Socket `/tmp/fab.sock`.
class Application
{
    const WORKER_POOL_SIZE = 4;
    const UNIX_SOCKET = '/tmp/foo.sock';

    # Holds the listening socket
    var $socket;

    # A socket pair between the worker and the master.
    # The child kills itself when the worker closed its read side (e.g. when
    # master is killed).
    var $selfPipe;

    # List of worker PIDs
    var $workers = array();

    function run()
    {
        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

        if (false === $this->socket) {
            fwrite(STDERR, sprintf("Unable to create Socket: %s\n", socket_strerror(socket_last_error())));
            exit(1);
        }

        socket_set_nonblock($this->socket);

        if (false === @socket_bind($this->socket, self::UNIX_SOCKET)) {
            fwrite(STDERR, sprintf("Unable to bind socket: %s\n", socket_strerror(socket_last_error())));
            exit(1);
        }

        if (false === @socket_listen($this->socket)) {
            fwrite(STDERR, sprintf("Listening failed: %s\n", socket_strerror(socket_last_error())));
            exit(1);
        }

        if (false === @socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $this->selfPipe)) {
            fwrite(STDERR, sprintf("Creating pipe failed: %s\n", socket_strerror(socket_last_error())));
            exit(1);
        }

        for ($i = 1; $i <= self::WORKER_POOL_SIZE; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                fwrite(STDERR, "Could not fork");
                exit(1);
            } else if ($pid) {
                # Parent gets the PID
                $this->workers[] = $pid;
            } else {
                # Child
                $this->workerProcess();
                exit();
            }
        }

        # Makes the pipe between the child and the master
        # unidirectional, by closing the write side.
        socket_close($this->selfPipe[1]);

        $socket = $this->socket;

        register_shutdown_function(function() use ($socket) {
            fwrite(STDERR, "Cleaning up.\n");
            socket_close($socket);
            unlink(Application::UNIX_SOCKET);
        });

        pcntl_signal(SIGCHLD, function() {
            fwrite(STDERR, "Child terminated!\n");
        });

        pcntl_signal(SIGINT, function() {
            fwrite(STDERR, "Master shutting down!\n");
            exit();
        });

        pcntl_signal(SIGUSR2, function() {
            fwrite(STDERR, "I'm a teapot!\n");
        });

        $masterPid = posix_getpid();
        $workers = "Workers: ".join(', ', $this->workers);

        fwrite(STDERR, <<<EOF
Master ($masterPid) is listening.
Kill with [STRG] + [C]
$workers

EOF
);

        # Master loop
        for (;;) {
            pcntl_signal_dispatch();
            # Give the CPU some time to breath for 100ms
            usleep(100000);
        }
    }

    protected function workerProcess()
    {
        pcntl_signal(SIGINT, function() {
            exit();
        });

        # Close the read side of the IPC pipe.
        socket_close($this->selfPipe[0]);

        for (;;) {
            $this->workerLoop();
            pcntl_signal_dispatch();
        }
    }

    protected function workerLoop()
    {
        $r = array($this->socket);
        $w = null;
        $e = array($this->selfPipe[1]);

        $readyCount = @socket_select($r, $w, $e, 15);

        if ($readyCount > 0) {
            if ($e) {
                # The pipe between the child and the parent has unexpectedly
                # closed. This means that the parent died. Let's die too.
                exit();

            } else if ($r) {
                $client = @socket_accept($this->socket);
                $request = '';

                if (!$client) {
                    usleep(100000);
                    return;
                }

                while ($chunk = @socket_read($client, 16348)) {
                    $request .= $chunk;
                }

                $selfPid = posix_getpid();

                fwrite(STDERR, "------ Worker $selfPid received a request: ------\n");
                fwrite(STDERR, "$request\n");

                socket_write($client, "($selfPid): $request\r\n");
                socket_close($client);
            }
        }
    }
}


$app = new Application;
$app->run();
