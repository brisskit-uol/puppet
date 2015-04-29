<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/


// Retrieve survey_id using form_name
function getSurveyId($form_name = null)
{
	global $Proj;
	if (empty($form_name)) $form_name = $Proj->firstForm;
	return (isset($Proj->forms[$form_name]['survey_id']) ? $Proj->forms[$form_name]['survey_id'] : "");
}

// Creates unique results_code (that is, unique within that survey) and returns that value
function getUniqueResultsCode($survey_id=null)
{
	if (!is_numeric($survey_id)) return false;
	do {
		// Generate a new random hash
		$code = strtolower(generateRandomHash(8));
		// Ensure that the hash doesn't already exist in either redcap_surveys or redcap_surveys_hash (both tables keep a hash value)
		$sql = "select r.results_code from redcap_surveys_participants p, redcap_surveys_response r 
				where p.survey_id = $survey_id and r.results_code = '$code' limit 1";
		$codeExists = (db_num_rows(db_query($sql)) > 0);
	} while ($codeExists);
	// Code is unique, so return it
	return $code;
}

// Exit the survey and give message to participant
function exitSurvey($text, $largeFont=true, $closeSurveyBtnText=null, $justCompletedSurvey=true)
{
	global $isMobileDevice, $lang;
	
	// If paths have not been set yet, call functions that set them (need paths set for HtmlPage class)
	if (!defined('APP_PATH_WEBROOT'))
	{
		// Pull values from redcap_config table and set as global variables
		setConfigVals();
		// Set directory definitions
		define_constants();	
	}
	
	// If a Twilio SMS or Voice Call
	if (isset($_SERVER['HTTP_X_TWILIO_SIGNATURE'])) 
	{	
		// Initialize Twilio
		TwilioRC::init();
		// An invalid choice was entered
		if (SMS) {
			// Instantiate a new Twilio Rest Client
			$twilioClient = TwilioRC::client();
			TwilioRC::sendSMS(strip_tags($text), $_POST['From'], $twilioClient);
		} else {
			// Set voice and language attributes for all Say commands
			$language = TwilioRC::getLanguage();
			$voice = TwilioRC::getVoiceGender();
			$say_array = array('voice'=>$voice, 'language'=>$language);
			// Set header to output TWIML/XML
			header('Content-Type: text/xml');
			// Output Twilio TwiML object
			$twiml = new Services_Twilio_Twiml();
			$twiml->say(strip_tags($text), $say_array);
		}
		exit;		
	}
	
	// Class for html page display system
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->addExternalJS(APP_PATH_JS . "base.js");
	$objHtmlPage->addExternalJS(APP_PATH_JS . "fontsize.js");
	$objHtmlPage->addExternalJS(APP_PATH_JS . "survey.js");
	$objHtmlPage->addStylesheet("smoothness/jquery-ui-".JQUERYUI_VERSION.".custom.css", 'screen,print');
	$objHtmlPage->addStylesheet("style.css", 'screen,print');
	$objHtmlPage->addStylesheet("survey.css", 'screen,print');
	// Mobile: Add mobile-specific stylesheets and CSS3 conditions to detect small browsers
	if ($isMobileDevice)
	{
		$objHtmlPage->addStylesheet("mobile_survey_portrait.css","only screen and (max-width: 320px)");
		$objHtmlPage->addStylesheet("mobile_survey_landscape.css","only screen and (min-width: 321px) and (max-width: 480px)");
	}	
	$objHtmlPage->PrintHeader();
	// Display a "close" button at top
	if ($closeSurveyBtnText !== false) {
		print 	RCView::div(array('style'=>'padding:10px 10px 0 0;'),
					RCView::button(array('onclick'=>"try{modifyURL('index.php')}catch(e){} window.open('', '_self', '');window.close();window.top.close();open(window.location, '_self').close();self.close();", 'class'=>'jqbuttonmed'), 
						($closeSurveyBtnText == null ? $lang['dataqueries_278'] : $closeSurveyBtnText)
					)
				);
	}
	// Display the text
	if ($largeFont) {
		print "<div style='font-size: 16px;margin:30px 0;font-weight:bold;'>$text</div>";
	} else {
		print "<div style='margin:30px 0;line-height: 1.5em;'>$text</div>";
	}
	// Only do the following if we just completed a survey
	if ($justCompletedSurvey) {
		// REDCap Hook injection point: Pass project/record/survey attributes to method
		global $fetched, $Proj;
		$group_id = (empty($Proj->groups)) ? null : Records::getRecordGroupId(PROJECT_ID, $fetched);
		if (!is_numeric($group_id)) $group_id = null;
		Hooks::call('redcap_survey_complete', array(PROJECT_ID, (is_numeric($_POST['__response_id__']) ? $fetched : null), $_GET['page'], $_GET['event_id'], $group_id, $_GET['s'], $_POST['__response_id__']));
		## Destroy the session on server and session cookie in user's browser
		$_SESSION = array();
		session_unset();
		session_destroy();
		unset($_COOKIE['survey']);
		deletecookie('survey');
	}
	// Footer
	$objHtmlPage->PrintFooter();
	exit;
}

