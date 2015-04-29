<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/


/**
 * Piping Class
 */
class Piping
{
	// Set string as the missing data replacement (underscores)
	const missing_data_replacement = "______";
	// Set piping receiver field CSS class
	const piping_receiver_class = "piping_receiver";
	// Set piping receiver field CSS class for prepending to field_name
	const piping_receiver_class_field = "piperec-";
	// Set regex for detecting conditional IF statement in a label
	// const conditional_if_regex = "/\[(if\()(.+)(,)(.+)(,)(.+)(\))\]/i";
	
	/**
	 * REPLACE VARIABLES IN LABEL
	 * Provide any test string and it will replace a [field_name] with its stored data value.
	 * @param array $record_data - Array of record data (record is 1st key, event_id is 2nd key, field is 3rd key) to be used for the replacement.
	 * @param int $event_id - The current event_id for the form/survey.
	 * @param string $record - The name of the record. If $record_data is empty/null, it will use $record to pull all relevant data for 
	 * that record to create $record_data on the fly.
	 * @param boolean $replaceWithUnderlineIfMissing - If true, replaces data value with 6 underscores, else does NOT replace anything.
	 * Returns the string with the replacements.
	 */
	public function replaceVariablesInLabel($label='', $record=null, $event_id=null, $record_data=array(), 
											$replaceWithUnderlineIfMissing=true, $project_id=null, $wrapValueInSpan=true)
	{
		// If label does not contain at least one [ and one ], then return the label as-is
		if (strpos($label, '[') === false || strpos($label, ']') === false) return $label;
		
		// If no record name nor data provided
		if (empty($record_data) && ($record == null || $record == '')) return $label;
		
		// Parse the label to pull out the events/fields used therein
		$fields = array_keys(getBracketedFields($label, true, true, false));
		
		// If no fields were found in string, then return the label as-is
		if (empty($fields)) return $label;
		
		// Set global vars that we can use in a callback function for replacing values inside HREF attributes of HTML link tags
		global $Proj, $lang, $piping_callback_global_string_to_replace, $piping_callback_global_string_replacement;
		
		// If we're not in a project-level script but have a project_id passed as a parameter, then instantiate $Proj
		if (defined('PROJECT_ID')) {
			$project_id = PROJECT_ID;
		} elseif (is_numeric($project_id)) {
			$Proj = new Project($project_id);
		}
		
		// Decode label, just in case
		$label = html_entity_decode($label, ENT_QUOTES);
		
		// Check upfront to see if the label contains a link
		$regex_link = "/(<)([^<]*)(href\s*=\s*)(\"|')([^\"']+)(\"|')([^<]*>)/i";
		$label_contains_link = preg_match($regex_link, $label);
		
		// Set array of disallowed field types as piping trasmitters
		if (isDev()) {
			$disallowedFieldTypesTransmitters = array('file', 'descriptive');
		} else {
			$disallowedFieldTypesTransmitters = array('checkbox', 'file', 'descriptive');
		}
		
		// Set flag if $record_data is provided
		$record_data_provided = (!empty($record_data));
		
		// Loop through fields, and if is longitudinal with prepended event names, separate out those with non-prepended fields
		$events = array();
		$fields_longi = array();
		$fields_classic = array();
		$fields_no_events = array();
		foreach ($fields as $this_key=>$this_field) 
		{
			// If longitudinal with a dot, parse it out and put unique event name in $events array
			if (strpos($this_field, '.') !== false) {
				// Add field to longi array
				$fields_longi[] = $this_field;
				// Separate event from field
				list ($this_event, $this_field) = explode(".", $this_field, 2);
				// Add field to fields_no_events array
				$fields_no_events[] = $this_field;
				// Put event in $events array
				$events[] = $this_event;
			} else {
				// Add field to fields_no_events array
				$fields_no_events[] = $fields_classic[] = $this_field;
			}
		}
		// Reorder $fields by putting prepended-event fields first
		$fields = array_merge($fields_longi, $fields_classic);
		// Perform unique on $events and $fields arrays
		$fields_no_events = array_unique($fields_no_events);
		$fields_longi = array_unique($fields_longi);
		$fields_classic = array_unique($fields_classic);
		// Also add current event to $events before setting as unique (because most of the time users will be using data from the current event)
		if (is_array($event_id)) {
			foreach ($event_id as $this_event_id) {
				$events[] = $this_event_id;
			}
		} else {
			$events[] = $event_id;
		}
		$events = array_unique($events);
		
		// If $record_data is not provided, obtain it via $record
		if (!$record_data_provided) {
			$record_data = Records::getData($project_id, 'array', $record, $fields_no_events, $events);
		}
		
		// Loop through all event-fields/fields and replace them with data in the label string
		foreach ($fields as $this_field) 
		{
			// If longitudinal with a dot, parse it out
			if (strpos($this_field, '.') !== false) {
				// Separate event from field
				list ($this_event, $this_field) = explode(".", $this_field, 2);
				// Format the event/field to the REDCap logic notation
				$string_to_replace = $string_to_replace_orig = "[$this_event][$this_field]";
				// Obtain event_id from unique event name
				$this_event_id = $Proj->getEventIdUsingUniqueEventName($this_event);
			} else {
				// Format the field to the REDCap logic notation
				$string_to_replace = $string_to_replace_orig = "[$this_field]";
				// If no prepended event name, then assume current event_id
				$this_event_id = $event_id;
			}
			// If $this_field is not a valid field, then do nothing and begin next loop
			if (!isset($Proj->metadata[$this_field])) continue;
			// Set field type
			$field_type = $Proj->metadata[$this_field]['element_type'];
			// If $this_field's field type is in the 'disallowed' list, skip and being next loop
			if (in_array($field_type, $disallowedFieldTypesTransmitters)) continue;
			// Set data_value
			$data_value = $record_data[$record][$this_event_id][$this_field];
			// If not data exists for this field AND the flag is set to not replace anything when missing, then stop this loop.
			$has_data_value = false;
			if (is_array($data_value)) {
				// Check all values to see if all are 0s
				
				$has_data_value = true;
				
			} else {
				// If \n (not a line break), then replace the backslash with its corresponding HTML character code) to
				// prevent parsing issues with MC field options that are piping receivers.
				$data_value = str_replace("\\n", "&#92;n", $data_value);
				if ($data_value != '') $has_data_value = true;
			}
			// Obtain data value for replacing
			if (isset($data_value) && $has_data_value) {
				// Get field's validation type				
				$field_validation = $Proj->metadata[$this_field]['element_validation_type'];
				// MC FIELD: If field is multiple choice, then replace using its option label and NOT its raw value
				if ($field_type == 'sql' || $Proj->isMultipleChoice($this_field)) {				
					// Parse enum choices into array
					$field_enum = $Proj->metadata[$this_field]['element_enum'];
					if ($field_type == 'sql') $field_enum = getSqlFieldEnum($field_enum);
					$choices = parseEnum($field_enum);
					// Replace data value with its option label
					if ($Proj->isCheckbox($this_field)) {
						// Set value as comma-delimited labels for Checkbox field
						$data_value2 = array();
						foreach ($choices as $this_code=>$this_label) {
							// Skip unchecked options
							if ($data_value[$this_code] == '0') continue;
							$data_value2[] = $this_label;
						}
						// Format the text as comma delimited						
						$data_value = implode($lang['comma']." ", $data_value2);
					} elseif (isset($choices[$data_value])) {
						// Set value as label for MC field
						$data_value = $choices[$data_value];
					} else {
						// If value is blank or orphaned (not a valid coded value), then set as blank
						$data_value = self::missing_data_replacement;
					}
				}
				// If data value is a formatted date (date[time] MDY or DMY), then reformat it from YMD to specified format
				elseif (substr($field_validation, 0, 4) == 'date' && (substr($field_validation, -4) == '_mdy' || substr($field_validation, -4) == '_dmy')) {
					$data_value = DateTimeRC::datetimeConvert($data_value, 'ymd', substr($field_validation, -3));
				}
			} else {
				// No data value saved yet
				$data_value = ($replaceWithUnderlineIfMissing) ? self::missing_data_replacement : '';
			}
			
			// Set string replacement text
			$string_replacement = 	// For text/notes fields, make sure we double-html-encode these + convert new lines 
									// to <br> tags to make sure that we end up with EXACTLY the same value and also to prevent XSS via HTML injection.
									(($field_type == 'textarea' || $field_type == 'text')
										? filter_tags(nl2br($data_value)) //htmlspecialchars(nl2br(htmlspecialchars($data_value, ENT_QUOTES)), ENT_QUOTES)
										: $data_value
									);
			$string_replacement_span = 	RCView::span(array('class'=>self::piping_receiver_class." ".self::piping_receiver_class_field."$this_event_id-$this_field"), 
											$string_replacement
										);
										
			// Before doing a general replace, let's first replace anything in the HREF attribute of a link.
			// Do a direct replace without the SPAN tag (because it won't work any other way), but this means that it can
			// never get updated dynamically via JavaScript if changed on the page (probably an okay assumption).
			if ($label_contains_link) {
				// Set global vars to be used in the callback function
				$piping_callback_global_string_to_replace = $string_to_replace;
				$piping_callback_global_string_replacement = $string_replacement;
				$label = preg_replace_callback($regex_link, "Piping::replaceVariablesInLabelCallback", $label);
			}
			
			// Now replace the event/field in the string. Put data in a span.
			$label = str_replace($string_to_replace, ($wrapValueInSpan ? $string_replacement_span : $string_replacement), $label);
		}
		
		// Return the label
		return $label;
	}
	
	
	// Callback function for replaceVariablesInLabel()
	public static function replaceVariablesInLabelCallback($matches)
	{
		// Set global vars that we can use in a callback function for replacing values inside HREF attributes of HTML link tags
		global $piping_callback_global_string_to_replace, $piping_callback_global_string_replacement;
		// Remove first element (because we just need to return the sub-elements)
		unset($matches[0]);
		// If label does not contain at least one [ and one ], then return the label as-is
		if (strpos($matches[5], '[') !== false && strpos($matches[5], ']') !== false) {
			// Now replace the event/field in the string
			$matches[5] = str_replace($piping_callback_global_string_to_replace, $piping_callback_global_string_replacement, $matches[5]);
		}
		// Return the matches array as a string with replaced text
		return implode("", $matches);
	}	
	
	
	/* 
	public static function evalulateConditionInLabel($label)
	{
		print $label."<br>";
		preg_match_all(self::conditional_if_regex, $label, $matches);
		print_array($matches);
		if (isset($matches[0])) {
			// String together the IF statement
			unset($matches[0]);
			$logic = implode("", $matches);
			print "<br>Condition: ".$logic;
			print "<br>is valid? ".(LogicTester::isValid($logic) ? "true" : "false");
			$record = '1';
			$logic_fields = array_keys(getBracketedFields($logic, true, true, true));
			print_array($logic_fields);
			$record_data = Records::getData('array', $record, $logic_fields);
			print "<br>Result: ";var_dump(LogicTester::evaluateCondition($logic, $record_data[$record]));
		}
	}
	 */
	 
