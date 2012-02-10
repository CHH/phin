<?php

require_once __DIR__.'/../vendor/.composer/autoload.php';

$server = new \Phin\HttpServer([
    'pid_file' => '/tmp/phin.pid'
]);

$server->run(function($io) use ($server) {
    $server->log->info('Got a request!');

    $message = "This is what I've got:\n";

    while ($chunk = $io->read(16384)) {
        if (false === $chunk) {
            $server->log->info("Failure while reading");
            $io->close();
            return;
        }
        $message .= $chunk;
    }

    $date = new \DateTime;

    $io->write("HTTP/1.1 200 OK\r\n");
    $io->write("Content-Type: text/html\r\n");
    $io->write("Content-Length: ".strlen($message)."\r\n");
    $io->write("Connection: close\r\n");
    $io->write("Date: ".$date->format(\DateTime::RFC1123)."\r\n");
    $io->write("\r\n");
    $io->write($message);

    $io->close();
});

$server->listen();