// Return participant_id when passed the hash
function getParticipantIdFromHash($hash=null)
{
	if ($hash == null) return false;
	$sql = "select participant_id from redcap_surveys_participants where hash = '" . prep($hash) . "' limit 1";
	$q = db_query($sql);
	// If participant_id exists, then return it
	return (db_num_rows($q) > 0) ? db_result($q, 0) : false;
}

// Make sure the survey belongs to this project
function checkSurveyProject($survey_id)
{
	global $Proj;
	return (is_numeric($survey_id) && isset($Proj->surveys[$survey_id]));
}

// Create array of field names designating their survey page with page number as key
function getPageFields($form_name, $question_by_section)
{
	global $Proj, $table_pk;
	// Set page counter at 1
	$page = 1;
	// Field counter
	$i = 1;
	// Create empty array
	$pageFields = array();
	// Loop through all form fields and designate fields to page based on location of section headers
	foreach (array_keys($Proj->forms[$form_name]['fields']) as $field_name)
	{
		// Do not include record identifier field nor form status field (since they are not shown on survey)
		if ($field_name == $table_pk || $field_name == $form_name."_complete") continue;
		// If field has a section header, then increment the page number (ONLY for surveys that have paging enabled)
		if ($question_by_section && $Proj->metadata[$field_name]['element_preceding_header'] != "" && $i != 1) $page++;
		// Add field to array
		$pageFields[$page][$i] = $field_name;
		// Increment field count
		$i++;
	}
	// Return array
	return array($pageFields, count($pageFields));
}

// Find the page number that a survey question is on based on variable name
function getQuestionPage($variable,$pageFields)
{
	$foundField = false;
	foreach ($pageFields as $this_page=>$these_fields) {
		foreach ($these_fields as $this_field) {
			if ($variable == $this_field) {
				// Found the page
				return $this_page;
			}
			if ($foundField) break;
		}
		if ($foundField) break;
	}
	// If not found, set to page 1
	return 1;
}

