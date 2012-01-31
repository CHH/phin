<?php

namespace Phin\Request;

use Phin\Environment;

# Interface for request parsers.
interface Parser
{
    # Parses the request into the given Environment instance.
    #
    # raw - The received request as string.
    # env - Environment, which holds all request data.
    #
    # Returns nothing.
    function parse($raw, Environment $env);
}

