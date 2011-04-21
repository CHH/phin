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

require_once "Net/Server.php";

use Net_Server,
    Net_Server_Driver,
    Phin\Server\Config,
    Phin\Server\Connection,
    Phin\Server\InvalidArgumentException,
    Phin\Server\Request\Handler;

class Server
{   
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
        $this->handler = $callback;
        return $this;
    }

    /**
     * Server starts listening for requests
     */
    function listen()
    {   
        $config = $this->config;
        
        $this->driver = Net_Server::create(
            $config->getDriverName(), $config->getHost(), $config->getPort()
        );
        
        $this->driver->setEndCharacter("\r\n\r\n");
        
        $this->connection = new Server\Connection($this->driver, $config);
        $this->connection->signals->handle->bind($this->handler);
        
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

