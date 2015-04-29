<?php

class Calculate 
{

	private $_results = array();
	private $_equations = array();
	
	
    public function feedEquation($name, $string) 
	{
		//Add field to calculated field list
		array_push($this->_results, $name);
		
		// Format logic to JS format
		$string = LogicTester::formatLogicToJS(html_entity_decode($string, ENT_QUOTES), true, $_GET['event_id']);
		
		array_push($this->_equations, $string);
    }
	
	
	public function exportJS() 
	{
		$result  = "\n<!-- Calculations -->";			
		$result .= "\n<script type=\"text/javascript\">\n";
		$result .= "function calculate(isOnPageLoad){\n";
		
		for ($i = 0; $i < sizeof($this->_results); $i++) 
		{
			// Set string for try/catch
			if (isset($_GET['__showerrors'])) {
				$try = "";
				$catch = "";
			} else {
				$try = "try{";
				$catch = "}catch(e){calcErr('" . $this->_results[$i] . "')}";
			}
			$result .= "  $try var varCalc_" . $i . "=" . html_entity_decode($this->_equations[$i], ENT_QUOTES) . ";";
			$result .= "var varCalc_" . $i . "b=document.form." . $this->_results[$i] . ".value;";
			$result .= "document.form." . $this->_results[$i] . ".value=isNumeric(varCalc_{$i})?varCalc_{$i}:'';";
			$result .= "if(varCalc_" . $i . "b!=document.form." . $this->_results[$i] . ".value){dataEntryFormValuesChanged=true;}";
			$result .= "$catch\n";
		}
		
		$result .= "  try{ updateCalcPipingReceivers(!!isOnPageLoad) }catch(e){ }\n";	
		$result .= "  return false;\n";			
		$result .= "}\n";
		$result .= "calcErrExist = calculate(true);\n";	
		$result .= "</script>\n";
		
		$result .= "<script type=\"text/javascript\">\n";
		$result .= "if(calcErrExist){calcErr2()}\n";
		$result .= "</script>\n";
		
		return $result;
	}
	
