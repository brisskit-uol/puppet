<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
 
// Default response
$response = "0";


## DISPLAY DIALOG PROMPT
if (isset($_POST['setting']) && isset($_POST['action']) && $_POST['action'] == 'view')
{
	## Display different messages for different settings
	
	// Survey participant email address field
	if ($_POST['setting'] == 'survey_email_participant_field') 
	{
		// Collect all email-validated fields and their labels into an array
		$emailFieldsLabels = array(''=>'--- '.$lang['random_02'].' ---');
		foreach ($Proj->metadata as $field=>$attr) {
			if ($attr['element_validation_type'] == 'email') {
				$emailFieldsLabels[$field] = "$field (\"{$attr['element_label']}\")";
			}
		}
		// Set dialog content
		$response = RCView::div(array(),
						$lang['setup_114'] . "<br><br>" . $lang['setup_122'] . RCView::br() . RCView::br() .
						RCView::span(array('style'=>'color:#C00000;'), $lang['setup_133']) . RCView::br() . RCView::br() .
						RCView::b($lang['global_02'].$lang['colon']) . " " . $lang['setup_115'] . RCView::br() . RCView::br() .
						RCView::b($lang['setup_116']) . RCView::br() .
						RCView::select(array('style'=>'width:70%;','id'=>'surveyPartEmailFieldName'), $emailFieldsLabels, '', 300)
					);
	}
	
	// Twilio voice/SMS services
	if ($_POST['setting'] == 'twilio_enabled') 
	{
		// Set checkbox settings
		$twilio_option_voice_initiate_chk = ($twilio_option_voice_initiate) ? "checked" : "";
		$twilio_option_sms_initiate_chk = ($twilio_option_sms_initiate) ? "checked" : "";
		$twilio_option_sms_invite_make_call_chk = ($twilio_option_sms_invite_make_call) ? "checked" : "";
		$twilio_option_sms_invite_receive_call_chk = ($twilio_option_sms_invite_receive_call) ? "checked" : "";
		// Collect all email-validated fields and their labels into an array
		$phoneFieldsLabels = array(''=>'--- '.$lang['random_02'].' ---');
		foreach ($Proj->metadata as $field=>$attr) {
			// Allow integers and U.S. phone numbers
			if ($attr['element_validation_type'] == 'phone' || $attr['element_validation_type'] == 'int') {
				$phoneFieldsLabels[$field] = "$field (\"{$attr['element_label']}\")";
			}
		}
		// Disable the items in the popup?
		$twilio_setup_dialog_disabled = (!($twilio_pre_enabled && (SUPER_USER || (!$twilio_enabled_by_super_users_only && $user_rights['design']))));
		$twilio_setup_element_disabled = ($twilio_setup_dialog_disabled ? "disabled" : "");
		// Set dialog content
		$response = RCView::div(array(),
						// Note for non-super users
						(!$twilio_setup_dialog_disabled ? '' :
							RCView::div(array('class'=>'red', 'style'=>'max-width:1000px;border-color:#C00000;margin:0 0 10px;line-height: 14px;'),
								RCView::b($lang['global_03'].$lang['colon']) . "  " . 
								($twilio_enabled_by_super_users_only ? $lang['survey_844'] : ($user_rights['design'] ? $lang['survey_915'] : $lang['survey_914']))
							)
						) .
						// Instructions
						RCView::div(array('style'=>'margin:0 0 10px;line-height:14px;'),
							$lang['survey_845'] . " " .
							RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;', 'onclick'=>"
								$(this).hide();
								$('#twilio_setup_instr').show('fade',function(){
									fitDialog($('#TwilioEnableDialog'));
									$('#TwilioEnableDialog').dialog('option', 'position', { my: 'center', at: 'center', of: window });
								});
							"),
								$lang['survey_846']
							)
						) .
						RCView::div(array('id'=>'twilio_setup_instr', 'style'=>'display:none;margin:0 0 15px;line-height:14px;'),
							$lang['survey_834'] . " " .
							RCView::a(array('href'=>'https://www.twilio.com', 'target'=>'_blank', 'style'=>'font-size:13px;text-decoration:underline;'),
								"www.twilio.com"
							) . $lang['period'] . " " .
							$lang['survey_835'] .
							RCView::div(array('style'=>'line-height: 11px;font-size:11px;margin-top:8px;color:#800000;'),
								$lang['survey_840']
							)
						) .
						RCView::form(array('id'=>'twilio_setup_form'),
							// Table
							RCView::table(array('cellspacing'=>0, 'class'=>'form_border', 'style'=>'width:100%;'),
								// Enabled?
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'label nowrap '.($twilio_enabled ? 'darkgreen' : 'red'), 'style'=>'padding:6px 10px;'),
										RCView::img(array('src'=>'twilio.gif', 'class'=>'imgfix')) .
										RCView::b($lang['survey_711']) . RCView::SP . RCView::SP
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data '.($twilio_enabled ? 'darkgreen' : 'red'), 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_enabled', $twilio_setup_element_disabled=>$twilio_setup_element_disabled,
											'onchange'=>"enableTwilioRowColor();", 'class'=>'x-form-text x-form-field', 'style'=>'padding-right:0;height:22px;'), 
											array(0=>$lang['global_23'], 1=>$lang['index_30']), $twilio_enabled, 200)
									)
								) . 
								// Header
								RCView::tr(array(),
									RCView::td(array('colspan'=>'2', 'class'=>'header', 'style'=>'padding:6px 10px;'),
										$lang['survey_714']
									)
								) .
								// Account SID
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'label', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_715'])
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::input(array('name'=>'twilio_account_sid', 'type'=>($twilio_setup_dialog_disabled ? 'password' : 'text'),
											$twilio_setup_element_disabled=>$twilio_setup_element_disabled,
											'class'=>'x-form-text x-form-field', 'style'=>($twilio_setup_dialog_disabled ? 'width:210px;' : 'width:260px;'), 'value'=>$twilio_account_sid))
									)
								) .
								// Account Token
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'label', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_716'])
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::input(array('type'=>'password', 'name'=>'twilio_auth_token', $twilio_setup_element_disabled=>$twilio_setup_element_disabled,
											'class'=>'x-form-text x-form-field', 'style'=>'width:210px;', 'value'=>$twilio_auth_token)) .
										($twilio_setup_dialog_disabled ? '' :
											RCView::a(array('href'=>'javascript:;', 'class'=>'cclink', 'style'=>'text-decoration:underline;font-size:7pt;margin-left:5px;', 'onclick'=>"$(this).remove();showTwilioAuthToken();"),
												$lang['survey_720']
											)
										)
									)
								) .
								// "From" Number
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'label', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_718'])
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::text(array('name'=>'twilio_from_number', $twilio_setup_element_disabled=>$twilio_setup_element_disabled,
											'class'=>'x-form-text x-form-field', 'style'=>'width:120px;', 
											'value'=>$twilio_from_number, 'onblur'=>"this.value = this.value.replace(/\D/g,''); redcap_validate(this,'','','soft_typed','integer',1)"))
										
									)
								) .
								// Header (options)
								RCView::tr(array(),
									RCView::td(array('colspan'=>'2', 'class'=>'header', 'style'=>'padding:6px 10px;'),
										$lang['survey_717']
									)
								) .
								// Gender of voice
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'label', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_722'])
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_voice_language', 'class'=>'x-form-text x-form-field', 'style'=>'padding-right:0;height:22px;'), 
											TwilioRC::getDropdownAllLanguages(), $twilio_voice_language)
									)
								) .
								// Survey settings
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'label', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_836']) .
										RCView::div(array('class'=>'cc_info'), 
											$lang['survey_837']
										) .
										RCView::div(array('style'=>'font-weight:normal;margin-top:20px;font-size:11px;color:#800000;text-indent:-0.7em;margin-left:0.7em;line-height:11px;'), 
											"* ".$lang['survey_839']
										)
									) .
									RCView::td(array('id'=>'twilio_options_checkboxes', 'valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;'), 
											RCView::checkbox(array('name'=>'twilio_option_voice_initiate', 'class'=>'imgfix2', $twilio_setup_element_disabled=>$twilio_setup_element_disabled, $twilio_option_voice_initiate_chk=>$twilio_option_voice_initiate_chk)) . $lang['survey_728']
										) .
										RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;'), 
											RCView::checkbox(array('name'=>'twilio_option_sms_initiate', 'class'=>'imgfix2', $twilio_setup_element_disabled=>$twilio_setup_element_disabled, $twilio_option_sms_initiate_chk=>$twilio_option_sms_initiate_chk)) . 
											$lang['survey_729'] . 
											RCView::span(array('style'=>'color:red;font-size:13px;font-weight:bold;'), "*")
										) .
										RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;'), 
											RCView::checkbox(array('name'=>'twilio_option_sms_invite_make_call', 'class'=>'imgfix2', $twilio_setup_element_disabled=>$twilio_setup_element_disabled, $twilio_option_sms_invite_make_call_chk=>$twilio_option_sms_invite_make_call_chk)) . $lang['survey_799']
										) .
										RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;'), 
											RCView::checkbox(array('name'=>'twilio_option_sms_invite_receive_call', 'class'=>'imgfix2', $twilio_setup_element_disabled=>$twilio_setup_element_disabled, $twilio_option_sms_invite_receive_call_chk=>$twilio_option_sms_invite_receive_call_chk)) . $lang['survey_800']
										)
									)
								) .
								// Designated phone field
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'label', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_793']) .
										RCView::div(array('class'=>'cc_info'), 
											$lang['survey_794']
										)
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'survey_phone_participant_field', $twilio_setup_element_disabled=>$twilio_setup_element_disabled,
											'class'=>'x-form-text x-form-field', 'style'=>'max-width:95%;padding-right:0;height:22px;'), 
											$phoneFieldsLabels, $survey_phone_participant_field) .
										RCView::div(array('class'=>'note', 'style'=>'line-height:11px;margin-top:10px;'), $lang['survey_838'])
									)
								)
							)
						)
					);
	}
}


