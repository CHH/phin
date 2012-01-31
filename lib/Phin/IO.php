<?php

namespace Phin;

interface IO
{
    # Writes the buffer to the device and should
    # return the number of Bytes written.
    #
    # buffer - String to be written.
    #
    # Returns number of Bytes written as Integer.
    function write($buffer);

    # Reads from the device.
    #
    # length - Number of bytes to read, optional.
    #
    # Returns the buffer as String.
    function read($length = 0);
}
