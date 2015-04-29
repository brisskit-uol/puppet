<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
include_once APP_PATH_DOCROOT . 'DataExport/stats_functions.php';

# Validate form and field names
$field = $_POST['field'];
if (!isset($Proj->metadata[$field])) {
	header("HTTP/1.0 503 Internal Server Error");
	return;
}

# Whether or not to reverse the $data
$reverse = false;

// If we have a whitelist of records/events due to report filtering, unserialize it
$includeRecordsEvents = (isset($_POST['includeRecordsEvents'])) ? unserialize($_POST['includeRecordsEvents']) : array();
// If $includeRecordsEvents is passed and not empty, then it will be the record/event whitelist
$checkIncludeRecordsEvents = (!empty($includeRecordsEvents));

# Limit records pulled only to those in user's Data Access Group
$group_sql  = ""; 
if ($user_rights['group_id'] != "") {
	$group_sql  = "and record in (" . pre_query("select record from redcap_data where project_id = $project_id and field_name = '__GROUPID__' and value = '{$user_rights['group_id']}'") . ")"; 
}

# Calculate lowest values
if ($_POST['svc'] == 'low') {
	$sql = "select record, value, event_id from redcap_data where project_id = $project_id and field_name = '$field' 
			and value is not null and value != '' $group_sql order by (value+0) asc limit 5";

# Calculate highest Values
} elseif ($_POST['svc'] == 'high') {
	$sql = "select record, value, event_id from redcap_data where project_id = $project_id and field_name = '$field' 
			and value is not null and value != '' $group_sql order by (value+0) desc limit 5";
	// Set flag to reverse data points for output
	$reverse = true;

# Calculate missing values
} elseif ($_POST['svc'] == 'miss') {
	$sql = "select distinct record, event_id from redcap_data where project_id = $project_id and field_name = '$table_pk' and 
			concat(event_id,',',record) not in (" . pre_query("select concat(event_id,',',record) from (select distinct event_id, record 
			from redcap_data where value is not null and value != '' and project_id = $project_id and field_name = '$field') 
			as x") . ") $group_sql order by event_id";
}

// Execute query to retrieve response
$i = 0;
$data = array();
$res = db_query($sql);
if ($res) {
	// Special conditions apply for missing values in a longitudinal project. 
	// Make sure the event_id here is in the events_forms table (i.e. that the form is even used by that event).
	if ($_POST['svc'] == 'miss' && $longitudinal) 
	{
		// Loop through data
		while ($ret = db_fetch_assoc($res)) {
			// If we have a record/event whitelist, then check the record/event
			if ($checkIncludeRecordsEvents && !isset($includeRecordsEvents[$ret['record']][$ret['event_id']])) continue;
			// Is event_id valid for this field's form?
			if (in_array($Proj->metadata[$field]['form_name'], $Proj->eventsForms[$ret['event_id']])) {
				// Only add to output if field's form is used for this event
				$data[] = removeDDEending($ret['record']) . ":" . $ret['event_id'];
				// Response count
				$i++;
			}
		}
	} 	
	// Loop through data normally	
	else 
	{
		while ($ret = db_fetch_assoc($res)) {
			// If we have a record/event whitelist, then check the record/event
			if ($checkIncludeRecordsEvents && !isset($includeRecordsEvents[$ret['record']][$ret['event_id']])) continue;
			$data[] = removeDDEending($ret['record']) . ":" . $ret['event_id'];
			// Response count
			$i++;
		}
	}
	// Sort the data by record name
	natcasesort($data);
	// Reverse order of data points, if set
	if ($reverse) 
	{
		$data = array_reverse($data);
	}
}

// Output response
header('Content-type: text/plain');
print $i . '|' . implode('|', $data);
