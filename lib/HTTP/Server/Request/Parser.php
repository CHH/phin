<?php
/**
 * Interface for HTTP Message parsers
 *
 * @package HTTP_Server
 * @author Christoph Hochstrasser <christoph.hochstrasser@gmail.com>
 * @license MIT License
 * @copyright (c) 2011 Christoph Hochstrasser
 */
namespace HTTP\Server\Request;

interface Parser
{
    /**
     * Parse the given raw Data into a valid Server Environment
     *
     * @param  string $raw Raw HTTP Message
     * @param  \HTTP\Server\Env $env
     */
    function parse($raw, \HTTP\Server\Env $env);
}

