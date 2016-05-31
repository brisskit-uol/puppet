<?php

class BK_Utils {

  static function audit($message, $audit=false) {
//    return;
	if ($audit) {
    	$file = '/tmp/bk_audit.log';
	}
	else {
    	$file = '/tmp/bk_debug.log';
	}
    $ts  = date('Y-m-d H:i:s');
    file_put_contents($file, $ts.' '.$message."\n", FILE_APPEND);
  }


  /**
   *
   * Displays a status in CMS-independent way
   * Statuses are displayed as Javascript popups except for 'no-popup'
   *
   * For CiviCRM 4.6, Valid values for type are 'no-popup', 'info', 'error', 'success', 'alert'
   *
   */
  static function set_status($message, $type='no-popup', $title='') {
      if ($type == '') {
        $type = 'no-popup';
      }
      // CRM_Core_Session::setStatus(ts($message), $title, $type);
  }

#utility method which determines the custom fields in parameters ($params) and populates them with human readable keys ($fields) for a particular 
#group ($group) of custom fields in civicrm
static function populate_custom_fields($fields,&$params,$group) {
	require_once("CRM/Core/BAO/CustomField.php");
	$settings = array();
	global $custom_fields;
	#go through provided field definitions and obtain the custom ID (e.g. custom_5) => create hash of custom ID => provided label
	#e.g. custom_6 => 'brisskit_id'
	foreach($fields as $key => $value) {
		$settings["custom_".CRM_Core_BAO_CustomField::getCustomFieldID( $value, $group )]=$key;
	}

	#loop through each provided (usually POST) parameter looking for 'custom' fields
	#if they are custom insert a new value with the provided label (e.g. custom_5 also represented by brisskit_id)
	#also store the custom_ parameter with the label in the custom_fields global array so it can be used by set_custom_field
	foreach($params as $key=>$value) {
		if (substr($key,0,6)=="custom") {
			$data = CRM_Core_BAO_CustomField::getKeyID($key);

			if (isset($settings["custom_".$data])) {
				$label = $settings["custom_".$data];
				$params[$label]=$value;
				$custom_fields[$label]=$key;
			}
		}
	}	
}

#utility method to get contact along with custom field values
static function get_contact_with_custom_values($contact_id) {
	require_once "api/v3/Contact.php";
	require_once "api/v3/utils.php";
	require_once "api/v3/CustomValue.php";

	
	#get contact details
	$contact_complete = civicrm_api3_contact_get(array('contact_id' => $contact_id));
	$contact_vals = $contact_complete['values'];
	$contact = array_shift($contact_vals);
	
	#have to get custom values separately by using the custom_value api
	$params = array('entity_table'=>'Contact', 'entity_id'=>$contact['contact_id'],'version'=>'3');
	$cust_vals = civicrm_api3_custom_value_get($params);
	
	#if no custom values then return the contact as is
	if (civicrm_error($cust_vals)) {
		if (preg_match('/^No values found for the specified entity ID/',$cust_vals['error_message'])) {
			return $contact;
		}
		else {
			throw new Exception("Unknown Error:" . print_r($cust_vals, TRUE));
		}
	}
	
	#if there are custom values insert these into the contact as 'custom_N' parameters to be dealt with later
	foreach($cust_vals['values'] as $id => $record) {
		if (isset($record['latest'])) {
			$contact["custom_".$id."_1"]=$record['latest'];
		}
	}
	self::populate_custom_fields(self::permission_fields(),$contact,"Permission");
	self::populate_custom_fields(self::status_fields(),$contact,"Participant Status");
	
	#return the contact
	return $contact;
}

#utility method to set a custom field in parameters using the global variable (e.g. internally substitutes 'custom_2_1' for 'brisskit_id'
static function set_custom_field($label, $value, &$params) {
	global $custom_fields;

  if (isset($custom_fields[$label])) {
    $field_name = $custom_fields[$label]; // e.g. brisskit_id;
	  $params[$field_name] = $value;
  }
  else {
    BK_Utils::set_status("$label is not a valid custom field in " . __FILE__ . " " . __FUNCTION__);
  }
}

#utility method containing human readable keys and names for custom  'permission' fields in the database
static function permission_fields() {
	$settings = array(
			"permission_given" => "Permission to contact",
		     "brisskit_id" => "BRISSkit ID",
			"date_given" => "Date Permission Given",
	);
	return $settings;
}

#utility method containing human readable keys and names for custom  'status' fields in the database
static function status_fields() {
	$settings = array(
			"status" => "Current status",
			"study" => "Initial study",
			"status_log" => "Status log",
	);
	return $settings;
}

#utility method to obtain the contact associated with an activity
static function get_case_contact_with_custom_values($case_id) {
	require_once "api/v3/Case.php";
	require_once "api/v3/utils.php";
	require_once "api/v3/CustomValue.php";
	
	#get case associated with activity using the case_id
	$case_complete = civicrm_api3_case_get(array('case_id' => $case_id));
	$case_vals = $case_complete['values'];
	$first_case = array_shift($case_vals);
	
	#only get a trimmed down contact without the custom values
	$contact = array_shift($case_contacts);
	###axa20151117 set contact_id on the contact using the first id from the case_get() call.
  $contact['contact_id']=array_shift($first_case['contact_id']);

	#have to get custom values separately
	$params = array('entity_table'=>'Contact', 'entity_id'=>$contact['contact_id'],'version'=>'3');
	$cust_vals = civicrm_api3_custom_value_get($params);
	
	if (civicrm_error($cust_vals)) {
		if (preg_match('/^No values found for the specified entity ID/',$cust_vals['error_message'])) {
			return $contact;
		}
		else {
			throw new Exception("Unknown Error:" . print_r($cust_vals, TRUE));
		}
	}
	
	#if there are custom values insert these into the contact as 'custom_N' parameters to be dealt with later
	foreach($cust_vals['values'] as $id => $record) {
		if (isset($record['latest'])) {
			$contact["custom_".$id."_1"]=$record['latest'];
		}
	}
	self::populate_custom_fields(self::permission_fields(),$contact,"Permission");
	return $contact;
}


#utility method to create a contact given names and DOB
static function create_contact($forename, $surname, $dob) {
	require_once "api/v3/Contact.php";
	require_once "api/v3/utils.php";
	$st = array('Individual');
	$params = array(
		'first_name' => $forename,
		'last_name' => $surname,
		'birth_date' => $dob,
		'contact_type' => 'Individual',
	);
	$contact_json = civicrm_api3_contact_create($params);
	return array_shift($contact_json['values']);
}

#utility method to get a contact given an ID
static function get_contact($contact_id) {
	require_once "api/v3/Contact.php";
	require_once "api/v3/CustomValue.php";
	require_once "api/v3/utils.php";
	$params = array('contact_id'=>$contact_id);
	$contact_vals = civicrm_api3_contact_get($params);
	$contact = array_shift($contact_vals['values']);
	
	$params = array('entity_table'=>'Contact', 'entity_id'=>$contact['contact_id'],'version'=>'3');
	$cust_vals = civicrm_api3_custom_value_get($params);
	
	if (civicrm_error($cust_vals)) {
		if (preg_match('/^No values found for the specified entity ID/',$cust_vals['error_message'])) {
			return $contact;
		}
		else {
			throw new Exception("Unknown Error:" . print_r($cust_vals, TRUE));
		}
	}
	
	#if there are custom values insert these into the contact as 'custom_N' parameters to be dealt with later
	foreach($cust_vals['values'] as $id => $record) {
		if (isset($record['latest'])) {
			$contact["custom_".$id."_1"]=$record['latest'];
		}
	}
	self::populate_custom_fields(self::permission_fields(),$contact,"Permission");

	
	return $contact;
}

#utility method to get an activity given an ID
static function get_activity($act_id) {
	require_once "api/v3/Activity.php";
	require_once "api/v3/CustomValue.php";
	require_once "api/v3/utils.php";
	$params = array('activity_id'=>$act_id);
	$act_vals = civicrm_api3_activity_get($params);
	$act = array_shift($act_vals['values']);

	$params = array('entity_table'=>'Activity', 'entity_id'=>$act_id,'version'=>'3');
	$cust_vals = civicrm_api3_custom_value_get($params);
	
	if (civicrm_error($cust_vals)) {
		if (preg_match('/^No values found for the specified entity ID/',$cust_vals['error_message'])) {
			return $act;
		}
		else {
			throw new Exception("Unknown Error:" . print_r($cust_vals, TRUE));
		}
	}
	
	#if there are custom values insert these into the contact as 'custom_N' parameters to be dealt with later
	foreach($cust_vals['values'] as $id => $record) {
		if (isset($record['latest'])) {
			$act["custom_".$id."_1"]=$record['latest'];
		}
	}
	self::populate_custom_fields(self::workflow_fields(),$act,"Workflow");
	
	return $act;
}

#utility method to update a contact given an array of contact info
static function update_contact($contact) {
	require_once "CRM/Contact/BAO/Contact.php";
	$contact_json = civicrm_api3_contact_create($contact);
	return array_shift($contact_json['values']);
}

#utility method to update a contact given an array of contact info
static function update_activity($activity) {
	require_once "CRM/Activity/BAO/Activity.php";
	$stat_id=null;
	
	if (isset($activity['status'])) {
		#get status ID for provided status
        	$stat_id = self::get_option_group_value('activity_status',$activity['status']);
		$activity['status_id']=$stat_id;
	}
	
	$contact_json = civicrm_api3_activity_create($activity);
	return array_shift($contact_json['values']);
}

#utility method to delete a contact given their ID
static function delete_contact($contact_id) {
	require_once "CRM/Contact/BAO/Contact.php";
	CRM_Contact_BAO_Contact::delete($contact_id);
}

#utility method to delete all cases associated with a contact given their ID
static function delete_contact_cases($contactID) {
require_once "CRM/Case/BAO/Case.php";
	require_once "api/v3/utils.php";
	require_once "api/v3/Case.php";

	#get case types associated with contact
	$cases = CRM_Case_BAO_Case::getContactCases($contactID);
	
	#loop through and return if already added
	foreach($cases as $key => $case) {
		CRM_Case_BAO_Case::deleteCase($case['case_id']);
	}
}

#utility method to obtain date in advance of today given a DateInterval() specific pattern
static function get_date_in_advance($interval) {
	$date = new DateTime();
	$date->add(new DateInterval($interval));
	return $date->format('Y-m-dTh:i:s');
}

#add the specified activity to a case with a specific status and subject
static function add_activity_to_case($params) {
	
	#split out parameters
	$case_id = $params['case_id'];
	$activity_type = $params['activity_type'];
	$subject = $params['subject'];
	$status = $params['status'];
	$creator_id = isset($params['creator_id']) ? $params['creator_id'] : 1;
	
	$details = "";
	if (isset($params['details'])) {
		$details = $params['details'];
	}
	
	require_once "api/v3/OptionValue.php";
	require_once "api/v3/Activity.php";
	require_once "api/v3/utils.php";
	
	#get status ID for provided status
	$stat_id = self::get_option_group_value('activity_status',$status);
	
	#get ID of activity type to add (will not accept name in API)
	$at_id = self::get_option_group_value('activity_type',$activity_type);
	
	$params=array(
		'case_id' => $case_id,
		'activity_type_id' => $at_id,
		'source_contact_id' => $creator_id,
		'version' => 3,
		'subject' => $subject,
		'activity_status_id'=>$stat_id,
		'details' => $details
	);
	
	#create the activity
	civicrm_api3_activity_create($params);
	
	return true;
}

#utility method to get 'option value' value for specific group and value
static function get_option_group_value($group,$name) {
	require_once "api/v3/OptionValue.php";
	require_once "api/v3/utils.php";
	
	if (!$name || strlen($name)==0) {
		return null;
	}
	
	$ov_params = array(
		'name' => $name,
		'option_group_name' => $group
	);
	
	$ov = civicrm_api3_option_value_get($ov_params);
	
	if (!isset($ov['id'])) {
		throw new Exception("'".$group."' option name '$name' could not be found or multiple options with the same name");
	}
	
	$id = $ov['id'];
		
	$values = $ov['values'][$id];
	return $values['value'];
}

#utility method to get case type from case ID
static function get_case_type($case_id) {
	require_once "api/v3/Case.php";
	require_once "api/v3/utils.php";
	
	$cs_params = array(
		'case_id' => $case_id
	);
	
	$cs = civicrm_api3_case_get($cs_params);

	if (!isset($cs['id'])) {
		throw new Exception("case ID '$case_id' could not be found or multiple options with the same name");
	}
	
	$id = $cs['id'];
		
	$values = $cs['values'][$id];
	$ct_id = $values['case_type_id'];
	return self::get_option_group_name("case_type",$ct_id);
}

#utility method to get 'option value' name for specific group and value
static function get_option_group_name($group,$value) {
	require_once "api/v3/OptionValue.php";
	require_once "api/v3/utils.php";
	
	if (!$value || strlen($value)==0) {
		return null;
	}
	
	$ov_params = array(
		'value' => $value,
		'option_group_name' => $group
	);
	
	$ov = civicrm_api3_option_value_get($ov_params);
	
	if (!isset($ov['id'])) {
		throw new Exception("'".$group."' option value '$value' could not be found or multiple options with the same name");
	}
	
	$id = $ov['id'];
		
	$values = $ov['values'][$id];
	return $values['name'];
}

#utility method to count the activities in a case given case ID and activity type name
static function count_activities_in_case($case_id, $activity_type) {
	require_once "CRM/Case/BAO/Case.php";
	
	$at_id = self::get_option_group_value('activity_type',$activity_type);
	return CRM_Case_BAO_CASE::getCaseActivityCount($case_id,$at_id);
}

/**
     * Function to get the case type ID by name (modified from the CiviCRM Case DAO)
     *
     * @param int $caseId
     *
     * @return  case type
     * @access public
     * @static
     */
    static function getCaseTypeId( $caseTypeName )
    {
    	require_once "CRM/Core/DAO.php";
        $sql = "
    SELECT  ov.value
      FROM  civicrm_option_value  ov
INNER JOIN  civicrm_option_group og ON ov.option_group_id=og.id AND og.name='case_type'
     WHERE  ov.label = %1";

        $params = array( 1 => array( $caseTypeName, 'String' ) );
        
        return CRM_Core_DAO::singleValueQuery( $sql, $params );
    }

#utility method to determine name of activity type from ID
static function get_activity_type_name($activity_type_id) {
	if (!$activity_type_id) throw new Exception("No activity_type_id provided");
	require_once "api/v3/OptionValue.php";
	require_once "api/v3/Activity.php";
	require_once "api/v3/utils.php";
	
	$ov_params = array(
		'value' => $activity_type_id,
		'option_group' => 'activity_type'
	);
	
	$ov = civicrm_api3_option_value_get($ov_params);
	$ov_result = array_shift($ov['values']);
	return $ov_result['name'];
}

#utility method to determine age in years from date of birth
static function age($date_of_birth){
	 list($year, $month, $day) = explode("-",$date_of_birth);
	 $day = (int)$day;
	 $month = (int)$month;
	 $year = (int)$year;
	
	 $y = (int)gmstrftime("%Y");
	 $m = (int)gmstrftime("%m");
	 $d = (int)gmstrftime("%d");

	 $age = $y - $year;
	 if($m <= $month)
	 {
	 if($m == $month)
	 {
	 if($d < $day) $age = $age - 1;
	 }
	 else $age = $age - 1;
	 }
	 return $age;
}

#utility method to create civi custom group
static function create_civi_custom_group($params) {
  self::audit(print_r($params, TRUE));
  
	require_once 'api/v3/CustomGroup.php';
	require_once 'api/v3/utils.php';
	
	$params['version']='3';
	$cg = civicrm_api3_custom_group_get($params);
	
	if ($cg['is_error']) {
		throw new Exception("Error creating custom group:".$ci['error']);
	}
	if ($cg['count']==0) {
		$cg = civicrm_api3_custom_group_create($params);
	}
	
	return array_shift($cg['values']);
}

#utility method to create custom field in civi custom group (where $group is a custom group object)
static function create_civi_custom_field(&$cg, $params) {
  self::audit(print_r($cg, TRUE));
  self::audit(print_r($params, TRUE));
	require_once 'api/v3/CustomField.php';
	require_once 'api/v3/utils.php';
	
	$params['version']='3';
	$params['custom_group_id']=$cg['id'];
	
	#lookup using label (name doesn't work!)
	$cut_params = array("label"=>$params['label'], "custom_group_id"=>$cg['id']);
	
	$ci = civicrm_api3_custom_field_get($cut_params);
	if ($ci['is_error']) {
		throw new Exception("Error creating/getting custom field:".$ci['error']);
	}
	if ($ci['count']==0) {
		try {
			$ci = civicrm_api3_custom_field_create($params);
		}
		catch(Exception $ex) {
      BK_Utils::set_status("Unknown error creating custom field " . $params['label']);
			var_dump($ex);
		}
	}

	return array_shift($ci['values']);
}

#utility method to create option value in civi group (where $group is a group name)
static function create_civi_option_value($group,$params) {
  self::audit(print_r($group, TRUE));
  self::audit(print_r($params, TRUE));
	require_once "api/v3/OptionGroup.php";
	require_once "api/v3/OptionValue.php";
	require_once "api/v3/utils.php";
	
	$og_pars = array('name'=>$group);
	$og = civicrm_api3_option_group_get($og_pars);
	
	if (!isset($og['id'])) {
		throw new Exception("'".$group."' option group '$group' could not be found or multiple options with the same name");
	}
	$id = $og['id'];
	
	$params['option_group_id']=$id;
	
	$ov = civicrm_api3_option_value_get($params);
	if (isset($ov['count'])) {
		if ($ov['count']>0) {
			return array_shift($ov['values']);
		}
	}

  # Count is not set or is 0
  try {
	  $ov = civicrm_api3_option_value_create($params);
  }
	catch(Exception $ex) {  
    BK_Utils::set_status("Unknown error creating option value " . $params['label']);
    var_dump($ex);
  }
	
	if (!isset($ov['id'])) {
		throw new Exception("'".$group."' option value '$value' could not be found or multiple options with the same name");
	}
	
	$id = $ov['id'];
	return $ov['values'][$id];
}

#utility method to create option group in civi
static function create_civi_option_group($params) {
  self::audit(print_r($params, TRUE));
	require_once "api/v3/OptionGroup.php";
	require_once "api/v3/utils.php";
	$params['version']=3;
	$og = civicrm_api3_option_group_get($params);
	
	if (!isset($og['id'])) {
    try {
		  $og = civicrm_api3_option_group_create($params);
    }
	  catch(Exception $ex) {  
      BK_Utils::set_status("Unknown error creating option group " . $params['label']);
      var_dump($ex);
    }
	}
	return array_shift($og['values']); 
}

#utility method to set contact status to a particular text value or ID
static function set_contact_status($contact, $status, $log_text, $status_id=null) {
	if (!$status_id) {
		$status_id = self::get_option_group_value("current_status_12345",$status);
	}
	self::set_custom_field("status",$status_id,$contact);
	$slog = "";
	if (isset($contact['status_log'])) {
		$slog = $contact['status_log'];
	}
	$slog.=date(DATE_ATOM)." - $log_text\n";

	self::set_custom_field("status_log",$slog,$contact);
	$params = array(
		'entity_table'=>"Contact",
		'entity_id'=>$contact['contact_id'],
		'version'=>3
	);
	foreach ($contact as $field => $value) {
		if (substr($field,0,6)=="custom") {
			$cust_fld = explode("_",$field);
			$params[$cust_fld[0]."_".$cust_fld[1]]=$value;
		}
	}

	try {
	  $cust_vals = civicrm_api3_custom_value_create($params);
  }
	catch(Exception $ex) {
    BK_Utils::set_status("Unknown error creating custom value " . $params['label']);
		# var_dump($ex);
	}
}

#utility method to set workflow trigger field to true
static function set_activity_triggered($id) {
	require_once("CRM/Core/BAO/CustomField.php");
	require_once("api/v3/CustomValue.php");
	$params = array(
		'entity_table'=>"Activity",
		'entity_id'=>$id,
		'version'=>3
	);
	$trigger_field = "custom_".CRM_Core_BAO_CustomField::getCustomFieldID( "Workflow triggered", "Workflow" );
	$params[$trigger_field]="1";
	try {
	  $cust_vals = civicrm_api3_custom_value_create($params);
  }
	catch(Exception $ex) {
    BK_Utils::set_status("Unknown error creating custom value " . $params['label']);
		# var_dump($ex);
	}
}

static function set_contact_status_via_case($case_id, $status, $log_text) {
	$contact = get_case_contact_with_custom_values($case_id);
	self::populate_custom_fields(status_fields(),$contact,"Participant Status");
	return self::set_contact_status($contact, $status, $log_text);
}

#utility method containing human readable keys and names for custom  'workflow' fields in the database
static function workflow_fields() {
	$settings = array(
			"wf_triggered" => "Workflow triggered",
	);
	return $settings;
}

#utility method to determine if workflow has been triggered (use parameters passed from civicrm_pre DB hook)
static function is_triggered(&$params) {
  self::set_status(print_r($params, TRUE));
	self::populate_custom_fields(self::workflow_fields(),$params,"Workflow");
  self::set_status("ljlkjlkjlkj");
  self::set_status(print_r($params, TRUE));
	if (isset($params['wf_triggered']) && $params['wf_triggered']==1) {
    self::set_status("true");
		return true;
	}
  self::set_status("false");
	return false;
}

