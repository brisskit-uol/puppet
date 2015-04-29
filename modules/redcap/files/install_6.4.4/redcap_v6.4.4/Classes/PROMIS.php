<?php


/**
 * CLASS FOR ACTIONS RELATED TO THE "PROMIS" API
 */
class PROMIS
{
	// Set PROMIS web service API version (relative directory path)
	const api_version = "2012-01";
	
	// How many digits to round the T-score to
	const tscore_round_digits = 1;
	
	// Value of empty GUID
	const empty_guid = '00000000-0000-0000-0000-000000000000';

	
	// Request new Assessment Center API registration_id and token
	public static function requestRegIdToken()
	{
		global $institution, $homepage_contact, $promis_registration_id, $promis_token, $promis_api_base_url, 
			   $homepage_grant_cite, $homepage_contact_email;
		// Parse homepage contact into first/last name
		list ($fname, $lname) = explode(" ", $homepage_contact, 2);
		// Set params to post
		$params = array('FName'=>trim($fname), 'LName'=>trim($lname), 'EMail'=>'redcapemailtest@gmail.com', 
						'Organization'=>label_decode($institution)." (".APP_PATH_WEBROOT_FULL."), Contact email: $homepage_contact_email", 
						'Usage'=>'REDCap', 'Funding'=>$homepage_grant_cite, 'TC_PROMIS'=>'on', 'btnRegister'=>'Request User ID/Password');
		$response = http_post($promis_api_base_url . self::api_version . "/Registration/", $params);
		// Parse the response
		list ($promis_registration_id, $promis_token) = self::parseRegIdTokenRequest($response);
		if ($promis_registration_id == null || $promis_token == null) return false;
		// Activate the registration ID
		$activated = http_get($promis_api_base_url . self::api_version . "/Registration/$promis_registration_id?Activate=true");
		// Save reg ID and token in config table
		$sql = "update redcap_config set value = '".prep($promis_registration_id)."' where field_name = 'promis_registration_id'";
		$q1 = db_query($sql);
		$sql = "update redcap_config set value = '".prep($promis_token)."' where field_name = 'promis_token'";
		$q2 = db_query($sql);
		return ($q1 && $q2);
	}

	
	// Parse returned response for request to get registration_id and token
	public static function parseRegIdTokenRequest($response)
	{
		$response = str_replace("\r", "", strip_tags($response));
		$current_field = $prev_field = null;
		foreach (explode("\n", $response) as $line) {
			$line = $current_field = trim($line);
			if ($prev_field == 'RegistrationOID') {
				$reg_id = $line;
			} elseif ($prev_field == 'Token') {
				$token = $line;
			}
			$prev_field = $current_field;
		}
		return array($reg_id, $token);
	}

	
	// Make API request
	public function promisApiRequest($url, $params=array(), $request_type="POST", $recursive=false)
	{
		global $promis_registration_id, $promis_token, $promis_api_base_url, $promis_enabled, $lang;
		// If PROMIS is disabled at the system level, then display an error (ideally you should not get this far if disabled)
		if (!$promis_enabled) exit($lang['system_config_319']);		
		// Make sure CURL is enabled on server
		if (!function_exists('curl_init')) {
			curlNotLoadedMsg();
			exit;
		}		
		// Make sure we have a reg_id and token
		if ($promis_registration_id == '' || $promis_token == '') {
			// They're missing, so try to request them
			if (!self::requestRegIdToken()) {
				// Failed to get reg ID and token, so display error
				exit("ERROR: Could not successfully activate registration ID and token from 
					 <a href='".dirname($promis_api_base_url)."' style='text-decoration:underline;' target='_blank'>".dirname($promis_api_base_url)."</a>.");
			}
		}
		// Make API call
		$curlpost = curl_init();
		curl_setopt($curlpost, CURLOPT_USERPWD, $promis_registration_id . ":" . $promis_token);
		curl_setopt($curlpost, CURLOPT_SSL_VERIFYPEER, SSL);
		curl_setopt($curlpost, CURLOPT_VERBOSE, 0);
		curl_setopt($curlpost, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curlpost, CURLOPT_AUTOREFERER, true);
		curl_setopt($curlpost, CURLOPT_MAXREDIRS, 10);
		curl_setopt($curlpost, CURLOPT_PROXY, PROXY_HOSTNAME);  
		curl_setopt($curlpost, CURLOPT_URL, $url);
		curl_setopt($curlpost, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlpost, CURLOPT_CUSTOMREQUEST, $request_type);
		curl_setopt($curlpost, CURLOPT_POSTFIELDS, (is_array($params) ? http_build_query($params, '', '&') : $params));
		curl_setopt($curlpost, CURLOPT_FRESH_CONNECT, 1); // Don't use a cached version of the url
		curl_setopt($curlpost, CURLOPT_CONNECTTIMEOUT, 30); // Set timeout time in seconds
		$response = curl_exec($curlpost);
		$info = curl_getinfo($curlpost);
		curl_close($curlpost);
		// If returns non-200 HTTP status code, then something went wrong (maybe API credentials are out of date).
		if ($info['http_code'] == '411') {
			// Try requesting new API credentials to see if that helps
			if (!self::requestRegIdToken()) {
				// Failed to get reg ID and token, so display error
				exit("ERROR: Could not successfully activate registration ID and token from 
					 <a href='".dirname($promis_api_base_url)."' style='text-decoration:underline;' target='_blank'>".dirname($promis_api_base_url)."</a>.");
			} elseif (!$recursive) {
				// Got new credentials, so try again
				$response = self::promisApiRequest($url, $params, $request_type, true);
			}
		}
		// Return response
		return $response;
	}
	

	// Convert form in JSON to HTML
	public function buildPromisForm($assessment_id, $form_array, $form_oid, $participant_id=null, $response_id=null, $acknowledgementText="")
	{
		global $lang, $Proj, $table_pk, $promis_skip_question;
		// If no items, then return false
		if (empty($form_array['Items'])) return false;
		// Set html form name/id
		$html_form_name = 'form';
		// Loop through array
		$html = '';
		$choices = '';
		foreach ($form_array['Items'] as $fields)
		{
			// Field ID name
			$field_ID = $fields['ID'];
			// Loop through elements
			foreach ($fields['Elements'] as $field_elements) {
				// If has Map
				if (isset($field_elements['Map'])) {
					// Loop through Map elements
					foreach ($field_elements['Map'] as $map_elements) {
						// Input name
						$input_name = $map_elements['FormItemOID'];
						// Add to choices
						$choices .= RCView::div(array('class'=>'frmrd'), 
										RCView::input(array('type'=>'radio', 'value'=>$map_elements['ItemResponseOID'], 'name'=>$input_name)) . 
										$map_elements['Description']
									);
					}
				} else {
					$html .= RCView::div(array('style'=>'font-weight:bold;margin:2px 0;'), $field_elements['Description']);
				}
			}
		}
		
		// SMS OR VOICE RESPONSE
		if (isset($_SERVER['HTTP_X_TWILIO_SIGNATURE'])) 
		{
			// Determine the REDCap variable name for this field
			return self::getRedcapVarFromPromisVar($field_ID, $_GET['page']);
		}
		
		// WEB PAGE RESPONSE: Return form HTML
		return 	
				// Add space if not displaying instructions
				($_SERVER['REQUEST_METHOD'] != 'POST' ? '' :
					RCView::p(array('style'=>'margin:0 0 5px;'), '&nbsp;')
				) .
				// Web form
				RCView::form(array('id'=>$html_form_name, 'name'=>$html_form_name, 'method'=>'post', 'action'=>APP_PATH_SURVEY."index.php?s={$_GET['s']}", 'enctype'=>'multipart/form-data'), 
					RCView::table(array('id'=>'form_table', 'class'=>'form_border', 'style'=>'display:block;width:100%;'), 
						// Question row
						RCView::tr(array('id'=>"$input_name-tr", 'sq_id'=>$input_name),  
							RCView::td(array('class'=>'label quesnum', 'valign'=>'top', 'style'=>'width:6%;', 'width'=>'6%'),
								''
							) . 
							RCView::td(array('class'=>'label', 'style'=>'font-size:15px;'),
								$html
							) . 
							RCView::td(array('class'=>'data', 'style'=>'font-size:15px;'),
								$choices .
								// Reset link
								RCView::div(array('style'=>'text-align:right;line-height:10px;'), 
									RCView::a(array('href'=>'javascript:;', 'class'=>'cclink', 'style'=>'font-weight:normal;font-size:7pt;', 'onclick'=>"$('input[name=\"$input_name\"]').prop('checked', false); return false;"), 
										$lang['form_renderer_20']
									)
								)
							)
						) .
						// Button row
						RCView::tr(array('id'=>"__SUBMITBUTTONS__-tr", 'sq_id'=>'__SUBMITBUTTONS__', 'class'=>'surveysubmit'),  
							RCView::td(array('class'=>'label', 'colspan'=>'3', 'style'=>'text-align:center;padding:15px 0;'),
								RCView::button(array('id'=>'catsubmit-btn', 'class'=>'jqbutton', 'style'=>'color:#800000;width:140px;', 'onclick'=>"$(this).button('disable');return submit_cat();"), $lang['data_entry_213'] . " >>") .
								RCView::img(array('src'=>'progress_circle.gif', 'class'=>'imgfix', 'style'=>'visibility:hidden;margin-left:10px;', 'id'=>'catsubmitprogress-img'))
							)
						) .
						// Library instrument acknowledgement text (only for first CAT page)
						(($acknowledgementText == '' || $_SERVER['REQUEST_METHOD'] != 'GET') ? '' :
							RCView::tr(array('class'=>'surveysubmit'),  
								RCView::td(array('class'=>'header toolbar', 'colspan'=>'3', 'style'=>'font-size:12px;font-weight:normal;border:1px solid #CCCCCC;'),
									nl2br($acknowledgementText)
								)
							)
						)
					) . 
					// If allow participants to skip questions, then give extra option to skip
					RCView::div(array('class'=>'simpleDialog', 'id'=>'cat_save_alert_dialog', 'title'=>$lang['survey_562']),
						($promis_skip_question ? $lang['survey_559'] : $lang['survey_560'])
					) .
					// Hidden field containing assessment_id value
					RCView::hidden(array('name'=>'promis-assessment_id', 'value'=>$assessment_id)) .
					//RCView::hidden(array('name'=>'response_id', 'value'=>$response_id)) .
					RCView::hidden(array('name'=>'__response_hash__', 'value'=>($response_id != null ? encryptResponseHash($response_id, $participant_id) : '')))
				) .
				// Add "Powered by Vanderbilt" text
				RCView::div(array('style'=>'text-align:center;padding:12px 15px 0 0;'),
					RCView::span(array('style'=>'font-size:12px;font-weight:normal;color:#bbb;vertical-align:middle;'), "Powered by") .
					RCView::img(array('src'=>'vanderbilt-logo-small.png', 'style'=>'vertical-align:middle;'))
				) .
				"<script type='text/javascript'>
				$(function() { 
					enableDataEntryRowHighlight(); 
					$('table#form_table :input:first').trigger('focus');
				});
				function submit_cat() {
					if ($('#$html_form_name input:checked').length < 1) {
						$('#catsubmit-btn').button('enable'); 
						".($promis_skip_question
							? "simpleDialog(null,null,'cat_save_alert_dialog',null,\"$('table#form_table :input:first').trigger('focus');\",'".cleanHtml($lang['survey_563'])."',\"submit_cat_do();\",'".cleanHtml($lang['survey_561'])."');"
							: "simpleDialog(null,null,'cat_save_alert_dialog');"
						)."
						return false;
					}
					submit_cat_do();
					return true;
				}
				function submit_cat_do() {
					$('#catsubmit-btn').button('disable'); 
					$('table#form_table tr:first').fadeTo(0,0.3);
					$('#catsubmitprogress-img').css('visibility','visible');
					$('#$html_form_name').submit();
				}
				</script>
				<style type='text/css'>
				div#footer { display: none; }
				div#surveyinstr, div#surveyinstr p { font-size:15px; }
				</style>";
	}
	
	
	// Get the PROMIS form title from the API
	public function getPromisFormTitle($form_oid)
	{
		global $promis_api_base_url;
		// Call FORMS API to get name of this form
		$response = self::promisApiRequest($promis_api_base_url . self::api_version . "/Forms/.json");
		$response_array = json_decode($response, true);
		foreach ($response_array['Form'] as $form_attr) {
			if ($form_attr['OID'] != $form_oid) continue;
			return $form_attr['Name'];
		}
	}
	
	
	// Determine the hashed-looking PROMIS field name and value from the REDCap variable name and value
	public static function getPromisVarFromRedcapVar($field_name, $field_value)
	{
		global $Proj, $promis_api_base_url;
		// Obtain PROMIS instrument key using project_id/form_name
		$form_oid = self::getPromisKey($Proj->metadata[$field_name]['form_name']);
		// Get the PROMIS field name (not the hash but the short variable)
		$promisShortVar = $Proj->metadata[$field_name]['element_preceding_header'];
		// Get form definition via PROMIS api
		$form_json = PROMIS::promisApiRequest($promis_api_base_url . PROMIS::api_version . "/Forms/$form_oid.json");
		$form_array = json_decode($form_json, true);
		// Loop through all fields of form and put into array
		foreach ($form_array['Items'] as $item) {
			if ($promisShortVar == $item['ID']) {
				foreach ($item['Elements'] as $item_element) {
					if (isset($item_element['Map'])) {
						// Loop through choices
						foreach ($item_element['Map'] as $item_element_map) {
							// Does the value match the choice?
							if ($item_element_map['Value'] == $field_value) {
								// This is it, so return them
								return array($item_element_map['FormItemOID'], $item_element_map['ItemResponseOID']);
							}
						}
					}
				}
				
			}
		}
		// Error
		return false;
	}
	
	
	// Get the PROMIS form from the API and process it
	public function renderPromisForm($project_id, $form_name, $participant_id)
	{
		global $table_pk, $promis_api_base_url, $promis_skip_question, $lang;
		
		// If form was downloaded from Shared Library and has an Acknowledgement, render it here
		$acknowledgementText = getAcknowledgement($project_id, $form_name);
		
		### ASSESSMENT
		if (   (isset($_SERVER['HTTP_X_TWILIO_SIGNATURE']) && !isset($_SESSION['promis-assessment_id'])) 
			|| (!isset($_SERVER['HTTP_X_TWILIO_SIGNATURE']) && $_SERVER['REQUEST_METHOD'] != 'POST') 
			|| ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['__startover']))) 
		{
			// UID for this participant (is this arbitrary?)
			$uid = md5(mt_rand());
			// Obtain PROMIS instrument key using project_id/form_name
			$form_oid = self::getPromisKey($form_name);
			// Set expiration of assessment (in days)
			$expiration_days = 1;
			// Obtain PROMIS instrument title from API
			// $promis_instrument_title = self::getPromisFormTitle($form_oid);
			// print RCView::h3(array(), $promis_instrument_title);
			// Initialize assessment
			$response = self::promisApiRequest($promis_api_base_url . self::api_version . "/Assessments/$form_oid.json", array('UID'=>$uid, 'Expiration'=>$expiration_days));
			$response_array = json_decode($response, true);
			// Validate response
			if (!is_array($response_array)) {				
				exit("<div class='red'><b>{$lang['global_01']}{$lang['colon']}</b> {$lang['system_config_320']}<br><br>
					{$lang['system_config_321']} <b>$promis_api_base_url</b>{$lang['period']}</div>");
			}
			// Set assessment ID
			$assessment_id = $response_array['OID'];
			// Display first question
			$response = self::promisApiRequest($promis_api_base_url . self::api_version . "/Participants/$assessment_id.json");
			$form_array = json_decode($response, true);
			$promisFormHtml = self::buildPromisForm($assessment_id, $form_array, $form_oid, $participant_id, $_POST['__response_id__'], $acknowledgementText);
			if (isset($_SERVER['HTTP_X_TWILIO_SIGNATURE'])) {
				// Add assessment_id to session
				$_SESSION['promis-assessment_id'] = $assessment_id;
				return $promisFormHtml;
			} else {
				print $promisFormHtml;
			}
		} 
		else 
		{
			// Twilio IVR: Determine the hashed-looking PROMIS field name and value from the REDCap variable name and value
			if (isset($_SERVER['HTTP_X_TWILIO_SIGNATURE'])) {
				// Get field and value
				list ($promis_field, $promis_value) = self::getPromisVarFromRedcapVar($_SESSION['field'], $_POST[$_SESSION['field']]);
				// Set in Post so the normal process below will pick it up
				$_POST = array(	'__response_id__' => $_SESSION['response_id'], 
								'promis-assessment_id' => $_SESSION['promis-assessment_id'],
								$promis_field => $promis_value);
				// If the CAT participant is skipping the question (has blank answer), then don't add to Post so that an empty GUID is used
				if ($promis_skip_question && $_POST[$promis_field] == '') {
					unset($_POST[$promis_field]);
				}
			}
			// Set length of GUIDs
			$guid_length = strlen(self::empty_guid);
			// If quesiton-skipping is allowed and participant is skipping a question, then send empty GUID
			$num_guids = 0;
			if ($promis_skip_question) {
				foreach ($_POST as $key=>$val) {
					if (strlen($key) == $guid_length && strlen($val) == $guid_length && substr_count($key, '-') == 4) {
						$num_guids++;
					}
				}
				if ($num_guids == 0) {
					$_POST[self::empty_guid] = self::empty_guid;
				}
			}
			// Display next question or results (if finished)
			$assessment_id = $_POST['promis-assessment_id'];
			// Set response_id
			$response_id = $_POST['__response_id__'];
			// Display question
			foreach ($_POST as $key=>$val) {
				// If not a valid ItemResponseOID, then skip
				if (strlen($key) != $guid_length || strlen($val) != $guid_length || substr_count($key, '-') != 4) {
					continue;
				}
				// Call API to get next question
				$params = array('ItemResponseOID'=>$val, 'Response'=>$key, 
								'Persist'=>'true'); // the "Persist" flag allows us to retrieve the results as we go, otherwise we can only get the results at the end
				$response = self::promisApiRequest($promis_api_base_url . self::api_version . "/Participants/$assessment_id.json", $params);// Decode the JSON response from the API
				$form_array = json_decode($response, true);
				if ($response == '' || !is_array($form_array) || !isset($form_array['Items'])) {
					// ERROR: Request to CAT API server failed unexpectedly
					print 	RCView::div(array('class'=>'red', 'style'=>'margin:30px 0;padding:15px;font-size:14px;'),
								RCView::img(array('src'=>'exclamation.png', 'class'=>'imgfix')) .
								"<b>ERROR:</b> For unknown reasons, the survey ended unexpectedly before it could be completed. Please notify the survey
								administrator of this issue immediately to see if you will be able to start over and re-take this survey.
								Our apologies for this inconvenience.<br><br>
								The survey administrators may need to notify the REDCap administrators at their institution to inform them
								that the REDCap server (".APP_PATH_WEBROOT_FULL.") had trouble communicating with the CAT server at <b>$promis_api_base_url</b>
								(hosted by Vanderbilt University) that is utilized when participants take this survey."
							);					
					exit;
				}
				// Determine if the survey has been completed
				$end_survey = empty($form_array['Items']);
				// Save the score and add record name to session to capture on acknowledgement page
				list ($fetched, $response_id) = self::saveParticipantResponses($assessment_id, $params, $form_name, $participant_id, $response_id, $end_survey);
				$_GET['id'] = $fetched;
				if ($end_survey) {
					// Add record name to session so we can catch it
					$_SESSION['record'] = $fetched;
					// Twilio: Return form status field to denote end of survey
					if (isset($_SERVER['HTTP_X_TWILIO_SIGNATURE'])) {
						return $form_name . "_complete";
					}
					// End the survey by redirecting with __endsurvey in query string
					redirect($_SERVER['REQUEST_URI'] . "&__endsurvey=1");
				} else {
					// Build question html from API response
					$promisFormHtml = self::buildPromisForm($assessment_id, $form_array, $form_oid, $participant_id, $response_id, $acknowledgementText);
					if (isset($_SERVER['HTTP_X_TWILIO_SIGNATURE'])) {
						return $promisFormHtml;
					} else {
						print $promisFormHtml;
					}
				}
				// Break because we should only be doing one loop anyway
				break;
			}	
		}
	}
	

