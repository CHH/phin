<?php

require_once realpath(__DIR__ . "/../") . "/src/_autoload.php";

use Phin\Server,
    Phin\Server\FileHandler;

$server = new Phin\Server(array(
    "document_root" => __DIR__ . "/public"
));

$server->run(new FileHandler)->listen();
