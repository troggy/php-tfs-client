<?php

namespace troggy\PhpTFSClient;

use Exception;


/**
 * @author Kosta Korenkov <7r0ggy@gmail.com>
 */
class XMLParseException extends Exception
{
    private $errorString;
    private $lineNumber;

    function __construct($errorString, $lineNumber = null)
    {
        $this->errorString = $errorString;
        $this->lineNumber = $lineNumber;
    }

    function __toString()
    {
        return sprintf("XML error: %s at line %d", $this->errorString, $this->lineNumber);
    }

}
