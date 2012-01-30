<?php

namespace Phin\Server\Request;

use Symfony\Component\HttpFoundation\Request,
    Phin\Server\Request\MalformedMessageException;

class StandardParser implements Parser
{
    function parse($raw, Request $req)
    {
        $raw = trim($raw);
        $lines = explode("\r\n", $raw);

        if (!preg_match("'([^ ]+) ([^ ]+) (HTTP/[^ ]+)'", $lines[0], $matches)) {
            throw new MalformedMessageException("Header Line not found");
        }

        $requestUri = $matches[2];
        $version = $matches[3];

        if ($version < "HTTP/1.0") {
            throw new MalformedMessageException("HTTP Version $version is not supported");
        }

        if (".." == substr($requestUri, 0, 2)) {
            $requestUri = substr($requestUri, 2);
        }

        $req->server->set("REQUEST_METHOD", $matches[1]);
        $req->server->set("REQUEST_URI", $requestUri);

        $this->parseRequestUri($requestUri, $req);

        for ($i = 1; $i < count($lines); $i++) {
            // In HTTP 1.1 a headers can span multiple lines. This is indicated by
            // a single space or tab in front of the line
            if (($lines[$i][0] == ' ' or $lines[$i][0] == "\t")
                and isset($headerName)
            ) {
                $env[$headerName] .= trim($lines[$i]);
                continue;
            }

            if (preg_match("'([^: ]+): (.+)'", $lines[$i], $header)) {
                $headerName = "HTTP_" . strtoupper(str_replace('-', '_', $header[1]));
                $env[$headerName] = $header[2];
            }
        }

        if (!empty($env["HTTP_HOST"])) {
            if (false !== strpos($env["HTTP_HOST"], ':')) {
                list($host, $port) = explode(':', $env["HTTP_HOST"]);
            } else {
                $host = $env["HTTP_HOST"];
                $port = 80;
            }
            $env->set("SERVER_NAME", $host);
            $env->set("SERVER_PORT", (int) $port);
        }

        return $env;
    }

    protected function parseRequestUri($uri, Request $req)
    {
        if (false !== $pos = strpos($uri, "?")) {
            $req->server->set("QUERY_STRING", substr($uri, $pos + 1));
            $uri = substr($uri, 0, $pos);
        }

        $req->server->set("PATH_INFO", dirname($uri));
        $env->server->set("SCRIPT_NAME", basename($uri));
    }
}