	/**
	 * Calculates values of multiple calc fields and returns array with field name as key 
	 * with both existing value and calculated value
	 * @param array $calcFields Array of calc fields to calculate (if contains non-calc fields, they will be removed automatically) - if an empty array, then assumes ALL fields in project. 
	 * @param array $records Array of records to perform the calculations for (if an empty array, then assumes ALL records in project). 
	 */
	public static function calculateMultipleFields($records=array(), $calcFields=array(), $returnIncorrectValuesOnly=false, $current_event_id)
	{
		// Call form functions
		require_once APP_PATH_DOCROOT . "ProjectGeneral/form_renderer_functions.php";
		// Validate $current_event_id
		if (!is_numeric($current_event_id)) $current_event_id = 'all';
		// Get globals
		global $Proj, $user_rights;
		// Return fields/values in $calcs array
		$calcs = array();
		// Validate as a calc field. If not a calc field, remove it.
		$calcFieldsNew = array();
		if (!is_array($calcFields) || empty($calcFields)) $calcFields = array_keys($Proj->metadata);
		foreach ($calcFields as $this_field) {
			if (isset($Proj->metadata[$this_field]) && $Proj->metadata[$this_field]['element_type'] == 'calc') {
				// Add to array of calc fields
				$calcFieldsNew[$this_field] = $Proj->metadata[$this_field]['element_enum'];
			}
		}
		$calcFields = $calcFieldsNew;
		unset($calcFieldsNew);
		// Get unique event names (with event_id as key)
		$events = $Proj->getUniqueEventNames();
		$eventNameToId = array_flip($events);
		$eventsUtilizedAllFields = array();
		// Create anonymous PHP functions from calc eqns
		$fieldToLogicFunc = $logicFuncToArgs = $logicFuncToCode = array();
		foreach ($calcFields as $this_field=>$this_logic) {
			// Format calculation to PHP format
			$this_logic = self::formatCalcToPHP($this_logic);
			// Array to collect list of which events are utilized the logic
			$eventsUtilized = array();
			if ($Proj->longitudinal) {
				// Longitudinal
				foreach (array_keys(getBracketedFields($this_logic, true, true, false)) as $this_field2)
				{
					// Check if has dot (i.e. has event name included)
					if (strpos($this_field2, ".") !== false) {
						list ($this_event_name, $this_field2) = explode(".", $this_field2, 2);
						// Get the event_id
						$this_event_id = array_search($this_event_name, $events);
						// Add event/field to $eventsUtilized array
						if (is_numeric($this_event_id))	$eventsUtilized[$this_event_id] = true;
					} else {
						// Add event/field to $eventsUtilized array
						$eventsUtilized[$current_event_id] = true;
					}
				}
			} else {
				// Classic
				$eventsUtilized[$current_event_id] = true;
			}
			// Add to $eventsUtilizedAllFields
			$eventsUtilizedAllFields = $eventsUtilizedAllFields + $eventsUtilized;
			// If classic or if using ALL events in longitudinal, then loop through all events to get this logic for ALL events
			$eventsUtilizedLogic = array();
			if (!$Proj->longitudinal) {
				// Classic
				$eventsUtilizedLogic[$Proj->firstEventId] = $this_logic;
			} else {
			// } elseif ($current_event_id == 'all' && isset($eventsUtilized['all']))) {
				// Longitudinal: Loop through each event and add
				foreach (array_keys($Proj->eventInfo) as $this_event_id) {
					// Make sure this calc field is utilized on this event
					if (in_array($Proj->metadata[$this_field]['form_name'], $Proj->eventsForms[$this_event_id])) {
						$eventsUtilizedLogic[$this_event_id] = LogicTester::logicPrependEventName($this_logic, $Proj->getUniqueEventNames($this_event_id));
					}
				}
			} 
			## THIS 'ELSE' WAS REMOVED BECAUSE IT SEEMS THAT IF A CALC FIELD REFERENCES ANOTHER CALC FIELD ON A PREVIOUS EVENT,
			## IN WHICH THAT OTHER FIELD DOES NOT USE EVENT-SPECIFIC LOGIC, THEN THE PREVIOUS EVENT GETS SKIPPED AND DOES NOT GET ADDED TO 
			## THE ARRAY $eventsUtilizedLogic AND THUS RESULTS IN EMPTY VALUES, WHICH THEN CASCADE DOWN FURTHER TO OTHER CALC FIELDS.
			/* 
			else {
				// Longitudinal: Loop through all relevant events for this field
				foreach (array_keys($eventsUtilized) as $this_event_id) {
					// Make sure this calc field is utilized on this event
					if (in_array($Proj->metadata[$this_field]['form_name'], $Proj->eventsForms[$this_event_id])) {
						$eventsUtilizedLogic[$this_event_id] = LogicTester::logicPrependEventName($this_logic, $Proj->getUniqueEventNames($this_event_id));
					}
				}
			}
			 */
			// If there is an issue in the logic, then return an error message and stop processing
			foreach ($eventsUtilizedLogic as $this_event_id=>$this_loop_logic) {
				$funcName = null;
				$args = array();
				try {
					// Instantiate logic parse
					$parser = new LogicParser();
					list($funcName, $argMap) = $parser->parse($this_loop_logic, $eventNameToId, true, true);
					$logicFuncToArgs[$funcName] = $argMap;
					$logicFuncToCode[$funcName] = $parser->generatedCode;
					$fieldToLogicFunc[$this_event_id][$this_field] = $funcName;
				}
				catch (Exception $e) {
					unset($calcFields[$this_field]);
				}
			}
		}
		if (!empty($calcFields)) {
			// GET ALL FIELDS USED IN EQUATIONS
			$dependentFields = getDependentFields(array_keys($calcFields), true, false);
			// Get data for all calc fields and all their dependent fields
			$recordData = Records::getData('array', $records, array_merge(array_keys($calcFields), $dependentFields), 
							(isset($eventsUtilizedAllFields['all']) ? array_keys($Proj->eventInfo) : array_keys($eventsUtilizedAllFields)), 
							$user_rights['group_id']);
			// Loop through all calc values in $recordData
			foreach ($recordData as $record=>&$this_record_data) {
				foreach (array_keys($this_record_data) as $event_id) {
					// Loop through ONLY calc fields in each event
					foreach (array_keys($calcFields) as $field) {
						// Get saved calc field value
						$savedCalcVal = $this_record_data[$event_id][$field];
						// If project is longitudinal, make sure field is on a designated event
						if ($Proj->longitudinal && !in_array($Proj->metadata[$field]['form_name'], $Proj->eventsForms[$event_id])) continue;
						// Calculate what SHOULD be the calculated value
						$funcName = $fieldToLogicFunc[$event_id][$field];
						$calculatedCalcVal = LogicTester::evaluateCondition(null, $this_record_data, $funcName, $logicFuncToArgs[$funcName]);
						// Change the value in $this_record_data for this record-event-field to the calculated value in case other calcs utilize it
						$this_record_data[$event_id][$field] = $calculatedCalcVal;
						// Now compare the saved value with the calculated value
						$is_correct = !($calculatedCalcVal !== false && $calculatedCalcVal."" != $savedCalcVal."");
						// Precision Check: If both are floating point numbers and within specific range of each other, then leave as-is
						if (!$is_correct) {
							// Convert temporarily to strings
							$calculatedCalcVal2 = $calculatedCalcVal."";
							$savedCalcVal2 = $savedCalcVal."";
							// Neither must be blank AND one must have decimal
							if ($calculatedCalcVal2 != "" && $savedCalcVal2 != "") {
								// Get position of decimal
								$calculatedCalcVal2Pos = strpos($calculatedCalcVal2, ".");
								$savedCalcVal2Pos = strpos($savedCalcVal2, ".");
								if ($calculatedCalcVal2Pos !== false || $savedCalcVal2Pos !== false) {
									// If numbers have differing precision, then round both to lowest precision of the two and compare
									$precision1 = strlen(substr($calculatedCalcVal2, $calculatedCalcVal2Pos+1));
									$precision2 = strlen(substr($savedCalcVal2, $savedCalcVal2Pos+1));
									$precision3 = ($precision1 < $precision2) ? $precision1 : $precision2;
									// Check if they are the same number after rounding
									$is_correct = (round($calculatedCalcVal, $precision3)."" == round($savedCalcVal, $precision3)."");
								}
							}
						}
						// If flag is set to only return incorrect values, then go to next value if current value is correct
						if ($returnIncorrectValuesOnly && $is_correct) continue;
						// Add to array
						$calcs[$record][$event_id][$field] = array('saved'=>$savedCalcVal."", 'calc'=>$calculatedCalcVal."", 'is_correct'=>$is_correct);
					}
				}
				// Remove data as we go
				unset($recordData[$record]);
			}
		}
		// Return array of values
		return $calcs;
	}
	
	
	/**
	 * For specific records and calc fields given, perform calculations to update those fields' values via server-side scripting.
	 * @param array $calcFields Array of calc fields to calculate (if contains non-calc fields, they will be removed automatically) - if an empty array, then assumes ALL fields in project. 
	 * @param array $records Array of records to perform the calculations for (if an empty array, then assumes ALL records in project). 
	 */
	public static function saveCalcFields($records=array(), $calcFields=array(), $current_event_id='all')
	{
		global $Proj;
		// Validate $current_event_id
		if (!is_numeric($current_event_id)) $current_event_id = 'all';
		// Return number of calculations that were updated/saved
		$calcValuesUpdated = 0;
		// Perform calculations on ALL calc fields over ALL records, and return those that are incorrect
		$calcFieldData = self::calculateMultipleFields($records, $calcFields, true, $current_event_id);
		if (!empty($calcFieldData)) {
			// Keep original POST array, just in case we need to restore it (because we will need to overrite it)
			$origPost = $_POST;		
			// Save original event_id, if was set
			if (isset($_GET['event_id'])) $event_id_orig = $_GET['event_id'];
			// Loop through all calc values in $calcFieldData
			foreach ($calcFieldData as $record=>&$this_record_data) {
				foreach ($this_record_data as $event_id=>&$this_event_data) {
					// Remove the DDE-ending if using DDE
					$record = removeDDEending($record);
					// Save new values for this record/event
					// Simulate new Post submission (as if submitted via data entry form)
					$_POST = array($Proj->table_pk=>$record);
					// Add calculated values to Post
					foreach ($this_event_data as $field=>$attr) {
						$_POST[$field] = $attr['calc'];
						$calcValuesUpdated++;
					}
					// Need event_id in query string for saving properly
					$_GET['event_id'] = $event_id; 
					// Save values
					saveRecord($record, false);
				}
			}
			// Restore POST array
			$_POST = $origPost;
			// Reset original event_id
			if (isset($event_id_orig)) $_GET['event_id'] = $event_id_orig;
		}
		// Return number of calculations that were updated/saved
		return $calcValuesUpdated;
	}
	
	
	/**
	 * Determine all calc fields based upon a trigger field used in their calc equation. Return as array of fields.
	 * Also return any calc fields that are found in $triggerFields as well.
	 */
	public static function getCalcFieldsByTriggerField($triggerFields=array(), $do_recursive=true)
	{
		global $Proj;
		// Array to capture the calc fields
		$calcFields = array();
		// Validate $triggerFields and add field to SQL where clause
		$triggerFieldsRegex = array();
		foreach ($triggerFields as $key=>$field) {
			if (isset($Proj->metadata[$field])) {
				if ($Proj->metadata[$field]['element_type'] == 'calc') {
					// If this field is a calc field, then add it to $calcFields automatically
					$calcFields[] = $field;
				} elseif ($Proj->isCheckbox($field)) {
					// Loop through all checkbox choices and add each
					foreach (parseEnum($Proj->metadata[$field]['element_enum']) as $code=>$label) {
						// Add to trigger fields regex array
						$triggerFieldsRegex[] = preg_quote("[$field($code)]");
					}
				} else {
					// Add to trigger fields regex array
					$triggerFieldsRegex[] = preg_quote("[$field]");
				}
			}
		}
		// Create regex string
		$regex = "/(" . implode("|", $triggerFieldsRegex) .")/";
		// Now loop through all calc fields to see if any trigger field is used in its equation
		foreach ($Proj->metadata as $field=>$attr) {
			if ($attr['element_type'] == 'calc' && $attr['element_enum'] != '' && 
				// Add if one field is used in the equation OR if no fields are used (means that it's purely numerical - unlikely but possible)
				(strpos($attr['element_enum'], "[") === false || preg_match($regex, $attr['element_enum'])))
			{
				$calcFields[] = $field;
			}
		}
		// Do array unique
		$calcFields = array_values(array_unique($calcFields));
		// In case some calc fields are used by other calc fields, do a little recursive check to get ALL calc fields used
		if ($do_recursive) {
			$loop = 1;
			do {
				// Get original field count
				$countCalcFields = count($calcFields);
				// Get more dependent calc fields, if any
				$calcFields = self::getCalcFieldsByTriggerField($calcFields, false);
				// Prevent over-looping, just in case
				$loop++;
			} while ($loop < 100 && $countCalcFields < count($calcFields));
		}
		// Return array
		return $calcFields;
	}
	
