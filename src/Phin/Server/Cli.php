<?php

namespace Phin\Server;

use RuntimeException,
    Phin\Server,
    Phin\Server\Handler\StaticFiles,
    Phin\Server\Handler\Cgi,
    Symfony\Component\Console\Input,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption;

class Cli
{
    /** @var InputDefinition */
    protected $options;
    
    function __construct()
    {
        $this->options = new Input\InputDefinition(array(
            new InputArgument(
                "action", InputArgument::REQUIRED, "Possible values: start"
            ),
            new InputOption(
                "document_root", 'd', 
                InputOption::VALUE_OPTIONAL, 
                "Directory where the server should look for files", 
                getcwd()
            ),
            new InputOption(
                "index", 'i', 
                InputOption::VALUE_OPTIONAL,
                "PHP File which should receive all requests for files which do not exist."
            ),
            new InputOption(
                "port", 'p', 
                InputOption::VALUE_OPTIONAL, 
                "Port on which the server should listen", 
                4020
            ),
            new InputOption(
                "host", 'H', 
                InputOption::VALUE_OPTIONAL, 
                "Host on which the server should be bound", 
                '0.0.0.0'
            ),
            new InputOption(
                "php_cgi", 'P',
                InputOption::VALUE_OPTIONAL,
                "Override Path of PHP CGI Executable",
                $this->findPhpCgi()
            ),
            new InputOption(
                "help", 'h', 
                InputOption::VALUE_NONE,
                "Display this help"
            )
        ));
    }
    
    function run()
    {
        try {
            $input = new Input\ArgvInput(null, $this->options);

            if ($action = $input->getArgument("action") !== "start") {
                throw new RuntimeException("Action $action is not implemented");
            }
            if ($input->getOption("help")) {
                throw new RuntimeException("");
            }
        } catch (RuntimeException $e) {
            print $this->displayHelp();
            return;
        }

        $config = new Server\Config;
        
        $documentRoot = $input->getOption("document_root");
        if ($documentRoot != getcwd() and !$this->isAbsolute($documentRoot)) {
            $documentRoot = realpath(getcwd() . DIRECTORY_SEPARATOR . $documentRoot);
        }
        $config->setDocumentRoot($documentRoot);

        if (!$input->hasOption("index")) {
            if (is_readable($documentRoot . DIRECTORY_SEPARATOR . "index.php")) {
                $indexScript = $documentRoot . DIRECTORY_SEPARATOR . "index.php";
            }
        } else {
            $indexScript = $input->getOption("index");
            if (!$this->isAbsolute($indexScript)) {
                $indexScript = realpath(getcwd() . DIRECTORY_SEPARATOR . $indexScript);
            }
        }

        $config->setPort($input->getOption("port"));
        $config->setHost($input->getOption("host"));

        $server = new Server($config);
        
        $staticHandler = new StaticFiles;
        $server->run($staticHandler);
        
        if (!empty($indexScript)) {
            $cgiHandler = new Cgi($input->getOption("php_cgi"), array(
                "SCRIPT_FILENAME" => $indexScript
            ));
            $server->run($cgiHandler);
        }
        
        $version = Server::VERSION;
        $socket  = sprintf("%s:%d", $config->getHost(), $config->getPort());
        
        print <<<EOL
>>> Welcome to Phin v$version!
>>> Using CGI Handler with {$input->getOption('php_cgi')}.
>>> Listening on $socket.
>>> Terminate with [ CTRL ] + [ C ].\r\n\r\n
EOL;
        
        $server->listen();
    }

    protected function displayHelp()
    {   
        $synopsis = $this->options->getSynopsis();
        $bin = basename($_SERVER["SCRIPT_FILENAME"]);

        $help = "Usage: $bin $synopsis\r\n";

        $help .= "\r\nArguments:\r\n";
        foreach ($this->options->getArguments() as $argument) {
            $help .= sprintf("\t%s: %s\r\n", $argument->getName(), $argument->getDescription());
        }
        
        $help .= "\r\nOptions:\r\n";
        foreach ($this->options->getOptions() as $option) {
            $help .= sprintf(
                "\t--%s, -%s: %s\r\n", 
                $option->getName(), 
                $option->getShortcut(),
                $option->getDescription()
            );
        }

        return $help;
    }
    
    /**
     * Search for a PHP CGI Executable
     *
     * @return string The Path to the CGI Executable
     */
    protected function findPhpCgi()
    {
        $suffix = '';

        if ("WIN" == strtoupper(substr(PHP_OS, 0, 3))) {
            $searchPaths = array(
                "C:\\php", "C:\\Program Files (x86)", 
                "C:\\Program Files", "C:\\xampp\\php"
            );
            $suffix = ".exe";
        } else {
            $searchPaths = array("/usr/bin", "/usr/local/bin");
        }

        foreach ($searchPaths as $path) {
            if (is_executable($path . "/php-cgi" . $suffix)) {
                return $path . "/php-cgi" . $suffix;
            }
        }
    }
    
    protected function isAbsolute($path) 
    {
        if ("WIN" == strtoupper(substr(PHP_OS, 0, 3))) {
            return preg_match($path, "/[a-zA-Z]\:\\\\/");
        } else if ($path[0] == '/') {
            return true;
        }
        return false;
    }
}
