<?php

// Check if coming from survey or authenticated form
if (isset($_GET['s']) && !empty($_GET['s']))
{
	// Call config_functions before config file in this case since we need some setup before calling config
	require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
	// Survey functions needed
	require_once dirname(dirname(__FILE__)) . "/Surveys/survey_functions.php";
	// Validate and clean the survey hash, while also returning if a legacy hash
	$hash = $_GET['s'] = Survey::checkSurveyHash();
	// Set all survey attributes as global variables
	Survey::setSurveyVals($hash);
	// Now set $_GET['pid'] before calling config
	$_GET['pid'] = $project_id;
	// Set flag for no authentication for survey pages
	define("NOAUTH", true);
}

// Config
require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
require_once dirname(dirname(__FILE__)) . "/Surveys/survey_functions.php";
// Init Twilio
TwilioRC::init();
// Twilio must be enabled first
if (!$twilio_enabled) exit($isAjax ? '0' : 'ERROR!');
// Instantiate a client to Twilio's REST API
$twilioClient = TwilioRC::client();

// Set values for dialog title and content
$popupContent = "";
$popupTitle = RCView::img(array('src'=>'phone.gif', 'style'=>'vertical-align:middle;')) .
			  RCView::span(array('style'=>'vertical-align:middle;'), $lang['survey_815']);


## DISPLAY DIALOG CONTENT
if (isset($_GET['action']) && $_GET['action'] == 'view') 
{
	// Set dialog content
	$popupContent = RCView::p(array('style'=>'margin-top:0;font-size:13px;'), 
						$lang['survey_808']
					) .
					RCView::div(array('style'=>'color:#800000;font-size:13px;margin-top:20px;'), 
						RCView::div(array('style'=>'margin-bottom:4px;'),
							$lang['survey_809']
						) .
						RCView::textarea(array('id'=>'call_sms_to_number', 'class'=>'x-form-field notesbox', 
							'style'=>'font-family:arial;height:100px;width:95%;'))
					) . 
					RCView::div(array('style'=>'margin:20px 0 5px;color:#004000;'), 
						$lang['survey_810']
					) . 
					RCView::div(array(), 
						RCView::select(array('id'=>'delivery_type', 'onchange'=>"showSmsCustomMessage();", 'class'=>'x-form-text x-form-field', 'style'=>'padding-right:0;height:22px;'),	
							Survey::getDeliveryMethods(false, true, null, false), 'VOICE_INITIATE')
					) .
					RCView::div(array('id'=>'sms_message_div', 'style'=>'display:none;color:#000088;font-size:13px;margin-top:20px;'), 
						RCView::div(array('style'=>'margin-bottom:4px;'),
							$lang['survey_814']
						) .
						RCView::textarea(array('id'=>'sms_message', 'class'=>'x-form-field notesbox', 
							'style'=>'font-family:arial;height:40px;width:95%;'))
					) .
					RCView::div(array('class'=>'spacer', 'style'=>'padding:10px 10px 0 0;margin:20px -10px 0 -10px;text-align:right;'), 
						RCView::button(array('class'=>'jqbutton', 'style'=>'padding:0.3em 0.6em !important;font-size:13px;color:#444;font-weight:bold;', 'onclick'=>"initCallSMS('".cleanHtml($_GET['s'])."',$('#call_sms_to_number').val(),$('#delivery_type').val());"), $lang['survey_792'])  .
						RCView::button(array('class'=>'jqbutton', 'style'=>'padding:0.3em 0.6em !important;font-size:13px;color:#666;', 'onclick'=>"$('#VoiceSMSdialog').dialog('close');"), $lang['global_53']) 
					);
	// Send back JSON response
	print json_encode(array('content'=>$popupContent, 'title'=>$popupTitle));
}


