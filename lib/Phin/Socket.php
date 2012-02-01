<?php

namespace Phin;

use Phin\Socket\Exception,
    Phin\Socket\AddrInfo;

class Socket
{
    # Internal: Wrapped Socket Resource, can be retrieved for
    # raw socket access.
    var $handle;

    static function unix($type = SOCK_STREAM)
    {
        return static::create(AF_UNIX, $type, 0);
    }

    static function inet($type = SOCK_STREAM, $protocol = SOL_TCP)
    {
        return static::create(AF_INET, $type, $protocol);
    }

    static function inet6($type = SOCK_STREAM, $protocol = SOL_TCP)
    {
        return static::create(AF_INET6, $type, $protocol);
    }

    static function createPair($domain, $type, $protocol)
    {
        $pipe = array();

        if (false === @socket_create_pair($domain, $type, $protocol, $pipe)) {
            throw new Exception(
                socket_strerror(socket_last_error()),
                socket_last_error()
            );
        }

        $pipe[0] = new static($pipe[0]);
        $pipe[1] = new static($pipe[1]);

        return $pipe;
    }

    static function select(&$read, &$write, &$except, $timeoutSeconds, $timeoutMicroSeconds = 0)
    {
    }

    static function create($domain, $type, $protocol)
    {
        $handle = @socket_create($domain, $type, $protocol);

        if (false === $handle) {
            throw new Exception(
                socket_strerror(socket_last_error()),
                socket_last_error()
            );
        }

        return new static($handle);
    }

    # Constructor
    #
    function __construct($resource)
    {
        $this->handle = $resource;
    }

    function bind($host, $port = 0)
    {
        $this->raiseError(
            @socket_bind($this->handle, $host, $port)
        );
        return $this;
    }

    function listen($backlog = 0)
    {
        $this->raiseError(
            @socket_listen($this->handle, $backlog)
        );
        return $this;
    }

    # Tries to accept a connection from the client.
    #
    # Returns an Array of the client connection and an AddrInfo object,
    # or Null when no client was found (probably because someone was faster
    # than we).
    function accept()
    {
        $client = @socket_accept($this->handle);

        # No client found.
        if (!$client) {
            return null;
        }

        socket_getpeername($client, $address, $port);
        $addrinfo = new AddrInfo($address, $port);

        return array(new static($client), $addrinfo);
    }

    function setOption($level, $option, $value)
    {
        $this->raiseError(
            socket_set_option($this->handle, $level, $option, $value)
        );
        return $this;
    }

    function setBlock()
    {
        $this->raiseError(
            socket_set_block($this->handle)
        );
        return $this;
    }

    function setNonBlock()
    {
        $this->raiseError(
            socket_set_nonblock($this->handle)
        );
        return $this;
    }

    function close()
    {
        @socket_close($this->handle);
    }

    protected function raiseError($result)
    {
        if (false === $result) {
            $code = socket_last_error($this->handle);
            $message = socket_strerror($code);

            throw new Exception($message, $code);
        }
        return $result;
    }
}