	// Save the participant's score
	public static function saveParticipantResponses($assessment_id, $params, $form_name, $participant_id, $response_id=null, $end_survey=false)
	{
		require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';
		global $table_pk, $public_survey, $Proj, $promis_api_base_url;
		// Get record name from response_id, if we have it
		$fetched = null;
		if (is_numeric($response_id)) {
			$sql = "select record from redcap_surveys_response where response_id = $response_id limit 1";
			$q = db_query($sql);
			$fetched = db_result($q, 0);
		}
		// Set current record as auto-numbered value
		$_GET['id'] = $fetched = ($fetched == null ? getAutoId() : $fetched);
		// Done, so display score
		$data = self::getParticipantResponses($assessment_id, $params, $form_name, $end_survey);
		// Add record ID field and form status to data
		$data[$table_pk] = $fetched;
		$data[$form_name.'_complete'] = ($end_survey ? '2' : '0');
		// Simulate new Post submission (as if submitted via data entry form)
		$_POST = $data;
		// Save new record
		saveRecord($fetched);
		// Set completion time
		$completion_time = ($end_survey ? "'".NOW."'" : "NULL");
		// Add as survey response
		if (is_numeric($response_id)) {
			// Already in table, so update if the survey has been completed
			if ($end_survey) {
				$sql = "update redcap_surveys_response set completion_time = $completion_time where response_id = $response_id";
			} else {
				$sql = "update redcap_surveys_response set first_submit_time = '".NOW."', completion_time = $completion_time 
						where response_id = $response_id and first_submit_time is null";
			}
			$q = db_query($sql);
		} else {
			// Not in table, so insert
			$sql = "insert into redcap_surveys_response (participant_id, record, first_submit_time, completion_time) values 
					(" . checkNull($participant_id) . ", " . checkNull($fetched) . ", '".NOW."', $completion_time)";
			$q = db_query($sql);
			// Apparently two responses came in with the same record for the same public survey.
			// Get new record name and re-insert.
			while (!$q && $public_survey) {
				$_GET['id'] = $fetched = $_POST[$table_pk] = $fetched+1;
				$sql = "insert into redcap_surveys_response (participant_id, record, first_submit_time, completion_time) values 
						(" . checkNull($participant_id) . ", " . checkNull($fetched) . ", '".NOW."', $completion_time)";
				$q = db_query($sql);
			}
			// Get response_id after insert
			$response_id = db_insert_id();
		}
		// Delete the submitted values on the API service if survey is completed
		if ($end_survey) {
			self::promisApiRequest($promis_api_base_url . self::api_version . "/Results/$assessment_id.json", array(), "DELETE");
		}
		// REDCap Hook injection point: Pass project_id and record name to method
		$group_id = (empty($Proj->groups)) ? null : Records::getRecordGroupId(PROJECT_ID, $fetched);
		if (!is_numeric($group_id)) $group_id = null;
		Hooks::call('redcap_save_record', array(PROJECT_ID, $fetched, $form_name, $_GET['event_id'], $group_id, $_GET['s'], $response_id));
		// Return record name and $response_id
		return array($fetched, $response_id);
	}
	

