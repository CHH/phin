<?php

require_once __DIR__ . "/../vendor/Symfony/Component/ClassLoader/UniversalClassLoader.php";

$classLoader = new \Symfony\Component\ClassLoader\UniversalClassLoader;

$classLoader->registerNamespaces(array(
    "Phin"   => __DIR__,
    "Symfony" => __DIR__ . "/../vendor"
));

$classLoader->register();
