<?php

/**
 * TwilioRC
 * This class is used for processes related to Voice Calling & SMS via Twilio.com's REST API
 */
class TwilioRC
{
	// Set max length for any SMS. Real limit is 160, but need some buffer room to add "(1/total) " at beginning and ellipses at end
	const MAX_SMS_LENGTH = 150;

	// Initialize Twilio classes and settings
	public static function init()
	{
		global $rc_autoload_function;
		// Call Twilio classes
		require_once APP_PATH_CLASSES . "Twilio/Services/Twilio.php";
		// Reset the class autoload function because Twilio's classes changed it		
		spl_autoload_register($rc_autoload_function);
	}
	
	
	// Get array of all voices (genders) available in Twilio voice calling service
	public static function getAllVoices()
	{
		return array('man', 'woman', 'alice');
	}	
	
	
	// Get array of all languages available in Twilio voice calling service
	public static function getAllLanguages()
	{
		return array(
					// Male/Female only
					'en'=>'English, United States',
					'en-gb'=>'English, UK',
					'es'=>'Spanish, Spain',
					'fr'=>'French, France',
					'de'=>'German, Germany',
					'it'=>'Italian, Italy',
					// Alice only
					'da-DK'=>'Danish, Denmark',
					'de-DE'=>'German, Germany',
					'en-AU'=>'English, Australia',
					'en-CA'=>'English, Canada',
					'en-GB'=>'English, UK',
					'en-IN'=>'English, India',
					'en-US'=>'English, United States',
					'ca-ES'=>'Catalan, Spain',
					'es-ES'=>'Spanish, Spain',
					'es-MX'=>'Spanish, Mexico',
					'fi-FI'=>'Finnish, Finland',
					'fr-CA'=>'French, Canada',
					'fr-FR'=>'French, France',
					'it-IT'=>'Italian, Italy',
					'ja-JP'=>'Japanese, Japan',
					'ko-KR'=>'Korean, Korea',
					'nb-NO'=>'Norwegian, Norway',
					'nl-NL'=>'Dutch, Netherlands',
					'pl-PL'=>'Polish-Poland',
					'pt-BR'=>'Portuguese, Brazil',
					'pt-PT'=>'Portuguese, Portugal',
					'ru-RU'=>'Russian, Russia',
					'sv-SE'=>'Swedish, Sweden',
					'zh-CN'=>'Chinese (Mandarin)',
					'zh-HK'=>'Chinese (Cantonese)',
					'zh-TW'=>'Chinese (Taiwanese Mandarin)'
			);
	}		
	
	
	// Get array of all Twilio languages spoken only by the 'man' voice (the rest will be spoken by 'alice')
	public static function getManOnlyLanguages()
	{
		return array('en', 'en-gb', 'es', 'fr', 'de', 'it');
	}
	
	
	// Return dropdown options (as array) of all languages available in Twilio voice calling service
	public static function getDropdownAllLanguages()
	{
		global $lang;
		// Get all languages available
		$allLang = self::getAllLanguages();
		// Get all voices available
		$allVoices = self::getAllVoices();
		// Get all 'man'-only languages (the rest will be spoken by 'alice')
		$manLang = self::getManOnlyLanguages();		
		// Build an array of drop-down options listing all voices/languages
		$options = array();
		foreach ($allLang as $this_lang=>$this_label) {
			// Is alice voice?
			$isAlice = (!in_array($this_lang, $manLang));
			// Get group name
			$this_group_label = $isAlice ? $lang['survey_723'] : $lang['survey_724'];
			// Add to array
			$options[$this_group_label][$this_lang] = "$this_label ($this_group_label)";
		}		
		// Return array of options
		return $options;
	}
	

	// Initialize Twilio client object
	public static function client()
	{
		// Get global variables, or if don't have them, set locals from above as globals
		global $twilio_account_sid, $twilio_auth_token, $twilio_from_number;
		// If not in a project (e.g. entering Survey Access Code) and Twilio is posting, 
		// then use the AccountSid passed to retrive the Twilio auth token from the redcap_projects table.
		if (!defined("PROJECT_ID") && isset($_SERVER['HTTP_X_TWILIO_SIGNATURE']) && isset($_POST['AccountSid'])) {
			$twilio_account_sid = $_POST['AccountSid'];
			list ($twilio_auth_token, $twilio_from_number) = self::getTokenByAcctSid($twilio_account_sid);
		}
		// Instantiate a new Twilio Rest Client
		return new Services_Twilio($twilio_account_sid, $twilio_auth_token);
	}
	