	// Retrieve the REDCap variable name using the PROMIS variable name (e.g., PAINBE08)
	// NOTE: The PROMIS variable name will be the text of the Section Header for the question.
	public static function getRedcapVarFromPromisVar($promis_var, $form_name)
	{
		global $Proj;
		// Loop through fields ONLY on this survey till we find the field who's Section Header matches
		foreach ($Proj->forms[$form_name]['fields'] as $field=>$label) {
			if ($Proj->metadata[$field]['element_preceding_header'] == $promis_var) {
				return $field;
			}
		}
		
	}
	

	// Retrieve PROMIS participant's responses, t-scores, and standard errors
	public static function getParticipantResponses($assessment_id, $params, $form_name, $end_survey)
	{
		global $Proj, $promis_api_base_url, $table_pk;
		
		// Add all response data into array
		$question_data = array();
		
		// Get results/scores
		$response = self::promisApiRequest($promis_api_base_url . self::api_version . "/Results/$assessment_id.json", $params);
		$results = json_decode($response, true);		
		
		// Calculate T-score and Standard Error (if we're finishing the survey)
		if ($end_survey) {
			$t_score = round(self::convertThetaToTScore($results['Items'][0]['Theta']), self::tscore_round_digits);
			$standard_error = round($results['Items'][0]['StdError']*10, self::tscore_round_digits);		
			// Obtain REDCap variable name of the final score and std error fields, and set their values
			$form_fields = array_keys($Proj->forms[$form_name]['fields']);
			$first_field = array_shift($form_fields);
			if ($first_field == $Proj->table_pk) {
				$score_field = array_shift($form_fields);
				$error_field = array_shift($form_fields);
			} else {
				$score_field = $first_field;
				$error_field = array_shift($form_fields);
			}
			$question_data[$score_field] = $t_score;
			$question_data[$error_field] = $standard_error;
		}
		
		// Reverse sort results so they are in chronilogically ascending order
		$results_array = $results['Items'];
		krsort($results_array);
		
		//print "<hr>RESULTS:";print_array($results_array);exit;		
		
		// Loop through each question in the form
		foreach ($results_array as $item) {
			// Get the REDCap variable names of this field and its associated current t-score and standard error fields
			$redcap_var = self::getRedcapVarFromPromisVar($item['ID'], $form_name);
			$redcap_var_tscore = $Proj->getNextField($redcap_var);
			$redcap_var_stderror = $Proj->getNextField($redcap_var_tscore);
			$redcap_var_qposition = $Proj->getNextField($redcap_var_stderror);
			// Add question position value to array
			$question_data[$redcap_var_qposition] = $item['Position'];
			// If question was not skipped, then get scores and Value of the choice selected
			if ($item['ItemResponseOID'] != self::empty_guid) {
				$question_data[$redcap_var_tscore] = round(self::convertThetaToTScore($item['Theta']), self::tscore_round_digits);
				$question_data[$redcap_var_stderror] = round($item['StdError']*10, self::tscore_round_digits);
				// Loop through this question's choices to find the Value of our selected
				foreach ($item['Elements'] as $element) {
					if (!isset($element['Map'])) continue;
					foreach ($element['Map'] as $element_choice) {
						if ($element_choice['ItemResponseOID'] != $item['ItemResponseOID']) continue;
						// Store data value for question in array
						$question_data[$redcap_var] = $element_choice['Value'];
						// Leave this question to go to next question
						break 2;
					}
				}
			}
		}
		
		// Return all data in this assessment
		return $question_data;
	}
	

