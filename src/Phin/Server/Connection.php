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

use Net_Server_Driver as Driver;

class Connection
{
    /**
     * Server Config
     *
     * @var \Phin\Server\Config
     */
    protected $config;
    
    /**
     * Parser for the request HTTP message
     * 
     * @var \Phin\Server\Request\Parser
     */
    protected $parser;
    
    /** @var Net_Server_Driver */
    protected $driver;
    
    /** @var object */
    public $signals;
    
    /**
     * Default response headers
     *
     * @var array
     */
    protected $defaultHeaders = array(
        "x-powered-by" => "Phin, the phun, PHP Web Server",
        "connection"   => "close"
    );
    
    function __construct(Config $config = null)
    {
        empty($config) ?: $this->setConfig($config);
        $this->setParser(new Request\StandardParser);
        
        $this->signals = (object) array(
            "handle" => new SignalSlot
        );
    }
    
    function setConfig(Config $config)
    {
        $this->config = $config;
        return $this;
    }

    function setDriver(Driver $driver)
    {
        $this->driver = $driver;
        return $this;
    }
    
    /**
     * Returns the request parser, uses by default the Pecl_Http Extension
     *
     * @return Server\Request\Parser
     */
    function getParser()
    {
        return $this->parser;
    }

    /**
     * Set the Parser which parses the Raw Request Message
     *
     * @param  Server\Env\Parser $parser
     * @return Server
     */
    function setParser(Request\Parser $parser)
    {
        $this->parser = $parser;
        return $this;
    }
    
    function onReceiveData($client, $rawMessage)
    {
        try {
            $env = $this->createEnvironment($client);
            
            // Parse Request Message Head
            $this->getParser()->parse($rawMessage, $env);

            // Retrieve the Request Body on POST or PUT requests
            $method = $env->get("REQUEST_METHOD");
            if ("POST" == $method or "PUT" == $method) {
                $this->parseEntityBody($client, $env);
            }

            $response = $this->signals->handle->sendUntilResponse($env);

            if (!$response) {
                $response = new Response(404);
            }
        } catch (Server\MalformedMessageException $e) {
            print $e->getPrevious();
            $response = new Response(400);

        } catch (\Exception $e) {
            $response = new Response($e->getCode() ?: 500);
            print $e;
        }

        if (is_array($response)) {
            $response = Response::fromArray($response);
        }
        
        $this->sendResponse($client, $env, $response);
        $this->driver->closeConnection($client);
    }
    
    /**
     * Sends the response
     *
     * @param int         $client   ID of the Client
     * @param Environment $env      The request environment
     * @param string      $response The Response, an array of ($status, $headers, $body)
     */
    protected function sendResponse($client = 0, Environment $env, $response)
    {
        $driver = $this->driver;
        $driver->sendData($client, (string) $response);
        $driver->closeConnection($client);
    }
    
    /**
     * Retrieve the Request's body directly from the Socket
     *
     * It's mainly a bad hack.
     *
     * @param int $clientId
     * @param Server\Env $env Server Environment Variables
     */
    protected function parseEntityBody($clientId = 0, Environment $env)
    {
        $driver = $this->driver;
        $socket = $driver->clientFD[$clientId];

        if (empty($env["HTTP_CONTENT_LENGTH"])) {
            return;
        }
        $contentLength = $env["HTTP_CONTENT_LENGTH"];

        $bufferSize = 1024;
        $data = $driver->_readLeftOver;
        
        $driver->_readLeftOver = null;

        // Read the rest of the body directly from the socket if not in _readLeftOver
        while (strlen($data) < $contentLength) {
            if ($bufferSize > $contentLength) {
                $bufferSize = $contentLength;
            }

            $buffer = @socket_read($socket, $bufferSize, PHP_BINARY_READ);
            $data .= $buffer;

            $contentLength = $contentLength - $bufferSize;

            if ($contentLength == 0 or !$buffer) {
                break;
            }
        }

        if (strlen($data) != $env["HTTP_CONTENT_LENGTH"]) {
            throw new Server\MalformedMessageException(sprintf(
                "Value of Content-Length Header does not match actual Content-Length: "
                . "%d Bytes expected, %d Bytes received",
                $env["HTTP_CONTENT_LENGTH"], strlen($data)
            ));
        }

        $env->set("server.input", fopen("data://text/plain," . $data, "rb"));
    }
    
    protected function createEnvironment($client = 0)
    {
        $config = $this->config;
    
        $env = new Environment;
        $env->set("SERVER_NAME", $config->getHost());
        $env->set("SERVER_PORT", $config->getPort());
        $env->set("DOCUMENT_ROOT", $config->getDocumentRoot());
        $env->set("TMP", $config->getTempDir());
        
        $clientInfo = $this->driver->getClientInfo($client);
        $env->set("REMOTE_ADDR", $clientInfo["host"]);
        $env->set("REMOTE_PORT", $clientInfo["port"]);
        $env->set("REQUEST_TIME", $clientInfo["connectOn"]);

        return $env;
    }
}
