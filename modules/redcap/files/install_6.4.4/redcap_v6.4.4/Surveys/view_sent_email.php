<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
require_once APP_PATH_DOCROOT . "Surveys/survey_functions.php";

// Validate email_recip_id
if (!is_numeric($_POST['email_recip_id'])) exit("0");

// Get email info
$sql = "select e.*, p.participant_id, p.hash, r.delivery_type as this_delivery_type,
		if (r.static_email is null, p.participant_email, r.static_email) as participant_email ,
		if (r.static_phone is null, p.participant_phone, r.static_phone) as participant_phone 
		from redcap_surveys s, redcap_surveys_emails e, redcap_surveys_emails_recipients r, redcap_surveys_participants p 
		where s.project_id = ".PROJECT_ID." and s.survey_id = e.survey_id and e.email_id = r.email_id 
		and r.email_recip_id = {$_POST['email_recip_id']} and p.participant_id = r.participant_id 
		and p.survey_id = s.survey_id limit 1";
$q = db_query($sql);
if (!db_num_rows($q)) exit("0");

// Set values as array
$email = db_fetch_assoc($q);

// Set "time sent"
$sendMethod = 'manual'; // Set default as manually sent from participant list
$time_sent = "";
// Since don't have a "time sent", get it from another table if scheduled or if failed to send
$sql = "select ss_id, scheduled_time_to_send, status, time_sent, reminder_num 
		from redcap_surveys_scheduler_queue 
		where email_recip_id = {$_POST['email_recip_id']}";
if ($_POST['ssq_id'] != '') $sql .= " and ssq_id = ".$_POST['ssq_id'];
$q = db_query($sql);
if (db_num_rows($q) > 0) {
	$emailScheduleInfo = db_fetch_assoc($q);
	// Set send method to "automatic invites" (i.e. auto)
	if (is_numeric($emailScheduleInfo['ss_id'])) {
		$sendMethod = 'auto';
	}
	// Scheduled to send at specific time
	if ($emailScheduleInfo['status'] == 'QUEUED') {
		$time_sent = RCView::span(array('style'=>'color:#777;'), 
						RCView::img(array('src'=>'clock_fill.png','style'=>'vertical-align:middle;')) .
						$lang['survey_394'] . RCView::SP . DateTimeRC::format_ts_from_ymd($emailScheduleInfo['scheduled_time_to_send'])
					 );
	} elseif ($emailScheduleInfo['status'] == 'SENT') {
		$time_sent = RCView::span(array('style'=>'color:green;'), 
						DateTimeRC::format_ts_from_ymd($emailScheduleInfo['time_sent'])
					 );
	} elseif ($emailScheduleInfo['status'] == 'DID NOT SEND') {
		$time_sent = RCView::span(array('style'=>'color:red;'), 
						DateTimeRC::format_ts_from_ymd($emailScheduleInfo['scheduled_time_to_send']) . RCView::SP . RCView::SP . $lang['survey_396']
					 );
	}
}
if ($time_sent == "" && $email['email_sent'] != "") {
	$time_sent = RCView::span(array('style'=>'color:green;'), DateTimeRC::format_ts_from_ymd($email['email_sent']));
}

// Set text if sent manually via participant list or via automatic invites
$sendMethodMsg = ($sendMethod == 'manual') ? $lang['survey_400'] : $lang['survey_401'];
$sendMethodText = RCView::span(array('style'=>'color:#444;font-weight:normal;'), $sendMethodMsg) . RCView::SP;

// Set email subject
$subjectEmail = label_decode($email['email_subject']);
// If a reminder, then add [Reminder] to subject line
if (is_numeric($emailScheduleInfo['reminder_num']) && $emailScheduleInfo['reminder_num'] > 0) {
	$subjectEmail = $lang['survey_732'] . " $subjectEmail";
}
// Limit to 240 chars since that's our limit in Message.php
$subjectEmail = substr($subjectEmail, 0, 240); 
if ($subjectEmail == "") {
	$subjectEmail = RCView::span(array('style'=>'color:#777;font-weight:normal;'), $lang['survey_397']);
}

// Set "from" email address
$username_name = $fromEmail = "";
if (is_numeric($email['email_sender']) && is_numeric($email['email_account'])) {
	// Get username, name, and email address from the user's account
	$senderInfo = User::getUserInfoByUiid($email['email_sender']);
	// Set the from email address as the user's CURRENT email
	$fromEmail = ($email['email_account'] == '1') ? $senderInfo['user_email'] : $senderInfo['user_email'.$email['email_account']];
	// Set name and username string
	$username_name = "{$senderInfo['username']} &nbsp;(" .
		RCView::a(array('href'=>"mailto:$fromEmail",'style'=>'text-decoration:underline;font-weight:normal;'), 
			"{$senderInfo['user_firstname']} {$senderInfo['user_lastname']}"
		) .
		")";
	$fromEmailText = "";
}
// If static email address was used, then use it instead of user's current email address
if ($fromEmail == "" && $email['email_static'] != "") {
	$fromEmailText = $email['email_static'];
}

// See if participant has already created a record. If so, get email/identifier/record name.
$recordArray = getRecordFromPartId(array($email['participant_id']));
$record = (isset($recordArray[$email['participant_id']])) ? $recordArray[$email['participant_id']] : "";
$emailIdentArray = ($record == "") ? array() : Survey::getResponsesEmailsIdentifiers(array($record)); 

