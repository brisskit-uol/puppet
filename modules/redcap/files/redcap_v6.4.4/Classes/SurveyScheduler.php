<?php

// Require math functions in case special functions are used in the conditional logic
require_once APP_PATH_DOCROOT . 'ProjectGeneral/math_functions.php';


/**
 * SurveyScheduler
 * This class is used for setup and execution of the survey scheduler.
 */
class SurveyScheduler
{	
	// Current project_id for this object
	public $project_id = null;
	// Array of schedules from the surveys_scheduler table
	public $schedules = null;
	// Array with PK from surveys_scheduler table (ss_id) as array key and survey_id=>event_id as array subkeys.
	// Can be used to link directly to $schedules array using ss_id instead of survey_id-event_id.
	private $schedulePkLink = null;
	// Array of survey invitations already queued to be sent for this project
	private $surveyInvitationQueueList = null;
	// Array of survey invitations already sent for this project
	private $surveyInvitationSentList = null;
	// Set default limit for number of emails to send in one batch per cron job instance.
	// This will be used if cannot be determined from values in redcap_surveys_emails_send_rate table.
	// (ideal batch = 5 minutes long to send, so default to ~3 emails/sec)
	const MAX_EMAILS_PER_BATCH = 1000;
	// Set minimum emails per batch
	const MIN_EMAILS_PER_BATCH = 100;
	// Set the ideal length of time for a full email batch to send
	const BATCH_LENGTH_MINUTES = 5;
	// Set the minimum number of emails sent in a batch that would constitute its email rate getting added to 
	// the redcap_surveys_emails_send_rate table to thus be used in future calculations for determining email batch size.
	const MIN_RECORD_EMAILS_SENT = 20;
	

	/**
	 * CONSTRUCTOR
	 */
	public function __construct($this_project_id=null) 
	{
		// Set project_id for this object
		if ($this_project_id == null) {
			if (defined("PROJECT_ID")) {
				$this->project_id = PROJECT_ID;
			} else {
				throw new Exception('No project_id provided!');
			}
		} else {
			$this->project_id = $this_project_id;
		}
	}	
	
	
	// Determine the number of emails to send per batch (optimally 5-min worth) based upon values
	// of previously sent emails in redcap_surveys_emails_send_rate table.
	public static function determineEmailsPerBatch()
	{
		// Get average emails_per_minute from last 20 batches
		$sql = "select round(avg(emails_per_minute)*" . self::BATCH_LENGTH_MINUTES . ") 
				from redcap_surveys_emails_send_rate order by esr_id desc limit 20";
		$q = db_query($sql);
		if ($q && db_num_rows($q) > 0) {
			// Return average send time for last 20 batches
			$emails_per_minute = db_result($q, 0);
			// If calculated value is less than minimum, then use minimum instead
			return ($emails_per_minute < self::MIN_EMAILS_PER_BATCH ? self::MIN_EMAILS_PER_BATCH : $emails_per_minute);
		} else {
			// If could not determine from table, then use hard-coded default
			return self::MAX_EMAILS_PER_BATCH;
		}
	}
	
