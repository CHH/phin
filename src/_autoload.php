<?php
namespace src;
$_map = array (
  'Spark\\Http\\Server' => __DIR__ . DIRECTORY_SEPARATOR . 'Spark/Http/Server.php',
  'Spark\\Http\\Server\\Exception' => __DIR__ . DIRECTORY_SEPARATOR . 'Spark/Http/Server/Exception.php',
  'Spark\\Http\\Server\\Request\\StandardParser' => __DIR__ . DIRECTORY_SEPARATOR . 'Spark/Http/Server/Request/StandardParser.php',
  'Spark\\Http\\Server\\Request\\Parser' => __DIR__ . DIRECTORY_SEPARATOR . 'Spark/Http/Server/Request/Parser.php',
  'Spark\\Http\\Server\\InvalidArgumentException' => __DIR__ . DIRECTORY_SEPARATOR . 'Spark/Http/Server/InvalidArgumentException.php',
  'Spark\\Http\\Server\\UnexpectedValueException' => __DIR__ . DIRECTORY_SEPARATOR . 'Spark/Http/Server/UnexpectedValueException.php',
  'Spark\\Http\\Server\\MalformedMessageException' => __DIR__ . DIRECTORY_SEPARATOR . 'Spark/Http/Server/MalformedMessageException.php',
  'Spark\\Http\\Server\\Environment' => __DIR__ . DIRECTORY_SEPARATOR . 'Spark/Http/Server/Environment.php',
  'Spark\\Http\\Server\\RuntimeException' => __DIR__ . DIRECTORY_SEPARATOR . 'Spark/Http/Server/RuntimeException.php',
);
spl_autoload_register(function($class) use ($_map) {
    if (array_key_exists($class, $_map)) {
        require_once $_map[$class];
    }
});

