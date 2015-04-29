<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

// Disable authentication (will catch issues later if not authentic request)
define("NOAUTH", true);
// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
// Ensure field_name belongs to this project
if (!isset($Proj->metadata[$_GET['field_name']])) exit('ERROR!');
// Get field's label and display it
$label = $Proj->metadata[$_GET['field_name']]['element_label'];
// If passing record/event_id, then implement piping, if needed
if (strpos($label, '[') !== false && isset($_GET['event_id']) && is_numeric($_GET['event_id'])) {
	if ($_GET['s'] != '') {
		// Get survey functions file
		require_once dirname(dirname(__FILE__)) . '/Surveys/survey_functions.php';
		// Get participant_id from survey hash
		$participant_id = getParticipantIdFromHash($_GET['s']);
		// Make sure the participant_id isn't a public survey
		$sql = "select 1 from redcap_surveys_participants where participant_id = $participant_id
				and participant_email is not null";
		$q = db_query($sql);
		if (db_num_rows($q) > 0) {
			// Get record name from participant_id
			$records = getRecordFromPartId(array($participant_id));
			// Pipe the label
			$label = Piping::replaceVariablesInLabel($label, $records[$participant_id], $_GET['event_id']);
		}
	} elseif ($_GET['record'] != '') {
		// Pipe the label
		$label = Piping::replaceVariablesInLabel($label, $_GET['record'], $_GET['event_id']);
	}
}
// Output label and field_name
print "<b>$label</b> <i>({$_GET['field_name']})</i>";
