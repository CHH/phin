<?php

namespace Phin;

use DateTime,
    Phin\InvalidArgumentException;

class Response
{
    /** @var int */
    protected $status = 200;

    /**
     * Response body
     *
     * @var array|string|resource
     */
    protected $body = '';

    /**
     * HTTP Response headers
     *
     * @var array
     */
    protected $headers = array();

    /**
     * List of HTTP status codes and their respective messages
     *
     * @var array
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

    static function fromArray(array $response)
    {
        $status  = isset($response[0]) ? $response[0] : 200;
        $headers = isset($response[1]) ? $response[1] : array();
        $body    = isset($response[2]) ? $response[2] : '';
    
        return new static($status, $headers, $body);
    }
    
    function __construct($status = 200, array $headers = array(), $body = '')
    {
        $date = new DateTime;
        $date->setTimezone(new \DateTimeZone("GMT"));
        
        $this->headers = array(
            "Date" => $date->format(DateTime::RFC1123),
            "Connection" => "Close"
        );
    
        $this->status  = $status;
        $this->headers = array_merge($this->headers, $headers);
        
        $this->setBody($body);
    }

    function toString()
    {
        $body = '';

        if (is_string($this->body) or is_callable(array($this->body, "__toString"))) {
            $body = (string) $this->body;

        } else if (is_array($this->body) or $this->body instanceof \Traversable) {
            foreach ($this->body as $line) {
                $body .= $line;
            }

        } else if (is_resource($this->body)) {
            $body = stream_get_contents($this->body);
            fclose($this->body);
        }

        $this->headers['Content-Length'] = strlen($body);

        $message = $this->headersToString().$body;
        return $message;
    }

    function __toString() 
    {
        try {
            return $this->toString();
        } catch (\Exception $e) {}
    }

    function getHeaders()
    {
        return $this->headers;
    }

    function setBody($body)
    {
        if (!is_string($body) and !is_array($body) and !is_resource($body)) {
            throw new InvalidArgumentException(sprintf(
                "Body must be either a String, an Array or an open Resource Handle, "
                . "%s given",
                gettype($body)
            ));
        }
        $this->body = $body;
        return $this;
    }
    
    function getBody()
    {
        return $this->body;
    }
    
    function getStatus()
    {
        return $this->status;
    }

    protected function getNormalizedHeaders()
    {
        $normalize = function($header) {
            $header = str_replace(array('-', '_'), ' ', $header);
            $header = ucwords($header);
            $header = str_replace(' ', '-', $header);
            return $header;
        };

        $return = array();
        
        foreach ($this->headers as $header => &$value) {
            $return[$normalize($header)] = $value;
        }
        return $return;
    }

    function headersToString()
    {
        $headerString  = sprintf(
            "HTTP/1.1 %d %s\r\n", $this->status, $this->getStatusMessage($this->status)
        );
        
        if (!$this->headers) {
            return $headerString . "\r\n";
        }
    
        $headers = $this->getNormalizedHeaders();
        
        foreach ($headers as $header => &$value) {
            $headerString .= $header . ': ' . $value . "\r\n";
        }

        $headerString .= "\r\n";
        return $headerString;
    }
    
    /**
     * Returns the Message for the given HTTP Status Code
     *
     * @param  int $code
     * @return string
     */
    protected function getStatusMessage($code)
    {
        if (!is_numeric($code)) {
            throw new InvalidArgumentException(sprintf(
                "Code must be a number, %s given", gettype($code)
            ));
        }

        if (empty($this->statusCodes[$code])) {
            throw new InvalidArgumentException("Code $code is not defined");
        }
        
        return $this->statusCodes[$code];
    }
}
