<?php

namespace Phin\Server;

class HttpStatus
{
    /** @var int */
    protected $status = 200;
    
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

    function __construct($status = 200)
    {
        $this->status = $status;
    }

    function __toString()
    {
        try {
            return $this->status . ' ' . $this->getStatusMessage($this->status);
        } catch (InvalidArgumentException $e) {
            return '';
        }
    }

    function getStatus()
    {
        return $this->status;
    }
    
    function isSuccess()
    {
        return ($this->status >= 200 and $this->status <= 206);
    }

    function isError()
    {
        return $this->isServerError() or $this->isClientError();
    }

    function isServerError()
    {
        return ($this->status >= 500 and $this->status <= 505);
    }

    function isClientError()
    {
        return ($this->status >= 400 and $this->status <= 415);
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
