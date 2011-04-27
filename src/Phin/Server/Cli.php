<?php

namespace Phin\Server;

use Phin\Server\Handler\StaticFiles,
    Phin\Server\Handler\Cgi;

class Cli
{
    protected $indexScript;
    protected $documentRoot;
    protected $phpCgi;
    protected $port = 3000;

    function __construct()
    {
    }
    
    function setDocumentRoot($docRoot)
    {
        if (!is_dir($docRoot)) {
            throw new InvalidArgumentException("Document Root is not a valid Directory");
        }
        $this->documentRoot = $docRoot;
        return $this;
    }
    
    function setPort($port)
    {
        $this->port = $port;
        return $this;
    }
    
    function setIndexScript($indexScript)
    {
        $this->indexScript = $indexScript;
        return $this;
    }
    
    function setPhpExecutable($php)
    {
        $this->phpCgi = $php;
        return $this;
    }
    
    function run()
    {
        if (empty($this->indexScript) and !empty($this->documentRoot)) {
            $this->indexScript = $this->documentRoot . "/index.php";
        
        } else if (empty($this->documentRoot)) {
            $this->documentRoot = dirname($this->indexScript);
        }
        
        if (!is_readable($this->indexScript)) {
            printf("FAILURE: Index script %s was not found!\r\n", $this->indexScript);
            exit(2);
        }
        
        $server = new \Phin\Server(array(
            "document_root" => $this->documentRoot,
            "port" => $this->port
        ));
        
        $staticHandler = new StaticFiles;
        
        $phpCgi = $this->findPhpCgi();
        $cgiHandler = new Cgi($phpCgi, array(
            "SCRIPT_FILENAME" => $this->indexScript
        ));
        
        $server->run($staticHandler)
               ->run($cgiHandler);
        
        $config = $server->getConfig();
        
        $version = \Phin\Server::VERSION;
        $socket = sprintf("%s:%d", $config->getHost(), $this->port);
        
        print <<<EOL
>>> Welcome to Phin v$version!
>>> Using CGI Handler with $phpCgi.
>>> Listening on $socket.
>>> Terminate with [ CTRL ] + [ C ].\r\n\r\n
EOL;
        
        $server->listen();
    }
    
    /**
     * Search for a PHP CGI Executable
     *
     * @return string The Path to the CGI Executable
     */
    protected function findPhpCgi()
    {
        if (is_executable($this->phpCgi)) {
            return $this->phpCgi;
        }
    
        $suffix = '';

        if ("WIN" == strtoupper(substr(PHP_OS, 0, 3))) {
            $searchPaths = array(
                "C:\\php", "C:\\Program Files (x86)", 
                "C:\\Program Files", "C:\\xampp\\php"
            );
            $suffix = ".exe";
        } else {
            $searchPaths = array("/usr/bin", "/usr/local/bin");
        }

        foreach ($searchPaths as $path) {
            if (is_executable($path . "/php-cgi" . $suffix)) {
                return $path . "/php-cgi" . $suffix;
            }
        }
    }
}
