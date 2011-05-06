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
    
    protected $index = array(
        "index.htm",
        "index.html"
    );
    
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
        
        $file = new SplFileInfo($file);
        
        if (!$file->isFile()) {
            if ($file->isDir()) {
                $file = $this->findIndex($file);   
            }
            if (false === $file) {
                return false;
            }
        }
        
        if (!$file->isReadable()) {
            return array(500);
        }

        $contentType = $this->fileInfo->file($file->getRealPath());

        $size = $file->getSize();

        $headers = array(
            "content-length" => $size,
            "content-type" => $contentType
        );
        
        $body = '';
        if ($env["REQUEST_METHOD"] !== "HEAD") {
            $body = fopen($file->getRealPath(), "rb");
        }
        
        return new Response(200, $headers, $body);
    }
    
    protected function findIndex(SplFileInfo $directory) {
        $path = $directory->getRealPath();
        
        foreach ($this->index as $index) {
            if (file_exists($path . DIRECTORY_SEPARATOR . $index)) {
                return new SplFileInfo($path . DIRECTORY_SEPARATOR . $index);
            }
        }
        return false;
    }
}