	// Retrieve a PROMIS participant's score and raw results
	public static function convertThetaToTScore($theta)
	{
		return $theta*10 + 50;
	}
	

	// Determine if form is a PROMIS instrument downloaded from the Shared Library. Return boolean.
	public static function isPromisInstrument($form_name)
	{
		global $promis_enabled;
		if (!$promis_enabled) return false;
		$sql = "select 1 from redcap_library_map where project_id = ".PROJECT_ID." 
				and form_name = '".prep($form_name)."' and promis_key is not null 
				and promis_key != '' limit 1";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}
	

	// Return array *only* of PROMIS instruments in this project downloaded from the Shared Library.
	public static function getPromisInstruments()
	{
		global $promis_enabled;
		if (!$promis_enabled) return  array();
		$promis_forms = array();
		$sql = "select form_name from redcap_library_map where project_id = ".PROJECT_ID." 
				and promis_key is not null and promis_key != ''";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$promis_forms[] = $row['form_name'];
		}
		return $promis_forms;
	}
	

	// Return the PROMIS instrument key for a given form
	public static function getPromisKey($form_name)
	{
		$sql = "select promis_key from redcap_library_map where project_id = ".PROJECT_ID." 
				and form_name = '".prep($form_name)."' limit 1";
		$q = db_query($sql);
		if (db_num_rows($q) > 0) {
			return db_result($q, 0);
		} else {
			return false;
		}
	}
	
}