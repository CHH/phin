<?php

namespace Phin\Server\Request;

use Phin\Server\InvalidArgumentException,
    Phin\Server\Environment;

class Handler
{
    protected $callback;
    
    function __construct($callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException(sprintf(
                "Callback must be a valid PHP Callback, %s given", var_export($callback)
            ));
        }
        $this->callback = $callback;
    }
    
    function call(Environment $env)
    {
        try {
            $response = call_user_func($this->callback, $env);
        } catch (\Exception $e) {
            $response = array(500, array(), (string) $e);
        }
        
        if (false === $response) {
            $response = array(500);
        }
        
        return $response;
    }
}
