<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
require_once APP_PATH_DOCROOT . "Surveys/survey_functions.php";

// Validate values
if (!is_numeric($_POST['email_recip_id']) || !is_numeric($_POST['reminder_num']) || !isset($_POST['action'])) exit("0");

// Defaults
$content = '';
$title   = '';

// View confirmation to delete invitation
if ($_POST['action'] == 'view_delete') 
{
	// Obtain timestamp of invitation and sender email
	$sql = "select q.scheduled_time_to_send, 
			if (p.participant_email is null, r.static_email, p.participant_email) as email 
			from redcap_surveys_scheduler_queue q, redcap_surveys_participants p, 
			redcap_surveys_emails_recipients r where q.email_recip_id = r.email_recip_id 
			and r.participant_id = p.participant_id and r.email_recip_id = ".$_POST['email_recip_id']."
			and q.reminder_num = ".$_POST['reminder_num'];
	$q = db_query($sql);
	if (!db_num_rows($q)) exit("0");
	// Set values
	$email = db_result($q, 0, 'email');
	$sendtime = db_result($q, 0, 'scheduled_time_to_send');
	$title = $lang['survey_486'];
	$content = 	$lang['survey_487'] . " " . RCView::b($email) . " " . $lang['global_51'] . " " . 
				RCView::b(DateTimeRC::format_ts_from_ymd($sendtime)) . $lang['questionmark'] . " " . $lang['survey_489'];
}

// Delete the invitation
elseif ($_POST['action'] == 'delete') 
{
	// Mark it as deleted in the scheduler_queue table
	$sql = "update redcap_surveys_scheduler_queue set status = 'DELETED'
			where email_recip_id = ".$_POST['email_recip_id']." and reminder_num = ".$_POST['reminder_num']." limit 1";
	if (!db_query($sql)) exit("0");
	$title = $lang['survey_486'];
	$content = 	RCView::div(array('style'=>'color:green;'),
					RCView::img(array('src'=>'tick.png','class'=>'imgfix')) .
					$lang['survey_488']
				);
}

// View confirmation to edit invitation time
if ($_POST['action'] == 'view_edit_time') 
{
	// Obtain timestamp of invitation and sender email
	$sql = "select q.scheduled_time_to_send,
			if (p.participant_email is null, r.static_email, p.participant_email) as email 
			from redcap_surveys_scheduler_queue q, redcap_surveys_participants p, 
			redcap_surveys_emails_recipients r where q.email_recip_id = r.email_recip_id 
			and r.participant_id = p.participant_id and r.email_recip_id = ".$_POST['email_recip_id']."
			and q.reminder_num = ".$_POST['reminder_num']." limit 1";
	$q = db_query($sql);
	if (!db_num_rows($q)) exit("0");
	// Set values
	$email = db_result($q, 0, 'email');
	$sendtime = substr(db_result($q, 0, 'scheduled_time_to_send'), 0, -3);
	list ($sendtime_date, $sendtime_time) = explode(" ", $sendtime, 2);
	$title = $lang['survey_490'];
	$content = 	$lang['survey_491'] . " " . RCView::b($email) . " " . $lang['global_51'] . " " . 
				RCView::b(DateTimeRC::format_ts_from_ymd($sendtime)) . $lang['questionmark'] . " " . $lang['survey_492'] .
				RCView::div(array('style'=>'padding-top:20px;'),
					RCView::b($lang['survey_493']) . RCView::br() . 
					RCView::text(array('id'=>'newInviteTime','value'=>DateTimeRC::format_ts_from_ymd($sendtime_date) . " $sendtime_time",
						'onblur'=>"if(redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter)) window.newInviteTime=this.value;",
						'class'=>'x-form-text x-form-field','style'=>'width:103px;')) .
					"<span class='df'>".DateTimeRC::get_user_format_label()." H:M</span>"
				);
}

// Edit invitation time
elseif ($_POST['action'] == 'edit_time') 
{
	$sendtime = DateTimeRC::format_ts_to_ymd(trim($_POST['newInviteTime']));
	// Edit the time in scheduler_queue table
	$sql = "update redcap_surveys_scheduler_queue set scheduled_time_to_send = '".prep($sendtime).":00'
			where email_recip_id = ".$_POST['email_recip_id']." and reminder_num = ".$_POST['reminder_num']." 
			order by ssq_id desc limit 1";
	if (!db_query($sql)) exit("0");
	$title = $lang['survey_490'];
	$content = 	RCView::div(array('style'=>'color:green;'),
					RCView::img(array('src'=>'tick.png','class'=>'imgfix')) .
					$lang['survey_494']
				);
}


// Return JSON
print '{"content":"'.cleanHtml2($content).'","title":"'.cleanHtml2($title).'"}';