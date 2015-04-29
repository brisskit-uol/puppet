<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
require_once APP_PATH_DOCROOT . "Surveys/survey_functions.php";

// If not using a type of project with surveys, then don't allow user to use this page.
if (!$surveys_enabled) redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");

// If no survey id in URL, then determine what it should be here (first available survey_id)
if (!isset($_GET['survey_id']))
{
	if ($Proj->firstFormSurveyId != null) {
		// Get first form's survey_id
		$_GET['survey_id'] = getSurveyId();
	} elseif (!empty($Proj->surveys)) {
		// Surveys exist, but the first form is not a survey. So get the first available survey_id (exclude any "deleted"/orphaned survey instruments)
		foreach ($Proj->surveys as $this_survey_id=>$attr) {
			if (isset($Proj->forms[$attr['form_name']])) {
				$_GET['survey_id'] = $this_survey_id;
				break;
			}
		}
		// If first form isn't a survey and user didn't explicity click the Public Survey Link tab, then redirect on to Participant List
		if (!isset($_GET['public_survey']) && !isset($_GET['participant_list']) && !isset($_GET['email_log'])) {
			redirect(PAGE_FULL . "?pid=$project_id&participant_list=1");
		}
	} elseif (empty($Proj->surveys)) {
		// If no surveys have been enabled, then redirect to Online Designer to enable them
		redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&dialog=enable_surveys");
	}
}

// Ensure the survey_id belongs to this project
if (!$Proj->validateSurveyId($_GET['survey_id']))
{
	redirect(APP_PATH_WEBROOT . "Surveys/create_survey.php?pid=$project_id&view=showform&redirectInvite=1");
}

// Obtain current event_id
if (!isset($_GET['event_id'])) {
	$_GET['arm_id']   = getArmId();
	$_GET['event_id'] = $Proj->getFirstEventIdArmId($_GET['arm_id']);
}

// Retrieve survey info
$q = db_query("select * from redcap_surveys where project_id = $project_id and survey_id = " . $_GET['survey_id']);
foreach (db_fetch_assoc($q) as $key => $value)
{
	$$key = trim(html_entity_decode($value, ENT_QUOTES));
}
	
// VALIDATE EVENT_ID FOR SURVEY: If event_id isn't applicable for the survey_id that we have here, then get first event_id that is applicable.
if (!$Proj->validateEventIdSurveyId($_GET['event_id'], $_GET['survey_id']))
{
	// Get first event for this survey
	foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
		if (in_array($Proj->surveys[$_GET['survey_id']]['form_name'], $these_forms)) {
			$_GET['event_id'] = $this_event_id;
			break;
		}
	}
}

// Check if this is a follow-up survey
$isFollowUpSurvey = $Proj->isFollowUpSurvey($_GET['survey_id']);

// Get all previously sent emails to put into hidden Dropdown Menu in pop-up dialog for sending participant emails
$emailSelect = array();
$sql = "select email_content, email_sent from redcap_surveys_emails where survey_id = {$_GET['survey_id']} order by email_id";
$q = db_query($sql);		
$divDispPrevEmails_display = (!db_num_rows($q) ? "display:none;" : "");
//Loop through query
while ($row = db_fetch_array($q)) 
{
	//Remove HTML tags and quotes because they cause problems
	$row['email_content'] = str_replace("\"","&quot;", RCView::escape(label_decode($row['email_content']),false));
	//Do not show repeating emails (if same email was sent more than once)
	if (!isset($emailSelect[$row['email_content']])) 
	{
		// Make sure text is not too long and format timestamp
		$this_val = DateTimeRC::format_ts_from_ymd($row['email_sent']).' - '.$row['email_content'];
		if (strlen($this_val) >= 85) $this_val = substr($this_val, 0, 83).'...';
		// Store in array
		$emailSelect[$row['email_content']] = $this_val;
	}
}



// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
// Call JS files
callJSfile("swfobject.js"); // Check if Flash is enabled for browser so we can enabled copy-to-clipboard functionality
callJSfile("invite_participants.js");
// Title
renderPageTitle("<img src='".APP_PATH_IMAGES."survey_participants.gif' class='imgfix'> ".$lang['app_22']);



// TABS
?>
<div id="sub-nav" style="margin:5px 0 20px;">
	<ul>
		<li<?php echo ((isset($_GET['public_survey']) || (!isset($_GET['email_log']) && !isset($_GET['participant_list']))) ? ' class="active"' : '') ?>>
			<a href="<?php echo APP_PATH_WEBROOT ?>Surveys/invite_participants.php?public_survey=1&pid=<?php echo $project_id ?>" style="font-size:13px;color:#393733;padding:6px 9px 7px 10px;"><img src="<?php echo APP_PATH_IMAGES ?>link.png" class="imgfix" style="padding-right:1px;"> <?php echo $lang['survey_279'] ?></a>
		</li>
		<li<?php echo (isset($_GET['participant_list']) ? ' class="active"' : '') ?>>
			<a href="<?php echo APP_PATH_WEBROOT ?>Surveys/invite_participants.php?participant_list=1&pid=<?php echo $project_id ?>" style="font-size:13px;color:#393733;padding:6px 9px 7px 10px;"><img src="<?php echo APP_PATH_IMAGES ?>users4.png" class="imgfix" style="padding-right:1px;"> <?php echo $lang['survey_37'] ?></a>
		</li>
		<li<?php echo (isset($_GET['email_log']) ? ' class="active"' : '') ?>>
			<a href="<?php echo APP_PATH_WEBROOT ?>Surveys/invite_participants.php?email_log=1&pid=<?php echo $project_id ?>" style="font-size:13px;color:#393733;padding:6px 9px 7px 10px;"><img src="<?php echo APP_PATH_IMAGES ?>mails_stack.png" class="imgfix" style="padding-right:1px;"> <?php echo $lang['survey_350'] ?></a>
		</li>
	</ul>
</div>
<div class="clear"></div>


<style type="text/css">
.wrapemail { line-height:12px; overflow: visible !important; white-space: normal !important; word-break: break-all !important; word-wrap: break-word !important; }
</style>
<?php










## SURVEY INVITATION EMAIL LOG
if (isset($_GET['email_log']))
{
	// Instantiate object
	$surveyScheduler = new SurveyScheduler();
	// Instructions
	print RCView::p(array('style'=>'margin-bottom:20px;'), 
			$lang['survey_399']." \"".getTimeZone()."\"".$lang['survey_297']." ".DateTimeRC::format_ts_from_ymd(NOW).$lang['period']
		  );
	// Display a table listing all survey invitations (past, present, and future)
	print $surveyScheduler->renderSurveyInvitationLog();
	?>
	<script type="text/javascript">
	$(function(){
		// Set datetime pickers
		$('.filter_datetime_mdy').datetimepicker({
			buttonText: 'Click to select a date', yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
			hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time', 
			timeFormat: 'hh:mm', constrainInput: true
		});		
		// Add fade mouseover for "delete scheduled invitation" icons
		$(".inviteLogDelIcon").hover(function() {
			$(this).removeClass('opacity50');
		}, function() {
			$(this).addClass('opacity50');
		});
		// Add trigger to "send time" column click when sorting to change image
		$('div#email_log_table .hDivBox table tr th:first').click(); // pre-click so the next will change it to descending
		$('div#email_log_table .hDivBox table tr th:first').click(function(){
			$('.survlogsendarrow').toggle();
		});
		// If user clicks other header other than first header, hide the arrow icon because they don't make sense anymore
		$('div#email_log_table .hDivBox table tr th:not(:first)').click(function(){
			$('.survlogsendarrow').remove();
		});
	})
	</script>
	<?php
}









