<?php

namespace Phin\Socket;

use Phin\Socket;

class SocketIO implements \Phin\IO
{
    protected $socket;

    function __construct(Socket $socket)
    {
        $this->socket = $socket;
    }

    function write($buffer)
    {
        $buffer = (string) $buffer;
        $bytesToWrite = strlen($buffer);

        while ($bytesToWrite > 0) {
            $written = @socket_write($this->socket->handle, $buffer);
            $bytesToWrite = $bytesToWrite - $written;

            if ($bytesToWrite > 0) {
                $buffer = substr($buffer, 0, $written);
            }
        }

        return $bytesToWrite;
    }

    function read($length = 0)
    {
        return @socket_read($this->socket->handle, $length, PHP_BINARY_READ);
    }

    function close()
    {
        $this->socket->close();
    }
}
