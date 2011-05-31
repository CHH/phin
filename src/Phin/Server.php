<?php
/**
 * A simple HTTP Server with a Rack-like Protocol
 *
 * @package Phin
 * @author Christoph Hochstrasser <christoph.hochstrasser@gmail.com>
 * @license MIT License
 * @copyright (c) 2011 Christoph Hochstrasser
 */

namespace Phin;

use Net_Server,
    Net_Server_Driver,
    Phin\Server\Config,
    Phin\Server\Connection,
    Phin\Server\InvalidArgumentException,
    Phin\Server\Request\Handler;

class Server
{   
    const VERSION = "0.3.0";

    /** @var \Phin\Server\Config */
    protected $config;
    
    /** @var \Phin\Server\Connection */
    protected $connection;
    
    /** @var \Phin\Server\Handler */
    protected $handler;
    
    /**
     * Constructor
     *
     * @param array $config Array of options
     */
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

        $this->setConnection(new Server\Connection($this->config));
    }

    /**
     * Sets a callback which should be run on every request and gets passed an
     * Environment as first argument
     *
     * The callback should have the signature: 
     * <code>function(\Phin\Server\Environment $env);</code>
     * The callback should return in the form of:
     * <code>array($status, $arrayOfResponseHeaders, $body)</code>
     *
     * @param  callback $callback
     * @return Server
     */
    function run($callback)
    {
        $this->connection->signals->handle->bind($callback);
        return $this;
    }

    /**
     * Server starts listening for requests
     */
    function listen()
    {   
        $config = $this->config;

        $server = new Net_Server;
        $driver = $server->create(
            $config->getDriverName(), $config->getHost(), $config->getPort()
        );

        if ($driver instanceof \PEAR_Error) {
            throw new Server\RuntimeException(
                "Error while creating Net_Server_Driver: " . $driver->toString()
            );
        }
        
        $this->driver = $driver;
        $this->connection->setDriver($this->driver);
        $this->driver->setEndCharacter("\r\n\r\n");
        
        $this->driver->setCallbackObject($this->connection);
        $this->driver->setDebugMode($config->isDebugModeEnabled());
        
        $this->driver->start();
    }

    /**
     * Stop the server from listening for requests
     */
    function stopListening()
    {
        if (!$this->driver instanceof \Net_Server_Driver) {
            throw new Server\RuntimeException("Server was not started");
        }
        $this->driver->shutDown();
    }

    function setConnection(Server\Connection $connection)
    {
        $this->connection = $connection;
        return $this;
    }
    
    function getConnection()
    {
        return $this->connection;
    }
    
    function getConfig()
    {
        return $this->config;
    }
}

