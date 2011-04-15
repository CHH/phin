<?php
/**
 * Script environment
 *
 * @package HTTP_Server
 * @author Christoph Hochstrasser <christoph.hochstrasser@gmail.com>
 * @license MIT License
 * @copyright (c) 2011 Christoph Hochstrasser
 */
namespace Spark\Http\Server;

class Environment extends \ArrayObject
{
    function __construct()
    {
        $env = array(
            "SCRIPT_NAME" => '',
            "PATH_INFO" => '/',
            "QUERY_STRING" => '',
            "REQUEST_METHOD" => "GET",
            "REQUEST_URI" => "/",
            "SERVER_NAME" => "",
            "SERVER_PORT" => "",
            "server.url_scheme" => "http",
            "server.input" => "",
            "DOCUMENT_ROOT" => "",
            "REMOTE_ADDR" => "0.0.0.0",
            "REMOTE_PORT" => ""
        );

        parent::__construct($env);
    }

    /**
     * Sets the given key
     *
     * @param mixed $key
     * @param mixed $value
     * @return Env
     */
    function set($key, $value)
    {
        $this[$key] = $value;
        return $this;
    }

    /**
     * Returns the value of the given key, if the key is not set, returns the default
     *
     * @param  mixed $key
     * @param  mixed $default
     * @return mixed
     */
    function get($key, $default = null)
    {
        return isset($this[$key]) ? $this[$key] : $default;
    }

    function toArray()
    {
        return $this->getArrayCopy();
    }
}