	// Retrive the Twilio auth token from the redcap_projects table using the Twilio account SID
	public static function getTokenByAcctSid($twilio_account_sid)
	{
		$sql = "select twilio_auth_token, twilio_from_number from redcap_projects 
				where twilio_account_sid = '".prep($twilio_account_sid)."' limit 1";
		$q = db_query($sql);
		$twilio_auth_token  = db_result($q, 0, 'twilio_auth_token');
		$twilio_from_number = db_result($q, 0, 'twilio_from_number');
		return array($twilio_auth_token, $twilio_from_number);
	}
	

	// Obtain the voice call "language" setting for the project
	public static function getLanguage()
	{
		global $twilio_voice_language;
		// Get all languages available
		$allLang = self::getAllLanguages();
		// Return abbreviation for language that is set for this request
		return ($twilio_voice_language == null || ($twilio_voice_language != null && !isset($allLang[$twilio_voice_language]))) 
				? 'en' : $twilio_voice_language;
	}
	

	// Obtain the gender of the "voice" for the voice call based upon the language selected.
	// Note: Use "man" for en, en-gb, es, fr, de, and it, but for all other languages, use "alice".
	public static function getVoiceGender()
	{
		// Get current language selected
		$current_language = self::getLanguage();
		// Get all 'man'-only languages (the rest will be spoken by 'alice')
		$manLang = self::getManOnlyLanguages();
		// Is alice voice?
		return (in_array($current_language, $manLang)) ? 'man' : 'alice';
	}
	
	
	// Function to send SMS message. Segments by 160 characters per SMS, if body is longer.
	public static function sendSMS($text, $number_to_sms, $twilioClient, $twilio_from_number_alt=null)
	{
		// Get the 'From' number
		if ($twilio_from_number_alt == null) {
			global $twilio_from_number;
		} else {
			$twilio_from_number = $twilio_from_number_alt;
		}
		// Set random string to use to explode message into multiple parts
		$sms_splitter = "||RCSMS||";
		// Clean string
		$text = trim(strip_tags(str_replace(array("\r\n", "\n", "\t"), array(" ", " ", " "), $text)));		
		// Dev testing of output
		//if (isDev() && !isset($_SERVER['HTTP_X_TWILIO_SIGNATURE'])) print "TEXT: $text\n";
		// If From and To number are the same, return an error
		if (str_replace(array(" ", "(", ")", "-"), array("", "", "", ""), $twilio_from_number) 
				== str_replace(array(" ", "(", ")", "-"), array("", "", "", ""), $number_to_sms)) {
			return "ERROR: The From and To number cannot be the same ($number_to_sms).";
		}
		// Send SMS		
		if (strlen($text) <= self::MAX_SMS_LENGTH) {
			// Send SMS
			try {
				$twilioClient->account->messages->sendMessage($twilio_from_number, $number_to_sms, $text);
				//echo ($isAjax) ? '1' : "Successfully sent SMS to $number_to_sms";
			} catch (Exception $e) {
				return $e->getMessage();
				//echo ($isAjax) ? '0' : "Error sending SMS to $number_to_sms: " . $e->getMessage();
			}
			// Pause to pace out the SMS messages correctly (since they can be received out of order)
			sleep(2);
		} else {
			// If body is longer than max length, then break up into multiple SMS messages to send.
			$chunks = explode($sms_splitter, wordwrap($text, self::MAX_SMS_LENGTH, $sms_splitter, true));
			$total = count($chunks);
			foreach ($chunks as $part=>$chunk) {
				// Format the text for this SMS
				$text = sprintf("(%d/%d) %s", $part+1, $total, $chunk) . (($part+1 == $total) ? '' : '...');
				// Send SMS
				try {
					$twilioClient->account->messages->sendMessage($twilio_from_number, $number_to_sms, $text);
					//echo ($isAjax) ? '1' : "Successfully sent SMS to $number_to_sms";
				} catch (Exception $e) {
					return $e->getMessage();
					//echo ($isAjax) ? '0' : "Error sending SMS to $number_to_sms: " . $e->getMessage();
				}
				// Pause to pace out the SMS messages correctly (since they can be received out of order)
				sleep(2);
			}
		}
		return true;
	}
	
	
	// Ask respondent to enter survey access code (either voice call or SMS)
	public static function promptSurveyCode($voiceCall=true, $respondentPhoneNumber=null)
	{
		global $lang;
		// If missing the respondent's phone number, then return error
		if ($respondentPhoneNumber == null) exit("ERROR!");	
		
		if ($voiceCall) {
			## VOICE CALL
			// Instantiate Twilio TwiML object
			$twiml = new Services_Twilio_Twiml();
			// Set question properties
			$gather = $twiml->gather(array('method'=>'POST', 'action'=>APP_PATH_SURVEY_FULL, 'finishOnKey'=>'#'));
			// Say the field label
			$gather->say($lang['survey_619']);			
			// Output twiml
			print $twiml;
		} else {
			## SMS
			// Instantiate a new Twilio Rest Client
			$twilioClient = TwilioRC::client();
			// Send SMS
			$smsSuccess = self::sendSMS($lang['survey_619'], $respondentPhoneNumber, $twilioClient);
			if ($smsSuccess !== true) {
				// Error sending SMS
			}
		}
		exit;		
	}
	
	
	// Obtain the Survey Access Code for a given phone number from the redcap_surveys_phone_codes table
	public static function getSmsAccessCodeFromPhoneNumber($participant_phone, $twilio_phone)
	{
		// Remove all non-numeral characters
		$participant_phone = preg_replace('/[^0-9]+/', '', $participant_phone);
		if ($participant_phone == '') return null;
		$twilio_phone = preg_replace('/[^0-9]+/', '', $twilio_phone);
		if ($twilio_phone == '') return null;
		// Remove "1" as U.S. prefix
		if (strlen($participant_phone) == 11 && substr($participant_phone, 0, 1) == '1') {
			$participant_phone = substr($participant_phone, 1);
		}
		if (strlen($twilio_phone) == 11 && substr($twilio_phone, 0, 1) == '1') {
			$twilio_phone = substr($twilio_phone, 1);
		}
		// Check if in table
		$sql = "select access_code from redcap_surveys_phone_codes 
				where phone_number = '".prep($participant_phone)."' and twilio_number = '".prep($twilio_phone)."'";
		$q = db_query($sql);
		// Return access code
		if (db_num_rows($q)) {
			// Now delete the code from the table since we no longer need it
			$sql = "delete from redcap_surveys_phone_codes 
					where phone_number = '".prep($participant_phone)."' and twilio_number = '".prep($twilio_phone)."'";
			db_query($sql);
			// Return code
			return db_result($q, 0);
		} else {
			// Return null since the number has no stored code
			return null;
		}
	}
	
	
	// Add Survey Access Code for a given phone number to the redcap_surveys_phone_codes table
	public static function addSmsAccessCodeForPhoneNumber($participant_phone, $twilio_phone, $access_code)
	{
		// Remove all non-numeral characters
		$participant_phone = preg_replace('/[^0-9]+/', '', $participant_phone);
		if ($participant_phone == '') return null;
		$twilio_phone = preg_replace('/[^0-9]+/', '', $twilio_phone);
		if ($twilio_phone == '') return null;
		// Remove "1" as U.S. prefix
		if (strlen($participant_phone) == 11 && substr($participant_phone, 0, 1) == '1') {
			$participant_phone = substr($participant_phone, 1);
		}
		if (strlen($twilio_phone) == 11 && substr($twilio_phone, 0, 1) == '1') {
			$twilio_phone = substr($twilio_phone, 1);
		}
		// Add to table (update table if phone number already exists for this survey)
		$sql = "insert into redcap_surveys_phone_codes (phone_number, twilio_number, access_code) 
				values ('".prep($participant_phone)."', '".prep($twilio_phone)."', '".prep($access_code)."')
				on duplicate key update access_code = '".prep($access_code)."'";
		// Return true on success or false on fail
		return db_query($sql);
	}
	
	
	// Determine if a field's multiple choice options all have numerical coded values
	// $enum is provided as the element_enum string
	public static function allChoicesNumerical($enum)
	{
		foreach (parseEnum($enum) as $this_code=>$this_label) {
			if (!is_numeric($this_code)) return false;
		}	
		return true;
	}
	