## PUBLIC SURVEY LINK
elseif (!(isset($_GET['participant_list']) || isset($_GET['email_log'])))
{
	// Obtain the public survey hash [for current arm]
	$hash = Survey::getSurveyHash($_GET['survey_id'], $_GET['event_id']);
	
	//print_array($Proj->events);
	
	// Build drop-down list of FIRST surveys/events for EACH arm
	if ($multiple_arms) {
		// Create drop-down of ALL surveys and, if longitudinal, the events for which they're designated
		$surveyEventOptions = array();
		foreach ($Proj->events as $this_arm=>$arm_attr) 
		{
			$this_event_id = array_shift(array_keys($arm_attr['events']));
			// Add event name
			$event_name = $Proj->eventInfo[$this_event_id]['name_ext'];
			// Truncate survey title if too long
			$survey_title = $Proj->surveys[$Proj->firstFormSurveyId]['title'];
			if (strlen($survey_title.$event_name) > 70) {
				$survey_title = substr($survey_title, 0, 67-strlen($event_name)) . "...";
			}			
			// Add this survey/event as drop-down option
			$surveyEventOptions[$arm_attr['id']] = "\"$survey_title\" - $event_name";
		}
		// Collect HTML
		$surveyEventDropdown = RCView::select(array('class'=>"x-form-text x-form-field",
			'style'=>'max-width:400px;font-weight:bold;padding-right:0;height:22px;font-size:11px;',
			'onchange'=>"var val=this.value;showProgress(1);setTimeout(function(){window.location.href=app_path_webroot+page+'?pid='+pid+'&public_survey=1&arm_id='+val;},300);"), 
				$surveyEventOptions, (isset($_GET['arm_id']) ? $_GET['arm_id'] : $Proj->firstArmId), 500
			);
	}
	
	?>
	<!-- Public survey link -->
	<div style="font-size:11px;max-width:700px;">
		
		<p><?php echo $lang['survey_165'] ?></p>
		
		<?php
		if ($Proj->firstFormSurveyId == null) {
			// If first form is not yet a survey, then cannot display public survey link, so inform user to enable form as survey
			print 	RCView::div(array('class'=>'yellow','style'=>'padding:10px;'), 
						RCView::div(array('style'=>'font-weight:bold;'), 
							RCView::img(array('src'=>'exclamation_orange.png','class'=>'imgfix')) .
							$lang['survey_352']
						) .
						$lang['survey_353'] .
						RCView::div(array('style'=>'padding-top:15px;'), 
							RCView::button(array('class'=>'jqbuttonmed','style'=>'','onclick'=>"window.location.href=app_path_webroot+'Surveys/create_survey.php?pid=$project_id&view=showform&page={$Proj->firstForm}&redirectInvite=1';"),
								$lang['survey_354']
							)
						)
					);
		} elseif ($longitudinal && !in_array($Proj->firstForm, $Proj->eventsForms[$Proj->firstEventId])) {
			// If first form is not designated for the first event, then cannot display public survey link, so inform user to designate form for event
			print 	RCView::div(array('class'=>'yellow','style'=>'padding:10px;'), 
						RCView::div(array('style'=>'font-weight:bold;'), 
							RCView::img(array('src'=>'exclamation_orange.png','class'=>'imgfix')) .
							$lang['survey_567']
						) .
						$lang['survey_568'] .
						RCView::div(array('style'=>'padding-top:15px;'), 
							RCView::button(array('class'=>'jqbuttonmed','style'=>'','onclick'=>"window.location.href=app_path_webroot+'Design/designate_forms.php?pid=$project_id';"),
								$lang['survey_569']
							)
						)
					);
		} else { ?>
			
			<p>
				<font style="color:#800000;"><?php echo $lang['survey_72'] ?></font> <?php echo $lang['survey_73'] ?>
				<?php if ($enable_url_shortener) { ?>
					<a href="javascript:;" onclick="getShortUrl('<?php echo $hash ?>', <?php echo $_GET['survey_id'] ?>)" style="text-decoration:underline;"><?php echo $lang['survey_74'] ?></a> 
					<?php echo $lang['global_47'] ?>
				<?php } ?>
				<a href="javascript:;" onclick="if($('#embed_div').css('display') == 'none'){ $('#embed_div').show('fade','fast'); } $('#embed_div').effect('highlight', 'slow');" 
					style="text-decoration:underline;"><?php echo $lang['survey_75'] ?></a>.
			</p>
			
			
			<?php if ($multiple_arms) { ?>
				<!-- Drop-down for changing arm -->
				<p style="font-weight:bold;color:#800000;">
					<?php echo $lang['survey_76'] ?>&nbsp; <?php echo $surveyEventDropdown ?>
				</p>
			<?php } ?>
			
			<!-- Public survey URL -->
			<div style="padding:5px 0px 6px;">
				<span style="vertical-align:middle;font-size:12px;"><b><?php echo $lang['survey_233'] ?></b></span>
				<!-- Input box and Flash object for copying URL to clipboard -->
				<?php $flashObjectName = 'longurl'; ?>
				<input id="<?php echo $flashObjectName ?>" value="<?php echo APP_PATH_SURVEY_FULL . "?s=$hash" ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="width:320px;">&nbsp;
				<object id='flashObj_<?php echo $flashObjectName ?>' codebase='<?php echo APP_PATH_WEBROOT ?>Resources/misc/swflash9.0.0.0.cab' width="127" height="23" style="vertical-align:middle;">
					<param name='allowScriptAccess' value='sameDomain' />
					<param name='allowFullScreen' value='false' />
					<param name='movie' value='<?php echo APP_PATH_WEBROOT ?>Resources/misc/clipboard.swf' />
					<param name='quality' value='high' />
					<param name='bgcolor' value='#ffffff' />
					<param name='wmode' value='transparent' />
					<param name='flashvars' value="callback=copyUrlToClipboard&callbackArg=<?php echo $flashObjectName ?>" />
					<embed class='clipboardAction' src='<?php echo APP_PATH_WEBROOT ?>Resources/misc/clipboard.swf' flashvars="callback=copyUrlToClipboard&callbackArg=<?php echo $flashObjectName ?>" quality='high' bgcolor='#ffffff' width="127" height="23" wmode='transparent' name='clipboard' allowscriptaccess='always' allowfullscreen='false' type='application/x-shockwave-flash' pluginspage='http://www.adobe.com/go/getflashplayer' style="vertical-align:middle;" />
				</object>
			</div>
			
			<!-- Short URL -->
			<div id="shorturl_loading_div" style="font-size:12px;display:none;padding:10px 0px;font-weight:bold;">
				<img src="<?php echo APP_PATH_IMAGES ?>progress_circle.gif" class="imgfix">&nbsp; <?php echo $lang['survey_79'] ?><br><br>
			</div>	
			<div id="shorturl_div" style="display:none;font-size:12px;padding:0 0 10px;">
				<div style="padding:0px 0px 4px 10px;color:#444;"><?php echo $lang['global_46'] ?></div>
				<b><?php echo $lang['survey_234'] ?></b>&nbsp;&nbsp;
				<!-- Input box and Flash object for copying URL to clipboard -->
				<?php $flashObjectName = 'shorturl'; ?>
				<input id="<?php echo $flashObjectName ?>" value="" onclick="this.select();" readonly="readonly" class="staticInput" style="width:120px;">&nbsp;
				<object id='flashObj_<?php echo $flashObjectName ?>' codebase='<?php echo APP_PATH_WEBROOT ?>Resources/misc/swflash9.0.0.0.cab' width="127" height="23" style="vertical-align:middle;">
					<param name='allowScriptAccess' value='sameDomain' />
					<param name='allowFullScreen' value='false' />
					<param name='movie' value='<?php echo APP_PATH_WEBROOT ?>Resources/misc/clipboard.swf' />
					<param name='quality' value='high' />
					<param name='bgcolor' value='#ffffff' />
					<param name='wmode' value='transparent' />
					<param name='flashvars' value="callback=copyUrlToClipboard&callbackArg=<?php echo $flashObjectName ?>" />
					<embed class='clipboardAction' src='<?php echo APP_PATH_WEBROOT ?>Resources/misc/clipboard.swf' flashvars="callback=copyUrlToClipboard&callbackArg=<?php echo $flashObjectName ?>" quality='high' bgcolor='#ffffff' width="127" height="23" wmode='transparent' name='clipboard' allowscriptaccess='always' allowfullscreen='false' type='application/x-shockwave-flash' pluginspage='http://www.adobe.com/go/getflashplayer' style="vertical-align:middle;" />
				</object>
			</div>
			
			<!-- Embed code for URL -->
			<div id='embed_div' style="font-size:12px;display:none;padding:0 0 5px;">
				<p><?php echo $lang['survey_240'] ?></p>
				<div>
					<b><?php echo $lang['survey_235'] ?> <span style="font-size:12px;">&lt; &gt;</span></b>&nbsp;
					<!-- Input box and Flash object for copying URL to clipboard -->
					<?php $flashObjectName = 'embedurl'; ?>
					<input id="<?php echo $flashObjectName ?>" value="<a href=&quot;<?php echo APP_PATH_SURVEY_FULL . "?s=$hash" ?>&quot;><?php echo $lang['survey_83'] ?></a>" onclick="this.select();" readonly="readonly" class="staticInput" style="width:340px;">&nbsp;
					<object id='flashObj_<?php echo $flashObjectName ?>' codebase='<?php echo APP_PATH_WEBROOT ?>Resources/misc/swflash9.0.0.0.cab' width="127" height="23" style="vertical-align:middle;">
						<param name='allowScriptAccess' value='sameDomain' />
						<param name='allowFullScreen' value='false' />
						<param name='movie' value='<?php echo APP_PATH_WEBROOT ?>Resources/misc/clipboard.swf' />
						<param name='quality' value='high' />
						<param name='bgcolor' value='#ffffff' />
						<param name='wmode' value='transparent' />
						<param name='flashvars' value="callback=copyUrlToClipboard&callbackArg=<?php echo $flashObjectName ?>" />
						<embed class='clipboardAction' src='<?php echo APP_PATH_WEBROOT ?>Resources/misc/clipboard.swf' flashvars="callback=copyUrlToClipboard&callbackArg=<?php echo $flashObjectName ?>" quality='high' bgcolor='#ffffff' width="127" height="23" wmode='transparent' name='clipboard' allowscriptaccess='always' allowfullscreen='false' type='application/x-shockwave-flash' pluginspage='http://www.adobe.com/go/getflashplayer' style="vertical-align:middle;" />
					</object>
				</div>
			</div>
			
			<!-- Buttons to open or email survey -->
			<div style="padding:5px 0 10px;margin-left:5px;">
				<button class="jqbuttonmed" style="margin-top:10px;" onclick="surveyOpen($('#longurl').val(),0);"
					><img src="<?php echo APP_PATH_IMAGES ?>arrow_right_curve.png" style="vertical-align:middle;"><span style="margin-left:1px;vertical-align:middle;"> <?php echo $lang['survey_236'] ?></span></button>
				&nbsp;
				<button class="jqbuttonmed" style="margin-top:10px;" onclick="sendSelfEmail(<?php echo $_GET['survey_id'] ?>,$('#longurl').val());"
					><img src="<?php echo APP_PATH_IMAGES ?>email.png" style="vertical-align:middle;"><span style="margin-left:2px;vertical-align:middle;"> <?php echo $lang['survey_237'] ?></span></button>
				&nbsp;
				<?php if (gd2_enabled()) { ?>
					<button class="jqbuttonmed" style="margin-top:10px;" onclick="getAccessCode('<?php echo $hash ?>');"
						><img src="<?php echo APP_PATH_IMAGES ?>ticket_arrow.png" style="vertical-align:middle;position:relative;top:-1px;"> 
						<span style="margin-left:1px;vertical-align:middle;"> <?php echo $lang['survey_621'] ?></span>
						<img src="<?php echo APP_PATH_IMAGES ?>qrcode.png" style="margin-left:2px;vertical-align:middle;position:relative;top:-1px;">
						<span style="vertical-align:middle;"> <?php echo $lang['survey_664'] ?></span></button>
				<?php } else { ?>
					<button class="jqbuttonmed" style="margin-top:10px;" onclick="getAccessCode('<?php echo $hash ?>');"
						><img src="<?php echo APP_PATH_IMAGES ?>ticket_arrow.png" style="vertical-align:middle;"><span style="margin-left:2px;vertical-align:middle;"> <?php echo $lang['survey_629'] ?></span></button>
				<?php } ?>
			</div>
			
			<?php 
			if ($twilio_enabled) { 
				$twilio_option_num = 1;
				$access_code_numeral = Survey::getAccessCode(getParticipantIdFromHash($hash), false, false, true);
				?>
				<!-- Buttons to take survey by voice call or SMS -->
				<fieldset style="margin:25px 0 20px 5px;padding-left:8px;border:1px solid #ccc;background-color:#F3F5F5;">
					<legend style="font-size:13px;font-weight:bold;color:#333;">
						<img src="<?php echo APP_PATH_IMAGES ?>phone.gif" class="imgfix">
						<?php echo $lang['survey_823'] ?>
					</legend>
					<div style="padding:15px 15px 15px 10px;font-size:12px;line-height: 1.4em;">
						<div><?php echo $lang['survey_816'] ?></div>
						<div style="margin:12px 0 20px 20px;font-weight:bold;">
							<?php echo $twilio_option_num++ . ") " . $lang['survey_825'] ?>	
							<button class="jqbuttonmed" style="margin-left:7px;color:#800000;" onclick="initCallSMS('<?php echo $hash ?>');"><?php echo $lang['survey_815'] ?></button>
						</div>
						<div><?php echo $lang['survey_821'] ?></div>
						<?php if ($twilio_option_voice_initiate) { ?>
							<div style="margin:15px 0 4px 20px;font-weight:bold;">
								<?php echo $twilio_option_num++ . ") " . $lang['survey_881'] ?>
							</div>
							<div style="margin-left:34px;">
								<span style="vertical-align:middle;font-size:12px;"><?php echo $lang['survey_817'] ?></span>
								<input value="<?php echo formatPhone($twilio_from_number) ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="width:110px;margin:0 2px 0 3px;">
								<span style="vertical-align:middle;font-size:12px;"><?php echo $lang['survey_820'] ?></span>
								<input value="<?php echo $access_code_numeral ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="width:80px;margin:0 2px 0 3px;">
							</div>
						<?php } ?>
						<?php if ($twilio_option_sms_invite_receive_call) { ?>
							<div style="margin:15px 0 4px 20px;font-weight:bold;">
								<?php echo $twilio_option_num++ . ") " . $lang['survey_882'] ?>
							</div>
							<div style="margin-left:34px;">
								<span style="vertical-align:middle;font-size:12px;"><?php echo $lang['survey_818'] ?></span>
								<input value="<?php echo Survey::PREPEND_ACCESS_CODE_NUMERAL . $access_code_numeral ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="width:87px;margin:0 2px 0 3px;">
								<span style="vertical-align:middle;font-size:12px;"><?php echo $lang['survey_819'] ?></span>
								<input value="<?php echo formatPhone($twilio_from_number) ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="width:110px;margin:0 2px 0 3px;">
							</div>
						<?php } ?>
						<?php if ($twilio_option_sms_initiate) { ?>
							<div style="margin:15px 0 4px 20px;font-weight:bold;">
								<?php echo $twilio_option_num++ . ") " . $lang['survey_883'] ?>
							</div>
							<div style="margin-left:34px;">
								<span style="vertical-align:middle;font-size:12px;"><?php echo $lang['survey_818'] ?></span>
								<input value="<?php echo $access_code_numeral ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="width:80px;margin:0 2px 0 3px;">
								<span style="vertical-align:middle;font-size:12px;"><?php echo $lang['survey_819'] ?></span>
								<input value="<?php echo formatPhone($twilio_from_number) ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="width:110px;margin:0 2px 0 3px;">
							</div>
						<?php } ?>
					</div>
				</fieldset>
				<?php 
			}
			
			## AUTOMATED INVITES CHECK
			// If auto invites are enabled for the first event-first instrument but the email field has not been designated yet,
			// then give a warning.
			// Check if AI are enabled for the first event-first instrument
			$sql = "select 1 from redcap_surveys_scheduler where condition_surveycomplete_survey_id = {$Proj->firstFormSurveyId}
					and condition_surveycomplete_event_id = {$Proj->firstEventId} and active = 1 limit 1";
			$q = db_query($sql);
			if (db_num_rows($q)) {
				// Yes, they are enabled
				if ($survey_email_participant_field == '') {
					// Email field is not designated. Tell user to designate one.
					print 	RCView::div(array('class'=>'red','style'=>'padding:10px;margin-top:15px;'), 
								RCView::div(array('style'=>'font-weight:bold;'), 
									RCView::img(array('src'=>'exclamation.png','class'=>'imgfix')) .
									$lang['global_48'].$lang['colon']." ".$lang['survey_481']
								) .
								$lang['survey_480'] . RCView::br() . RCView::br() .
								$lang['survey_482']
							);
				} elseif ($survey_email_participant_field != '' && !isset($Proj->forms[$Proj->firstForm]['fields'][$survey_email_participant_field])) {
					// Email field is designated but does not exist on first instrument (problematic)					
					print 	RCView::div(array('class'=>'red','style'=>'padding:10px;margin-top:15px;'), 
								RCView::div(array('style'=>'font-weight:bold;'), 
									RCView::img(array('src'=>'exclamation.png','class'=>'imgfix')) .
									$lang['global_48'].$lang['colon']." ".$lang['survey_483']
								) .
								$lang['survey_484'] . ' ' . 
								RCView::b($survey_email_participant_field) . ' ("' . $Proj->metadata[$survey_email_participant_field]['element_label'] . '")' .
								$lang['period'] . RCView::br() . RCView::br() .
								$lang['survey_482']
							);
				}
			}
		} 
		?>
		
	</div>
	<?php
}








