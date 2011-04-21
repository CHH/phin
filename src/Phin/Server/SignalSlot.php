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
}
