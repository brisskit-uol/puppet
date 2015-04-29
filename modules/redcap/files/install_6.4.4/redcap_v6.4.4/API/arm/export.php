<?php
global $format, $returnFormat, $post;

defined("PROJECT_ID") or define("PROJECT_ID", $post['projectid']);

# get all the records to be exported
$result = getRecords();

# structure the output data accordingly
switch($format)
{
	case 'json':
		$content = json_encode($result);
		break;
	case 'xml':
		$content = xml($result);
		break;
	case 'csv':
		$content = csv($result);
		break;
}

/************************** log the event **************************/
$query = "SELECT username FROM redcap_user_rights WHERE api_token = '" . prep($post['token']) . "'";
defined("USERID") or define("USERID", db_result(db_query($query), 0));

# Logging
log_event("", "redcap_events_arms", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export arms (API)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function xml($dataset)
{
	$output = '<?xml version="1.0" encoding="UTF-8" ?>';
	$output .= "\n<arms>\n";

	foreach ($dataset as $row)
	{
		$line = '';
		foreach ($row as $item => $value)
		{
			if ($value != "")
				$line .= "<$item><![CDATA[" . html_entity_decode($value, ENT_QUOTES) . "]]></$item>";
			else
				$line .= "<$item></$item>";
		}

		$output .= "<item>$line</item>\n";
	}
	$output .= "</arms>\n";

	return $output;
}

function csv($dataset)
{
	$output = "";

	foreach ($dataset as $index => $row) {
		$output .= $row['arm_num'].',"'.str_replace('"', '""', html_entity_decode($row['name'], ENT_QUOTES)).'"'."\n";
	}

	$fieldList = "arm_num,name";
	$output = $fieldList . "\n" . $output;

	return $output;
}

function getRecords()
{
	global $post;
	
	# get project information
	$Proj = new Project();

	//This function only works for longitudinal projects
	if (!$Proj->longitudinal) die(RestUtility::sendResponse(400, 'You cannot export arms for classic projects'));

	$arms = array();
	foreach ($Proj->events as $num => $data)
	{
		// If arms were explicitly set as API parameter array, then return only those
		if (!empty($post['arms']) && !in_array($num, $post['arms'])) continue;
		// Add to array
		$arms[] = array("arm_num"	=> $num,
						"name"		=> $data["name"]);
	}

	return $arms;
}