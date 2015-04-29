<?php

// Require math functions in case special functions are used in the conditional logic
require_once APP_PATH_DOCROOT . 'ProjectGeneral/math_functions.php';


/**
 * LogicTester
 * This class is used for execution/testing of logic used in Data Quality, branching logic, Automated Invitations, etc.
 */
class LogicTester
{	
	/**
	 * Tests the logic with existing data and returns boolean as TRUE if all variables have values 
	 * and the logic evaluates as true. Otherwise, return FALSE.
	 * @param string $logic is the raw logic provided by the user in the branching logic/Data Quality logic format.
	 * @param array $record_data holds the record data with event_id as first key, field name as second key,
	 * and value as data value (if checkbox, third key is raw coded value with value as 0/1).
	 */
	public static function apply($logic, $record_data=array())
	{
		global $Proj;
		// Get unique event names (with event_id as key)
		$events = $Proj->getUniqueEventNames();	
		// If there is an issue in the logic, then return an error message and stop processing
		$funcName = null;
		try {
			// Instantiate logic parser
			$parser = new LogicParser();
			list ($funcName, $argMap) = $parser->parse($logic, array_flip($events));
			// print $parser->generatedCode;
		}
		catch (LogicException $e) {
			// print "Error: ".$e->getMessage();
			return false;
		}
		// Execute the logic to return boolean (return TRUE if is 1 and not 0 or FALSE)
		return (self::applyLogic($funcName, $argMap, $record_data) === 1);
	}
	
	
	/**
	 * Check if the logic is syntactically valid
	 */
	public static function isValid($logic)
	{
		$parser = new LogicParser();
		try {
			$parser->parse($logic, null, false);
			return true;
		} catch (LogicException $e) {
			return false;
		}
	}
	
	
	/**
	 * Evaluate a logic string for a given record
	 */
	public static function evaluateLogicSingleRecord($raw_logic, $record, $record_data=null) 
	{
		global $Proj;
		// Check the logic to see if it's syntactically valid
		if (!self::isValid($raw_logic)) {
			return false;
		}
		// Array to collect list of all fields used in the logic
		$fields = array();
		$events = ($Proj->longitudinal) ? array() : array($Proj->firstEventId);
		// Loop through fields used in the logic. Also, parse out any unique event names, if applicable
		foreach (array_keys(getBracketedFields($raw_logic, true, true, false)) as $this_field)
		{
			// Check if has dot (i.e. has event name included)
			if (strpos($this_field, ".") !== false) {
				list ($this_event_name, $this_field) = explode(".", $this_field, 2);
				$events[] = $this_event_name;
			}
			// Verify that the field really exists (may have been deleted). If not, stop here with an error.
			if (!isset($Proj->metadata[$this_field])) return false;
			// Add field to array
			$fields[] = $this_field;
		}
		$events = array_unique($events);
		// Obtain array of record data (including default values for checkboxes and Form Status fields)
		if ($record_data == null) {
			// Retrieve data from data table since $record_data array was not passed as parameter
			$record_data = Records::getData($Proj->project_id, 'array', $record, $fields, $events);
		}
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
		// Apply the logic and return the result (TRUE = all conditions are true)
		return self::apply($raw_logic, $record_data[$record]);
	}
	
	
	/**
	 * Runs the logic function and returns the *COMPLEMENT* of the result;
	 * @param string $funcName the name of the function to execute.
	 * @param array $recordData first key is the event name, second key is the
	 * field name, and third key is either the field value, or if the field is
	 * a checkbox, it will be an array of checkbox codes => values.
	 * @param string $currEventId the event ID of the current record being examined.
	 * @param array $rule_attr a description of the Data Quality rule.
	 * @param array $args used to inform the caller of the arguments that were
	 * actually used in the rule logic function.
	 * @param array $useArgs if given, this function will use these arguments
	 * instead of running $this->buildLogicArgs().
	 * @return 0 if the function returned false, 1 if the result is non-false, and
	 * false if an exception was thrown.
	 */
	public static function applyLogic($funcName, $argMap=array(), $recordData=array(), $firstEventId=null)
	{
		$args = array();
		try {
			if (!self::buildLogicArgs($argMap, $recordData, $args, $firstEventId)) {
				throw new Exception("recordData does not contain the parameters we need");
			}
			$logicCheckResult = call_user_func_array($funcName, $args);
			return ($logicCheckResult === false ? 0 : 1);
		}
		catch (Exception $e) {
			return false;
		}
	}
	
