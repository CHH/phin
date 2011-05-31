<?php

set_include_path(__DIR__ . "/../vendor" 
    . PATH_SEPARATOR . get_include_path());

require_once "Net/Server.php";

require_once __DIR__ . "/../vendor/Symfony/Component/ClassLoader/UniversalClassLoader.php";

$classLoader = new \Symfony\Component\ClassLoader\UniversalClassLoader;

$classLoader->registerNamespaces(array(
    "Phin"   => __DIR__,
    "Symfony" => __DIR__ . "/../vendor"
));

$classLoader->register();
