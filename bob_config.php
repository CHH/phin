<?php

# Put the `bob_config.php` into the "Bob" namespace,
# otherwise you would've to call the `task` and
# `desc` functions with a `Bob\` prefix.
namespace Bob;

use FileUtils;

$pharFiles = fileList('*.php')->in(array('lib', 'bin', 'vendor'));

# The "default" task is invoked when there's no
# task explicitly given on the command line.
task('default', array('phar'));

# Note: All file paths used here should be relative to the project
# directory. Bob automatically sets the current working directory
# to the path where the `bob_config.php` resides.

desc('Compiles a executable, standalone PHAR file');
task('phar', array('composer.lock', 'test', 'bin/phin.phar'));

task('clean', function() {
    file_exists('bin/phin.phar') and unlink('bin/phin.phar');
    file_exists('bin/phin')      and unlink('bin/phin');
});

fileTask('bin/phin.phar', $pharFiles, function($task) {
    if (file_exists($task->name)) {
        unlink($task->name);
    }

    $stub = <<<'EOF'
#!/usr/bin/env php
<?php

Phar::mapPhar('phin.phar');

require 'phar://phin.phar/bin/phin.php';

__HALT_COMPILER();
EOF;

    $projectDir = \Bob::$application->projectDir;

    $phar = new \Phar($task->name, 0, basename($task->name));
    $phar->startBuffering();

    foreach ($task->prerequisites as $file) {
        $file = (string) $file;
        $phar->addFile($file, FileUtils::relativize($file, $projectDir));
    }

    $phar->setStub($stub);
    $phar->stopBuffering();

    chmod($task->name, 0555);

    println(sprintf(
        'Regenerated Archive "%s" with %d entries', basename($task->name), count($phar)
    ));
    unset($phar);
});

desc("Runs the test suite");
task("test", function($task) {
    sh('phpunit');
});

fileTask('composer.lock', array('composer.json'), function() {
    sh('composer update');
});

