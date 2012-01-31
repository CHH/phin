<?php

namespace Phin;

class Config
{
    # Server Document Root.
    var $documentRoot;

    # Turn on debug messages. Does nothing currently.
    var $debugMode = false;

    # Path for temporary files.
    var $tempDir;

    var $socket;

    # Server Hostname as String.
    var $host = "0.0.0.0";

    # Port Number as Integer.
    var $port = 3000;

    var $workerPoolSize = 4;

    function __construct(array $options = array())
    {
        $this->tempDir = sys_get_temp_dir();
        empty($options) or $this->setOptions($options);
    }

    function isDebugModeEnabled()
    {
        return (bool) $this->debugMode;
    }

    protected function setOptions(array $options)
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