	// Replace all instances of "NaN" and 'NaN' in string is_nan()
	public static function replaceNaN($string)
	{
		// Return if not applicable
		if ($string == '') return '';
		if (strpos($string, "'NaN'") === false && strpos($string, '"NaN"') === false) return $string;
		// Pad with spaces to avoid certain parsing issues
		$string = " $string ";
		// Do regex replacement to format string for parsing purposes
		$string = preg_replace(	array("/('|\")(NaN)('|\")(\s*)(=|!=|<>)/", "/(=|!=|<>)(\s*)('|\")(NaN)('|\")/"), 
								array("'NaN'$5", "$1'NaN'"), $string);
		$string = str_replace(array("'NaN'<>", "<>'NaN'"), array("'NaN'!=", "!='NaN'"), $string);
		
		//print "<hr><b>$string</b>";
		
		// Set max loops to prevent infinite looping mistakenly
		$max_loops = 200;
		
		// Replace "'NaN'=" and "'NaN'!="
		$nanStrings = array("'NaN'=", "'NaN'!=");
		foreach ($nanStrings as $nanString) {
			$nanStringLen = strlen($nanString);
			$nanPos = strpos($string, $nanString);
			$loop_num = 1;
			while ($nanPos !== false && $loop_num <= $max_loops) {
				// How many nested parentheses we're inside of
				$nested_paren_count = 0;
				$string_len = strlen($string);
				// Capture the position to put the closing parenthesis for is_nan() - default to the length of the string
				$isnanCloseInsertParenPos = $string_len;
				// Loop through each letter in string to find where the logical close will be for the expression
				for ($i = $nanPos; $i <= $string_len; $i++) {
					// Get current character
					$letter = substr($string, $i, 1);
					if ($i == $string_len) {
						// BINGO! This is the last letter of the string, so this must be it
						$isnanCloseInsertParenPos = $i;
					} elseif ($letter == "(") {
						// Increment the count of how many nested parentheses we're inside of
						$nested_paren_count++;
					} elseif (($letter == ")" || $letter == ",") && $nested_paren_count == 0) {
						// BINGO!
						$isnanCloseInsertParenPos = $i;
						break;
					} elseif ($letter == ")") {
						// We just left a nested parenthesis, so reduce count by 1 and keep looping
						$nested_paren_count--;
					}
				}
				// Rebuild the string and insert the is_nan() function
				$string = substr($string, 0, $nanPos) . (strpos($nanString, "!") === false ? "" : "!") . "is_nan(" 
						. substr($string, $nanPos+$nanStringLen, $isnanCloseInsertParenPos-$nanPos-$nanStringLen)
						. ")" . substr($string, $isnanCloseInsertParenPos);
				// Set value for next loop, if needed
				$nanPos = strpos($string, $nanString);
				// Increment loop num
				$loop_num++;
			}
		}
		
		// Replace "='NaN'" and "!='NaN'"
		$nanStrings = array("!='NaN'", "='NaN'");
		foreach ($nanStrings as $nanString) {
			$nanStringLen = strlen($nanString);
			$nanPos = strpos($string, $nanString);
			$loop_num = 1;
			while ($nanPos !== false && $loop_num <= $max_loops) {
				// How many nested parentheses we're inside of
				$nested_paren_count = 0;
				$string_len = strlen($string);
				// Capture the position to put the closing parenthesis for is_nan() - default to the length of the string
				$isnanCloseInsertParenPos = 0;
				// Loop through each letter in string to find where the logical close will be for the expression
				for ($i = $nanPos; $i >= 0; $i--) {
					// Get current character
					$letter = substr($string, $i, 1);
					if ($i == 0) {
						// BINGO! This is the first letter of the string, so this must be it
						$isnanCloseInsertParenPos = $i;
					} elseif ($letter == ")") {
						// Increment the count of how many nested parentheses we're inside of
						$nested_paren_count++;
					} elseif (($letter == "(" || $letter == ",") && $nested_paren_count == 0) {
						// BINGO!
						$isnanCloseInsertParenPos = $i;
						break;
					} elseif ($letter == "(") {
						// We just left a nested parenthesis, so reduce count by 1 and keep looping
						$nested_paren_count--;
					}
				}
				//print "<br>\$nanPos: $nanPos, \$isnanCloseInsertParenPos: $isnanCloseInsertParenPos, \$nanStringLen: $nanStringLen";
				$string = substr($string, 0, $isnanCloseInsertParenPos+1) . (strpos($nanString, "!") === false ? "" : "!")
						. "is_nan(" . substr($string, $isnanCloseInsertParenPos+1, $nanPos-$isnanCloseInsertParenPos-1)
						. ")" . substr($string, $nanPos+$nanStringLen);
				//print "<br>$string";
				// Set value for next loop, if needed
				$nanPos = strpos($string, $nanString);
				// Increment loop num
				//print "<br>\$loop_num: $loop_num";
				$loop_num++;
			}
		}
		// Trim the string and return it
		return trim($string);
	}
	
