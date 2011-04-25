<?php

namespace Phin\Test\Server\Request;

use Phin\Server\Request\StandardParser,
    Phin\Server\Environment;

class StandardParserTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;
    protected $message;
    protected $env;
    
    function setUp()
    {
        $this->parser = new StandardParser;
        $this->env = new Environment;
        
        $this->message = "GET /foo/bar?foo=bar&bar=baz HTTP/1.1\r\n"
                       . "Host: www.example.com:3000\r\n"
                       . "X-Multiline-Header: Foo Bar\r\n"
                       . " Bar Baz\r\n"
                       . "\tBaz Foo\r\n"
                       . "Accept: */*\r\n"
                       . "X-Foo-Bar: Hello World\r\n\r\n";
                       
        $this->parser->parse($this->message, $this->env);
    }
    
    /**
     * @expectedException \Phin\Server\MalformedMessageException
     */
    function testThrowsExceptionIfStatusLineIsNotFound()
    {
        $this->parser->parse("Hello World\r\nFoo: Bar\r\n\r\n", $this->env);
    }
    
    function testParsesRequestMethod()
    {
        $this->assertEquals("GET", $this->env->get("REQUEST_METHOD"));
    }
    
    function testParsesRequestUri()
    {
        $this->assertEquals("/foo/bar?foo=bar&bar=baz", $this->env->get("REQUEST_URI"));
    }
    
    function testParsesHeaders()
    {
        $this->assertEquals("*/*", $this->env->get("HTTP_ACCEPT"));
        $this->assertEquals("Hello World", $this->env->get("HTTP_X_FOO_BAR"));
    }
    
    /**
     * @depends testParsesHeaders
     */
    function testParsesServerNameAndServerPortFromHostHeader()
    {
        $this->assertEquals("www.example.com", $this->env->get("SERVER_NAME"));
        $this->assertEquals(3000, $this->env->get("SERVER_PORT"));
    }
    
    function testHeadersCanSpanMultipleLines()
    {
        $this->assertEquals("Foo BarBar BazBaz Foo", $this->env->get("HTTP_X_MULTILINE_HEADER"));
    }
    
    function testParsesQueryStrings()
    {
        $this->assertEquals("foo=bar&bar=baz", $this->env->get("QUERY_STRING"));
    }
}
