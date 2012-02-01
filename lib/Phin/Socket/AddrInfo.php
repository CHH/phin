<?php

namespace Phin\Socket;

use Phin\UnexpectedValueException;

class AddrInfo
{
    protected $address;
    protected $port;

    # Constructor
    #
    # address - Address of the peer, can be a path to a unix socket too.
    # port    - Optional port number.
    function __construct($address, $port = null)
    {
        $this->address = $address;
        $this->port = $port;
    }

    # Returns the address, or path to Unix Socket as String.
    function getAddress()
    {
        return $this->address;
    }

    # Returns the port number or Null.
    function getPort()
    {
        return $this->port;
    }

    # Tries to resolve the IP Address to a hostname.
    #
    # Returns the hostname as String. When the address could not be
    # resolved, the Address is returned.
    function getHostName()
    {
        $name = @gethostbyaddr($this->address);

        if ($name === false) {
            throw new UnexpectedValueException("Malformed IP Address string.");
        }

        return $name;
    }
}
