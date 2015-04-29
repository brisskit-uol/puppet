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

// Validate record
if ($record == '') {
	RestUtility::sendResponse(400, "The parameter 'record' is missing");
} elseif (!recordExists($record)) {
	RestUtility::sendResponse(400, "The record '$record' does not exist");	
}

// If survey queue is not enabled for this project yet, return error
if (!Survey::surveyQueueEnabled()) {
	RestUtility::sendResponse(400, "The Survey Queue has not been enabled in this project. You will need to enable the Survey Queue before using this method.");
}

// Get survey link
$survey_queue_link = REDCap::getSurveyQueueLink($record);

// Check for errors
if ($survey_queue_link == null) {
	RestUtility::sendResponse(400, "An unknown error occurred");
} else {
	// Log the event
	$logging_data_values = "record = '$record'";
	$_GET['event_id'] = $eventId;
	log_event("","redcap_surveys_participants","MANAGE",$record,$logging_data_values,"Download survey queue link (API)");
	// Return the link text
	print $survey_queue_link;
}
