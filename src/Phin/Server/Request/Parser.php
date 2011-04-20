<?php
/**
 * Interface for HTTP Message parsers
 *
 * @package Phin
 * @author Christoph Hochstrasser <christoph.hochstrasser@gmail.com>
 * @license MIT License
 * @copyright (c) 2011 Christoph Hochstrasser
 */
namespace Phin\Server\Request;

interface Parser
{
    /**
     * Parse the given raw Data into a valid Server Environment
     *
     * @param  string $raw Raw HTTP Message
     * @param  \HTTP\Server\Env $env
     */
    function parse($raw, \Phin\Server\Environment $env);
}

