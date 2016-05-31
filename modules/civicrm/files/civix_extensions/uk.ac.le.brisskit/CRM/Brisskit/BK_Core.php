<?php 


include_once("BK_Constants.php");
include_once("BK_Utils.php");
include_once("BK_ID.php");

class BK_Core {

#given parameters($params) determines whether participant has been made available to be contacted
static function is_participant_available($params) {

	require_once "api/v3/utils.php";
	require_once "api/v3/Case.php";
	require_once "api/v3/Contact.php";
	
	#determine required activity type and status values (e.g. ids)
	$contact_check_activity = BK_Utils::get_option_group_value("activity_type", BK_Constants::ACTIVITY_CHECK_STATUS);
	$contact_complete_status = BK_Utils::get_option_group_value("activity_status", BK_Constants::ACT_STATUS_COMPLETED); 
	
	#check the current activity type is the 'Check participant status' activity
	if ($params['activity_type_id']==$contact_check_activity) {
		#check the current activity status is 'Completed'
		if ($params['status_id']==$contact_complete_status) {
			#then get contact associated with case
			$contact = BK_Utils::get_case_contact_with_custom_values($params['case_id']);
			#populate contact with known human readable custom fields
			self::populate_custom_fields(status_fields(),$contact,"Participant Status");
			
			$contact_available_status = BK_Utils::get_option_group_value("current_status_12345", BK_Constants::CONTACT_STATUS_AVAILABLE); 
			#result is true if the contact status is 'Available'
			if ($contact['status']==$contact_available_status) {
				return true;
			}
			#otherwise return false
			return false;
		}
	}
	#if this is another type of activity then return false
	else {
		return false;
	}
}

#determine if status has changed and add to the status log custom field if so
static function log_status_if_required($contact_id, $op, $prev_stat_id=null) {
	$contact = BK_Utils::get_contact_with_custom_values($contact_id);
	$latest_stat_id = isset($contact['status']) ? $contact['status'] : null;
	$latest_stat_name = BK_Utils::get_option_group_name("current_status_12345",$latest_stat_id);
	if ($op=="create") {
		BK_Utils::set_contact_status($contact, null,  "Status initially set to '".$latest_stat_name."'",$latest_stat_id);
	}
	else if ($op=="edit") {
 		if ($prev_stat_id != $latest_stat_id) {
			$status_name = get_option_group_name("current_status_12345",$contact['status']);
			BK_Utils::set_contact_status($contact, null,  "Status changed to '".$latest_stat_name."'", $latest_stat_id);
		}
	}
}

#determine if participant has replied positively in this activity
static function is_participant_reply_positive($params) {
	#determine required activity type value (e.g. id)
	$contact_replied_activity = BK_Utils::get_option_group_value("activity_type", BK_Constants::ACTIVITY_POSITIVE_REPLY);
	
	#check the current activity type is the 'Positive reply' activity
	if ($params['activity_type_id']==$contact_replied_activity) {
		#determine required activity status value (e.g. id)
		$contact_complete_status = BK_Utils::get_option_group_value("activity_status", BK_Constants::ACT_STATUS_COMPLETED); 

		#result is true if current activity status is 'Completed'
		if ($params['status_id']==$contact_complete_status) {
			return true;
		}
	}
	#otherwise return false
	else {
		return false;
	}
}

#determine if consent has been given in this activity
static function is_consent_level_accepted($params) {
	#determine required activity status value (e.g. id)
	$act_accepted_status = BK_Utils::get_option_group_value("activity_status", BK_Constants::ACT_STATUS_ACCEPTED); 
	
	#result is only true if status is 'Accepted'
	if (isset($params['status_id'])) {
		if ($params['status_id']==$act_accepted_status) {
			return true;
		}
		else {
			return false;
		}
	}
}


#determines if permission was given to contact participant
# 3. The extension determines if permission has been set to yes (1) 
static function is_permission_given_to_contact(&$params) {
	BK_Utils::populate_custom_fields(BK_Utils::permission_fields(),$params,"Permission");

	if (isset($params['permission_given'])) {
		if ($params['permission_given']=="1") {
			# 4. If this is the case it checks if an ID was previously set. If it had return false
			if (isset($params['brisskit_id'])) {
				if (strlen($params['brisskit_id'])>0) return FALSE;
			}
			return TRUE;
		}
	}
	else {
		return FALSE;
	}
}


#creates 'Base' case for participant
static function add_participant_to_base_study($contactID) {
	return self::add_participant_to_study($contactID, BK_Constants::CASE_BASE_STUDY);
}

#create case for contact based on specified name
#but prevent creation of the same case type multiple times
static function add_participant_to_study($contactID, $studyName) {
	require_once "CRM/Case/BAO/Case.php";
	require_once "api/v3/utils.php";
	require_once "api/v3/Case.php";
	
	#get case types associated with contact
	$cases = CRM_Case_BAO_Case::getContactCases($contactID);
	
	#loop through and return false if already added
	foreach($cases as $key => $case) {
		if ($case['case_type']==$studyName) {
			error_log("Case $studyName already exists for contact");
			return false;
		}
	}
	
	#get case type ID from study name
	$caseTypeID=BK_Utils::getCaseTypeId($studyName);	
	$case_vars = array(
		"case_type_id"=>$caseTypeID, 
		"subject"=>"Added to ". $studyName . " study", 
		"status_id"=>1, 
		"contact_id"=>$contactID,
		"start_date"=>"2012-05-08", 
		"version" => 1,
		"creator_id"=>1
	);
	
	#create case for contact
	$study = civicrm_api3_case_create($case_vars);
	
	#return case information
	return $study['values']['id'];
}

#utility method to load XML for a particular case given its ID
static function load_case_xml($case_id) {
	require_once "CRM/Case/XMLProcessor.php";
	require_once "CRM/Case/XMLProcessor/Process.php";
	require_once "CRM/Case/BAO/Case.php";
	require_once "CRM/Core/Permission.php";
	
	#get case type for case
	$case_type = CRM_Case_BAO_Case::getCaseType($case_id);
	
	#load XML file for case type
	$xml = CRM_Case_XMLProcessor::retrieve($case_type);
	return $xml;
}

#add all activities in a specific ActivitySet to the specified case
static function add_activity_set_to_case($case_id, $activity_set, $creator_id) {
	require_once "CRM/Case/XMLProcessor.php";
	require_once "CRM/Case/XMLProcessor/Process.php";
	require_once "CRM/Case/BAO/Case.php";
	require_once "CRM/Core/Permission.php";
	
	#load XML file for case type
	$xml = self::load_case_xml($case_id);
	
	
	$reqd_aset = null;
	
	#process XML to obtain required ActivitySet
	foreach($xml->ActivitySets->children() as $as) {
		if ($as->name == $activity_set) {
			$reqd_aset = $as;
			continue;
		}
	}
	#if required activity set is not found then throw a wobbly	
	if (!$reqd_aset) {
		throw new Exception("ActivitySet $activity_set not found");
	}
	
	#get the contact associated with this case
	$contact = BK_Utils::get_case_contact_with_custom_values($case_id);
	
	$process = new CRM_Case_XMLProcessor_Process;
	$process->_isMultiClient=false;
	
	#add ActivityTypes in chosen ActivitySet to case
	$params = array('caseID'=>$case_id, 'creatorID'=>$creator_id, "activity_date_time" => get_date_in_advance(BK_Constants::LETTER_DATE_INTERVAL), "clientID"=>$creator_id);
	$process->processActivitySet($reqd_aset,$params);
	
	return true;
}


#determine if an activity type is already present in a case
static function is_activity_type_present($case_id, $activity_type,$reqd_status) {
	require_once "api/v3/Activity.php";
	$at_id = BK_Utils::get_option_group_value('activity_type',$activity_type);
	
	$count = self::count_activities_in_case($case_id,$activity_type);
	
	
	if ($count==0) return false;
		
	$params = array('activity_type_id'=>$at_id);
	$contact = BK_Utils::get_case_contact_with_custom_values($case_id);
	$acts = CRM_Case_BAO_Case::getCaseActivity($case_id,$params,$contact['contact_id']);
	foreach($acts as $id=>$act) {
		if (strlen(strpos($act['status'],$reqd_status))==0) {
			return true;
		}
	}
}

#determine if imported participant has an initial study (ie. whether it should be added or not)
static function is_participant_in_initial_study(&$params) {
 	$contactID = $params["contactID"];
  
	$init_study = array(
		"study" => "Initial study",
	);
	$import_fields = $params['fields'];
	$fields = array();
	foreach($import_fields as $ifield) {
		$fields[$ifield->_name]=$ifield->_value;
	}
	BK_Utils::populate_custom_fields(BK_Utils::status_fields(),$fields, "Participant Status");
	BK_Utils::populate_custom_fields(BK_Utils::permission_fields(),$fields, "Permission");
	
	if (isset($fields['study'])) {
		$study = $fields['study'];
		if (strlen($study)==0) {
			return false;
		}
		else {
			return true;
		}
	}
	else {
		return false;
	}
}

#add imported participant to initial study (case type) obtained using 'study' parameter
static function add_participant_to_initial_study($params) {
	$contactID = $params["contactID"];
	self::add_participant_to_study($contactID, $params['study']);
}

#pseudonomise individual using 'make_brisskit_id' method
# 5.3. The new ID is added to the participant (assuming it does not exist) and added to the participant along with a date permission given
static function pseudo_individual(&$params) {
	$bkid = BK_ID::make_brisskit_id();

	BK_Utils::set_custom_field('brisskit_id',$bkid, $params);
	$date = new DateTime();
	BK_Utils::set_custom_field('date_given',$date->format('Y-m-d'),$params);
	return $bkid;
}

static function case_allows_activity($case_id, $activity_name) {
	
        # Be defensive
        if ((empty($case_id))
        ||  (empty($activity_name))) { 
                return false;
        }

        #load XML file for case type
        $xml = self::load_case_xml($case_id);

        # Be defensive
        if (!is_object($xml)) {
                return false;
        }

	foreach($xml->ActivityTypes->children() as $at) {
		if ($at->name == $activity_name) {
			return true;
		}
	}
	return false;
}

static function is_added_to_duplicate_case($op, $post, $params) {
	require_once "api/v3/Case.php";
	if (!$op == "create") return; #return if not creating a new activity
	
	if (isset($params['activity_type_id'])) {
		$opencase_id = BK_Utils::get_option_group_value("activity_type", BK_Constants::ACTIVITY_OPEN_CASE);
		
		#is this an 'Open Case' activity?
		if ($opencase_id == $params['activity_type_id']) {
			$case_type_id = $post['case_type_id'];
			$case_type = BK_Utils::get_option_group_name("case_type",$case_type_id);

			$contactIDs = $params['target_contact_id'];
			
			#loop through target contacts associated with case
			foreach($contactIDs as $no => $contactID) {
				$same_cases = 0;
				#get case types associated with contact
				$cases = CRM_Case_BAO_Case::getContactCases($contactID);

				#loop through and add to counter if already added
				foreach($cases as $key => $case) {
					if ($case['case_type']==$case_type) {
						$same_cases++;
												}
				}
				
				#is there more than a single case added?
				if ($same_cases>1) {
					#delete the new case as this should not have been added
					$options = array("case_id"=>$params['case_id'],"version"=>3, "move_to_trash"=>1);
					civicrm_api3_case_delete($options);
					return $case_type; #return case type name for use in drupal_set_message
				}
			}
		}
	}
	#no problem return false
	return false;
}
}

