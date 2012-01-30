<?php

namespace Phin\Server\Request;

use Symfony\Component\HttpFoundation\Request;

# Interface for request parsers.
interface Parser
{
    # Parses the raw request and fills the given request object
    # with the data.
    #
    # raw     - The received request as string.
    # request - A request instance, where the parsed request data
    #           should be set.
    #
    # Returns nothing.
    function parse($raw, Request $request);
}

