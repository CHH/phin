<?php

namespace Phin\Server;

class SignalSlot
{
    /** @var SplQueue */
    protected $listeners;

    function __construct()
    {
        $this->listeners = new \SplQueue;
    }

    function connect($listener)
    {
        if (!is_callable($listener)) {
            throw new InvalidArgumentException(sprintf(
                "Listener must be a valid callback, %s given", gettype($listener)
            ));
        }

        $this->listeners->enqueue($listener);
        return $this;
    }

    function send(array $args)
    {
        $results = new \SplDoublyLinkedList;
    
        foreach ($this->listeners as $listener) {
            $r = call_user_func_array($listener, $args);
            $results->push($r);
        }

        return $results;
    }
}
