<?php

namespace Phin\Server;

use Net_Server_Driver as Driver,
    DateTime;

class Connection
{
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
    
    function __construct(Driver $driver)
    {
        $this->driver = $driver;
        $this->setParser(new Request\StandardParser);
        
        $this->signals = (object) array(
            "handle" => new SignalSlot
        );
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
}