	/**
	 * Builds the arguments to an anonymous function given record data.
	 * @param string $funcName the name of the function to build args for.
	 * @param array $recordData first key is the event name, second key is the
	 * field name, and third key is either the field value, or if the field is
	 * a checkbox, it will be an array of checkbox codes => values.
	 * @param array $args used to inform the caller of the arguments that were
	 * actually used in the rule logic function.
	 * @return boolean true if $recordData contained all data necessary to
	 * populate the function parameters, false if not.
	 */
	static public function buildLogicArgs($argMap=array(), $recordData=array(), &$args, $firstEventId=null)
	{
		$isValid = true;
		// Get first event ID for the relevant project
		if ($firstEventId == null) {
			global $Proj;
			$firstEventId = $Proj->firstEventId;
		}
		try {
			$args = array();
			foreach ($argMap as $argData) 
			{
				// Get event_id, variable, and (if a checkbox) checkbox choice
				list ($eventVar, $projectVar, $cboxChoice) = $argData;
				// If missing the event_id, assume the first event_id in the project
				if (!is_numeric($eventVar)) $eventVar = $firstEventId;
				// Check event key
				if (!isset($recordData[$eventVar])) {
					throw new Exception("Missing event: $eventVar");
				}
				$projFields = $recordData[$eventVar];
				// Check field key
				if (!isset($projFields[$projectVar])) {
					throw new Exception("Missing project field: $projectVar");
				}
				// Set value, then validate it based on field type
				$value = $projFields[$projectVar];
				if ($cboxChoice === null && is_array($value) || $cboxChoice !== null && !is_array($value))
					throw new Exception("checkbox/value mismatch! $value " . print_r($value, true));
				if ($cboxChoice !== null && !isset($value[$cboxChoice]))
					throw new Exception("Missing checkbox choice: $cboxChoice");
				if ($cboxChoice !== null) {
					$value = $value[$cboxChoice];
				}
				// Add value to args array
				$args[] = $value;
			}
		}
		catch (Exception $e) {
			$isValid = false;
		}
		// Return if all arguments are valid and accounted for
		return $isValid;
	}
	
	
	/**
	 * For a general logic string, prepend all variables with a unique event name provided if the
	 * variable is not already prepended with a unique event name. 
	 * (Used to define an event explicityly before being evaluated for a record.)
	 */
	public static function logicPrependEventName($logic, $unique_event_name)
	{
		// First, prepend fields with unique event name
		$logic = preg_replace("/([^\]])(\[)/", "$1[$unique_event_name]$2", " " . $logic);
		// Lastly, remove instances of double event names in logic
		$logic = preg_replace("/(\[)([^\[]*)(\]\[)([^\[]*)(\]\[)([^\[]*)(\])/", "[$4][$6]", $logic);
		// Return the formated logic
		return $logic;
	}
	
	/**
	 * Evaluates a condition using existing data and returns value if all variables have values. Otherwise, return FALSE.
	 * @param string $logic is the raw logic provided by the user in the branching logic/Data Quality logic format.
	 * @param array $record_data holds the record data with event_id as first key, field name as second key,
	 * and value as data value (if checkbox, third key is raw coded value with value as 0/1).
	 */
	public static function evaluateCondition($logic=null, $record_data=array(), $funcName=null, $argMap=null)
	{
		global $Proj;
		// If there is an issue in the logic, then return an error message and stop processing
		if ($funcName == null) {
			try {
				// Get unique event names (with event_id as key)
				$events = $Proj->getUniqueEventNames();	
				// Instantiate logic parser
				$parser = new LogicParser();
				list ($funcName, $argMap) = $parser->parse($logic, array_flip($events));
				// print $funcName."(){ ".$parser->generatedCode." }";
			}
			catch (LogicException $e) {
				// print "Error: ".$e->getMessage();
				return false;
			}
		}
		// Execute the logic to return boolean (return TRUE if is 1 and not 0 or FALSE)
		return self::applyLogicForEvaluateCondition($funcName, $argMap, $record_data);
	}
	
