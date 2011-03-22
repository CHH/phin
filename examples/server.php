<?php

define("INCLUDE_PATH", realpath(__DIR__ . "/../lib"));

spl_autoload_register(function($class) {
    $file = INCLUDE_PATH . '/' . str_replace(array('\\', '_'), '/', $class) . ".php";

    if (!file_exists($file)) {
        return false;
    }
    return require_once($file);
});

$server = new \HTTP\Server;

$server->run(function($env) {
    $body = array();
    $body[] = "<!DOCTYPE html>";
    $body[] = "<html><head></head><body>";
    $body[] = "<h1>It Works</h1>";

    $body[] = "<h2>Environment</h2>";
    $body[] = "<pre>" . print_r($env, true). "</pre>";

    if ($env->getRequestMethod() == "POST" or $env->getRequestMethod() == "PUT") {
        $body[] = "<h2>Request Body</h2>";
        $body[] = "<pre>" . stream_get_contents($env["server.input"]) . "</pre>";
    }

    $body[] = "</body></html>";

    return array(200, array(), $body);
});

$server->start();