// Set "to" email address or phone
if ($email['this_delivery_type'] == 'EMAIL') {
	$toEmail = $email['participant_email'];
	if ($toEmail == "") {
		// Since didn't find email from static address or initial survey pariticpant list, use other methods to obtain it
		$toEmail = $emailIdentArray[$record]['email'];
		if ($toEmail == "") $toEmail = RCView::span(array('style'=>'color:#777;'), $lang['survey_284']);
	}
} else {
	$toEmail = $email['participant_phone'];
	if ($toEmail == "") {
		// Since didn't find email from static address or initial survey pariticpant list, use other methods to obtain it
		$toEmail = formatPhone($emailIdentArray[$record]['phone']);
		if ($toEmail == "") $toEmail = RCView::span(array('style'=>'color:#777;'), $lang['survey_284']);
	}
}

// If email/phone is from Participant List and identifiers are disabled and survey email/phone field is disabled, 
// then do NOT display the email/phone if has no identifier.
if (!$enable_participant_identifiers && $record != "" && isset($emailIdentArray[$record]) && $emailIdentArray[$record]['identifier'] == "" 
	&& (($email['this_delivery_type'] == 'EMAIL' && $survey_email_participant_field == '' && $emailIdentArray[$record]['email'] != "")
		|| ($email['this_delivery_type'] != 'EMAIL' && $survey_phone_participant_field == '' && $emailIdentArray[$record]['phone'] != ""))) 
{
	$toEmail = ($email['this_delivery_type'] == 'EMAIL') ? $lang['survey_499'] : $lang['survey_903'];
}

// Invitation message
$invitation_msg = nl2br(label_decode($email['email_content']));
if ($email['this_delivery_type'] == 'EMAIL') {
	$invitation_msg .=	'<br /><br />	
						'.$lang['survey_134'].'<br />
						<a target="_blank" style="text-decoration:underline;" href="' . APP_PATH_SURVEY_FULL . '?s=' . $email['hash'] . '">'.
						($Proj->surveys[$email['survey_id']]['title'] == "" ? APP_PATH_SURVEY_FULL . '?s=' . $email['hash'] : $Proj->surveys[$email['survey_id']]['title']).'</a><br /><br />
						'.$lang['survey_135'].'<br />
						'.APP_PATH_SURVEY_FULL.'?s='.$email['hash'].'<br /><br />	
						'.$lang['survey_137'];
} elseif ($email['this_delivery_type'] == 'SMS_INVITE_MAKE_CALL') {
	if ($invitation_msg != '') $invitation_msg .= " -- ";
	$invitation_msg .= $lang['survey_863'] . " " . formatPhone($twilio_from_number);
} elseif ($email['this_delivery_type'] == 'SMS_INVITE_RECEIVE_CALL') {
	if ($invitation_msg != '') $invitation_msg .= " -- ";
	$invitation_msg .= $lang['survey_866'];
} else {
	if ($invitation_msg != '') $invitation_msg .= " -- ";
	$invitation_msg .= $lang['survey_865'];
}

// Set dialog content
$content = 	RCView::div(array('style'=>'padding:2px 7px;'),	
				RCView::div(array('style'=>'margin-bottom:10px;padding-bottom:15px;border-bottom:1px solid #ddd;'),	
					$lang['survey_398']
				) .
				RCView::table(array('cellspacing'=>'0','border'=>'0','style'=>'table-layout:fixed;width:100%;'),
					// Time sent
					RCView::tr('',
						RCView::td(array('style'=>'vertical-align:top;width:70px;color:#777;'),
							$lang['survey_395']
						) .
						RCView::td(array('style'=>'vertical-align:top;font-weight:bold;'),
							$time_sent
						)
					) .
					// From 
					RCView::tr('',
						RCView::td(array('style'=>'vertical-align:middle;width:70px;padding-top:10px;color:#777;'),
							$lang['global_37']
						) .
						RCView::td(array('style'=>'vertical-align:middle;padding-top:10px;font-weight:bold;'),
							$sendMethodText . $username_name . $fromEmailText
						)
					) .
					// To
					RCView::tr('',
						RCView::td(array('style'=>'vertical-align:middle;width:70px;padding-top:10px;color:#777;'),
							$lang['global_38']
						) .
						RCView::td(array('style'=>'vertical-align:middle;padding-top:10px;font-weight:bold;color:#800000;'),
							$toEmail
						)
					) .
					// Subject (for emails only)
					RCView::tr(array('style'=>($email['this_delivery_type'] == 'EMAIL' ? '' : 'display:none;')),
						RCView::td(array('style'=>'vertical-align:top;padding-top:10px;width:70px;color:#777;'),
							$lang['survey_103']
						) .
						RCView::td(array('style'=>'vertical-align:top;padding-top:10px;font-weight:bold;'),
							$subjectEmail
						)
					) .
					// Message
					RCView::tr('',
						RCView::td(array('colspan'=>'2','style'=>'padding-top:15px;vertical-align:top;'),
							RCView::div(array('style'=>($email['this_delivery_type'] == 'EMAIL' ? 'height:200px;' : 'height:60px;').'overflow:auto;padding:10px;border:1px solid #ddd;background-color:#f5f5f5;'),	
								$invitation_msg
							)
						)
					)
				)
			);

// Return JSON
print '{"content":"'.cleanHtml2($content).'","title":"'.cleanHtml2($lang['survey_393']).'"}';