## PARTICIPANT LIST
elseif (isset($_GET['participant_list']))
{
	?>
	
	<!-- Participant List Section -->	
	<div style="margin:0 0 20px;max-width:700px;line-height: 1.4em;">
		
		<div style="margin:10px 0;">
			<div>
				<?php echo $lang['survey_355'] . " " . ($isFollowUpSurvey ? "" : $lang['survey_356']) ?>
				<a href="javascript:;" onclick="$('#partListInstrMore').toggle('fade');" style="text-decoration:underline;"><?php echo $lang['survey_86'] ?></a>
			</div>
			<div id="partListInstrMore" style="display:none;margin-top:15px;">
				<?php echo $lang['survey_87'] ?> 
				<b><?php echo $lang['survey_88'] ?></b> <img src="<?php echo APP_PATH_IMAGES ?>tick_circle_frame.png" style="vertical-align:middle;"> <?php echo $lang['global_47'] ?>
				<b><?php echo $lang['survey_89'] ?></b> <img src="<?php echo APP_PATH_IMAGES ?>circle_orange_tick.png" style="vertical-align:middle;"><?php echo $lang['survey_91'] ?> 
				<b><?php echo $lang['survey_90'] ?></b> <img src="<?php echo APP_PATH_IMAGES ?>stop_gray.png" style="vertical-align:middle;"><?php echo $lang['period'] ?>
				<u><?php echo $lang['survey_92'] ?></u> 
				<?php echo $lang['survey_93'] ?>
			</div>
		</div>
		
	</div>

	<!-- Participant List -->
	<div id="partlist_outerdiv" style="margin-bottom:20px;">
		<?php
		// Build Participant List
		include APP_PATH_DOCROOT . 'Surveys/participant_list.php';	
		?>
	</div>
		
	<!-- Hidden "Add Participants" dialog -->
	<div id="emailAdd" title="<?php echo cleanHtml2($twilio_enabled ? $lang['survey_774'] : $lang['survey_770']) ?>" style="display:none;font-family:arial;">
		<p>
			<?php echo ($twilio_enabled ? $lang['survey_775'] : $lang['survey_267']) ?>
			<span class="partIdentInstrText"><?php echo $lang['survey_268'] ?></span>
			<?php if ($multiple_arms) { ?>
				<br><br><?php echo $lang['survey_97'] ?> <b><?php echo $armNameFull ?></b>.
			<?php } ?>
		</p>
		<div style="">
			<textarea id="newPart" class="x-form-field notesbox" style="font-family:arial;width:500px;height:130px;"></textarea>
		</div>
		<?php 
		if ($twilio_enabled) { 
			// Set the participant's preference for delivery method
			print 	RCView::div(array('style'=>'border:1px solid #ccc;background-color:#f5f5f5;padding:5px 5px 7px;margin-bottom:15px;width:496px;'),
						RCView::div(array('style'=>''), 
							RCView::img(array('src'=>'arrow_right_curve.png', 'class'=>'imgfix')) .
							$lang['survey_778']
						) . 
						RCView::div(array('style'=>'margin:3px 0 0 20px;'), 
							RCView::select(array('id'=>'delivery_preference', 'class'=>'x-form-text x-form-field', 'style'=>'padding-right:0;height:22px;'),	
								Survey::getDeliveryMethods(false, true), 'EMAIL') .
							RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'style'=>'margin-left:5px;font-size: 12px;', 
								'title'=>$lang['form_renderer_02'], 'onclick'=>"deliveryPrefExplain();"), '?')
						)
					);
		} 
		?>
		<div style="color:#111111;margin:5px 0 0;line-height:15px;">
			<div style="color:#C00000;margin-bottom:10px;"><?php echo $lang['survey_771'] ?></div>
			<div class="partIdentInstrText">
				<b><?php echo $lang['survey_98'] ?></b>&nbsp; 
				<?php echo ($twilio_enabled ? $lang['survey_772'] : $lang['survey_99']) ?>
			</div> 
			<div style="border-top:1px solid #bbb;padding-top:10px;margin-top:25px;font-size:11px;color:#666;">
				<?php if ($twilio_enabled) { ?>
					<b><?php echo $lang['survey_101'] ?> #1:</b>&nbsp; john.williams@hotmail.com, 615-123-4567<br>
					<b><?php echo $lang['survey_101'] ?> #2:</b>&nbsp; (270) 398-1111<span class="partIdentInstrText">, Jim Taylor</span><br>
					<b><?php echo $lang['survey_101'] ?> #3:</b>&nbsp; putnamtr@gmail.com, 365 908 7283<span class="partIdentInstrText">, ID 4930-72</span><br>
					<b><?php echo $lang['survey_101'] ?> #4:</b>&nbsp; +16158877747<br>
					<div style="margin-top:10px;font-size:11px;line-height:12px;"><?php echo $lang['survey_907'] ?></div>
				<?php } else { ?>
					<b><?php echo $lang['survey_101'] ?> #1:</b>&nbsp; john.williams@hotmail.com<br>
					<b><?php echo $lang['survey_101'] ?> #2:</b>&nbsp; jimtaylor@yahoo.com<span class="partIdentInstrText">, Jim Taylor</span><br>
					<b><?php echo $lang['survey_101'] ?> #3:</b>&nbsp; putnamtr@gmail.com<span class="partIdentInstrText">, ID 4930-72</span><br>
				<?php } ?>
			</div>
		</div>
	</div>

	<!-- Hidden pop-up div to display warning message about trying to click identifiers when they are disabled -->
	<div id='tooltipIdentDisabled' class='tooltip' style='max-width:300px;padding:3px 6px;z-index:9999;'>
		<?php echo "<b>{$lang['global_23']}{$lang['colon']}</b><br>" . ($status < 1 ? $lang['survey_262'] : $lang['survey_263']) ?>
	</div>

	<!-- Hidden pop-up div to display warning message about editing email/identifier -->
	<div id='tooltipEdit' class='tooltip' style='max-width:300px;padding:3px 6px;z-index:9999;'>
		<?php echo "<b>{$lang['survey_264']}</b><br>{$lang['survey_859']}" ?>
	</div>

	<!-- Hidden pop-up div to display warning message about editing email/identifier -->
	<div id='tooltipNoEditIdentFollowup' class='tooltip' style='max-width:350px;padding:3px 6px;z-index:9999;'>
		<?php echo "<b>{$lang['survey_498']}</b><br>{$lang['survey_501']}" ?>
	</div>

	<!-- Hidden pop-up div to display warning message about editing email/identifier -->
	<div id='tooltipNoEditEmailFollowup' class='tooltip' style='max-width:350px;padding:3px 6px;z-index:9999;'>
		<?php echo "<b>{$lang['survey_502']}</b><br>{$lang['survey_503']}" ?>
	</div>

	<!-- Hidden pop-up div to display warning message about editing phone -->
	<div id='tooltipNoEditPhoneFollowup' class='tooltip' style='max-width:350px;padding:3px 6px;z-index:9999;'>
		<?php echo "<b>{$lang['survey_857']}</b><br>{$lang['survey_858']}" ?>
	</div>

	<!-- Hidden pop-up div to display warning message about editing email for response from Public Survey -->
	<div id='tooltipNoEditEmailPublic' class='tooltip' style='max-width:350px;padding:3px 6px;z-index:9999;'>
		<?php echo "<b>{$lang['survey_502']}</b><br>{$lang['survey_854']}" ?>
	</div>

	<!-- Hidden pop-up div to display warning message about editing phone number for response from Public Survey -->
	<div id='tooltipNoPhoneEmailPublic' class='tooltip' style='max-width:350px;padding:3px 6px;z-index:9999;'>
		<?php echo "<b>{$lang['survey_806']}</b><br>{$lang['survey_807']}" ?>
	</div>

	<!-- Hidden pop-up div to enable/disable Participant Identifiers -->
	<div id='popupEnablePartIdent' style='display:none;'></div>
	<?php
}
?>



