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
    DateTime,
    Phin\Server\Environment,
    Phin\Server\Request\Handler,
    Phin\Server\HttpStatus;

class Server
{   
    /** @var Net_Server_Driver */
    protected $driver;

    /** @var string */
    protected $documentRoot;
    
    /**
     * Hostname or IP Address for listening
     *
     * @var string
     */
    protected $host = "127.0.0.1";

    /**
     * TCP Port, extended permissions are needed to run on a port < 1024 ("Well known Port")
     *
     * @var int
     */
    protected $port = "3000";

    /**
     * Default response headers
     *
     * @var array
     */
    protected $defaultHeaders = array(
        "x-powered-by" => "Spark_Http_Server",
        "connection"   => "close"
    );
    
    /**
     * Parser for the request HTTP message
     * 
     * @var \Phin\Server\Request\Parser
     */
    protected $parser;

    /**
     * Request Handler
     *
     * @var callback
     */
    protected $handler;
    
    /**
     * Name of the Net_Server Driver. You may use the "Fork" Driver on *nix Systems for
     * better Performance.
     *
     * @var string
     */
    protected $driverName = "Fork";

    protected $debugMode = false;
    
    /**
     * Constructor
     *
     * @param array $config Array of options
     */
    function __construct(array $config = array())
    {   
        /*
         * Use the Sequential driver by default on Windows, because
         * PCNTL (and therefore Process Forking) is not supported on Windows.
         */
        if (strtoupper(substr(PHP_OS, 0, 3)) == "WIN") {
            $this->driverName = "Sequential";
        }
        
        if ($config) {
            $this->setConfig($config);
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
        $this->handler = new Handler($callback);
        return $this;
    }

    /**
     * Server starts listening for requests
     */
    function listen($port = null)
    {
        $port = $port ?: $this->port;
        
        $this->driver = Net_Server::create($this->driverName, $this->host, $port);
        $this->driver->setEndCharacter("\r\n\r\n");
        $this->driver->setCallbackObject($this);
        $this->driver->setDebugMode($this->debugMode);
        
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

    protected function createEnvironment($client = 0)
    {
        $env = new Environment;
        $env->set("SERVER_NAME", $this->host);
        $env->set("SERVER_PORT", $this->port);
        $env->set("DOCUMENT_ROOT", $this->documentRoot);
        
        $clientInfo = $this->driver->getClientInfo($client);
        $env->set("REMOTE_ADDR", $clientInfo["host"]);
        $env->set("REMOTE_PORT", $clientInfo["port"]);
        $env->set("REQUEST_TIME", $clientInfo["connectOn"]);

        return $env;
    }
    
    /**
     * Gets called by the driver when a request gets in
     */
    function onReceiveData($client = 0, $rawMessage)
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

            if (null !== $this->handler) {
                $response = $this->handler->call($env);
            }
        } catch (Server\MalformedMessageException $e) {
            print $e->getPrevious();
            $response = array(400);

        } catch (\Exception $e) {
            $status = $e->getCode() ?: 500;
            $response = array($status);
            print $e;
        }
        $this->sendResponse($client, $env, $response);
        $this->driver->closeConnection($client);
    }
    