// Track the page number as a GET variable (not seen in query string). 
// Return the label for the Save button and array of fields to hide on this page.
function setPageNum($pageFields, $totalPages, $bypassReturnCodeSection=false)
{
	global $table_pk, $participant_id, $return_code, $lang;
	// FIRST PAGE OF SURVEY (i.e. request method = GET)
	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_GET['__page__'] = 1;
	}
	// If returning and just entered return code, determine page based upon last field with data entered
	elseif (!$bypassReturnCodeSection && isset($return_code) && !empty($return_code)) {
		// Query data table for data and retrieve field with highest field order on this form 
		// (exclude calc fields because may allow participant to pass up required fields that occur earlier)
		$sql = "select m.field_name from redcap_data d, redcap_metadata m where m.project_id = " . PROJECT_ID . " 
				and d.record = ". pre_query("select record from redcap_surveys_response where return_code = '" . prep($return_code) . "' 
				and participant_id = $participant_id and completion_time is null limit 1") . " 
				and m.project_id = d.project_id and m.field_name = d.field_name and d.event_id = {$_GET['event_id']}
				and m.field_name != '$table_pk' and m.field_name != concat(m.form_name,'_complete') and m.form_name = '{$_GET['page']}'
				and m.element_type != 'calc' and d.value != '' order by m.field_order desc limit 1";
		$lastFieldWithData = db_result(db_query($sql), 0);
		// Now find the page of this field
		$_GET['__page__'] = getQuestionPage($lastFieldWithData, $pageFields);
	}
	// Reduce page number if clicked previous page button
	elseif (isset($_POST['submit-action']) && isset($pageFields[$_POST['__page__']]) && is_numeric($_POST['__page__']))
	{
		if (!isset($_GET['__reqmsg'])) {
			// PREV PAGE
			if (isset($_GET['__prevpage'])) {
				// Decrement $_POST['__page__'] value by 1
				$_GET['__page__'] = $_POST['__page__'] - 1;
			}
			// NEXT PAGE
			else {
				// Increment $_POST['__page__'] value by 1
				$_GET['__page__'] = $_POST['__page__'] + 1;
			}
		} else {
			// If reloaded page for REQUIRED FIELDS, then set Get page as Post page (i.e. no increment)
			$_GET['__page__'] = $_POST['__page__'];
		}
	}
	
	// Make sure page num is not in error
	if (!isset($_GET['__page__']) || $_GET['__page__'] < 1 || !is_numeric($_GET['__page__'])) {
		$_GET['__page__'] = 1;
	}
	
	// Set the label for the Submit button
	if ($totalPages > 1 && $totalPages != $_GET['__page__']) {
		$saveBtn = $lang['data_entry_213']." >>";
	} else {
		$saveBtn = $lang['survey_200'];
	}
	
	// Given the current page number, determine the fields on this form that should be hidden
	$hideFields = array();
	foreach ($pageFields as $this_page=>$these_fields) {
		if ($this_page != $_GET['__page__']) {
			foreach ($these_fields as $this_field) {
				$hideFields[] = $this_field;
			}
		}
	}
	
	// Return the label for the Save button and array of fields to hide on this page
	return array($saveBtn, $hideFields);
}

