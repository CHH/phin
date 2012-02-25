<?php

namespace Phin;

use Evenement\EventEmitter;

class Config
{
    # Path for temporary files.
    var $tempDir;

    var $socket;

    # Server Hostname as String.
    var $host = "0.0.0.0";

    # Port Number as Integer.
    var $port = 3000;

    var $workerPoolSize = 4;

    var $pidFile = '/var/run/phin.pid';

    # Worker timeout in seconds.
    var $workerTimeout = 60;

    # EventEmitter instance.
    var $events;

    var $debug = false;

    function __construct($options = array())
    {
        $this->events  = new EventEmitter;
        $this->tempDir = sys_get_temp_dir();

        if ($options) $this->setOptions($options);
    }

    # Reads a config file. Config files are normal PHP files, except
    # that `$this` refers to the config object.
    #
    # filename - File Path.
    #
    # Returns nothing.
    function readConfigFile($filename)
    {
        if (!is_file($filename)) {
            throw new InvalidArgumentException("$filename does not exist.");
        }

        include($filename);
    }

    # Disable dynamic properties on this object:
    function __get($prop)         { throw new RuntimeException("Undefined property $prop."); }
    function __set($prop, $value) { throw new RuntimeException("Undefined property $prop."); }

    # Register a handler which is run before each worker is forked.
    function beforeFork($callback)
    {
        $this->events->on("beforeFork", $callback);
        return $this;
    }

    # Register a handler to run after a worker was forked.
    function afterFork($callback)
    {
        $this->events->on("afterFork", $callback);
        return $this;
    }

    # Initialize the config from an options array.
    protected function setOptions($options)
    {
        foreach ($options as $option => $value) {
            // Convert option_name to setOptionName
            $property = lcfirst(
                str_replace(' ', '', ucwords(
                    str_replace(array('-', '_'), ' ', strtolower($option))
                ))
            );

            if (property_exists($this, $property)) {
                $this->$property = $value;
            } else {
                throw new InvalidArgumentException("Option $option is not defined.");
            }
        }
    }
}

