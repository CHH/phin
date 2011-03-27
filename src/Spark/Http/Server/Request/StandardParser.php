<?php

namespace Spark\Http\Server\Request;

use \Spark\Http\Server\Environment;

class StandardParser implements Parser
{
    function parse($raw, Environment $env)
    {
        $raw = trim($raw);
        $lines = explode("\r\n", $raw);

        if (!preg_match("'([^ ]+) ([^ ]+) (HTTP/[^ ]+)'", $lines[0], $matches)) {
            return false;
        }

        $env->setRequestMethod($matches[1]);
        $this->parseRequestUri($matches[2], $env);
        $version = $matches[3];

        for ($i = 1; $i < count($lines); $i++) {
            // In HTTP 1.1 a headers can span multiple lines. This is indicated by
            // a single space or tab in front of the line
            if ("1.1" == $version
                and (substr($line[$i], 0, 1) == ' ' or substr($line[$i], 0, 1) == "\t")
                and isset($headerName)
            ) {
                $env[$headerName] .= trim($line[$i]);
                continue;
            }

            if (preg_match("'([^: ]+): (.+)'", $lines[$i], $header)) {
                $headerName = "HTTP_" . strtoupper(str_replace('-', '_', $header[1]));
                $env[$headerName] = $header[2];
            }
        }

        if (!empty($env["HTTP_HOST"])) {
            list($host, $port) = explode(':', $env["HTTP_HOST"]);
            $env->setServerName($host);
            $env->setServerPort($port);
        }

        return $env;
    }

    protected function parseRequestUri($uri, Environment $env)
    {
        // TODO: Revise this Regular Expression, as it does not recognize more than
        // one url param, e.g. if URL is "/foo/bar.html?foo=bar&bar=baz" then
        // it only captures "foo=bar" and not "foo=bar&bar=baz"
        if (!preg_match("'([^?]*)(?:\?([^#]*))?(?:#.*)? *'", $uri, $matches)) {
            return false;
        }
        
        $path = $matches[1];
        
        $env->setPathInfo(dirname($path));
        $env->setScriptName(basename($path));
        $env->setQueryString(isset($matches[2]) ? $matches[2] : "");
    }
}

