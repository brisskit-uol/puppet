<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

// If no survey id, assume it's the first form and retrieve
if (!isset($_GET['survey_id']))
{
	$_GET['survey_id'] = getSurveyId();
}

// Ensure the survey_id belongs to this project and that Post method was used
if (!checkSurveyProject($_GET['survey_id']) || $_SERVER['REQUEST_METHOD'] != "POST" || !isset($_POST['participants']))
{
	redirect(APP_PATH_WEBROOT . "Surveys/invite_participants.php?pid=" . PROJECT_ID);
}

// Ensure that participant_id's are all numerical
foreach (explode(",", $_POST['participants']) as $this_part)
{
	if (!is_numeric($this_part)) redirect(APP_PATH_WEBROOT . "Surveys/invite_participants.php?pid=" . PROJECT_ID);
}



// Check if this is a follow-up survey
$isFollowUpSurvey = ($_GET['survey_id'] != $Proj->firstFormSurveyId);

// Obtain current event_id
$_GET['event_id'] = getEventId();

// If cron is not running, then stop here
if (!Cron::checkIfCronsActive()) {
	print  "ERROR: The REDCap cron job is not running. The cron job must be running in order to send all survey invitations. 
			If you are not a REDCap administrator, then please notify your local REDCap administrator about this issue.";
	exit;
}

// Get the delivery type - default to EMAIL
$delivery_methods = Survey::getDeliveryMethods(true);
if (!$twilio_enabled || !isset($_POST['delivery_type']) || !isset($delivery_methods[$_POST['delivery_type']])) {
	$_POST['delivery_type'] = 'EMAIL';
}

// Get user info
$user_info = User::getUserInfo($userid);

