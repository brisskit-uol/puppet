<?php
defined("PROJECT_ID") or define("PROJECT_ID", $post['projectid']);

# get project information
$Proj = new Project();
$longitudinal = $Proj->longitudinal;

// If user has "No Access" export rights, then return error
if ($post['export_rights'] == '0') {
	exit(RestUtility::sendResponse(403, 'The API request cannot complete because currently you have "No Access" data export rights. Higher level data export rights are required for this operation.'));
}

// Get user's user rights
$query = "SELECT username FROM redcap_user_rights WHERE api_token = '" . prep($post['token']) . "'";
defined("USERID") or define("USERID", db_result(db_query($query), 0));
$user_rights = UserRights::getPrivileges(PROJECT_ID, USERID);
$user_rights = $user_rights[PROJECT_ID][strtolower(USERID)];
$ur = new UserRights();
$ur->setFormLevelPrivileges(PROJECT_ID);

// Set vars
$project_id = $_GET['pid'] = $post['projectid'];
$record = (isset($post['record']) && $post['record'] != '') ? $post['record'] : '';
$form_name = (isset($post['instrument']) && $post['instrument'] != '') ? $post['instrument'] : '';
$eventName = (isset($post['event']) && $post['event'] != '') ? $post['event'] : '';

// Verify form name, if included
if ($form_name != '' && !isset($Proj->forms[$form_name])) {
	RestUtility::sendResponse(400, "Invalid instrument");
}

# check to make sure the record exists, if included
$eventId = "";
if ($record != '') {
	// Get the event id for the item to be downloaded
	if ($longitudinal) {
		# check the event that was passed in and get the id associated with it
		if ($eventName != '') {
			$eventId = $_GET['event_id'] = $Proj->getEventIdUsingUniqueEventName($eventName);	
			if (!is_numeric($eventId)) {
				RestUtility::sendResponse(400, "Invalid event");
			}
		}
	} else {
		$eventId = $_GET['event_id'] = $Proj->firstEventId;
	}
	// Verify record exists
	if (!recordExists($record)) {
		RestUtility::sendResponse(400, "The record '$record' does not exist");	
	}
}


// Output PDF of all forms (ALL records)
$logging_data_values = "";
if (isset($post['allrecords']) || isset($post['allRecords'])) {
	$_GET['allrecords'] = '1';
	$logging_description = "Download all data entry forms as PDF (all records)";
}
// Output PDF of single form (blank)
elseif ($form_name != '' && $record == '') {
	$_GET['page'] = $form_name;
	$logging_data_values = "form_name = '$form_name'";
	$logging_description = "Download data entry form as PDF";
}
// Output PDF of single form (single record's data)
elseif ($form_name != '' && $record != '') {
	$_GET['id'] = $record;
	$_GET['page'] = $form_name;
	$logging_data_values = "record = '$record',\nform_name = '$form_name'";
	if ($eventId != '') $logging_data_values .= ",\nevent_id = $eventId";
	$logging_description = "Download data entry form as PDF (with data)";
}
// Output PDF of all forms (blank)
elseif ($form_name == '' && $record == '') {
	$_GET['all'] = '1';
	$logging_description = "Download all data entry forms as PDF";
}
// Output PDF of all forms (single record's data)
elseif ($form_name == '' && $record != '') {
	$_GET['id'] = $record;
	$logging_data_values = "record = '$record'";
	if ($eventId != '') $logging_data_values .= ",\nevent_id = $eventId";
	$logging_description = "Download all data entry forms as PDF (with data)";
} 
// Unknown error
else {
	RestUtility::sendResponse(400, "Unknown error");
}

## OUTPUT PDF
include APP_PATH_DOCROOT . "PDF/index.php";

// log the event
log_event($sql,"redcap_metadata","MANAGE",$project_id,$logging_data_values,"$logging_description (API)");
