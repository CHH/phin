<?php

namespace Spark\Http\Server;

use Symfony\Component\Process;

class CgiHandler
{
    protected $bin;
    
    function __construct($bin)
    {
        if (!is_executable($bin)) {
            throw new InvalidArgumentException("CGI Executable is not executable");
        }
        $this->bin = $bin;
    }

    function __invoke(Environment $env)
    {
        $cgiEnv = $env->toArray();
        $stdIn = stream_get_contents($env->get("server.input"));
    
        $process = new Process($this->bin, $env->get("DOCUMENT_ROOT"), $cgiEnv, $stdIn);
        $process->run();
        
        // Parse response and transform it to a standard response
        return array(200, array(), $process->getOutput());
    }
}
