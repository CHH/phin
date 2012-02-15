<?php

require_once __DIR__.'/../vendor/.composer/autoload.php';

$server = new \Phin\HttpServer(array(
    'pid_file' => '/tmp/phin.pid',
    'worker_pool_size' => 4
));

$server->run(function($client) use ($server) {
    $request  = stream_get_contents($client);

    $message  = "<p>This is what I've got:</p>";
    $message .= "<pre><code>".$request."</pre></code>";

    $date = new \DateTime;

    fwrite($client, "HTTP/1.1 200 OK\r\n");
    fwrite($client, "Connection: close\r\n");
    fwrite($client, "Date: ".$date->format(\DateTime::RFC1123)."\r\n");
    fwrite($client, "Content-Type: text/html\r\n");
    fwrite($client, "Content-Length: ".strlen($message)."\r\n");
    fwrite($client, "\r\n");
    fwrite($client, $message);

    fclose($client);
});

$server->listen();
