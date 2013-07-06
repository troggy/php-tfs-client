<?php

namespace troggy\PhpTFSClient\Tests;

include_once "TFSClientWithCLIStubbed.php";

use PHPUnit_Framework_TestCase;
use ReflectionProperty;

class TFSClientTest extends PHPUnit_Framework_TestCase
{

    /**
     * @test
     */
    function shouldAssignDifferentWorkspacenamesForDifferentInstances() {
        $firstClient = TFSClientWithCLIStubbed::createWithCredentials("http://server/defaultcollection", "user", "password");
        $secondClient = TFSClientWithCLIStubbed::createWithCredentials("http://server/defaultcollection", "user", "password");
        $this->assertNotSame(self::getPrivate($firstClient, 'workspacename'), self::getPrivate($secondClient, 'workspacename'));
    }

    /**
     * @test
     */
    function shouldGetWorkspaceProperties()
    {
        $fixture = array(
            "Collection: https://server/defaultcollection/",
            "Workspace Owner          Computer Comment",
            "--------- -------------- -------- ---------------------------------------------",
            "BLUEBOX   saveug@mail.ru BLUEBOX  wkspace comment");

        $client = TFSClientWithCLIStubbed::createWithOutput($fixture, 0);

        $workspaceProperties = $client->getWorkspaceProperties("someworkspace");

        $this->assertNotNull($workspaceProperties);
        $this->assertEquals("BLUEBOX", $workspaceProperties['name']);
        $this->assertEquals("saveug@mail.ru", $workspaceProperties['owner']);
        $this->assertEquals("BLUEBOX", $workspaceProperties['computer']);
        $this->assertEquals("wkspace comment", $workspaceProperties['comment']);
    }

