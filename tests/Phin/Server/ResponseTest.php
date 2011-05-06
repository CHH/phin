<?php

namespace Phin\Test\Server;

use Phin\Server\Response;

class ResponseTest extends \PHPUnit_Framework_TestCase
{
    protected $response;

    function setUp() 
    {
        $this->response = new Response;
    }

    function testDefaultStatusIs200() 
    {
        $this->assertEquals(200, $this->response->getStatus());
    }
}
