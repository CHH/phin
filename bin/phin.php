<?php

namespace Phin;

require_once realpath(__DIR__ . "/../") . "/src/_autoload.php";

use \Phin\Server,
    \Phin\Server\HandlerQueue,
    \Phin\Server\Handler\StaticFiles,
    \Phin\Server\Handler\Cgi;

class PhpServer
{
    function __invoke()
    {
        $root = realpath(__DIR__ . "/../");
    
        $server = new Server(array(
            "document_root" => $root . "/examples/public"
        ));

        $cgiHandler = new Cgi($this->findPhpCgi(), array(
            "SCRIPT_FILENAME" => $root . "/examples/hello_world.php",
            "TMP" => "C:\\temp"
        ));
        
        $handlers = new HandlerQueue;
        $handlers->add(new StaticFiles)
                 ->add($cgiHandler);

        $server->run($handlers);
        $server->listen();
    }

    /**
     * Search for a PHP CGI Executable
     *
     * @return string The Path to the CGI Executable
     */
    protected function findPhpCgi()
    {
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

$server = new PhpServer;
$server();
