<?php

define("INCLUDE_PATH", realpath(__DIR__ . "/../"));

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
    $body[] = "<pre>" . print_r($env, true). "</pre>";

    $body[] = <<<HTML
        <form method="post" action="/">
            <input name="foo" placeholder="Type something">
            <input type="submit" value="Submit">
        </form>
HTML;

    $body[] = "</body></html>";
    return array(200, array(), $body);
});

$server->start();