	// Determine if a field's usage in a SMS or voice call survey is viable for those mediums.
	// Provide $field_type of the field and $type as "SMS" or "VOICE", as well as its validation type $val_type, if applicable.
	// Return FALSE if the field is not viable for the given medium, which means that the field will be skipped in the survey.
	public static function fieldUsageIVR($type="SMS", $field_name)
	{
		// Get globals
		global $Proj, $lang;
		
		// Get all validation types
		$all_val_types = getValTypes();
		
		// Get field attributes
		$field_type = $Proj->metadata[$field_name]['element_type'];
		$choices = $Proj->metadata[$field_name]['element_enum'];
		$val_type = convertLegacyValidationType($Proj->metadata[$field_name]['element_validation_type']);
		$data_type = ($field_type == 'text' && $val_type != '') ? $all_val_types[$val_type]['data_type'] : '';
		
		## SMS
		if ($type == "SMS") {
			if ($field_type == 'text') {
				return true;
			} elseif ($field_type == 'textarea') {
				return true;
			} elseif ($field_type == 'calc') {
				return $lang['survey_886'];
			} elseif ($field_type == 'select') {
				return true;
			} elseif ($field_type == 'radio') {
				return true;
			} elseif ($field_type == 'yesno') {
				return true;
			} elseif ($field_type == 'truefalse') {
				return true;
			} elseif ($field_type == 'checkbox') {
				return true;
			} elseif ($field_type == 'file') {
				return $lang['survey_888'];
			} elseif ($field_type == 'slider') {
				return true;
			} elseif ($field_type == 'descriptive') {
				return true;
			} elseif ($field_type == 'sql') {
				return true;
			} else {
				return $lang['survey_887'];
			}
		} 
		
		## VOICE CALL
		else {
			if ($field_type == 'text') {
				// Only allow number or integer data types
				return ($data_type == 'integer' || $data_type == 'number' ? true : $lang['survey_889']);
			} elseif ($field_type == 'textarea') {
				return $lang['survey_892'];
			} elseif ($field_type == 'calc') {
				return $lang['survey_886'];
			} elseif ($field_type == 'select') {
				return (self::allChoicesNumerical($choices) ? true : $lang['survey_890']);
			} elseif ($field_type == 'radio') {
				return (self::allChoicesNumerical($choices) ? true : $lang['survey_890']);
			} elseif ($field_type == 'yesno') {
				return true;
			} elseif ($field_type == 'truefalse') {
				return true;
			} elseif ($field_type == 'checkbox') {
				return $lang['survey_891'];
			} elseif ($field_type == 'file') {
				return $lang['survey_888'];
			} elseif ($field_type == 'slider') {
				return true;
			} elseif ($field_type == 'descriptive') {
				return true;
			} elseif ($field_type == 'sql') {
				return (self::allChoicesNumerical(getSqlFieldEnum($choices)) ? true : $lang['survey_890']);
			} else {
				return $lang['survey_887'];
			}
		}	
	}
	
	
	// Redirect Twilio to another survey page
	public static function redirectSurvey($hash)
	{
		// Redirect to survey page
		print  '<?xml version="1.0" encoding="UTF-8"?>
				<Response>
					<Redirect method="POST">'.APP_PATH_SURVEY."index.php?s=$hash".'</Redirect>
				</Response>';
		exit;
	}
	
	
	// Take enum array and return int of the maximum number of numerical digits that a set of choices has
	public static function getChoicesMaxDigits($this_enum_array)
	{
		// Set defaults
		$num_digits = 1;
		// In not array, return default
		if (!is_array($this_enum_array)) return $num_digits;
		// Loop through choices
		foreach ($this_enum_array as $key=>$val) {
			// If not numeric, then skip
			if (!is_numeric($key)) continue;
			// If numeric, then count digits
			$num_digits = strlen($key."");
		}
		// Return count
		return $num_digits;
	}
	
}