<!-- Hidden pop-up div to display warning message about clicking responded icon -->
<div id='tooltipViewResp' class='tooltip' style='max-width:300px;padding:3px 6px;z-index:9999;'>
	<?php echo "<b>{$lang['survey_244']}</b><br>{$lang['survey_243']}" ?>
</div>
	

	
<script type="text/javascript">
// Language vars
var langSave = '<?php echo cleanHtml($lang['designate_forms_13']) ?>';	
// Note the first form's survey_id (referenced in order to disable editing email/identifier for followup surveys)
var firstFormSurveyId = <?php echo is_numeric($Proj->firstFormSurveyId) ? $Proj->firstFormSurveyId : "''" ?>;
var firstEventId = <?php echo $Proj->firstEventId ?>;
var isFollowUpSurvey = <?php print ($isFollowUpSurvey ? 'true' : 'false'); ?>;
var survey_id = <?php echo $_GET['survey_id'] ?>;
var event_id = <?php echo $_GET['event_id'] ?>;

$(function(){
	// If "Add Participants" button is disabled but user tried to click it, then provide user with message why they cannot add participants
	$('#addPartsBtnSpan').click(function(){
		if ($('#addPartsBtn').prop('disabled')) {
			simpleDialog('<?php echo cleanHtml($lang['survey_389']) ?>','<?php echo cleanHtml($lang['survey_388']) ?>');
		}
	});
});

