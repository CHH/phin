<?php

namespace HTTP\Server;

class Env extends \ArrayObject
{
    function __construct()
    {
        $env = array(
            "SCRIPT_NAME" => '',
            "PATH_INFO" => '/',
            "QUERY_STRING" => '',
            "REQUEST_METHOD" => "GET",
            "SERVER_NAME" => "",
            "SERVER_PORT" => "",
            "server.url_scheme" => "http",
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

    function setInputStream($stream)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException(sprintf(
                "Input stream is not a valid resource, %s given", gettype($stream)
            ));
        }
        $this["server.input"] = $stream;
        return $this;
    }

    function getInputStream()
    {
        return $this["server.input"];
    }

    function setScriptName($scriptName)
    {
        $this["SCRIPT_NAME"] = $scriptName;
        return $this;
    }

    function getScriptName()
    {
        return $this["SCRIPT_NAME"];
    }

    function setRequestMethod($method)
    {
        $method = strtoupper($method);

        if (!in_array($method, array("GET", "POST", "PUT", "DELETE", "HEAD", "OPTIONS", "TRACE", "CONNECT"))) {
            throw new InvalidArgumentException("$method is not a valid HTTP Method");
        }

        $this["REQUEST_METHOD"] = $method;
    }

    function getRequestMethod()
    {
        return $this["REQUEST_METHOD"];
    }

    function setPathInfo($pathInfo)
    {
        $this["PATH_INFO"] = $pathInfo;
    }

    function getPathInfo()
    {
        return $this["PATH_INFO"];
    }

    function setQueryString($queryString)
    {
        $this["QUERY_STRING"] = $queryString;
    }

    function getQueryString()
    {
        return $this["QUERY_STRING"];
    }

    function setServerName($serverName)
    {
        $this["SERVER_NAME"] = $serverName;
    }

    function getServerName()
    {
        return $this["SERVER_NAME"];
    }

    function setServerPort($port)
    {
        $this["SERVER_PORT"] = $port;
    }

    function getServerPort()
    {
        return $this["SERVER_PORT"];
    }
}