  #utility method to determine if ANY results returned in an array (from invoking modules) are true
  static function check_results($results) {
    foreach($results as $key=>$value) {
      if ($value==true) {
        return true;
      }
    }
    return false;
  }

  #utility method to update the template dir (e.g. the civicases directory)
  static function update_template_dir($custom_dir) {
    require_once "CRM/Core/DAO.php";
    #	CRM_Core_DAO::setFieldValue("CRM_Core_DAO_Setting", "customTemplateDir", "value", "s:".strlen($custom_dir)."\"$custom_dir\";", "name");
    $sql = "UPDATE  civicrm_setting s SET s.value = %1 WHERE s.name='customTemplateDir';";

    $params = array( 1 => array( "s:".strlen($custom_dir).":\"$custom_dir\";", 'String' ) );

    CRM_Core_DAO::executeQuery( $sql, $params );
  }

  /**
   * Helper function to load groups/roles/ACLs required for Brisskit ACL config from an XML file.
   *  We need to create these groups & roles at install time and remove them at uninstall time.
   *
   * @param $filename string
   *
   * @throws CRM_Extension_Exception_ParseException
   *
   */
  static function get_brisskit_xml ($filename) {
    BK_Utils::audit($filename);

    // Load the file, we expect it to live in our extensions' xml dir
    // $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '.' . DIRECTORY_SEPARATOR . '.' . DIRECTORY_SEPARATOR . "xml" . DIRECTORY_SEPARATOR . $filename;

    $file = implode(DIRECTORY_SEPARATOR, array(dirname(__FILE__), '..', '..', 'xml', $filename));
    BK_Utils::audit($file);
    try {
      list ($xml, $error) = CRM_Utils_XML::parseFile($file);
    }
    catch(Exception $ex) {
      BK_Utils::audit("Could not parse xml file $file");
      BK_Utils::set_status("Could not parse xml file $file");
      throw new Exception("");
    }

    //Complain if we can't load the file
    if ($xml === FALSE) {
      throw new CRM_Extension_Exception_ParseException("Failed to parse group XML: $file");
    }

    //We loaded the file, convert the XML into an array of groups
    foreach ($xml as $attr => $val) {
      $bk_acl_data[]= CRM_Utils_XML::xmlObjToArray($val);
    }
    return $bk_acl_data;
  }

