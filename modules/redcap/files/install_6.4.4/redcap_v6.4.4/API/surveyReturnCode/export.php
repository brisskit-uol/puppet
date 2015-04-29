<?php
defined("PROJECT_ID") or define("PROJECT_ID", $post['projectid']);

# get project information
$Proj = new Project();
$longitudinal = $Proj->longitudinal;

// Get user's user rights
$query = "SELECT username FROM redcap_user_rights WHERE api_token = '" . prep($post['token']) . "'";
defined("USERID") or define("USERID", db_result(db_query($query), 0));
$user_rights = UserRights::getPrivileges(PROJECT_ID, USERID);
$user_rights = $user_rights[PROJECT_ID][strtolower(USERID)];
$ur = new UserRights();
$ur->setFormLevelPrivileges(PROJECT_ID);

// If user has "No Access" export rights, then return error
if ($user_rights['participants'] == '0') {
	exit(RestUtility::sendResponse(403, 'The API request cannot complete because currently you do not have "Manage Survey Participants" privileges, which are required for this operation.'));
}

// Set vars
$project_id = $_GET['pid'] = $post['projectid'];
$record = (isset($post['record']) && $post['record'] != '') ? $post['record'] : '';
$form_name = (isset($post['instrument']) && $post['instrument'] != '') ? $post['instrument'] : '';
$eventName = (isset($post['event']) && $post['event'] != '') ? $post['event'] : '';


// Validate record
if ($record == '') {
	RestUtility::sendResponse(400, "The parameter 'record' is missing");
} elseif (!recordExists($record)) {
	RestUtility::sendResponse(400, "The record '$record' does not exist");	
}

// Validate instrument
if ($form_name == '') {
	RestUtility::sendResponse(400, "The parameter 'instrument' is missing");
} elseif ($form_name != '' && !isset($Proj->forms[$form_name])) {
	RestUtility::sendResponse(400, "Invalid instrument");
} elseif ($form_name != '' && !isset($Proj->forms[$form_name]['survey_id'])) {
	RestUtility::sendResponse(400, "The instrument '$form_name' has not been enabled as a survey");
}

// Validate event
if ($longitudinal) {
	# check the event that was passed in and get the id associated with it
	if ($eventName == '') {
		RestUtility::sendResponse(400, "The parameter 'event' is missing");
	} elseif ($eventName != '') {
		$eventId = $Proj->getEventIdUsingUniqueEventName($eventName);	
		if (!is_numeric($eventId)) {
			RestUtility::sendResponse(400, "Invalid event");
		}
	}
} else {
	$eventId = $Proj->firstEventId;
}

// If "Save & Return Later" is not enabled, then return error
if (!$Proj->surveys[$Proj->forms[$form_name]['survey_id']]['save_and_return'] && !Survey::surveyLoginEnabled()) {
	RestUtility::sendResponse(400, "The 'Save & Return Later' feature has not been enabled for this survey");
}

// Get return code
$return_code = REDCap::getSurveyReturnCode($record, $form_name, $eventId);

// Check for errors
if ($return_code == null) {
	RestUtility::sendResponse(400, "An unknown error occurred");
} else {
	// Log the event
	$logging_data_values = "record = '$record',\nform_name = '$form_name'";
	if ($longitudinal) $logging_data_values .= ",\nevent_id = $eventId";
	$_GET['event_id'] = $eventId;
	log_event("","redcap_surveys_participants","MANAGE",$record,$logging_data_values,"Download survey return code (API)");
	// Return the code
	print $return_code;
}
