<?php

namespace troggy\PhpTFSClient;

use DateTime;

/**
 * @author Kosta Korenkov <7r0ggy@gmail.com>
 */
class TFSHistoryParser
{

    const CHANGESET_DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    var $history = array();

    var $historyElement = array();

    var $currentXmlElement;

    public function parse($xml)
    {
        $this->history = array();
        $resParser = xml_parser_create();
        xml_set_object($resParser, $this);
        xml_set_element_handler($resParser, "startElement", "endElement");
        xml_set_character_data_handler($resParser, "elementData");

        $strXmlData = xml_parse($resParser, $xml);
        if (!$strXmlData) { //todo: error handling
            throw new XMLParseException(
                xml_error_string(xml_get_error_code($resParser)),
                xml_get_current_line_number($resParser));
        }

        xml_parser_free($resParser);

        return $this->history;
    }

    private function startElement($parser, $name, $attrs)
    {
        $this->currentXmlElement = $name;
        if ($name == 'CHANGESET') {
            $this->historyElement = array();
            $this->historyElement['changes'] = array();
            $this->historyElement['author'] = $attrs['COMMITTER'];
            $this->historyElement['version'] = intval($attrs['ID']);
            $this->historyElement['date'] = DateTime::createFromFormat(TFSHistoryParser::CHANGESET_DATE_FORMAT, $attrs['DATE']);
        } else if ($name == 'ITEM') {
            $item = array();
            $item['change-type'] = $attrs['CHANGE-TYPE'];
            $item['server-item'] = $attrs['SERVER-ITEM'];
            array_push($this->historyElement['changes'], $item);
        }
    }

    private function endElement($parser, $name)
    {
        $this->currentXmlElement = '';
        if ($name == 'CHANGESET') {
            array_push($this->history, $this->historyElement);
        }
    }

    private function elementData($parser, $tagData)
    {
        if ($this->currentXmlElement == 'COMMENT') {
            $this->historyElement['comment'] = $tagData;
        }
    }

}

?>