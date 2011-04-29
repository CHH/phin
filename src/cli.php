<?php

namespace Phin\Server\Cli;

require_once __DIR__ . "/_autoload.php";

use \Phin\Server\Cli;

function is_absolute($path)
{
    if ("WIN" == strtoupper(substr(PHP_OS, 0, 3))) {
        if (preg_match("/^[a-zA-Z]\:\\\\/", $path)) {
            return true;
        }
        return false;
    }
    return '/' == $path[0];
}

if (empty($argv[1])) {
    throw new \Phin\Server\UnexpectedValueException("First argument must be either the"
        . " Index Script or the Document Root, nothing given.");
}

$cli = new Cli;
$cwd = getcwd();

if (!is_absolute($argv[1])) {
    $argv[1] = $cwd . DIRECTORY_SEPARATOR . $argv[1];
}

if (empty($argv[2])) {
    if (is_dir($argv[1])) {
        $cli->setDocumentRoot(realpath($argv[1]));
        
    } else if (file_exists($argv[1])) {
        $cli->setIndexScript(realpath($argv[1]));
    }
} else {
    $cli->setDocumentRoot(realpath($argv[1]));
    
    $indexScript = is_absolute($argv[2]) ? $argv[2] : $cwd . DIRECTORY_SEPARATOR . $argv[2];
    $cli->setIndexScript(realpath($indexScript));
}

$cli->run();