<?php if ((isset($_GET['public_survey']) || (!isset($_GET['email_log']) && !isset($_GET['participant_list'])))) { ?>
// Check Flash version to see if we should hide Flash action triggers on page
$(function(){
	var playerVersion = swfobject.getFlashPlayerVersion();
	if (!(isNumeric(playerVersion.major) && playerVersion.major > 0)) {
		$('.clipboardAction').hide();
	}
});
<?php } ?>

// Delete ALL participants from list
function deleteParticipants(survey_id,event_id) {
	simpleDialog('<?php echo cleanHtml($lang['survey_795']) ?>','<?php echo cleanHtml($lang['survey_358']) ?>',null,null,null,'<?php echo cleanHtml($lang['global_53']) ?>',
		"deleteParticipants2("+survey_id+","+event_id+");",'<?php echo cleanHtml($lang['survey_368']) ?>');
}
function deleteParticipants2(survey_id,event_id) {
	simpleDialog('<?php echo cleanHtml($lang['survey_796']) ?>','<?php echo cleanHtml($lang['survey_369']) ?>',null,null,null,'<?php echo cleanHtml($lang['global_53']) ?>',
		"deleteParticipantsDo("+survey_id+","+event_id+");",'<?php echo cleanHtml($lang['survey_368']) ?>');
}
function deleteParticipantsDo(survey_id,event_id) {
	$.post(app_path_webroot+'Surveys/delete_participants.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id, { }, function(data){
		if (data == '1') {
			loadPartList(survey_id,event_id,1,'<?php echo cleanHtml($lang['survey_798']) ?>','<?php echo cleanHtml($lang['survey_797']) ?>');
		} else {
			alert(woops);
		}
	});
}

// Delete a participant from list
function deleteParticipant(survey_id,event_id,part_id) {
	simpleDialog('<?php echo cleanHtml($lang['survey_361']) ?>','<?php echo cleanHtml($lang['survey_360']) ?>',null,null,null,'<?php echo cleanHtml($lang['global_53']) ?>',
		"deleteParticipantDo("+survey_id+","+event_id+","+part_id+");",'<?php echo cleanHtml($lang['scheduling_57']) ?>');
}
function deleteParticipantDo(survey_id,event_id,part_id) {
	$.post(app_path_webroot+'Surveys/delete_participant.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id, { participant_id: part_id }, function(data){
		if (data == '1') {
			loadPartList(survey_id,event_id,1,'<?php echo cleanHtml($lang['survey_363']) ?>','<?php echo cleanHtml($lang['survey_362']) ?>');
		} else {
			alert(woops);
		}
	});
}

