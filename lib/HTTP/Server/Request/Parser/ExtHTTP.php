<?php
/**
 * A HTTP Message parser which uses Pecl_Http
 *
 * @package HTTP_Server
 * @author Christoph Hochstrasser <christoph.hochstrasser@gmail.com>
 * @license MIT License
 * @copyright (c) 2011 Christoph Hochstrasser
 */
namespace HTTP\Server\Request\Parser;

if (!extension_loaded("http")) {
    throw new \HTTP\Server\RuntimeException("PECL_Http must be installed to use the ExtHTTP parser");
}

use HTTP\Server\Env,
    HTTP\Server\Request\Parser,
    HttpMessage;

class ExtHTTP implements Parser
{
    function parse($raw, Env $env)
    {
        try {
            $message = HttpMessage::factory($raw);

        } catch (\HttpMalformedHeadersException $e) {
            throw new \HTTP\Server\MalformedMessageException("Malformed Message", 0, $e);
        }

        $method = $message->getRequestMethod();

        $env->setRequestMethod($method);

        $requestUrl = $message->getRequestUrl();

        // Parse Query Parameters
        if ($pos = strpos($requestUrl, '?')) {
            $env->setQueryString(substr($requestUrl, $pos + 1));
            $requestUrl = substr($requestUrl, 0, $pos);
        }

        $env->setScriptName(basename($requestUrl));
        $env->setPathInfo(dirname($requestUrl));

        foreach ($message->getHeaders() as $header => $value) {
            $key = "HTTP_" . strtoupper(str_replace('-', '_', $header));

            // The value of the "Host" header should be prefered for SERVER_NAME and SERVER_PORT
            if ("HTTP_HOST" == $key) {
                list($host, $port) = explode(':', $value);
                $env->setServerName($host);
                $env->setServerPort($port);
            }

            $env[$key] = $value;
        }

        if ("PUT" == $method or "POST" == $method) {
            $body = $message->getBody();

            $input = fopen("data:/text/plain," . $body, "rb");
            $env->setInputStream($input);
        }

        return $env;
    }
}