## SAVE TWILIO SETTINGS
elseif (isset($_POST['twilio_enabled']))
{
	if (!(SUPER_USER || (!$twilio_enabled_by_super_users_only && $user_rights['design']))) exit("ERROR: You must be a super user to perform this action!");
	// Get all available Twilio languages
	$allLang = TwilioRC::getAllLanguages();
	// Set values to be saved
	$twilio_enabled = (isset($_POST['twilio_enabled']) && $_POST['twilio_enabled'] == '1') ? '1' : '0';
	$twilio_option_voice_initiate = (isset($_POST['twilio_option_voice_initiate']) && $_POST['twilio_option_voice_initiate'] == 'on') ? '1' : '0';
	$twilio_option_sms_initiate = (isset($_POST['twilio_option_sms_initiate']) && $_POST['twilio_option_sms_initiate'] == 'on') ? '1' : '0';
	$twilio_option_sms_invite_make_call = (isset($_POST['twilio_option_sms_invite_make_call']) && $_POST['twilio_option_sms_invite_make_call'] == 'on') ? '1' : '0';
	$twilio_option_sms_invite_receive_call = (isset($_POST['twilio_option_sms_invite_receive_call']) && $_POST['twilio_option_sms_invite_receive_call'] == 'on') ? '1' : '0';
	$twilio_from_number = (isset($_POST['twilio_from_number']) && is_numeric($_POST['twilio_from_number'])) ? $_POST['twilio_from_number'] : '';
	$twilio_voice_language = (isset($_POST['twilio_voice_language']) && isset($allLang[$_POST['twilio_voice_language']])) ? $_POST['twilio_voice_language'] : 'en';
	$survey_phone_participant_field = (isset($Proj->metadata[$_POST['survey_phone_participant_field']])) ? $_POST['survey_phone_participant_field'] : "";
	$twilio_account_sid = $_POST['twilio_account_sid'];
	$twilio_auth_token = $_POST['twilio_auth_token'];
	// Modify settings in table
	$sql = "update redcap_projects set twilio_enabled = $twilio_enabled, twilio_option_voice_initiate = $twilio_option_voice_initiate, 
			twilio_option_sms_initiate = $twilio_option_sms_initiate, twilio_option_sms_invite_make_call = $twilio_option_sms_invite_make_call, 
			twilio_option_sms_invite_receive_call = $twilio_option_sms_invite_receive_call,
			twilio_from_number = ".checkNull($twilio_from_number).", twilio_account_sid = ".checkNull($twilio_account_sid).", 
			twilio_auth_token = ".checkNull($twilio_auth_token).", twilio_voice_language = '".prep($twilio_voice_language)."',
			survey_phone_participant_field = '".prep($survey_phone_participant_field)."'
			where project_id = $project_id";
	if (db_query($sql)) {
		// Set response
		$response = "1";
		// Logging
		log_event($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id","Modify project settings");
		## TWILIO CHECK: Check connection to Twilio and also set the voice/sms URLs to the REDCap survey URL, if not set yet
		// Initialize Twilio
		TwilioRC::init();
		// Instantiate a new Twilio Rest Client
		$twilioClient = TwilioRC::client();
		// Error msg
		$error_msg = "";
		// SET URLS: Loop over the list of numbers and get the sid of the phone number
		$numberBelongsToAcct = false;
		$allNumbers = array();
		try {
			foreach ($twilioClient->account->incoming_phone_numbers as $number) {
				// Collect number in array
				$allNumbers[] = $number->phone_number;
				// If number does not match, then skip
				if ($twilio_from_number != '' && substr($number->phone_number, -1*strlen($twilio_from_number)) != $twilio_from_number) {
					continue;
				}
				// We verified that the number belongs to this Twilio account
				$numberBelongsToAcct = true;
				// Set VoiceUrl and SmsUrl for this number, if not set yet
				if ($number->voice_url != APP_PATH_SURVEY_FULL || $number->sms_url != APP_PATH_SURVEY_FULL) {
					$number->update(array("VoiceUrl"=>APP_PATH_SURVEY_FULL, "SmsUrl"=>APP_PATH_SURVEY_FULL));
				}
			}
			// If number doesn't belong to account
			if ($twilio_from_number != '' && !$numberBelongsToAcct) {
				// Set error message
				$error_msg = $lang['survey_833'];
				if (empty($allNumbers)) {
					$error_msg .= RCView::div(array('style'=>'margin-top:10px;font-weight:bold;'), $lang['survey_843']);
				} else {
					$error_msg .= RCView::div(array('style'=>'margin-top:5px;font-weight:bold;'), " &nbsp; " . implode("<br> &nbsp; ", $allNumbers));
				}
			}				
		} catch (Exception $e) {
			// Set error message
			$error_msg = $lang['survey_832'];
			// Make sure Localhost isn't being used as REDCap base URL (not valid for Twilio)
			if (strpos(APP_PATH_SURVEY_FULL, "http://localhost") !== false || strpos(APP_PATH_SURVEY_FULL, "https://localhost") !== false) {
				$error_msg .= "<br><br>".$lang['survey_841'];
			}
		}
		// If we are missing the phone number or if an error occurred with Twilio, then disable this module
		if ($twilio_enabled && ($twilio_from_number == '' || $error_msg != '')) {
			$sql = "update redcap_projects set twilio_enabled = 0 where project_id = $project_id";
			db_query($sql);
			// If Twilio credentials worked but no phone number was entered, then let them know that the module was NOT enabled
			if ($twilio_enabled && $twilio_from_number == '' && $error_msg == '') {
				$error_msg = $lang['survey_842'];
			}
		}
		// If there's an error message, then display it
		if ($error_msg != '') {
			// Display error message
			print 	RCView::div(array('class'=>'red'),
						RCView::img(array('src'=>'exclamation.png', 'class'=>'imgfix')) .
						$error_msg
					);
			exit;
		}
		
	}
}


## SAVE PROJECT SETTING VALUE
else
{
	// Make sure the "name" setting is a real one that we can change
	$viableSettingsToChange = array('auto_inc_set', 'scheduling', 'randomization', 'repeatforms', 'surveys_enabled', 
									'survey_email_participant_field', 'realtime_webservice_enabled', 'twilio_pre_enabled');
	if (!empty($_POST['name']) && in_array($_POST['name'], $viableSettingsToChange)) 
	{
		// If this is a super-user-only attribute, then make sure the user is a super user before doing anything
		if ($_POST['name'] == 'realtime_webservice_enabled' && !SUPER_USER) exit;
		// Modify setting in table
		$sql = "update redcap_projects set {$_POST['name']} = '" . prep(label_decode($_POST['value'])). "' 
				where project_id = $project_id";
		if (db_query($sql)) {
			$response = "1";
			// Logging
			log_event($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id","Modify project settings");
		}
	}
}


// Send response
print $response;
