<?php

namespace troggy\PhpTFSClient;

use Logger;

/**
 * PHP wrapper for Team Foundation Server command-line client (Team Explorer Everywhere)
 *
 * @author Kosta Korenkov <7r0ggy@gmail.com>
*/
class TFSClient
{

    private $server;

    private $user;

    private $pass;

    private $workspacename;

    private $debug;

    private $cliPath;

    private $historyParser;

    private $log;
    
    function __construct($server, $user, $pass, $cliPath, $workspace_prefix = '')
    {
        $this->log = Logger::getLogger('SCM');

        $this->server = $server;
        $this->user = $user;
        $this->pass = $pass;

        $this->workspacename = $workspace_prefix . "_" . mt_rand();
        $this->cliPath = $cliPath;
        $this->debug = array();

        $this->acceptCLILicenseAgreement();
        $this->createTempWorkspace();
        $this->historyParser = new TFSHistoryParser();
    }

    function __destruct()
    {
        $this->deleteWorkspace($this->workspacename);
    }


    /**
     *  Returns properties for the files and folders addressed by $itemspec.
     *
     * @param string  $itemspec Item specification addressing files or folders
     * @return Array  List of properties, false on error
     */
    function getProperties($itemspec)
    {
        $output = $this->execCLI("info", $itemspec);
        if (!$output) {
            return false; // todo: throw exception from execCLI and remove this block
        }
        $properties = array();
        foreach ($output as $value) {
            if (TFSClient::startsWith(trim($value), 'Last modified:')) {
                $properties['last-mod'] = trim(substr($value, strpos($value, ": ") + 1));
            } else if (TFSClient::startsWith(trim($value), 'Size:')) {
                $properties['length'] = trim(substr($value, strpos($value, ": ") + 1));
            } else if (TFSClient::startsWith(trim($value), 'File type:')) {
                $properties['content-type'] = trim(substr($value, strpos($value, ": ") + 1));
            }
        }

        return $properties;
    }

    /**
     *  Returns all the files and folders addressed by $itemspec of the version $version of the repository.
     *
     * @param $folder
     * @param string  $version Versionspec. Default value is latest revision
     * @internal param string $itemspec Item specification addressing files or folders
     * @return Array  List of files, false on error
     */
    function getDirectoryFiles($folder, $version = 'T')
    {
        $output = $this->execCLI("dir", "$folder /version:$version");

        $files = array();
        $path = "";
        if (sizeof($output) == 1) { // assuming we have empty dir
            return $files;
        }
        foreach ($output as $value) {
            if (trim($value) == '') {
                break;
            }
            $file = array();
            $filename = $value;
            if (TFSClient::startsWith($filename, '$/')) {
                $path = substr($filename, 0, strlen($filename) - 1);
            } else {
                if (TFSClient::startsWith($filename, '$')) {
                    $file['type'] = 'directory';
                    $filename = substr($filename, 1);
                } else {
                    $file['type'] = 'file';
                }
                $file['path'] = $path . "/" . $filename;
                $file['name'] = $filename;
                //$file = array_merge($file, $this->getProperties($path . "/" . $filename));
                array_push($files, $file);
            }
        }

        return $files;
    }

    /**
     *  Returns file contents
     *
     * @param    string     $file File pathname
     * @param integer|string $version Versionspec. Default value is latest revision
     * @return    string    File content and information, false on error, or if a
     *                  directory is requested
     */
    function getFile($file, $version = 'T')
    {
        $output = $this->execCLI("print", "$file /version:$version");
        if (!$output) {
            return false; // todo: throw exception from execCLI and remove this block
        }
        return join("\n", $output);
    }

    /**
     *
     * @param string $itemspec Item specification addressing files or folders
     * @param int|string $startFromVersion version to start from, default is latest
     * @param int $limit number of changesets to get, default is unlimited
     * @throws TFSClientException
     * @return Array Respository Logs
     */
    function getHistory($itemspec, $startFromVersion = 'T', $limit = -1)
    {
        $args = "$itemspec /recursive /format:xml /version:$startFromVersion";

        if ($limit > 0) {
            $args .= " /stopafter:$limit";
        }

        $output = $this->execCLI("history", $args);
        if (sizeof($output) == 1) // assuming no files for given itemspec found
        {
            throw new TFSClientException($output[0]);
        }
        return $this->historyParser->parse(join('', $output));
    }

