<?php

namespace Spark\Http\Server;

class HandlerQueue
{
    protected $queue;

    function __construct()
    {
        $this->queue = new \SplQueue;
    }

    function __invoke(Environment $env) 
    {
        foreach ($this->queue as $handler) {
            $response = $handler->call($env);

            if (is_array($response)) {
                $status = isset($response[0]) ? $response[0] : 200;

                if ($status >= 200 and $status < 400) {
                    return $response;
                }
            } else if ($response) {
                return $response;
            }
        }

        $response = array(404);
        return $response;
    }
    
    function add($handler)
    {
        $this->queue->enqueue(new Request\Handler($handler));
        return $this;
    }
}