// Gather participant list (with identfiers and if Sent/Responded) and return as array
function getParticipantList($survey_id, $event_id = null)
{
	global $Proj, $table_pk, $user_rights;

	// Check event_id (if not provided, then use first one - i.e. for public surveys)
	if (!is_numeric($event_id)) $event_id = getEventId();
	// Ensure the survey_id belongs to this project
	if (!checkSurveyProject($survey_id))
	{
		redirect(APP_PATH_WEBROOT . "index.php?pid=" . PROJECT_ID);
	}
	
	// Check if this is a follow-up survey
	$isFollowUpSurvey = !($survey_id == $Proj->firstFormSurveyId && $Proj->isFirstEventIdInArm($event_id));
	
	// If a followup survey, go ahead and pre-populate the participants table and responses table with row for each record (if not already there)
	// This must be done in order for the follow-up survey's participant list to display fully
	// Find all records NOT in participants table yet
	$sub = "select r.record from redcap_surveys_participants p, redcap_surveys_response r 
			where p.survey_id = $survey_id and r.participant_id = p.participant_id 
			and p.event_id = $event_id and p.participant_email is not null";
	$sql = "select distinct record from redcap_data where project_id = " . PROJECT_ID . " 
			and field_name = '$table_pk' and record not in (" . pre_query($sub) . ")";
	if (count($Proj->events) > 1) {
		// Multiple arms exist, so only query records in current arm
		$eventIdsThisArm = array();
		foreach ($Proj->events as $this_arm_num=>$arm_attr) {
			if (isset($arm_attr['events'][$event_id])) {
				$eventIdsThisArm = array_keys($arm_attr['events']);
				break;
			}
		}
		if (!empty($eventIdsThisArm)) {
			$sql .= " and event_id in (".prep_implode($eventIdsThisArm).")";
		}
	}
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
		// Add row to participants table and responses table for this record (return value not needed here)
		Survey::getFollowupSurveyParticipantIdHash($survey_id, $row['record'], $event_id, true);
	}
	
	// Build participant list
	$part_list = array();
	$sql = "select p.* from redcap_surveys_participants p, redcap_surveys s 
			where p.survey_id = $survey_id and s.survey_id = p.survey_id and p.event_id = $event_id
			and p.participant_email is not null";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
		// Set with email, identifier, and basic defaults for counts
		$part_list[$row['participant_id']] = array( 
			'record'=>'', 'email'=>$row['participant_email'], 'identifier'=>$row['participant_identifier'],
			'phone'=>$row['participant_phone'], 'hash'=>$row['hash'], 'sent' =>0, 'response'=>0, 'return_code'=>'', 
			'scheduled'=>'', 'next_invite_is_reminder'=>0,
			'delivery_preference'=>($row['delivery_preference'] == '' ? 'EMAIL' : $row['delivery_preference'])
		);
	}

	// Query email invitations sent
	$sql = "select p.participant_id from redcap_surveys_emails e1, redcap_surveys_participants p, redcap_surveys_emails_recipients r 
			left join redcap_surveys_scheduler_queue q on q.email_recip_id = r.email_recip_id 
			where e1.survey_id = $survey_id and e1.email_id = r.email_id and p.survey_id = e1.survey_id 
			and p.participant_id = r.participant_id and p.event_id = $event_id 
			and ((q.ssq_id is not null and q.time_sent is not null) or (q.ssq_id is null and e1.email_sent is not null))";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
		$part_list[$row['participant_id']]['sent'] = 1;
	}

	// Query for any responses AND return codes
	$saveAndReturnEnabled = ($Proj->surveys[$survey_id]['save_and_return']);
	$sql = "select p.participant_id, r.first_submit_time, r.completion_time, r.return_code, r.record, p.participant_email
			from redcap_surveys_participants p, redcap_surveys_response r
			where p.survey_id = $survey_id and r.participant_id = p.participant_id and p.participant_email is not null 
			and p.event_id = $event_id";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{	
		$part_list[$row['participant_id']]['record'] = $row['record'];
		if ($row['participant_email'] === null) {
			// Initial survey
			$part_list[$row['participant_id']]['response'] = ($row['completion_time'] == "" ? 1 : 2);
		} else {
			// Followup surveys (participant_email will be '' not null)
			if ($row['completion_time'] == "" && $row['first_submit_time'] == "") {
				$part_list[$row['participant_id']]['response'] = 0;
			} elseif ($row['completion_time'] == "" && $row['first_submit_time'] != "") {
				$part_list[$row['participant_id']]['response'] = 1;
			} else {
				$part_list[$row['participant_id']]['response'] = 2;
			}
		}
		// If save and return enabled, then include return code, if exists.
		if ($saveAndReturnEnabled) {
			$part_list[$row['participant_id']]['return_code'] = $row['return_code'];
		}
	}
	
	// If this is an INITIAL SURVEY, then it is possible that a double entry in the response table exists if the response
	// was create via a Public Survey, so the completions timestamps (and thus completion status) may belong to the public survey
	// response but NOT to the unique link response. In that case, get all public survey response status and overwrite any 
	// that are missing as a unique link response status.
	if (!$isFollowUpSurvey) {
		$sql = "SELECT p.participant_id, if (rpub.completion_time is null, 1, 2) as response 
				FROM redcap_surveys_participants pub, redcap_surveys_response rpub, redcap_surveys_participants p, redcap_surveys_response r 
				where pub.participant_email is null and pub.participant_id = rpub.participant_id and rpub.first_submit_time is not null 
				and pub.survey_id = p.survey_id and pub.event_id = p.event_id and p.participant_id = r.participant_id 
				and r.record = rpub.record and p.event_id = $event_id and p.survey_id = $survey_id";
				// and (r.first_submit_time is null or (rpub.completion_time is not null and r.completion_time is null))";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			if (!isset($part_list[$row['participant_id']])) continue;
			// Add response status
			if ($part_list[$row['participant_id']]['response'] == 0) {
				$part_list[$row['participant_id']]['response'] = $row['response'];
			}
		}
	}

	// SCHEDULED: Query for any responses that have been scheduled via the Invitation Scheduler
	// Store the reminder_num of the next invitation
	$next_reminder_num = array();
	// Order by send time desc because there might be several reminders, and we want to capture the NEXT one in the array (as well as its reminder_num)
	$sql = "select p.participant_id, q.scheduled_time_to_send, q.reminder_num from redcap_surveys_participants p, 
			redcap_surveys_scheduler_queue q, redcap_surveys_emails_recipients r where p.survey_id = $survey_id 
			and p.event_id = $event_id and p.participant_email is not null and q.email_recip_id = r.email_recip_id 
			and p.participant_id = r.participant_id and q.status = 'QUEUED' order by q.scheduled_time_to_send desc";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
		$part_list[$row['participant_id']]['scheduled'] = $row['scheduled_time_to_send'];
		$next_reminder_num[$row['participant_id']] = $row['reminder_num'];
	}
	// Loop through reminder_nums and add next_invite_is_reminder for each if > 0
	foreach ($next_reminder_num as $this_part=>$this_reminder_num) {
		if ($this_reminder_num > 0) {
			$part_list[$this_part]['next_invite_is_reminder'] = 1;
		}
	}
	
	## OBTAIN EMAIL ADDRESSES FOR FOLLOWUP SURVEYS (SINCE THEY DON'T HAVE THEM NATURALLY)
	// Follow-up surveys will not have an email in the participants table, so pull from initial survey's Participant List (if exists there)
	// Store record as key so we can retrieve this survey's participan_id for this record later
	$partRecords = array();		
	foreach ($part_list as $this_part=>$attr) 
	{
		// If record is blank, then remove from participant list and do not add to array
		// (How did a blank record get here, btw?)
		if ($isFollowUpSurvey && $attr['record'] == '') {
			unset($part_list[$this_part]);
			continue;
		}
		// Add to array
		$partRecords[$attr['record']] = $this_part;
	}
	// Get all participant attributes for this followup survey
	$participantAttributes = Survey::getResponsesEmailsIdentifiers(array_keys($partRecords));
	// Now use that record list to get the original email from first survey's participant list
	foreach ($participantAttributes as $record=>$attr) {
		if (isset($part_list[$partRecords[$record]])) {
			// Add email and identifier
			$part_list[$partRecords[$record]]['email'] = $attr['email'];
			$part_list[$partRecords[$record]]['identifier'] = $attr['identifier'];
			$part_list[$partRecords[$record]]['phone'] = $attr['phone'];
			$part_list[$partRecords[$record]]['delivery_preference'] = $attr['delivery_preference'];
		}
	}
	
	// Order array first by email address then by participant_id
	foreach ($part_list as $this_part_id=>$attr) {
		$attr['participant_id'] = $this_part_id;
		unset($part_list[$this_part_id]);
		$part_list[$attr['email']."--".$this_part_id] = $attr;
	}
	natcaseksort($part_list);
	foreach ($part_list as $this_email_part_id=>$attr) {
		$this_part_id = $attr['participant_id'];
		unset($part_list[$this_email_part_id], $attr['participant_id']);
		$part_list[$this_part_id] = $attr;
	}
	
	
	// DUPLICATE EMAIL ADDRESSES: Track when there are email duplicates so we can pre-pend with #) when displaying it multiple times in table
	$part_list_duplicates = array();
	foreach ($part_list as $this_part_id=>$attr) {
		if ($attr['email'] == '') continue;
		// Set to lowercase to group same emails together regardless of case
		$attr['email'] = strtolower($attr['email']);
		if (isset($part_list_duplicates[$attr['email']])) {
			$part_list_duplicates[$attr['email']]['total']++;
		} else {
			$part_list_duplicates[$attr['email']]['total'] = 1;
			$part_list_duplicates[$attr['email']]['current'] = 1;
		}
	}
	
	// If user is in a DAG, only allow them to see participants in their DAG
	if ($user_rights['group_id'] != '')
	{
		// Validate DAG that user is in
		$dags = $Proj->getGroups();
		if (isset($dags[$user_rights['group_id']])) {
			$dag_records = Records::getData('array', array(), $table_pk, array(), $user_rights['group_id']);
			// Loop through participants and remove any that have records NOT in user's DAG
			foreach ($part_list as $this_part_id=>$attr) {
				// If record not in user's DAG, remove participant from array
				if ($attr['record'] != '' && !isset($dag_records[$attr['record']])) {
					unset($part_list[$this_part_id]);
				}
			}
		}
	}
	
	//print_array($part_list);
	
	// Return array
	return array($part_list, $part_list_duplicates);
}