/*

$params = array();
$contact_id = 1;
$op = 'asdasd';
$contactID = 22;
$studyName = 'asdas';
$case_id = 678;
$activity_set = 213;
$activity_type = 'hjkassd';
$reqd_status = 'asdd';
$activity_name = 'sadasd';
$post = 'sadads';
$creator_id = 132;


$path = '/var/local/brisskit/drupal/site/civicrm/sites/all/modules/civicrm';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
try {
#BK_Core::is_participant_available($params);
echo "1";
#BK_Core::log_status_if_required($contact_id, $op, $prev_stat_id=null);
echo "1";
#BK_Core::is_participant_reply_positive($params);
echo "1";
#BK_Core::is_consent_level_accepted($params);
echo "1";
#BK_Core::is_permission_given_to_contact($params);
echo "1";
#BK_Core::add_participant_to_base_study($contactID);
echo "1";
#BK_Core::add_participant_to_study($contactID, $studyName);
echo "1";
#BK_Core::load_case_xml($case_id);
echo "1";
#BK_Core::add_activity_set_to_case($case_id, $activity_set, $creator_id);
echo "1";
#BK_Core::is_activity_type_present($case_id, $activity_type,$reqd_status);
echo "1";
#BK_Core::is_participant_in_initial_study($params);
echo "1";
#BK_Core::add_participant_to_initial_study($params);
echo "1";
#BK_Core::pseudo_individual($params);
echo "1";
#BK_Core::case_allows_activity($case_id, $activity_name);
echo "1";
BK_Core::is_added_to_duplicate_case($op, $post, $params);
echo "1";

echo "Fin";
}
catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}
*/


?>
