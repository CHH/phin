<?php
/**
 * A simple request handler which starts a CGI process
 *
 * @author Christoph Hochstrasser <christoph.hochstrasser@gmail.com>
 * @license MIT License
 * @copyright (c) Christoph Hochstrasser
 */

namespace Phin\Server\Handler;

use Symfony\Component\Process\Process,
    Phin\Server\Environment,
    Phin\Server\HttpStatus;

class Cgi
{
    /**
     * CGI executable
     * @var string
     */
    protected $bin;

    /**
     * Custom Environment variables which get passed to the CGI script
     * @var array
     */
    protected $customEnv = array();
    
    /**
     * Constructor
     *
     * @param string $bin Path to the CGI Executable which gets called for incoming requests
     * @param array  $env Custom Environment variables
     */
    function __construct($bin, array $env = array())
    {
        if (!is_executable($bin)) {
            throw new InvalidArgumentException("CGI Executable is not executable");
        }
        $this->bin = $bin;
        $this->customEnv = $env;
    }

    /**
     * Process the incoming request
     *
     * @param  Environment $env The parsed request data
     * @return array Response
     */
    function __invoke(Environment $env)
    {
        $cgiEnv = array_merge($env->toArray(), $this->customEnv);

        $stdIn = null;
        if ($env["REQUEST_METHOD"] == "POST" or $env["REQUEST_METHOD"] == "PUT") {
            $stdIn  = stream_get_contents($env->get("server.input"));
        }
        
        $cgiEnv["GATEWAY_INTERFACE"] = "CGI/1.1";
        $cgiEnv["SERVER_PROTOCOL"] = "HTTP/1.1";
        $cgiEnv["SERVER_SOFTWARE"] = "Spark_Http_Server";
        $cgiEnv["REDIRECT_STATUS"] = 200;

        if ("WIN" == strtoupper(substr(PHP_OS, 0, 3))) {
            $cgiEnv["SystemRoot"] = "C:\\Windows";
        }
        
        $cgiEnv["CONTENT_LENGTH"] = isset($cgiEnv["HTTP_CONTENT_LENGTH"]) 
            ? $cgiEnv["HTTP_CONTENT_LENGTH"] : 0;

        $cgiEnv["CONTENT_TYPE"] = isset($cgiEnv["HTTP_CONTENT_TYPE"])
            ? $cgiEnv["HTTP_CONTENT_TYPE"] : "application/x-www-form-urlencoded";
        
        $process = new Process($this->bin, $env->get("DOCUMENT_ROOT"), $cgiEnv, $stdIn);
        $process->run();
        
        $output = $process->getOutput();

        if ("HTTP" != substr($output, 0, 4)) {
            $output = "HTTP/1.1 200 OK\r\n" . $output;
        }
        
        return $output;
    }
}
