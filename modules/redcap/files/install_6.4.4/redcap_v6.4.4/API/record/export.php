<?php
$logFields = array();
# get all the records to be exported
# Send the response to the requestor

function xml($dataset)
function csv($dataset)
function getRecords()
		$query = "select r.record, r.completion_time, p.participant_identifier, s.form_name, p.event_id
			$timestamp_identifiers[$row['record']][$row['event_id']][$row['form_name']] = array('ts'=>$row['completion_time'], 'id'=>$row['participant_identifier']);
	// get field information from metadata
	if ($type == 'eav')
			if ($fieldData["types"][$row['field_name']] == "truefalse")
	return $result;
function renderEnumData($data, $enum, $rawOrLabel)
			if ($data == $value) {
	return $newValue;
function ValueArray($sql)