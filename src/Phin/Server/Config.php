<?php

/**
 * A simple HTTP Server with a Rack-like Protocol
 *
 * @package Phin
 * @author Christoph Hochstrasser <christoph.hochstrasser@gmail.com>
 * @license MIT License
 * @copyright (c) 2011 Christoph Hochstrasser
 */

namespace Phin\Server;

class Config
{
    protected $documentRoot;
    protected $debugMode = false;
    
    /**
     * Name of the Net_Server Driver. You may use the "Fork" Driver on *nix Systems for
     * better Performance.
     *
     * @var string
     */
    protected $driverName = "Fork";
    
    /**
     * Hostname or IP Address for listening
     *
     * @var string
     */
    protected $host = "0.0.0.0";
    
    /**
     * TCP Port, extended permissions are needed to run on a port < 1024 ("Well known Port")
     *
     * @var int
     */
    protected $port = 3000;
    
    function __construct(array $options = array())
    {
        /*
         * Use the Sequential driver by default on Windows, because
         * PCNTL (and therefore Process Forking) is not supported on Windows.
         */
        if (strtoupper(substr(PHP_OS, 0, 3)) == "WIN") {
            $this->driverName = "Sequential";
        }
    
        empty($options) ?: $this->setOptions($options);
    }
    
    /**
     * TCP/IP Port on which the server should listen
     *
     * Note: The server needs root privileges to run on a port < 1024 ("well known port")
     */
    function setPort($port)
    {
        $this->port = $port;
    }
    
    function getPort()
    {
        return $this->port;
    }
    
    function setDebugMode($debugMode = true)
    {
        $this->debugMode = $debugMode;
    }
    
    function isDebugModeEnabled()
    {
        return (bool) $this->debugMode;
    }
    
    function setDocumentRoot($docRoot)
    {
        if (!is_dir($docRoot)) {
            throw new Server\InvalidArgumentException("Document root does not exist");
        }
        $this->documentRoot = $docRoot;
    }
    
    function getDocumentRoot()
    {
        return $this->documentRoot;
    }
    
    function setHost($host)
    {
        $this->host = $host;
    }
    
    function getHost()
    {
        return $this->host;
    }

    function setDriverName($name)
    {
        $this->driverName = $name;
    }
    
    function getDriverName()
    {
        return $this->driverName;
    }
    
    /**
     * Converts the array of options to respective Setter names and calls them
     * with the option value
     *
     * e.g. for the option "host" the Setter setHost() would be called, with the
     * option value as argument or for the option "document_root" the Setter
     * setDocumentRoot() would be called.
     *
     * @param array $options
     */
    protected function setOptions(array $options)
    {
        foreach ($options as $option => $value) {
            // Convert option_name to setOptionName
            $method = "set" . str_replace(' ', '', ucwords(str_replace(array('-', '_'), ' ', strtolower($option))));

            if (!is_callable(array($this, $method))) {
                throw new Server\UnexpectedValueException("$option is not defined");
            }
            $this->{$method}($value);
        }
    }
}

