<?php

/**
 * A simple HTTP Server with a Rack-like Protocol
 *
 * @package Phin
 * @author Christoph Hochstrasser <christoph.hochstrasser@gmail.com>
 * @license MIT License
 * @copyright (c) 2011 Christoph Hochstrasser
 */

namespace Phin\Server;

class SignalSlot
{
    /** @var SplQueue */
    protected $listeners;

    function __construct()
    {
        $this->listeners = new \SplQueue;
    }

    function bind($listener)
    {
        if (!is_callable($listener)) {
            throw new InvalidArgumentException(sprintf(
                "Listener must be a valid callback, %s given", gettype($listener)
            ));
        }

        $this->listeners->enqueue($listener);
        return $this;
    }

    function send(Environment $env)
    {
        $results = new \SplDoublyLinkedList;
    
        foreach ($this->listeners as $listener) {
            $r = call_user_func($listener, $env);
            $results->push($r);
        }

        return $results;
    }

    function sendUntil(Environment $env, $filter)
    {
        foreach ($this->listeners as $listener) {
            $response = call_user_func($listener, $env);

            if (true === $filter($response)) {
                return $response;
            }
        }

        return false;
    }

    function sendUntilResponse(Environment $env)
    {
        foreach ($this->listeners as $listener) {
            $response = call_user_func($listener, $env);

            if ((is_array($response) or is_string($response)) and !empty($response)) {
                return $response;
            }
        }

        return false;
    }
}
