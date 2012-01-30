<?php

require_once __DIR__.'/../vendor/.composer/autoload.php';

$server = new \Phin\Server(array(
    'socket' => '/tmp/phin.sock',
));

$server->listen();
