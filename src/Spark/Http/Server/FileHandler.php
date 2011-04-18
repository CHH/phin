<?php

namespace Spark\Http\Server;

use SplFileInfo,
    finfo;

class FileHandler
{
    /** @var finfo */
    protected $fileInfo;

    function __construct()
    {
        $this->fileInfo = new finfo;
    }

    function __invoke(Environment $env)
    {
        $docRoot = $env["DOCUMENT_ROOT"];
        $file    = $docRoot . $env["PATH_INFO"] . '/' . $env["SCRIPT_NAME"];
        
        if (!is_file($file)) {
            return array(404);
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
        
        $body = fopen((string) $file, "rb");
        
        return array(200, $headers, $body);
    }
}
