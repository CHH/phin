<?php
/**
 * A simple HTTP Server with a Rack-like Protocol
 *
 * @author Christoph Hochstrasser <christoph.hochstrasser@gmail.com>
 * @license MIT License
 * @copyright (c) 2011 Christoph Hochstrasser
 */

namespace Spark\Http;

require_once "Net/Server.php";

use Net_Server,
    Net_Server_Driver,
    DateTime,
    \Spark\Http\Server\Environment;

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
     * HTTP Protocol version
     */
    protected $httpVersion = "1.1";

    /**
     * Parser for the request HTTP message
     * 
     * @var \HTTP\Server\Server\Request\Parser
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

        /*
         * Use the Sequential driver by default on Windows, because
         * PCNTL (and therefore Forking) is not supported on Windows.
         */
        if (strtoupper(substr(PHP_OS, 0, 3)) == "WIN") {
            $this->driverName = "Sequential";
        }
    }

    /**
     * Sets a callback which should be run on every request and gets passed an
     * Environment as first argument
     *
     * The callback should have the signature: 
     * <code>function(\Spark\Http\Server\Environment $env);</code>
     * The callback should return in the form of:
     * <code>array($status, $arrayOfResponseHeaders, $body)</code>
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
        $this->handler = $callback;
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
        
        $this->driver->start();
    }

    /**
     * Stop the server from listening for requests
     */
    function stopListening()
    {
        if (!$this->driver instanceof \Net_Server_Driver) {
            throw new Server\UnexpectedValueException("Server was not started");
        }
        $this->driver->shutDown();
    }

    /**
     * Gets called by the driver when a request gets in
     */
    function onReceiveData($client = 0, $rawMessage)
    {
        try {
            $env = new Environment;
            $env->setServerName($this->host);
            $env->setServerPort($this->port);

            // Parse Request Message Head
            $this->getParser()->parse($rawMessage, $env);

            // Retrieve the Request Body on POST or PUT requests
            $method = $env->getRequestMethod();
            if ("POST" == $method or "PUT" == $method) {
                $this->parseEntityBody($client, $env);
            }

            // Call the Request Handler with the Server Environment as sole argument
            if (is_callable($this->handler)) {
                $response = call_user_func($this->handler, $env);
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
        $this->sendResponse($client, $env, $response);
        $this->driver->closeConnection($client);
    }

    /**
     * Sends the response
     *
     * @param int         $client   ID of the Client
     * @param Environment $env      The request environment
     * @param array       $response The Response, an array of ($status, $headers, $body)
     */
    protected function sendResponse($client = 0, Environment $env, array $response)
    {
        $status  = empty($response[0]) ? 200     : $response[0];
        $headers = empty($response[1]) ? array() : $response[1];
        $body    = empty($response[2]) ? ''      : $response[2];

        $headers = array_merge($this->defaultHeaders, $headers);
        $driver  = $this->driver;

        // Send message head
        $driver->sendData($client, sprintf(
            "HTTP/%s %d %s\r\n", $this->httpVersion, $status, $this->resolveStatusCode($status)
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
        
        if ("HEAD" == $env->getRequestMethod()) {
            $body = null;
        }
        
        // Send headers
        foreach ($headers as $header => $value) {
            $header = $this->normalizeHeader($header);
            $driver->sendData($client, sprintf("%s: %s\r\n", $header, $value));
        }
        
        $driver->sendData($client, $headers ? "\r\n" : "\r\n\r\n");

        // Send the body
        if (is_string($body)) {
            $driver->sendData($client, $body);

        } else if (is_array($body)) {
            $driver->sendData($client, join($body, ""));

        // Send the file if the body is a resource handle
        } else if (is_resource($body)) {
            while (!feof($body)) {
                $data = fread($body, 4096);
                $driver->sendData($client, $data);
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

