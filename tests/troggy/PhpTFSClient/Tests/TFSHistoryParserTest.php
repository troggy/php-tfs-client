<?php

namespace troggy\PhpTFSClient\Tests;

use DateTime;
use PHPUnit_Framework_TestCase;
use troggy\PhpTFSClient\TFSHistoryParser;

class TFSHistoryParserTest extends PHPUnit_Framework_TestCase
{

    var $validHistoryXML = '<?xml version="1.0" encoding="utf-8"?><history>
  <changeset id="3" owner="Windows Live ID\saveug@mail.ru" committer="Windows Live ID\saveug@mail.ru" date="2013-04-07T23:55:48.973+0530">
    <comment>commit comment</comment>
    <item change-type="delete" server-item="$/project/deleted.file"/>
    <item change-type="edit" server-item="$/project/updated.file"/>
    <item change-type="add" server-item="$/project/added.file"/>
  </changeset>
  <changeset id="2" owner="Windows Live ID\saveug@mail.ru" committer="Windows Live ID\saveug@mail.ru" date="2013-04-07T23:50:12.497+0530">
    <comment>another meaningful commit</comment>
    <item change-type="delete, source rename" server-item="$/project/test.txt"/>
    <item change-type="rename" server-item="$/project/test2.txt"/>
  </changeset>
  <changeset id="1" owner="vstfs:///Framework/Generic/df6a9717-e9fe-4397-890f-abbae9ed4569\Project Collection Service Accounts" committer="vstfs:///Framework/Generic/df6a9717-e9fe-4397-890f-abbae9ed4569\Project Collection Service Accounts" date="2013-04-04T14:13:04.270+0530">
    <item change-type="add" server-item="$/"/>
  </changeset>
</history>
';

    var $invalidHistoryXML = 'df6a9717-e9fe-4397-890f-abbae9ed4569';

    var $emptyHistoryXML = '<?xml version="1.0" encoding="utf-8"?><history></history>';

    /** @var  TFSHistoryParser */
    var $parser;

    function setUp()
    {
        $this->parser = new TFSHistoryParser();
    }

    /**
     * @test
     */
    function shouldParseAllChangesetsInHistory()
    {
        $history = $this->parser->parse($this->validHistoryXML);
        $this->assertEquals(3, sizeof($history));
    }

    /**
     * @test
     */
    function shouldParseChangesetAttributes()
    {
        $history = $this->parser->parse($this->validHistoryXML);
        $this->assertEquals(3, $history[0]['version'], "Changeset version should be parsed");
        $this->assertTrue(is_int($history[0]['version']));
        $this->assertEquals("Windows Live ID\saveug@mail.ru", $history[0]['author'], "Changeset author should be parsed");
        $this->assertEquals("commit comment", $history[0]['comment'], "Changeset comments should be parsed");
        $date = DateTime::createFromFormat(TFSHistoryParser::CHANGESET_DATE_FORMAT, "2013-04-07T23:55:48.973+0530");
        $this->assertEquals($date, $history[0]['date'], "Changeset dates should be parsed");
    }

    /**
     * @test
     */
    function shouldParseChangesetFiles() {
        $history = $this->parser->parse($this->validHistoryXML);
        $this->assertEquals(3, sizeof($history[0]['changes']));
        $this->assertEquals("$/project/added.file", $history[0]['changes'][2]['server-item']);
        $this->assertEquals("add", $history[0]['changes'][2]['change-type']);
    }

    /**
     * @test
     */
    function shouldParseTestWithNoComment()
    {
        $history = $this->parser->parse($this->validHistoryXML);
        $this->assertEquals("1", $history[2]['version'], "Changeset version should be parsed");
        $this->assertFalse(isset($history[2]['comment']));
    }

    /**
     * @test
     */
    function shouldParseEmptyHistory()
    {
        $history = $this->parser->parse($this->emptyHistoryXML);
        $this->assertEquals(0, sizeof($history));
    }

    /**
     * @test
     * @expectedException troggy\PhpTFSClient\XMLParseException
     */
    function shouldFailOnInvalidXML()
    {
        $this->parser->parse($this->invalidHistoryXML);
    }

    /**
     * @test
     */
    function shouldBeReenterable()
    {
        $history = $this->parser->parse($this->validHistoryXML);
        $this->assertNotEmpty($history);
        $history = $this->parser->parse($this->emptyHistoryXML);
        $this->assertEmpty($history);

    }

}

?>