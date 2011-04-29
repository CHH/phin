<?php

/**
 * A simple HTTP Server with a Rack-like Protocol
 *
 * @package Phin
 * @author Christoph Hochstrasser <christoph.hochstrasser@gmail.com>
 * @license MIT License
 * @copyright (c) 2011 Christoph Hochstrasser
 */

namespace Phin\Server\Handler;

use SplFileInfo,
    finfo,
    Phin\Server\Environment,
    Phin\Server\Response;

class StaticFiles
{
    /** @var finfo */
    protected $fileInfo;

    /**
     * Constructor
     */
    function __construct()
    {
        $this->fileInfo = new finfo(FILEINFO_MIME_TYPE);
    }

    function __invoke(Environment $env)
    {
        $docRoot = $env["DOCUMENT_ROOT"];
        $file    = $docRoot . $env["PATH_INFO"] . '/' . $env["SCRIPT_NAME"];
        
        if (!is_file($file)) {
            return false;
        }
        if (!is_readable($file)) {
            return array(500);
        }

        $file = new SplFileInfo($file);
        $contentType = $this->fileInfo->file((string) $file);

        $size = $file->getSize();

        $headers = array(
            "content-length" => $size,
            "content-type" => $contentType
        );

        if ($env["REQUEST_METHOD"] !== "HEAD") {
            $body = fopen($file->getRealPath(), "rb");
        }
        
        return new Response(200, $headers, $body);
    }
}