// Returns array of record names from an array of participant_ids (with participant_id as array key)
// NOTE: For FOLLOWUP SURVEYS ONLY (assumes row exists in response table)
function getRecordFromPartId($partIds=array())
{
	$records = array();
	$sql = "select p.participant_id, r.record from redcap_surveys_participants p, redcap_surveys_response r
			where r.participant_id = p.participant_id and p.participant_id in (".prep_implode($partIds, false).")
			order by abs(r.record), r.record";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{	
		$records[$row['participant_id']] = $row['record'];
	}
	return $records;
}


// SEND CONFIRMATION EMAIL TO RESPONDENT
function sendSurveyConfirmationEmail($survey_id, $event_id, $record, $respondent_email_override=null)
{
	global $Proj;
	
	// Get survey attributes
	$survey_attr = $Proj->surveys[$survey_id];
	// Set boolean flag to determine if email sends
	$emailSentSuccessfully = false;
	
	// See if this is enabled
	if ($survey_attr['confirmation_email_subject'] != '' && $survey_attr['confirmation_email_content'] != '') 
	{
		// Get respondent's email, if we have it
		if ($respondent_email_override == null) {
			$emailsIdents = Survey::getResponsesEmailsIdentifiers(array($record));
			$respondent_email = $emailsIdents[$record]['email'];
		} else {
			$respondent_email = $respondent_email_override;
		}
		if (!empty($respondent_email)) {
			// Perform piping on subject and message
			$this_subject = strip_tags(Piping::replaceVariablesInLabel($survey_attr['confirmation_email_subject'], $record, $event_id));
			$this_content = Piping::replaceVariablesInLabel($survey_attr['confirmation_email_content'],  $record, $event_id);
			// Send email
			$email = new Message ();
			$email->setTo($respondent_email); 
			$email->setFrom($survey_attr['confirmation_email_from']);
			$email->setSubject($this_subject);
			$email->setBody('<html><body style="font-family:Arial;font-size:10pt;">'.nl2br($this_content).'</body></html>');
			if (is_numeric($survey_attr['confirmation_email_attachment'])) {
				## ATTACHMENT
				// Move file to temp directory using edoc_id
				$attachment_full_path = Files::copyEdocToTemp($survey_attr['confirmation_email_attachment']);
				// Add attachment
				$email->setAttachment($attachment_full_path);
			}
			$emailSentSuccessfully = $email->send();
			// Delete temp file, if applicable
			if (isset($attachment_full_path) && !empty($attachment_full_path)) {
				unlink($attachment_full_path);
			}
		}
	}
	
	// Return if email was sent
	return $emailSentSuccessfully;
}