    /**
     * Sends the response
     *
     * @param int          $client   ID of the Client
     * @param Environment  $env      The request environment
     * @param array|string $response The Response, an array of ($status, $headers, $body)
     */
    protected function sendResponse($client = 0, Environment $env, $response)
    {
        $driver = $this->driver;

        // Handler returned simple response
        if (is_array($response)) {
            $status  = empty($response[0]) ? 200     : $response[0];
            $headers = empty($response[1]) ? array() : $response[1];
            $body    = empty($response[2]) ? ''      : $response[2];

        // Handler returned raw response message, send and return early
        } else if (is_string($response)) {
            $this->driver->sendData($client, $response);
            $this->driver->closeConnection($client);
            return;
        }

        $headers = $this->normalizeHeaders($headers);
        $status  = new HttpStatus($status);
        
        // Send message head
        $driver->sendData($client, sprintf(
            "HTTP/1.1 %s\r\n", $status
        ));
        
        // Append GMT date/time
        $date = new DateTime;
        $date->setTimezone(new \DateTimeZone("GMT"));
        
        $headers["Date"] = $date->format(DateTime::RFC1123);

        /*
         * Build headers for entity body
         */
        if (!empty($body)) {
            // Default Content-Type to text/html
            isset($headers["Content-Type"]) ?: $headers["Content-Type"] = "text/html";

            if (is_string($body)) {
                $headers["Content-Length"] = strlen($body);
            } else if (is_array($body)) {
                $headers["Content-Length"] = array_reduce($body, function($sum, $value) {
                    return $sum + strlen($value);
                }, 0);
            }
        }
        
        if ("HEAD" == $env->get("REQUEST_METHOD")) {
            $body = null;
        }
        
        $this->sendHeaders($client, $headers);
        $driver->sendData($client, $headers ? "\r\n" : "\r\n\r\n");
        
        if ($body) {
            $this->sendBody($client, $body);
        }

        $driver->closeConnection($client);
    }

    protected function normalizeHeaders(array $headers)
    {
        $normalize = function($header) {
            $header = str_replace(array('-', '_'), ' ', $header);
            $header = ucwords($header);
            $header = str_replace(' ', '-', $header);
            return $header;
        };

        $return = array();
        
        foreach ($headers as $header => &$value) {
            $return[$normalize($header)] = $value;
        }
        return $return;
    }
    
    protected function sendHeaders($client = 0, array $headers = array())
    {
        $driver  = $this->driver;
        $headers = array_merge($this->defaultHeaders, $headers);

        // Send headers
        foreach ($headers as $header => $value) {
            $driver->sendData($client, sprintf("%s: %s\r\n", $header, $value));
        }
    }

    protected function sendBody($client = 0, $body)
    {
        $driver = $this->driver;
    
        // Send the body
        if (is_string($body)) {
            $driver->sendData($client, $body);

        } else if (is_array($body)) {
            foreach ($body as $b) {
                $driver->sendData($client, $b);
            }

        // Send the file if the body is a resource handle
        } else if (is_resource($body)) {
            while (!feof($body)) {
                $data = fread($body, 4096);
                $driver->sendData($client, $data);
            }
            fclose($body);
        }
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

    function setDebugMode($debugMode = true)
    {
        $this->debugMode = $debugMode;
    }
    
    function setDocumentRoot($docRoot)
    {
        if (!is_dir($docRoot)) {
            throw new Server\InvalidArgumentException("Document root does not exist");
        }
        $this->documentRoot = $docRoot;
    }

    function setHost($host)
    {
        $this->host = $host;
    }

    function setDriverName($name)
    {
        $this->driverName = $name;
    }

    /**
     * Returns the request parser, uses by default the Pecl_Http Extension
     *
     * @return Server\Request\Parser
     */
    function getParser()
    {
        if (null === $this->parser) {
            $this->parser = new Server\Request\StandardParser;
        }
        return $this->parser;
    }

    /**
     * Set the Parser which parses the Raw Request Message
     *
     * @param  Server\Env\Parser $parser
     * @return Server
     */
    function setParser(Server\Request\Parser $parser)
    {
        $this->parser = $parser;
        return $this;
    }

    /**
     * Converts the array of options to respective Setter names and calls them
     * with the option value
     *
     * e.g. for the option "host" the Setter setHost() would be called, with the
     * option value as argument
     *
     * @param  array $config
     * @return Server
     */
    protected function setConfig(array $config)
    {
        foreach ($config as $option => $value) {
            // Convert option_name to setOptionName
            $method = "set" . str_replace(' ', '', ucwords(str_replace(array('-', '_'), ' ', strtolower($option))));

            if (!is_callable(array($this, $method))) {
                throw new Server\UnexpectedValueException("$option is not defined");
            }
            $this->{$method}($value);
        }
        return $this;
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
}