	// Return array of survey_id/event_id's of any surveys that are dependent upon *this* survey_id/event_id 
	// being completed in order to trigger Automated Invitations. (Check this to prevent infinite looping of triggers.)
	static private function getDependentSurveyEventIds($survey_id, $event_id)
	{
		$dependentSurveyEventIds = array();
		$sql = "select survey_id, event_id from redcap_surveys_scheduler where condition_surveycomplete_survey_id = $survey_id
				and condition_surveycomplete_event_id = $event_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$dependentSurveyEventIds[] = $row['survey_id']."-".$row['event_id'];
		}
		return $dependentSurveyEventIds;
	}
	
	
	// Return array of days of week choices for scheduling
	public static function daysofWeekOptions()
	{
		global $lang;
		return 	array(''=>$lang['survey_416'], "DAY"=>$lang['global_96'], "WEEKDAY"=>$lang['global_97'], "WEEKENDDAY"=>$lang['global_98'], 
					"SUNDAY"=>$lang['global_99'], "MONDAY"=>$lang['global_100'], "TUESDAY"=>$lang['global_101'], 
					"WEDNESDAY"=>$lang['global_102'], "THURSDAY"=>$lang['global_103'], "FRIDAY"=>$lang['global_104'],
					"SATURDAY"=>$lang['global_105']);
	}
	
	
	// Output HTML table for setting up the conditional survey invitation schedule for a given survey/event
	public function renderConditionalInviteSetupTable($survey_id, $event_id)
	{
		// Set variables needed
		global $Proj, $longitudinal, $lang, $user_firstname, $user_lastname, $user_email, $twilio_enabled;
		// Includes
		require_once APP_PATH_DOCROOT . "ProjectGeneral/form_renderer_functions.php";
		// Fill up $schedules array with schedules
		$this->setSchedules();
		// Add days of the week + work day + weekend day as drop-down list options
		$daysOfWeekDD = self::daysofWeekOptions();
		
		// Get list of survey_id-event_id's that are dependent upon this $survey_id and $event_id.
		// Prevent users from creating infinite loop with triggering via survey completion.
		$dependentSurveyEventIds = self::getDependentSurveyEventIds($survey_id, $event_id);	
							  
		// Create list of all surveys/event instances as array to use for looping below and also to feed a drop-down
		$surveyEvents = array();
		$surveyDD = array(''=>'--- '.$lang['survey_404'].' ---');
		// Loop through all events (even for classic)
		foreach ($Proj->eventsForms as $this_event_id=>$forms)
		{
			// Go through each form and see if it's a survey
			foreach ($forms as $form)
			{
				// Get survey_id
				$this_survey_id = isset($Proj->forms[$form]['survey_id']) ? $Proj->forms[$form]['survey_id'] : null;
				// Only display surveys, so ignore if does not have survey_id
				if (!is_numeric($this_survey_id)) continue;
				// Add form, event_id, and survey_id to drop-down array
				$title = $Proj->surveys[$this_survey_id]['title'];
				$event = $Proj->eventInfo[$this_event_id]['name_ext'];
				// Don't add this current survey-event option to drop-down (would create infinite loop)
				if (!($survey_id == $this_survey_id && $this_event_id == $event_id)) {
					$surveyDD["$this_survey_id-$this_event_id"] = "\"$title\"" . ($longitudinal ? " - $event" : "");
				}
				// Add values to array
				$surveyEvents[] = array('event_id'=>$this_event_id, 'event_name'=>$event, 'form'=>$form, 
										'survey_id'=>$this_survey_id, 'survey_title'=>$title);
			}
		}
		
		// Check if survey_id/event_id have a saved schedule
		$savedSchedule = isset($this->schedules[$survey_id][$event_id]) ? $this->schedules[$survey_id][$event_id] : false;
		// Set row attributes
		$emailSubject = label_decode($savedSchedule['email_subject']);
		$emailContent = label_decode($savedSchedule['email_content']);
		$emailSender = label_decode($savedSchedule['email_sender']);
		$conditionSurveyId = $savedSchedule['condition_surveycomplete_survey_id'];
		$conditionEventId = $savedSchedule['condition_surveycomplete_event_id'];
		$conditionSurveyCompSelected = (is_numeric($conditionSurveyId) && is_numeric($conditionEventId)) ? "$conditionSurveyId-$conditionEventId" : '';
		$conditionSurveyCompChecked = (is_numeric($conditionSurveyId) && is_numeric($conditionEventId)) ? 'checked' : '';
		$conditionAndOr = (isset($savedSchedule['condition_andor']) && $savedSchedule['condition_andor'] != '') ? label_decode($savedSchedule['condition_andor']) : 'AND';
		$conditionLogic = (isset($savedSchedule['condition_logic']) && $savedSchedule['condition_logic'] != '') ? label_decode($savedSchedule['condition_logic']) : '';
		$conditionLogicChecked = (isset($savedSchedule['condition_logic']) && $savedSchedule['condition_logic'] != '') ? 'checked' : '';
		$conditionSendTimeSelectedImmediately = (isset($savedSchedule['condition_send_time_option']) && $savedSchedule['condition_send_time_option'] == 'IMMEDIATELY') ? 'checked' : '';
		$conditionSendTimeSelectedTimeLag = (isset($savedSchedule['condition_send_time_option']) && $savedSchedule['condition_send_time_option'] == 'TIME_LAG') ? 'checked' : '';
		$conditionSendTimeSelectedNextOccur = (isset($savedSchedule['condition_send_time_option']) && $savedSchedule['condition_send_time_option'] == 'NEXT_OCCURRENCE') ? 'checked' : '';
		$conditionSendTimeSelectedExactTime = (isset($savedSchedule['condition_send_time_option']) && $savedSchedule['condition_send_time_option'] == 'EXACT_TIME') ? 'checked' : '';
		$conditionSendTimeLagDays = '';
		$conditionSendTimeLagHours = '';
		$conditionSendTimeLagMinutes = '';			
		$conditionSendNextDayType = '';
		$conditionSendNextTime = '';
		$conditionExactTimeValue = '';
		if (isset($savedSchedule['condition_send_time_option']) && $savedSchedule['condition_send_time_option'] == 'TIME_LAG') {
			$conditionSendTimeLagDays = (isset($savedSchedule['condition_send_time_lag_days']) && $savedSchedule['condition_send_time_lag_days'] != '') ? $savedSchedule['condition_send_time_lag_days'] : 0;
			$conditionSendTimeLagHours = (isset($savedSchedule['condition_send_time_lag_hours']) && $savedSchedule['condition_send_time_lag_hours'] != '') ? $savedSchedule['condition_send_time_lag_hours'] : 0;
			$conditionSendTimeLagMinutes = (isset($savedSchedule['condition_send_time_lag_minutes']) && $savedSchedule['condition_send_time_lag_minutes'] != '') ? $savedSchedule['condition_send_time_lag_minutes'] : 0;
		} elseif (isset($savedSchedule['condition_send_time_option']) && $savedSchedule['condition_send_time_option'] == 'NEXT_OCCURRENCE') {			
			$conditionSendNextDayType = (isset($savedSchedule['condition_send_next_day_type']) && $savedSchedule['condition_send_next_day_type'] != '') ? $savedSchedule['condition_send_next_day_type'] : '';
			$conditionSendNextTime = (isset($savedSchedule['condition_send_next_time']) && $savedSchedule['condition_send_next_time'] != '') ? substr($savedSchedule['condition_send_next_time'], 0, 5) : '';
		} elseif (isset($savedSchedule['condition_send_time_option']) && $savedSchedule['condition_send_time_option'] == 'EXACT_TIME' && $savedSchedule['condition_send_time_exact'] != '') {
			list ($this_date, $this_time) = explode(" ", $savedSchedule['condition_send_time_exact']);
			$conditionExactTimeValue = trim(DateTimeRC::format_ts_from_ymd($this_date) . " " . substr($this_time, 0, 5));
		}
		if (isset($savedSchedule['active'])) {
			$scheduleActiveSelected = ($savedSchedule['active'] == '1') ? 'checked' : '';
			$scheduleInactiveSelected = ($savedSchedule['active'] == '0') ? 'checked' : '';
			$scheduleActiveClass = ($savedSchedule['active'] == '1') ? 'darkgreen' : 'red';
		} else {
			$scheduleActiveSelected = $scheduleInactiveSelected = '';
			$scheduleActiveClass = 'gray';
		}
		$reminder_type = (isset($savedSchedule['reminder_type']) && $savedSchedule['reminder_type'] != '') ? $savedSchedule['reminder_type'] : '';
		$reminder_num = '1';
		$reminder_timelag_days = '';
		$reminder_timelag_hours = '';
		$reminder_timelag_minutes = '';			
		$reminder_nextday_type = '';
		$reminder_nexttime = '';
		$reminder_exact_time = '';
		if ($reminder_type == 'TIME_LAG') {
			$reminder_timelag_days = (isset($savedSchedule['reminder_timelag_days']) && $savedSchedule['reminder_timelag_days'] != '') ? $savedSchedule['reminder_timelag_days'] : 0;
			$reminder_timelag_hours = (isset($savedSchedule['reminder_timelag_hours']) && $savedSchedule['reminder_timelag_hours'] != '') ? $savedSchedule['reminder_timelag_hours'] : 0;
			$reminder_timelag_minutes = (isset($savedSchedule['reminder_timelag_minutes']) && $savedSchedule['reminder_timelag_minutes'] != '') ? $savedSchedule['reminder_timelag_minutes'] : 0;
			$reminder_num = $savedSchedule['reminder_num'];
		} elseif ($reminder_type == 'NEXT_OCCURRENCE') {			
			$reminder_nextday_type = (isset($savedSchedule['reminder_nextday_type']) && $savedSchedule['reminder_nextday_type'] != '') ? $savedSchedule['reminder_nextday_type'] : '';
			$reminder_nexttime = (isset($savedSchedule['reminder_nexttime']) && $savedSchedule['reminder_nexttime'] != '') ? substr($savedSchedule['reminder_nexttime'], 0, 5) : '';
			$reminder_num = $savedSchedule['reminder_num'];
		} elseif ($reminder_type == 'EXACT_TIME' && $savedSchedule['reminder_exact_time'] != '') {
			list ($this_date, $this_time) = explode(" ", $savedSchedule['reminder_exact_time']);
			$reminder_exact_time = trim(DateTimeRC::format_ts_from_ymd($this_date) . " " . substr($this_time, 0, 5));
		}
		$reminder_type_selected_timelag = ($reminder_type == 'TIME_LAG') ? 'checked' : '';
		$reminder_type_selected_next_occurrence = ($reminder_type == 'NEXT_OCCURRENCE') ? 'checked' : '';
		$reminder_type_selected_exacttime = ($reminder_type == 'EXACT_TIME') ? 'checked' : '';
		$reminder_checkbox_checked = ($reminder_type != '') ? 'checked' : '';
		$reminder_div_display = ($reminder_type == '') ? 'display:none;' : '';
		$reminders_text1_display = ($reminder_type == '') ? 'display:none;' : 'display:inline;';
		$reminder_num_disabled = ($reminder_type == 'EXACT_TIME') ? "disabled" : "";		
		if ($savedSchedule['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $savedSchedule['delivery_type'] == 'SMS_INITIATE'
			 || $savedSchedule['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL') {
			$emailSubjectDisplay = $emailFromDisplay = "display:none;";
		} elseif ($savedSchedule['delivery_type'] == 'VOICE_INITIATE') {
			$emailComposeBoxDisplay = $emailSubjectDisplay = $emailFromDisplay = "display:none;";
		} else {
			$emailComposeBoxDisplay = $emailSubjectDisplay = $emailFromDisplay = "";
		}
		
		// Create HTML content
		$html = // Instructions
				RCView::div(array('style'=>'padding-bottom:5px;line-height:14px;'),
					RCView::b($lang['global_24'].$lang['colon']) . RCView::SP .
					$lang['survey_744'] . RCView::SP .
					// "Tell me more" link
					RCView::a(array('href'=>'javascript:;','style'=>'text-decoration:underline;','onclick'=>"$(this).hide();$('#defineAutoInvitesMoreInfo').toggle('fade',function(){ fitDialog($('#popupSetUpCondInvites')); $('#popupSetUpCondInvites').dialog('option', 'position', { my: 'center', at: 'center', of: window }); });"), $lang['global_58']) .
					RCView::div(array('id'=>'defineAutoInvitesMoreInfo','style'=>'padding-top:10px;display:none;'), 
						$lang['survey_745'] . RCView::br(). RCView::br() . 
						$lang['survey_406'] . RCView::br(). RCView::br() . $lang['survey_414'] .
						($longitudinal ? RCView::br(). RCView::br() . $lang['survey_407'] : '') .
						RCView::br(). RCView::br()
					) .
					// If ASI has already been scheduled, give a note that changing it only applies to future schedules (not to those already scheduled)
					(!$savedSchedule ? '' :
						RCView::span(array('style'=>'color:#C00000'), 
							RCView::b($lang['global_03'] . $lang['colon']) . " " .$lang['survey_555']
						)
					)
				) .
				// Hidden input for preventing dependent survey/events's from being selected in the drop-down (to prevent infinite looping of automated invites)
				RCView::input(array('id'=>'dependent-survey-event','type'=>'hidden','value'=>implode(',', $dependentSurveyEventIds))) .
				// Table with set-up options
				RCView::table(array('cellspacing'=>'0','border'=>'0','style'=>'table-layout:fixed;'),
					RCView::tr('',
						RCView::td(array('valign'=>'top','style'=>'width:380px;padding:6px 10px 0 0;'),
							## INFO
							RCView::fieldset(array('class'=>'darkgreen', 'style'=>'padding:0 0 0 8px;border-width:1px;margin-bottom:10px;'),
								RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
									RCView::img(array('src'=>'txt.gif','class'=>'imgfix')) . 
									$lang['survey_340']
								) .
								RCView::div(array('style'=>'padding:3px 8px 8px 2px;'),
									// Survey title
									RCView::div(array('style'=>'color:#800000;'),
										RCView::b($lang['survey_310']) . 
										RCView::span(array('style'=>'font-size:13px;margin-left:8px;'), 
											RCView::escape($Proj->surveys[$_GET['survey_id']]['title'])
										)				
									) .
									// Event name (if longitudinal)
									RCView::div(array('style'=>'color:#000066;padding-top:3px;' . ($longitudinal ? '' : 'display:none;')),
										RCView::b($lang['bottom_23']) . 
										RCView::span(array('style'=>'font-size:13px;margin-left:8px;'), 
											RCView::escape($Proj->eventInfo[$_GET['event_id']]['name_ext'])
										) 
									)
								)
							) . 
							// If TWILIO is enabled, give option to send as SMS or VOICE
							(!$twilio_enabled ? '' :
								RCView::fieldset(array('style'=>'padding:0 0 2px 8px;background-color:#FFFFD3;border:1px solid #FFC869;margin-bottom:10px;'),
									RCView::legend(array('style'=>'color:#333;'),
										RCView::img(array('src'=>'arrow_right_curve.png', 'class'=>'imgfix', 'style'=>'margin-right:2px;')) .
										RCView::b($lang['survey_741'] . " " . $lang['survey_687']). " " . $lang['survey_691']
									) .
									RCView::div(array('style'=>'padding:6px 2px 6px 2px;'),
										RCView::select(array('name'=>'delivery_type', 'class'=>'x-form-text x-form-field', 'style'=>'padding-right:0;height:22px;', 'onchange'=>"setInviteDeliveryMethod(this)"),
											Survey::getDeliveryMethods(true, true), $savedSchedule['delivery_type']) .
										RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'style'=>'margin-left:5px;font-size: 12px;', 
											'title'=>$lang['form_renderer_02'], 'onclick'=>"deliveryPrefExplain();"), '?')
									)
								)
							) .
							## COMPOSE EMAIL SUBJECT AND MESSAGE
							RCView::fieldset(array('id'=>'compose_email_form_fieldset', 'style'=>$emailComposeBoxDisplay.'padding-left:8px;background-color:#FFFFD3;border:1px solid #FFC869;'),
								RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
									RCView::img(array('src'=>'email.png','class'=>'imgfix')) . 
									($twilio_enabled ? $lang['survey_742'] : $lang['survey_741']) . " " . $lang['survey_692']
								) .
								RCView::div(array('style'=>'padding:10px 0 10px 2px;'),							
									RCView::table(array('cellspacing'=>'0','border'=>'0','width'=>'100%'),
										// From 
										RCView::tr(array('id'=>'compose_email_from_tr', 'style'=>$emailFromDisplay),
											RCView::td(array('style'=>'vertical-align:top;width:50px;padding-top:2px;'),
												$lang['global_37']
											) .
											RCView::td(array('style'=>'vertical-align:top;color:#555;'),
												User::emailDropDownListAllUsers($emailSender, true, 'email_sender', 'email_sender') .
												RCView::div(array('style'=>'padding:2px 0 0 2px;font-size:11px;color:#777;'), 
													$lang['survey_417']
												)
											)
										) .
										// To
										RCView::tr('',
											RCView::td(array('style'=>'vertical-align:middle;width:50px;padding-top:10px;'),
												$lang['global_38']
											) .
											RCView::td(array('style'=>'vertical-align:middle;padding-top:10px;color:#666;font-weight:bold;'),
												$lang['survey_338']
											)
										) .
										// Subject
										RCView::tr(array('id'=>'compose_email_subject_tr', 'style'=>$emailSubjectDisplay),
											RCView::td(array('valign'=>'top', 'style'=>'padding:13px 0 0;width:50px;'),
												$lang['survey_103']
											) .
											RCView::td(array('valign'=>'top', 'style'=>'padding:10px 0;'),
												'<input class="x-form-text x-form-field" style="font-family:arial;width:280px;" type="text" id="sssubj-'."$survey_id-$event_id".'" onkeydown="if(event.keyCode == 13){return false;}" value="'.cleanHtml2(str_replace('"', '&quot;', label_decode($emailSubject))).'"/>' .
												(!$twilio_enabled ? "" : '<div class="show_for_part_pref show_for_sms show_for_voice" style="padding-top:1px;line-height:11px;color:#000066;font-size:11px;">'.$lang['survey_917'].'</div>')
											)
										) .
										// Message
										RCView::tr('',
											RCView::td(array('colspan'=>'2','style'=>'padding:5px 0 10px;'),
												'<textarea class="x-form-field notesbox" id="ssemail-'."$survey_id-$event_id".'" style="font-family:arial;height:120px;width:95%;">'.nl2br(label_decode($emailContent)).'</textarea>' .
												(!$twilio_enabled ? "" : '<div class="show_for_voice show_for_part_pref" style="line-height:11px;color:#000066;font-size:11px;">'.$lang['survey_918'].'</div>')
											)
										)
									)
								) . 
								// Extra instructions
								RCView::div(array('style'=>'padding:0 5px;'),
									RCView::div(array('style'=>'font-size:11px;color:#C00000;padding-bottom:6px;'),
										RCView::b($lang['survey_105']) . RCView::SP . $lang['survey_104']
									) .
									RCView::div(array('style'=>'font-size:11px;color:#555;padding-bottom:6px;'),
										$lang['survey_164'] .
										'&lt;b&gt; bold, &lt;u&gt; underline, &lt;i&gt; italics, &lt;a href="..."&gt; link, etc.'
									) .
									// Piping link
									RCView::div(array('style'=>'padding-bottom:6px;'),
										RCView::img(array('src'=>'pipe_small.gif','class'=>'imgfix')) . 
										RCView::a(array('href'=>'javascript:;','style'=>'font-size:11px;color:#3E72A8;text-decoration:underline;','onclick'=>'pipingExplanation();'), $lang['design_468'])
									)
								)
							)
						) .
						## SCHEDULER CONDITIONAL SETTINGS
						RCView::td(array('valign'=>'top','style'=>'padding:6px 0 0 10px;width:480px;'),
							RCView::fieldset(array('style'=>'padding-left:8px;background-color:#FFFFD3;border:1px solid #FFC869;margin-bottom: 10px;'),
								RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
									RCView::img(array('src'=>'gear.png','class'=>'imgfix')) . 
									($twilio_enabled ? $lang['survey_743'] : $lang['survey_742']) . " " . $lang['survey_341']
								) .
								RCView::div(array('style'=>'padding:10px 0 10px 2px;'), 
									// Select a condition
									RCView::div(array('style'=>'font-weight:bold;margin-bottom:2px;font-size:13px;color:#800000;'), 
										$lang['survey_418']
									) . 
									// When survey is completed
									RCView::div(array('style'=>'text-indent:-1.9em;margin-left:1.9em;padding:1px 0;'), 
										RCView::checkbox(array('id'=>"sscondoption-surveycomplete-$survey_id-$event_id",'class'=>'imgfix2',$conditionSurveyCompChecked=>$conditionSurveyCompChecked)) . 
										$lang['survey_419'] . 
										RCView::br() . 
										// Drop-down of surveys/events
										RCView::select(array('id'=>"sscondoption-surveycompleteids-$survey_id-$event_id",'style'=>'font-size:11px;width:360px;max-width:360px;',
											'onchange'=>"$('#sscondoption-surveycomplete-$survey_id-$event_id').prop('checked', (this.value.length > 0) ); hasDependentSurveyEvent(this);"), $surveyDD, $conditionSurveyCompSelected, 200)
									) .   
									// AND/OR drop-down list for conditions
									RCView::div(array('style'=>'padding:2px 0 1px;'), 
										RCView::select(array('id'=>"sscondoption-andor-$survey_id-$event_id",'style'=>'font-size:11px;'), array('AND'=>$lang['global_87'],'OR'=>$lang['global_46']), $conditionAndOr)
									) .  
									// When logic becomes true
									RCView::div(array('style'=>'text-indent:-1.9em;margin-left:1.9em;'), 
										RCView::checkbox(array('id'=>"sscondoption-logic-$survey_id-$event_id",'class'=>'imgfix2',$conditionLogicChecked=>$conditionLogicChecked)) . 
										$lang['survey_420'] . RCView::br() . 
										RCView::textarea(array('id'=>"sscondlogic-$survey_id-$event_id",'class'=>'x-form-field', 'style'=>'line-height:12px;font-size:11px;width:350px;height:24px;',
											'onblur'=>"this.value=trim(this.value); if(this.value.length > 0) { $('#sscondoption-logic-$survey_id-$event_id').prop('checked',true); } if(!checkLogicErrors(this.value,1,true)){validate_auto_invite_logic($(this));}"), $conditionLogic
										) .
										RCView::div(array('style'=>'text-indent:-1.9em;margin-left:1.9em;font-size:11px;color:#666;padding-right:10px;'), 
											RCView::div(array('style'=>'float:left;'), 
												($longitudinal ? "(e.g., [enrollment_arm_1][age] > 30 and [enrollment_arm_1][gender] = \"1\")" : "(e.g., [age] > 30 and [gender] = \"1\")")
											) .
											RCView::div(array('style'=>'float:right;'), 
												RCView::a(array('href'=>'javascript:;','style'=>'text-decoration:underline;font-size:10px;','onclick'=>"helpPopup('AutomatedInvitations')"), $lang['dataqueries_79'])
											) .
											RCView::div(array('class'=>'clear'), '')
										)
									)
								)
							) .
							## WHEN TO SEND ONCE CONDITION IS MET
							RCView::fieldset(array('style'=>'padding-left:8px;background-color:#FFFFD3;border:1px solid #FFC869;margin-bottom: 10px;'),
								RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
									RCView::img(array('src'=>'clock_fill.png','class'=>'imgfix')) . 
									($twilio_enabled ? $lang['survey_780'] : $lang['survey_743']) . " " . $lang['survey_421']
								) .  
								RCView::div(array('style'=>'padding:5px 0 10px 2px;'), 
									## When to send once condition is met
									RCView::div(array('id'=>"sscondtimes-$survey_id-$event_id"),
										// Immediately
										RCView::div(array('style'=>'padding:1px 0;'), 
											RCView::radio(array('name'=>"sscondwhen-$survey_id-$event_id",'value'=>'IMMEDIATELY',$conditionSendTimeSelectedImmediately=>$conditionSendTimeSelectedImmediately)) . 
											$lang['survey_422']
										) .
										// Next occurrence of (e.g., Work day at 11:00am)
										RCView::div(array('style'=>'padding:1px 0;'), 
											RCView::radio(array('name'=>"sscondwhen-$survey_id-$event_id",'value'=>'NEXT_OCCURRENCE',$conditionSendTimeSelectedNextOccur=>$conditionSendTimeSelectedNextOccur)) . 
											$lang['survey_423'] . RCView::SP . RCView::SP .
											RCView::select(array('id'=>"sscond-nextdaytype-$survey_id-$event_id",'style'=>'font-size:11px;'), $daysOfWeekDD, $conditionSendNextDayType) . RCView::SP .
											$lang['survey_424'] . RCView::SP . RCView::SP .  
											RCView::input(array('id'=>"sscond-nexttime-$survey_id-$event_id",'type'=>'text', 'class'=>'x-form-text x-form-field time2', 'value'=>$conditionSendNextTime,
												'style'=>'height:14px;line-height:14px;text-align:right;font-size:11px;width:30px;',
												'onfocus'=>"if( $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}")) . 
											RCView::span(array('class'=>'df'), 'H:M')
										).
										// Time lag of X amount of days/hours/minutes
										RCView::div(array('style'=>'padding:1px 0;'), 
											RCView::radio(array('name'=>"sscondwhen-$survey_id-$event_id",'value'=>'TIME_LAG',$conditionSendTimeSelectedTimeLag=>$conditionSendTimeSelectedTimeLag)) . 
											$lang['survey_425'] . RCView::SP . RCView::SP . 
											RCView::span(array('style'=>'font-size:11px;'), 
												RCView::input(array('id'=>"sscond-timelagdays-$survey_id-$event_id",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'height:14px;line-height:14px;text-align:center;font-size:11px;width:17px;', 'value'=>$conditionSendTimeLagDays, 'maxlength'=>'3', 'onblur'=>"redcap_validate(this,'0','999','hard','int');")) . 
												$lang['survey_426'] . RCView::SP . RCView::SP .  
												RCView::input(array('id'=>"sscond-timelaghours-$survey_id-$event_id",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'height:14px;line-height:14px;text-align:center;font-size:11px;width:12px;', 'value'=>$conditionSendTimeLagHours, 'maxlength'=>'2', 'onblur'=>"redcap_validate(this,'0','99','hard','int');")) . 
												$lang['survey_427'] . RCView::SP . RCView::SP .  
												RCView::input(array('id'=>"sscond-timelagminutes-$survey_id-$event_id",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'height:14px;line-height:14px;text-align:center;font-size:11px;width:12px;', 'value'=>$conditionSendTimeLagMinutes, 'maxlength'=>'2', 'onblur'=>"redcap_validate(this,'0','99','hard','int');")) . 
												$lang['survey_428']
											)
										) .
										// Exact time
										RCView::div(array('style'=>'padding:1px 0;'), 
											RCView::radio(array('name'=>"sscondwhen-$survey_id-$event_id",'value'=>'EXACT_TIME', $conditionSendTimeSelectedExactTime=>$conditionSendTimeSelectedExactTime)) . 
											$lang['survey_429'] . RCView::SP . RCView::SP . 
											RCView::input(array('id'=>"ssdt-$survey_id-$event_id", 'type'=>'text', 'class'=>'ssdt x-form-text x-form-field', 
												'value'=>$conditionExactTimeValue, 'style'=>'width:92px;height:14px;line-height:14px;font-size:11px;padding-bottom:1px;', 
												'onkeydown'=>"if(event.keyCode==13){return false;}", 
												'onfocus'=>"this.value=trim(this.value); if(this.value.length == 0 && $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}" ,
												'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) . 
											RCView::span(array('class'=>'df'), DateTimeRC::get_user_format_label().' H:M')
										)
									)
								)
							) .
							
							## REMINDERS
							RCView::fieldset(array('style'=>'padding-left:8px;background-color:#FFFFD3;border:1px solid #FFC869;margin-bottom: 10px;'),
								RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
									RCView::img(array('src'=>'bell.png','class'=>'imgfix')) . 
									$lang['survey_746'] . " " . $lang['survey_733']
								) .  
								RCView::div(array('style'=>'padding:5px 0 10px 2px;'), 
									// Instructions
									RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;padding:3px 15px 3px 0;color:#444;'), 
										RCView::checkbox(array('id'=>"enable_reminders_chk", 'class'=>'imgfix2', 'style'=>'margin-right:3px;', $reminder_checkbox_checked=>$reminder_checkbox_checked)) .
										$lang['survey_734'] .
										RCView::span(array('id'=>'reminders_text1', 'style'=>$reminders_text1_display), $lang['survey_749'])
									) .
									## When to send once condition is met
									RCView::div(array('id'=>"reminders_choices_div", 'style'=>'margin-left:20px;'.$reminder_div_display),
										// Next occurrence of (e.g., Work day at 11:00am)
										RCView::div(array('style'=>'padding:4px 0 1px;'), 
											RCView::radio(array('name'=>"reminder_type",'value'=>'NEXT_OCCURRENCE', $reminder_type_selected_next_occurrence=>$reminder_type_selected_next_occurrence)) . 
											$lang['survey_735'] . RCView::SP . RCView::SP .
											RCView::select(array('name'=>"reminder_nextday_type",'style'=>'font-size:11px;', 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=NEXT_OCCURRENCE]').prop('checked',true).trigger('change'); }"), SurveyScheduler::daysofWeekOptions(), $reminder_nextday_type) . RCView::SP .
											$lang['survey_424'] . RCView::SP . RCView::SP .  
											RCView::input(array('name'=>"reminder_nexttime",'type'=>'text', 'class'=>'x-form-text x-form-field time2',
												'style'=>'height:14px;line-height:14px;text-align:right;font-size:11px;width:30px;', 'value'=>$reminder_nexttime,
												'onfocus'=>"if( $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}", 
												'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=NEXT_OCCURRENCE]').prop('checked',true).trigger('change'); }")) . 
											RCView::span(array('class'=>'df', 'style'=>'padding-left: 5px;'), 'H:M')
											
										).
										// Time lag of X amount of days/hours/minutes
										RCView::div(array('style'=>'padding:1px 0;'), 
											RCView::radio(array('name'=>"reminder_type",'value'=>'TIME_LAG', $reminder_type_selected_timelag=>$reminder_type_selected_timelag)) . 
											$lang['survey_735'] . RCView::SP . RCView::SP . 
											RCView::span(array('style'=>'font-size:11px;'), 
												RCView::input(array('name'=>"reminder_timelag_days",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'height:14px;line-height:14px;text-align:center;font-size:11px;width:17px;', 'value'=>$reminder_timelag_days, 'maxlength'=>'3', 'onblur'=>"redcap_validate(this,'0','999','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) . 
												$lang['survey_426'] . RCView::SP . RCView::SP .  
												RCView::input(array('name'=>"reminder_timelag_hours",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'height:14px;line-height:14px;text-align:center;font-size:11px;width:12px;', 'value'=>$reminder_timelag_hours, 'maxlength'=>'2', 'onblur'=>"redcap_validate(this,'0','99','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) . 
												$lang['survey_427'] . RCView::SP . RCView::SP .  
												RCView::input(array('name'=>"reminder_timelag_minutes",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'height:14px;line-height:14px;text-align:center;font-size:11px;width:12px;', 'value'=>$reminder_timelag_minutes, 'maxlength'=>'2', 'onblur'=>"redcap_validate(this,'0','99','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) . 
												$lang['survey_428']
											)
										) .
										// Exact time
										RCView::div(array('style'=>'padding:1px 0;'), 
											RCView::radio(array('name'=>"reminder_type",'value'=>'EXACT_TIME', $reminder_type_selected_exacttime=>$reminder_type_selected_exacttime)) . 
											$lang['survey_429'] . RCView::SP . RCView::SP . 
											RCView::input(array('name'=>"reminder_exact_time", 'type'=>'text', 'class'=>'reminderdt x-form-text x-form-field', 
												'value'=>$reminder_exact_time, 'style'=>'width:92px;height:14px;line-height:14px;font-size:11px;padding-bottom:1px;', 
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
											RCView::select(array('name'=>"reminder_num", $reminder_num_disabled=>$reminder_num_disabled, 'style'=>'font-size:11px;'), array('1'=>$lang['survey_736'], '2'=>"{$lang['survey_737']} 2 {$lang['survey_738']}", 
												'3'=>"{$lang['survey_737']} 3 {$lang['survey_738']}", '4'=>"{$lang['survey_737']} 4 {$lang['survey_738']}", 
												'5'=>"{$lang['survey_737']} 5 {$lang['survey_738']}", ), $reminder_num)
										)
									)
								)
							).
							// Is schedule activated?
							RCView::fieldset(array('id'=>'condSurvPopupActiveBox','class'=>$scheduleActiveClass,'style'=>'padding:0 0 0 8px;'),
								RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
									RCView::img(array('src'=>'email_check.png','class'=>'imgfix')) . 
									($twilio_enabled ? $lang['survey_781'] : $lang['survey_780']) . " " . $lang['survey_430']
								) .
								RCView::div(array('style'=>'padding:6px 4px 10px 2px;'),
									RCView::div(array('style'=>'padding-bottom:6px;font-size:11px;'),
										$lang['survey_431']
									) .
									RCView::div(array('style'=>''),
										RCView::radio(array('name'=>"ssactive-$survey_id-$event_id",'onclick'=>"$('#condSurvPopupActiveBox').removeClass('gray').removeClass('red').addClass('darkgreen');",'value'=>'1',$scheduleActiveSelected=>$scheduleActiveSelected)) . 
										$lang['survey_432'] . RCView::SP . RCView::SP .
										RCView::radio(array('name'=>"ssactive-$survey_id-$event_id",'onclick'=>"$('#condSurvPopupActiveBox').removeClass('gray').removeClass('darkgreen').addClass('red');",'value'=>'0',$scheduleInactiveSelected=>$scheduleInactiveSelected)) . 
										$lang['survey_433']
									)
								)
							)
						)
					)
				);
		
		// Return the HTML 
		return $html;
	}
	
	
	// Fill up array with the survey schedules for this project
	private function setSchedules() 
	{
		global $Proj;
		// Set $schedules as array
		if ($this->schedules == null)
		{
			// Set these as arrays
			$this->schedules = array();
			$this->schedulePkLink = array();
			// Query to get schedules for project and put in array
			$sql = "select r.*, s.form_name from redcap_surveys_scheduler r, redcap_surveys s 
					where s.survey_id = r.survey_id and s.project_id = " . $this->project_id . "
					and r.event_id in (" . prep_implode(array_keys($Proj->eventInfo)) . ")";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				// Make sure the survey hasn't been deleted (and thus orphaned)
				if (!isset($Proj->forms[$row['form_name']])) continue;
				// Use survey_id and event_id for array keys
				$survey_id = $row['survey_id'];
				$event_id = $row['event_id'];
				$ss_id = $row['ss_id'];
				// Remove unnecessary items
				unset($row['survey_id'], $row['event_id'], $row['ss_id'], $row['form_name']);
				// Add to arrays
				$this->schedules[$survey_id][$event_id] = $row;
				$this->schedulePkLink[$ss_id][$survey_id] = $event_id;
			}
		}
	}
	
	
	// Display a report of the error check for the survey scheduler
	public function renderProjectScheduleErrorTable()
	{
		// Fill up $schedules array with schedules
		$this->setSchedules();
		print_array($this->schedules);
		
		// CHECK SCHEDULE ATTRIBUTES: Make sure all attributes are accounted for (nothing missing)
		$errors = $this->checkScheduleAttr();		
		
		// WORKFLOW LOGIC CHECK: Find starting-point surveys (exact time for survey invite)
		$startingPoints = array();
		foreach ($this->schedules as $survey_id=>$events) {
			foreach ($events as $event_id=>$attr) {			
				// Where to start w/o exact time?
				$startingPoints[$survey_id][$event_id] = $attr;
			}
		}
		print_array($startingPoints);
	
	}
	
	/* 
	### NOT COMPLETE
	// Check attributes of survey schedule to make sure nothing is missing
	private function checkScheduleAttr()
	{
		// Initialize vars
		$errors = array();
		// Fill up $schedules array with schedules
		$this->setSchedules();
		// Loop through schedules and check attributes of each
		foreach ($this->schedules as $survey_id=>$events) 
		{
			foreach ($events as $event_id=>$attr) 
			{
				// Check email attrs
				if ($attr['email_subject'] == '') $errors[] = "Email invitation has no subject.";
				if ($attr['email_content'] == '') $errors[] = "Email invitation has no content.";
				// Make sure we have a trigger (logic and/or survey completion)
				if (!($attr['condition_logic'] != '' 
					|| (is_numeric($attr['condition_surveycomplete_survey_id']) && is_numeric($attr['condition_surveycomplete_event_id'])))) 
				{
					$errors[] = "A condition has not been specified for when to send email invitations.";
				}
				// Check temporal settings
				else {
					// Check is has temporal component set
					if ($attr['condition_send_time_option'] == '') {
						$errors[] = "The time component denoting when to send email invitations has not been set.";
					}
					// Check if have values for NextOccurrence 
					elseif ($attr['condition_send_time_option'] == 'NEXT_OCCURRENCE') {
						if ($attr['condition_send_next_day_type'] == '') $errors[] = "The day component is missing for when to send email invitations after conditions are met.";
						if ($attr['condition_send_next_time'] == '') $errors[] = "The time value is missing for when to send email invitations after conditions are met.";
					} 
					// Check if have values for TimeLag
					elseif ($attr['condition_send_time_option'] == 'TIME_LAG') {
						if ($attr['email_subject'] == '') $errors[] = "Email invitation has no subject.";
						if ($attr['email_content'] == '') $errors[] = "Email invitation has no content.";
					}	
					// Check if has exact_time date/time
					elseif ($attr['condition_send_time_option'] == 'EXACT_TIME') {
						if ($attr['email_subject'] == '') $errors[] = "Email invitation has no subject.";
						if ($attr['email_content'] == '') $errors[] = "Email invitation has no content.";
					}					
				}
			}
		}
		// Return 
		return $errors;
	}
	*/
	

	
	// Return array of drop-down options of ALL surveys and, if longitudinal, the events for which they're designated
	public function getInvitationLogSurveyList($includeAllEventsOptionsForLongitudinal=true)
	{
		global $lang, $Proj, $longitudinal;
		$surveyEventOptions = array();
		// If longitudinal, then first display list of all surveys for ALL events (set 0 for event in drop-down value)
		if ($includeAllEventsOptionsForLongitudinal && $longitudinal) {
			foreach ($Proj->surveys as $this_survey_id=>$survey_attr) {
				// Add this survey/event as drop-down option
				$surveyEventOptions["$this_survey_id-0"] = "\"{$survey_attr['title']}\" ".$lang['survey_434'];
			}
		}
		// Loop through each event and output each where this form is designated
		foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
			// Loop through forms
			foreach ($these_forms as $form_name) {
				// Ignore if not a survey
				if (!isset($Proj->forms[$form_name]['survey_id'])) continue;
				// Get survey_id
				$this_survey_id = $Proj->forms[$form_name]['survey_id'];
				// If longitudinal, add event name
				$event_name = ($longitudinal) ? " - ".$Proj->eventInfo[$this_event_id]['name_ext'] : "";
				// If survey title is blank (because using a logo instead), then insert the instrument name
				$survey_title = ($Proj->surveys[$this_survey_id]['title'] == "") ? $Proj->forms[$form_name]['menu'] : $Proj->surveys[$this_survey_id]['title'];
				// Truncate survey title if too long
				if (strlen($survey_title.$event_name) > 70) {
					$survey_title = substr($survey_title, 0, 67-strlen($event_name)) . "...";
				}
				// Add this survey/event as drop-down option
				$surveyEventOptions["$this_survey_id-$this_event_id"] = "\"$survey_title\"$event_name";
			}
		}
		// Return the array of surveys
		return $surveyEventOptions;
	}			
	
	// Display a table listing all survey invitations (past, present, and future) with filters and paging
	public function renderSurveyInvitationLog()
	{
		// Initialize vars
		global  $Proj, $longitudinal, $table_pk, $table_pk_label, $lang, $user_rights, 
				$enable_participant_identifiers, $survey_email_participant_field, $twilio_enabled;
		
		// Set error msg default
		$errorMsg = '';
		
		// Set NOW in user defined date format but with military time
		$now_user_date_military_time = DateTimeRC::format_ts_from_ymd(TODAY).date(' H:i');
				
		## DEFINE FILTERING VALUES
		// Set some flags to disable buttons
		$disableViewPastInvites = $disableViewFutureInvites = "";
		// Set defaults
		if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum'])) {
			// do nothing
		} elseif (!isset($_GET['pagenum'])) {
			$_GET['pagenum'] = 1;
		} else {
			$_GET['pagenum'] = 'ALL';
		}
		if (!isset($_GET['filterInviteType']) || (isset($_GET['filterInviteType']) && !in_array($_GET['filterInviteType'], array('1','0','-1')))) $_GET['filterInviteType'] = '';
		if (!isset($_GET['filterResponseType'])) $_GET['filterResponseType'] = '';
		if (!isset($_GET['filterSurveyEvent'])) $_GET['filterSurveyEvent'] = '0-0';
		// Santize all filter inputs
		$valTypes = getValTypes();
		// Run the value through the regex pattern
		if (!isset($_GET['filterBeginTime'])) {
			// Default beginTime = right now
			$_GET['filterBeginTime'] = $now_user_date_military_time;
		}
		if (!isset($_GET['filterEndTime'])) {
			// Default endTime
			$_GET['filterEndTime'] = '';
		}
		// Set flags (if timestamp is within the same hour as now, then consider it now)
		if ($_GET['filterBeginTime'] == '' && substr($_GET['filterEndTime'], 0, -2) == substr($now_user_date_military_time, 0, -2)) {
			$disableViewPastInvites = "disabled";
		}
		if ($_GET['filterEndTime'] == '' && substr($_GET['filterBeginTime'], 0, -2) == substr($now_user_date_military_time, 0, -2)) {
			$disableViewFutureInvites = "disabled";
		}
		if (!isset($_GET['filterReminders'])) {
			$_GET['filterReminders'] = '0';
		}
		// Set survey_id and event_id for filtering
		list ($survey_id, $event_id) = explode('-', $_GET['filterSurveyEvent'], 2);
		if (!is_numeric($survey_id) || $survey_id == '0') $survey_id = null;
		if (!is_numeric($event_id)  || $event_id  == '0') $event_id = null;
		// If project is not longitudinal, then constrict to only single event 
		if (!$longitudinal) $event_id = $Proj->firstEventId;
	
		// If user is in a DAG, only allow them to see participants in their DAG
		$dag_records = array();
		if ($user_rights['group_id'] != '')
		{
			// Validate DAG that user is in
			$dags = $Proj->getGroups();
			if (isset($dags[$user_rights['group_id']])) {
				$dag_records = Records::getData('array', array(), $table_pk, array(), $user_rights['group_id']);
			}
		}
		
		// Get list of participant_ids/records (if record exists) - will use later to insert record name into log table
		$participantRecordsComplete = $participantRecords = array();
		$sql = "select r.participant_id, r.record, if (r.first_submit_time is null, 0, if (r.completion_time is null, 1, 2)) as completed 
				from redcap_surveys s, redcap_surveys_emails e, redcap_surveys_emails_recipients er, 
				redcap_surveys_response r where s.project_id = ".$this->project_id." and s.survey_id = e.survey_id and e.email_id = er.email_id 
				and r.participant_id = er.participant_id";
		if (is_numeric($survey_id)) $sql .= " and s.survey_id = $survey_id";
		$q = db_query($sql);
		// Loop through all rows
		while ($row = db_fetch_assoc($q))
		{
			$participantRecordsComplete[$row['participant_id']] = $row['completed'];
			$participantRecords[$row['participant_id']] = label_decode($row['record']);
		}
		
		// Get invitation log info for table
		$rows = $invitationLog = $record_names = array();
		$sql = "select q.ssq_id, if (q.status is null, e.email_sent, if (q.status = 'SENT', q.time_sent, q.scheduled_time_to_send)) as send_time, 
				q.reminder_num, if ((q.reason_not_sent is null and q.status = 'SENT'), 1, if (q.reason_not_sent is null, 0, -1)) as was_sent, 
				p.participant_id, p.survey_id, p.event_id, p.hash, er.email_recip_id, p.participant_email, 
				p.participant_phone, p.participant_identifier, er.static_email, 
				q.status as scheduled_status, q.reason_not_sent
				from redcap_surveys s, redcap_surveys_emails e, redcap_surveys_participants p, redcap_surveys_emails_recipients er 
				left outer join redcap_surveys_scheduler_queue q on q.email_recip_id = er.email_recip_id 
				where s.project_id = ".$this->project_id." and s.survey_id = e.survey_id and e.email_id = er.email_id 
				and p.participant_id = er.participant_id and q.status != 'DELETED'";
		if (is_numeric($survey_id)) $sql .= " and s.survey_id = $survey_id";
		if (is_numeric($event_id))  $sql .= " and p.event_id = $event_id";
		$sql .= " order by if (q.status is null, e.email_sent, if (q.status = 'SENT', q.time_sent, q.scheduled_time_to_send)),
				p.participant_email, abs(p.participant_identifier), p.participant_identifier";
		$q = db_query($sql);
		// Loop through all rows and store values in array
		$rownum = 0;
		while ($row = db_fetch_assoc($q))
		{
			// Merge recipient emails
			if ($row['participant_email'] == "" && $row['static_email'] != "") {
				$row['participant_email'] = $row['static_email'];
			}
			// Add record name and completed status (if record exists)
			if (isset($participantRecords[$row['participant_id']])) {
				$row['record'] = $participantRecords[$row['participant_id']];
				$row['completed'] = $participantRecordsComplete[$row['participant_id']];
			} else {
				$row['record'] = "";
				$row['completed'] = "0";
			}
			// If has a record name, then add to array to obtain email/identifier/phone in next section
			if ($row['record'] != "") {
				$record_names[$rownum] = $row['record'];
			}
			if ($row['reminder_num'] == '') $row['reminder_num'] = 0;
			// Unset some values we don't need
			unset($row['static_email']);
			if (!$twilio_enabled) {
				unset($row['participant_phone']);
			}
			// Add this invitation to array
			$invitationLog[] = $row;
			// Increment counter
			$rownum++;
		}
		// Remove unneeded arrays
		unset($participantRecords, $participantRecordsComplete);
		
		// For existing records, get participant identifier and email (if don't have them - i.e. because this is a follow-up survey)
		if (!empty($record_names))
		{
			// Get emails/identifiers
			$recordsEmail = Survey::getResponsesEmailsIdentifiers($record_names);
			// Loop through those that are missing and add those to $invitationLog from $recordsEmail
			foreach ($record_names as $logkey=>$this_record)
			{
				if ($invitationLog[$logkey]['participant_email'] == "") {
					$invitationLog[$logkey]['participant_email'] = $recordsEmail[$this_record]['email'];
				}
				if ($invitationLog[$logkey]['participant_identifier'] == "") {
					$invitationLog[$logkey]['participant_identifier'] = $recordsEmail[$this_record]['identifier'];
				}
				if ($twilio_enabled && $invitationLog[$logkey]['participant_phone'] == "") {
					$invitationLog[$logkey]['participant_phone'] = $recordsEmail[$this_record]['phone'];
				}
			}
			unset($record_names, $recordsEmail);
		}
		
		## PERFORM MORE FILTERING
		// Now filter $invitationLog by filters defined
		if ($_GET['filterBeginTime'] != '') {
			$filterBeginTimeYmd = DateTimeRC::format_ts_to_ymd($_GET['filterBeginTime']);
		}
		if ($_GET['filterEndTime'] != '') {
			$filterEndTimeYmd = DateTimeRC::format_ts_to_ymd($_GET['filterEndTime']);
		}
		// Make sure begin time occurs *before* end time. If not, display error message to user.
		if (isset($filterBeginTimeYmd) && isset($filterEndTimeYmd) && $filterBeginTimeYmd > $filterEndTimeYmd) {
			$errorMsg = RCView::div(array('class'=>'yellow','style'=>'margin-bottom:10px;'), 
							RCView::b($lang['global_01'].$lang['colon']).' '.$lang['survey_402']
						);
		}
		
		// Loop through all invitations and remove those that should be filtered
		foreach ($invitationLog as $key=>$attr)
		{
			// Filter if sent/not sent
			if ($_GET['filterInviteType'] != '' && $attr['was_sent'] != $_GET['filterInviteType']) {
				unset($invitationLog[$key]); continue;
			}
			// Filter by response type
			if ($_GET['filterResponseType'] != '' && $attr['completed'] != $_GET['filterResponseType']) {
				unset($invitationLog[$key]); continue;
			}
			// Filter by begin time
			if (isset($filterBeginTimeYmd) && substr($attr['send_time'], 0, 16) < $filterBeginTimeYmd) {
				unset($invitationLog[$key]); continue;
			}
			// Filter by end time
			if (isset($filterEndTimeYmd) && substr($attr['send_time'], 0, 16) > $filterEndTimeYmd) {
				unset($invitationLog[$key]); continue;
			}
			// Filter by DAG (if current user is assigned to a DAG)
			if ($user_rights['group_id'] != '' && $attr['record'] != '' && !isset($dag_records[$attr['record']])) {
				unset($invitationLog[$key]); continue;
			}			
			// If have identifiers disabled AND not using survey email field AND is an existing record 
			// AND has an email AND does not have an identifier, then obscure the email address from the user (preserve anonymity).
			if (!$enable_participant_identifiers && $survey_email_participant_field == '' && $attr['record'] != ""
				&& $attr['participant_email'] != "" && $attr['participant_identifier'] == "") 
			{
				$invitationLog[$key]['participant_email'] = $lang['survey_499'];
			}if (!$enable_participant_identifiers && $survey_phone_participant_field == '' && $attr['record'] != ""
				&& $attr['participant_phone'] != "" && $attr['participant_identifier'] == "") 
			{
				$invitationLog[$key]['participant_phone'] = $lang['survey_903'];
			}
		}
		
		## BUILD THE DROP-DOWN FOR PAGING THE INVITATIONS
		// Get participant count
		$invite_count = count($invitationLog);		
		// Section the Participant List into multiple pages
		$num_per_page = 100;
		$limit_begin  = 0;
		if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum']) && $_GET['pagenum'] > 1) {
			$limit_begin = ($_GET['pagenum'] - 1) * $num_per_page;
		}
		## Build the paging drop-down for participant list
		$pageDropdown = "<select id='pageNumInviteLog' onchange='loadInvitationLog(this.value)' style='vertical-align:middle;font-size:11px;'>";
		//Calculate number of pages of for dropdown
		$num_pages = ceil($invite_count/$num_per_page);
		if ($num_pages == 0) {
			$pageDropdown .= "<option value=''>0</option>";
		} else {
			$pageDropdown .= "<option value='ALL'>-- {$lang['docs_44']} --</option>";
		}
		//Loop to create options for dropdown
		for ($i = 1; $i <= $num_pages; $i++) {
			$end_num   = $i * $num_per_page;
			$begin_num = $end_num - $num_per_page + 1;
			$value_num = $end_num - $num_per_page;
			if ($end_num > $invite_count) $end_num = $invite_count;
			$pageDropdown .= "<option value='$i' " . ($_GET['pagenum'] == $i ? "selected" : "") . ">$begin_num - $end_num</option>";
		}
		$pageDropdown .= "</select>";
		$pageDropdown  = "{$lang['survey_45']} $pageDropdown {$lang['survey_133']} $invite_count";
		
		// If viewing ALL invitations, then set $num_per_page to null to return all invitations
		if ($_GET['pagenum'] == 'ALL') $num_per_page = null;
		
		// Loop through all invitations for THIS PAGE and build table
		$rownum = 0;
		foreach (array_slice($invitationLog, $limit_begin, $num_per_page) as $row)
		{
			// If this is a reminder invitation and we're not displaying reminders, then skip this loop
			if ($_GET['filterReminders'] == '0' && $row['reminder_num'] > 0) continue;
			
			// Set color of timestamp (green if already sent, red if failed) and icon
			$tsColor = ($row['was_sent'] == '0') ? "gray" : ($row['was_sent'] == '1' ? "green" : "red");
			$tsIcon  = ($row['was_sent'] == '0') ? "clock_small.png" : ($row['was_sent'] == '1' ? "tick_small_circle.png" : "bullet_delete.png");
			$tsIconStyle = ($row['reminder_num'] == '0' ? 'margin-right:2px;' : '');
			
			// If this invitation is a reminder, then display bell icon
			$reminderIcon = ($row['reminder_num'] == '0' ? '' : 
								RCView::span(array('class'=>"remn", 'title'=>$lang['survey_754'] . " " . $row['reminder_num']), 
									"(" . RCView::img(array('src'=>"bell_small.png", 'class'=>'opacity75', 'style'=>'margin-right:-2px;')) .
									$row['reminder_num'] . ")"
								)
							);
			
			// If scheduled and not sent yet, display cross icon to delete the invitation
			$deleteEditInviteIcons = '';
			if ($row['was_sent'] == '0') {
				$deleteEditInviteIcons = RCView::a(array('href'=>'javascript:;','style'=>'margin-left:8px;','onclick'=>"editSurveyInviteTime({$row['email_recip_id']},{$row['reminder_num']})"),
											RCView::img(array('src'=>'pencil_small.png','class'=>'inviteLogDelIcon opacity50','title'=>$lang['survey_490']))
										 ) .
										 RCView::a(array('href'=>'javascript:;','style'=>'margin:0 2px 0 3px;','onclick'=>"deleteSurveyInvite({$row['email_recip_id']},{$row['reminder_num']})"),
											RCView::img(array('src'=>'cross_small2.png','class'=>'inviteLogDelIcon opacity50','title'=>$lang['survey_486']))
										 );
			}
			
			// Get the form name of this survey_id
			$form = $Proj->surveys[$row['survey_id']]['form_name'];
			
			// Send time (and icon)
			$rows[$rownum][] = 	// Invisible YMD timestamp (for sorting purposes
								RCView::span(array('class'=>'hidden'), $row['send_time']) .
								// Display time and icon
								RCView::span(array('style'=>"color:$tsColor;"), 
									RCView::img(array('src'=>$tsIcon, 'style'=>$tsIconStyle)) .
									DateTimeRC::format_ts_from_ymd($row['send_time']) .
									$deleteEditInviteIcons . 
									$reminderIcon
								);
								
			// View email
			$rows[$rownum][] = 	RCView::a(array('href'=>'javascript:;','onclick'=>"viewEmail('{$row['email_recip_id']}','{$row['ssq_id']}');"), 
									RCView::img(array('src'=>'mail_open_document.png','title'=>$lang['survey_902']))
								);
			
			// Email address
			if ($row['participant_email'] != "") {
				$rows[$rownum][] = RCView::div(array('class'=>'wrapemail'), $row['participant_email']);
			} else {
				$rows[$rownum][] = RCView::span(array('style'=>"color:#777;"), $lang['survey_284']);
			}
			
			// Phone number
			if ($twilio_enabled) {
				$rows[$rownum][] = RCView::div(array('class'=>'wrapemail'), formatPhone($row['participant_phone']));
			}
			
			// Participant Identifier
			$rows[$rownum][] = RCView::div(array('class'=>'wrapemail'), $row['participant_identifier']);
			
			// Survey title (and event)
			$rows[$rownum][] = 	RCView::div(array('style'=>"color:#800000;"), 
									// Survey title
									$Proj->surveys[$row['survey_id']]['title']
								) .
								RCView::div(array('style'=>"color:#777;"), 
									// Display event (if longitudinal)
									(!$longitudinal ? "" : $Proj->eventInfo[$row['event_id']]['name_ext'])
								);
			
			// Display "open survey" link (if not completed yet)
			if ($row['completed'] == "2") {
				$rows[$rownum][] = "-";
			} else {
				$rows[$rownum][] = 	RCView::a(array('target'=>'_blank','href'=>APP_PATH_SURVEY_FULL."index.php?s={$row['hash']}"),
										RCView::img(array('src'=>'link.png','style'=>'','title'=>$lang['survey_246']))
									);
			}
									
			## Response-completed status
			if ($row['completed'] == "1") {
				// Partial response
				$completedIcon = "circle_orange_tick.png";
			} elseif ($row['completed'] == "2") {
				// Completed response
				$completedIcon = "tick_circle_frame.png";
			} else {
				// Response doesn't exist yet (not started survey)
				$completedIcon = "stop_gray.png";
			}
			// If record exists and has an identifier, then make icon a link to the record
			if ($row['completed'] != "" && $row['participant_identifier'] != "") {
				$rows[$rownum][] = 	RCView::a(array('href'=>APP_PATH_WEBROOT."DataEntry/index.php?pid=".$this->project_id."&page=$form&event_id={$row['event_id']}&id={$row['record']}",'target'=>'_blank'), 
										RCView::img(array('src'=>$completedIcon,'title'=>$lang['survey_245'],'class'=>'viewresponse'))
									);
			} 
			// Display only icon with no link
			else {
				$rows[$rownum][] = RCView::img(array('src'=>$completedIcon,'class'=>'noviewresponse'));
			}
								
			// Reason not sent
			$rows[$rownum][] = ($row['reason_not_sent'] == "") ? $row['reason_not_sent'] : RCView::span(array('class'=>'wrap'), $row['reason_not_sent']);
			
			// Increment counter
			$rownum++;
		}
		
		// Give message if no invitations were sent
		if (empty($rows)) {
			$rows[$rownum] = array(RCView::div(array('class'=>'wrap','style'=>'color:#800000;'), $lang['survey_435']),"","","");
		}
		
		// Define table headers
		$headers = array();
		$headers[] = array(171, RCView::img(array('class'=>'survlogsendarrow', 'src'=>'draw-arrow-down.png', 'style'=>'vertical-align:middle;')) . 
								RCView::img(array('class'=>'survlogsendarrow', 'src'=>'draw-arrow-up.png', 'style'=>'display:none;vertical-align:middle;')) .
								RCView::SP . 
								$lang['survey_436'],
					 "left");
		$headers[] = array(28,  RCView::span(array('class'=>'wrap'), $lang['survey_901']), "center");
		if ($twilio_enabled) {			
			// Phone number
			$headers[] = array(108, RCView::span(array('class'=>'wrap'), $lang['survey_392']));
			$headers[] = array(95, RCView::span(array('class'=>'wrap'), $lang['survey_392']));
			$headers[] = array(95, RCView::span(array('class'=>'wrap'), $lang['survey_250']));
		} else {
			$headers[] = array(160, RCView::span(array('class'=>'wrap'), $lang['survey_392']));
			$headers[] = array(100, RCView::span(array('class'=>'wrap'), $lang['survey_250']));
		}
		$headers[] = array(195, $lang['survey_437']);
		$headers[] = array(34,  RCView::span(array('class'=>'wrap'), $lang['global_90']), "center");
		$headers[] = array(60,  $lang['survey_47'], "center");
		$headers[] = array(70, RCView::span(array('class'=>'wrap'), "Errors (if any)"));
		// Add checkbox as checked for "Display reminders?"
		$filterRemindersChecked = ($_GET['filterReminders'] == '1') ? "checked" : "";
		// Define title
		$title =	RCView::div(array('style'=>'font-family:arial;'),
						RCView::div(array('style'=>'padding:2px 20px 0 5px;float:left;font-size:14px;'),
							RCView::img(array('src'=>'mails_stack.png','class'=>'imgfix')) .
							$lang['survey_350'] . RCView::br() . 
							RCView::span(array('style'=>'line-height:24px;color:#666;font-size:11px;font-weight:normal;'), 
								$lang['survey_570']
							) . RCView::br() . RCView::br() . 
							RCView::span(array('style'=>'color:#555;font-size:11px;font-weight:normal;'), 							
								$pageDropdown
							)
						) .
						## QUICK BUTTONS
						RCView::div(array('style'=>'font-weight:normal;float:left;font-size:11px;padding-left:12px;border-left:1px solid #ccc;'),
							RCView::button(array($disableViewPastInvites=>$disableViewPastInvites, 'class'=>'jqbuttonsm', 'style'=>'margin-top:12px;font-size:11px;color:green;display:block;', 
								'onclick'=>"$('#filterBeginTime').val('');$('#filterEndTime').val('$now_user_date_military_time');loadInvitationLog(1)"), $lang['survey_571']) .
							RCView::button(array($disableViewFutureInvites=>$disableViewFutureInvites, 'class'=>'jqbuttonsm', 'style'=>'margin-top:12px;font-size:11px;color:#000066;display:block;', 
								'onclick'=>"$('#filterBeginTime').val('$now_user_date_military_time');$('#filterEndTime').val('');loadInvitationLog(1)"), $lang['survey_572'])
						) .
						## FILTERS
						RCView::div(array('style'=>'font-weight:normal;float:left;font-size:11px;padding-left:15px;margin-left:15px;border-left:1px solid #ccc;'),
							// Date/time range
							$lang['survey_439'] .
							RCView::text(array('id'=>'filterBeginTime','value'=>$_GET['filterBeginTime'],'class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-right:8px;margin-left:3px;width:92px;height:14px;line-height:14px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) . 
							$lang['survey_440'] .
							RCView::text(array('id'=>'filterEndTime','value'=>$_GET['filterEndTime'],'class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-left:3px;width:92px;height:14px;line-height:14px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) . 
							RCView::span(array('class'=>'df','style'=>'color:#777;'), '('.DateTimeRC::get_user_format_label().' H:M)') . RCView::br() .
							// Display invitations types and responses status types
							$lang['survey_441'] .
							RCView::select(array('id'=>'filterInviteType','style'=>'margin-left:3px;font-size:11px;'), 
								array(''=>$lang['survey_443'], '1'=>$lang['survey_444'], '0'=>$lang['survey_445'], '-1'=>$lang['survey_479']),$_GET['filterInviteType']) .
							" {$lang['global_43']} " .
							RCView::select(array('id'=>'filterResponseType','style'=>'font-size:11px;'), 
								array(''=>$lang['survey_446'], '0'=>$lang['survey_447'], '1'=>$lang['survey_448'], '2'=>$lang['survey_449']),$_GET['filterResponseType']) .
							RCView::br() . 
							// Display specific surveys
							$lang['survey_441'] .
							RCView::select(array('id'=>'filterSurveyEvent','style'=>'margin-left:3px;font-size:11px;'), 
								array_merge(array('0-0'=>$lang['survey_450']), self::getInvitationLogSurveyList()),$_GET['filterSurveyEvent'],300) .
							RCView::br() .
							// Display invitation reminders
							RCView::checkbox(array('id'=>'filterReminders', 'class'=>'imgfix2', 'style'=>'', $filterRemindersChecked=>$filterRemindersChecked)) .
							$lang['survey_740'] .
							RCView::br() . 
							// "Apply filters" button
							RCView::button(array('class'=>'jqbuttonsm','style'=>'margin-top:5px;font-size:11px;color:#800000;','onclick'=>"loadInvitationLog(1)"), $lang['survey_442']) .
							RCView::a(array('href'=>PAGE_FULL."?pid=".$this->project_id."&email_log=1",'style'=>'margin-left:15px;text-decoration:underline;font-weight:normal;font-size:10px;'), $lang['setup_53'])
						) .
						RCView::div(array('class'=>'clear'), '')
					);
		// Build Invitation Log table
		return $errorMsg . renderGrid("email_log_table", $title, 915+($twilio_enabled ? 50 : 0), 'auto', $headers, $rows, true, true, false);
	}
	
	
	// Return true if a record's survey invitation is already scheduled for a given survey/event
	public static function checkIfRecordScheduled($survey_id, $event_id, $record)
	{
		$sql = "select 1 from redcap_surveys_scheduler s, redcap_surveys_scheduler_queue q 
				where s.ss_id = q.ss_id and s.survey_id = $survey_id and s.event_id = $event_id 
				and q.record = '" . prep($record) . "' limit 1";
		$q = db_query($sql);
		// Return true if has been scheduled
		return (db_num_rows($q) > 0);
	}
	
	
	// Return true if a record's Form Status value for a given survey/event is Complete (=2)
	public static function isFormStatusCompleted($survey_id, $event_id, $record)
	{
		// Query data table for value of 2
		$sql = "select 1 from redcap_data d, redcap_surveys s where d.project_id = s.project_id 
				and d.event_id = $event_id and d.record = '" . prep($record) . "' and s.survey_id = $survey_id
				and d.field_name = concat(s.form_name, '_complete') and d.value = '2' limit 1";
		$q = db_query($sql);
		// Return true if has been scheduled
		return (db_num_rows($q) > 0);
	}
	
	
	// Determine if this record needs to have a survey invitation scheduled
	public function checkConditionsOfRecordToSchedule($survey_id, $event_id, $record)
	{
		// Fill up $schedules array with schedules
		$this->setSchedules();
		// Check the schedule's attributes
		if (!isset($this->schedules[$survey_id][$event_id])) return false;
		$thisSchedule = $this->schedules[$survey_id][$event_id];
		// If conditional upon survey completion, check if completed survey
		$conditionsPassedSurveyComplete = ($thisSchedule['condition_andor'] == 'AND'); // Initial true value if using AND (false if using OR)
		if (is_numeric($thisSchedule['condition_surveycomplete_survey_id']) && is_numeric($thisSchedule['condition_surveycomplete_event_id']))
		{
			// Is it a completed response?
			$conditionsPassedSurveyComplete = isResponseCompleted($thisSchedule['condition_surveycomplete_survey_id'], $record, $thisSchedule['condition_surveycomplete_event_id']);
			// If not listed as a completed response, then also check Form Status (if entered as plain record data instead of as response), just in case
			if (!$conditionsPassedSurveyComplete) {
				$conditionsPassedSurveyComplete = self::isFormStatusCompleted($thisSchedule['condition_surveycomplete_survey_id'], $thisSchedule['condition_surveycomplete_event_id'], $record);
			}
		}
		// If conditional upon custom logic
		$conditionsPassedLogic = ($thisSchedule['condition_andor'] == 'AND'); // Initial true value if using AND (false if using OR)
		if ($thisSchedule['condition_logic'] != '' 
			// If using AND and $conditionsPassedSurveyComplete is false, then no need to waste time checking evaluateLogicSingleRecord().
			// If using OR and $conditionsPassedSurveyComplete is true, then no need to waste time checking evaluateLogicSingleRecord().
			&& (($thisSchedule['condition_andor'] == 'OR' && !$conditionsPassedSurveyComplete) 
				|| ($thisSchedule['condition_andor'] == 'AND' && $conditionsPassedSurveyComplete)))
		{
			// Does the logic evaluate as true?
			$conditionsPassedLogic = LogicTester::evaluateLogicSingleRecord($thisSchedule['condition_logic'], $record);
		}
		// Check pass/fail values and return boolean if record is ready to have its invitation for this survey/event
		if ($thisSchedule['condition_andor'] == 'OR') {
			// OR
			return ($conditionsPassedSurveyComplete || $conditionsPassedLogic);
		} else {
			// AND (default)
			return ($conditionsPassedSurveyComplete && $conditionsPassedLogic);
		}
	}
	
	
	// Get all schedules where record has already had invitations scheduled. Return array with survey_id and event_id as key/subkey
	public function getAlreadyScheduledForRecord($record)
	{
		// Initial empty array
		$alreadyScheduled = array();
		// Query surveys_scheduler_queue table to find any previous invitations already scheduled
		$sql = "select ss_id from redcap_surveys_scheduler_queue where record = '" . prep($record) . "' 
				and ss_id in (" . implode(",", array_keys($this->schedulePkLink)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			foreach ($this->schedulePkLink[$row['ss_id']] as $survey_id=>$event_id) {
				$alreadyScheduled[$survey_id][$event_id] = true;
			}
		}
		// Return array
		return $alreadyScheduled;	
	}
	
	
	// Check if we're ready to schedule the participant's survey invitation to be sent. Return boolean regarding if was scheduled.
	public function checkToScheduleParticipantInvitation($record)
	{
		// Call survey_functions
		require_once APP_PATH_DOCROOT . "Surveys/survey_functions.php";	
		
		// Set initial return value as 0
		$numInvitationsScheduled = 0;
		
		// Fill up $schedules array with schedules
		$this->setSchedules();
		
		// Find available schedules by removing all irrelevant ones 
		// (e.g., exact time schedules, any that are already scheduled, schedules dependent upon other available schedules)
		$availableSchedules = $this->getAvailableSchedulesForRecord($record);
		
		// Loop through all relevant schedules
		foreach ($availableSchedules as $survey_id=>$events) 
		{
			foreach (array_keys($events) as $event_id) 
			{
				// Determine if this record needs to have a survey invitation scheduled
				$readyToSchedule = $this->checkConditionsOfRecordToSchedule($survey_id, $event_id, $record);
				if ($readyToSchedule) {
					// Schedule the participant's survey invitation to be sent by adding it to the scheduler_queue table
					$invitationWasScheduled = $this->scheduleParticipantInvitation($survey_id, $event_id, $record);		
					if ($invitationWasScheduled) {
						// Increment number of invitations scheduled just now
						$numInvitationsScheduled++;
					}
				}
			}
		}
		
		// Return count of invitation scheduled, if any
		return $numInvitationsScheduled;
	}
	

	// Return all surveys/events where survey has been completed for this record (so we can exclude them)
	private function getAlreadyCompletedForRecord($record)
	{
		// Put completed surveys/events in array
		$alreadyCompleted = array();
		// Get list of all available schedules for this record
		$availableSchedules = $this->schedules;
		// Query response table
		if (!empty($availableSchedules)) {
			$sql = "select p.survey_id, p.event_id, r.completion_time from redcap_surveys_participants p, redcap_surveys_response r
					where r.participant_id = p.participant_id and p.survey_id in (".prep_implode(array_keys($availableSchedules)).")
					and r.record = '" . prep($record) . "' and r.completion_time is not null";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				if (isset($availableSchedules[$row['survey_id']][$row['event_id']])) {
					$alreadyCompleted[$row['survey_id']][$row['event_id']] = $row['completion_time'];
				}
			}
		}
		// Return array
		return $alreadyCompleted;
	}
	

	// Find available schedules by removing all irrelevant ones 
	// (e.g., exact time schedules, any that are already scheduled, schedules dependent upon other available schedules)
	private function getAvailableSchedulesForRecord($record)
	{
		// Get all schedules where record has already had invitations scheduled
		$alreadyScheduled = $this->getAlreadyScheduledForRecord($record);
		
		// Get all surveys/events where survey has been completed for this record (so we can exclude them)
		$alreadyCompleted = $this->getAlreadyCompletedForRecord($record);
		
		// First, get only Conditional schedules to put in array AND remove all schedules already scheduled for this record
		$availableSchedules = $this->schedules;
		foreach ($availableSchedules as $survey_id=>$events) {
			foreach ($events as $event_id=>$attr) {
				// Ignore if invitations have already been scheduled for this survey/event
				// OR if survey has already been completed
				// OR if the schedule is set as Inactive.
				if (isset($alreadyCompleted[$survey_id][$event_id]) || isset($alreadyScheduled[$survey_id][$event_id]) || !$attr['active']) 
				{
					unset($availableSchedules[$survey_id][$event_id]);
					// If survey_id sub-array is not empty them remove it
					if (empty($availableSchedules[$survey_id])) unset($availableSchedules[$survey_id]);
				}
				// If it's dependent upon another survey being completed, then check if participant has completed it. If so, then we can remove it
				elseif (is_numeric($attr['condition_surveycomplete_survey_id']) && is_numeric($attr['condition_surveycomplete_event_id'])
					// Check if they've completed this survey
					&& (isset($alreadyCompleted[$attr['condition_surveycomplete_survey_id']][$attr['condition_surveycomplete_event_id']]) 
						|| isResponseCompleted($attr['condition_surveycomplete_survey_id'], $record, $attr['condition_surveycomplete_event_id'])))
				{
					unset($availableSchedules[$attr['condition_surveycomplete_survey_id']][$attr['condition_surveycomplete_event_id']]);
					// If survey_id sub-array is now empty them remove it
					if (empty($availableSchedules[$attr['condition_surveycomplete_survey_id']])) unset($availableSchedules[$attr['condition_surveycomplete_survey_id']]);
				}
			}
		}
		
		// Now remove all schedules that are dependent upon other completed surveys in this schedule 
		// and put them in dependentAvailableSchedules array (e.g., if Week 2 requires that Week 1 be finished first).
		$dependentAvailableSchedules = array();
		do {
			// Initial value
			$removedSchedule = false;
			// Loop through all available schedules and remove those that are dependent upon other available ones (cascading issue)
			foreach ($availableSchedules as $survey_id=>$events) {
				foreach ($events as $event_id=>$attr) {
					// If schedule is dependent upon an available schedule OR is dependent upon another dependent schedule, then remove
					if (isset($availableSchedules[$attr['condition_surveycomplete_survey_id']][$attr['condition_surveycomplete_event_id']])
						|| isset($dependentAvailableSchedules[$attr['condition_surveycomplete_survey_id']][$attr['condition_surveycomplete_event_id']])) 
					{
						// Set flag so that we'll know to loop over this whole survey again
						$removedSchedule = true;
						// Remove schedule from array
						unset($availableSchedules[$survey_id][$event_id]);
						// Add schedule that was removed to the dependentAvailableSchedules array
						$dependentAvailableSchedules[$survey_id][$event_id] = $attr;
					}
				}
				// If survey_id sub-array is now empty them remove it
				if (empty($availableSchedules[$survey_id])) unset($availableSchedules[$survey_id]);
			}
		} while ($removedSchedule);
		
		// Return array of available schedules
		return $availableSchedules;
	}
	
	
	// Calculate the date/time when the survey invitation should be send to this participant
	private function calculateParticipantInvitationTime($survey_id, $event_id)
	{
		// Get this schedule's attributes
		$attr = $this->schedules[$survey_id][$event_id];
		
		// SEND AT EXACT TIME 
		if ($attr['condition_send_time_option'] == 'EXACT_TIME') 
		{
			// Set invitation time as the "exact date/time" specified
			$invitationTime = $attr['condition_send_time_exact'];
		}
		
		// IMMEDIATELY SEND
		elseif ($attr['condition_send_time_option'] == 'IMMEDIATELY')
		{
			// Set invitation time as current time right now
			$invitationTime = NOW;
		}
		
		// SEND AFTER SPECIFIED LAPSE OF TIME
		elseif ($attr['condition_send_time_option'] == 'TIME_LAG')
		{
			// Get temporal components
			$days = $attr['condition_send_time_lag_days'];
			$hours = $attr['condition_send_time_lag_hours'];
			$minutes = $attr['condition_send_time_lag_minutes'];
			// Calculate invitation time by adding time lag to current time
			$invitationTime = date("Y-m-d H:i:s", mktime(date("H")+$hours,date("i")+$minutes,date("s"),date("m"),date("d")+$days,date("Y")));
		}
		
		// SEND ON NEXT SPECIFIED DAY/TIME
		elseif ($attr['condition_send_time_option'] == 'NEXT_OCCURRENCE')
		{
			// Set time component of the timestamp
			$timeTS = $attr['condition_send_next_time'];
			// Set the date component of the timestamp
			// If day type is "WEEKEND DAY"
			if ($attr['condition_send_next_day_type'] == 'WEEKENDDAY') {
				// If today is Saturday, then next weekend day = next Sunday (i.e. tomorrow)
				if (date('D') == 'Sat') {
					$dateTS = date('Y-m-d', strtotime('NEXT SUNDAY'));
				}
				// If today is any day other than Saturday, then next weekend day is next Saturday
				else {
					$dateTS = date('Y-m-d', strtotime('NEXT SATURDAY'));
				}
			} 
			// Any other day type (can use strtotime to parse into date)
			else {
				$dateTS = date('Y-m-d', strtotime('NEXT '.$attr['condition_send_next_day_type']));
			}
			// Combine date and time components
			$invitationTime = "$dateTS $timeTS";
		}
		
		// Validate the date/time with regex (in case components are missing or are calculated incorrectly)
		$datetime_regex = '/^(\d{4})([-\/.])?(0[1-9]|1[012])\2?(0[1-9]|[12][0-9]|3[01])\s([0-9]|[0-1][0-9]|[2][0-3]):([0-5][0-9]):([0-5][0-9])$/';
		if (!preg_match($datetime_regex, $invitationTime)) $invitationTime = false;
		
		// Return invitation date/time
		return $invitationTime;
	}
	
	
	// SURVEY REMINDERS: Calculate the date/time when the survey reminder should be send to this participant
	// $baseInvitationTime represents the time in which this reminder is being added to (e.g. 4 hours after $baseInvitationTime)
	public static function calculateReminderTime($attr, $baseInvitationTime)
	{
		// Set strtotime version of base invite time
		$strtotimeBaseInvitationTime = strtotime($baseInvitationTime);
		
		// SEND AT EXACT TIME 
		if ($attr['reminder_type'] == 'EXACT_TIME') 
		{
			// Convert user's formatted datetime to YMD timestamp
			list ($this_date, $this_time) = explode(" ", $attr['reminder_exact_time']);
			// Set invitation time as the "exact date/time" specified
			$invitationTime = trim(DateTimeRC::format_ts_to_ymd($this_date) . " $this_time:00");
		}
		
		// SEND AFTER SPECIFIED LAPSE OF TIME
		elseif ($attr['reminder_type'] == 'TIME_LAG')
		{
			// Get temporal components
			$days = (!is_numeric($attr['reminder_timelag_days']) ? 0 : $attr['reminder_timelag_days']);
			$hours = (!is_numeric($attr['reminder_timelag_hours']) ? 0 : $attr['reminder_timelag_hours']);
			$minutes = (!is_numeric($attr['reminder_timelag_minutes']) ? 0 : $attr['reminder_timelag_minutes']);
			// Calculate invitation time by adding time lag to current time
			$invitationTime = 	date("Y-m-d H:i:s", mktime(
									date("H", $strtotimeBaseInvitationTime)+$hours,
									date("i", $strtotimeBaseInvitationTime)+$minutes,
									date("s", $strtotimeBaseInvitationTime),
									date("m", $strtotimeBaseInvitationTime),
									date("d", $strtotimeBaseInvitationTime)+$days,
									date("Y", $strtotimeBaseInvitationTime)
								));
		}
		
		// SEND ON NEXT SPECIFIED DAY/TIME
		elseif ($attr['reminder_type'] == 'NEXT_OCCURRENCE')
		{
			// Set time component of the timestamp
			$timeTS = $attr['reminder_nexttime'].":00";
			$timeTS = substr($timeTS, 0, 8);
			// Set the date component of the timestamp
			// If day type is "WEEKEND DAY"
			if ($attr['reminder_nextday_type'] == 'WEEKENDDAY') {
				// If today is Saturday, then next weekend day = next Sunday (i.e. tomorrow)
				if (date('D', $strtotimeBaseInvitationTime) == 'Sat') {
					$dateTS = date('Y-m-d', strtotime('NEXT SUNDAY', $strtotimeBaseInvitationTime));
				}
				// If today is any day other than Saturday, then next weekend day is next Saturday
				else {
					$dateTS = date('Y-m-d', strtotime('NEXT SATURDAY', $strtotimeBaseInvitationTime));
				}
			} 
			// Any other day type (can use strtotime to parse into date)
			else {
				$dateTS = date('Y-m-d', strtotime('NEXT '.$attr['reminder_nextday_type'], $strtotimeBaseInvitationTime));
			}
			// Combine date and time components
			$invitationTime = "$dateTS $timeTS";
		}
		
		// Validate the date/time with regex (in case components are missing or are calculated incorrectly)
		$datetime_regex = '/^(\d{4})([-\/.])?(0[1-9]|1[012])\2?(0[1-9]|[12][0-9]|3[01])\s([0-9]|[0-1][0-9]|[2][0-3]):([0-5][0-9]):([0-5][0-9])$/';
		if (!preg_match($datetime_regex, $invitationTime)) $invitationTime = false;
		
		// Return invitation date/time
		return $invitationTime;
	}
	
	
	// Schedule the participant's survey invitation to be sent by adding it to the scheduler_queue table. Return boolean
	public function scheduleParticipantInvitation($survey_id, $event_id, $record)
	{
		global $Proj;
		
		// Fill up $schedules array with schedules
		$this->setSchedules();
		
		// Get the schedule for this survey/event
		$thisSchedule = $this->schedules[$survey_id][$event_id];
		
		// Capture all queries in array for logging purposes
		$sql_all = array();
	
		// First, make sure that there is a placeholder set in the participants table for this record-survey-event
		list ($participant_id, $hash) = Survey::getFollowupSurveyParticipantIdHash($survey_id, $record, $event_id);
		
		// Calculate the date/time when the survey invitation should be senT to this participant
		$invitationTime = $this->calculateParticipantInvitationTime($survey_id, $event_id);
		if ($invitationTime === false) return false;
		
		## PIPING: Obtain email subject/content to see if piping should be performed
		$sql = "select email_subject, email_content, email_sender, delivery_type from redcap_surveys_scheduler
				where survey_id = $survey_id and event_id = $event_id";
		$q = db_query($sql);
		$row = db_fetch_assoc($q);		
		// Perform filtering on email subject/content
		$row['email_sender']  = label_decode($row['email_sender']);
		$row['email_content'] = filter_tags(label_decode($row['email_content']));
		$row['email_subject'] = filter_tags(label_decode($row['email_subject']));
		// If delivery type if partipant's preference, then determine their preference and set it
		if ($row['delivery_type'] == 'PARTICIPANT_PREF') {
			// Get delivery method
			$participantAttributes = Survey::getResponsesEmailsIdentifiers(array($record));
			$row['delivery_type'] = isset($participantAttributes[$record]) ? $participantAttributes[$record]['delivery_preference'] : 'EMAIL';
		}
		// Set flags
		$doPiping = $doPipingContent = $doPipingSubject = false;
		$piping_fields = array();
		// EMAIL CONTENT PIPING
		if (strpos($row['email_content'], '[') !== false && strpos($row['email_content'], ']') !== false) {
			// Parse the label to pull out the field names
			$piping_fields_content = array_keys(getBracketedFields($row['email_content'], true, true, true));
			// Validate the field names
			foreach ($piping_fields_content as $key=>$this_field) {
				// If not a valid field name, then remove
				if (!isset($Proj->metadata[$this_field])) unset($piping_fields_content[$key]);
			}
			// Set flag to true if some fields were indeed piped
			if (!empty($piping_fields_content)) $doPiping = $doPipingContent = true;
		}
		// EMAIL SUBJECT PIPING
		if (strpos($row['email_subject'], '[') !== false && strpos($row['email_subject'], ']') !== false) {
			// Parse the label to pull out the field names
			$piping_fields_subject = array_keys(getBracketedFields($row['email_subject'], true, true, true));
			// Validate the field names
			foreach ($piping_fields_subject as $key=>$this_field) {
				// If not a valid field name, then remove
				if (!isset($Proj->metadata[$this_field])) unset($piping_fields_subject[$key]);
			}
			// Set flag to true if some fields were indeed piped
			if (!empty($piping_fields_subject)) $doPiping = $doPipingSubject = true;
		}
		// Set subject
		$row['email_subject'] = ($doPipingSubject) ? strip_tags(Piping::replaceVariablesInLabel($row['email_subject'], $record, $event_id, array(), true, $this->project_id)) : $row['email_subject'];
		// Set content
		$row['email_content'] = ($doPipingContent) ? Piping::replaceVariablesInLabel($row['email_content'], $record, $event_id, array(), true, $this->project_id, false) : $row['email_content'];
		
		## REMINDERS
		$participantSendTimes = array(0=>$invitationTime);
		## If reminders are enabled, then add times of all reminders in array
		$addReminders = ($thisSchedule['reminder_type'] != '');
		if ($addReminders) {
			// Loop through each reminder
			$thisReminderTime = $invitationTime;
			for ($k = 1; $k <= $thisSchedule['reminder_num']; $k++) {
				// Get reminder time for next reminder
				$participantSendTimes[$k] = $thisReminderTime = SurveyScheduler::calculateReminderTime($thisSchedule, $thisReminderTime);
			}
		}
		
		## SCHEDULE THE INVITATIONS AND REMINDERS	
		// Keep track of any errors
		$insertErrors = 0;
		// Add to surveys_emails table
		$sql_all[] = $sql = "insert into redcap_surveys_emails (survey_id, email_subject, email_content, email_static) values
							($survey_id, '".prep($row['email_subject'])."', '".prep($row['email_content'])."', '".prep($row['email_sender'])."')";
		if (db_query($sql)) 
		{
			// Get email_id
			$email_id = db_insert_id();
			// Now add to surveys_emails_recipients table
			$sql_all[] = $sql = "insert into redcap_surveys_emails_recipients (email_id, participant_id, delivery_type) 
								 values ($email_id, $participant_id, '".prep($row['delivery_type'])."')";
			if (db_query($sql)) 
			{
				// Get email_recip_id
				$email_recip_id = db_insert_id();
				// Now add to scheduler_queue table (loop through orig invite + any reminder invites)
				foreach ($participantSendTimes as $reminder_num=>$thisSendTime) {
					$sql_all[] = $sql = "insert into redcap_surveys_scheduler_queue (ss_id, email_recip_id, record, scheduled_time_to_send, reminder_num) 
										(select ss_id, '$email_recip_id', '" . prep($record) . "', '$thisSendTime', '".prep($reminder_num)."' 
										from redcap_surveys_scheduler where survey_id = $survey_id and event_id = $event_id)
										on duplicate key update scheduled_time_to_send = '$thisSendTime'";
					if (!db_query($sql)) $insertErrors++;
					// Get ssq_id from insert
					$ssq_id = db_insert_id();			
					// If need to send the invite right now, then send it here NOW (except for Data Import Tool and API data import, which use transactions)
					$pagesExcludeSendNow = array("DataImport/index.php", "api/index.php", "API/index.php");
					if ($thisSendTime == NOW && !in_array(PAGE, $pagesExcludeSendNow)) {
						self::emailInvitations($ssq_id);
					}
				}
			}
		}
		
		// Log this event, if scheduled
		if ($insertErrors == 0) 
		{
			log_event(implode(";\n", $sql_all),"redcap_surveys_scheduler_queue","MANAGE",$record,
				"survey_id = $survey_id,\nevent_id = $event_id,\nrecord = '$record',\nssq_id = $ssq_id,\nemail_recip_id = $email_recip_id",
				"Automatically schedule survey invitation", "", "SYSTEM", $this->project_id);
		}
		
		// Return true if was scheduled successfully
		return ($insertErrors == 0);
	}
	
	// Replace literal values into logic for a single record for a single rule
	static private function formatRuleLogicLiteral($logic, $record_data)
	{
		global $Proj, $longitudinal;
		
		// Get unique event names (with event_id as key)
		$events = $Proj->getUniqueEventNames();	
		
		// Loop through the data and replace the variable with its literal value
		foreach ($record_data as $event_id=>$field_name_value)
		{
			// Get unique event name for this event_id
			$unique_event_name = $events[$event_id];				
			// Replace "][" with "[]" so that event+field syntax gets replaced correctly because the normal field replacement was messing it up
			$logic = str_replace("][", "[]", $logic);
			// Now loop through each field for this record-event
			foreach ($field_name_value as $field_name=>$value)
			{
				// Replace variable with value
				if (is_array($value)) {
					// Replace checkbox logic
					foreach ($value as $chkbox_choice=>$chkbox_val) 
					{
						// If also longitudinal, then try replacing the unique event name + field with the value
						if ($longitudinal) {
							$logic = str_replace("[{$unique_event_name}[]$field_name($chkbox_choice)]", " '$chkbox_val' ", $logic);
						}
						// Replace field name
						$logic = str_replace("[$field_name($chkbox_choice)]", " '$chkbox_val' ", $logic);
					}
				} else {
					// Determine if field is a numerical value and not a string so we can know to surround it with apostrophes
					$fieldType = $Proj->metadata[$field_name]['element_type'];
					$valType   = $Proj->metadata[$field_name]['element_validation_type'];
					$isNumericField = ($fieldType == 'calc' || $fieldType == 'slider' || ($fieldType == 'text' && ($valType == 'int' || $valType == 'float')));
					$quote = ($isNumericField && is_numeric($value)) ? "" : "'"; // Doubly ensure that value is numeric to prevent crashing
					// Escape any apostrophes in the value since we around enclosing the value with apostrophes
					$value = cleanHtml($value);
					// If also longitudinal, then try replacing the unique event name + field with the value
					if ($longitudinal) {
						$logic = str_replace("[{$unique_event_name}[]$field_name]", " {$quote}{$value}{$quote} ", $logic);
					}
					// Replace field name
					$logic = str_replace("[$field_name]", " {$quote}{$value}{$quote} ", $logic);
				}
			}
			// Undo the replacement made above
			$logic = str_replace("[]", "][", $logic);
		}
		
		// In case there are some fields still left in the logic in square brackets (because they have no data),
		// then return false so that we can ignore this record-event for this rule.
		$fieldsNoValue = array_keys(getBracketedFields($logic, true, true, true));
		if (!empty($fieldsNoValue))
		{
			return false;
		}
		
		// Return the literal logic back
		return $logic;
	}	
	
	// MAILER: Send one batch of invitations (limit based on determineEmailsPerBatch())
	// or send single invitation if ssq_id is provided.
	// Ignore all inactive, archived, and "deleted" projects.
	public static function emailInvitations($ssq_id=null)
	{
		global $lang, $twilio_enabled_global, $enable_url_shortener;
		// Init Twilio (in case SMS or Voice Calls are used)
		if ($twilio_enabled_global) TwilioRC::init();
		// First, get ssq_id of all records for this batch to have invitations scheduled
		$ssq_ids = array();
		// Collect languages for each project in case one is not in English so we can display email text in that language
		$language_by_ssq_id = array();
		// Count of emailsl sent successfully and failed
		$emailCountSuccess = 0;
		$emailCountFail    = 0;
		// Add ssq_id's to arrays while looping
		$ssq_ids_emails = $ssq_ids_voice_sms = $static_emails = $static_phones = array();
		// If single ssq_id is provided, then limit query only to this one
		$sqlsub   = (is_numeric($ssq_id)) ? "and ssq_id = $ssq_id" : "";
		$sqllimit = (is_numeric($ssq_id)) ? "1" : self::determineEmailsPerBatch();
		$sql = "select q.ssq_id, a.project_language, r.static_email, r.static_phone 
				from redcap_surveys_scheduler_queue q, redcap_surveys_emails_recipients r, 
				redcap_surveys_participants p, redcap_surveys s, redcap_projects a 
				where q.time_sent is null and q.scheduled_time_to_send <= '" . NOW . "' and q.status = 'QUEUED' 
				and q.email_recip_id = r.email_recip_id and a.status <= 1 and p.survey_id = s.survey_id
				and r.participant_id = p.participant_id and s.project_id = a.project_id $sqlsub
				and a.date_deleted is null order by q.scheduled_time_to_send, q.ssq_id limit $sqllimit";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			## Get all ssq_id's and put in array
			while ($row = db_fetch_assoc($q)) {
				// Add ssq_id's to array
				$ssq_ids[] = $row['ssq_id'];
				$language_by_ssq_id[$row['ssq_id']] = $row['project_language'];
				// Add phone number to arrays, if applicable
				if ($row['static_phone'] != '') {
					// SMS or VOICE number
					$ssq_ids_voice_sms[$row['ssq_id']] = $static_phones[$row['ssq_id']] = $row['static_phone'];
				}
				// Add email address to arrays, if applicable
				if ($row['static_email'] != '') {
					// EMAIL address
					$ssq_ids_emails[$row['ssq_id']] = $static_emails[$row['ssq_id']] = label_decode($row['static_email']);
				}
			}
			
			## Set all those ssq_id's status as SENDING
			$sql = "update redcap_surveys_scheduler_queue set status = 'SENDING' 
					where ssq_id in (" . prep_implode($ssq_ids) . ")";
			$q = db_query($sql);

			## GET EMAIL ADDRESSES (if we didn't already get them from the manual static values in emails_recipients table)
			// First, get any emails connected to a Participant List for all the records involved here.
			// The first part of union query gets emails for initial surveys (i.e. record is null, doesn't exist yet),
			// while second part of query gets emails of followup surveys (i.e. existing records).
			$sql = "(select q.ssq_id, p.participant_email, p.participant_phone 
					from redcap_surveys_scheduler_queue q, redcap_surveys_emails_recipients e, redcap_surveys_participants p
					where q.record is null and q.email_recip_id = e.email_recip_id and p.participant_id = e.participant_id 
					and p.participant_email is not null 
					and q.ssq_id in (" . prep_implode($ssq_ids) . "))
					union
					(select q.ssq_id, p2.participant_email, p2.participant_phone
					from redcap_surveys_scheduler_queue q, redcap_surveys_emails_recipients e, redcap_surveys_participants p, 
					redcap_surveys s, redcap_surveys s2, redcap_surveys_participants p2, redcap_surveys_response r
					where q.record is not null and q.email_recip_id = e.email_recip_id and p.participant_email is not null
					and p.participant_id = e.participant_id and p.survey_id = s.survey_id and s.project_id = s2.project_id 
					and s2.survey_id = p2.survey_id and p2.participant_id = r.participant_id and p2.participant_email is not null
					and r.record = q.record and q.ssq_id in (" . prep_implode($ssq_ids) . "))";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				if ($row['participant_phone'] != '' && !isset($static_phones[$row['ssq_id']])) {
					// SMS or VOICE delivery
					$ssq_ids_voice_sms[$row['ssq_id']] = $row['participant_phone'];
				}
				if ($row['participant_email'] != '' && !isset($static_emails[$row['ssq_id']])) {
					// EMAIL delivery
					$ssq_ids_emails[$row['ssq_id']] = label_decode($row['participant_email']);
				}
			}
			// Secondly, if we are missing any emails that are NOT in a Participant List but project
			// uses special "participant email" data field, get value for that in redcap_data table.
			$sql = "select q.ssq_id, d.value, if (d.field_name = p2.survey_email_participant_field, 'email', 'phone') as value_type
					from redcap_surveys_scheduler_queue q, redcap_surveys_emails_recipients r, 
					redcap_surveys_emails e, redcap_surveys_participants p, redcap_projects p2, redcap_surveys s, 
					redcap_data d where q.email_recip_id = r.email_recip_id and e.email_id = r.email_id 
					and p.participant_id = r.participant_id and s.survey_id = e.survey_id 
					and s.project_id = p2.project_id and p2.project_id = d.project_id and d.record = q.record 
					and ((d.field_name = p2.survey_email_participant_field and p2.survey_email_participant_field is not null 
					and p2.survey_email_participant_field != '') or (d.field_name = p2.survey_phone_participant_field 
					and p2.survey_phone_participant_field is not null and p2.survey_phone_participant_field != ''))
					and q.ssq_id in (" . prep_implode($ssq_ids) . ")";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				if ($row['value'] == '') continue;
				if ($row['value_type'] == 'phone' && !isset($static_phones[$row['ssq_id']])) {
					// SMS or VOICE number
					$ssq_ids_voice_sms[$row['ssq_id']] = $row['value'];
				} elseif ($row['value_type'] == 'email' && !isset($static_emails[$row['ssq_id']])) {
					// EMAIL address
					$ssq_ids_emails[$row['ssq_id']] = label_decode($row['value']);
				}
			}
			
			
			## SEND EMAILS, VOICE CALLS, OR SMS TEXT MESSAGES
			// Initialize email
			$email = new Message();
			// Initialize counter of number of emails sent
			$numEmailsSent = 0;
			// Now loop though all ssq_id's and send email for each
			$sql = "select q.ssq_id, q.reminder_num, e.email_id, e.email_subject, e.email_content, 
					if (e.email_static is null, (select if (e.email_account=1, u.user_email, if (e.email_account=2, u.user_email2, u.user_email3)) 
						from redcap_user_information u where u.ui_id = e.email_sender), e.email_static) as email_static,
					p.hash, p.participant_id, s.title, s.survey_id, r.delivery_type,
					pr.twilio_enabled, pr.twilio_account_sid, pr.twilio_auth_token, pr.twilio_from_number
					from redcap_surveys_scheduler_queue q, redcap_surveys_emails_recipients r, redcap_surveys_emails e, 
					redcap_surveys_participants p, redcap_surveys s, redcap_projects pr where q.email_recip_id = r.email_recip_id 
					and e.email_id = r.email_id and p.participant_id = r.participant_id and s.survey_id = e.survey_id 
					and pr.project_id = s.project_id and q.ssq_id in (" . prep_implode($ssq_ids) . ") 
					order by q.scheduled_time_to_send";
			$q = db_query($sql);
			// Enable email time sent tracker to count time it takes to send all emails
			$mtime = explode(" ", microtime()); 
			$starttime = $mtime[1] + $mtime[0];
			// Set language
			$prev_language = 'English';
			// Loop through all emails to be sent and send them
			while ($row = db_fetch_assoc($q))
			{
				// Set variables for this loop
				$invitationSent = false;
				$ssq_id = $row['ssq_id'];
				$email_id = $row['email_id'];
				$reminder_num = $row['reminder_num'];
				// Get the TO email of the participant
				$participantEmail = (isset($ssq_ids_emails[$ssq_id]) && isEmail($ssq_ids_emails[$ssq_id])) ? $ssq_ids_emails[$ssq_id] : null;
				// Get the phone number of the participant
				$participantPhone = (isset($ssq_ids_voice_sms[$ssq_id])) ? $ssq_ids_voice_sms[$ssq_id] : null;
				// Get this project's language
				$this_language = $language_by_ssq_id[$row['ssq_id']];
				// If project language is not the same as the previous loop's language, then get this project's language, 
				// thus replacing $lang variable temporarily.
				if ($this_language != $prev_language) {
					$lang = getLanguage($this_language);
				}
				
				/* 
				// FAILSAFE if we do not have the email or phone number when delivery type if email or SMS/voice, respectively
				$sms_invite_survey_link = false;
				if ($participantPhone == null && $participantEmail != null 
					&& ($row['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $row['delivery_type'] == 'SMS_INITIATE' 
						|| $row['delivery_type'] == 'VOICE_INITIATE' || $row['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL')) {
					// If we don't have phone number but we DO have email address, then convert to email invitation
					$row['delivery_type'] = 'EMAIL';
				} elseif ($participantPhone != null && $participantEmail == null && $row['delivery_type'] == 'EMAIL') {
					// If we don't have an email address but we DO have a phone number, then send survey link via SMS
					$row['delivery_type'] = 'SMS_INVITE_MAKE_CALL';
					// Set special flag to make it send survey link and not phone number
					$sms_invite_survey_link = true;
				}
				*/
				
				// Determine how to deliver it
				if ($row['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $row['delivery_type'] == 'SMS_INITIATE' 
					|| $row['delivery_type'] == 'VOICE_INITIATE' || $row['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL') 
				{
					## TWILIO SMS/VOICE
					// Set Twilio values
					$twilio_enabled = $row['twilio_enabled'];
					$twilio_account_sid = $row['twilio_account_sid'];
					$twilio_auth_token = $row['twilio_auth_token'];
					$twilio_from_number = $row['twilio_from_number'];
					// Determine SMS or Voice Call delivery method
					if (!$twilio_enabled || !$twilio_enabled_global) {
						// TWILIO IS DISABLED
						$sql = "update redcap_surveys_scheduler_queue set status = 'DID NOT SEND',
								reason_not_sent = 'VOICE/SMS SETTING DISABLED' where ssq_id = $ssq_id";
					} elseif ($participantPhone == null) {
						// MISSING THE PHONE NUMBER
						$sql = "update redcap_surveys_scheduler_queue set status = 'DID NOT SEND',
								reason_not_sent = 'PHONE NUMBER NOT FOUND' where ssq_id = $ssq_id";
					} elseif ($row['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $row['delivery_type'] == 'SMS_INITIATE' || $row['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL') {
						## SMS DELIVERY
						// Get message to send
						$sms_message = $row['email_content'];
						if ($sms_message != '') $sms_message .= " -- ";
						// Get the survey access code for this survey link
						$survey_access_code = Survey::getAccessCode(getParticipantIdFromHash($row['hash']), false, false, true);						
						/* 
						// Change SMS message based on whether it is an SMS survey or voice survey with SMS invite
						if ($sms_invite_survey_link) {
							// Set survey link
							$this_survey_link = APP_PATH_SURVEY_FULL . '?s=' . $row['hash'];
							// If survey link itself exceeds the max length of the SMS *and* the bit.ly shortener is enabled,
							// then obtain the shortened link via bit.ly API call.
							if ($enable_url_shortener && strlen($this_survey_link) > TwilioRC::MAX_SMS_LENGTH) {
								// Retrieve shortened URL from URL shortener service
								$shorturl = Survey::getShortUrl($this_survey_link);	
								// Ensure that we received a link in the expected format
								if ($shorturl !== false) $this_survey_link = $shorturl;
							}							
							// Send survey link via SMS
							$sms_message .= "To begin the survey, visit $this_survey_link";
						} else
						*/						
						// Change SMS message based on whether it is an SMS survey or voice survey with SMS invite
						if ($row['delivery_type'] == 'SMS_INVITE_MAKE_CALL') {
							// Send phone number + access code via SMS
							$sms_message .= $lang['survey_863'] . " " . formatPhone($twilio_from_number);
							// Add phone number and access code to table
							TwilioRC::addSmsAccessCodeForPhoneNumber($participantPhone, $twilio_from_number, $survey_access_code);
						} elseif ($row['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL') {
							// Send access code via SMS for them to receive a call
							$sms_message .= $lang['survey_866'];
							// Add phone number and access code to table
							TwilioRC::addSmsAccessCodeForPhoneNumber($participantPhone, $twilio_from_number, Survey::PREPEND_ACCESS_CODE_NUMERAL . $survey_access_code);
						} else {
							// Send access code via SMS
							$sms_message .= $lang['survey_865'];
							// Add phone number and access code to table
							TwilioRC::addSmsAccessCodeForPhoneNumber($participantPhone, $twilio_from_number, $survey_access_code);
						}
						// Instantiate a client to Twilio's REST API
						$twilioClient = new Services_Twilio($twilio_account_sid, $twilio_auth_token);
						// Send SMS to the phone number
						$success = TwilioRC::sendSMS($sms_message, $participantPhone, $twilioClient, $twilio_from_number);
						if ($success === true) {
							// Set query to update as SENT with timestamp when sent
							$sql = "update redcap_surveys_scheduler_queue set status = 'SENT', time_sent = '".NOW."' 
									where ssq_id = $ssq_id";
							$invitationSent = true;
							$emailCountSuccess++;
						} else {
							// Set default query in case of failure
							$sql = "update redcap_surveys_scheduler_queue set status = 'DID NOT SEND',
									reason_not_sent = 'ERROR SENDING SMS' where ssq_id = $ssq_id";
							$emailCountFail++;
						}
					} else {
						## VOICE DELIVERY
						// Set the survey URL that Twilio will make the request to
						$question_url = APP_PATH_SURVEY_FULL . '?s=' . $row['hash'];
						// Instantiate a client to Twilio's REST API
						$twilioClient = new Services_Twilio($twilio_account_sid, $twilio_auth_token);
						// Call the phone number
						try {
							$twilioClient->account->calls->create($twilio_from_number, $participantPhone, $question_url);
							// Set query to update as SENT with timestamp when sent
							$sql = "update redcap_surveys_scheduler_queue set status = 'SENT', time_sent = '".NOW."' 
									where ssq_id = $ssq_id";
							$invitationSent = true;
							$emailCountSuccess++;
						} catch (Exception $e) {
							// Set default query in case of failure
							$sql = "update redcap_surveys_scheduler_queue set status = 'DID NOT SEND',
									reason_not_sent = 'ERROR MAKING VOICE CALL' where ssq_id = $ssq_id";
							$emailCountFail++;
						}
					}
				} 
				else 
				{
					## EMAIL DELIVERY
					// Set default query (failure to send because email address doesn't exist)
					$sql = "update redcap_surveys_scheduler_queue set status = 'DID NOT SEND',
							reason_not_sent = 'EMAIL ADDRESS NOT FOUND' where ssq_id = $ssq_id";
					// If fromEmail is missing, then have it sent from the recipient themself
					$fromEmail = empty($row['email_static']) ? $participantEmail : $row['email_static'];
					// If we have an email for this ssq_id, then send it. Otherwise, mark it as DID NOT SEND
					if ($participantEmail != null) 
					{
						// Decode subject/content (just in case)
						$row['email_content'] = label_decode($row['email_content']);
						$row['email_subject'] = strip_tags(label_decode($row['email_subject']));
						// If this is a reminder (not the first invitation), then prepend "[Reminder]" to the subject
						if ($reminder_num > 0) $row['email_subject'] = $lang['survey_732'] . " " . $row['email_subject'];
						// Build email message content	
						$emailContents = '<html><body style="font-family:Arial;font-size:10pt;">
							'.nl2br($row['email_content']).'<br /><br />	
							'.$lang['survey_134'].'<br />
							<a href="' . APP_PATH_SURVEY_FULL . '?s=' . $row['hash'] . '">'
							.($row['title'] == "" ? APP_PATH_SURVEY_FULL . '?s=' . $row['hash'] : strip_tags(label_decode($row['title']))).'</a><br /><br />
							'.$lang['survey_135'].'<br />
							' . APP_PATH_SURVEY_FULL . '?s=' . $row['hash'] . '<br /><br />	
							'.$lang['survey_137'].'
							</body></html>';
						// Construct email components
						$email->setTo($participantEmail);
						$email->setFrom($fromEmail);
						$email->setSubject($row['email_subject']);
						$email->setBody($emailContents);
						// Send email
						$invitationSent = $email->send();
						if ($invitationSent) {
							// Set query to update as SENT with timestamp when sent
							$sql = "update redcap_surveys_scheduler_queue set status = 'SENT', time_sent = '".NOW."' 
									where ssq_id = $ssq_id";
							$emailCountSuccess++;
						} else {
							// Mark as DID NOT SEND with reason why
							//if (php_sapi_name() != "cli") print $email->getSendError();
							$sql = "update redcap_surveys_scheduler_queue set status = 'DID NOT SEND',
									reason_not_sent = 'EMAIL ATTEMPT FAILED' where ssq_id = $ssq_id";
							$emailCountFail++;
						}
					}
				}
				
				// Execute query after email was sent or did not send
				$q2 = db_query($sql);
				// If email was sent successfully, then also add it to surveys_emails table to "log" it
				if ($q2 && $invitationSent) 
				{
					// Update surveys_emails table
					$sql = "update redcap_surveys_emails set email_sent = '".NOW."' 
							where email_id = $email_id and email_sent is null";
					db_query($sql);
				}
				// Set language for next loop
				$prev_language = $this_language;
				// Increment counter
				$numEmailsSent++;
			}
			
			// Free up memory
			db_free_result($q);
			unset($ssq_ids, $ssq_ids_emails, $ssq_ids_voice_sms);
			
			// If last loop's project language was NOT English, then reset $lang back to English
			if ($prev_language != 'English') {
				$lang = getLanguage('English');
			}
			
			// Now that all emails have been sent, record in table how long it took to send them (to use rate in future batches)
			if ($numEmailsSent >= self::MIN_RECORD_EMAILS_SENT) 
			{
				// Stop the email time sent tracker to count time it takes to send all emails
				$mtime = explode(" ", microtime()); 
				$endtime = $mtime[1] + $mtime[0];
				$totalTimeEmailsSent = ($endtime - $starttime);
				// Calculate the email sending rate in emails/minute
				$emailsSentPerMinuteCalculated = round(($numEmailsSent / $totalTimeEmailsSent) * 60);
				// Now add this value to the redcap_surveys_emails_send_rate table
				$sql = "insert into redcap_surveys_emails_send_rate (sent_begin_time, emails_per_batch, emails_per_minute)
						values ('" . NOW . "', $numEmailsSent, $emailsSentPerMinuteCalculated)";
				db_query($sql);
			}
		}
		// Return email-sending success/fail count
		return array($emailCountSuccess, $emailCountFail);
	}
	
	
	// If this was a survey response, and it was just completed BEFORE an invitation was sent out (when it was alrady queued to send), 
	// then remove it from the scheduler_queue table (if already in there).
	public static function deleteInviteIfCompletedSurvey($survey_id, $event_id, $record)
	{
		// Make sure the response is completed first
		if (!isResponseCompleted($survey_id, $record, $event_id)) return false;		
		// Initialize vars
		$ssq_ids = $email_recip_ids = array();
		$wasDeleted = false;
		// If invitation is already queued, then set it as DID NOT SEND with reason_not_sent of SURVEY ALREADY COMPLETED
		$sql = "select q.ssq_id, e.email_recip_id from redcap_surveys_participants p, redcap_surveys_response r, 
				redcap_surveys_scheduler_queue q, redcap_surveys_emails_recipients e where p.survey_id = $survey_id 
				and p.event_id = $event_id and r.participant_id = p.participant_id and p.participant_email is not null 
				and q.email_recip_id = e.email_recip_id and p.participant_id = e.participant_id
				and q.status = 'QUEUED' and r.record = '" . prep($record) . "'";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			while ($row = db_fetch_assoc($q)) {
				$ssq_ids[] = $row['ssq_id'];
			}
			// Remove from scheduler queue and recipient list
			if (!empty($ssq_ids)) {
				$sql = "delete from redcap_surveys_scheduler_queue where ssq_id in (".prep_implode($ssq_ids).")";
				$wasDeleted = db_query($sql);
			}
		}
		// Return true if removed it from queue table
		return $wasDeleted;
	}
	
}