// Send emails to survey admins when a survey is completed, if enabled for any admin
function sendEndSurveyEmails($survey_id, $event_id, $participant_id, $record)
{
	global $Proj, $redcap_version, $lang;
	
	// Get survey attributes
	$survey_title = strip_tags($Proj->surveys[$survey_id]['title']);
	
	## SEND EMAILS TO SURVEY ADMINS
	// Check if any emails need to be sent and to whom
	$sql = "select distinct(trim(if (a.action_response = 'EMAIL_TERTIARY', u.user_email3, 
				if (a.action_response = 'EMAIL_SECONDARY', u.user_email2, u.user_email)))) 
			as user_email from redcap_actions a, redcap_user_information u, redcap_user_rights r 
			where a.project_id = " . PROJECT_ID . " and a.survey_id = $survey_id and a.project_id = r.project_id 
			and r.username = u.username and a.action_trigger = 'ENDOFSURVEY' 
			and a.action_response in ('EMAIL_PRIMARY', 'EMAIL_SECONDARY', 'EMAIL_TERTIARY') and u.ui_id = a.recipient_id";
	$q = db_query($sql);
	if (db_num_rows($q) > 0)
	{	
		// If this participant has an identifier, display identifier name in email
		$identifier = "";
		$sql = "select participant_identifier from redcap_surveys_participants where participant_identifier is not null 
				and participant_identifier != '' and participant_id = $participant_id limit 1";
		$q2 = db_query($sql);
		if (db_num_rows($q2) > 0) {
			$identifier = "(" . db_result($q2, 0) . ") ";
		}
		// Initialize email
		$emailContents = "
			{$lang['survey_15']} {$identifier}{$lang['survey_16']} \"<b>$survey_title</b>\" {$lang['global_51']} ".date('m/d/Y g:ia').". 	
			{$lang['survey_17']} <a href='".APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/DataEntry/index.php?pid=".PROJECT_ID."&page={$_GET['page']}&event_id=$event_id&id=$record'>{$lang['survey_18']}</a>{$lang['period']}<br><br>	
			{$lang['survey_371']} <a href='".APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/Design/online_designer.php?pid=".PROJECT_ID."'>{$lang['design_25']}</a> 
			{$lang['survey_20']}";
		$email = new Message();	
		$email->setSubject('[REDCap] '.$lang['survey_21'].' "'.$survey_title.'"');
		$email->setBody($emailContents,true);
		// Loop through all applicable admins and send email to each
		while ($row = db_fetch_assoc($q))
		{
			$email->setTo($row['user_email']); 
			$email->setFrom($row['user_email']); // Have it send it from themself
			$email->send();
		}
	}
}

