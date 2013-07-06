<?php

namespace troggy\PhpTFSClient;

use Exception;

/**
 * @author Kosta Korenkov <7r0ggy@gmail.com>
 */
class TFSClientException extends Exception
{

    function __construct($message)
    {
        parent::__construct($message, 1);
    }

}
