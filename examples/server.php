<?php

define("INCLUDE_PATH", realpath(__DIR__ . "/../src"));

require_once INCLUDE_PATH . "/_autoload.php";

$server = new \Phin\Server;

$server->run(function($env) {
    $body = array();
    $body[] = "<!DOCTYPE html>";
    $body[] = "<html><head></head><body>";
    $body[] = "<h1>It Works</h1>";

    $body[] = "<h2>Environment</h2>";
    $body[] = "<pre>" . print_r($env, true). "</pre>";

    if ($env["REQUEST_METHOD"] == "POST" or $env["REQUEST_METHOD"] == "PUT") {
        $body[] = "<h2>Request Body</h2>";
        $body[] = "<pre>" . stream_get_contents($env["server.input"]) . "</pre>";
    }

    $body[] = "</body></html>";

    return array(200, array(), $body);
});

$server->listen();

