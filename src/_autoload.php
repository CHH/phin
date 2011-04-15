<?php

require_once realpath(__DIR__ . "/../vendor/Symfony/Component/UniversalClassLoader.php");

$classLoader = new \Symfony\Component\UniversalClassLoader;

$classLoader->registerNamespaces(array(
    "Spark" => __DIR__,
    "Symfony" => realpath(__DIR__ . "/../vendor")
));

$classLoader->register();