	/**
	 * Runs a logic condition's result and returns the *COMPLEMENT* of the result;
	 * @param string $funcName the name of the function to execute.
	 * @param array $recordData first key is the event name, second key is the
	 * field name, and third key is either the field value, or if the field is
	 * a checkbox, it will be an array of checkbox codes => values.
	 * @param string $currEventId the event ID of the current record being examined.
	 * @param array $rule_attr a description of the Data Quality rule.
	 * @param array $args used to inform the caller of the arguments that were
	 * actually used in the rule logic function.
	 * @param array $useArgs if given, this function will use these arguments
	 * instead of running $this->buildLogicArgs().
	 * @return 0 if the function returned false, 1 if the result is non-false, and
	 * false if an exception was thrown.
	 */
	static private function applyLogicForEvaluateCondition($funcName, $argMap=array(), $recordData=array())
	{
		$args = array();
		try {
			if (!LogicTester::buildLogicArgs($argMap, $recordData, $args)) {
				throw new Exception("recordData does not contain the parameters we need");
			}
			$logicCheckResult = call_user_func_array($funcName, $args);
			return $logicCheckResult;
		}
		catch (Exception $e) {
			return false;
		}
	}
	
	
	// Replace datediff()'s comma with -DDC- (to be removed later) so it does not interfere with ternary formatting later
	public static function replaceDatediff($string)
	{
		if (strpos($string, "datediff") !== false)
		{
			## Determine which format of datediff() they're using (can include or exclude certain parameters)
			// Include the 'returnSignedValue' parameter
			$regex = "/(datediff)(\s*)(\()([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(\))/";
			if (preg_match($regex, $string)) 
			{
				$string = preg_replace($regex, "datediff($4-DDC-$6-DDC-$8-DDC-$10-DDC-$12)", $string);
			}
			// Include the 'dateformat' parameter
			$regex = "/(datediff)(\s*)(\()([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(\))/";
			if (preg_match($regex, $string)) 
			{
				$string = preg_replace($regex, "datediff($4-DDC-$6-DDC-$8-DDC-$10)", $string);
			}
			// Now try pattern without the 'dateformat' parameter (legacy)
			$regex = "/(datediff)(\s*)(\()([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(\))/";
			if (preg_match($regex, $string)) 
			{
				$string = preg_replace($regex, "datediff($4-DDC-$6-DDC-$8)", $string);
			}
		}
		return $string;
	}
	
	
	// Replace round()'s comma with -ROC- (to be removed later) so it does not interfere with ternary formatting later
	public static function replaceRound($string,$i=0)
	{
		// Deal with round(, if any are present
		if (strpos($string, "round") !== false) 
		{
			$regex = "/(round)(\s*)(\()([^,]+)(,)([^,]+)(\))/";
			// Replace all instances of round() that contain a comma inside so it does not interfere with ternary formatting later
			while (preg_match($regex, $string) && $i++ < 20) 
			{
				$string = preg_replace_callback($regex, "LogicTester::replaceRoundCallback", $string);
			}
		}
		// Replace back commas that are not used in round()
		$string = str_replace("-REMOVE-", ",", $string);
		return $string;
	}
	
	
	// Callback function for replacing round()'s comma
	public static function replaceRoundCallback($matches)
	{
		// If non-equal number of '(' vs. ')', then send back -REMOVE- to replace as comma to prevent function from 
		// going into infinite loops. Otherwise, assume the comma belongs to this round().
		return ((substr_count($matches[4], "(") != substr_count($matches[4], ")")) ? "round(".$matches[4]."-REMOVE-".$matches[6].")" : "round(".$matches[4]."-ROC-".$matches[6].")");
	}	
	
		
	//Replace ^ exponential form with javascript or php equivalent
	public static function replaceExponents($string, $replaceForPHP=false) 
	{
		//First, convert any "sqrt" functions to javascript equivalent	
		if (!$replaceForPHP) { // PHP already has a sqrt function
			$first_loop = true;
			while (preg_match("/(sqrt)(\s*)(\()/", $string)) {
				//Ready the string to location "sqrt(" substring easily
				if ($first_loop) {
					$string = preg_replace("/(sqrt)(\s*)(\()/", "sqrt(", $string);
					$first_loop = false;
				}
				//Loop through each character and find outer parenthesis location
				$last_char = strlen($string);
				$sqrt_pos  = strpos($string, "sqrt(");
				$found_end = false;
				$rpar_count = 0;
				$lpar_count = 0;
				$i = $sqrt_pos;
				//Since there are parentheses inside "sqrt", loop through each letter to localize and replace
				if (!preg_match("/(sqrt)(\()([^\(\)]{1,})(\))/", $string)) {
					while ($i <= $last_char && !$found_end) {
						//Keep count of left/right parentheses
						if (substr($string, $i, 1) == "(") {
							$lpar_count++;
						} elseif (substr($string, $i, 1) == ")") {
							$rpar_count++;
						}
						//If found the parentheses boundary, then end loop
						if ($rpar_count > 0 && $lpar_count > 0 && $rpar_count == $lpar_count) {
							$found_end = true;
						} else {
							$i++;
						}
					}
					$inside = substr($string, $sqrt_pos + 5, $i - $sqrt_pos - 5);
					//Replace this instance of "sqrt"
					$string = substr($string, 0, $sqrt_pos) . "Math.pow($inside-EXPC-0.5)" . substr($string, $i + 1);
				//There are no parentheses inside "sqrt", so do simple preg_replace
				} else {
					$string = preg_replace("/(sqrt)(\()([^\(\)]{1,})(\))/", "Math.pow($3-EXPC-0.5)", $string);
				}
			}
		}

		//Find all ^ and locate outer parenthesis for its number and exponent
		$powReplacement = ($replaceForPHP) ? "pow(" : "Math.pow(";
		$powReplacementLen = strlen($powReplacement);
		$caret_pos = strpos($string, "^");
		$num_carets_total = substr_count($string, "^");
		while ($caret_pos !== false) 
		{
			//For first half of string
			$found_end = false;
			$rpar_count = 0;
			$lpar_count = 0;
			$i = $caret_pos;
			while ($i >= 0 && !$found_end) {
				$i--;
				//Keep count of left/right parentheses
				if (substr($string, $i, 1) == "(") {
					$lpar_count++;
				} elseif (substr($string, $i, 1) == ")") {
					$rpar_count++;
				}
				//If found the parentheses boundary, then end loop
				if ($rpar_count > 0 && $lpar_count > 0 && $rpar_count == $lpar_count) {
					$found_end = true;
				}
			}
			//Completed first half of string
			$string = substr($string, 0, $i). $powReplacement . substr($string, $i);
			$caret_pos += $powReplacementLen; // length of "Math.pow(" or "pow("
			
			//For last half of string
			$last_char = strlen($string);
			$found_end = false;
			$rpar_count = 0;
			$lpar_count = 0;
			$i = $caret_pos;
			while ($i <= $last_char && !$found_end) {
				$i++;
				//Keep count of left/right parentheses
				if (substr($string, $i, 1) == "(") {
					$lpar_count++;
				} elseif (substr($string, $i, 1) == ")") {
					$rpar_count++;
				}
				//If found the parentheses boundary, then end loop
				if ($rpar_count > 0 && $lpar_count > 0 && $rpar_count == $lpar_count) {
					$found_end = true;
				}
			}
			//Completed last half of string
			$string = substr($string, 0, $caret_pos) . "-EXPC-" . substr($string, $caret_pos + 1, $i - $caret_pos) . ")" . substr($string, $i + 1);
			
			if ($num_carets_total == substr_count($string, "^")) {
				// If the replacement did NOT work, then stop looping or else it'll go on forever
				$caret_pos = false;
			} else {
				// Set again for checking in next loop
				$caret_pos = strpos($string, "^");
			}
		}
		
		// Re-replace the comma (PHP only)
		if ($replaceForPHP) {
			$string = str_replace("-EXPC-", ",", $string);
		}
		
		// Return string
		return $string;
	}

