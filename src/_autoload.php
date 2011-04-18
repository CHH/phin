<?php

define("VENDOR_PATH", realpath(__DIR__ . "/../vendor"));

require_once VENDOR_PATH . "/Symfony/Component/ClassLoader/UniversalClassLoader.php";

$classLoader = new \Symfony\Component\ClassLoader\UniversalClassLoader;

$classLoader->registerNamespaces(array(
    "Spark"   => __DIR__,
    "Symfony" => VENDOR_PATH
));

$classLoader->register();
