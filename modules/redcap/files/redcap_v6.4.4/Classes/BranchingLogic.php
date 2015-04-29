<?php

class BranchingLogic
{	
	private $_results = array();
	private $_equations = array();
    
    public function feedBranchingEquation($name, $string) 
	{		
		array_push($this->_results, $name);
		
		// Format logic to JS format
		$string = LogicTester::formatLogicToJS(html_entity_decode($string, ENT_QUOTES), false, $_GET['event_id']);
		
		// Add to array
		array_push($this->_equations, $string);
    }
	
	public function exportBranchingJS() 
	{
		global $Proj;
		
		$result  = "\n<!-- Branching Logic -->";	
		$result .= "\n<script type=\"text/javascript\">\n";
		$result .= "function doBranching(){\n";
		// Loop through all branching logic fields
		for ($i = 0; $i < sizeof($this->_results); $i++) 
		{
			// Show the field only if the condition is true; Hide it if false. Prompt if about to hide a field with data already entered.
			$this_field = $this->_results[$i];
			// Set string for try/catch
			if (isset($_GET['__showerrors'])) {
				$try = "";
				$catch = "";
			} else {
				$try = "try{";
				$catch = "}catch(e){brErr('$this_field')}";
			}
			// Add line of JS
			$result .= "  $try evalLogic('$this_field',(" . html_entity_decode($this->_equations[$i], ENT_QUOTES) . ")); $catch\n";
		}		
		// Hide any section headers in which all fields in the section have been hidden
		$result .= "  hideSectionHeaders();\n";
		$result .= "  return false;\n";		
		$result .= "}\n";
		
		// Add javascript for form/survey page to show form table right before we execute the branching
		$result .= "document.getElementById('form_table_loading').style.display='none';\n";
		$result .= "document.getElementById('form_table').style.display='block';\n";
		$result .= "if (elementExists(document.getElementById('form_response_header'))) document.getElementById('form_response_header').style.display='block';\n";
		
		$result .= "brErrExist = doBranching();\n";
		$result .= "</script>\n";
		$result .= "<script type=\"text/javascript\">\n";
		$result .= "if(brErrExist){brErr2()}\n";
		$result .= "</script>\n";
		
		return $result;
	}
	
	
	// Determines if ALL fields provided in $fields would be hidden by branching logic 
	// based on existing saved data values. Returns boolean.
	public static function allFieldsHidden($record, $event_id=null, $fields=array())
	{
		global $Proj, $longitudinal, $table_pk;
		// Return false if $fields is empty
		if (empty($fields)) return false;
		// Loop through all fields and check to make sure they ALL have branching logic. 
		// If at least one does NOT have branching logic, then return false.
		foreach ($fields as $field) {
			if ($Proj->metadata[$field]['branching_logic'] == '') {
				return false;
			}
		}
		// If longitudinal, then get unique event name from event_id
		if ($event_id == null) $event_id = $Proj->getFirstEventIdArm(getArm());
		$unique_event_name = $Proj->getUniqueEventNames($event_id);
		// Obtain all dependent fields for the fields displayed
		$fieldsDependent = getDependentFields($fields, false, true);
		// Obtain array of record data (including default values for checkboxes and Form Status fields)
		$record_data = Records::getData('array', $record, array_merge($fieldsDependent, $fields));
		$record_data = $record_data[$record];
		// For longitudinal only, there might be cross-event logic that references events that dont' have any
		// data yet, which will cause it to return FALSE mistakenly in some cases. So for all events with no data,
		// add each event with empty values and add to $record_data array so that they are present (and blank) to be used in apply().
		if ($longitudinal) {
			// Get any missing events from $record_data
			$missing_event_ids = array_diff(array_keys($Proj->eventInfo), array_keys($record_data));
			// If there exist some events with no data, then loop through $record_data and add empty events
			if (!empty($missing_event_ids)) {
				$empty_data = array();
				foreach ($record_data as $this_event_id=>$these_fields) {
					foreach ($these_fields as $this_field=>$this_value) {
						if (is_array($this_value)) {
							// Checkboxes
							foreach ($this_value as $this_code=>$this_checkbox_value) {
								// Add to array a 0 as default checkbox value
								$empty_data[$this_field][$this_code] = '0';
							}
						} else {
							// Non-checkbox fields							
							// Set value as blank (but not for record ID field and not for Form Status fields)
							if ($this_field == $table_pk) {
								// Do nothing, leave record ID value as-is
							} elseif ($Proj->isFormStatus($this_field)) {
								// Set default value as 0
								$this_value = '0';
							} else {
								$this_value = '';
							}
							// Add to array
							$empty_data[$this_field] = $this_value;
						}
					}
					// Stop here since we only need just one event's field structure
					break;
				}
			}
			// Add empty event arrays to $record_data
			if (!empty($empty_data)) {
				// Loop through missing event_ids and add each event with blank event data
				foreach ($missing_event_ids as $this_event_id) {
					$record_data[$this_event_id] = $empty_data;
				}
			}
		}
		// Loop through all fields visible on survey and evaluate their branching logic one by one
		foreach ($fields as $field) {
			// Get branching logic for this field
			$logic = $Proj->metadata[$field]['branching_logic'];
			// If longitudinal, then inject the unique event names into logic (if missing)
			// in order to specific the current event.
			if ($longitudinal) {
				$logic = LogicTester::logicPrependEventName($logic, $unique_event_name);
			}
			// Make sure that the field's branching logic has proper syntax before we evaluate it with data
			if (!LogicTester::isValid($logic)) return false;
			// Now evaluate the logic with data
			$displayField = LogicTester::apply($logic, $record_data);
			// If at least one field is to be displayed, then return false
			if ($displayField) return false;
		}
		// If we made it this far, then all fields must be hidden		
		return true;
	}
	
}
