<?php
/**
 * A simple HTTP Server with a Rack-like Protocol
 *
 * @author Christoph Hochstrasser <christoph.hochstrasser@gmail.com>
 * @license MIT License
 * @copyright (c) 2011 Christoph Hochstrasser
 */

namespace HTTP;

require_once "Net/Server.php";

use Net_Server,
    Net_Server_Driver,
    HTTP\Server\Env;

/**
 * @todo Write own Net Drivers
 * @todo Write own Request Parsers and parse while reading from socket to correctly
 * capture Request Bodies
 */
class Server
{
    /**
     * list of HTTP status codes
     * @var array $_statusCodes
     */
    protected $statusCodes = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoriative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Overloaded',
        503 => 'Gateway Timeout',
        505 => 'HTTP Version not supported',
        507 => 'Insufficient Storage'
    );

    /** @var Net_Server_Driver */
    protected $driver;

    /** @var string */
    protected $documentRoot;

    /**
     * Hostname or IP Address for listening
     */
    protected $host = "127.0.0.1";

    /**
     * TCP Port, extended permissions are needed to run on a port < 1024 ("Well known Port")
     *
     * @var int
     */
    protected $port = "3000";

    /**
     * HTTP Protocol version
     */
    protected $httpVersion = "1.1";

    /**
     * @var \HTTP\Server\Env\Parser
     */
    protected $parser;

    /**
     * Request Handler
     *
     * @var callback
     */
    protected $app;

    /**
     * Name of the Net_Server Driver to use, defaults to the Sequential driver, 
     * as it is cross-platform. You may use the "Fork" Driver on *nix Systems for
     * better Performance.
     * 
     * @var string
     */
    protected $driverName = "Sequential";
    
    /**
     * Constructor
     *
     * @param array $config Array of options
     */
    function __construct(array $config = array())
    {
        if ($config) {
            $this->setConfig($config);
        }
    }

    /**
     * Sets a callback which should be run on every request and gets passed an
     * Environment as first argument
     *
     * The callback should have the signature: <code>function(\HTTP\Server\Env $env);</code>
     * The callback should return in the form of:
     * <code>array("status" => $statusCode, "body" => $responseBody, $headers => $arrayOfResponseHeaders)</code>
     *
     * @param  callback $callback
     * @return Server
     */
    function run($callback)
    {
        if (!is_callable($callback)) {
            throw new Server\InvalidArgumentException(sprintf(
                "run() expects a valid callback, %s given.", gettype($callback)
            ));
        }
        $this->app = $callback;
        return $this;
    }

    /**
     * Server starts listening for requests
     */
    function start()
    {
        $this->getDriver()->start();
    }

    /**
     * Stop the server from listening for requests
     */
    function shutdown()
    {
        $this->getDriver()->shutDown();
    }

    /**
     * Gets called by the driver when a request gets in
     */
    function onReceiveData($client = 0, $rawMessage)
    {
        try {
            $env = new Env;
            $env->setServerName($this->host);
            $env->setServerPort($this->port);
            
            $this->getParser()->parse($rawMessage, $env);
            
            $method = $env->getRequestMethod();
            
            if ("POST" == $method or "PUT" == $method) {
                $this->parseRequestBody($client, $env);
            }
            
            if (is_callable($this->app)) {
                $response = call_user_func($this->app, $env);
            } else {
                $response = array(404);
            }

            if (false === $response) {
                $response = array(500);
            }
        } catch (Server\MalformedMessageException $e) {
            print $e->getPrevious();
            $response = array(400);

        } catch (\Exception $e) {
            $status = $e->getCode() ?: 500;
            $response = array($status);
            print $e;
        }
        $this->sendResponse($client, $response);

        $this->getDriver()->closeConnection($client);
    }

    /**
     * Sends the response
     *
     * @param int $client ID of the Client
     * @param array $response The Response array,
     */
    protected function sendResponse($client = 0, array $response)
    {
        $status  = empty($response[0]) ? 200     : $response[0];
        $headers = empty($response[1]) ? array() : $response[1];
        $body    = empty($response[2]) ? ''      : $response[2];

        $headers = array_merge(array(
            "x-powered-by" => "PEAR Net_HTTP2",
            "connection" => "close"
        ), $headers);

        $driver = $this->getDriver();

        // Send Response head
        $driver->sendData($client, sprintf(
            "HTTP/%s %d %s\r\n", $this->httpVersion, $status, $this->resolveStatusCode($status)
        ));

        // Append date/time
        $format = ini_get('y2k_compliance') ? 'D, d M Y' : 'l, d-M-y';
        $headers["Date"] = gmdate($format .' H:i:s \G\M\T', time());

        /*
         * Append content length
         */
        if (is_string($body)) {
            $headers["Content-Length"] = strlen($body);
        } else if (is_array($body)) {
            $headers["Content-Length"] = array_reduce($body, function($sum, $value) {
                return $sum + strlen($value);
            }, 0);
        }

        // Send headers
        foreach ($headers as $header => $value) {
            $header = $this->normalizeHeader($header);
            $driver->sendData($client, sprintf("%s: %s\r\n", $header, $value));
        }

        $driver->sendData($client, "\r\n\r\n");

        // Send the body
        if (is_string($body)) {
            $driver->sendData($client, $body);

        } else if (is_array($body)) {
            $driver->sendData($client, join($body, ""));

        // Send the file if the body is a resource handle
        } else if (is_resource($body)) {
            while (!feof($body)) {
                $data = fread($body, 4096);
                $driver->sendData($clientId, $data);
            }
            fclose($body);
        }

        $driver->closeConnection($client);
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

    function setHttpVersion($version)
    {
        $this->httpVersion = $version;
    }

    function setHost($host)
    {
        $this->host = $host;
    }

    function setDocumentRoot($documentRoot)
    {
        $this->documentRoot = $documentRoot;
    }

    function setDriverName($name)
    {
        $this->driverName = $name;
    }

    /**
     * Retrieve an instance of our TCP/IP Server stack
     *
     * @return Net_Server_Driver
     */
    function getDriver()
    {
        if (null === $this->driver) {
            $this->driver = Net_Server::create($this->driverName, $this->host, $this->port);
            $this->driver->setEndCharacter("\r\n\r\n");
            $this->driver->setCallbackObject($this);
        }
        return $this->driver;
    }

    /**
     * Set the driver used to communicate with the client
     *
     * @param  Net_Server_Driver
     * @return Server
     */
    function setDriver(Net_Server_Driver $driver)
    {
        $this->driver = $driver;
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
    function setParser(Server\Env\Parser $parser)
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
        }
        return $this;
    }

    /**
     * Retrieve the Request's body directly from the Socket
     */
    protected function parseRequestBody($clientId = 0, \HTTP\Server\Env $env)
    {
        $socket = $this->getDriver()->clientFD[$clientId];

        if (empty($env["HTTP_CONTENT_LENGTH"])) {
            return;
        }
        $contentLength = $env["HTTP_CONTENT_LENGTH"];
        
        $bufferSize = 1024;
        $data  = '';

        while (true) {
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
        
        $env->setInputStream(fopen("data://text/plain," . $data, "rb"));
    }
    
    protected function normalizeHeader($header)
    {
        $header = str_replace(array('-', '_'), ' ', $header);
        $header = ucwords($header);
        $header = str_replace(' ', '-', $header);
        return $header;
    }

    /**
     * Returns the Message for the given HTTP Status Code
      *
     * @param  int $code
     * @return string
     */
    protected function resolveStatusCode($code)
    {
        if (!is_numeric($code)) {
            throw new Server\InvalidArgumentException(sprintf(
                "Code must be a number, %s given", gettype($code)
            ));
        }

        if (empty($this->statusCodes[$code])) {
            throw new Server\InvalidArgumentException("Code $code is not defined");
        }
        return $this->statusCodes[$code];
    }
}