  /*
   *
   * Set recruitment count. This is done on case creation.
   * Depending on requirements we may want something more sophisticated, for example
   * a patient may have to get past a certain activity (such as consent form received) to be properly considered
   * a participant.
   *
   */
  static function set_recruitment_count($contact_id, $count) {
    $params = array(
    'Brisskit_Recruitment_Count_1' => 1,
    'entity_id' => $contact_id,
    'id' => 1,
    'custom_Brisskit_Contact_Data:Brisskit Recruitment Count' => $count,
  );
    try{
      $result = civicrm_api3('CustomValue', 'create', $params);
    }
    catch (CiviCRM_API3_Exception $e) {
      // Handle error here.
      $errorMessage = $e->getMessage();
      $errorCode = $e->getErrorCode();
      $errorData = $e->getExtraParams();
      self::audit ('GVce:'.print_r(array(
        'error' => $errorMessage,
        'error_code' => $errorCode,
        'error_data' => $errorData,
      ), TRUE));
      return array(
        'error' => $errorMessage,
        'error_code' => $errorCode,
        'error_data' => $errorData,
      );
    }
    return $result;
  }


  /*
   *
   * get custom field recruitment count, example since syntax is not obvious, currently not called.
   *
   */
  static function get_recruitment_count ($contact_id) {
    $params = array(
      'bk_study_count_1' => 1,
      'entity_id' => $contact_id,
      'return.Brisskit_Contact_Data:Brisskit_Recruitment_Count_1' => 1,
    );
    try{
      $result = civicrm_api3('CustomValue', 'get', $params);
      }
    catch (CiviCRM_API3_Exception $e) {
      // Handle error here.
      $errorMessage = $e->getMessage();
      $errorCode = $e->getErrorCode();
      $errorData = $e->getExtraParams();
      self::audit ('GVe:'.print_r(array(
          'error' => $errorMessage,
          'error_code' => $errorCode,
          'error_data' => $errorData,
      ), TRUE));

      return array(
          'error' => $errorMessage,
          'error_code' => $errorCode,
          'error_data' => $errorData,
      );
    }
    return $result;
  }

  /*
   *
   * A very simple version of CRM_Core_DAO::singleValueQuery:
   * 1) Does not rely on DAO
   * 2) Returns a single field
   * 3) Takes a single integer or string as a parameter
   * 4) Must be guaranteed to return 0 or 1 row only
   * 5) sql must include a single ? placeholder
   *
   */
  static function single_value_query ($sql, $param) {
    $db = DB::connect(CIVICRM_DSN);
    if (PEAR::isError($db)) {
      die($db->getMessage());
    }
    $sth = $db->prepare($sql);
    $res =& $db->execute($sth, $param);

    if (PEAR::isError($res)) {
        die($res->getMessage());
    }

    BK_Utils::audit($sql);

    if ($row =& $res->fetchRow()) {
    BK_Utils::audit(print_r($row, TRUE));
      return $row[0];
    }
    else {
      return NULL;
    }
    $db->close();
  }
}
?>