    /**
     *  Returns workspace details
     *
     * @param    string $workspacename Workspace name
     * @throws   TFSClientException if no matching workspace found
     * @return   Array    Workspace details, false on error
     */
    function getWorkspaceProperties($workspacename)
    {
        $output = $this->execCLI("workspaces", $workspacename, false);

        if (!$output) {
            return false; // todo: throw exception from execCLI and remove this block
        }

        if (sizeof($output) == 1 && TFSClient::startsWith($output[0], 'No workspace matching')) {
            throw new TFSClientException($output[0]);
        }

        $workspace = array();
        $properties = preg_split("/\s+/", $output[3]);
        assert(sizeof($properties) >= 3);
        $workspace['name'] = $properties[0];
        $workspace['owner'] = $properties[1];
        $workspace['computer'] = $properties[2];
        if (isset($properties[3])) {
            $workspace['comment'] = join(" ", array_slice($properties, 3));
        }
        return $workspace;
    }

    /**
     *  Creates new workspace
     *
     * @param    string     $workspacename Workspace name
     * @return    boolean    true on success, false on error
     */
    function createWorkspace($workspacename)
    {
        $output = $this->execCLI("workspace", "/new " . $workspacename, false);
        return $output and $output[0] == "Workspace '$workspacename' created.";
    }

    /**
     *  Deletes workspace
     *
     * @param    string     $workspacename Workspace name
     * @return    boolean    true on success, false on error
     */
    function deleteWorkspace($workspacename)
    {
        $output = $this->execCLI("workspace", "/delete " . $workspacename, false);
        return $output and $output[0] == "Workspace '$workspacename' deleted.";
    }

    /**
     *  Maps local working folder to repository path
     *
     * @param    string     $serverPath Path on the server
     * @param  string  $localPath Path to local folder
     * @return    boolean    true on success, false on error
     */
    function mapWorkingFolder($serverPath, $localPath)
    {
        $output = $this->execCLI("workfold", "/map $serverPath $localPath");
        return $output
            and $this->debug("Working folder '$localPath' has been mapped to '$serverPath'");
    }

    function getDebugLog()
    {
        return $this->debug;
    }

    function setHistoryParser($historyParser)
    {
        $this->historyParser = $historyParser;
    }

    protected function acceptCLILicenseAgreement()
    {
        exec($this->cliPath . "/tf eula /accept 2>&1", $output); //todo: not sure if it is okay to silently accept TEE license agreement like this
    }

    protected function error($debug_output)
    {
        if (is_array($debug_output)) {
            array_merge($this->debug, $debug_output);
            $this->log->error(join('\n', $debug_output));
        } else {
            array_push($this->debug, $debug_output);
            $this->log->error($debug_output);
        }
        return true;
    }

    protected function debug($debug_output)
    {
        if (is_array($debug_output)) {
            array_merge($this->debug, $debug_output);
            $this->log->debug(join(PHP_EOL, $debug_output));
        } else {
            array_push($this->debug, $debug_output);
            $this->log->debug($debug_output);
        }
        return true;
    }

    protected function isWorkspaceExists($workspacename)
    {
        try {
            $this->getWorkspaceProperties($workspacename);
        } catch (TFSClientException $e) {
            return false;
        }
        return true;
    }

    protected function nativeExec($cmd, &$ret_code)
    {
        exec($cmd, $output, $ret_code);
        return $output;
    }

    protected function execCLI($command, $option, $useWorkspace = true)
    {
        $cmd = $this->cliPath . "/tf $command /server:$this->server" . ($useWorkspace ? " /workspace:" . $this->workspacename : "") . " /login:$this->user,$this->pass $option /noprompt 2>&1";
        $this->debug("Executing: $cmd");
        $output = $this->nativeExec($cmd, $ret_code);
        $this->debug("Return code: " . ($ret_code <> 0 ? "ERROR" : "OK"));
        if ($ret_code <> 0) {
            $this->error("Output: " . PHP_EOL . $output[0]);
            throw new TFSClientException($output[0]);
        } else {
            $this->debug("Output: " . PHP_EOL . join(PHP_EOL, $output));
        }
        return $output;
    }

    protected function createTempWorkspace()
    {
        if (!$this->isWorkspaceExists($this->workspacename)) {
            $this->debug("No temp workspace exists on current machine. Creating new one..");
            $this->createWorkspace($this->workspacename);
        }
    }

    private static function startsWith($haystack, $needle)
    { //todo: this should not be here. Move to some common place or better use standard library
        return !strncmp($haystack, $needle, strlen($needle));
    }

}

?>