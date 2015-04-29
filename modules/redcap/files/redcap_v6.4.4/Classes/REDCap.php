<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/


/**
 * REDCap Class used for plugins only
 */
class REDCap
{
	/**
	 * CHECK FOR PROJECT CONTEXT (i.e. has PROJECT_ID constant defined)
	 * If not in project context, exit with error.
	 */
	private static function checkProjectContext($method_name)
	{
		if (!defined('PROJECT_ID')) exit("ERROR: $method_name can only be used in a project context!");
	}
		
	
	/**
	 * SUMMARY: Returns a set of data (i.e. records) in a specified format from the current project
	 * DESCRIPTION: mixed <b>REDCap::getData</b> ( [ int <b>$project_id</b>, ] [ string <b>$return_format</b> = 'array' [, mixed <b>$records</b> = NULL [, mixed <b>$fields</b> = NULL [, mixed <b>$events</b> = NULL [, mixed <b>$groups</b> = NULL [, bool <b>$combine_checkbox_values</b> = FALSE [, bool <b>$exportDataAccessGroups</b> = FALSE [, bool <b>$exportSurveyFields</b> = FALSE [, string <b>$filterLogic</b> = NULL [, bool <b>$exportAsLabels</b> = FALSE [, bool <b>$exportCsvHeadersAsLabels</b> = FALSE ]]]]]]]]]]] )
	 * DESCRIPTION_TEXT: Returns a set of data (i.e. records) from the current project. The format of the returned data may be specified, and the data returned may be limited to specific records, fields, events, and/or data access groups.
	 * PARAM: project_id (optional) - The project ID number of the REDCap project from which to pull data. If not provided in a project-level plugin, it will assume the current project ID of the plugin and will also infer return_format to be the first parameter passed to the method. If project_id is not provided in a system-level plugin, it will throw a fatal error.
	 * PARAM: return_format - The format in which the data should be returned. Valid options: 'array', 'csv', 'json', and 'xml'. By default, 'array' is used.
	 * PARAM: records - An array of record names, or alternatively a single record name (as a string). This will limit the data returned only to those records specified. By default, NULL is used, which will return data for all records from the current project.
	 * PARAM: fields - An array of field variable names, or alternatively a single field variable name (as a string). This will limit the data returned only to those fields specified. By default, NULL is used, which will return data for all fields from the current project.	
	 * PARAM: events - An array of unique event names or event_id's, or alternatively a single unique event name or event_id (as a string or int, respectively). This will limit the data returned only to those events specified. By default, NULL is used, which will return data for all events from the current project. If the project is not longitudinal, NULL is used.
	 * PARAM: groups - An array of unique group names or group_id's, or alternatively a single unique group name or group_id (as a string or int, respectively). This will limit the data returned only to those data access groups specified. By default, NULL is used, which will return data for all data access groups from the current project. If the project does not contain any data access groups, NULL is used.
	 * PARAM: combine_checkbox_values - Sets the format in which data from checkbox fields are returned. By default, FALSE is used. Combine_checkbox_values can only be used when return_format is 'csv', 'json', or 'xml'. If return_format is 'array', then combine_checkbox_values is set to FALSE. When combine_checkbox_values is set to TRUE, it will return a checkbox field's data as a single field with all its checked options (excludes unchecked options) combined as a comma-delimited string (e.g., meds="1,3,4" if only choices 1, 3, and 4 are checked off). If set to FALSE, a checkbox's data values are returned as multiple fields appended with triple underscores with a value of "1" if checked and "0" if unchecked (e.g., meds___1="1", meds___2="0", meds___3="1", meds___4="1").
	 * PARAM: exportDataAccessGroups - Specifies whether or not to return the "redcap_data_access_group" field when data access groups are utilized in the project. By default, FALSE is used. 
	 * PARAM: exportSurveyFields - Specifies whether or not to return the survey identifier field (e.g., "redcap_survey_identifier") or survey timestamp fields (e.g., form_name+"_timestamp") when surveys are utilized in the project. By default, FALSE is used.
	 * PARAM: filterLogic - Text string of logic to be applied to the data set so that only record-events that evaluate as TRUE for the logic will be output in the returned data set. By default, this parameter is NULL and is thus ignored. This logic string is the same format as used all throughout REDCap in advanced filters for reports, branching logic, Data Quality module, etc. Example: [gender] = "1".
	 * PARAM: exportAsLabels - Sets the format of the data returned. If FALSE, it returns the raw data. If TRUE, it returns the data as labels (e.g., "Male" instead of "0"). By default, FALSE is used.
	 * PARAM: exportCsvHeadersAsLabels - Sets the format of the CSV headers returned (only applicable to 'csv' return formats). If FALSE, it returns the variable names as the headers. If TRUE, it returns the fields' Field Label text as the headers. By default, FALSE is used.
	 * RETURN: If return_format = 'csv', 'json', or 'xml', then data will be returned in the standard format for those return formats. If return_format = 'array', returns array of data with record name as the 1st-level array key, event_id as the 2nd-level array key, field variable name as the 3rd-level array key, and the data values as the array values for each field. If a field is a checkbox field, then the checkbox's coded values will the the 4th-level array keys while the value of each checkbox option ("0" or "1") will be the array value for each option.
	 * RESTRICTIONS: If used in a system-level plugin, the project_id parameter is required.
	 * VERSION: 5.5.0
	 * EXAMPLE: This example illustrates many different variations of how to export data from a project using various values for each parameter.
<pre>
// Export ALL data in ARRAY format
$data = REDCap::getData('array');

// Export data in CSV format for only record "101" for ALL fields
$data = REDCap::getData('csv', '101');

// Export data in CSV format for a single record for ALL fields from two different 
// REDCap projects (project_id = 44 and 723, respectively)
$data1 = REDCap::getData(44, 'csv', '101');
$data2 = REDCap::getData(723, 'csv', '934-2');

// Export data in JSON format for records "101" and "102"
// for only the fields "record_id" and "dob"
$data = REDCap::getData('json', array('101', '102'), array('record_id', 'dob'));

// Export data in XML format for ALL records, for only the field "dob", and 
// for only two specific events (assuming a longitudinal project)
$data = REDCap::getData('xml', null, 'dob', array('enrollment_arm1', 'visit1_arm1'));

// Export ALL data in ARRAY format for the data access group named "Vanderbilt Group"
$data = REDCap::getData('array', null, null, null, 'vanderbilt_group');

// Export data in CSV format for ALL records and for the fields "study_id" and "meds" 
// in which each checkbox field's checked values are combined into a comma-delimited string.
$data = REDCap::getData('csv', null, array('study_id', 'meds'), null, null, true);

// Export data as labels in CSV format with label headers for only the fields 'record_id', 'dob',
// and the Data Access Group field but JUST records whose record name ends with "--1"
$data = REDCap::getData('csv', null, array('record_id', 'dob'), null, null, false, true, false, 
	'ends_with([record_id], "--1")', true, true);

// Export data in XML format for only the fields 'record_id', 'dob', survey identifier, 
// and survey timestamps but JUST records where the last name contains "tay"
$data = REDCap::getData('xml', null, array('record_id', 'dob'), null, null, false, false, true, 
	'contains([last_name], "tay")');

// Export data as labels in JSON format for ALL fields but JUST records where [gender] = "0"
$data = REDCap::getData('json', null, null, null, null, false, false, false, '[gender] = "0"', true);
</pre>
	 */
	public static function getData()
	{
		// Call Records class method getData()
		$args = func_get_args();
		return call_user_func_array('Records::getData', $args);
	}
	
	
	/**
	 * SUMMARY: Determines the title of the current project
	 * DESCRIPTION: string <b>REDCap::getProjectTitle</b> ( void )
	 * DESCRIPTION_TEXT: Returns the title of the current project.
	 * RETURN: Returns the project's title as a string.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 5.5.0
	 * EXAMPLE: This example illustrates how one might want to display the title of a project on a page.
<pre>
print 'The REDCap project is named "' . REDCap::getProjectTitle() . '".';
</pre>
	 */
	public static function getProjectTitle()
	{
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Return project title
		global $app_title;
		return $app_title;
	}
	
	
	/**
	 * SUMMARY: Determines the variable name of the Record ID field (i.e. the first field) of the current project
	 * DESCRIPTION: string <b>REDCap::getRecordIdField</b> ( void )
	 * DESCRIPTION_TEXT: Determines the variable name of the Record ID field (i.e. the first field) of the current project (e.g., study_id, participant_id).
	 * RETURN: Returns the field variable name as a string.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 5.5.0
	 * EXAMPLE: This example illustrates how to get the Record ID field to export all the record names from a project.
<pre>
// Get the project's Record ID field
$record_id_field = REDCap::getRecordIdField();

// Export data in array format for all records for only the Record ID field
$data = REDCap::getData('array', null, $record_id_field);

// Since the data was returned as an array with multi-level keys, obtain the record names 
// from the 1st-level array keys in $data via array_keys() and place them in a separate array.
$record_names = array_keys($data);

// Display all the record names on the page
var_dump($record_names);
</pre>
	 */
	public static function getRecordIdField()
	{
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Return the record ID field
		global $table_pk;
		return $table_pk;
	}
	
	
	/**
	 * SUMMARY: Determines the field type for a specified field in the current project
	 * DESCRIPTION: string <b>REDCap::getFieldType</b> ( string <b>$field_name</b> )
	 * DESCRIPTION_TEXT: Returns the field type for a specified field in the current project. The field type corresponds to the values seen in the Data Dictionary (e.g., dropdown, yesno, notes, slider).<br><br>NOTE: Please note that "dropdown" and "notes" fields actually have an element_type of "select" and "textarea", respectively, in the redcap_metadata database tables, although all other fields have an element_type value the same as their field type value. This is important to know if you ever query the redcap_metadata tables directly.
	 * PARAM: field_name - A field's variable name. If field_name is invalid for the project, returns FALSE.
	 * RETURN: Returns the specified field's type as a string.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 5.5.0
	 * EXAMPLE: This example illustrates how one might obtain a field's type to make a decision in the code logic.
<pre>
// Set the field variable name manually for this example
$field = 'first_name';

// Check if the field is a checkbox or not
if (REDCap::getFieldType($field) == 'checkbox') {
	// Do something for checkbox fields
	
} else {
	// Do something for all other field types
	
}
</pre>
	 */
	public static function getFieldType($field_name)
	{
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Get $Proj object
		global $Proj;
		// If field is invalid, return false
		if (!isset($Proj->metadata[$field_name])) return false;
		// Array to translate back-end field type to front-end (some are different, e.g. "textarea"=>"notes")
		$fieldTypeTranslator = array('textarea'=>'notes', 'select'=>'dropdown');
		// Get field type
		$fieldType = $Proj->metadata[$field_name]['element_type'];
		// Translate field type, if needed
		if (isset($fieldTypeTranslator[$fieldType])) {
			$fieldType = $fieldTypeTranslator[$fieldType];
		}
		// Return field type
		return $fieldType;
	}
	
	
	/**
	 * SUMMARY: Returns a list of all field variable names for the current project
	 * DESCRIPTION: array <b>REDCap::getFieldNames</b> ( [ mixed <b>$instruments</b> = NULL ] )
	 * DESCRIPTION_TEXT: Returns a list of all field variable names for the current project. If $instruments parameter is supplied (as array or string), it will only return the fields contained in the data collection instrument(s) provided in the array or string.
	 * PARAM: instruments - An array of data collection instrument names (i.e. the unique name, not the instrument label) or a single instrument name (string), which will return only fields from that instrument. By default, NULL is used, in which it will return all field variables for the entire project.
	 * RETURN: Returns array of field variable names. The variables are ordered in the order in which they are specified in the project.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 5.5.0
	 * EXAMPLE: This example shows how one can loop through all fields in a project to perform a specific action for each one.
<pre>
// Get all field variable names in project
$fields = REDCap::getFieldNames();

// Loop through each field and do something with each
foreach ($fields as $this_field) {
	// Do something with $this_field
	
}
</pre>
	 * EXAMPLE: This example illustrates how to retrieve variables for only specific instruments.
<pre>
// Get variables for multiple instruments in the project
$instruments = array('demographics', 'baseline_data');
var_dump( REDCap::getFieldNames($instruments) );

// Or get variables from just a single instrument
$instrument = 'demographics';
var_dump( REDCap::getFieldNames($instrument) );
</pre>
	 */
	public static function getFieldNames($instruments=null)
	{
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Get $Proj object
		global $Proj;
		// Return ALL fields in project
		if ($instruments == null) {
			return array_keys($Proj->metadata);
		} 
		// Return fields for instruments in array
		elseif (is_array($instruments)) {
			// Validate the instruments
			foreach ($instruments as $this_key=>$this_instrument) {
				if (!isset($Proj->forms[$this_instrument])) {
					unset($instruments[$this_key]);
				}
			}
			// If no instruments were valid, return false.
			if (empty($instruments)) return false;
			// Collect all fields into an array
			$fields = array();
			foreach ($instruments as $this_instrument) {
				$fields = array_merge($fields, array_keys($Proj->forms[$this_instrument]['fields']));
			}
			// Return fields for all specief forms
			return $fields;
		} 
		// Return fields for single instrument
		else {
			return (isset($Proj->forms[$instruments]) ? array_keys($Proj->forms[$instruments]['fields']) : false);
		}
	}
	
	
	/**
	 * SUMMARY: Finds whether the current project is longitudinal
	 * DESCRIPTION: bool <b>REDCap::isLongitudinal</b> ( void )
	 * DESCRIPTION_TEXT: Finds whether the current project has longitudinal data collection enabled and also has at least two events defined.
	 * RETURN: Returns TRUE if project is longitudinal, FALSE otherwise.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 5.5.0
	 * EXAMPLE: This example shows how one might perform a specific action if the project is longitudinal.
<pre>
// Determine if project is longitudinal
if (REDCap::isLongitudinal()) {
	// Longitudinal project
	
} else {
	// Classic project
	
}
</pre>
	 */
	public static function isLongitudinal()
	{
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Get $longitudinal global vaar
		global $longitudinal;
		return $longitudinal;
	}
	
	
	/**
	 * SUMMARY: Returns a list of usernames of all users with access to the current project
	 * DESCRIPTION: array <b>REDCap::getUsers</b> ( void )
	 * DESCRIPTION_TEXT: Returns a list of usernames of all users with access to the current project.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * RETURN: Returns array of usernames ordered by username.
	 * VERSION: 5.5.0
	 * EXAMPLE: This example shows how one can count the number of users in a project and check if a specific user has access to the project.
<pre>
// Get array of all project users
$users = REDCap::getUsers();

// Print out a count of number of users
print "This project contains " . count($users) . " users.\n";

// Check if a specific user has access to this project
$user_to_look_for = "jon_williams";
if (in_array($user_to_look_for, $users)) {
	print "User $user_to_look_for has access to this project.";
} else {
	print "User $user_to_look_for does NOT have access to this project.";
}
</pre>
	 */
	public static function getUsers()
	{
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Return user array
		return array_keys(User::getProjectUsernames());
	}
	
	
	/**
	 * SUMMARY: Returns a list of the user privileges for all users for the current project
	 * DESCRIPTION: array <b>REDCap::getUserRights</b> ( [ string <b>$username</b> = NULL ] )
	 * DESCRIPTION_TEXT: Returns a list of the user privileges for all users for the current project. If $username is specified as a single user's username, it will only return the user rights of that user. If a user is assigned to a role, then their user rights returned will reflect the role's rights.
	 * PARAM: username - Username of an individual user. If provided, it will only return the user rights of that user only. By default, NULL is used, in which it will return the user rights of all users for the current project.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * RETURN: Returns array of user privileges with usernames as 1st-level array keys and the rights attribute name as the 2nd-level array keys (the rights attribute names the column names come from the redcap_user_rights database table). NOTE: The usernames will *always* be returned in lowercase format as the array keys.
	 * VERSION: 5.5.0
	 * EXAMPLE: This example shows how one can loop through the rights of all users in a project to see which users' rights have expired. This examples uses the REDCap constant TODAY, which represents today's date in YMD format (e.g., 2013-07-18).
<pre>
// Get array of user privileges of all users in project
$rights = REDCap::getUserRights();

// For all users whose rights have expired, place their username into an array
$expired_users = array();
foreach ($rights as $this_username=>$these_rights) {
	// If user's expiration occurs before TODAY, then add to array
	if ($these_rights['expiration'] != "" && $these_rights['expiration'] < TODAY) {
		// Add to array
		$expired_users[] = $this_username;
	}
}

// Display expired users
var_dump($expired_users);
</pre>
	 * EXAMPLE: This example illustrates how one can check particular user privileges of a single user, specifically if the user has been granted the ability to create new records and if they are assigned to a data access group.
<pre>
// Manually set username of single user in project
$this_user = 'jon_williams';

// Get array of user privileges for a single user in project (will have username as array key)
$rights = REDCap::getUserRights($this_user);

// If $rights returns NULL, then user does not have access to this project
if (empty($rights)) exit("User $this_user does NOT have access to this project.");

// Check if user can create new records
if ($rights[$this_user]['record_create']) {
	print "User $this_user CAN create records.\n";
} else {
	print "User $this_user CANNOT create records.\n";
}

// Check if the user is in a data access group (DAG)
$group_id = $rights[$this_user]['group_id'];
// If $group_id is blank, then user is not in a DAG
if ($group_id == '') {
	print "User $this_user is NOT assigned to a data access group.";
} else {
	// User is in a DAG, so get the DAG's name to display
	print "User $this_user is assigned to the DAG named \"" . REDCap::getGroupNames(false, $group_id) 
		. "\", whose unique group name is \"" . REDCap::getGroupNames(true, $group_id) . "\".";
}

</pre>
	 */
	public static function getUserRights($username=null)
	{
		global $data_resolution_enabled;
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Get rights for this user or all users in project
		$rights = UserRights::getPrivileges(PROJECT_ID, $username);
		$rights = $rights[PROJECT_ID];
		// Loop through each user
		foreach ($rights as $this_user=>$attr) {
			// Parse form-level rights
			$allForms = explode("][", substr(trim($attr['data_entry']), 1, -1));
			foreach ($allForms as $forminfo) 
			{
				list($this_form, $this_form_rights) = explode(",", $forminfo, 2);
				$rights[$this_user]['forms'][$this_form] = $this_form_rights;
				unset($rights[$this_user]['data_entry']);
			}
			// Data resolution workflow: disable rights if module is disabled
			if ($data_resolution_enabled != '2') $rights[$this_user]['data_quality_resolution'] = '0';
		}
		// Return rights
		return $rights;
	}
	
	
	/**
	 * SUMMARY: Returns a list of event names (or unique event names) for all events defined in the current project (longitudinal projects only)
	 * DESCRIPTION: mixed <b>REDCap::getEventNames</b> ( [ bool <b>$unique_names</b> = FALSE [, bool <b>$append_arm_name</b> = FALSE [, int <b>$event_id</b> = NULL ]]] )
	 * DESCRIPTION_TEXT: Returns a list of event names (or unique event names) for all events defined in the current project. If $event_id is specified for a single event, it will return only the event name for that event.
	 * PARAM: unique_names - Set this to TRUE to return the unique event names for the events, else it will return the normal event names (i.e. event labels). By default, FALSE is used.
	 * PARAM: append_arm_name - Determines if the arm number and arm name should be appended to the event name if the current project contains multiple arms. If the project does not contain multiple arms, FALSE is used. If unique_names is set to TRUE, append_arm_name is set to FALSE.
	 * PARAM: event_id - Event_id of a single event defined in the current project. If provided, it will return the event name (as a string) for only that event. By default, NULL is used, in which it will return an array of all event names in the current project. If event_id is invalid, returns FALSE.
	 * RETURN: Returns array of event names with their corresponding event_id's as array keys. Returns FALSE if project is not longitidinal. The events are ordered in the order in which they are specified in the project.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 5.5.0
	 * EXAMPLE: This example shows how to simply display the event name of all events in a project.
<pre>
// Check if project is longitdudinal first
if (!REDCap::isLongitudinal()) exit("Cannot get event names because this project is not longitudinal.");

// Print out the names of all events in the project (append the arm name if multiple arms exist)
$events = REDCap::getEventNames(false, true);
foreach ($events as $event_name) {
	// Print this event name
	print $event_name . ",\n";
}
</pre>
	 * EXAMPLE: This example illustrates how to determine the first event's event_id in a project and export all data for only that one event.
<pre>
// Check if project is longitdudinal first
if (!REDCap::isLongitudinal()) exit("Cannot get event names because this project is not longitudinal.");

// Obtain array of all events in the project
$events = REDCap::getEventNames(false, true);

// Get event_id of the first event in the project (obtain from first array key in $events)
$first_event_id = array_shift(array_keys($events));

// Export data in CSV format for all records for that first event
$csv_data = REDCap::getData('csv', null, null, $first_event_id);

// Display all the CSV data on the page
print $csv_data;
</pre>
	 */
	public static function getEventNames($unique_names=false, $append_arm_name=false, $event_id=null)
	{
		global $Proj;
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Make sure project is longitudinal, else return FALSE
		if (!self::isLongitudinal()) return false;
		// If $event_id is not valid, then return FALSE
		if ($event_id != null && !isset($Proj->eventInfo[$event_id])) return false;
		// Get and return events
		if ($unique_names) {
			$events = $Proj->getUniqueEventNames($event_id);
		} else {
			// Validate $append_arm_name
			$append_arm_name = ($append_arm_name === true);
			// Loop through all events and collect event_id and name to return as array
			$events = array();
			foreach ($Proj->eventInfo as $this_event_id=>$attr) {
				// If event_id was specified, return only its event name
				if ($this_event_id == $event_id) {
					return ($append_arm_name ? $attr['name_ext'] : $attr['name']);
				} else {
					$events[$this_event_id] = ($append_arm_name ? $attr['name_ext'] : $attr['name']);
				}
			}
		}
		// Return events as array
		return $events;
	}
	
	
	/**
	 * SUMMARY: Returns a list of group names (or unique group names) for all data access groups defined in the current project
	 * DESCRIPTION: mixed <b>REDCap::getGroupNames</b> ( [ bool <b>$unique_names</b> = FALSE [, int <b>$group_id</b> = NULL ]] )
	 * DESCRIPTION_TEXT: Returns a list of group names (or unique group names) for all data access groups defined in the current project. If $group_id is specified for a single data access group, it will return only the unique group name for that data access group.
	 * PARAM: unique_names - Set this to TRUE to return the unique group names for the data access groups, else it will return the normal group names (i.e. group labels). By default, FALSE is used.
	 * PARAM: group_id - Group_id of a single data access group defined in the current project. If provided, it will return the group name (as a string) for only that data access group. By default, NULL is used, in which it will return an array of all unique group names in the current project. If group_id is invalid, returns FALSE.
	 * RETURN: Returns array of group names with their corresponding group_id's as array keys. Returns FALSE if no data access groups exist for the current project. The groups are ordered in the order in which they appear in the project.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 5.5.0
	 * EXAMPLE: This example shows how to simply display the names of all data access groups (DAGs) in a project.
<pre>
// Get all data access groups
$groups = REDCap::getGroupNames(false);

// Check if any DAGs exist in the project
if (empty($groups)) exit("Project does NOT contain any data access groups.");

// Print out the names of all DAGs
print "Groups: ";
foreach ($groups as $group_name) {
	// Print this DAG name
	print $group_name . ",\n";
}
</pre>
	 * EXAMPLE: This example illustrates how to obtain the unique group name for a single data access group using a group_id, as well as check if a group_id is valid for the current project.
<pre>
// Manually set the group_id for a single data access group
$group_id = 52;

// Get the unique group name for the DAG
$unique_group_name = REDCap::getGroupNames(true, $group_id);

// Check if group_id was valid (if so, will have returned FALSE)
if ($unique_group_name === false) {
	// Group_id is not valid
	print "Group_id $group_id is not a valid group_id for this project.";
} else {
	// Display the unique group name
	print "The unique group name for group_id $group_id is \"$unique_group_name\".";
}
</pre>
	 */
	public static function getGroupNames($unique_names=false, $group_id=null)
	{
		global $Proj;
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Get groups
		if ($unique_names) {
			$groups = $Proj->getUniqueGroupNames($group_id);
		} else {
			$groups = $Proj->getGroups($group_id);
		}
		// If no groups exist, return FALSE
		if (empty($groups)) return false;
		// Return groups as array
		return $groups;
	}
	
	
	/**
	 * SUMMARY:  Compares two "REDCap-standardized" version number strings
	 * DESCRIPTION: mixed <b>REDCap::versionCompare</b> ( string <b>$version1</b> , string <b>$version2</b> [, string <b>$operator</b> ] )
	 * DESCRIPTION_TEXT: Compares two "REDCap-standardized" version number strings. You may use the constant REDCAP_VERSION (i.e. the current REDCap version) for either parameter version1 or version2. This method is useful if you would like to write plugins that behave differently on different versions of REDCap.
	 * PARAM: version1 - First version number
	 * PARAM: version2 - Second version number
	 * PARAM: operator - If you specify the third optional operator argument, you can test for a particular relationship. The possible operators are: <b><, lt, <=, le, >, gt, >=, ge, ==, =, eq, !=, <>, ne</b> respectively. This parameter is case-sensitive, so values should be lowercase.
	 * RETURN: By default, returns -1 if the first version is lower than the second, 0 if they are equal, and 1 if the second is lower. When using the optional operator argument, the function will return TRUE if the relationship is the one specified by the operator, FALSE otherwise.
	 * VERSION: 5.5.0
	 * EXAMPLE: The examples below use the REDCAP_VERSION constant, which contains the value of the REDCap version that is executing the code.
<pre>
if (REDCap::versionCompare(REDCAP_VERSION, '6.0.0') >= 0) {
    echo 'I am at least REDCap version 6.0.0, my version: ' . REDCAP_VERSION . ".\n";
}
if (REDCap::versionCompare(REDCAP_VERSION, '5.3.0') >= 0) {
    echo 'I am at least REDCap version 5.3.0, my version: ' . REDCAP_VERSION . ".\n";
}
if (REDCap::versionCompare(REDCAP_VERSION, '5.0.0', '>=')) {
    echo 'I am using REDCap 5, my version: ' . REDCAP_VERSION . ".\n";
}
if (REDCap::versionCompare(REDCAP_VERSION, '5.0.0', '<')) {
    echo 'I am using REDCap 4 or an even earlier version, my version: ' . REDCAP_VERSION . ".\n";
}
</pre>
	 */
	public static function versionCompare($version1, $version2, $operator=null)
	{
		// Use PHP's version_compare, which does exactly the same thing
		if ($operator == null) {
			return version_compare($version1, $version2);
		} else {
			return version_compare($version1, $version2, $operator);
		}
	}
	
	
	// Add leading zeroes inside version number (remove dots)
	private static function GetDecimalVersion($dotVersion) {
		list ($one, $two, $three) = explode(".", $dotVersion);
		return ($one . sprintf("%02d", $two) . sprintf("%02d", $three))*1;
	}
	
	
	/**
	 * SUMMARY: Limit a plugin's use only to specific REDCap projects
	 * DESCRIPTION: bool <b>REDCap::allowProjects</b> ( mixed <b>$project_ids</b> )
	 * DESCRIPTION_TEXT: Limit a plugin's use only to specific REDCap projects, in which the plugin will only function for the projects that are explicitly specified in the parameter of this method. It is recommended that this method be placed closer to the beginning of the plugin script (e.g., immediately after "require redcap_connect.php").<br><br>NOTE: As of version 5.5.0, this method supercedes the older global scope function allowProjects(), which will still continue to work if already used. The method REDCap::allowProjects() operates exactly the same as the older allowProjects() function.
	 * PARAM: project_ids - A list of project_id's for the REDCap projects for which this plugin will function. Either an array of project_id's or a comma-delimited list of project_id's (i.e. each project_id is a separate argument/parameter).
	 * RETURN: TRUE is returned if the current project is in the list of allowable projects that can use this plugin. If not or if the "pid" parameter is not found in the query string of the plugin URL, the plugin script will terminate right after displaying an HTML error on the page.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 5.5.0
	 * EXAMPLE: This example demonstrates how to limit the plugin's use to three specific projects by passing the project_id's as an array list.
<pre>
// Limit this plugin only to projects with project_id 3, 12, and 45
$projects = array(3, 12, 45);
allowProjects($projects);
</pre>
	 * EXAMPLE: This example shows how to limit the plugin's use to two specific projects by passing the project_id's as separate arguments/parameters to the method.
<pre>
// Limit this plugin only to projects with project_id 56 and 112
allowProjects(56, 112);
</pre>
	 * EXAMPLE: This example illustrates how to utilize both REDCap::allowProjects() and REDCap::allowUsers() together to limit the plugin's use to two specific users in two specific projects
<pre>
// Limit this plugin only to users 'taylorr4' and 'harrispa' in projects with project_id 56 and 112
allowProjects(56, 112);
allowUsers('taylorr4', 'harrispa');
</pre>
	 */
	public static function allowProjects()
	{
		global $lang;
		// Set error message
		$error_msg = "<div style='background-color:#FFE1E1;border:1px solid red;max-width:700px;padding:6px;color:#800000;'>
						<img src='" . APP_PATH_IMAGES . "exclamation.png' class='imgfix'> 
						<b>{$lang['global_05']}</b> {$lang['config_05']}
					</div>";
		// Get arguments passed
		$args = func_get_args();
		// If project_id is not defined (i.e. not a project-level page) OR if no project_id's are provided, then display error message
		if (!defined("PROJECT_ID") || empty($args)) exit($error_msg);
		// Set flag if the project_id does not exist as a parameter
		$projectIdNotFound = true;
		// Loop through all project_ids as parameter
		foreach ($args as $item) {
			if (is_array($item)) {
				if (empty($item)) return false;
				foreach ($item as $project_id) {
					if ($project_id == PROJECT_ID) {
						$projectIdNotFound = false;
					}
				}
			} else {
				if ($item == PROJECT_ID) {
					$projectIdNotFound = false;
				}
			}
		}
		// Now do a check if the project_id for this project was not set as a parameter
		if ($projectIdNotFound) exit($error_msg);
		// If we made it this far, return true
		return true;
	}
	
	
	/**
	 * SUMMARY: Limit a plugin's use only to specific REDCap users
	 * DESCRIPTION: bool <b>REDCap::allowUsers</b> ( mixed <b>$usernames</b> )
	 * DESCRIPTION_TEXT: Limit a plugin's use only to specific REDCap users, in which the plugin will only function for the users that are explicitly specified in the parameter of this method. It is recommended that this method be placed closer to the beginning of the plugin script (e.g., immediately after "require redcap_connect.php").<br><br>NOTE: As of version 5.5.0, this method supercedes the older global scope function allowUsers(), which will still continue to work if already used. The method REDCap::allowUsers() operates exactly the same as the older allowUsers() function.
	 * PARAM: usernames - A list of usernames for the REDCap users for which this plugin will function. Either an array of usernames (each as a string) or a comma-delimited list of usernames (each as a string), in which each username is a separate argument/parameter.
	 * RETURN: TRUE is returned if the current user's username is in the list of allowable usernames that can use this plugin. If not, the plugin script will terminate right after displaying an HTML error on the page. If authentication has been disabled for the plugin script, it will return FALSE.
	 * VERSION: 5.5.0
	 * EXAMPLE: This example demonstrates how to limit the plugin's use to three specific users by passing the usernames as an array list.
<pre>
// Limit this plugin only to users 'taylorr4', 'minorbl', and 'harrispa'
$users = array('taylorr4', 'minorbl', 'harrispa');
allowUsers($users);
</pre>
	 * EXAMPLE: This example shows how to limit the plugin's use to two specific users by passing the usernames as separate arguments/parameters to the method.
<pre>
// Limit this plugin only to users 'taylorr4' and 'harrispa'
allowUsers('taylorr4', 'harrispa');
</pre>
	 * EXAMPLE: This example illustrates how to utilize both REDCap::allowProjects() and REDCap::allowUsers() together to limit the plugin's use to two specific users in two specific projects
<pre>
// Limit this plugin only to users 'taylorr4' and 'harrispa' in projects with project_id 56 and 112
allowProjects(56, 112);
allowUsers('taylorr4', 'harrispa');
</pre>
	 */
	public static function allowUsers()
	{
		global $lang;
		// Set error message
		$error_msg = "<div style='background-color:#FFE1E1;border:1px solid red;max-width:700px;padding:6px;color:#800000;'>
						<img src='" . APP_PATH_IMAGES . "exclamation.png' class='imgfix'> 
						<b>{$lang['global_05']}</b> {$lang['config_05']}
					</div>";
		// Get arguments passed
		$args = func_get_args();
		// If authentication has been disabled, then return false with no error warning
		if (defined("NOAUTH")) return false;
		// If userid is not defined OR if no userid's were provided, then display error message
		if (!defined("USERID") || empty($args)) exit($error_msg);
		// Set flag if the userid does not exist as a parameter
		$userIdNotFound = true;
		// Loop through all project_ids as parameter
		foreach ($args as $item) {
			if (is_array($item)) {
				if (empty($item)) return false;
				foreach ($item as $userid) {
					if ($userid == USERID) {
						$userIdNotFound = false;
					}
				}
			} else {
				if ($item == USERID) {
					$userIdNotFound = false;
				}
			}
		}
		// Now do a check if the userid was not set as parameter
		if ($userIdNotFound) exit($error_msg);
		// If we made it this far, return true
		return true;
	}
	
	
	/**
	 * SUMMARY: Create a custom logged event
	 * DESCRIPTION: void <b>REDCap::logEvent</b> ( string <b>$action_description</b> [, string <b>$changes_made</b> = NULL [, string <b>$sql</b> = NULL [, string <b>$record</b> = NULL [, string <b>$event</b> = NULL ]]]] )
	 * DESCRIPTION_TEXT: Create a custom logged event, which will be displayed in the Control Center's Activity Log, and if a project-level plugin, it will be associated with the project and thus displayed on the project's Logging page.
	 * PARAM: action_description - A short description of the action being performed. This can be whatever text you wish, either a custom action specific to your plugin (e.g., "Perform meta-analysis", "Export data to EMR") or an existing REDCap logged action type (e.g., "Updated Record"). The action_description will be displayed as-is on the Control Center's Activity Log and (if a project-level plugin) the project's Logging page.
	 * PARAM: changes_made - (optional) A string of text listing any notable changes made (not necessarily data values for a project). If a project-level plugin, this text will be displayed in the List of Changes column on the project's Logging page. For display purposes on the Logging page, you may use <span style="font-size:14px;font-weight:bold;font-family:monospace;">\n</span> or natural line breaks in the string to begin a new line for each item you are listing.
	 * PARAM: sql - (optional) An SQL query executed by the plugin that you wish to associate with this logged event (e.g., the SQL used if a database table was queried). You may input multiple queries together by delimiting them with a semi-colon all as a single string. This will never be displayed within REDCap anywhere but is merely for record keeping, in which it will stay stored in the redcap_log_event database table for reference/audit purposes.
	 * PARAM: record - (optional) The name of the record, assuming this logged event involves a record (e.g., data changes). If this is set, the logged event will be filterable by that record name on a project's Logging page.
	 * PARAM: event - (optional) The event_id number OR the unique event name of a REDCap event in a project, assuming this logged event involves a record (e.g., data changes).
	 * RETURN: Returns nothing.
	 * VERSION: 5.5.1
	 * EXAMPLE: This example demonstrates how one might log a specific data change to a record in project.
<pre>
// Update the data table (assumes a value already exists for this field)
$sql = "update redcap_data set value = 'Paul' where project_id = 43 
		and record = '1002' and event_id = 78 and field_name = 'first_name'";
if (db_query($sql)) 
{
	// Log the data change
	REDCap::logEvent("Updated Record", "study_id = '1002',\nfirst_name = 'Paul'", $sql, '1002', 78);
}
</pre>
	 * EXAMPLE: This example shows how data values stored in an array can be logged as a custom logged event.
<pre>
// Array of data with REDCap variable name as array key
$data = array(
	'record_id' => '23-4832',
	'hypertension' => '1',
	'type_diabetes' => '2',
	'age' => '66'
);

// [Perform plugin actions here]

// Format $data array for logging. First put into array, then implode into string.
$data_formatted = array();
foreach ($data as $this_field => $this_value) {
	$data_formatted[] = "$this_field = '$this_value'";
} 
$data_changes = implode(",\n", $data_formatted);

// Log the event
REDCap::logEvent("Imported data from I2B2", $data_changes, NULL, '23-4832', 'visit1_arm1');
</pre>
	 * EXAMPLE: This example shows how to log only the action that occurred without including any other related information.
<pre>
REDCap::logEvent("Downloaded attendee report");
</pre>
	 */
	public static function logEvent($description, $changes_made="", $sql="", $record=null, $event_id=null)
	{	
		// In case event_id exists in query string, temporary remove to prevent it from being used be log_event
		if (isset($_GET['event_id'])) {
			$get_event_id = $_GET['event_id'];
		}
		// If event_id OR unique event name is provided, set in GET so that log_event picks it up (will be removed later)
		if ($event_id != null) {
			if (is_numeric($event_id)) {
				$_GET['event_id'] = $event_id;
			} elseif (defined("PROJECT_ID")) {
				// If this is a project-level plugin, get event_id from unique event name
				global $Proj;
				$unique_events = $Proj->getUniqueEventNames();
				$event_id_key = array_search($event_id, $unique_events);
				$_GET['event_id'] = ($event_id_key !== false) ? $event_id_key : null;
			}
		}
		// Call log_event
		log_event($sql, "", "OTHER", $record, $changes_made, $description);
		// Reset event_id in query string, if was originally there
		if (isset($get_event_id)) {
			$_GET['event_id'] = $get_event_id;
		} else {
			unset($_GET['event_id']);
		}
	}
	
	
	/**
	 * SUMMARY: Send an email to one or more receipients
	 * DESCRIPTION: bool <b>REDCap::email</b> ( string <b>$to</b>, string <b>$from</b>, string <b>$subject</b>, string <b>$message</b> [, string <b>$cc</b>] )
	 * DESCRIPTION_TEXT: Provides a simple way to send emails to one or more recipients without having to format complicated headers, such as with PHP's mail() function. Since this method natively uses UTF-8 encoding, it is okay to use special non-Latin characters in either the email subject or message text.
	 * PARAM: to - The recipient's email address. If using more than one email address, they must be separated by commas.
	 * PARAM: from - The sender's email address (i.e., from whom the email will appear to be sent). This will also be the "reply-to" address as it appears to the recipient.
	 * PARAM: subject - The email subject.
	 * PARAM: message - The email message text. You may use HTML in the message, and if you wish to do so, you will need to wrap the entire message text in &lt;html&gt;&lt;body&gt;...&lt;/body&gt;&lt;/html&gt; tags.
	 * PARAM: cc - The email address of someone being CC'd on this email. If using more than one email address, they must be separated by commas.
	 * RETURN: TRUE is returned if the email has been sent successfully, else FALSE if not.
	 * VERSION: 5.11.0
	 * EXAMPLE: This example shows how to send a basic email.
<pre>
// Set the text of the email first
$email_text = "A participant (record '$record') noted on the survey that they are suicidal. "
			. "Please take appropriate actions immediately to contact them.";
			
// Send the email
REDCap::email('surveyadmin@mystudy.com', 'redcap@yoursite.edu', 'Suicide alert', $email_text);
</pre>
	 * EXAMPLE: This example illustrates how to send an HTML email with some styling.
<pre>
// Set the text and HTML of the email first
$email_text =  '&lt;html&gt;&lt;body style="font-family:Arial;font-size:10pt;"&gt;
				You can use HTML to &lt;b&gt;bold&lt;/b&gt; text in the email, or style it
				with &lt;span style="color:red;"&gt;red text&lt;/span&gt;. You can also
				add &lt;a href="http://mysite.com"&gt;links&lt;/a&gt; to your email text.
				&lt;/body&gt;&lt;/html&gt;';

// Send the HTML email
REDCap::email('recipient@mysite.com', 'sender@yoursite.edu', 'Suicide alert', $email_text);
</pre>
	 * EXAMPLE: This example shows how to send a basic email with error catching if the email does not send successfully.
<pre>
// Send the email
$sentSuccessfully = REDCap::email('recipient@mysite.com', 'redcap@yoursite.edu', 
					'My custom subject', 'My email generic text to recipient.');

// If not sent successfully, display an error message to user
if (!$sentSuccessfully) {
	print "&lt;div class='red'&gt;ERROR: The email could not be sent!&lt;/div&gt;";
}
</pre>
	 */
	public static function email($to='', $from='', $subject='', $message='', $cc='')
	{
		$email = new Message();
		$email->setTo($to);
		if ($cc != '') $email->setCc($cc);
		$email->setFrom($from);
		$email->setSubject($subject);
		$email->setBody($message);
		return $email->send();
	}
	
	
	/**
	 * SUMMARY: Returns a list of data collection instruments (both unique instrument name and label) for the current project
	 * DESCRIPTION: mixed <b>REDCap::getInstrumentNames</b> ( [ mixed <b>$instruments</b> = NULL ] )
	 * DESCRIPTION_TEXT: Returns a list of data collection instruments (both unique instrument name and label) for the current project. If $instruments parameter is supplied (as array or string), it will only return the data collection instrument(s) provided in the array or string. If $instruments is specified for a single data collection instrument, it will return only the label for that instrument.<br><br>NOTE: If the project is in production status with Draft Mode enabled, it will NOT output any of the instruments from Draft Mode. It will always only output the instruments as they are seen on data entry forms and survey pages, regardless of the project's development/production status.
	 * PARAM: instruments - If provided as an array of data collection instrument names (i.e. the unique name, not the instrument label), it will return an array of only those instruments. If provided as a single instrument name (string), it will return only the label for that instrument. By default, NULL is used, in which it will return all instruments for the entire project.
	 * RETURN: Returns array of instrument labels with their corresponding unique instrument name as array keys. The instruments are ordered in the order in which they are specified in the project. If $instruments is provided as a single instrument name (string), it will return only the label for that instrument. 
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 5.11.0
	 * EXAMPLE: This example shows how to simply display the unique instrument name and label of all data collection instruments in a project.
<pre>
// Print out the names of all instruments in the project
$instrument_names = REDCap::getInstrumentNames();

foreach ($instrument_names as $unique_name=>$label) 
{
    // Print this instrument name and label
    print "$unique_name => $label,\n";
}
</pre>
	 * EXAMPLE: This example illustrates how to get the label of a single instrument.
<pre>
// We have our unique instrument name
$unique_name = 'enrollment_form';

// Get the label of our instrument
$instrument_label = REDCap::getInstrumentNames($unique_name);
</pre>
	 */
	public static function getInstrumentNames($instruments=null)
	{
		global $Proj;	
		$forms = array();
		$returnSingleLabel = false;
		if ($instruments === null || (is_array($instruments) && empty($instruments))) {
			$instruments = array_keys($Proj->forms);
		} elseif (!is_array($instruments)) {
			$returnSingleLabel = true;
			$instruments = array($instruments);
		}
		foreach ($Proj->forms as $form=>$attr) {
			if (in_array($form, $instruments)) {
				$label = strip_tags(html_entity_decode($attr['menu'], ENT_QUOTES));
				if ($returnSingleLabel) return $label;
				$forms[$form] = $label;
			}
		}
		return $forms;
	}
	
	
	/**
	 * SUMMARY: Escapes a string of text or HTML for outputting to a webpage
	 * DESCRIPTION: string <b>REDCap::escapeHtml</b> ( string <b>$string</b> )
	 * DESCRIPTION_TEXT: Escapes a string of text or HTML for outputting to a webpage. If the text being printed to the page is user input (i.e., was originally generated by a user), then it is highly recommended to escape it to prevent any possibility of Cross-site Scripting (XSS).
	 * PARAM: string - Text string to be escaped.
	 * RETURN: Returns the escaped string.
	 * VERSION: 5.11.0
	 * EXAMPLE: This example shows how to print a string of text on a webpage literally so that any HTML tags inside the text do not get interpreted. The output of the example below should be the following:<br><br>Here's my &lt;b&gt;bold&lt;/b&gt; text. Attempt to perform cross-site scripting with &lt;script&gt;alert('XSS successful!')&lt;/script&gt;
<pre>
// Set the text value
$text = "Here's my &lt;b&gt;bold&lt;/b&gt; text. Attempt to perform cross-site scripting 
		 with &lt;script&gt;alert('XSS successful!')&lt;/script&gt;";

// Escape the text and output it to the webpage, which should display the string *exactly* 
// as you see $text displayed above. If the string were not escaped, the word "bold" would
// appear in bold on the page, and it would cause a JavaScript pop-up saying "XSS successful!".
print REDCap::escapeHtml($text);
</pre>
	 */
	public static function escapeHtml($string)
	{
		return RCView::escape($string, false);	
	}
	
	
	/**
	 * SUMMARY: Filters a string of text to remove any potentially harmful HTML tags or potentially harmful attributes inside allowable HTML tags
	 * DESCRIPTION: string <b>REDCap::filterHtml</b> ( string <b>$string</b> )
	 * DESCRIPTION_TEXT: Filter a string of text to remove any potentially harmful HTML tags (e.g., &lt;script&gt;, &lt;embed&gt;) or potentially harmful attributes inside allowable HTML tags (e.g., &lt;a onclick="..." onselect="..."&gt;...&lt;/a&gt;). The main application of this method is for outputing to a webpage some text that may contain HTML, in which you wish for all the HTML tags to be interpreted properly by the web browser while removing any potentially harmful tags that might exist in the text, such as &lt;script&gt;, which can be used maliciously for attempting Cross-site Scripting (XSS). If any allowable HTML tags, such as &lt;a&gt;, contain attributes deemed potentially harmful, it will not remove the whole HTML tag but instead will only remove the attribute from inside the tag.<br><br>NOTE: The HTML tags that are allowable and will NOT get filtered from the text are &lt;label&gt;&lt;pre&gt;&lt;p&gt;&lt;a&gt;&lt;br&gt;&lt;br/&gt;&lt;center&gt;&lt;font&gt;&lt;b&gt;&lt;i&gt;&lt;u&gt;&lt;h3&gt;&lt;h2&gt;&lt;h1&gt;&lt;hr&gt;&lt;table&gt;&lt;tr&gt;&lt;th&gt;&lt;td&gt;&lt;img&gt;&lt;span&gt;&lt;div&gt;&lt;em&gt;&lt;strong&gt;&lt;acronym&gt;.
	 * PARAM: string - Text string to be filtered.
	 * RETURN: Returns the filtered string.
	 * VERSION: 5.11.0
	 * EXAMPLE: This example shows how to print a string of text on a webpage so that allowable HTML tags get interpreted properly by the browser while potentially malicious tags are filtered out. The output of the example below should be the following:<br><br>Here's my <b>bold</b> text. Attempt to perform cross-site scripting with alert('XSS successful!')
<pre>
// Set the text value
$text = "Here's my &lt;b&gt;bold&lt;/b&gt; text. Attempt to perform cross-site scripting 
		 with &lt;script&gt;alert('XSS successful!')&lt;/script&gt;";

// Filter the text and output it to the webpage
print REDCap::filterHtml($text);
</pre>
	 */
	public static function filterHtml($string)
	{
		return filter_tags($string);	
	}
	
	
	/**
	 * SUMMARY: Obtains a survey participant's email address using the record name to which it belongs (assumes the record already exists)
	 * DESCRIPTION: string <b>REDCap::getParticipantEmail</b> ( string <b>$record</b> )
	 * DESCRIPTION_TEXT: Obtains a survey participant's email address using the record name to which it belongs (assumes the record already exists). This method will first check if there exists an email address entered into the Participant List of the first survey instrument, and if not, it will then retrieve the value of the designated email field for the record (if the designated email field option has been enabled on the Project Setup page).
	 * PARAM: record - The name of the record/response to which the participant's email address belongs.
	 * RETURN: Returns the survey participant's email address if exists, else it returns NULL.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 5.11.0
	 * EXAMPLE: This example illustrates how obtain the email address for a particular participant record.
<pre>
// We have our record name
$record = '101';

// Get the email address of this participant record
$email_address = REDCap::getParticipantEmail($record);
</pre>
	 */
	public static function getParticipantEmail($record)
	{
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Return NULL if no record name
		if ($record == '') return null;
		// Get email/identifier
		$array = Survey::getResponsesEmailsIdentifiers(array($record));
		// Return email address, or if missing, return NULL
		return (isset($array[$record]['email']) && $array[$record]['email'] != '' ? $array[$record]['email'] : null);	
	}
	
	
	/**
	 * SUMMARY: Returns the list of all participants for a specific survey instrument (and for a specific event, if a longitudinal project)
	 * DESCRIPTION: mixed <b>REDCap::getParticipantList</b> ( string <b>$instrument</b> [, int <b>$event_id</b> [, string <b>$return_format</b> = 'array' ]] )
	 * DESCRIPTION_TEXT: Returns the list of participants for a specific survey instrument (and for a specific event, if a longitudinal project). This method assumes the instrument has already been enabled as a survey in the project.
	 * PARAM: instrument - The name of the data collection instrument (i.e., the unique name, not the instrument label) to which this survey corresponds. This corresponds to the value of Column B in the Data Dictionary.
	 * PARAM: event_id - (longitudinal projects only) The event ID number that corresponds to a defined event in a longitudinal project. For classic projects, the event_id is not explicitly required, and thus it will be supplied automatically since there will only ever be one event_id for the project.
	 * PARAM: return_format - The format in which the list should be returned. Valid options: 'array', 'csv', 'json', and 'xml'. By default, 'array' is used.
	 * RETURN: Returns the list of all participants for the specified survey instrument [and event] in the desired format. The following fields are returned: email, email_occurrence, identifier, invitation_sent_status, invitation_send_time, response_status, survey_access_code, survey_link. The attribute "email_occurrence" represents the current count that the email address has appeared in the list (because emails can be used more than once), thus email + email_occurrence represent a unique value pair. "invitation_sent_status" is "0" if an invitation has not yet been sent to the participant, and is "1" if it has. "invitation_send_time" is the date/time in which the next invitation will be sent, and is blank if there is no invitation that is scheduled to be sent. "response_status" represents whether the participant has responded to the survey, in which its value is 0, 1, or 2 for "No response", "Partial", or "Completed", respectively. Note: If an incorrect event_id or instrument name is used or if the instrument has not been enabled as a survey, then NULL will be returned.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 6.4.0
	 * EXAMPLE: This example illustrates how to obtain the participant list as an array for a classic (non-longitudinal) project.
<pre>
// The unique instrument name for the survey
$instrument = 'participant_info_survey';

// Get the participant list for this instrument
$participant_list_array = REDCap::getParticipantList($instrument);
</pre>
	 * EXAMPLE: This example illustrates how to obtain the participant list in JSON format for a classic (non-longitudinal) project.
<pre>
// The unique instrument name for the survey
$instrument = 'participant_info_survey';

// Get the participant list for this instrument
$participant_list_json = REDCap::getParticipantList($instrument, NULL, 'json');
</pre>
	 * EXAMPLE: This example demonstrates how to obtain the participant list in CSV format for a specific survey and event in a longitudinal project.
<pre>
// The unique instrument name for the survey and the event_id for the event
$instrument = 'participant_info_survey';
$event_id = 339;

// Get the participant list for this instrument-event
$participant_list_csv = REDCap::getParticipantList($instrument, $event_id, 'csv');
</pre>
	 */
	public static function getParticipantList($instrument='', $event_id='', $return_format='array')
	{
		global $longitudinal, $Proj, $lang;
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// If a longitudinal project and no event_id is provided, return null
		if ($longitudinal && !is_numeric($event_id)) return null;
		// If a non-longitudinal project, then set event_id automatically 
		if (!$longitudinal) $event_id = $Proj->firstEventId;
		// If instrument is not a survey, return null
		if (!isset($Proj->forms[$instrument]['survey_id'])) return null;		
		// Set array of valid $return_format values
		$return_format = trim(strtolower($return_format));
		$validReturnFormats = array('csv', 'xml', 'json', 'array');
		// If $return_format is not valid, set to default 'array'
		if (!in_array($return_format, $validReturnFormats)) $return_format = 'array';
		// Set value of edit_completed_response
		$edit_completed_response = $Proj->surveys[$Proj->forms[$instrument]['survey_id']]['edit_completed_response'];
		// Get survey functions
		require_once APP_PATH_DOCROOT . "Surveys/survey_functions.php";
		// Gather participant list (with identfiers and if Sent/Responded)
		list ($part_list, $part_list_duplicates) = getParticipantList($Proj->forms[$instrument]['survey_id'], $event_id);
		// Get survey queue hash for these participants (if survey queue is enabled)
		$surveyQueueEnabled = Survey::surveyQueueEnabled();
		if ($surveyQueueEnabled) 
		{
			 // Create array of all the record names
			$records = array();
			foreach ($part_list as $this_part=>$attr) {
				// Add record name to array
				$records[] = $attr['record'];
			}
			// Get all survey queue hashes
			$sq_hashes = Survey::getRecordSurveyQueueHashBulk($records);
			// Add survey queue hash to each participant
			foreach ($part_list as $this_part=>$attr) {
				// Add record name to array
				$part_list[$this_part]['survey_queue_hash'] = $sq_hashes[$attr['record']];
			}
			unset($records, $sq_hashes);
		}
		// Get survey access codes
		$partIdAccessCodes = Survey::getAccessCodes(array_keys($part_list));
		foreach ($partIdAccessCodes as $this_part=>$this_access_code) {
			$part_list[$this_part]['access_code'] = $this_access_code;
		}
		unset($partIdAccessCodes);
		// Set headers		
		$headers = array('email', 'email_occurrence');
		if ($Proj->project['twilio_enabled']) {
			$headers[] = 'phone';
		}
		$headers[] = 'identifier';
		$headers[] = 'invitation_sent_status';
		$headers[] = 'invitation_send_time';
		$headers[] = 'response_status';
		$headers[] = 'survey_access_code';
		$headers[] = 'survey_link';
		if ($surveyQueueEnabled) {
			$headers[] = 'survey_queue_link';
		}
		// Do some more formatting
		$part_list2 = array();
		$i = 0;
		foreach ($part_list as $key=>$row)
		{
			// Set email occurrence number
			if ($part_list_duplicates[strtolower($row['email'])]['total'] > 1) {
				// Set current email occurrence
				$row['email_occurrence'] = $part_list_duplicates[strtolower($row['email'])]['current'];
				// Increment current email number for next time 
				$part_list_duplicates[strtolower($row['email'])]['current']++;
			} else {
				$row['email_occurrence'] = 1;
			}
			// Decode the identifier
			if ($row['identifier'] != "") {
				$row['identifier'] = label_decode($row['identifier']);
			}
			// Set survey access code to blank if response is completed
			if ($row['response'] == '2' && !$edit_completed_response) {
				$row['access_code'] = '';
			}
			// Convert hashes to full URLs (but only if they have NOT completed the survey yet)
			$survey_link = ($row['response'] == '2' && !$edit_completed_response) ? '' : APP_PATH_SURVEY_FULL."?s=".$row['hash'];
			if ($surveyQueueEnabled) {
				$survey_queue_link = (isset($row['survey_queue_hash']) ? APP_PATH_SURVEY_FULL."?sq=".$row['survey_queue_hash'] : '');
			}
			
			// ADD ATTRS: Reset the order of all fields
			$part_list2[$i] = array('email'=>$row['email'], 'email_occurrence'=>$row['email_occurrence']);
			// If not using phone numbers, then remove that column
			if ($Proj->project['twilio_enabled']) {
				$part_list2[$i]['phone'] = formatPhone($row['phone']);
			}
			// Other attrs
			$part_list2[$i]['identifier'] = $row['identifier'];
			$part_list2[$i]['invitation_sent_status'] = $row['sent'];
			$part_list2[$i]['invitation_send_time'] = $row['scheduled'];
			$part_list2[$i]['response_status'] = $row['response'];
			$part_list2[$i]['survey_access_code'] = $row['access_code'];
			$part_list2[$i]['survey_link'] = $survey_link;
			if ($surveyQueueEnabled) {
				$part_list2[$i]['survey_queue_link'] = $survey_queue_link;
			}
			// Increment new key
			$i++;
			// Remove attr to preserve memory as we go
			unset($part_list[$key]);
		}		
		## Return array of participants in desired format
		if ($return_format == 'array') {
			// Array
			return $part_list2;
		} elseif ($return_format == 'csv') {
			// CSV
			// Open connection to create file in memory and write to it
			$fp = fopen('php://memory', "x+");
			// Add header row to CSV
			fputcsv($fp, $headers);
			// Loop through array and output line as CSV
			foreach ($part_list2 as $key=>&$line) {
				// Write this line to CSV file
				fputcsv($fp, $line);
				// Remove line from array to free up memory as we go
				unset($part_list2[$key]);
			}
			// Open file for reading and output to user
			fseek($fp, 0);
			$csv_file_contents = stream_get_contents($fp);
			fclose($fp);
			// Return CSV string
			return $csv_file_contents;		
		} elseif ($return_format == 'json') {
			// JSON
			// Convert all data into JSON string (do record by record to preserve memory better)
			$json = '';
			foreach ($part_list2 as $key=>&$item) {
				// Loop through each record and encode
				$json .= ",".json_encode($item);
				// Remove line from array to free up memory as we go
				unset($part_list2[$key]);
			}
			return '[' . substr($json, 1) . ']';
		} elseif ($return_format == 'xml') {
			// XML
			// Convert all data into XML string
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<participants>\n";				
			// Loop through array and add to XML string
			foreach ($part_list2 as $key=>&$item) {
				// Begin item
				$xml .= "<participant>";
				// Loop through all fields/values
				foreach ($item as $this_field=>$this_value) {
					// If ]]> is found inside this value, then "escape" it (cannot really escape it but can do clever replace with "]]]]><![CDATA[>")
					if (strpos($this_value, "]]>") !== false) {
						$this_value = str_replace("]]>", "]]]]><![CDATA[>", $this_value);
					}
					// Add value
					$xml .= "<$this_field><![CDATA[$this_value]]></$this_field>";
				}
				// End item
				$xml .= "</participant>\n";
				// Remove line from array to free up memory as we go
				unset($part_list2[$key]);
			}
			// End XML string
			$xml .= "</participants>";
			// Return XML string
			return $xml;
		}
	}
	
	
	/**
	 * SUMMARY: Obtains the survey link for a specific record on a specific survey instrument (and for a specific event, if a longitudinal project) - assumes the record already exists
	 * DESCRIPTION: string <b>REDCap::getSurveyLink</b> ( string <b>$record</b>, string <b>$instrument</b> [, int <b>$event_id</b> ] )
	 * DESCRIPTION_TEXT: Obtains the survey link for a specific record on a specific survey instrument (and for a specific event, if a longitudinal project). This method assumes the record already exists.
	 * PARAM: record - The name of the record/response to which the survey link belongs.
	 * PARAM: instrument - The name of the data collection instrument (i.e., the unique name, not the instrument label) to which this survey corresponds. This corresponds to the value of Column B in the Data Dictionary.
	 * PARAM: event_id - (longitudinal projects only) The event ID number that corresponds to a defined event in a longitudinal project. For classic projects, the event_id is not explicitly required, and thus it will be supplied automatically since there will only ever be one event_id for the project.
	 * RETURN: Returns the survey link (i.e., full survey URL) for this record-instrument[-event], else it returns NULL if survey link was not found (i.e., if any parameters are incorrect).
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 5.11.0
	 * EXAMPLE: This example illustrates how to obtain the survey link for a specific record for a specific survey instrument in a classic (non-longitudinal) project.
<pre>
// We have our record name and instrument name
$record = '101';
$instrument = 'participant_info_survey';

// Get the survey link for this record-instrument
$survey_link = REDCap::getSurveyLink($record, $instrument);
</pre>
	 * EXAMPLE: This example demonstrates how to obtain the survey link for a specific record-survey-event longitudinal project.
<pre>
// We have our record name, instrument name, and event_id
$record = '101';
$instrument = 'participant_info_survey';
$event_id = 339;

// Get the survey link for this record-instrument-event
$survey_link = REDCap::getSurveyLink($record, $instrument, $event_id);
</pre>
	 */
	public static function getSurveyLink($record='', $instrument='', $event_id='')
	{
		global $longitudinal, $Proj;
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Return NULL if no record name or not instrument name
		if ($record == '' || $instrument == '') return null;
		// If a longitudinal project and no event_id is provided, return null
		if ($longitudinal && !is_numeric($event_id)) return null;
		// If a non-longitudinal project, then set event_id automatically 
		if (!$longitudinal) $event_id = $Proj->firstEventId;
		// If instrument is not a survey, return null
		if (!isset($Proj->forms[$instrument]['survey_id'])) return null;
		// Get hash
		$array = Survey::getFollowupSurveyParticipantIdHash($Proj->forms[$instrument]['survey_id'], $record, $event_id);
		// If did not return a hash, return null
		if (!isset($array[1])) return null;
		// Return full survey URL
		return APP_PATH_SURVEY_FULL . '?s=' . $array[1];
	}
	
	
	/**
	 * SUMMARY: Obtains the survey queue link for a specific record in a project in which the Survey Queue has been enabled
	 * DESCRIPTION: string <b>REDCap::getSurveyQueueLink</b> ( string <b>$record</b> )
	 * DESCRIPTION_TEXT: Obtains the survey queue link for a specific record in a project in which the Survey Queue has been enabled. If the Survey Queue has not been enabled, then NULL will be returned. NOTE: The survey queue link is different from a survey link, which will be unique for each record-instrument[-event]. There will only ever be one survey queue link per record in a project, while there may be many survey links for the record (depending on the number of surveys and events in the project). For more information on the Survey Queue, see the documentation inside the Survey Queue popup on the Online Designer page in any project.
	 * PARAM: record - The name of the record/response in the project.
	 * RETURN: Returns the survey queue link (i.e., full survey queue URL) for this record. If the Survey Queue has not been enabled, then NULL will be returned.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 5.11.0
	 * EXAMPLE: This example illustrates how to obtain the survey queue link for a specific record.
<pre>
// We have our record name
$record = '101';

// Get the survey queue link for this record
$survey_queue_link = REDCap::getSurveyQueueLink($record);
</pre>
	 */
	public static function getSurveyQueueLink($record='')
	{
		global $longitudinal, $Proj;
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Return NULL if no record name
		if ($record == '') return null;
		// If survey queue is not enabled for this project yet, return null
		if (!Survey::surveyQueueEnabled()) return null;
		// Obtain the survey queue hash for this record
		$survey_queue_hash = Survey::getRecordSurveyQueueHash($record);
		if ($survey_queue_hash == '') return null;
		// Return full survey URL
		return APP_PATH_SURVEY_FULL . '?sq=' . $survey_queue_hash;
	}
	
	
	/**
	 * SUMMARY: Obtains the return code for a specific record on a specific survey instrument (and for a specific event, if a longitudinal project), in which the "Save & Return Later" feature is enabled for the survey - assumes the record already exists
	 * DESCRIPTION: string <b>REDCap::getSurveyReturnCode</b> ( string <b>$record</b>, string <b>$instrument</b> [, int <b>$event_id</b> ] )
	 * DESCRIPTION_TEXT: Obtains the return code for a specific record on a specific survey instrument (and for a specific event, if a longitudinal project), in which the "Save & Return Later" feature is enabled for the survey. This method assumes the record already exists.
	 * PARAM: record - The name of the record/response to which the survey return code belongs.
	 * PARAM: instrument - The name of the data collection instrument (i.e., the unique name, not the instrument label) to which the survey corresponds. This corresponds to the value of Column B in the Data Dictionary.
	 * PARAM: event_id - (longitudinal projects only) The event ID number that corresponds to a defined event in a longitudinal project. For classic projects, the event_id is not explicitly required, and thus it will be supplied automatically since there will only ever be one event_id for the project.
	 * RETURN: Returns the return code (alphanumeric string of text) for this record-instrument[-event], else it returns NULL if the return code was not found (i.e., if any parameters are incorrect) or if the "Save & Return Later" feature has not been enabled for the survey.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 5.11.0
	 * EXAMPLE: This example illustrates how to obtain the survey return code for a specific record for a specific survey instrument in a classic (non-longitudinal) project.
<pre>
// We have our record name and instrument name
$record = '101';
$instrument = 'participant_info_survey';

// Get the survey return code for this record-instrument
$return_code = REDCap::getSurveyReturnCode($record, $instrument);
</pre>
	 * EXAMPLE: This example demonstrates how to obtain the survey return code for a specific record-survey-event longitudinal project.
<pre>
// We have our record name, instrument name, and event_id
$record = '101';
$instrument = 'participant_info_survey';
$event_id = 339;

// Get the survey return code for this record-instrument-event
$return_code = REDCap::getSurveyReturnCode($record, $instrument, $event_id);
</pre>
	 */
	public static function getSurveyReturnCode($record='', $instrument='', $event_id='')
	{
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Return code
		return Survey::getSurveyReturnCode($record, $instrument, $event_id);
	}
	
	
	/**
	 * SUMMARY: Returns the content of a PDF file of one data collection instrument or all instruments in a project, in which the instruments can be 1) blank (no data), 2) contain data from a single record, or 3) contain data from all records in the project.
	 * DESCRIPTION: string <b>REDCap::getPDF</b> ( [ string <b>$record</b> = NULL [, string <b>$instrument</b> = NULL [, int <b>$event_id</b> = NULL [, bool <b>$all_records</b> = FALSE ]]]] )
	 * DESCRIPTION_TEXT: Returns a PDF file of one data collection instrument or all instruments in a project, in which the instruments can be 1) blank (no data), 2) contain data from a single record (from either one event or all events, if longitudinal), or 3) contain data from all records in the project.
	 * PARAM: record - The name of an existing record in the project. If record=NULL, then the method will return a blank PDF (containing no data) of one or all instruments.
	 * PARAM: instrument - The unique name of the data collection instrument (not the instrument label), which corresponds to the value of Column B in the Data Dictionary. If instrument=NULL, then all instruments in the project will be included in the PDF.
	 * PARAM: event_id - (longitudinal projects only) The event ID number that corresponds to a defined event in a longitudinal project. For classic projects, the event_id is not explicitly required, and thus it will be supplied automatically since there will only ever be one event_id for the project. If event_id=NULL for a longitudinal project and also record=NULL, then it will return data for all events for the given record.
	 * PARAM: all_records - Set to TRUE to return a PDF of all instruments with data from all records (and all events, if longitudinal). Note: If this parameter is set to TRUE, the parameters record, instrument, and event_id will be ignored. If set to FALSE, then the method will behave according to the first three parameters provided.
	 * RETURN: Returns the content of a PDF file, which can then be 1) stored as a file, 2) displayed inline on a webpage, or 3) downloaded as a file by a user's web browser.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 6.4.0
	 * EXAMPLE: This example illustrates how to obtain a blank PDF file of all instruments in project and save it as a file on the web server.
<pre>
// Get the content of the blank PDF of all instruments
$pdf_content = REDCap::getPDF();

// Save the PDF to a local web server directory
file_put_contents("/var/app001/my_pdfs/blank.pdf", $pdf_content);
</pre>
	 * EXAMPLE: This example illustrates how to obtain a PDF of one instrument for one record in a classic (non-longitudinal) project, and then display it as an inline PDF in a user's web browser.
<pre>
// We have our record name and instrument name
$record = '101';
$instrument = 'participant_info';

// Get the content of the PDF for one record and one instrument
$pdf_content = REDCap::getPDF($record, $instrument);

// Set PHP headers to display the PDF inline in the web browser
header('Content-type: application/pdf');
header('Content-disposition: inline; filename="redcap_instrument.pdf"');

// Output the PDF content
print $pdf_content;
</pre>
	 * EXAMPLE: This example illustrates how to obtain a PDF of one instrument for one event for one record in a longitudinal project, and then have the PDF download as a file in a user's web browser.
<pre>
// We have our record name, instrument name, and event_id
$record = '101';
$instrument = 'participant_info';
$event_id = 339;

// Get the content of the PDF for one record for one event for one instrument
$pdf_content = REDCap::getPDF($record, $instrument, $event_id);

// Set PHP headers to output the PDF to be downloaded as a file in the web browser
header('Content-type: application/pdf');
header('Content-disposition: attachment; filename="redcap_instrument.pdf"');

// Output the PDF content
print $pdf_content;
</pre>
	 * EXAMPLE: This example illustrates how to obtain a PDF file of all instruments and all records in project and save it as a file on the web server.
<pre>
// Get the content of the PDF of all instruments and all records
$pdf_content = REDCap::getPDF(null, null, null, true);

// Save the PDF to a local web server directory
file_put_contents("C:\\my_pdfs\\all_records.pdf", $pdf_content);
</pre>
	 */
	public static function getPDF($record=null, $instrument=null, $event_id=null, $all_records=false)
	{
		global $longitudinal, $Proj;
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// If a longitudinal project and no event_id is provided, then manually set to null
		if ($longitudinal && $record != null && $event_id != null && !isset($Proj->eventInfo[$event_id])) {
			exit("ERROR: Event ID \"$event_id\" is not a valid event_id for this project!");
		// If a non-longitudinal project, then set event_id automatically 
		} elseif (!$longitudinal) {
			$event_id = $Proj->firstEventId;
		}
		// If instrument is not null and does not exist, then return error
		if ($instrument != null && !isset($Proj->forms[$instrument])) {
			exit("ERROR: \"$instrument\" is not a valid unique instrument name for this project!");
		}
		// If record is not null and does not exist, then return error
		if ($record != null && !recordExists($record)) {
			exit("ERROR: \"$record\" is not an existing record in this project!");
		}
		// Capture original $_GET params since we're manipulating them here in order to use the existing PDF script
		$get_orig = $_GET;
		// Set event_id
		if (is_numeric($event_id)) {
			$_GET['event_id'] = $event_id;
		}
		// Output PDF of all forms (ALL records)
		if ($all_records) {
			$_GET['allrecords'] = '1';
		}
		// Output PDF of single form (blank)
		elseif ($instrument != null && $record == null) {
			$_GET['page'] = $instrument;
		}
		// Output PDF of single form (single record's data)
		elseif ($instrument != null && $record != null) {
			$_GET['id'] = $record;
			$_GET['page'] = $instrument;
		}
		// Output PDF of all forms (blank)
		elseif ($instrument == null && $record == null) {
			$_GET['all'] = '1';
		}
		// Output PDF of all forms (single record's data)
		elseif ($instrument == null && $record != null) {
			$_GET['id'] = $record;
		}
		// Capture PDF output using output buffer
		ob_start();
		// Since we're including a file from INSIDE a method, the global variables used in that file will not be defined,
		// so we need to loop through ALL global variables to make them local variables in this scope.
		foreach ($GLOBALS as $this_var=>&$this_val) {
			// Ignore super globals
			if ($this_var == 'GLOBALS' || substr($this_var, 0, 1) == '_') continue;
			// Set as local var
			$$this_var = $this_val;
		}
		// Output PDF to buffer
		include APP_PATH_DOCROOT . "PDF/index.php";
		// Reset $_GET params
		$_GET = $get_orig;
		// Obtain PDF content from buffer and return it
		return ob_get_clean();
	}
	
	
	/**
	 * SUMMARY: Returns the event_id associated with an event in a longitudinal project when given its associated unique event name
	 * DESCRIPTION: int <b>REDCap::getEventIdFromUniqueEvent</b> ( string <b>$unique_event_name</b>  )
	 * DESCRIPTION_TEXT: Returns the event_id associated with an event in a longitudinal project when given its associated unique event name.
	 * PARAM: unique_event_name - The unique name of the event, as provided on the project's Define My Events page.
	 * RETURN: Returns the event_id number of the event. Returns FALSE if project is not longitidinal or if the unique event name is not valid.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 6.4.0
	 * EXAMPLE: This example shows how to obtain the event_id for a given event using the unique event name, and then use the event_id to obtain a survey Participant List.
<pre>
// Check if project is longitdudinal first
if (!REDCap::isLongitudinal()) exit("Cannot get event_id because this project is not longitudinal.");

// We have the unique event name and a unique instrument name for a survey
$unique_event = 'screening_arm_1';
$instrument = 'enrollment_survey';

// Get the event_id from the the unique event name
$event_id = REDCap::getEventIdFromUniqueEvent($unique_event);

// Now use the event_id and instrument name to fetch the survey's Participant List in CSV format
$participant_list_csv = REDCap::getParticipantList($instrument, $event_id);
</pre>
	 */
	public static function getEventIdFromUniqueEvent($unique_event_name=null)
	{
		global $Proj;
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Make sure project is longitudinal, else return FALSE
		if (!self::isLongitudinal()) return false;
		// If $event_id is not valid, then return FALSE
		if ($unique_event_name == null) return false;
		$event_id = $Proj->getEventIdUsingUniqueEventName($unique_event_name);
		// Get and return event_id
		return (!is_numeric($event_id) ? false : $event_id);
	}
	
	
	/**
	 * SUMMARY: Returns a list of the export/import-specific version of field names for all fields (or for one field, if desired) in the current project
	 * DESCRIPTION: array <b>REDCap::getExportFieldNames</b> ( [ string <b>$field_name</b> = NULL ]  )
	 * DESCRIPTION_TEXT: Returns a list of the export/import-specific version of field names for all fields (or for one field) in the current project. This is mostly used for checkbox fields because during data exports and data imports, checkbox fields have a different variable name used than the exact one defined for them in the Online Designer and Data Dictionary, in which *each checkbox option* gets represented as its own export field name in the following format: field_name + triple underscore + converted coded value for the choice. For non-checkbox fields, the export field name will be exactly the same as the original field name. Note: The following field types will be automatically removed from the list returned by this method since they cannot be utilized during the data import process: "calc", "file", and "descriptive".
	 * PARAM: field_name - A field's variable name. By default, NULL is used. If field_name is provided, then it will return an array of only the export field name(s) for that field, but if the field name is invalid, it will return FALSE.
	 * RETURN: By default, returns an array of the export-specific version of field names for all fields in the project. If the field_name parameter is provided, then it will return an array of the export field names for just that field. In the array returned, the array keys will be the original field name (variable). For non-checkbox fields, the corresponding value for each array element will also be the original field name (i.e., the key and value will be the same). But for checkbox fields, the corresponding array value will itself be a sub-array of all choices for the checkbox, in which each key of the sub-array is the raw coded value of the choice with its associated value being the export field name for that choice.
	 * RESTRICTIONS: This method can ONLY be used in a project context (i.e. when "pid" parameter is in the query string of the plugin URL) or else a fatal error is produced.
	 * VERSION: 6.4.0
	 * EXAMPLE: This example shows how to obtain all the export field names for all fields in the project.
<pre>
// Get an array of all the export field names for all fields in the project
$all_export_field_names = REDCap::getExportFieldNames();
</pre>
	 * EXAMPLE: This example shows how to obtain all the export field names for a checkbox field named "medications_checkbox".
<pre>
// Set the variable name of our checkbox field
$checkbox_field = "medications_checkbox";

// Get an array of all the export field names for our checkbox field
$medications_export_field_names = REDCap::getExportFieldNames($checkbox_field);
</pre>
	 */
	public static function getExportFieldNames($field_name=null)
	{
		global $Proj;
		// Make sure we are in the Project context
		self::checkProjectContext(__METHOD__);
		// Get fields to start with
		if ($field_name == null) {
			// Get all fields
			$fields = array_keys($Proj->metadata);
		} elseif (isset($Proj->metadata[$field_name])) {
			// Set array of just this one field
			$fields = array($field_name);
		} else {
			// Error
			return false;
		}
		// Remove any fields of the following types: "calc", "file", and "descriptive"
		$invalidExportFieldTypes = array("calc", "file", "descriptive");
		// Put all export fields in array to return
		$export_fields = array();
		// Loop through all fields
		foreach ($fields as $this_field)
		{
			// Get field type
			$this_field_type = $Proj->metadata[$this_field]['element_type'];
			// If a checkbox field, then loop through choices to render pseudo field names for each choice
			if ($this_field_type == 'checkbox') 
			{
				foreach (array_keys(parseEnum($Proj->metadata[$this_field]['element_enum'])) as $this_value) {
					// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
					$export_fields[$this_field][$this_value] = Project::getExtendedCheckboxFieldname($this_field, $this_value);
				}
			} elseif (!in_array($this_field_type, $invalidExportFieldTypes)) {
				// Add to array if not an invalid export field type
				$export_fields[$this_field] = $this_field;
			}
		}
		// Return export fields array
		return $export_fields;
	}
	
}