	// Convert a string with an IF statement from Excel format - e.g. if(cond, true, false) -
	// to PHP ternary operator format - e.g. if(cond ? true : false).
	public static function convertIfStatement($string,$recursions=0) 
	{
		// Check if has any IF statements
		if (preg_match("/(if)(\s*)(\()/i", $string) && substr_count($string, ",") >= 2 && $recursions < 1000) 
		{	
			// Remove spaces between "if" and parenthesis so we can more easily parse it downstream
			$string_temp = preg_replace("/(if)(\s*)(\()/i", "if(", $string);
			// Defaults
			$curstr = "";
			$nested_paren_count = 0; // how many nested parentheses we're inside of
			$found_first_comma = false;
			$found_second_comma = false;
			$location_first_comma = null;
			$location_second_comma = null;
			// Only begin parsing at first IF (i.e. only use string_temp)
			list ($cumulative_string, $string_temp) = explode("if(", $string_temp, 2);
			// First, find the first innermost IF in the string and get its location. We'll begin parsing there.			
			$string_array = explode("if(", $string_temp);
			// print_array($string_array);
			foreach ($string_array as $key => $this_string) 
			{
				// Check if we should parse this loop
				if ($this_string != "") 
				{
					// If current string is empty, then set it as this_string, otherwise prepend curstr from last loop to this_string
					$curstr .= $this_string;
					// Check if this string has ALL we need (2 commas, 1 right parenthesis, and num right parens = num left parens+1)
					$num_commas 	 = substr_count($curstr, ",");
					$num_left_paren  = substr_count($curstr, "(");
					$num_right_paren = substr_count($curstr, ")");
					$hasCompleteIfStatement = ($num_commas >= 2 && $num_right_paren > 0 && $num_right_paren > $num_left_paren);
					if ($hasCompleteIfStatement) 
					{
						// The entire IF statement MIGHT be in this_string. Check if it is (commas and parens in correct order).
						$curstr_len = strlen($curstr);
						// Loop through the string letter by letter
						for ($i = 0; $i < $curstr_len; $i++)
						{
							// Get current letter
							$letter = substr($curstr, $i, 1);
							// Perform logic based on current letter and flags already set
							if ($letter == "(") {
								// Increment the count of how many nested parentheses we're inside of
								$nested_paren_count++;
							} elseif ($letter != ")" && $nested_paren_count > 0) {
								if ($i+1 == $curstr_len) {
									// This is the last letter of the string, and we still haven't completed the entire IF statement.
									// So reset curstr and go to next loop, which should have a nested IF (we'll work our way outwards)
									$cumulative_string .= "if($curstr";
									$curstr = "";
								} else {
									// We're inside a nested parenthesis, so there's nothing to do -> keep looping till we get out
								}
							} elseif ($letter == ")" && $nested_paren_count > 0) {
								// We just left a nested parenthesis, so reduce count by 1 and keep looping
								$nested_paren_count--;
							} elseif ($letter == "," && $nested_paren_count == 0 && !$found_first_comma) {
								// Found first valid comma AND not in a nested parenthesis
								$found_first_comma = true;
								$found_second_comma = false;
								$location_first_comma = $i;
								$location_second_comma = null;
							} elseif ($letter == "," && $nested_paren_count == 0 && $found_first_comma && !$found_second_comma) {
								// Found second valid comma AND not in a nested parenthesis
								$found_second_comma = true;
								$location_second_comma = $i;
							} elseif ($letter == ")" && $nested_paren_count == 0 && $found_first_comma && $found_second_comma) {
								// Found closing valid parenthesis of IF statement, so replace the commas with ternary operator format
								$cumulative_string .= "(" . substr($curstr, 0, $location_first_comma) 
													. " ? " . substr($curstr, $location_first_comma+1, $location_second_comma-($location_first_comma+1))
													. " : " . substr($curstr, $location_second_comma+1);
								// Reset values for further processing
								$curstr = "";
								$found_first_comma = false;
								$found_second_comma = false;
								$location_first_comma = null;
								$location_second_comma = null;
							}
						}						
					} else {
						// The entire IF statement is NOT in this_string, therefore there must be a nested IF after this one.
						// Reset curstr and begin anew with next nested IF (we'll work our way outwards from the innermost IFs)
						$cumulative_string .= "if($curstr";
						$curstr = "";
					}
				}
			}
			// If the string still has IFs because of nesting, then do recursively.
			return self::convertIfStatement($cumulative_string,++$recursions);
		}
		// Now that we're officially done parsing, return the string
		return $string;
	}	
	

