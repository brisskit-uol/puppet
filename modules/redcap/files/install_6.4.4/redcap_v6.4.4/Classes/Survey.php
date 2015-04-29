<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . "/Surveys/survey_functions.php";

/**
 * SURVEY Class
 * Contains methods used with regard to surveys
 */
class Survey
{
	// Time period after which survey short codes will expire
	const SHORT_CODE_EXPIRE = 60; // minutes
	
	// Character length of survey short codes
	const SHORT_CODE_LENGTH = 5;
	
	// Character length of survey access codes
	const ACCESS_CODE_LENGTH = 9;
	
	// Character length of numeral survey access codes
	const ACCESS_CODE_NUMERAL_LENGTH = 10;
	
	// Character to prepend to numeral survey access codes to denote for REDCap to call them back
	const PREPEND_ACCESS_CODE_NUMERAL = "V";

	// Return array of form_name and survey response status (0=partial,2=complete) 
	// for a given project-record-event. $record may be a single record name or array of record names.
	public static function getResponseStatus($project_id, $record=null, $event_id=null)
	{
		$surveyResponses = array();
		$sql = "select r.record, p.event_id, s.form_name, if(r.completion_time is null,0,2) as survey_complete 
				from redcap_surveys s, redcap_surveys_participants p, redcap_surveys_response r 
				where s.survey_id = p.survey_id and p.participant_id = r.participant_id 
				and s.project_id = $project_id and r.first_submit_time is not null";
		if ($record != null && is_array($record)) {
			$sql .= " and r.record in (".prep_implode($record).")";
		} elseif ($record != null) {
			$sql .= " and r.record = '".prep($record)."'";
		}
		if (is_numeric($event_id)) 	$sql .= " and p.event_id = $event_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$surveyResponses[$row['record']][$row['event_id']][$row['form_name']] = $row['survey_complete'];
		}
		return $surveyResponses;
	}
	
	
	// Survey Notifications: Return array of surveys/users with attributes regarding email notifications for survey responses
	public static function getSurveyNotificationsList()
	{	
		// First get list of all project users to fill default values for array
		$endSurveyNotify = array();
		$sql = "select if(u.ui_id is null,0,1) as hasEmail, u.user_email as email1, u.user_firstname, u.user_lastname,
				if (u.email2_verify_code is null, u.user_email2, null) as email2, 
				if (u.email3_verify_code is null, u.user_email3, null) as email3,
				lower(r.username) as username from redcap_user_rights r 
				left outer join redcap_user_information u on u.username = r.username 
				where r.project_id = ".PROJECT_ID." order by lower(r.username)";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// where 0 is default value for hasEmail
			$endSurveyNotify[$row['username']] = array('surveys'=>array(), 'hasEmail'=>$row['hasEmail'], 'email1'=>$row['email1'],
													   'email2'=>$row['email2'], 'email3'=>$row['email3'], 
													   'name'=>label_decode($row['user_firstname'] == '' ? '' : trim("{$row['user_firstname']} {$row['user_lastname']}")) );
		}
		// Get list of users who have and have not been set up for survey notification via email
		$sql = "select lower(u.username) as username, a.survey_id, a.action_response 
				from redcap_actions a, redcap_user_information u 
				where a.project_id = ".PROJECT_ID." and a.action_trigger = 'ENDOFSURVEY' 
				and a.action_response in ('EMAIL_PRIMARY', 'EMAIL_SECONDARY', 'EMAIL_TERTIARY') 
				and u.ui_id = a.recipient_id order by u.username";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$email_acct = ($row['action_response'] == 'EMAIL_TERTIARY' ? 3 : ($row['action_response'] == 'EMAIL_SECONDARY' ? 2 : 1));
			$endSurveyNotify[$row['username']]['surveys'][$row['survey_id']] = $email_acct;
		}
		// Return array
		return $endSurveyNotify;
	}
	
	
	// Return boolean regarding if Survey Notifications are enabled
	public static function surveyNotificationsEnabled()
	{	
		// Get list of users who have and have not been set up for survey notification via email
		$sql = "select 1 from redcap_actions where project_id = ".PROJECT_ID." and action_trigger = 'ENDOFSURVEY' 
				and action_response in ('EMAIL_PRIMARY', 'EMAIL_SECONDARY', 'EMAIL_TERTIARY') limit 1";
		$q = db_query($sql);
		// Return boolean
		return (db_num_rows($q) > 0);
	}
	
	
	// Return boolean if Survey Queue is enabled for at least one instrument in this project
	public static function surveyQueueEnabled()
	{
		// Order by event then by form order
		$sql = "select count(1) from redcap_surveys_queue q, redcap_surveys s, redcap_metadata m, redcap_events_metadata e, 
				redcap_events_arms a where s.survey_id = q.survey_id and s.project_id = ".PROJECT_ID." and m.project_id = s.project_id 
				and s.form_name = m.form_name and q.event_id = e.event_id and e.arm_id = a.arm_id and q.active = 1 and s.survey_enabled = 1";
		$q = db_query($sql);
		return (db_result($q, 0) > 0);
	}
	
	
	// Return the complete Survey Queue prescription for this project
	public static function getProjectSurveyQueue($ignoreInactives=true)
	{
		$project_queue = array();
		// Order by event then by form order
		$sql = "select distinct q.* from redcap_surveys_queue q, redcap_surveys s, redcap_metadata m, redcap_events_metadata e, 
				redcap_events_arms a where s.survey_id = q.survey_id and s.project_id = ".PROJECT_ID." and m.project_id = s.project_id 
				and s.form_name = m.form_name and q.event_id = e.event_id and e.arm_id = a.arm_id and s.survey_enabled = 1";
		if ($ignoreInactives) $sql .= " and q.active = 1";
		$sql .= " order by a.arm_num, e.day_offset, e.descrip, m.field_order";
		$q = db_query($sql);
		if (db_num_rows($q) > 0) {
			while ($row = db_fetch_assoc($q)) {
				$survey_id = $row['survey_id'];
				$event_id = $row['event_id'];
				unset($row['survey_id'], $row['event_id']);
				$project_queue[$survey_id][$event_id] = $row;
			}
		}
		return $project_queue;
	}
	
	
	// Return the Survey Queue of completed/incomplete surveys for a given record.
	// If $returnTrueIfOneOrMoreItems is set to TRUE, then return boolean if one or more items exist in this record's queue.
	public static function getSurveyQueueForRecord($record, $returnTrueIfOneOrMoreItems=false)
	{
		global $Proj;
		// Add queue itmes for record to array
		$record_queue = array();
		// First, get the project's survey queue and loop to see how many are applicable for this record
		$project_queue = self::getProjectSurveyQueue();
		
		// Collect all survey/events where surveys have been completed for this record
		$completedSurveyEvents = array();
		$sql = "select p.event_id, p.survey_id from redcap_surveys_participants p, redcap_surveys_response r
				where r.participant_id = p.participant_id and p.survey_id in (".prep_implode(array_keys($Proj->surveys)).")
				and p.event_id in (".prep_implode(array_keys($Proj->eventInfo)).") and r.record = '" . prep($record) . "'
				and r.completion_time is not null";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$completedSurveyEvents[$row['survey_id']][$row['event_id']] = true;
		}
		
		// GET DATA for all fields used in Survey Queue conditional logic (for all queue items)
		$fields = array();
		$events = ($Proj->longitudinal) ? array() : array($Proj->firstEventId);
		// Loop through project queue for this record to get conditional logic used
		foreach ($project_queue as $survey_id=>$sattr) {
			foreach ($sattr as $event_id=>$queueItem) {
				if (trim($queueItem['condition_logic']) == '') continue;
				// Loop through fields used in the logic. Also, parse out any unique event names, if applicable
				foreach (array_keys(getBracketedFields($queueItem['condition_logic'], true, true, false)) as $this_field)
				{
					// Check if has dot (i.e. has event name included)
					if (strpos($this_field, ".") !== false) {
						list ($this_event_name, $this_field) = explode(".", $this_field, 2);
						$events[] = $this_event_name;
					}
					// Add field to array
					$fields[] = $this_field;
				}
			}
		}
		$events = array_unique($events);
		$fields = array_unique($fields);
		// Retrieve data from data table since $record_data array was not passed as parameter
		$record_data = Records::getData($Proj->project_id, 'array', $record, $fields, $events);
		if (empty($record_data[$record])) $record_data = null;
		
		// If some events don't exist in $record_data because there are no values in data table for that event, 
		// then add empty event with default values to $record_data (or else the parse will throw an exception).
		if (count($events) > count($record_data[$record])) {		
			// Get unique event names (with event_id as key)
			$unique_events = $Proj->getUniqueEventNames();
			// Loop through each event
			foreach ($events as $this_event_name) {
				$this_event_id = array_search($this_event_name, $unique_events);
				if (!isset($record_data[$record][$this_event_id])) {
					// Add all fields from $fields with defaults for this event
					foreach ($fields as $this_field) {
						// If a checkbox, set all options as "0" defaults
						if ($Proj->isCheckbox($this_field)) {
							foreach (parseEnum($Proj->metadata[$this_field]['element_enum']) as $this_code=>$this_label) {
								$record_data[$record][$this_event_id][$this_field][$this_code] = "0";
							}
						} 
						// If a Form Status field, give "0" default
						elseif ($this_field == $Proj->metadata[$this_field]['form_name']."_complete") {
							$record_data[$record][$this_event_id][$this_field] = "0";
						} else {
							$record_data[$record][$this_event_id][$this_field] = "";
						}
					}
				}
			}
		}
		
		// Loop through project queue for this record
		foreach ($project_queue as $survey_id=>$sattr) {
			foreach ($sattr as $event_id=>$queueItem) {
				// Should this item be displayed in the record's queue?
				$displayInQueue = self::checkConditionsOfRecordToDisplayInQueue($record, $queueItem, $completedSurveyEvents, $record_data);
				// If will be displayed in queue, get their survey link hash and then add to array
				if ($displayInQueue) {
					// If set flag to return boolean, then stop here and return TRUE
					if ($returnTrueIfOneOrMoreItems) return true;
					// Determine if participant has completed this survey-event already
					$completedSurvey = (isset($completedSurveyEvents[$survey_id][$event_id]));
					// Get the survey hash for this survey-event-record
					list ($participant_id, $hash) = self::getFollowupSurveyParticipantIdHash($survey_id, $record, $event_id);
					// Add to array
					$record_queue["$survey_id-$event_id"] = array(
						'survey_id'=>$survey_id, 'event_id'=>$event_id, 'title'=>$Proj->surveys[$survey_id]['title'],
						'participant_id'=>$participant_id, 'hash'=>$hash, 'auto_start'=>$queueItem['auto_start'], 'completed'=>($completedSurvey ? 1 : 0)
					);
				}
			}
		}
		
		
		// Loop through all surveys and add to record's queue if a survey has already been completed
		$record_queue_all = array();
		// Loop through all arms
		foreach ($Proj->events as $arm_num=>$attr) {
			// Loop through each event in this arm
			foreach (array_keys($attr['events']) as $event_id) {
				// Loop through forms designated for this event
				foreach ($Proj->eventsForms[$event_id] as $form_name) {
					// If form is enabled as a survey
					if (isset($Proj->forms[$form_name]['survey_id'])) {
						// Get survey_id
						$survey_id = $Proj->forms[$form_name]['survey_id'];
						// If we have already saved this survey in our record_queue, then just copy the existing attributes
						if (isset($record_queue["$survey_id-$event_id"])) {
							// Add to array
							$record_queue_all["$survey_id-$event_id"] = $record_queue["$survey_id-$event_id"];
						} else {
							// Check if survey was completed
							$completedSurvey = (isset($completedSurveyEvents[$survey_id][$event_id]));
							// Only add survey to queue if completed
							if ($completedSurvey) {
								// Get the survey hash for this survey-event-record
								list ($participant_id, $hash) = self::getFollowupSurveyParticipantIdHash($survey_id, $record, $event_id);			
								// Prepend to array
								$record_queue_all["$survey_id-$event_id"] = array(
									'survey_id'=>$survey_id, 'event_id'=>$event_id, 'title'=>$Proj->surveys[$survey_id]['title'],
									'participant_id'=>$participant_id, 'hash'=>$hash, 'auto_start'=>'0', 'completed'=>($completedSurvey ? 1 : 0)
								);
							}
						}
					}
				}
			}
		}
		
		// If set flag to return boolean, then stop here
		if ($returnTrueIfOneOrMoreItems) return (!empty($record_queue_all));
		
		// Return the survey queue for this record
		return $record_queue_all;
	}
	
	
	// Display the Survey Queue of completed/incomplete surveys for a given record in HTML table format
	public static function displaySurveyQueueForRecord($record, $isSurveyAcknowledgement=false)
	{
		global $Proj, $lang, $isAjax, $survey_queue_custom_text;
		// Get survey queue items for this record
		$survey_queue_items = self::getSurveyQueueForRecord($record);
		// Obtain the survey queue hash for this record
		$survey_queue_hash = self::getRecordSurveyQueueHash($record);
		$survey_queue_link = APP_PATH_SURVEY_FULL . '?sq=' . $survey_queue_hash;
		// If empty, then return and display nothing
		if (empty($survey_queue_items)) return "";
		// Obtain participant's email address, if we have one
		$participant_emails_idents = self::getResponsesEmailsIdentifiers(array($record));
		foreach ($participant_emails_idents as $participant_id=>$pattr) {
			$participant_email = $pattr['email'];
		}
		// AUTO-START: If enabled for the first incomplete survey in queue, then redirect there
		if ($isSurveyAcknowledgement) {
			// Loop through queue to find the first incomplete survey
			foreach ($survey_queue_items as $queueAttr) {
				if ($queueAttr['completed'] > 0) continue;
				if ($queueAttr['auto_start']) {
					// Redirect to first incomplete survey in queue
					redirect(APP_PATH_SURVEY_FULL . '?s=' . $queueAttr['hash']);
				}
				// Stop looping if first incomplete survey does not have auto-start enabled
				break;
			}
		}
		// Get a count of the number of surveys in queue that have been completed already. If more than 4, then compact them.
		$numSurveysCompleted = 0;
		foreach ($survey_queue_items as $queueAttr) {
			if ($queueAttr['completed'] > 0) $numSurveysCompleted++;
		}
		// Collect all html as variable
		$html = "";
		$row_data = array();
		// Loop through items to display each as a row
		$isFirstIncompleteSurvey = true;
		$hideCompletedSurveys = ($numSurveysCompleted > 5);
		$num_survey_queue_items = count($survey_queue_items);
		$allSurveysCompleted = ($num_survey_queue_items == $numSurveysCompleted);
		$cumulRowHtml = $thisRowHtml = '';
		$rowCounter = 1;
		$surveyCompleteIconText = 	RCView::img(array('src'=>'tick.png', 'style'=>'vertical-align:middle;')) .
									RCView::span(array('style'=>'font-weight:normal;vertical-align:middle;line-height:22px;font-size:12px;color:green;'), $lang['survey_507']);
		foreach ($survey_queue_items as $queueAttr) 
		{
			// Set onclick action for link/button
			$onclick = ($isAjax) ? "window.open(app_path_webroot_full+'surveys/index.php?s={$queueAttr['hash']}','_blank');"
								 : "window.location.href = app_path_webroot_full+'surveys/index.php?s={$queueAttr['hash']}';";
			// Set button text
			$rowClass = $title_append = '';
			if ($queueAttr['completed']) {
				// If completed and more than $maxSurveysCompletedHide are completed, then hide row
				$rowClass = ($hideCompletedSurveys) ? 'hidden' : '';				
				// Set image and text
				$button = $surveyCompleteIconText;
				$title_style = 'color:#aaa;';
				// If this survey has Save&Return + Edit Completed Response setting enabled, give link to open response
				if ($Proj->surveys[$queueAttr['survey_id']]['save_and_return'] 
					&& $Proj->surveys[$queueAttr['survey_id']]['edit_completed_response']) 
				{
					$title_append = RCView::div(array('class'=>'opacity75 nowrap', 'onmouseover'=>"$(this).removeClass('opacity75');", 'onmouseout'=>"$(this).addClass('opacity75');", 'style'=>'float:right;margin:0 10px 0 20px;'), 
										RCView::button(array('class'=>'jqbuttonmed', 'onclick'=>$onclick, 'style'=>'font-weight:normal;font-size:11px;'),
											RCView::img(array('src'=>'pencil_small2.png', 'style'=>'vertical-alignment:middle;position:relative;top:1px;margin-right:2px;')) .
											RCView::span(array('style'=>'vertical-alignment:middle;'), $lang['data_entry_174'])
										)
									);
				}
			} else {
				// Set button and text
				$button = RCView::button(array('class'=>'jqbuttonmed', 'style'=>'vertical-align:middle;', 'onclick'=>$onclick), $lang['survey_504']);
				$title_style = '';
			}
			// Add extra row to allow participant to display all completed surveys
			if (($allSurveysCompleted && $rowCounter == $num_survey_queue_items && $hideCompletedSurveys) 
				|| (!$queueAttr['completed'] && $hideCompletedSurveys && $isFirstIncompleteSurvey)) 
			{
				// Set flag so that this doesn't get used again
				$isFirstIncompleteSurvey = false;
				// Add extra row
				$row_data[] = 	array(
									RCView::div(array('class'=>"wrap", 'style'=>'font-weight:normal;padding:2px 0;'), $surveyCompleteIconText),
									RCView::div(array('class'=>"wrap", 'style'=>'font-weight:normal;line-height:22px;font-size:13px;color:#444;'),
										($allSurveysCompleted
											? RCView::span(array('style'=>'font-size:13px;color:green;font-weight:bold;'), $lang['survey_536'])
											: $numSurveysCompleted . " " . $lang['survey_534']
										) .
										RCView::a(array('href'=>'javascript:;', 'style'=>'margin-left:8px;font-size:11px;font-weight:normal;', 'onclick'=>"
											$(this).parents('tr:first').hide();
											$('table#table-survey_queue div.hidden').show('fade');
										"), 
											$lang['survey_535']
										)
									)
								);
			}
			// Add this row's HTML
			$row_data[] = 	array(
								RCView::div(array('class'=>"wrap $rowClass", 'style'=>'padding:2px 0;'), $button),
								RCView::div(array('class'=>"wrap $rowClass", 'style'=>$title_style.'padding:4px 0;font-size:13px;font-weight:bold;float:left;'),
									RCView::escape($queueAttr['title'])
								) .
								$title_append .
								RCView::div(array('class'=>'clear'), '')
							);
			// Increment counter
			$rowCounter++;
		}
		// Survey queue header text
		$table_title = 	RCView::div(array('style'=>''), 
							RCView::div(array('style'=>'float:left;color:#800000;font-size:14px;'),
								RCView::img(array('src'=>'list_red.gif', 'style'=>'vertical-align:middle;position:relative;top:2px;')) .
								RCView::span(array('style'=>'vertical-align:middle;'), $lang['survey_505'])
							) .
							RCView::div(array('style'=>'float:right;margin-right:10px;'), 
								RCView::button(array('class'=>'jqbuttonmed', 'style'=>'', 'onclick'=>"simpleDialog(null,null,'survey_queue_link_dialog',600);"), 
									RCView::img(array('src'=>'link.png', 'style'=>'vertical-align:middle;')) .
									RCView::span(array('style'=>'vertical-align:middle;'), $lang['survey_510'])
								)
							) .
							RCView::div(array('class'=>'wrap', 'style'=>'clear:both;padding-top:2px;font-weight:normal;font-family:arial;font-size:12px;'),
								// Display custom survey queue text (invoke piping also) OR the default text
								($survey_queue_custom_text != ''
									?  label_decode(Piping::replaceVariablesInLabel(nl2br(filter_tags(label_decode($survey_queue_custom_text))), $record, $Proj->firstEventId))
									: $lang['survey_506'] . RCView::br() . $lang['survey_511']
								)
							)
						);
		// Set table headers
		$table_hdrs = array( 
			array(120, $lang['dataqueries_23'], "center"), 
			array(657, $lang['survey_49'])
		);
		// Build table
		$html .= renderGrid("survey_queue", $table_title, 802, 'auto', $table_hdrs, $row_data, true, false, false);
		// Hidden dialog div for getting link to survey queue
		$html .= RCView::div(array('id'=>'survey_queue_link_dialog', 'class'=>'simpleDialog', 'style'=>'z-index: 9999;', 'title'=>$lang['survey_510']), 
					RCView::div(array('style'=>'margin:0 0 20px;'),
						$lang['survey_516']
					) .
					RCView::div(array(),
						RCView::img(array('src'=>'link.png', 'class'=>'imgfix')) .
						RCView::b($lang['survey_513']) .
						RCView::div(array('style'=>'margin:5px 0 10px 25px;'),
							RCView::text(array('readonly'=>'readonly', 'class'=>'staticInput', 'style'=>'width:90%;', 'onclick'=>"this.select();", 'value'=>$survey_queue_link))
						)
					) .
					RCView::div(array('style'=>'margin:20px 0 15px 10px;color:#999;'), 
						"&mdash; ".$lang['global_46']. " &mdash;"
					) .
					RCView::div(array('style'=>'margin-bottom:20px;'),
						RCView::img(array('src'=>'email.png', 'class'=>'imgfix', 'style'=>'margin-right:1px;')) .
						RCView::b($lang['survey_514']) .
						RCView::div(array('style'=>'margin:5px 0 10px 25px;'),
							RCView::text(array('id'=>'survey_queue_email_send', 'class'=>'x-form-text x-form-field', 'style'=>'margin-left:8px;width:250px;'.($participant_email == '' ? "color:#777777;" : ''), 
								'onblur'=>"if(this.value==''){this.value='".cleanHtml($lang['survey_515'])."';this.style.color='#777777';} if(this.value != '".cleanHtml($lang['survey_515'])."'){redcap_validate(this,'','','soft_typed','email')}", 
								'value'=>($participant_email == '' ? $lang['survey_515'] : $participant_email),
								'onfocus'=>"if(this.value=='".cleanHtml($lang['survey_515'])."'){this.value='';this.style.color='#000000';}",
								'onclick'=>"if(this.value=='".cleanHtml($lang['survey_515'])."'){this.value='';this.style.color='#000000';}"
							)) .
							RCView::button(array('class'=>'jqbuttonmed', 'style'=>'', 'onclick'=>"
								var emailfld = document.getElementById('survey_queue_email_send');
								if (emailfld.value == '".cleanHtml($lang['survey_515'])."') {
									simpleDialog('".cleanHtml($lang['survey_515'])."',null,null,null,'document.getElementById(\'survey_queue_email_send\').focus();');
								} else if (redcap_validate(emailfld, '', '', '', 'email')) {
									$.post('$survey_queue_link',{ to: emailfld.value },function(data){
										if (data != '1') {
											alert(woops);
										} else {
											$('#survey_queue_link_dialog').dialog('close');
											simpleDialog('".cleanHtml($lang['survey_225'])." '+emailfld.value+'".cleanHtml($lang['period'])."','".cleanHtml($lang['survey_524'])."');
										}
									});
								}
							"), $lang['survey_180']) .
							($participant_email != '' ? '' :
								RCView::div(array('style'=>'color:#800000;font-size:11px;margin:5px 10px 0;'), '* '.$lang['survey_125'])
							)
						)
					)
				 );
		// If ajax call, then add a Close button to close the dialog
		if ($isAjax) {
			$html .= RCView::div(array('style'=>'text-align:right;background-color:#fff;padding:8px 15px;'), 
						RCView::button(array('class'=>'jqbutton', 'onclick'=>"$('#survey_queue_corner_dialog').hide();$('#overlay').hide();"), 
							RCView::span(array('style'=>'line-height:22px;margin:5px;color:#555;'), $lang['calendar_popup_01'])
						)
					 );
		}
		// If this is the Acknowledgement section of a survey (and not the Survey Queue page itself), 
		// then change the URL to the survey queue link, in case they decide to bookmark the page.
		if ($isSurveyAcknowledgement && !$isAjax) {
			$html .= "<script type='text/javascript'>modifyURL('$survey_queue_link');</script>";
		}
		// Return all html
		return RCView::div(array(), $html);
	}
	
	
	// Get the Survey Queue hash for this record. If doesn't exist yet, then generate it.
	// Use $hashExistsOveride=true to skip the initial check that the hash exists for this record if you know it does not.
	public static function getRecordSurveyQueueHash($record=null, $hashExistsOveride=false)
	{
		// Validate record name
		if ($record == '') return null;
		// Default value
		$hashExists = false;
		// Check if record already has a hash
		if (!$hashExistsOveride) {
			$sql = "select hash from redcap_surveys_queue_hashes where project_id = ".PROJECT_ID." 
					and record = '".prep($record)."' limit 1";
			$q = db_query($sql);
			$hashExists = (db_num_rows($q) > 0);
		}
		// If hash exists, then get it from table
		if ($hashExists) {
			// Hash already exists
			$hash = db_result($q, 0);
		} else {
			// Hash does NOT exist, so generate a unique one
			do {
				// Generate a new random hash
				$hash = generateRandomHash(10);
				// Ensure that the hash doesn't already exist in either redcap_surveys or redcap_surveys_hash (both tables keep a hash value)
				$sql = "select hash from redcap_surveys_queue_hashes where hash = '$hash' limit 1";
				$hashExists = (db_num_rows(db_query($sql)) > 0);
			} while ($hashExists);
			// Add newly generated hash for record
			$sql = "insert into redcap_surveys_queue_hashes (project_id, record, hash) 
					values (".PROJECT_ID.", '".prep($record)."', '$hash')";
			if (!db_query($sql) && $hashExistsOveride) {
				// The override failed, so apparently the hash DOES exist, so get it
				$hash = self::getRecordSurveyQueueHash($record);
			}
		}
		// Return the hash
		return $hash;
	}
	
	
	// Get the Survey Queue hash for LOTS of records in an array.
	// Return hashes as array values with record name as array key.
	public static function getRecordSurveyQueueHashBulk($records=array())
	{
		// Put hashes in array
		$hashes = array();
		// Get all existing hashes
		$sql = "select record, hash from redcap_surveys_queue_hashes where project_id = ".PROJECT_ID." 
				and record in (".prep_implode($records).")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$hashes[$row['record']] = $row['hash'];
		}
		// For those without a hash, go generate one
		foreach (array_diff($records, array_keys($hashes)) as $this_record) {
			if ($this_record == '') continue;
			$hashes[$this_record] = self::getRecordSurveyQueueHash($this_record);
		}
		// Order by record
		natcaseksort($hashes);
		// Return hashes
		return $hashes;
	}
	
	
	// Determine if this survey-event should be displayed in the Survey Queue for this record.
	// Parameter $completedSurveyEvents can be optionally passed, in which it contains the survey_id (first level key)
	// and event_id (second level key) of all completed survey responses for this record.
	public function checkConditionsOfRecordToDisplayInQueue($record, $queueItem, $completedSurveyEvents=null, $record_data=null)
	{
		// If conditional upon survey completion, check if completed survey
		$conditionsPassedSurveyComplete = ($queueItem['condition_andor'] == 'AND'); // Initial true value if using AND (false if using OR)
		if (is_numeric($queueItem['condition_surveycomplete_survey_id']) && is_numeric($queueItem['condition_surveycomplete_event_id']))
		{
			// Is it a completed response?
			if (is_array($completedSurveyEvents)) {
				$conditionsPassedSurveyComplete = (isset($completedSurveyEvents[$queueItem['condition_surveycomplete_survey_id']][$queueItem['condition_surveycomplete_event_id']]));
			} else {
				$conditionsPassedSurveyComplete = isResponseCompleted($queueItem['condition_surveycomplete_survey_id'], $record, $queueItem['condition_surveycomplete_event_id']);
			}
			// If not listed as a completed response, then also check Form Status (if entered as plain record data instead of as response), just in case
			if (!$conditionsPassedSurveyComplete) {
				$conditionsPassedSurveyComplete = SurveyScheduler::isFormStatusCompleted($queueItem['condition_surveycomplete_survey_id'], $queueItem['condition_surveycomplete_event_id'], $record);
			}
		}
		// If conditional upon custom logic
		$conditionsPassedLogic = ($queueItem['condition_andor'] == 'AND'); // Initial true value if using AND (false if using OR)
		if ($queueItem['condition_logic'] != '' 
			// If using AND and $conditionsPassedSurveyComplete is false, then no need to waste time checking evaluateLogicSingleRecord().
			// If using OR and $conditionsPassedSurveyComplete is true, then no need to waste time checking evaluateLogicSingleRecord().
			&& (($queueItem['condition_andor'] == 'OR' && !$conditionsPassedSurveyComplete) 
				|| ($queueItem['condition_andor'] == 'AND' && $conditionsPassedSurveyComplete)))
		{
			// Does the logic evaluate as true?
			$conditionsPassedLogic = LogicTester::evaluateLogicSingleRecord($queueItem['condition_logic'], $record, $record_data);
		}
		// Check pass/fail values and return boolean if record is ready to have its invitation for this survey/event
		if ($queueItem['condition_andor'] == 'OR') {
			// OR
			return ($conditionsPassedSurveyComplete || $conditionsPassedLogic);
		} else {
			// AND (default)
			return ($conditionsPassedSurveyComplete && $conditionsPassedLogic);
		}
	}

	// Validate and clean the survey queue hash, while also returning the record name to which it belongs
	public static function checkSurveyQueueHash($survey_queue_hash)
	{
		global $lang, $project_language;
		// Language: Call the correct language file for this project (default to English)
		if (empty($lang)) {
			$lang = getLanguage($project_language);
		}
		// Trim hash, just in case
		$survey_queue_hash = trim($survey_queue_hash);
		// Ensure integrity of hash, and if extra characters have been added to hash somehow, chop them off.
		if (strlen($survey_queue_hash) > 10) {
			$survey_queue_hash = substr($survey_queue_hash, 0, 10);
		}
		// Check if hash is valid
		$sql = "select project_id, record from redcap_surveys_queue_hashes 
				where hash = '".prep($survey_queue_hash)."' limit 1";
		$q = db_query($sql);
		$hashValid = (db_num_rows($q) > 0);
		// If the hash is valid, then return project_id and record, else stop and give error message
		if ($hashValid) {
			$row = db_fetch_assoc($q);
			return array($row['project_id'], $row['record']);
		} else {
			exitSurvey($lang['survey_508'], true, $lang['survey_509']);
		}
	}
	

	// Validate and clean the survey hash, while also returning if a legacy hash
	public static function checkSurveyHash()
	{
		global $lang, $project_language;
		// Obtain hash from GET or POST
		$hash = isset($_GET['s']) ? $_GET['s'] : (isset($_POST['s']) ? $_POST['s'] : "");
		// If could not find hash, try as legacy hash
		if (empty($hash)) {
			$hash = isset($_GET['hash']) ? $_GET['hash'] : (isset($_POST['hash']) ? $_POST['hash'] : "");
		}
		// Trim hash, just in case
		$hash = trim($hash);
		// Language: Call the correct language file for this project (default to English)
		if (empty($lang)) {
			$lang = getLanguage($project_language);
		}
		// Ensure integrity of hash, and if extra characters have been added to hash somehow, chop them off.
		$hash_length = strlen($hash);
		if ($hash_length >= 4 && $hash_length <= 10 && preg_match("/([A-Za-z0-9])/", $hash)) {
			$legacy = false;
		} elseif ($hash_length > 10 && strlen($hash) < 32 && preg_match("/([A-Za-z0-9])/", $hash)) {
			$hash = substr($hash, 0, 10);
			$legacy = false;
		} elseif ($hash_length == 32 && preg_match("/([a-z0-9])/", $hash)) {
			$legacy = true;
		} elseif ($hash_length > 32 && preg_match("/([a-z0-9])/", $hash)) {
			$hash = substr($hash, 0, 32);
			$legacy = true;
		} elseif (empty($hash)) {
			exitSurvey("{$lang['survey_11']}
						<a href='javascript:;' style='font-size:16px;color:#800000;' onclick=\"
							window.location.href = app_path_webroot+'Surveys/create_survey.php?pid='+getParameterByName('pid',true)+'&view=showform';
						\">{$lang['survey_12']}</a> {$lang['survey_13']}");
		} else {
			exitSurvey($lang['survey_14']);
		}
		// If legacy hash, then retrieve newer hash to return
		if ($legacy)
		{
			$q = db_query("select hash from redcap_surveys_participants where legacy_hash = '$hash'");
			if (db_num_rows($q) > 0) {
				$hash = db_result($q, 0);
			} else {
				exitSurvey($lang['survey_14']);
			}
		}
		// Return hash
		return $hash;
	}


	// Pull survey values from tables and set as global variables
	public static function setSurveyVals($hash)
	{
		global $lang;
		// Ensure that hash exists. Retrieve ALL survey-related info and make all table fields into global variables
		$sql = "select * from redcap_surveys s, redcap_surveys_participants h where h.hash = '".prep($hash)."' 
				and s.survey_id = h.survey_id limit 1";
		$q = db_query($sql);
		if (!$q || !db_num_rows($q)) {
			exitSurvey($lang['survey_14']);
		}
		foreach (db_fetch_assoc($q) as $key => $value) 
		{
			if ($value === null) {
				$GLOBALS[$key] = $value;
			} else {
				// Replace non-break spaces because they cause issues with html_entity_decode()
				$value = str_replace(array("&amp;nbsp;", "&nbsp;"), array(" ", " "), $value);
				// Don't decode if cannnot detect encoding
				if (function_exists('mb_detect_encoding') && (
					(mb_detect_encoding($value) == 'UTF-8' && mb_detect_encoding(html_entity_decode($value, ENT_QUOTES)) === false)
					|| (mb_detect_encoding($value) == 'ASCII' && mb_detect_encoding(html_entity_decode($value, ENT_QUOTES)) === 'UTF-8')
				)) {
					$GLOBALS[$key] = trim($value);
				} else {
					$GLOBALS[$key] = trim(html_entity_decode($value, ENT_QUOTES));
				}
			}
		}
	}

	
	// Returns array of emails, identifiers, phone numbers, and delivery preference for a list of records
	public static function getResponsesEmailsIdentifiers($records=array())
	{
		global $Proj, $survey_email_participant_field, $survey_phone_participant_field;
		
		// If pass in empty array of records, pass back empty array
		if (empty($records)) return array();
		
		// Get the first event_id of every Arm and place in array
		$firstEventIds = array();
		foreach ($Proj->events as $this_arm_num=>$arm_attr) {
			$firstEventIds[] = print_r(array_shift(array_keys($arm_attr['events'])), true);
		}
		
		// Create an array to return with participant_id as key and attributes as subarray
		$responseAttributes = array();
		// Pre-fill with all records passed in first
		foreach ($records as $record) {
			if ($record == '') continue;
			$responseAttributes[label_decode($record)] = array('email'=>'', 'identifier'=>'', 
															   'phone'=>'', 'delivery_preference'=>'EMAIL');
		}
		
		## GET EMAILS FROM INITIAL SURVEY'S PARTICIPANT LIST (if there is an initial survey)
		if ($Proj->firstFormSurveyId != null)
		{
			// Create record list to query participant table. Escape the record names for the query.
			$partRecordsSql = array();
			foreach ($records as $record) {
				if ($record == '') continue;
				$partRecordsSql[] = label_decode($record);
			}
			// Now use that record list to get the original email from first survey's participant list	
			$sql = "select r.record, p.participant_email, p.participant_identifier, p.participant_phone, p.delivery_preference 
					from redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys s 
					where s.project_id = ".PROJECT_ID." and p.survey_id = s.survey_id and p.participant_id = r.participant_id 
					and r.record in (".prep_implode($partRecordsSql).") and s.form_name = '".$Proj->firstForm."' 
					and p.event_id in (".prep_implode($firstEventIds).") and p.participant_email is not null 
					and (p.participant_email != '' or p.participant_phone is not null or p.participant_phone != '')";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$row['record'] = label_decode($row['record']);
				if ($row['participant_email'] != '') {
					$responseAttributes[$row['record']]['email'] = label_decode($row['participant_email']);
				}
				if ($row['participant_identifier'] != '') {
					$responseAttributes[$row['record']]['identifier'] = strip_tags(label_decode($row['participant_identifier']));
				}
				if ($row['participant_phone'] != '') {
					$responseAttributes[$row['record']]['phone'] = $row['participant_phone'];
				}
				if ($row['delivery_preference'] != '') {
					$responseAttributes[$row['record']]['delivery_preference'] = $row['delivery_preference'];
				}
			}
		}
		
		## GET ANY REMAINING MISSING EMAILS FROM SPECIAL EMAIL FIELD IN REDCAP_PROJECTS TABLE
		if ($survey_email_participant_field != '')
		{
			// Create record list of responses w/o emails to query data table. Escape the record names for the query.
			$partRecordsSql = array();
			foreach ($responseAttributes as $record=>$attr) {
				$partRecordsSql[] = label_decode($record);
			}
			// Now use that record list to get the email value from the data table
			$sql = "select record, value from redcap_data where project_id = ".PROJECT_ID." 
					and field_name = '".prep($survey_email_participant_field)."' 
					and record in (".prep_implode($partRecordsSql).")";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				// Skip if blank
				if ($row['value'] == '') continue;
				// Trim and decode, just in case
				$email = trim(label_decode($row['value']));
				// Don't use it unless it's a valid email address
				if (isEmail($email)) {
					$responseAttributes[label_decode($row['record'])]['email'] = $email;
				}
			}
		}
		
		## GET ANY REMAINING MISSING PHONE NUMBERS FROM SPECIAL PHONE FIELD IN REDCAP_PROJECTS TABLE
		if ($survey_phone_participant_field != '')
		{
			// Create record list of responses w/o emails to query data table. Escape the record names for the query.
			$partRecordsSql = array();
			foreach ($responseAttributes as $record=>$attr) {
				if ($attr['phone'] != '') continue;
				$partRecordsSql[] = label_decode($record);
			}
			// Now use that record list to get the phone value from the data table
			$sql = "select record, value from redcap_data where project_id = ".PROJECT_ID." 
					and field_name = '".prep($survey_phone_participant_field)."' 
					and record in (".prep_implode($partRecordsSql).") and value != ''";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$phone = preg_replace("/[^0-9]/", "", label_decode($row['value']));
				// Don't use it unless it's a valid phone number
				if ($phone != '') {
					$responseAttributes[label_decode($row['record'])]['phone'] = $phone;
				}
			}
		}
		
		// Return array
		return $responseAttributes;
	}
	
	
	// Display the Survey Queue setup table in HTML table format
	public static function displaySurveyQueueSetupTable()
	{
		global $lang, $longitudinal, $Proj, $survey_queue_custom_text;

		// Get this project's currently saved queue
		$projectSurveyQueue = self::getProjectSurveyQueue(false);
		
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
					// If has no survey title, then substitute it with form label
					if (trim($title) == '') $title = $Proj->forms[$form]['menu'];
					// Add survey to array
					$surveyDD["$this_survey_id-$this_event_id"] = "\"$title\"" . ($longitudinal ? " - $event" : "");
				}
				// Add values to array
				$surveyEvents[] = array('event_id'=>$this_event_id, 'event_name'=>$event, 'form'=>$form, 
										'survey_id'=>$this_survey_id, 'survey_title'=>$title);
			}
		}
		// Loop through surveys-events
		$hdrs = RCView::tr(array(),
					RCView::td(array('class'=>'header', 'style'=>'width:75px;text-align:center;font-size:11px;'), $lang['survey_430']) .
					RCView::td(array('class'=>'header'), $lang['survey_49']) .
					RCView::td(array('class'=>'header', 'style'=>'width:400px;'), $lang['survey_526']) .				
					RCView::td(array('class'=>'header', 'style'=>'width:34px;text-align:center;font-size:11px;line-height:13px;'), $lang['survey_529'])
				);
		$rows = '';
		foreach ($Proj->eventsForms as $event_id=>$these_forms) {
			// Loop through forms			
			$alreadyDisplayedEventHdr = false;
			foreach ($these_forms as $form_name) {
				// If form is not enabled as a survey, then skip it
				if (!isset($Proj->forms[$form_name]['survey_id'])) continue;
				// Get survey_id
				$survey_id = $Proj->forms[$form_name]['survey_id'];
				// Skip the first instrument survey since it is naturally not included in the queue till after it is completed
				if ($survey_id == $Proj->firstFormSurveyId) continue;
				// If longitudinal, display Event Name as header
				if ($longitudinal && !$alreadyDisplayedEventHdr) {
					$rows .= RCView::tr(array(),
								RCView::td(array('class'=>'header blue', 'colspan'=>'4', 'style'=>'padding:3px 6px;font-weight:bold;'), 
									$Proj->eventInfo[$event_id]['name_ext']
								)
							);
					$alreadyDisplayedEventHdr = true;
				}
				// Set form+event+arm label
				$form_event_label = $Proj->forms[$form_name]['menu'] . (!$longitudinal ? '' : " (" . $Proj->eventInfo[$event_id]['name_ext'] . ")");
				// Get any saved attributes for this survey/event
				if (isset($projectSurveyQueue[$survey_id][$event_id])) {
					$queue_item = $projectSurveyQueue[$survey_id][$event_id];
					$conditionSurveyActivatedChecked = ($queue_item['active']) ? 'checked' : '';
					$conditionSurveyActivatedDisabled = '';
					$conditionSurveyCompChecked = (is_numeric($queue_item['condition_surveycomplete_survey_id']) && is_numeric($queue_item['condition_surveycomplete_event_id'])) ? 'checked' : '';
					$conditionSurveyCompSelected = (is_numeric($queue_item['condition_surveycomplete_survey_id']) && is_numeric($queue_item['condition_surveycomplete_event_id'])) ? $queue_item['condition_surveycomplete_survey_id'].'-'.$queue_item['condition_surveycomplete_event_id'] : '';
					$conditionAndOr = ($queue_item['condition_andor'] == 'OR') ? 'OR' : 'AND';
					$conditionLogicChecked = (trim($queue_item['condition_logic']) == '') ? '' : 'checked';
					$conditionLogic = $queue_item['condition_logic'];
					$conditionAutoStartChecked = ($queue_item['auto_start']) ? 'checked' : '';
					$queue_item_class = $queue_item_class_firstcell = 'darkgreen';
					$queue_item_active_flag = 'active';
					$queue_item_active_flag_value = '1';
					$queue_item_icon_enabled_style = '';
					$queue_item_icon_disabled_style = 'display:none;';
				} else {
					$conditionSurveyActivatedChecked = $conditionSurveyCompChecked = $conditionSurveyCompSelected = '';
					$conditionAndOr = $conditionLogicChecked = $conditionLogic = $conditionAutoStartChecked = '';
					$queue_item_class_firstcell = $queue_item_active_flag = $queue_item_active_flag_value = '';
					$queue_item_class = 'opacity35';
					$queue_item_icon_enabled_style = 'display:none;';
					$queue_item_icon_disabled_style = '';
					$conditionSurveyActivatedDisabled = 'disabled';
				}
				// Set survey title for this row
				$title = $Proj->surveys[$survey_id]['title'];
				// If has no survey title, then substitute it with form label
				if (trim($title) == '') $title = $Proj->forms[$form_name]['menu'];
				// Render row
				$rows .= RCView::tr(array('id'=>"sqtr-$survey_id-$event_id", $queue_item_active_flag=>$queue_item_active_flag_value),
							RCView::td(array('class'=>"data $queue_item_class_firstcell", 'valign'=>'top', 'style'=>'text-align:center;padding:6px;padding-top:10px;'),
								// "Enabled" text/icon
								RCView::div(array('id'=>"div_sq_icon_enabled-$survey_id-$event_id", 'style'=>$queue_item_icon_enabled_style),
									RCView::img(array('src'=>'checkbox_checked.png')) .
									RCView::div(array('style'=>'color:green;'), $lang['survey_544']) .
									RCView::div(array('style'=>'padding:20px 0 0;'),
										RCView::button(array('class'=>'jqbuttonsm', 'style'=>'font-size:9px;font-family:tahoma;', 
											'onclick'=>"surveyQueueSetupActivate(0, $survey_id, $event_id);return false;"),  
											$lang['survey_546']
										)
									)
								) .
								// "Not enabled" text/icon
								RCView::div(array('id'=>"div_sq_icon_disabled-$survey_id-$event_id", 'style'=>$queue_item_icon_disabled_style),
									RCView::img(array('src'=>'checkbox_cross.png')) .
									RCView::div(array('style'=>'color:#F47F6C;'), $lang['survey_543']) .
									RCView::div(array('style'=>'padding:20px 0 0;'),
										RCView::button(array('class'=>'jqbuttonsm', 'style'=>'font-size:9px;font-family:tahoma;', 
											'onclick'=>"surveyQueueSetupActivate(1, $survey_id, $event_id);return false;"),
											$lang['survey_547']
										)
									)
								) .
								// Hidden checkbox to denote activation
								RCView::checkbox(array('name'=>"sqactive-$survey_id-$event_id", 'id'=>"sqactive-$survey_id-$event_id", 'class'=>'hidden', $conditionSurveyActivatedChecked=>$conditionSurveyActivatedChecked))
							) . 
							RCView::td(array('class'=>"data $queue_item_class_firstcell", 'style'=>'padding:6px;', 'valign'=>'top'),
								RCView::div(array('style'=>'padding:3px 8px 8px 2px;font-size:13px;'),
									// Survey title
									RCView::span(array('style'=>'font-size:13px;'), 
										'"'.RCView::b(RCView::escape($title)).'"'
									) .
									// Event name (if longitudinal)
									(!$longitudinal ? '' : 
										RCView::span(array(), 
											" &nbsp;-&nbsp; ".RCView::escape($Proj->eventInfo[$event_id]['name_ext'])
										)
									)
								)
							) .
							RCView::td(array('class'=>"data $queue_item_class", 'style'=>'padding:6px 6px 3px;font-size:12px;'),
								// When survey is completed
								RCView::div(array('style'=>'text-indent:-1.9em;margin-left:1.9em;padding:1px 0;'), 
									RCView::checkbox(array('name'=>"sqcondoption-surveycomplete-$survey_id-$event_id",'id'=>"sqcondoption-surveycomplete-$survey_id-$event_id",'class'=>'imgfix2',$conditionSurveyCompChecked=>$conditionSurveyCompChecked, $conditionSurveyActivatedDisabled=>$conditionSurveyActivatedDisabled)) . 
									$lang['survey_419'] . 
									RCView::br() . 
									// Drop-down of surveys/events
									RCView::select(array('name'=>"sqcondoption-surveycompleteids-$survey_id-$event_id",'id'=>"sqcondoption-surveycompleteids-$survey_id-$event_id",'style'=>'font-size:11px;width:360px;max-width:360px;', $conditionSurveyActivatedDisabled=>$conditionSurveyActivatedDisabled,
										'onchange'=>"$('#sqcondoption-surveycomplete-$survey_id-$event_id').prop('checked', (this.value.length > 0) ); hasDependentSurveyEvent(this);"), $surveyDD, $conditionSurveyCompSelected, 200)
								) .   
								// AND/OR drop-down list for conditions
								RCView::div(array('style'=>'padding:2px 0 1px;'), 
									RCView::select(array('name'=>"sqcondoption-andor-$survey_id-$event_id",'id'=>"sqcondoption-andor-$survey_id-$event_id",'style'=>'font-size:11px;', $conditionSurveyActivatedDisabled=>$conditionSurveyActivatedDisabled), array('AND'=>$lang['global_87'],'OR'=>$lang['global_46']), $conditionAndOr)
								) .  
								// When logic becomes true
								RCView::div(array('style'=>'text-indent:-1.9em;margin-left:1.9em;'), 
									RCView::checkbox(array('name'=>"sqcondoption-logic-$survey_id-$event_id",'id'=>"sqcondoption-logic-$survey_id-$event_id",'class'=>'imgfix2',$conditionLogicChecked=>$conditionLogicChecked, $conditionSurveyActivatedDisabled=>$conditionSurveyActivatedDisabled)) . 
									$lang['survey_420'] . 
									RCView::a(array('href'=>'javascript:;','class'=>'opacity65','style'=>'margin-left:50px;text-decoration:underline;font-size:10px;','onclick'=>"helpPopup('logic_functions')"), $lang['survey_527']) .
									RCView::br() . 
									RCView::textarea(array('name'=>"sqcondlogic-$survey_id-$event_id",'id'=>"sqcondlogic-$survey_id-$event_id",'class'=>'x-form-field', 'style'=>'line-height:12px;font-size:11px;width:350px;height:24px;',
										'onblur'=>"this.value=trim(this.value); if(this.value.length > 0) { $('#sqcondoption-logic-$survey_id-$event_id').prop('checked',true); } if(!checkLogicErrors(this.value,1,true)){validate_auto_invite_logic($(this));}"), $conditionLogic
									) .
									RCView::br() . 
									RCView::span(array('style'=>'font-family:tahoma;font-size:10px;color:#888;'), 
										($longitudinal ? "(e.g., [enrollment_arm_1][age] > 30 and [enrollment_arm_1][gender] = \"1\")" : "(e.g., [age] > 30 and [gender] = \"1\")")
									)
								)
							) .
							RCView::td(array('class'=>"data $queue_item_class", 'valign'=>'top', 'style'=>'text-align:center;padding:6px;padding-top:10px;'),
								// Auto start?
								RCView::checkbox(array('name'=>"ssautostart-$survey_id-$event_id", 'id'=>"ssautostart-$survey_id-$event_id", $conditionAutoStartChecked=>$conditionAutoStartChecked, $conditionSurveyActivatedDisabled=>$conditionSurveyActivatedDisabled))
							)
						);
			}
		}
		
		// HTML
		$html = '';
		
		// Instructions
		$html .= RCView::div(array('style'=>'margin:0 0 5px;'.($Proj->firstFormSurveyId != null ? '' : 'margin-bottom:20px;')),
					$lang['survey_531'] . " " .
					RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;', 'onclick'=>"$(this).hide();$('#survey_queue_form_hidden_instr').show();fitDialog($('#surveyQueueSetupDialog'));"), 
						$lang['global_58']) .
					RCView::span(array('id'=>'survey_queue_form_hidden_instr', 'style'=>'display:none;'), 
						$lang['survey_542'])
				);
		
		// If custom text already exists, then display it's textarea box
		$survey_queue_custom_text_style = ($survey_queue_custom_text == '') ? 'display:none;' : '';
		$survey_queue_custom_text_add_style = ($survey_queue_custom_text == '') ? '' : 'display:none;';
		
		// If the first instrument is a survey, explain to user why it's not displayed here
		if ($Proj->firstFormSurveyId != null) {
			$html .= RCView::div(array('style'=>'margin:0 0 20px;font-size:11px;color:#777;'),
						$lang['survey_530']
					);
		}
		
		// Add table/form html if there is something to display
		if (strlen($rows) > 0) 
		{
			// Add table/form html
			$html .= RCView::form(array('id'=>'survey_queue_form'),
						// Header
						RCView::div(array('id'=>'div_survey_queue_custom_text_link', 'style'=>'padding:0 0 20px 4px;'.$survey_queue_custom_text_add_style),
							RCView::img(array('src'=>'add.png', 'class'=>'imgfix')) .
							RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;color:green;', 'onclick'=>"$('#div_survey_queue_custom_text_link').hide();$('#div_survey_queue_custom_text').show('fade');"), 
								$lang['survey_541']
							)
						) .
						// Custom text (optional)
						RCView::div(array('id'=>'div_survey_queue_custom_text', 'class'=>'data', 'style'=>'padding:8px 8px 4px;margin:0 0 15px;'.$survey_queue_custom_text_style),
							RCView::div(array('style'=>'margin:0 0 5px;font-weight:bold;'),
								$lang['survey_537'] . ' &#8211; ' .
								RCView::span(array('style'=>'font-weight:normal;'),
									$lang['survey_538'] . " " .
									RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;', 
										'onclick'=>"simpleDialog('".cleanHtml("\"{$lang['survey_506']} {$lang['survey_511']}\"")."','".cleanHtml("{$lang['survey_538']} {$lang['survey_539']} {$lang['survey_540']}")."',null,600);"), $lang['survey_539']) . " " .
									$lang['survey_540'] .
									RCView::a(array('href'=>'javascript:;', 'style'=>'margin-left:40px;font-family:tahoma;font-size:11px;color:#888;', 'onclick'=>"$('#div_survey_queue_custom_text_link').show('fade');$('#div_survey_queue_custom_text').hide();$('#survey_queue_custom_text').val('');"), 
										'['.$lang['ws_144'].']'
									)
								)
							) .
							RCView::div(array(),
								RCView::textarea(array('name'=>"survey_queue_custom_text",'id'=>"survey_queue_custom_text",'class'=>'x-form-field', 'style'=>'line-height:13px;font-size:12px;width:98%;height:38px;'), 
									$survey_queue_custom_text
								) .
								RCView::div(array('style'=>'float:right;margin:0 10px 0 20px;'),
									RCView::img(array('src'=>'pipe_small.gif', 'class'=>'imgfix')) .
									RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:11px;color:#3E72A8;text-decoration:underline;', 'onclick'=>"pipingExplanation();"), 
										$lang['design_456']
									)
								) .
								RCView::div(array('style'=>'float:right;color:#777;font-size:11px;'),
									$lang['survey_554'] .
									' &lt;b&gt; bold, &lt;u&gt; underline, &lt;i&gt; italics, &lt;a href="..."&gt; link, etc.'
								) .
								RCView::div(array('class'=>'clear'), '')
							)
						) .
						// Table of surveys
						RCView::table(array('cellspacing'=>'0', 'class'=>'form_border', 'style'=>'width:100%;table-layout:fixed;'), 
							$hdrs . $rows
						)
					 );
		} else {
			// No rows to display, so give notice that they can't use the Survey Queue yet
			$html .= 	RCView::div(array('class'=>'yellow', 'style'=>'max-width:100%;margin:20px 0;'),
							RCView::img(array('src'=>'exclamation_orange.png', 'class'=>'imgfix')) .
							RCView::b($lang['global_03'].$lang['colon'])." ".$lang['survey_552']
						);
		}
		
		// Return all html to display
		return $html;
	}


	// Obtain the survey hash for array of participant_id's
	public static function getParticipantHashes($participant_id=array())
	{
		// Collect hashes in array with particpant_id as key
		$hashes = array();	
		// Retrieve hashes
		$sql = "select participant_id, hash from redcap_surveys_participants 
				where participant_id in (".prep_implode($participant_id, false).")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$hashes[$row['participant_id']] = $row['hash'];
		}
		// Return hashes
		return $hashes;
	}

	// Create a new survey participant for followup survey (email will be '' and not null)
	// Return participant_id (set $forceInsert=true to bypass the Select query if we already know it doesn't exist yet)
	public static function getFollowupSurveyParticipantIdHash($survey_id, $record, $event_id=null, $forceInsert=false)
	{
		// Make sure record isn't blank
		if ($record == '') return false;
		// Check event_id
		if (!is_numeric($event_id)) return false;
		// Set flag to perform the insert query
		if ($forceInsert) {
			$doInsert = true;
		}
		// Check if participant_id for this event-record-survey exists yet
		else {
			$sql = "select p.participant_id, p.hash from redcap_surveys_participants p, redcap_surveys_response r 	
					where p.survey_id = $survey_id and p.participant_id = r.participant_id
					and p.event_id = $event_id and p.participant_email is not null
					and r.record = '".prep($record)."' limit 1";
			$q = db_query($sql);
			// If participant_id exists, then return it
			if (db_num_rows($q) > 0) {
				$participant_id = db_result($q, 0, 'participant_id');
				$hash = db_result($q, 0, 'hash');
			} else {
				$doInsert = true;
			}
		}
		// Create placeholder in participants and response tables
		if ($doInsert) {
			// Generate random hash
			$hash = self::getUniqueHash();
			// Since participant_id does NOT exist yet, create it. 
			$sql = "insert into redcap_surveys_participants (survey_id, event_id, participant_email, participant_identifier, hash) 
					values ($survey_id, $event_id, '', null, '$hash')";
			if (!db_query($sql)) return false;
			$participant_id = db_insert_id();	
			// Now place empty record in surveys_responses table to complete this process (sets first_submit_time as NULL - very crucial for followup)
			$sql = "insert into redcap_surveys_response (participant_id, record) values ($participant_id, '".prep($record)."')";
			if (!db_query($sql)) {
				// If query failed (likely to the fact that it already exists, which it shouldn't), then undo
				db_query("delete from redcap_surveys_participants where participant_id = $participant_id");
				// If $forceInsert flag was to true, then try with it set to false (in case there was a mistaken determining that this placeholder existed already)
				if (!$forceInsert) {
					return false;
				} else {
					// Run recursively with $forceInsert=false
					return self::getFollowupSurveyParticipantIdHash($survey_id, $record, $event_id);
				}
			}
		}
		// Return nothing if could not store hash
		return array($participant_id, $hash);
	}

	// Creates unique return_code (that is, unique within that survey) and returns that value
	public static function getUniqueReturnCode($survey_id=null, $response_id=null)
	{
		// Make sure we have a survey_id value
		if (!is_numeric($survey_id)) return false;
		// If response_id is provided, then fetch existing return code. If doesn't have a return code, then generate one.
		if (is_numeric($response_id))
		{
			// Query to get existing return code
			$sql = "select r.return_code from redcap_surveys_participants p, redcap_surveys_response r 
					where p.survey_id = $survey_id and r.response_id = $response_id 
					and p.participant_id = r.participant_id limit 1";
			$q = db_query($sql);
			$existingCode = (db_num_rows($q) > 0) ? db_result($q, 0) : "";
			if ($existingCode != "") {
				return strtoupper($existingCode);
			}
		}
		// Generate a new unique return code for this survey (keep looping till we get a non-existing unique value)
		do {
			// Generate a new random hash
			$code = strtolower(generateRandomHash(8, false, true));
			// Ensure that the hash doesn't already exist
			$sql = "select r.return_code from redcap_surveys_participants p, redcap_surveys_response r 
					where p.survey_id = $survey_id and r.return_code = '$code'
					and p.participant_id = r.participant_id limit 1";
			$q = db_query($sql);
			$codeExists = (db_num_rows($q) > 0);
		} 
		while ($codeExists);
		// If the response_id provided does not have an existing code, then save the new one we just generated
		if (is_numeric($response_id) && $existingCode == "")
		{
			$sql = "update redcap_surveys_response set return_code = '$code' where response_id = $response_id";
			$q = db_query($sql);
		}
		// Code is unique, so return it
		return strtoupper($code);
	}
	
	
	// Obtain survey return code for record-instrument[-event]
	public static function getSurveyReturnCode($record='', $instrument='', $event_id='')
	{
		global $longitudinal, $Proj;
		// Return NULL if no record name or not instrument name
		if ($record == '' || $instrument == '') return null;
		// If a longitudinal project and no event_id is provided, return null
		if ($longitudinal && !is_numeric($event_id)) return null;
		// If a non-longitudinal project, then set event_id automatically 
		if (!$longitudinal) $event_id = $Proj->firstEventId;
		// If instrument is not a survey, return null
		if (!isset($Proj->forms[$instrument]['survey_id'])) return null;
		// Get survey_id
		$survey_id = $Proj->forms[$instrument]['survey_id'];
		// If "Save & Return Later" is not enabled, then return null
		if (!$Proj->surveys[$survey_id]['save_and_return'] && !self::surveyLoginEnabled()) return null;
		// Check if return code exists already
		$sql = "select r.response_id, r.return_code from redcap_surveys_participants p, redcap_surveys_response r 
				where p.survey_id = $survey_id and p.participant_id = r.participant_id 
				and record = '".prep($record)."' and p.event_id = $event_id 
				order by p.participant_email desc limit 1";
		$q = db_query($sql);
		if (db_num_rows($q) > 0) {
			// Get return code that already exists in table
			$return_code = db_result($q, 0, 'return_code');
			$response_id = db_result($q, 0, 'response_id');
			// If code is blank, then try to generate a return code
			if ($return_code == '') {
				$return_code = self::getUniqueReturnCode($survey_id, $response_id);
			}
		} else {
			// Make sure the record exists first, else return null
			if (!recordExists($record)) return null;
			// Create new row in response table
			self::getFollowupSurveyParticipantIdHash($survey_id, $record, $event_id);
			// Row now exists in response table, but it has no return code, so recursively re-run this method to generate it.
			return self::getSurveyReturnCode($record, $instrument, $event_id);
		}
		// Return the code
		return ($return_code == '' ? null : strtoupper($return_code));
	}


	// Obtain the survey hash for specified event_id (return public survey hash if participant_id is not provided)
	public static function getSurveyHash($survey_id, $event_id = null, $participant_id=null)
	{
		global $Proj;
		
		// Check event_id (use first event_id in project if not provided)
		if (!is_numeric($event_id)) $event_id = $Proj->firstEventId;
		
		// Retrieve hash ("participant_email=null" means it's a public survey)
		$sql = "select hash from redcap_surveys_participants where survey_id = $survey_id and event_id = $event_id ";
		if (!is_numeric($participant_id)) {
			// Public survey
			$sql .= "and participant_email is null ";
		} else {
			// Specific participant
			$sql .= "and participant_id = $participant_id ";
		}
		$sql .= "order by participant_id limit 1";
		$q = db_query($sql);
		
		// Hash exists
		if (db_num_rows($q) > 0) {
			$hash = db_result($q, 0);
		}
		// Create hash
		else {
			$hash = self::setHash($survey_id, null, $event_id, null, (!is_numeric($participant_id)));
		}
		
		return $hash;
	}
	

	// Create a new survey hash [for current arm] 
	public static function setHash($survey_id, $participant_email=null, $event_id=null, $identifier=null, 
								   $isPublicSurvey=false, $phone="", $delivery_preference="")
	{
		// Check event_id
		if (!is_numeric($event_id)) return false;
		
		// Set string for email (null = public survey
		$sql_participant_email = ($participant_email === null) ? "null" : "'" . prep($participant_email) . "'";
		
		// Create unique hash
		$hash = self::getUniqueHash(10, $isPublicSurvey);
		$sql = "insert into redcap_surveys_participants (survey_id, event_id, participant_email, participant_phone, participant_identifier, hash, delivery_preference) 
				values ($survey_id, $event_id, $sql_participant_email, " . checkNull($phone) . ", " . checkNull($identifier) . ", '$hash', " . checkNull($delivery_preference) . ")";
		$q = db_query($sql);
		
		// Return nothing if could not store hash
		return ($q ? $hash : "");
	}
	

	// Creates unique hash after checking current hashes in tables, and returns that value
	function getUniqueHash($hash_length=10, $isPublicSurvey=false)
	{
		do {
			// Generate a new random hash
			$hash = generateRandomHash($hash_length, false, $isPublicSurvey);
			// Ensure that the hash doesn't already exist in either redcap_surveys or redcap_surveys_hash (both tables keep a hash value)
			$sql = "select hash from redcap_surveys_participants where hash = '$hash' limit 1";
			$hashExists = (db_num_rows(db_query($sql)) > 0);
		} while ($hashExists);
		// Hash is unique, so return it
		return $hash;
	}
	
	
	// Return boolean for if Survey Login is enabled
	public static function surveyLoginEnabled()
	{
		global $survey_auth_enabled;
		return ($survey_auth_enabled == '1');
	}
	

	// Survey Login: Display survey login form for respondent to log in
	public static function getSurveyLoginForm($record=null, $surveyLoginFailed=false, $surveyTitle=null)
	{
		global $survey_auth_field1, $survey_auth_event_id1, $survey_auth_field2, $survey_auth_event_id2, 
			   $survey_auth_field3, $survey_auth_event_id3, $survey_auth_min_fields, $longitudinal, 
			   $survey_auth_custom_message, $Proj, $lang;
		// Put html in $html
		$html = $rows = "";
		// Set array of fields/events
		$surveyLoginFieldsEvents = self::getSurveyLoginFieldsEvents();
		// Count auth fields
		$auth_field_count = count($surveyLoginFieldsEvents);
		
		// If record already exists, then retrieve its data to see if we need to display all fields in login form.
		if ($record != '' && $auth_field_count > $survey_auth_min_fields ) {
			$data_fields = $data_events = array();
			foreach ($surveyLoginFieldsEvents as $fieldEvent) {
				$data_fields[] = $fieldEvent['field'];
				$data_events[] = $fieldEvent['event_id'];				
			}
			// Get data for record
			$survey_login_data = Records::getData('array', $record, $data_fields, $data_events);
			// Loop through fields again and REMOVE any where the value is empty for this record
			foreach ($surveyLoginFieldsEvents as $key=>$fieldEvent) {
				if (isset($survey_login_data[$record][$fieldEvent['event_id']][$fieldEvent['field']]) 
					&& $survey_login_data[$record][$fieldEvent['event_id']][$fieldEvent['field']] == '' ) {
					// Remove the field
					unset($surveyLoginFieldsEvents[$key]);
					$auth_field_count--;
				}			
			}
		}
		
		// Loop through array of login fields
		foreach ($surveyLoginFieldsEvents as $fieldEvent) 
		{
			// Get field and event_id
			$survey_auth_field_variable = $fieldEvent['field'];
			$survey_auth_event_id_variable = $fieldEvent['event_id'];
			// Set some attributes
			$dformat = $width = $onblur = "";
			$val_type = $Proj->metadata[$survey_auth_field_variable]['element_validation_type'];
			if ($val_type != '') {
				$onblur = "redcap_validate(this,'','','soft_typed','$val_type',1);";
				// Adjust size for date/time fields
				if ($val_type == 'time' || substr($val_type, 0, 4) == 'date' || substr($val_type, 0, 5) == 'date_') {
					$dformat = RCView::span(array('class'=>'df'), MetaData::getDateFormatDisplay($val_type));
					$width = "width:".MetaData::getDateFieldWidth($val_type).";";
				}
			}
			$field_note = "";
			if ($Proj->metadata[$survey_auth_field_variable]['element_note'] != "") {
				$field_note = RCView::div(array('class'=>'note', 'style'=>'width:100%;'), $Proj->metadata[$survey_auth_field_variable]['element_note']);
			}
			// Add row
			$rows .= RCView::tr(array(),
						RCView::td(array('valign'=>'top', 'class'=>'label', 'style'=>'font-size:14px;'),
							RCView::escape($Proj->metadata[$survey_auth_field_variable]['element_label'])
						) .
						RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'width:320px;'),
							RCView::text(array('name'=>$survey_auth_field_variable, 'class'=>"x-form-text x-form-field $val_type", 
								'onblur'=>$onblur, 'size'=>'30', 'style'=>$width), '') .
							$dformat .
							$field_note
						)
					);
		}
		// Instructions
		$numOutOfNum = ($survey_auth_min_fields < $auth_field_count) 
			? "{$lang['survey_575']} $survey_auth_min_fields {$lang['survey_576']} $auth_field_count {$lang['survey_577']}"
			: ($auth_field_count > 1 ? $lang['survey_578'] : $lang['survey_587']);
		$html .= RCView::p(array('style'=>'font-size:14px;margin:5px 0 15px;color:#800000;'), 
					$lang['survey_310'] . " \"" . RCView::b(RCView::escape($surveyTitle)) . "\""
				);
		$html .= RCView::p(array('style'=>'font-size:14px;margin:5px 0 15px;'), 
					$lang['survey_574'] . " " .
					RCView::b($numOutOfNum) . " " . $lang['survey_594']
				);
		// If previous login attempt failed, then display error message
		if ($surveyLoginFailed === true) {
			// Display default error message
			$html .= RCView::div(array('class'=>'red survey-login-error-msg', 'style'=>'margin:0 0 20px;'), 
						RCView::img(array('src'=>'exclamation.png', 'class'=>'imgfix')) .
						RCView::b($lang['global_01'] . $lang['colon']) . " " . 
						($survey_auth_min_fields == '1' ? $lang['survey_580'] : $lang['survey_579']) .
						// Display custom message (if set)
						(trim($survey_auth_custom_message) == '' ? '' :
							RCView::div(array('style'=>'margin:10px 0 0;'), 
								nl2br(filter_tags(br2nl(trim($survey_auth_custom_message))))
							)
						)
					);
		}
		// If there are no fields to display (most likely because the participant has no data for the required fields),
		// then display an error message explaining this.
		if ($rows == '') {
			// Display default error message
			$html .= RCView::div(array('class'=>'red survey-login-error-msg', 'style'=>'margin:0 0 20px;'), 
						RCView::img(array('src'=>'exclamation.png', 'class'=>'imgfix')) .
						RCView::b($lang['global_01'] . $lang['colon']) . " " . 
						$lang['survey_589'] .
						// Display custom message (if set)
						(trim($survey_auth_custom_message) == '' ? '' :
							RCView::div(array('style'=>'margin:10px 0 0;'), 
								nl2br(filter_tags(br2nl(trim($survey_auth_custom_message))))
							)
						)
					);
		}
		// Add form and table
		$html .= RCView::form(array('id'=>'survey_auth_form', 'action'=>$_SERVER['REQUEST_URI'], 'enctype'=>'multipart/form-data', 'target'=>'_self', 'method'=>'post'),
					RCView::table(array('cellspacing'=>0, 'class'=>'form_border'), $rows) .
					// Hidden input to denote this specific action
					RCView::hidden(array('name'=>'survey-auth-submit'), '1')
				);
		// Return html
		return RCView::div(array('id'=>'survey_login_dialog', 'class'=>'simpleDialog', 'style'=>'margin-bottom:10px;'), $html);
	}
	
	
	// Get list of Text variables in project that can be used for Survey Login fields that can
	// be used as options in a drop-down.
	public static function getTextFieldsForDropDown()
	{
		global $Proj, $lang;
		// Build an array of drop-down options listing all REDCap fields
		$rc_fields = array(''=>'-- '.$lang['random_02'].' --');
		foreach ($Proj->metadata as $this_field=>$attr1) {
			// Text fields only
			if ($attr1['element_type'] != 'text') continue;
			// Add to fields/forms array. Get form of field.
			$this_form_label = $Proj->forms[$attr1['form_name']]['menu'];
			// Truncate label if long
			if (strlen($attr1['element_label']) > 65) {
				$attr1['element_label'] = trim(substr($attr1['element_label'], 0, 47)) . "... " . trim(substr($attr1['element_label'], -15));
			}
			$rc_fields[$this_form_label][$this_field] = "$this_field \"{$attr1['element_label']}\"";
		}
		// Return all options
		return $rc_fields;
	}
	
	
	// Return array of field name and event_id of the survey auth fields (up to 3)
	public static function getSurveyLoginFieldsEvents()
	{
		global $survey_auth_field1, $survey_auth_event_id1, $survey_auth_field2, $survey_auth_event_id2, 
			   $survey_auth_field3, $survey_auth_event_id3, $Proj;
		// Set array of fields
		$survey_auth_fields = array(1, 2, 3);
		$loginFieldsEvents = array();
		// Loop through array of login fields
		foreach ($survey_auth_fields as $num) 
		{
			// Get global variable for this login field 
			$survey_auth_field = 'survey_auth_field'.$num;
			$survey_auth_event_id = 'survey_auth_event_id'.$num;
			$survey_auth_field_variable = $$survey_auth_field;
			$survey_auth_event_id_variable = $$survey_auth_event_id;
			if ($survey_auth_field_variable != '' && isset($Proj->metadata[$survey_auth_field_variable])) {
				// Make sure event_id is valid, else default to first event_id
				if (!isset($Proj->eventInfo[$survey_auth_event_id_variable])) $survey_auth_event_id_variable = $Proj->firstEventId;
				// Add to array
				$loginFieldsEvents[] = array('field'=>$survey_auth_field_variable, 'event_id'=>$survey_auth_event_id_variable);
			}
		}
		// Return array
		return $loginFieldsEvents;
	}
	
	
	// Return boolean if the "check survey login failed attempts" is enabled
	public static function surveyLoginFailedAttemptsEnabled()
	{
		global $survey_auth_fail_limit, $survey_auth_fail_window;
		return (is_numeric($survey_auth_fail_limit) && $survey_auth_fail_limit > 0 && is_numeric($survey_auth_fail_window) && $survey_auth_fail_window > 0);	
	}
	
	
	// Return the auto-logout time (in minutes) for Survey Login (based on $autologout_timer for REDCap sessions). Default = 30 if not set.
	public static function getSurveyLoginAutoLogoutTimer() 
	{
		global $autologout_timer;
		return ($autologout_timer == "0" || !is_numeric($autologout_timer)) ? 30 : $autologout_timer;
	}
	
	
	// Generate or retrieve a Survey Access Codes for multiple participants and return array with
	// participant_id's as key and access code as value.
	public static function getAccessCodes($participant_ids=array())
	{
		if (!is_array($participant_ids)) return false;	
		// Query to see if Survey Access Code has already been generated
		$partIdsAccessCodes = array();
		$sql = "select participant_id, access_code from redcap_surveys_participants 
				where participant_id in (".prep_implode($participant_ids).")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// If access code is null, then generate it
			if ($row['access_code'] == '') {
				$partIdsAccessCodes[$row['participant_id']] = self::getAccessCode($row['participant_id'], false, true);
			} else {
				$partIdsAccessCodes[$row['participant_id']] = $row['access_code'];
			}
		}
		// Return array
		return $partIdsAccessCodes;
	}
	
	
	// Generate a new Survey Access Code (or retrieve existing one) OR generate new Short Code
	public static function getAccessCode($participant_id, $shortCode=false, $forceGenerate=false, $return_numeral=false)
	{
		if (!is_numeric($participant_id)) return false;
		if (!$shortCode) {
			## SURVEY ACCESS CODE
			// Determine access code's column name in db table
			$code_colname = ($return_numeral) ? "access_code_numeral" : "access_code";
			// Query to see if Survey Access Code has already been generated
			if (!$forceGenerate) {
				$sql = "select $code_colname from redcap_surveys_participants where participant_id = $participant_id 
						and $code_colname is not null limit 1";
				$q = db_query($sql);
			}
			if (!$forceGenerate && db_num_rows($q)) {
				// Get existing code
				$code = db_result($q, 0);
			} else {
				// Generate random non-existing code		
				do {
					// Generate a new random code
					if ($return_numeral) {
						$code = sprintf("%010d", mt_rand(0, 9999999999));
					} else {
						$code = generateRandomHash(9, false, true);
					}
					// Ensure that the code doesn't already exist in the table
					$sql = "select $code_colname from redcap_surveys_participants where $code_colname = '".prep($code)."' limit 1";
					$codeExists = (db_num_rows(db_query($sql)) > 0);
				} while ($codeExists);
				// Add code to table
				$sql = "update redcap_surveys_participants set $code_colname = '".prep($code)."' where participant_id = $participant_id";
				if (!db_query($sql)) return false;
			}
		} else {
			## SHORT CODE
			// Generate random non-existing code		
			do {
				// Generate a new random code
				$code = generateRandomHash(2, false, true, true) . sprintf("%03d", mt_rand(0, 999));
				// Ensure that the code doesn't already exist in the table
				$sql = "select code from redcap_surveys_short_codes where code = '".prep($code)."' limit 1";
				$codeExists = (db_num_rows(db_query($sql)) > 0);
			} while ($codeExists);
			// Add code to table
			$sql = "insert into redcap_surveys_short_codes (ts, code, participant_id) values
					('".NOW."', '".prep($code)."', $participant_id)";
			if (!db_query($sql)) return false;
		}
		// Code is unique, so return it
		return $code;
	}
	
	
	// Validate the Survey Code and redirect to the survey
	public static function validateAccessCodeForm($code)
	{
		global $redcap_version;
		// Get length of code
		$code_length = strlen($code);
		// Is a short code?
		$isShortCode = ($code_length == self::SHORT_CODE_LENGTH && preg_match("/^[A-Za-z0-9]+$/", $code));
		// Is an access code?
		$isAccessCode = ($code_length == self::ACCESS_CODE_LENGTH && preg_match("/^[A-Za-z0-9]+$/", $code));
		// Is a numeral access code?
		$isNumeralAccessCode = ($code_length == self::ACCESS_CODE_NUMERAL_LENGTH && is_numeric($code));
		// Is a numeral access code beginning with "V", which denotes that Twilio should call them?		
		$lengthPrependAccessCodeNumeral = strlen(self::PREPEND_ACCESS_CODE_NUMERAL);
		$isNumeralAccessCodeReceiveCall = (!$isShortCode && !$isAccessCode && !$isNumeralAccessCode 
			&& $code_length == (self::ACCESS_CODE_NUMERAL_LENGTH + $lengthPrependAccessCodeNumeral) 
			&& strtolower(substr($code, 0, $lengthPrependAccessCodeNumeral)) == strtolower(self::PREPEND_ACCESS_CODE_NUMERAL)
			&& is_numeric(substr($code, $lengthPrependAccessCodeNumeral)));
		if ($isNumeralAccessCodeReceiveCall) {
			$code = substr($code, $lengthPrependAccessCodeNumeral);
			$isNumeralAccessCode = true;
		}
		// If not a valid code based on length or content alone, then stop
		if (!$isShortCode && !$isAccessCode && !$isNumeralAccessCode && !$isNumeralAccessCodeReceiveCall) return false;
		// Determine if Short Code or normal Access Code
		if ($isShortCode) {
			## SHORT CODE		
			// Get timestamp older than X minutes
			$xMinAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-Survey::SHORT_CODE_EXPIRE,date("s"),date("m"),date("d"),date("Y")));	
			$sql = "select p.hash from redcap_surveys_participants p, redcap_surveys_short_codes c
					where p.participant_id = c.participant_id and c.code = '".prep($code)."' and c.ts > '$xMinAgo' limit 1";
			$q = db_query($sql);
			if (db_num_rows($q) == 0) return false;
			$hash = db_result($q, 0);
			// Now remove the code since it only gets used once
			$sql = "delete from redcap_surveys_short_codes where code = '".prep($code)."' limit 1";
			db_query($sql);
		} elseif (!$isShortCode) {
			## SURVEY ACCESS CODE
			$sql = "select hash from redcap_surveys_participants where "
				 . ($isNumeralAccessCode ? "access_code_numeral = '".prep($code)."'" : "access_code = '".prep($code)."'");
			$q = db_query($sql);
			if (db_num_rows($q) == 0) return false;
			$hash = db_result($q, 0, 'hash');
			// If user submitted code in order to receive phone call, then initiate survey by calling them
			if ($isNumeralAccessCodeReceiveCall && isset($_POST['From'])) {
				// Redirect to the correct page to make the call to the respondent
				redirect(APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/" .
						"Surveys/twilio_initiate_call_sms.php?s=$hash&action=init&delivery_type=VOICE_INITIATE&phone=".$_POST['From']);
			}
		}
		// Return hash
		return $hash;
	}
	
	
	// Return array of available delivery methods for surveys (e.g. email, sms_invite, voice_initiate, sms_initiate).
	// To be used as drop-down list options.
	public static function getDeliveryMethods($addParticipantPrefOption=false, $addDropdownGroups=false, $appendPreferenceTextToOption=null, $addEmailOption=true)
	{
		global $lang, $twilio_enabled, $twilio_option_voice_initiate, $twilio_option_sms_initiate, 
			   $twilio_option_sms_invite_make_call, $twilio_option_sms_invite_receive_call;
		// Add array of delivery methods (email by default)
		$delivery_methods = array();
		// Email option
		if ($addEmailOption) {
			$delivery_methods[$lang['survey_804']]['EMAIL'] = $lang['survey_688'] .
										 ($appendPreferenceTextToOption == 'EMAIL' ? " " . $lang['survey_782'] : '');
		}
		// If using Twilio, add the SMS/Voice choices
		if ($twilio_enabled) {
			if ($twilio_option_sms_initiate) {
				$delivery_methods[$lang['survey_803']]['SMS_INITIATE'] = $lang['survey_767'] .
													($appendPreferenceTextToOption == 'SMS_INITIATE' ? " " . $lang['survey_782'] : '');
			}
			if ($twilio_option_voice_initiate) {
				$delivery_methods[$lang['survey_802']]['VOICE_INITIATE'] = $lang['survey_884'] .
													  ($appendPreferenceTextToOption == 'VOICE_INITIATE' ? " " . $lang['survey_782'] : '');
			}
			if ($twilio_option_sms_invite_make_call) {
				$delivery_methods[$lang['survey_802']]['SMS_INVITE_MAKE_CALL'] = $lang['survey_690'] .
												  ($appendPreferenceTextToOption == 'SMS_INVITE_MAKE_CALL' ? " " . $lang['survey_782'] : '');
			}
			if ($twilio_option_sms_invite_receive_call) {
				$delivery_methods[$lang['survey_802']]['SMS_INVITE_RECEIVE_CALL'] = $lang['survey_801'] .
												  ($appendPreferenceTextToOption == 'SMS_INVITE_RECEIVE_CALL' ? " " . $lang['survey_782'] : '');
			}
		}
		// Add participant's preference as option?
		if ($addParticipantPrefOption) {
			$delivery_methods[$lang['survey_805']]['PARTICIPANT_PREF'] = $lang['survey_768'];
		}
		// If we're not adding the optgroups, then remove them
		if (!$addDropdownGroups) {
			$delivery_methods2 = array();
			foreach ($delivery_methods as $key=>$attr) {
				if (is_array($attr)) {
					foreach ($attr as $key2=>$attr2) {
						$delivery_methods2[$key2] = $attr2;
					}
				} else {
					$delivery_methods2[$key] = $attr;
				}
			}
			$delivery_methods = $delivery_methods2;
		}
		// Return array
		return $delivery_methods;
	}
	
	
	// Display the Survey Code form for entering the code
	public static function displayAccessCodeForm($displayErrorMsg=false)
	{
		global $lang;
		return 	RCView::form(array('id'=>'survey_code_form', 'style'=>'font-weight:bold;margin:0 0 10px;font-size:16px;', 'action'=>$_SERVER['REQUEST_URI'], 'enctype'=>'multipart/form-data', 'target'=>'_self', 'method'=>'post'),
					RCView::div(array('style'=>'margin:-32px 0 5px;text-align:right;'),
						RCView::a(array('href'=>'http://projectredcap.org/', 'target'=>'_blank'),
							RCView::img(array('src'=>'redcaplogo_small2.gif'))
						)
					) . 
					RCView::div(array(),
						$lang['survey_619'] .
						RCView::text(array('name'=>'code', 'maxlength'=>'20', 'class'=>'x-form-text x-form-field', 'style'=>'margin:0 4px 0 10px;font-size:16px;width:120px;padding:4px 6px;')) .
						RCView::button(array('class'=>'jqbutton', 'onclick'=>"
							var ob = $('input[name=\"code\"]');
							ob.val( trim(ob.val()) );
							if (ob.val() == '') {
								simpleDialog('".cleanHtml($lang['survey_634'])."');
								return false;
							}
							$('#survey_code_form').submit();
						"), $lang['survey_200'])
					) .
					// Error msg
					(!$displayErrorMsg ? '' :
						RCView::div(array('class'=>'red', 'style'=>'font-size:14px;margin-top:20px;padding:10px 15px 12px;'),
							RCView::img(array('src'=>'exclamation.png', 'class'=>'imgfix')) . 
							RCView::b($lang['global_01'].$lang['colon']) . " " . $lang['survey_622']
						)
					) .
					RCView::div(array('style'=>'font-size:14px;font-weight:normal;color:#777;margin-top:30px;'),
						$lang['survey_642']
					)
				) .
				"<style type='text/css'>
				div#footer { display:none; }
				</style>
				<script type='text/javascript'>
				$(function(){
					$('input[name=\"code\"]').focus();
				});
				</script>";
	}
	

	// OBTAIN SHORT URL VIA BIT.LY API	
	public static function getShortUrl($original_url)
	{
		// URL shortening service
		$service = "j.mp";
		// Set parameters for URL shortener service
		$serviceurl = "http://api.bit.ly/v3/shorten?domain=$service&format=txt&login=projectredcap&apiKey=R_6952a44cd93f2c200047bb81cf3dbb71&longUrl=";
		$urlbase	= "http://$service/";	
		// Retrieve shortened URL from URL shortener service
		$shorturl = trim(http_get($serviceurl . urlencode($original_url)));
		// Ensure that we received a link in the expected format
		if (!empty($shorturl) && substr($shorturl, 0, strlen($urlbase)) == $urlbase) {
			// Output
			return $shorturl;
		}
		// On error, return false
		return false;
	}
	

	// OBTAIN HTML ICON FOR A GIVEN SMS/VOICE DELIVERY PREFERENCE IN THE PARTICIPANT LIST
	public static function getDeliveryPrefIcon($delivery_pref)
	{
		global $lang;
		// Deliever preference
		if ($delivery_pref == 'VOICE_INITIATE') {
			$deliv_pref_icon = RCView::img(array('src'=>'phone.gif', 'class'=>'imgfix1', 'title'=>$lang['survey_884']));
		} else if ($delivery_pref == 'SMS_INITIATE') {
			$deliv_pref_icon = RCView::img(array('src'=>'balloons_box.png', 'class'=>'imgfix1', 'title'=>$lang['survey_767']));
		} else if ($delivery_pref == 'SMS_INVITE_MAKE_CALL') {
			$deliv_pref_icon = RCView::img(array('src'=>'balloon_phone.gif', 'class'=>'imgfix1', 'title'=>$lang['survey_690']));
		} else if ($delivery_pref == 'SMS_INVITE_RECEIVE_CALL') {
			$deliv_pref_icon = RCView::img(array('src'=>'balloon_phone_receive.gif', 'class'=>'imgfix1', 'title'=>$lang['survey_801']));
		} else {
			$deliv_pref_icon = RCView::img(array('src'=>'email.png', 'class'=>'imgfix1', 'title'=>$lang['global_33']));
		}
		return $deliv_pref_icon;
	}
	
}