// Encrypt the survey participant's response id as a hash
function encryptResponseHash($response_id, $participant_id)
{
	global $__SALT__;
	return md5($__SALT__ . $response_id) . md5($__SALT__ . $participant_id);	
}

// Decrypt the survey participant's response hash as the response id
function decryptResponseHash($hash, $participant_id)
{
	global $__SALT__;
	// Make sure it's 64 chars long
	if (empty($hash) || (!empty($hash) && strlen($hash) != 64)) return '';
	// Break into two pieces
	$response_id_hash = substr($hash, 0, 32);
	$participant_id_hash = substr($hash, 32);
	// Verify participant_id value
	if ($participant_id_hash != md5($__SALT__ . $participant_id)) return '';
	// Now we must find the response_id by running a query to find it using one-way md5 hashing
	$sql = "select response_id from redcap_surveys_response where participant_id = $participant_id 
			and md5(concat('$__SALT__',response_id)) = '$response_id_hash' limit 1";
	$q = db_query($sql);
	if ($q) {
		// Return the response_id
		return db_result($q, 0);
	}
	return '';
}

// Obtain the response_hash value from the results code in the query string
function getResponseHashFromResultsCode($results_code, $participant_id)
{
	$sql = "select response_id from redcap_surveys_response where participant_id = $participant_id 
			and results_code = '".prep($results_code)."' limit 1";
	$q = db_query($sql);
	if ($q && db_num_rows($q))
	{
		$response_id = db_result($q, 0);
		if (is_numeric($response_id)) {
			return encryptResponseHash($response_id, $participant_id);
		}
	}
	return '';
}

// Encrypt the page number __page__ on the form in order to later verify against the real value
function getPageNumHash($page)
{
	global $__SALT__;
	return md5($__SALT__ . $page . $__SALT__);
}

// Verify that the page number hash is correct for the page number sent via Post
function verifyPageNumHash($hash, $page)
{
	return ($hash == getPageNumHash($page));
}