	// Replace round() in calc field with roundRC(), which returns FALSE with non-numbers
	public static function replaceRoundRC($string)
	{
		// Deal with round(, if any are present
		$regex = "/(round)(\s*)(\()/";
		if (strpos($string, "round") !== false && preg_match($regex, $string)) {
			// Replace all instances of round( with roundRC(
			$string = preg_replace($regex, "roundRC(", $string);
		}
		return $string;
	}
	
	// Replace all instances of "log" in string with "logRC" to handle non-numbers
	public static function replaceLog($string)
	{
		// Deal with log(, if any are present
		$regex = "/(log)(\s*)(\()/";
		if (strpos($string, "log") !== false && preg_match($regex, $string)) {
			// Replace all instances of log( with logRC(
			$string = preg_replace($regex, "logRC(", $string);
		}
		return $string;
	}

	// Wrap all field names with chkNull (except date/time fields)
	public static function addChkNull($string)
	{
		global $Proj;
		// Loop through all fields used in logic
		$all_logic_fields = $all_logic_fields_events = array();
		foreach (array_keys(getBracketedFields($string, true, true)) as $field) {
			if (strpos($field, ".") !== false) {
				// Event is prepended
				list ($event, $field) = explode(".", $field);
				if ($Proj->isCheckbox($field)) {
					// Loop through all options
					foreach (array_keys(parseEnum($Proj->metadata[$field]['element_enum'])) as $code) {
						$all_logic_fields_events["[$event][$field($code)]"] = "chkNull([$event--RCEVT--$field($code)])";
					}
				} else {
					// Ignore if a date/time field (they shouldn't get wrapped in chkNull)
					$fieldValidation = $Proj->metadata[$field]['element_validation_type'];
					if (!($Proj->metadata[$field]['element_type'] == 'text' 
						&& ($fieldValidation == 'time' || substr($fieldValidation, 0, 4) == 'date'))) 
					{
						$all_logic_fields_events["[$event][$field]"] = "chkNull([$event--RCEVT--$field])"; // Add --RCEVT-- to replace later on so that non-event field replacement won't interfere
					}
				}
			} else {
				// Normal field syntax (no prepended event)
				if ($Proj->isCheckbox($field)) {
					// Loop through all options
					foreach (array_keys(parseEnum($Proj->metadata[$field]['element_enum'])) as $code) {
						$all_logic_fields["[$field($code)]"] = "chkNull([$field($code)])";
					}
				} else {
					// Ignore if a date/time field (they shouldn't get wrapped in chkNull)
					$fieldValidation = $Proj->metadata[$field]['element_validation_type'];
					if (!($Proj->metadata[$field]['element_type'] == 'text' 
						&& ($fieldValidation == 'time' || substr($fieldValidation, 0, 4) == 'date'))) 
					{
						$all_logic_fields["[$field]"] = "chkNull([$field])"; // Add --RCEVT-- to replace later on so that non-event field replacement won't interfere
					}
				}
			}
		}	
		// Now through through all replacement strings and replace
		foreach ($all_logic_fields_events as $orig=>$repl) {
			$string = str_replace($orig, $repl, $string);
		}
		foreach ($all_logic_fields as $orig=>$repl) {
			$string = str_replace($orig, $repl, $string);
		}
		$string = str_replace("--RCEVT--", "][", $string);
		// Return the filtered string with chkNull
		return $string;
	}
	
	
	// Replace all literal date values inside datediff()
	public static function replaceDatediffLiterals($string)
	{
		// Deal with datediff(), if any are present
		$regex = "/(datediff)(\s*)(\()(\s*)/";
		$dd_func_paren = "datediff(";
		
		// If string contains datediff(), then reformat so that no spaces exist between it and parenthesis (makes it easier to process)
		if (strpos($string, "datediff") !== false && preg_match($regex, $string)) {
			// Replace strings
			$string = preg_replace($regex, $dd_func_paren, $string);
		} else {
			// No datediffs, so return 
			return $string;
		}
		
		// Set other variables to be used
		$dd_func_paren_replace = "rcr-diff(";
		$dd_func_paren_len = strlen($dd_func_paren);
		
		// Loop through each datediff instance in string
		$num_loops = 0;
		$max_loops = 200;
		$dd_pos = strpos($string, $dd_func_paren);
		while ($dd_pos !== false && preg_match($regex, $string) && $num_loops < $max_loops) {
			// Replace this current datediff with another string (so we know we're working on it)
			$string = substr($string, 0, $dd_pos) . $dd_func_paren_replace . substr($string, $dd_pos+$dd_func_paren_len);	
			// Explode the string to get the first parameters
			$first_of_string = substr($string, 0, $dd_pos+$dd_func_paren_len);
			list ($first_param, $second_param, $third_param, $fourth_param, $last_of_string) = explode(",", substr($string, $dd_pos+$dd_func_paren_len), 5);
			// Trim params
			$first_param = trim($first_param);
			$second_param = trim($second_param);
			$third_param = trim($third_param);
			$fourth_param = trim($fourth_param);
			$fourth_param_beginning = strtolower(substr($fourth_param, 0, 5));
			// Get the date format (if not specific, then assumes YMD, in which case it's okay and we can leave here and return string as-is.
			if (in_array($fourth_param_beginning, array("'mdy'", "'dmy'", '"mdy"', '"dmy"'))) {
				// Get date format
				$date_format = substr($fourth_param, 1, 3);
				// Check each param and convert to YMD format if a MDY or DMY literal date
				$first_param_charcheck = substr($first_param, 0, 1).substr($first_param, 3, 1).substr($first_param, 6, 1).substr($first_param, -1);
				if (($first_param_charcheck == '"--"' || $first_param_charcheck == "'--'")) {
					// This is a literal date, so convert it to YMD.
					$first_param_no_quotes = substr($first_param, 1, -1);
					// Convert date to YMD and wrap with quotes
					$first_param = '"' . DateTimeRC::datetimeConvert($first_param_no_quotes, $date_format, 'ymd') . '"';
				}
				$second_param_charcheck = substr($second_param, 0, 1).substr($second_param, 3, 1).substr($second_param, 6, 1).substr($second_param, -1);
				if (($second_param_charcheck == '"--"' || $second_param_charcheck == "'--'")) {
					// This is a literal date, so convert it to YMD.
					$second_param_no_quotes = substr($second_param, 1, -1);
					// Convert date to YMD and wrap with quotes
					$second_param = '"' . DateTimeRC::datetimeConvert($second_param_no_quotes, $date_format, 'ymd') . '"';
				}
				// Splice the string back together again
				$string = $first_of_string . "$first_param, $second_param, $third_param, $fourth_param" . ($last_of_string == null ? '' : ", $last_of_string");
			}
			// Check string again for an instance of "datediff" to see if we should keep looping
			$dd_pos = strpos($string, $dd_func_paren);
			// Increment loop
			$num_loops++;
		}
		// Unreplace "datediff"
		$string = str_replace($dd_func_paren_replace, $dd_func_paren, $string);
		// Return string
		return $string;
	}
	
	
	// Format calculation to PHP format
	public static function formatCalcToPHP($string)
	{
		// Replace any instances of round() with roundRC()
		$string = self::replaceRoundRC($string);
		// Wrap all field names with chkNull (except date/time fields)
		$string = self::addChkNull($string);
		// Replace all instances of "NaN" and 'NaN' in string with ""
		$string = self::replaceNaN($string);
		// Replace all instances of "log" in string with "logRC" to handle non-numbers
		$string = self::replaceLog($string);
		// Replace all literal date values inside datediff()
		$string = self::replaceDatediffLiterals($string);
		// Return formatted string
		return $string;
	}
	
}