	// Format a logic string into JS notation (including converting fields into JS notation)
	public static function formatLogicToJS($string, $doExtraCalcFormatting=false, $current_event_id=null) 
	{
		global $Proj;
		
		// Get list of field names used in string (Calc fields only)
		if ($doExtraCalcFormatting) {
			$these_fields = getBracketedFields($string, true, true);
		}
		
		//Replace operators in equation with javascript equivalents (Strangely, the < character causes issues with str_replace later when it has no spaces around it, so add spaces around it)
		$orig = array("<"  , "=" , "===", "====", "> ==", "< ==", ">==", "<==", "< >", "<>", " and ", " AND ", " or ", " OR ");
		$repl = array(" < ", "==", "==" , "=="  , ">="  , "<="  , ">="  , "<="  , "!=" , "!=", " && " , " && " , " || ", " || ");
		$string = str_replace($orig, $repl, $string);
		
		// Replace unique event name+field_name in brackets with javascript equivalent
		if ($Proj->longitudinal) {
			$string = preg_replace("/(\[)([^\[]*)(\]\[)([^\[]*)(\])/", "document.form__$2.$4.value", $string);
		}
		
		// Replace field_name in brackets with javascript equivalent
		$string = preg_replace("/(\[)([^\[]*)(\])/", "document.form.$2.value", $string);
		
		// Now compensate for unique formatting for checkboxes in javascript, if there are any checkboxes
		if (strpos($string, ").value") !== false) {
			$string = preg_replace("/(document.)([a-z0-9_]+)(.)([a-zA-Z0-9_]+)([\(]{1})([a-zA-Z0-9_-]+)([\)]{1})(.value)/", 
								   "(if(document.forms['$2'].elements['__chk__$4_RC_$6'].value=='',0,1)*1)", $string);
		}
		
		// If using unique event name in equation and we're currently on that event, replace the event name in the JS
		if ($Proj->longitudinal) {
			$this_event = $Proj->getUniqueEventNames($current_event_id);
			if (!is_array($this_event) && $this_event != '') {
				$string = str_replace("document.form__{$this_event}.", "document.form.", $string);
				$string = str_replace("document.forms['form__{$this_event}'].", "document.form.", $string);
			}
		}
		
		// Calc fields only
		if ($doExtraCalcFormatting) 
		{		
			// Replace field names with javascript equivalent 
			foreach (array_keys($these_fields) as $this_field) 
			{
				// If field is not a date[time] field, then wrap the field with chkNull function to 
				// ensure that we get either a numerical value or NaN.
				$fieldValidation = $Proj->metadata[$this_field]['element_validation_type'];
				if (!($Proj->metadata[$this_field]['element_type'] == 'text' 
					&& ($fieldValidation == 'time' || substr($fieldValidation, 0, 4) == 'date'))) 
				{
					// Any field except a date[time] field
					$string = str_replace("document.form.$this_field.value", "chkNull(document.form.$this_field.value)", $string);
				}
			}
			
			// Now swap all "+" with "*1+1*" in the equation to work around possibility of JavaScript concatenation in some cases
			if (strpos($string, "+") !== false) $string = str_replace("+", "*1+1*", $string);
		}
		
		// Replace ^ exponential form with javascript equivalent
		$string = self::replaceExponents($string);
			
		// Temporarily swap out commas in any datediff() functions (so they're not confused in IF statement processing).
		// They will be replaced back at the end.
		$string = self::replaceDatediff($string);
		
		// Temporarily swap out commas in any round() functions (so they're not confused in IF statement processing).
		// They will be replaced back at the end.
		$string = self::replaceRound($string);
		
		// If using conditional logic, format any conditional logic to Javascript ternary operator standards
		$string = self::convertIfStatement($string);
			
		// Now swap datediff() commas back into the equation (was replaced with -DDC-)
		if (strpos($string, "-DDC-") !== false) $string = str_replace("-DDC-", ",", $string);
		
		// Now swap round() commas back into the equation (was replaced with -ROC-)
		if (strpos($string, "-ROC-") !== false) $string = str_replace(array("-ROC-)","-ROC-"), array(")",","), $string);
		
		// Now swap sqrt() or exponential commas back into the equation (was replaced with -DDC-)
		if (strpos($string, "-EXPC-") !== false) $string = str_replace("-EXPC-", ",", $string);
		
		// Return formatted string
		return $string;
	}
	
}