// Set page header text
if ($_POST['emailSendTime'] != 'IMMEDIATELY') {
	if ($_POST['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $_POST['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL' || $_POST['delivery_type'] == 'SMS_INITIATE' || $_POST['delivery_type'] == 'PARTICIPANT_PREF') {
		// SMS
		$hdr_icon = ($_POST['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $_POST['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL' || $_POST['delivery_type'] == 'SMS_INITIATE') ? "<img src='".APP_PATH_IMAGES."phone.png' class='imgfix2'> ": "";
		renderPageTitle("$hdr_icon{$lang['survey_708']}");
	} elseif ($_POST['delivery_type'] == 'VOICE_INITIATE') {
		// VOICE
		renderPageTitle("<img src='".APP_PATH_IMAGES."phone.gif' class='imgfix2'> {$lang['survey_709']}");
	} else {
		// EMAIL
		renderPageTitle("<img src='".APP_PATH_IMAGES."clock_frame.png' class='imgfix2'> {$lang['survey_334']}");
	}
} else {
	if ($_POST['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $_POST['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL' || $_POST['delivery_type'] == 'SMS_INITIATE' || $_POST['delivery_type'] == 'PARTICIPANT_PREF') {
		// SMS
		$hdr_icon = ($_POST['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $_POST['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL' || $_POST['delivery_type'] == 'SMS_INITIATE') ? "<img src='".APP_PATH_IMAGES."phone.png' class='imgfix2'> ": "";
		renderPageTitle("$hdr_icon{$lang['survey_698']}");
	} elseif ($_POST['delivery_type'] == 'VOICE_INITIATE') {
		// VOICE
		renderPageTitle("<img src='".APP_PATH_IMAGES."phone.gif' class='imgfix2'> {$lang['survey_699']}");
	} else {
		// EMAIL
		renderPageTitle("<img src='".APP_PATH_IMAGES."email.png' class='imgfix2'> {$lang['survey_138']}");
	}
}


// Get email address for each participant_id (whether it's an initial survey or follow-up survey)
$participant_emails_ids = $participant_delivery_pref = array();
if ($isFollowUpSurvey) {
	// Follow-up surveys (may not have an email stored for this specific survey/event, so can't simply query participants table)
	$participant_records = getRecordFromPartId(explode(",",$_POST['participants']));
	$responseAttr = Survey::getResponsesEmailsIdentifiers($participant_records);
	foreach ($participant_records as $partId=>$record) {
		$participant_emails_ids[$partId] = $responseAttr[$record]['email'];
		$participant_delivery_pref[$partId] = $responseAttr[$record]['delivery_preference'];
	}
} else {
	// Initial survey: Obtain email from participants table
	$sql = "select participant_email, participant_id, delivery_preference from redcap_surveys_participants 
			where survey_id = {$_GET['survey_id']} and participant_id in ({$_POST['participants']})";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		$participant_emails_ids[$row['participant_id']] = $row['participant_email'];
		$participant_delivery_pref[$row['participant_id']] = $row['delivery_preference'];
	}
	$participant_records = getRecordFromPartId(array_keys($participant_emails_ids));
}


// Set the From address for the emails sent
$fromEmailTemp = 'user_email' . ($_POST['emailFrom'] > 1 ? $_POST['emailFrom'] : '');
$fromEmail = $$fromEmailTemp;
if (!isEmail($fromEmail)) $fromEmail = $user_email;

// Set some value for insert query into redcap_surveys_emails
$emailsTableSendTime = "";
$emailsTableStaticEmail = ($_POST['emailSendTime'] != 'IMMEDIATELY') ? $fromEmail : "";

// Perform filtering on email subject/content
$_POST['emailCont']  = filter_tags(label_decode($_POST['emailCont']));
$_POST['emailTitle'] = filter_tags(label_decode($_POST['emailTitle']));


## PIPING CHECK: See if any fields have been inserted into the subject or content for piping purposes
$doPiping = $doPipingContent = $doPipingSubject = false;
$piping_fields = array();
// EMAIL CONTENT PIPING
if (!empty($participant_records)) 
{
	if (strpos($_POST['emailCont'], '[') !== false && strpos($_POST['emailCont'], ']') !== false) {
		// Parse the label to pull out the field names
		$piping_fields_content = array_keys(getBracketedFields($_POST['emailCont'], true, true, true));
		// Validate the field names
		foreach ($piping_fields_content as $key=>$this_field) {
			// If not a valid field name, then remove
			if (!isset($Proj->metadata[$this_field])) unset($piping_fields_content[$key]);
		}
		// Set flag to true if some fields were indeed piped
		if (!empty($piping_fields_content)) $doPiping = $doPipingContent = true;
	}
	// EMAIL SUBJECT PIPING
	if (strpos($_POST['emailTitle'], '[') !== false && strpos($_POST['emailTitle'], ']') !== false) {
		// Parse the label to pull out the field names
		$piping_fields_subject = array_keys(getBracketedFields($_POST['emailTitle'], true, true, true));
		// Validate the field names
		foreach ($piping_fields_subject as $key=>$this_field) {
			// If not a valid field name, then remove
			if (!isset($Proj->metadata[$this_field])) unset($piping_fields_subject[$key]);
		}
		// Set flag to true if some fields were indeed piped
		if (!empty($piping_fields_subject)) $doPiping = $doPipingSubject = true;
	}
}


// Initialize array where key will be participant_id and value will be email_id
$email_ids_by_participant = array();

if (!$doPiping) 
{
	// NO PIPING, so add email info to table only as a single row
	// OR sending via ajax because Cron is not working, so it will insert any piped data in ajax request.
	$sql = "insert into redcap_surveys_emails (survey_id, email_subject, email_content, email_sender, 
			email_account, email_static, email_sent, delivery_type) values 
			({$_GET['survey_id']}, '" . prep($_POST['emailTitle']) . "', 
			'" . prep($_POST['emailCont']) . "', {$user_info['ui_id']}, 
			'" . prep($_POST['emailFrom']) . "', ".checkNull($emailsTableStaticEmail).", ".checkNull($emailsTableSendTime).", 
			'" . prep($_POST['delivery_type']) . "')";
	db_query($sql);
	// Get email_id
	$email_id = db_insert_id();
	// Loop through each participant and add the same email_id to all in $email_ids_by_participant
	foreach (array_keys($participant_emails_ids) as $this_part) {
		$email_ids_by_participant[$this_part] = $email_id;
	}
	// Logging
	log_event($sql,"redcap_surveys_emails","MANAGE",null,"email_id = $email_id,\nsurvey_id = {$_GET['survey_id']},\nevent_id = {$_GET['event_id']}","Email survey participants");
} 
else 
{
	// DO PIPING, so insert once for EACH participant to record each's unique values for email subject/content
	// Get array of all piping fields for data pull
	$piping_fields = array_unique(array_merge($piping_fields_subject, $piping_fields_content));
	// Loop through each participant/record and customize the email subject/content with piped data
	foreach ($participant_records as $this_part=>$this_record) 
	{
		// Set subject
		$this_subject = ($doPipingSubject) ? strip_tags(Piping::replaceVariablesInLabel($_POST['emailTitle'], $this_record, $_GET['event_id'])) : $_POST['emailTitle'];
		// Set content
		$this_content = ($doPipingContent) ? Piping::replaceVariablesInLabel($_POST['emailCont'], $this_record, $_GET['event_id'], array(), true, null, false) : $_POST['emailCont'];
		// Insert email into table
		$sql = "insert into redcap_surveys_emails (survey_id, email_subject, email_content, email_sender, 
				email_account, email_static, email_sent, delivery_type) values 
				({$_GET['survey_id']}, '" . prep($this_subject) . "', 
				'" . prep($this_content) . "', {$user_info['ui_id']}, 
				'" . prep($_POST['emailFrom']) . "', ".checkNull($emailsTableStaticEmail).", ".checkNull($emailsTableSendTime).", 
				'" . prep($_POST['delivery_type']) . "')";
		db_query($sql);
		// Get email_id
		$email_id = db_insert_id();
		// Add to array
		$email_ids_by_participant[$this_part] = $email_id;
	}
	// Logging
	log_event($sql,"redcap_surveys_emails","MANAGE",null,"email_ids = ".implode(",", $email_ids_by_participant).",\nsurvey_id = {$_GET['survey_id']},\nevent_id = {$_GET['event_id']}","Email survey participants");
}
	
	
// Get count of recipients
$recipCount = count($participant_emails_ids);


## SCHEDULE ALL EMAILS: Since cron is running, offload all emails to the cron emailer (even those to be sent immediately)
// If specified exact date/time, convert timestamp from mdy to ymd for saving in backend
if ($_POST['emailSendTimeTS'] != '') {
	list ($this_date, $this_time) = explode(" ", $_POST['emailSendTimeTS']);
	$_POST['emailSendTimeTS'] = trim(DateTimeRC::format_ts_to_ymd($this_date) . " $this_time:00");
}

// Set the send time for the emails
$sendTime = ($_POST['emailSendTime'] == 'IMMEDIATELY') ? NOW : $_POST['emailSendTimeTS'];

## REMOVE INVITATIONS ALREADY QUEUED: If any participants have already been scheduled, 
## then remove all those instances so they can be scheduled again here (first part of query returns those where
## record=null - i.e. from initial survey Participant List, and second part return those that are existing records).
removeQueuedSurveyInvitations($_GET['survey_id'], $_GET['event_id'], array_keys($participant_emails_ids));



## REMINDERS
$participantSendTimes = array(0=>$sendTime);
## If reminders are enabled, then add times of all reminders in array
$addReminders = (isset($_POST['reminder_type']) && $_POST['reminder_type'] != '');
if ($addReminders) {
	// Set reminder num
	if (!is_numeric($_POST['reminder_num'])) $_POST['reminder_num'] = 1;
	// Loop through each reminder
	$thisReminderTime = $sendTime;
	for ($k = 1; $k <= $_POST['reminder_num']; $k++) {
		// Get reminder time for next reminder
		$participantSendTimes[$k] = $thisReminderTime = SurveyScheduler::calculateReminderTime($_POST, $thisReminderTime);
	}
}


## ADD PARTICIPANTS TO THE EMAIL QUEUE (i.e. the emails_recipients table - since email_sent=NULL)
$insertErrors = 0;
//print_array($participant_emails_ids);exit;
foreach (array_keys($participant_emails_ids) as $this_part) 
{
	// If using participant preference for "delivery method", then get the person's delivery preference
	if ($_POST['delivery_type'] == 'PARTICIPANT_PREF') {
		$this_part_pref = (isset($participant_delivery_pref[$this_part])) ? $participant_delivery_pref[$this_part] : 'EMAIL' ;
	} else {
		$this_part_pref = $_POST['delivery_type'];
	}
	// Add to emails_recipients table
	$sql = "insert into redcap_surveys_emails_recipients (email_id, participant_id, delivery_type) 
			values (".$email_ids_by_participant[$this_part].", $this_part, '" . prep($this_part_pref) . "')";
	if (db_query($sql)) {
		// Get email_recip_id
		$email_recip_id = db_insert_id();
		// Get record name (may not have one if this is an initial survey's Participant List)
		$this_record = (isset($participant_records[$this_part])) ? $participant_records[$this_part] : "";
		// Now add to scheduler_queue table (loop through orig invite + any reminder invites)
		foreach ($participantSendTimes as $reminder_num=>$thisSendTime) {
			$sql = "insert into redcap_surveys_scheduler_queue (email_recip_id, record, scheduled_time_to_send, reminder_num) 
					values ($email_recip_id, ".checkNull($this_record).", '".prep($thisSendTime)."', '".prep($reminder_num)."')";
			if (!db_query($sql)) $insertErrors++;
		}
	} else {
		$insertErrors++;
	}
}

// Confirmation text for IMMEDIATE sending
if ($_POST['emailSendTime'] == 'IMMEDIATELY') 
{
	if ($_POST['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $_POST['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL' || $_POST['delivery_type'] == 'SMS_INITIATE' || $_POST['delivery_type'] == 'PARTICIPANT_PREF') {
		// SMS
		print 	RCView::div(array('style'=>'font-size:13px;margin-bottom:10px;'), $lang['survey_694']) .
				RCView::div(array('style'=>'font-weight:bold;'),
					RCView::span(array('style'=>'margin-right:15px;'), 
						($recipCount > 1 ? "$recipCount {$lang['survey_696']}" : "$recipCount {$lang['survey_697']}")
					) .
					RCView::img(array('src'=>'accept.png','class'=>'imgfix')) .
					RCView::span(array('style'=>'color:green;'), $lang['survey_695'])
				);			
	} elseif ($_POST['delivery_type'] == 'VOICE_INITIATE') {
		// VOICE
		print 	RCView::div(array('style'=>'font-size:13px;margin-bottom:10px;'), $lang['survey_700']) .
				RCView::div(array('style'=>'font-weight:bold;'),
					RCView::span(array('style'=>'margin-right:15px;'), 
						($recipCount > 1 ? "$recipCount {$lang['survey_702']}" : "$recipCount {$lang['survey_703']}")
					) .
					RCView::img(array('src'=>'accept.png','class'=>'imgfix')) .
					RCView::span(array('style'=>'color:green;'), $lang['survey_701'])
				);			
	} else {
		// EMAIL
		print 	RCView::div(array('style'=>'font-size:13px;margin-bottom:10px;'), $lang['survey_328']) .
				RCView::div(array('style'=>'font-weight:bold;'),
					RCView::span(array('style'=>'margin-right:15px;'), 
						($recipCount > 1 ? "$recipCount {$lang['survey_696']}" : "$recipCount {$lang['survey_697']}")
					) .
					RCView::img(array('src'=>'accept.png','class'=>'imgfix')) .
					RCView::span(array('style'=>'color:green;'), $lang['survey_329'])
				);
	}
} 		
// Confirmation text for SCHEDULING the emails to be sent
else
{
	if ($_POST['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $_POST['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL' || $_POST['delivery_type'] == 'SMS_INITIATE' || $_POST['delivery_type'] == 'PARTICIPANT_PREF') {
		// SMS
		print 	RCView::div(array('style'=>'font-size:13px;margin-bottom:10px;'), $lang['survey_704']) .
				RCView::div(array('style'=>'font-weight:bold;'),
					RCView::img(array('src'=>'accept.png','class'=>'imgfix')) .
					RCView::span(array('style'=>'color:green;'), $lang['survey_705']) .
					RCView::div(array('style'=>'padding:15px 0 0 5px;'),
						"$recipCount {$lang['survey_333']} " . 
						RCView::span(array('style'=>'color:#800000;'), DateTimeRC::format_ts_from_ymd($sendTime))
					)
				);
	} elseif ($_POST['delivery_type'] == 'VOICE_INITIATE') {
		// VOICE
		print 	RCView::div(array('style'=>'font-size:13px;margin-bottom:10px;'), $lang['survey_706']) .
				RCView::div(array('style'=>'font-weight:bold;'),
					RCView::img(array('src'=>'accept.png','class'=>'imgfix')) .
					RCView::span(array('style'=>'color:green;'), $lang['survey_707']) .
					RCView::div(array('style'=>'padding:15px 0 0 5px;'),
						"$recipCount {$lang['survey_333']} " . 
						RCView::span(array('style'=>'color:#800000;'), DateTimeRC::format_ts_from_ymd($sendTime))
					)
				);
	} else {
		// EMAIL
		print 	RCView::div(array('style'=>'font-size:13px;margin-bottom:10px;'), $lang['survey_331']) .
				RCView::div(array('style'=>'font-weight:bold;'),
					RCView::img(array('src'=>'accept.png','class'=>'imgfix')) .
					RCView::span(array('style'=>'color:green;'), $lang['survey_332']) .
					RCView::div(array('style'=>'padding:15px 0 0 5px;'),
						"$recipCount {$lang['survey_333']} " . 
						RCView::span(array('style'=>'color:#800000;'), DateTimeRC::format_ts_from_ymd($sendTime))
					)
				);
	}
}