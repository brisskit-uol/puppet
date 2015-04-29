<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
require_once APP_PATH_DOCROOT . "Surveys/survey_functions.php";

// Confirm the participant_id, survey_id, and event_id
$sql = "select 1 from redcap_surveys s, redcap_surveys_participants p 
		where s.project_id = $project_id and s.survey_id = p.survey_id and s.survey_id = '".prep($_POST['survey_id'])."'
		and p.event_id = '".prep($_POST['event_id'])."' and p.participant_id = '".prep($_POST['participant_id'])."'";
$q = db_query($sql);
if (!db_num_rows($q)) exit("0");

// Get first event_id in the current arm using the given event_id
$first_event_id = $Proj->getFirstEventIdInArmByEventId($_POST['event_id']);
// Get first survey_id
$first_survey_id = $Proj->firstFormSurveyId;
// Is this the first event and first survey?
$is_first_survey_event = ($first_event_id == $_POST['event_id'] && $first_survey_id == $_POST['survey_id']);

// Make sure to seed the participant row of the first event and first survey, just in case
if (!$is_first_survey_event && $_POST['record'] != '') {
	list ($participant_id, $hash) = Survey::getFollowupSurveyParticipantIdHash($first_survey_id, $_POST['record'], $first_event_id);
} else {
	$participant_id = $_POST['participant_id'];
}

// Set the preference on the first event / first survey
$sql = "update redcap_surveys_participants set delivery_preference = '".prep($_POST['delivery_preference'])."'
		where participant_id in ($participant_id)";
if (db_query($sql)) {
	// Logging
	log_event($sql,"redcap_surveys_participants","MANAGE",$_POST['participant_id'],"participant_id = {$_POST['participant_id']}","Change participant invitation preference");
	// Return html for delivery preference icon
	print Survey::getDeliveryPrefIcon($_POST['delivery_preference']);
} else {
	print "0";
}