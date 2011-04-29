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

use Phin\Server\Environment,
    Phin\Server\Response;

class SignalSlot
{
    /** @var SplQueue */
    protected $listeners;

    function __construct()
    {
        $this->listeners = new \SplQueue;
    }

    /**
     * Bind a listener to a signal
     *
     * @param  callback $listener
     * @return SignalSlot
     */
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

    /**
     * Send the value to all listeners
     *
     * @param  Environment $env
     * @return SplDoublyLinkedList Return Value of each listener
     */
    function send(Environment $env)
    {
        $results = new \SplDoublyLinkedList;
    
        foreach ($this->listeners as $listener) {
            $r = call_user_func($listener, $env);
            $results->push($r);
        }

        return $results;
    }

    /**
     * Send until the return value of the filter callback is TRUE. The filter callback
     * gets passed the value to filter
     *
     * @param  Environment $env
     * @param  callback $filter
     * @return mixed
     */
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

            if ($response) {
                return $response;
            }
        }

        return false;
    }
}