// Dialog for adding new participants
function addPart(survey_id,event_id) {
	$('#emailAdd').dialog({ bgiframe: true, modal: true, width: 550, buttons: { 
		'<?php echo cleanHtml($lang['global_53']) ?>': function() { $('#newPart').val(''); $(this).dialog('close'); },
		'<?php echo cleanHtml($lang['survey_230']) ?>': function () {
			$('#newPart').val( trim($('#newPart').val()) );
			if ($('#newPart').val().length < 1) {
				simpleDialog('<?php echo cleanHtml($lang['survey_776'] . ($twilio_enabled ? " ".$lang['survey_777'] : "")) ?>');
				return;
			}
			showProgress(1);
			$.post(app_path_webroot+'Surveys/add_participants.php?pid='+pid+'&event_id='+event_id+'&survey_id='+survey_id, { delivery_preference: $('#delivery_preference').val(), participants: $('#newPart').val() }, function(data){
				showProgress(0);
				if (data == '1') {
					$('#newPart').val('');
					$('#emailAdd').dialog('destroy');
					loadPartList(survey_id,event_id,1,'<?php echo cleanHtml($lang['survey_855']) ?>','<?php echo cleanHtml($lang['survey_856']) ?>');
				} else if (data == '0') {
					alert(woops);
				} else {
					simpleDialog(data);
				}
			});
		}
	} });
}
// Open email-sending dialog
function sendEmails(survey_id,event_id) {
	$('#emailSendList_div').html('<img src="'+app_path_images+'progress_circle.gif" class="imgfix">&nbsp; <?php echo cleanHtml($lang['survey_287']) ?>');
	$('#emailPart').dialog({ bgiframe: true, modal: true, width: 1000, open: function(){ fitDialog(this); }, buttons: { 
		'<?php echo cleanHtml($lang['global_53']) ?>': function() { $(this).dialog('close'); },
		'<?php echo cleanHtml($lang['survey_792']) ?>': function() {
			// Trim email subject/message
			$('#emailTitle').val( trim($('#emailTitle').val()) );
			$('#emailCont').val( trim($('#emailCont').val()) );
			// If set exact time in future to send surveys, make sure time doesn't exist in the past
			var now_mdyhm = '<?php echo date('m-d-Y H:i') ?>';
			var now_ymdhm = '<?php echo date('YmdHi') ?>';
			var eTs = $('form#emailPartForm #emailSendTimeTS').val();			
			if (user_date_format_validation == 'mdy') {
				var emailSendTimeTs_ymdhm = eTs.substr(6,4)+eTs.substr(0,2)+eTs.substr(3,2)+eTs.substr(11,2)+eTs.substr(14,2);
			} else if (user_date_format_validation == 'dmy') {
				var emailSendTimeTs_ymdhm = eTs.substr(6,4)+eTs.substr(3,2)+eTs.substr(0,2)+eTs.substr(11,2)+eTs.substr(14,2);
			} else {
				var emailSendTimeTs_ymdhm = eTs.substr(0,4)+eTs.substr(5,2)+eTs.substr(8,2)+eTs.substr(11,2)+eTs.substr(14,2);
			}
			if ($('form#emailPartForm #emailSendTimeTS').length && $('form#emailPartForm input[name="emailSendTime"]:checked').val() == 'EXACT_TIME') {
				if ($('form#emailPartForm #emailSendTimeTS').val().length < 1) {
					simpleDialog('<?php echo cleanHtml($lang['survey_325']) ?>',null,null,null,"$('form#emailPartForm #emailSendTimeTS').focus();");
					return;
				} else if (!redcap_validate(document.getElementById('emailSendTimeTS'),'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter)) {
					return;
				} else if (emailSendTimeTs_ymdhm < now_ymdhm) {
					simpleDialog('<?php echo cleanHtml($lang['survey_326']." <b>".DateTimeRC::format_user_datetime(NOW, 'Y-M-D_24', null, true)."</b>".$lang['period']) ?>','<?php echo cleanHtml($lang['survey_327']) ?>');
					return;
				}
			}
			
			// Check reminder options
			if (!validateSurveyRemindersOptions()) return;
			// If reminder option is unchecked, then uncheck the reminder option radio that is hidden
			if (!$('#enable_reminders_chk').prop('checked')) {
				$('#reminders_choices_div input[name="reminder_type"]:checked').prop('checked', false);
			}
			
			// Determine if Voice/SMS options are enabled
			var voiceSmsEnabled = ($('form#emailPartForm select[name="delivery_type"]').length);
			var delivery_type = (voiceSmsEnabled) ? $('form#emailPartForm select[name="delivery_type"]').val() : '';
			var email_delivery = (delivery_type == 'EMAIL');
			var use_part_pref = (delivery_type == 'PARTICIPANT_PREF');
			var this_email_delivery;
			
			// Gather participant_ids
			var invalidInviteType = 0;
			var participants = new Array();
			var i = 0;
			$('input.chk_part:checked').each(function(){
				// Make sure we can successfully send the invitations in their desired delivery method
				if (voiceSmsEnabled) {
					if (use_part_pref) {
						// Send using participant's preference
						this_email_delivery = ($(this).attr('partpref') == 'EMAIL');
						if ((this_email_delivery && $(this).attr('hasemail') != '1') || (!this_email_delivery && $(this).attr('hasphone') != '1')) {
							invalidInviteType++;
						}
					} else {
						// Send using the manually set invitation type
						if ((email_delivery && $(this).attr('hasemail') != '1') || (!email_delivery && $(this).attr('hasphone') != '1')) {
							invalidInviteType++;
						}
					}
				}		
				// Add participant to array
				participants[i] = $(this).prop('id').substring(8);
				i++;		
			});
			// If there are invitation types that are invalid and won't send because of missing email or phone, then display error
			if (invalidInviteType > 0) {
				simpleDialog('<?php echo cleanHtml($lang['survey_827']) ?> '+invalidInviteType+' <?php echo cleanHtml($lang['survey_828']) ?>', 
					'<?php echo cleanHtml($lang['survey_826']) ?>', null, 550, null,
					'<?php echo cleanHtml($lang['global_53']) ?>', function(){ 
						$('input.chk_part:checked').each(function(){
							if (use_part_pref) {
								// Send using participant's preference
								this_email_delivery = ($(this).attr('partpref') == 'EMAIL');
								if ((this_email_delivery && $(this).attr('hasemail') != '1') || (!this_email_delivery && $(this).attr('hasphone') != '1')) {
									$(this).prop('checked', false);
								}
							} else {
								// Send using the manually set invitation type
								if ((email_delivery && $(this).attr('hasemail') != '1') || (!email_delivery && $(this).attr('hasphone') != '1')) {
									$(this).prop('checked', false);
								}
							}	
						});
						// Click the Send Invitations button
						$('#emailPart').dialog("widget").find(".ui-dialog-buttonpane button").eq(1).trigger('click');
					}, '<?php echo cleanHtml($lang['survey_829']) ?>');
				return;
			}
			// Give error message if no participants are selected
			if (participants.length < 1) {
				simpleDialog('<?php echo cleanHtml($lang['survey_286']) ?>');
				return;
			}
			// Set all checked participant_id's as a single input field
			$('#emailPartForm').append('<input type="hidden" name="participants" value="'+participants.join(",")+'">');
			// Give confirmation message if any participants are about to have their invitations rescheduled
			var numScheduled = $('input.sched:checked').length;
			if (numScheduled > 0) {
				// Display pop-up with confirmation about rescheduling invites
				$('#reschedule-reminder-dialog-resched-count').html(numScheduled);
				$('#reschedule-reminder-dialog').dialog({ bgiframe: true, modal: true, width: 500, buttons: { 
					'<?php echo cleanHtml($lang['global_53']) ?>': function() { $(this).dialog('close'); },
					'<?php echo cleanHtml($lang['survey_285']) ?>': function () {
						// Submit the form
						sendEmailsSubmit(survey_id,event_id);
					}
				}});
				return;
			}
			// Submit the form
			sendEmailsSubmit(survey_id,event_id);
		}
	} });
	// After opening "compose email" dialog, load participant list via ajax
	$.get(app_path_webroot+'Surveys/participant_list.php?emailformat=1&survey_id='+survey_id+'&event_id='+event_id+'&pid='+pid, { }, function(data){
		$('#emailSendList_div').html(data);
		// Disable onclick attribute for the "check all" checkbox in table header
		$('#emailSendList_div div#participant_table_email div.hDiv table th:first').attr('onclick', '');
	});
}
</script>

<style type="text/css">
.edit_active { background: #fafafa url(<?php echo APP_PATH_IMAGES ?>pencil.png) no-repeat right; }
.edit_saved  { background: #C1FFC1 url(<?php echo APP_PATH_IMAGES ?>tick.png) no-repeat right; }
</style>


<!-- Reschedule Participants Dialog -->
<div id="reschedule-reminder-dialog" title='<?php echo cleanHtml($lang['survey_343']) ?>' class="simpleDialog">
	<?php echo $lang['survey_344'] ?>
	<span id="reschedule-reminder-dialog-resched-count">0</span>
	<?php echo $lang['survey_345'] ?>
</div>

<!-- Popup for changing a particiant's Twilio invitation preference -->
<?php 
print 	RCView::div(array('id'=>'invPrefPopup', 'style'=>'display:none;'),
			RCView::div(array('id'=>'invPrefPopupSub'),
				RCView::img(array('src'=>'arrow_right_curve.png', 'class'=>'imgfix')) .
				RCView::b($lang['survey_860']) . 
				RCView::div(array('style'=>'margin:6px 0 8px;'),
					RCView::hidden(array('id'=>'partInvPrefPartId')) . 
					RCView::hidden(array('id'=>'partInvPrefRecord')) . 
					RCView::select(array('id'=>'partInvPref', 'class'=>'x-form-text x-form-field', 'style'=>'padding-right:0;height:22px;', 'onchange'=>"setInviteDeliveryMethod(this)"),
						Survey::getDeliveryMethods(false, true), 'EMAIL')
				) . 
				RCView::div(array('style'=>'text-align:right;margin-right:10px;'),
					RCView::span(array('id'=>'partInvPrefSaved', 'style'=>'font-weight:bold;color:#C00000'),
						$lang['design_243']
					) .
					RCView::button(array('class'=>'jqbuttonmed', 'style'=>'color:#800000;margin:0 10px 0 20px;', 'onclick'=>"changeInvPref({$_GET['survey_id']},{$_GET['event_id']});"), $lang['designate_forms_13']) .
					RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:11px;text-decoration:underline;', 
						'onclick'=>"enableEditInvPref($('#editinvpref_'+$('#partInvPrefPartId').val()));$('#invPrefPopup').hide();"), $lang['global_53'])
				)
			)
		);
?>
	

<!-- Email Participants Dialog -->
<div id="emailPart" title='<?php echo cleanHtml(RCView::img(array('src'=>'email.png','class'=>'imgfix','style'=>'margin-right:3px;')) . $lang['survey_791']) ?>' style="display:none;">
	<form id="emailPartForm">
	<table cellspacing=0 border=0 style="table-layout:fixed;"><tr>
		<td valign="top" align="left" style="width:430px;padding:2px 10px 0 0;">
			
			<fieldset style="padding-left:8px;background-color:#FFFFD3;border:1px solid #FFC869;margin-bottom:10px;">
				<legend style="font-weight:bold;color:#333;">
					<img src="<?php echo APP_PATH_IMAGES ?>txt.gif" class="imgfix" style="margin-right:2px;">
					<?php echo $lang['survey_340'] ?>
				</legend>
				<?php
				print 	RCView::div(array('style'=>'padding:3px 8px 8px 2px;'),
							// Survey title
							RCView::div(array('style'=>'color:#800000;'),
								RCView::b($lang['survey_310']) . 
								RCView::span(array('style'=>'font-size:13px;margin-left:8px;'), 
									// If survey title is blank (because using a logo instead), then insert the instrument name
									RCView::escape($Proj->surveys[$_GET['survey_id']]['title'] == "" 
										? $Proj->forms[$Proj->surveys[$_GET['survey_id']]['form_name']]['menu'] 
										: $Proj->surveys[$_GET['survey_id']]['title']
									)
								)				
							) .
							// Event name (if longitudinal)
							RCView::div(array('style'=>'color:#000066;padding-top:3px;' . ($longitudinal ? '' : 'display:none;')),
								RCView::b($lang['bottom_23']) . 
								RCView::span(array('style'=>'font-size:13px;margin-left:8px;'), 
									RCView::escape($Proj->eventInfo[$_GET['event_id']]['name_ext'])
								) 
							)
						);
				?>
			</fieldset>
			
			<?php 
			// If Twilio is enabled, give option to send as SMS or VOICE
			if ($twilio_enabled) {
				print 	RCView::fieldset(array('style'=>'padding:0 0 2px 8px;border:1px solid #ccc;background-color:#F3F5F5;margin-bottom:10px;'),
							RCView::legend(array('style'=>'color:#333;'),
								RCView::img(array('src'=>'arrow_right_curve.png', 'class'=>'imgfix', 'style'=>'margin-right:2px;')) .
								RCView::b($lang['survey_687']). " " . $lang['survey_691']
							) .
							RCView::div(array('class'=>'nowrap', 'style'=>'padding:6px 2px 6px 2px;'),
								RCView::select(array('name'=>'delivery_type', 'class'=>'x-form-text x-form-field', 'style'=>'padding-right:0;height:22px;', 'onchange'=>"setInviteDeliveryMethod(this)"),
									Survey::getDeliveryMethods(true, true), 'PARTICIPANT_PREF') .
								RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'style'=>'margin-left:5px;font-size: 12px;', 
									'title'=>$lang['form_renderer_02'], 'onclick'=>"deliveryPrefExplain();"), '?')
							)
						);
			}
			?>
			
			<fieldset style="padding-left:8px;border:1px solid #ccc;background-color:#F3F5F5;margin-bottom:10px;">
				<legend style="font-weight:bold;color:#333;">
					<img src="<?php echo APP_PATH_IMAGES ?>clock_fill.png" class="imgfix" style="margin-right:3px;">
					<?php echo $lang['survey_322'] ?>
				</legend>
				<?php
				print 	RCView::div(array('style'=>'padding:5px 8px 7px 2px;'),
							RCView::radio(array('name'=>'emailSendTime','value'=>'IMMEDIATELY','class'=>'imgfix2','style'=>'','checked'=>'checked')) .
							$lang['survey_323'] . RCView::br() .
							RCView::radio(array('name'=>'emailSendTime','value'=>'EXACT_TIME','class'=>'imgfix2','style'=>'','onclick'=>"if ($('#emailSendTimeTS').val().length<1) $('#emailSendTimeTS').focus();")) .
							$lang['survey_324'] . 
							RCView::input(array('name'=>'emailSendTimeTS', 'id'=>'emailSendTimeTS', 'type'=>'text', 'class'=>'x-form-text x-form-field', 
								'style'=>'width:92px;height:14px;line-height:14px;font-size:11px;margin-left:7px;padding-bottom:1px;','onkeydown'=>"if(event.keyCode==13){return false;}", 
								'onfocus'=>"$('form#emailPartForm input[name=\"emailSendTime\"][value=\"EXACT_TIME\"]').prop('checked',true); this.value=trim(this.value); if(this.value.length == 0 && $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}",
								'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) . 
							RCView::span(array('class'=>'df','style'=>'padding-left:5px;'), DateTimeRC::get_user_format_label().' H:M') .
							// Get current time zone, if possible
							RCView::div(array('style'=>'margin:4px 0 0 22px;font-size:10px;line-height:10px;color:#777;'),
								"{$lang['survey_296']} <b>".getTimeZone()."</b>{$lang['survey_297']} <b>" . 
								DateTimeRC::format_user_datetime(NOW, 'Y-M-D_24', null, true) . "</b>{$lang['period']}"
							)
						);
				?>
			</fieldset>
			
			<?php
			## REMINDERS
			print 	RCView::fieldset(array('style'=>'padding-left:8px;border:1px solid #ccc;background-color:#F3F5F5;margin-bottom: 10px;'),
						RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
							RCView::img(array('src'=>'bell.png','class'=>'imgfix')) . 
							$lang['survey_733']
						) .  
						RCView::div(array('style'=>'padding:5px 0 10px 2px;'), 
							// Instructions
							RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;padding:3px 5px 3px 0;color:#444;'), 
								RCView::checkbox(array('id'=>"enable_reminders_chk", 'class'=>'imgfix2', 'style'=>'margin-right:3px;')) .
								$lang['survey_734'] .
								RCView::span(array('id'=>'reminders_text1'), $lang['survey_749'])
							) .
							## When to send once condition is met
							RCView::div(array('id'=>"reminders_choices_div", 'style'=>'margin-left:20px;display:none;'),
								// Next occurrence of (e.g., Work day at 11:00am)
								RCView::div(array('style'=>'padding:4px 0 1px;'), 
									RCView::radio(array('name'=>"reminder_type",'value'=>'NEXT_OCCURRENCE')) . 
									$lang['survey_735'] . RCView::SP . RCView::SP .
									RCView::select(array('name'=>"reminder_nextday_type",'style'=>'font-size:11px;', 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=NEXT_OCCURRENCE]').prop('checked',true).trigger('change'); }"), SurveyScheduler::daysofWeekOptions(), '') . RCView::SP .
									$lang['survey_424'] . RCView::SP . RCView::SP .  
									RCView::input(array('name'=>"reminder_nexttime",'type'=>'text', 'class'=>'x-form-text x-form-field time2',
										'style'=>'height:14px;line-height:14px;text-align:right;font-size:11px;width:30px;',
										'onfocus'=>"if( $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}", 
										'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=NEXT_OCCURRENCE]').prop('checked',true).trigger('change'); }")) . 
									RCView::span(array('class'=>'df', 'style'=>'padding-left: 5px;'), 'H:M')
									
								).
								// Time lag of X amount of days/hours/minutes
								RCView::div(array('style'=>'padding:1px 0;'), 
									RCView::radio(array('name'=>"reminder_type",'value'=>'TIME_LAG')) . 
									$lang['survey_735'] . RCView::SP . RCView::SP . 
									RCView::span(array('style'=>'font-size:11px;'), 
										RCView::input(array('name'=>"reminder_timelag_days",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'height:14px;line-height:14px;text-align:center;font-size:11px;width:17px;', 'value'=>'', 'maxlength'=>'3', 'onblur'=>"redcap_validate(this,'0','999','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) . 
										$lang['survey_426'] . RCView::SP . RCView::SP .  
										RCView::input(array('name'=>"reminder_timelag_hours",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'height:14px;line-height:14px;text-align:center;font-size:11px;width:12px;', 'value'=>'', 'maxlength'=>'2', 'onblur'=>"redcap_validate(this,'0','99','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) . 
										$lang['survey_427'] . RCView::SP . RCView::SP .  
										RCView::input(array('name'=>"reminder_timelag_minutes",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'height:14px;line-height:14px;text-align:center;font-size:11px;width:12px;', 'value'=>'', 'maxlength'=>'2', 'onblur'=>"redcap_validate(this,'0','99','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) . 
										$lang['survey_428']
									)
								) .
								// Exact time
								RCView::div(array('style'=>'padding:1px 0;'), 
									RCView::radio(array('name'=>"reminder_type",'value'=>'EXACT_TIME')) . 
									$lang['survey_429'] . RCView::SP . RCView::SP . 
									RCView::input(array('name'=>"reminder_exact_time", 'type'=>'text', 'class'=>'reminderdt x-form-text x-form-field', 
										'value'=>'', 'style'=>'width:92px;height:14px;line-height:14px;font-size:11px;padding-bottom:1px;', 
										'onkeydown'=>"if(event.keyCode==13){return false;}", 
										'onfocus'=>"this.value=trim(this.value); if(this.value.length == 0 && $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}" ,
										'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);", 
										'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=EXACT_TIME]').prop('checked',true).trigger('change'); }")) . 
									RCView::span(array('class'=>'df', 'style'=>'padding-left: 5px;'), DateTimeRC::get_user_format_label().' H:M')
								) .
								// Recurrence
								RCView::div(array('style'=>'margin:4px 0 5px -15px;color:#999;'),
									"&ndash; " . $lang['global_87'] . " &ndash;"
								) .
								RCView::div(array('style'=>''),
									$lang['survey_739'] . RCView::SP . RCView::SP . 
									RCView::select(array('name'=>"reminder_num",'style'=>'font-size:11px;'), array('1'=>$lang['survey_736'], '2'=>"{$lang['survey_737']} 2 {$lang['survey_738']}", 
										'3'=>"{$lang['survey_737']} 3 {$lang['survey_738']}", '4'=>"{$lang['survey_737']} 4 {$lang['survey_738']}", 
										'5'=>"{$lang['survey_737']} 5 {$lang['survey_738']}", ), '1')
								)
							)
						)
					);
			?>
			
			<!-- Email form -->
			<fieldset id="compose_email_form_fieldset" style="padding-left:8px;border:1px solid #ccc;background-color:#F3F5F5;">
				<legend style="font-weight:bold;color:#333;">
					<img src="<?php echo APP_PATH_IMAGES ?>email.png" class="imgfix" style="margin-right:3px;">
					<?php echo $lang['survey_692'] ?>
				</legend>
				<div style="padding:10px 0 10px 2px;">
					<table border=0 cellspacing=0 width=100%>
					<tr id="compose_email_from_tr">
						<!-- from drop-down -->
						<td style="vertical-align:middle;width:50px;"><?php echo $lang['global_37'] ?></td>
						<td style="vertical-align:middle;color:#555;">
						<?php echo User::emailDropDownList() ?> 
					</tr>
					<tr>
						<td style="vertical-align:middle;width:50px;padding-top:10px;"><?php echo $lang['global_38'] ?></td>
						<td style="vertical-align:middle;padding-top:10px;color:#666;font-weight:bold;"><?php echo $lang['survey_693'] ?></td>
					</tr>
					<tr id="compose_email_subject_tr">
						<!-- email subject -->
						<td valign="top" style="width:50px;padding:13px 0 0;"><?php echo $lang['survey_103'] ?></td>
						<td valign="top" style="padding:10px 0;">
							<input class="x-form-text x-form-field" style="font-family:arial;width:280px;" type="text" id="emailTitle" name="emailTitle" onkeydown="if(event.keyCode == 13){return false;}" />
							<?php if ($twilio_enabled) { ?>
								<div class="show_for_part_pref show_for_sms show_for_voice" style="color:#000066;font-size:11px;"><?php print $lang['survey_917'] ?></div>
							<?php } ?>
						</td>
					</tr>
					<tr>
						<!-- email message -->
						<td colspan="2" style="padding:5px 0 10px;">
							<textarea class="x-form-field notesbox" id="emailCont" name="emailCont" style="font-family:arial;height:100px;width:95%;"></textarea>
							<?php if ($twilio_enabled) { ?>
								<div class="show_for_voice show_for_part_pref" style="color:#000066;font-size:11px;margin-bottom:2px;"><?php print $lang['survey_918'] ?></div>
							<?php } ?>
						</td>
					</tr>
					</table>
					<!-- Text below email form -->
					<div style="padding:0 5px;">
						<div style="font-size:11px;color:#C00000;padding-bottom:6px;">
							<b><?php echo $lang['survey_105'] ?></b> <?php echo $lang['survey_104'] ?>
						</div>
						
						<div style="font-size:11px;color:#555;padding:0 5px 0 0;">
							<?php echo $lang['survey_164'] ?>
							&lt;b&gt; bold, &lt;u&gt; underline, &lt;i&gt; italics, &lt;a href="..."&gt; link, etc.
						</div>
						<!-- Piping link -->
						<div style="margin:3px 0;">
							<img src="<?php echo APP_PATH_IMAGES ?>pipe_small.gif" class="imgfix">
							<a href="javascript:;" style="font-size:11px;color:#3E72A8;text-decoration:underline;" onclick="pipingExplanation();"><?php echo $lang['design_468'] ?></a>
						</div>
						<div id="divDispPrevEmails" style="font-size:11px;<?php echo $divDispPrevEmails_display ?>">
							<?php echo $lang['survey_106'] ?>
							<input type="checkbox" id="chkDispPrevEmails" class="imgfix" onclick="
								if (this.checked == false) {
									$('#selectDispPrevEmails').val('');
								}
								$('#selectDivDispPrevEmails').toggle('blind','fast');
							"> 
						</div>
						<div id="selectDivDispPrevEmails" style="display:none;font-size:11px;padding-top:6px;">
							<select id="selectDispPrevEmails" style="font-size:11px;font-family:Tahoma,Arial;" onchange="document.getElementById('emailCont').value=this.value;">
								<option value=""> - <?php echo $lang['survey_107'] ?> - </option>
								<?php foreach ($emailSelect as $key=>$val) { ?>
								<option value="<?php echo $key ?>"><?php echo $val ?></option>
								<?php } ?>
							</select>
						</div>
					</div>
				</div>
			</fieldset>
		</td>
		<td style="padding:10px 0 0 10px;width:480px;" id="emailSendList_div" valign="top"></td>
	</tr></table>
	</form>
</div>

<?php	

// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	