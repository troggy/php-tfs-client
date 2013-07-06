<?php

namespace troggy\PhpTFSClient\Tests;

use troggy\PhpTFSClient\TFSClient;

/**
 * @author Kosta Korenkov <7r0ggy@gmail.com>
 */
class TFSClientWithCLIStubbed extends TFSClient
{
    private $outputFromCLI;
    private $returnCode;

    static function createWithOutput(array $outputFromCLI, $returnCode)
    {
        $stub = new TFSClientWithCLIStubbed("","","","","");
        $stub->outputFromCLI = $outputFromCLI;
        $stub->returnCode = $returnCode;
        return $stub;
    }

    static function createWithCredentials($server, $user, $password)
    {
        $stub = new TFSClientWithCLIStubbed($server, $user, $password, "", "");
        $stub->outputFromCLI = "";
        $stub->returnCode = 1;
        return $stub;
    }


    protected function acceptCLILicenseAgreement() {}

    protected function createTempWorkspace() {}

    function __destruct() {}

    protected function nativeExec($cmd, &$ret_code)
    {
        $ret_code = $this->returnCode;
        return $this->outputFromCLI;
    }
}