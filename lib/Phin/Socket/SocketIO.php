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
        return @socket_write($this->socket->handle, (string) $buffer);
    }

    function read($length = 0)
    {
        return @socket_read($this->socket->handle, $length, PHP_BINARY_READ);
    }
}