## MAKE CALL OR SEND SMS
elseif (isset($_GET['action']) && $_GET['action'] == 'init') 
{
	// Set error count flag
	$errorNumbers = array();
	$successfulNumbers = array();
	// Set voice and language for all statements in call
	$language = TwilioRC::getLanguage();
	$voice = TwilioRC::getVoiceGender();
	// Get format
	$all_delivery_methods = Survey::getDeliveryMethods();
	$delivery_type = (!isset($_GET['delivery_type']) || !isset($all_delivery_methods[$_GET['delivery_type']])) ? 'VOICE_INITIATE' : $_GET['delivery_type'];
	// Set number(s) to call
	$number_to_call = (isset($_GET['phone'])) ? $_GET['phone'] : $_POST['phone'];
	if ($number_to_call == '') exit($isAjax ? '0' : 'Missing "phone" in query string!');
	// Convert numbers to array
	$number_to_calls = explode("\n", $number_to_call);
	// Loop through all numbers to remove invalid numbers and duplicates
	foreach ($number_to_calls as $key=>$number_to_call) 
	{
		// Remove blank lines
		if ($number_to_call == '') {
			unset($number_to_calls[$key]);
			continue;
		}
		// Clean the number
		$number_to_call_orig = $number_to_call;
		$number_to_call = preg_replace("/[^0-9]/", "", $number_to_call);
		// If invalid format, then give error
		if ($number_to_call == '' || ($number_to_call != '' && strlen($number_to_call) < 7)) {
			$errorNumbers[] = $number_to_call_orig;	
			unset($number_to_calls[$key]);			
		} else {
			$number_to_calls[$key] = $number_to_call;
		}
	}
	$number_to_calls = array_unique($number_to_calls);
	// Get SMS custom message (if applicable)
	$sms_message = trim($_POST['sms_message']);
	if ($sms_message != '') $sms_message .= " -- ";
	// Loop through all numbers to call/send
	foreach ($number_to_calls as $number_to_call) 
	{
		## VOICE CALL
		if ($delivery_type == 'VOICE_INITIATE') {
			// Set the survey URL that Twilio will make the request to
			$question_url = APP_PATH_SURVEY_FULL . "?s={$_GET['s']}&voice=$voice&language=$language";
			// Call the phone number
			try {
				$call = $twilioClient->account->calls->create($twilio_from_number, $number_to_call, $question_url);
				$successfulNumbers[] = formatPhone($number_to_call);
			} catch (Exception $e) {
				$errorNumbers[] = formatPhone($number_to_call);
			}
		} 
		## SMS
		else {
			// Get the survey access code for this survey link
			$survey_access_code = Survey::getAccessCode(getParticipantIdFromHash($_GET['s']), false, false, true);
			// Set message/content for SMS
			if ($delivery_type == 'SMS_INVITE_MAKE_CALL') {
				// Send phone number + access code via SMS
				$sms_message .= $lang['survey_863'] . " " . formatPhone($twilio_from_number);
				// Add phone number and access code to table
				TwilioRC::addSmsAccessCodeForPhoneNumber($number_to_call, $twilio_from_number, $survey_access_code);
			} elseif ($delivery_type == 'SMS_INVITE_RECEIVE_CALL') {
				// Send access code via SMS for them to receive a call
				$sms_message .= $lang['survey_866'];
				// Add phone number and access code to table
				TwilioRC::addSmsAccessCodeForPhoneNumber($number_to_call, $twilio_from_number, Survey::PREPEND_ACCESS_CODE_NUMERAL . $survey_access_code);
			} else {
				// Send access code via SMS
				$sms_message .= $lang['survey_865'];
				// Add phone number and access code to table
				TwilioRC::addSmsAccessCodeForPhoneNumber($number_to_call, $twilio_from_number, $survey_access_code);
			}
			// Send SMS to the phone number
			$success = TwilioRC::sendSMS($sms_message, $number_to_call, $twilioClient);
			if ($success === true) {
				$successfulNumbers[] = formatPhone($number_to_call);
			} else {
				$errorNumbers[] = formatPhone($number_to_call);
			}
		}
	}
	
	// Set dialog content
	$popupContent = "";
	if (count($successfulNumbers) > 0) {
		$popupContent .= RCView::div(array('style'=>'margin-bottom:15px;font-size:13px;color:#004000;'), 
							RCView::img(array('src'=>'tick.png', 'class'=>'imgfix')) .
							$lang['survey_811'] . " " . 
							RCView::div(array('style'=>'margin-top:5px;line-height:12px;font-size:11px;overflow:auto;max-height:120px;'), 
								" &nbsp; &nbsp; - " . 
								implode("<br> &nbsp; &nbsp; - ", $successfulNumbers)
							)
						);
	}
	// Report any errors
	if (count($errorNumbers) > 0) {
		if ($popupContent != '') {
			$popupContent .= RCView::div(array('class'=>'spacer'), '');
		}
		$popupContent .= 	RCView::div(array('style'=>'margin-bottom:15px;font-size:13px;color:#C00000;'), 
								RCView::img(array('src'=>'exclamation.png', 'class'=>'imgfix')) .
								$lang['survey_813'] . " " . 
								RCView::div(array('style'=>'line-height:12px;font-size:11px;overflow:auto;max-height:120px;'), 
									" &nbsp; &nbsp; - " . 
									implode("<br> &nbsp; &nbsp; - ", $errorNumbers)
								)
							);
	}
	// Buttons
	$popupContent .= 	RCView::div(array('class'=>'spacer'), '') .
						RCView::div(array('style'=>'padding:10px 10px 0 0;margin:10px -10px 0 -10px;text-align:right;'), 
							RCView::button(array('class'=>'jqbutton', 'style'=>'padding:0.3em 0.6em !important;font-size:13px;color:#111;', 'onclick'=>"$('#VoiceSMSdialog').dialog('close');initCallSMS('".cleanHtml($_GET['s'])."');"), $lang['survey_812']) .
							RCView::button(array('class'=>'jqbutton', 'style'=>'padding:0.3em 0.6em !important;font-size:13px;color:#111;', 'onclick'=>"$('#VoiceSMSdialog').dialog('close');"), $lang['calendar_popup_01']) 
						);
	// Send back JSON response
	print json_encode(array('errors'=>count($errorNumbers), 'content'=>$popupContent, 'title'=>$popupTitle));
}


## ERROR
else
{
	print "0";
}