    /**
     * @test
     * @expectedException troggy\PhpTFSClient\TFSClientException
     */
    function shouldThrowExceptionWhenUnexistingWorkspacePropertiesRequested()
    {
        $client = TFSClientWithCLIStubbed::createWithOutput(array("No workspace matching DOESNT_EXISTS on computer BLUEBOX found in Team
Foundation Server https://server/defaultcollection."), 1);

        $client->getWorkspaceProperties("DOESNT_EXISTS");
    }

    /**
     * @test
     * @expectedException troggy\PhpTFSClient\TFSClientException
     */
    function shouldThrowExceptionWhenUnexistingWorkspacePropertiesRequestedAndCurrentHostHaveNoWorkspaces()
    {
        $client = TFSClientWithCLIStubbed::createWithOutput(array("No workspace matching DOESNT_EXISTS on computer BLUEBOX found in Team
Foundation Server https://server/defaultcollection."), 0);

        $client->getWorkspaceProperties("DOESNT_EXISTS");
    }

    /**
     * @test
     */
    function shouldGetDirectoryFiles()
    {
        $fixture = array(
            "$/tfsconnector/samples:",
            "\$res",
            "\$src",
            "build.xml",
            "project.properties",
            "",
            "4 item(s).    ");
        $client = TFSClientWithCLIStubbed::createWithOutput($fixture, 0);

        $files = $client->getDirectoryFiles("$/tfsconnect/samples");

        $this->assertEquals(4, sizeof($files));
        $this->assertEquals("src", $files[1]['name']);
        $this->assertEquals("directory", $files[1]['type']);
        $this->assertEquals("$/tfsconnector/samples/src", $files[1]['path']);
        $this->assertEquals("file", $files[3]['type']);
    }

    /**
     * @test
     */
    function shouldReturnEmptyArrayForEmptyDirectory()
    {
        $client = TFSClientWithCLIStubbed::createWithOutput(array("No items found under $/tfsconnector/emptyDir"), 0);

        $files = $client->getDirectoryFiles("$/tfsconnect/emptyDir");
        $this->assertEquals(0, sizeof($files));
    }

    /**
     * @test
     * @expectedException troggy\PhpTFSClient\TFSClientException
     */
    function shouldThrowExceptionWhenUnexistingDirectoryListingRequested()
    {
        $client = TFSClientWithCLIStubbed::createWithOutput(array("No items match $/tfsconnectors"), 1);

        $client->getDirectoryFiles("$/tfsconnect/doesntExists");
    }

    /**
     * @test
     */
    function shouldGetFolderProperties()
    {
        $outputFromCLI = array(
            "Local information:",
            "  Local path:  C:\\locapath\\samples",
            "  Server path: $/tfsconnector/samples",
            "  Changeset:   5",
            "  Change:      none",
            "  Type:        folder",
            "Server information:",
            "  Server path:   $/tfsconnector/samples",
            "  Changeset:     5",
            "  Deletion ID:   0",
            "  Lock:          none",
            "  Lock owner:",
            "  Last modified: 07.04.2013 23:49:10",
            "  Type:          folder    ");

        $client = TFSClientWithCLIStubbed::createWithOutput($outputFromCLI, 0);

        $properties = $client->getProperties("$/tfsconnect/samples");

        $this->assertNotNull($properties);
        $this->assertEquals("07.04.2013 23:49:10", $properties['last-mod']);
        $this->assertFalse(isset($properties['content-type']));
        $this->assertFalse(isset($properties['length']));
    }

    /**
     * @test
     */
    function shouldGetFileProperties()
    {
        $outputFromCLI = array(
            "Local information:",
            "  Local path:  C:\\localpath\\build.xml",
            "  Server path: $/tfsconnector/build.xml",
            "  Changeset:   5",
            "  Change:      none",
            "  Type:        file",
            "Server information:",
            "  Server path:   $/tfsconnector/build.xml",
            "  Changeset:     5",
            "  Deletion ID:   0",
            "  Lock:          none",
            "  Lock owner:",
            "  Last modified: 07.04.2013 23:49:10",
            "  Type:          file",
            "  File type:     windows-1251",
            "  Size:          3593");

        $client = TFSClientWithCLIStubbed::createWithOutput($outputFromCLI, 0);

        $properties = $client->getProperties("$/tfsconnect/build.xml");

        $this->assertNotNull($properties);
        $this->assertEquals("07.04.2013 23:49:10", $properties['last-mod']);
        $this->assertEquals("windows-1251", $properties['content-type']);
        $this->assertEquals("3593", $properties['length']);
    }

    /**
     * @test
     * @expectedException troggy\PhpTFSClient\TFSClientException
     */
    function shouldThrowExceptionWhenUnexistingItemPropertiesRequested()
    {
        $client = TFSClientWithCLIStubbed::createWithOutput(array("No items match $/tfsconnector/doesntExists"), 1);

        $client->getProperties("$/tfsconnect/doesntExists");
    }

    /**
     * @test
     */
    function shouldGetHistory()
    {
        $outputFromCLI = array (
                '<?xml version="1.0" encoding="utf-8"?><history>',
                '  <changeset id="3" owner="Windows Live ID\saveug@mail.ru" committer="Windows Live ID\saveug@mail.ru" date="2013-04-07T23:55:48.973+0530">',
                '    <comment>commit comment</comment>',
                '    <item change-type="delete" server-item="$/project/deleted.file"/>',
                '    <item change-type="edit" server-item="$/project/updated.file"/>',
                '    <item change-type="add" server-item="$/project/added.file"/>',
                '  </changeset>',
                '  <changeset id="2" owner="Windows Live ID\saveug@mail.ru" committer="Windows Live ID\saveug@mail.ru" date="2013-04-07T23:50:12.497+0530">',
                '    <comment>another meaningful commit</comment>',
                '    <item change-type="add" server-item="$/tfsconnector/library"/>',
                '  </changeset>',
                '  <changeset id="1" owner="vstfs:///Framework/Generic/df6a9717-e9fe-4397-890f-abbae9ed4569\Project Collection Service Accounts" committer="vstfs:///Framework/Generic/df6a9717-e9fe-4397-890f-abbae9ed4569\Project Collection Service Accounts" date="2013-04-04T14:13:04.270+0530">',
                '    <item change-type="add" server-item="$/"/>',
                '  </changeset>',
                '</history>');

        $client = TFSClientWithCLIStubbed::createWithOutput($outputFromCLI, 0);
        $historyParserMock = $this->getMock("TFSHistoryParser", array("parse"));

        $historyParserMock->expects($this->once())
                ->method('parse')
                ->with($this->equalTo(join('', $outputFromCLI)));
        $client->setHistoryParser($historyParserMock);

        $client->getHistory("$/tfsconnector/");
    }

    /**
     * @test
     * @expectedException troggy\PhpTFSClient\TFSClientException
     */
    function shouldThrowExceptionWhenUnexistingItemHistoryRequested()
    {
        $client = TFSClientWithCLIStubbed::createWithOutput(array("No history entries were found for the item and version combination specified."), 0);

        $history = $client->getHistory("$/tfsconnect/doesntExists");
        $this->assertNull($history);
    }

    /**
     * @test
     */
    function shouldCreateNewWorkspace() {
        $client = TFSClientWithCLIStubbed::createWithOutput(array("Workspace 'test' created."), 0);

        $this->assertTrue($client->createWorkspace("test"));
    }

    /**
     * @test
     * @expectedException troggy\PhpTFSClient\TFSClientException
     */
    function shouldThrowExceptionWhenCreatingWorkspaceWithDuplicateName() {
        $client = TFSClientWithCLIStubbed::createWithOutput(
            array("An error occurred: The workspace test already exists on computer COMPUTER."),
            1);

        $client->createWorkspace("test");
    }

    /**
     * @test
     */
    function shouldDeleteExistingWorkspace() {
        $client = TFSClientWithCLIStubbed::createWithOutput(array("Workspace 'test' deleted."), 0);

        $this->assertTrue($client->deleteWorkspace("test"));
    }

    /**
     * @test
     * @expectedException troggy\PhpTFSClient\TFSClientException
     */
    function shouldThrowExceptionWhenDeletingNonexistingWorkspace() {
        $client = TFSClientWithCLIStubbed::createWithOutput(
            array("An argument error occurred: The workspace 'test' could not be found."),
            1);

        $client->deleteWorkspace("test");
    }


    static function getPrivate($object, $property) {
        $reflector = new ReflectionProperty(get_parent_class($object), $property);
        $reflector->setAccessible(true);
        return $reflector->getValue($object);
    }

}