	/**
	 * PIPING EXPLANATION
	 * Output general instructions and documentation on how to utilize the piping feature.
	 */
	public static function renderPipingInstructions()
	{
		global $lang;
		// Place all HTML into $h
		$h = '';
		// 
		$h .= 	RCView::div('', 
					$lang['design_457'] . " " .
					RCView::a(array('href'=>'https://redcap.vanderbilt.edu/surveys/?s=ph9ZIB', 'target'=>'_blank', 'style'=>'text-decoration:underline;'), $lang['design_476']) .
					$lang['period']
				) .
				RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_458']) .
				RCView::div('', 
					$lang['design_459'] .
					RCView::div(array('style'=>'margin-top:5px;margin-left:15px;'), "&bull; " . $lang['global_40']) .
					RCView::div(array('style'=>'margin-left:15px;'), "&bull; " . $lang['database_mods_69']) .
					RCView::div(array('style'=>'margin-left:15px;'), "&bull; " . $lang['database_mods_65']) .
					RCView::div(array('style'=>'margin-left:15px;'), "&bull; " . $lang['design_461']) .
					RCView::div(array('style'=>'margin-left:15px;'), "&bull; " . $lang['design_462']) .
					RCView::div(array('style'=>'margin-left:15px;'), "&bull; " . $lang['design_460']) .
					RCView::div(array('style'=>'margin-left:15px;'), "&bull; " . $lang['survey_65']) .
					RCView::div(array('style'=>'margin-left:15px;'), "&bull; " . $lang['survey_747']) .
					RCView::div(array('style'=>'margin-left:15px;'), "&bull; " . $lang['design_464']) .
					RCView::div(array('style'=>'margin-left:15px;'), "&bull; " . $lang['design_506']) .
					RCView::div(array('style'=>'margin-left:15px;'), "&bull; " . $lang['design_513'])
				) .
				RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_470']) .
				RCView::div('', $lang['design_471']) .
				RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_465']) .
				RCView::div('', $lang['design_466']) .
				RCView::div(array('style'=>'margin:10px 0 0;'), $lang['design_469']) .
				RCView::div(array('style'=>'margin:10px 0 0;'), $lang['design_467']) .
				## Example images
				// Example 1
				RCView::div(array('style'=>'color:#800000;margin:40px 0 10px;font-size:14px;font-weight:bold;'), 
					$lang['design_472'] . " 1"
				) .
				RCView::div(array('style'=>'margin:5px 0 0;'), 
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_475']) .
					RCView::img(array('src'=>'piping_example_mc1c.png', 'style'=>'border:1px solid #666;'))
				) . 
				RCView::div(array('style'=>'margin:5px 0 0;'), 
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_473']) .
					RCView::img(array('src'=>'piping_example_mc1a.png', 'style'=>'border:1px solid #666;'))
				) . 
				RCView::div(array('style'=>'margin:5px 0 0;'), 
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_474']) .
					RCView::img(array('src'=>'piping_example_mc1b.png', 'style'=>'border:1px solid #666;'))
				) .
				// Example 2
				RCView::div(array('style'=>'color:#800000;margin:40px 0 10px;font-size:14px;font-weight:bold;'), 
					$lang['design_472'] . " 2"
				) .
				RCView::div(array('style'=>'margin:5px 0 0;'), 
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_475']) .
					RCView::img(array('src'=>'piping_example_text1a.png', 'style'=>'border:1px solid #666;'))
				) . 
				RCView::div(array('style'=>'margin:5px 0 0;'), 
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_473']) .
					RCView::img(array('src'=>'piping_example_text1b.png', 'style'=>'border:1px solid #666;'))
				) . 
				RCView::div(array('style'=>'margin:5px 0 0;'), 
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_474']) .
					RCView::img(array('src'=>'piping_example_text1c.png', 'style'=>'border:1px solid #666;'))
				)
				;
		// Return HTML
		return $h;
	}
}
