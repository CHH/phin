<?php

require_once realpath(__DIR__ . "/../") . "/src/_autoload.php";

use Spark\Http\Server,
    Spark\Http\Server\FileHandler;

$server = new Spark\Http\Server(array(
    "document_root" => __DIR__ . "/public"
));

$server->run(new FileHandler)->listen();