// GET RESPONSE ID: If $_POST['__response_hash__'] exists and is not empty, then set $_POST['__response_id__']
function initResponseId()
{
	global $participant_id;
	// If somehow __response_id__ was posted on form (it should NOT), then remove it here
	unset($_POST['__response_id__']);
	// If response_hash exists, convert to response_id
	if (isset($_POST['__response_hash__']) && !empty($_POST['__response_hash__']))
	{
		$_POST['__response_id__'] = decryptResponseHash($_POST['__response_hash__'], $participant_id);
		// Somehow it failed to get response_id, then unset it
		if (empty($_POST['__response_id__'])) unset($_POST['__response_id__']);
	}
}

// CHECK POSTED PAGE NUMBER (verify if correct to prevent gaming the system)
function initPageNumCheck()
{
	if (isset($_POST['__page__']))
	{
		if (!isset($_POST['__page_hash__']) || (isset($_POST['__page_hash__']) && !verifyPageNumHash($_POST['__page_hash__'], $_POST['__page__'])))
		{
			// Could not verify page hash, so set to 0 (so gets set to page 1)
			$_POST['__page__'] = 0;
		}
	}
	// Remove page_hash from Post
	unset($_POST['__page_hash__']);
	// If someone manually inserts __page__ into query string, then remove (illegal)
	unset($_GET['__page__']);
}


// Returns boolean with regard to if the record-survey-event is a completed survey response 
// (as opposed to a regular record or a partial response)
function isResponseCompleted($survey_id = null, $record = null, $event_id = null)
{
	// Check event_id/survey_id/record
	if (!is_numeric($event_id) || !is_numeric($survey_id) || $record == '') return false;
	// Query response table	
	$sql = "select 1 from redcap_surveys_participants p, redcap_surveys_response r
			where r.participant_id = p.participant_id and p.survey_id = $survey_id
			and p.event_id = $event_id and r.record = '" . prep($record) . "'
			and r.completion_time is not null limit 1";
	$q = db_query($sql);
	// Return true if found the response to be completed
	return (db_num_rows($q) > 0);
}

// REMOVE QUEUED SURVEY INVITATIONS
// If any participants have already been scheduled, then remove all those instances so they can be 
// scheduled again here (first part of query returns those where record=null - i.e. from initial survey 
// Participant List, and second part return those that are existing records).
function removeQueuedSurveyInvitations($survey_id, $event_id, $email_ids=array())
{
	$deleteErrors = 0;
	if (!empty($email_ids))
	{
		$ssq_ids_delete = array();
		$sql = "(select q.ssq_id from redcap_surveys_participants p, redcap_surveys_scheduler_queue q, 
				redcap_surveys_emails_recipients e where p.survey_id = $survey_id and p.event_id = $event_id 
				and p.participant_email is not null and q.email_recip_id = e.email_recip_id 
				and p.participant_id = e.participant_id and q.status = 'QUEUED' 
				and p.participant_id in (".prep_implode($email_ids, false)."))
				union
				(select q.ssq_id from redcap_surveys_participants p, redcap_surveys_response r, 
				redcap_surveys_scheduler_queue q, redcap_surveys_emails_recipients e where p.survey_id = $survey_id 
				and p.event_id = $event_id and r.participant_id = p.participant_id and p.participant_email is not null 
				and q.email_recip_id = e.email_recip_id and p.participant_id = e.participant_id and r.record = q.record 
				and q.status = 'QUEUED' and p.participant_id in (".prep_implode($email_ids, false)."))";
		$q = db_query($sql);
		if (db_num_rows($q) > 0) 
		{
			// Gather all ssq_id's and email_recip_id's into arrays so we know what to delete
			while ($row = db_fetch_assoc($q)) {
				$ssq_ids_delete[] = $row['ssq_id'];
			}
			// Delete those already scheduled in redcap_surveys_emails_recipients (this will cascade to also delete in redcap_surveys_scheduler_queue)
			$sql = "update redcap_surveys_scheduler_queue set status = 'DELETED' 
					where ssq_id in (".implode(",", $ssq_ids_delete).")";
			if (!db_query($sql)) $deleteErrors++;
		}
	}
	// Return false if errors occurred
	return ($deleteErrors > 0);
}