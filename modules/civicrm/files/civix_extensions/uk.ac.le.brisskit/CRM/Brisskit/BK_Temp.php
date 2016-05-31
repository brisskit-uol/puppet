<?php
/*
  * Class for various functions until we work out where they should be.
  * Generally they are incomplete
*/

require_once("BK_Constants.php");
require_once("BK_Utils.php");


class BK_Temp {

  /* 
    * Determine whether a user has access to a particular case 
    * TODO - placeholder for now
  */
  static function has_access_to_view_patients($user_group_id, $case_id, $patient_id) {
    return TRUE;
  }

 
  /* 

    When a study is created we also create the following:

      1)  A group for each professional type that can be involved with a study, for example .Nurse, study abc..  When the personnel are added to a 
          study they must be added to this group using the civi backend, possibly using a bespoke screen if it.s necessary to prevent access to certain ACL management functions.
      2)  A group for the patients that will be on the study, for example .Patient, study abc.
      3)  Links between role and professional group are created
      4)  Links between role and required acls are created.

    When a patient is added to a case type (case creation) we do the following
      1)  Add the patient (contact) to the patient group associated with the case type

    We will also need to address changes to studies and cases (modifications and deletions).

  */

  /* 
    We have a set of standard professional groups, each of which may have a role on a particular study.
    Here we insert new contact groups into the civi database so that when an admin user assigns a person a role on the study,
    the correct permissions are granted via CiviCRM ACLs.
  */
  // Note: The $case_type_id represents the study 

  static function create_contact_groups_for_study ($case_type_id) {
    $result = civicrm_api3('CaseType', 'get', array(
      'sequential' => 1,
      'id' => $case_type_id,
    ));

    if ($result['is_error']) {  
       throw new Exception("Error retrieving case $case_id " . __FILE__ . ' ' . __METHOD__ . "\n");
    }
    else {
      if ($result['count'] != 1) {
        throw new Exception("$case_id is not a valid case id in " . __FILE__ . ' ' . __METHOD__ . "\n");
      }
      else {
        $case_type_name = $result['values'][0]['name'];
      }
    }
/*
    $professional_groups = BK_Temp::get_professional_groups ();
    foreach ($professional_groups as $group_name) {
    }
*/

    $result = civicrm_api3('Group', 'create', array(
      'sequential' => 1,
      'title' => $case_type_name
    ));
  }

  # Currently unused 
  static function get_professional_groups () {
    return array(
      "Receptionist",
      "Study Member",
      "Practice Nurse",
      "Administrator",    // TODO - sort out a less generic name?
      "Power User",
      "Study Administrator",
      "Nurse");
  }


  // When a contact is added to a study, we want to automatically add the patient to the group associated with the study
  static function add_patient_to_group ($contact_id, $case_type_id) {
    $result = civicrm_api3('CaseType', 'get', array(
      'sequential' => 1,
      'id' => $case_type_id,
    ));

    if ($result['is_error']) {  
       throw new Exception("Error retrieving case $case_id " . __FILE__ . ' ' . __METHOD__ . "\n");
    }
    else {
      if ($result['count'] != 1) {
        throw new Exception("$case_id is not a valid case id in " . __FILE__ . ' ' . __METHOD__ . "\n");
      }
      else {
        $case_type_name = $result['values'][0]['name'];
      }
    }

    
    $result = civicrm_api3('Group', 'get', array(
      'sequential' => 1,
      'title' => $case_type_name,
    ));

    if ($result['is_error']) {  
       throw new Exception("Error retrieving group for $case_type_name" . __FILE__ . ' ' . __METHOD__ . "\n");
    }
    else {
      if ($result['count'] != 1) {
        throw new Exception("$case_type_name is not a valid group title in " . __FILE__ . ' ' . __METHOD__ . "\n");
      }
      else {
        $group_id = $result['values'][0]['id'];
      }
    }

    $result = civicrm_api3('GroupContact', 'create', array(
      'sequential' => 1,
      'group_id' => $group_id,
      'contact_id' => $contact_id,
      'status' => 'Added'
    ));
  }

  static function remove_patient_from_group ($contact_id, $case_type_id) {
    $result = civicrm_api3('CaseType', 'get', array(
      'sequential' => 1,
      'id' => $case_type_id,
    ));

    if ($result['is_error']) {  
       throw new Exception("Error retrieving case $case_id " . __FILE__ . ' ' . __METHOD__ . "\n");
    }
    else {
      if ($result['count'] != 1) {
        throw new Exception("$case_id is not a valid case id in " . __FILE__ . ' ' . __METHOD__ . "\n");
      }
      else {
        $case_type_name = $result['values'][0]['name'];
      }
    }

    
    $result = civicrm_api3('Group', 'get', array(
      'sequential' => 1,
      'title' => $case_type_name,
    ));

    if ($result['is_error']) {  
       throw new Exception("Error retrieving group for $case_type_name" . __FILE__ . ' ' . __METHOD__ . "\n");
    }
    else {
      if ($result['count'] != 1) {
        throw new Exception("$case_type_name is not a valid group title in " . __FILE__ . ' ' . __METHOD__ . "\n");
      }
      else {
        $group_id = $result['values'][0]['id'];
      }
    }

    $result = civicrm_api3('GroupContact', 'delete', array(
      'group_id' => $group_id,
      'contact_id' => $contact_id
    ));
  }
  

  /* 
   * When a study is created in CiviStudy (a Case) we need to create an associated study in CiviRecruitment (a Case Type)
   * Boilerplate info is created - admin users can pad out using the civi backend
   * 
   */
  static function create_study_case_type_in_recruitment ( &$objectRef ) {
    //
    // The objectRef belongs to the Case we are creating.
    //
    // The study has 2 main records
    // 1) A case (alredy created, immediately prior to arriving here)
    // 2) A case type (created by this function)
    //


    BK_Utils::set_status("Creating study case type for recruitment (Case Type)");
    BK_Utils::set_status(print_r($objectRef, TRUE));

    $subject = $objectRef->subject;
    $case_type_id = $objectRef->case_type_id;

    // The name for the case type must be unique
    // It is derived from the subject of the study case: non-alphacharacters are stripped out, whitespace trimmed 
    // and whitespace converted to underscore
    // We prefix with study_ so we can identify visually

    $case_type_name = strtolower($subject);
    $case_type_name = trim(preg_replace('/\s+/', '_', $case_type_name));
    $case_type_name = preg_replace('/[^0-9a-z]/', '', $case_type_name);

    //
    // We retrieve the activities from the template attached to the parent Study Type
    //
    // The template is held in a Case Type so the users can maintain template activities via the standard CiviCRM case type screen
    //

    $description_prefix = BK_Constants::STUDY_TEMPLATE_PREFIX . $case_type_id . ' ';

    $activities_template = civicrm_api3('CaseType', 'get', array(
      'sequential' => 1,
      'description' => array('LIKE' => "$description_prefix%"),
    ));

    if ($activities_template['is_error']) {  
      BK_Utils::set_status("Result error");
			BK_Utils::audit("Error retrieving case type template for $case_type_id " . __FILE__ . ' ' . __METHOD__ . "\n");
      throw new Exception("Error retrieving case type template for $case_type_id " . __FILE__ . ' ' . __METHOD__ . "\n");
    }
    else {
      BK_Utils::set_status("Result no error");
    }
    BK_Utils::set_status("Retrieved case type template for $case_type_id");

    $definition = $activities_template['values'][0]['definition'];

    $result = civicrm_api3('CaseType', 'create', array(
      'sequential' => 1,
      'name' => 'study_' . $case_type_name,
      'title' => 'Study: ' . $subject,
      'weight' => 1,
      'definition' => $definition,
    ));

    BK_Utils::audit(print_r($result, TRUE));

    if ($result['is_error']) {  
      BK_Utils::set_status("Result error");
			BK_Utils::audit("Error creating case type $case_type_name " . __FILE__ . ' ' . __METHOD__ . "\n");
      throw new Exception("Error creating case type $case_type_name " . __FILE__ . ' ' . __METHOD__ . "\n");
    }
    else {
      BK_Utils::set_status("Result no error");
      BK_Utils::set_status("id is " . $result['values'][0]['id']);
      BK_Utils::set_status(print_r($result, TRUE));
      return $result['values'][0]['id'];
    }
    BK_Utils::set_status("Created study");
  }




	//  $new_case_type_id = BK_Temp::create_case_type_template( $objectRef);
// Create a template based on the definition, name and title from another
  static function create_case_type_template( $case_type_id ) {
    BK_Utils::audit("Creating study type template (case type)");
		BK_Utils::audit(__FUNCTION__ . $case_type_id);


    $result = civicrm_api3('CaseType', 'get', array(
      'sequential' => 1,
      'id' => $case_type_id,
    ));

		BK_Utils::audit(print_r($result, true));

    if ($result['is_error']) {
			BK_Utils::audit("Error retrieving case type $case_type_id " . __FILE__ . ' ' . __METHOD__ . "\n");
       throw new Exception("Error retrieving case type $case_type_id " . __FILE__ . ' ' . __METHOD__ . "\n");
    }
    else {
      if ($result['count'] != 1) {
				BK_Utils::audit("$case_type_id is not a valid case type id in " . __FILE__ . ' ' . __METHOD__ . "\n");
        throw new Exception("$case_type_id is not a valid case type id in " . __FILE__ . ' ' . __METHOD__ . "\n");
      }
      else {
				BK_Utils::audit("OK");
        $name = $result['values'][0]['name'];
    		$id = $result['values'][0]['id'];
    		$title = $result['values'][0]['title'];
    		$definition = $result['values'][0]['definition'];
      }
    }

    $new_name = $name . '_template';
    $new_title = $title . ' Template';

    BK_Utils::audit("Case type name is $case_type_name");

		$new_description = BK_Constants::STUDY_TEMPLATE_PREFIX;
		$new_description .= $id . ' ' . $title;

    $result = civicrm_api3('CaseType', 'create', array(
      'sequential' => 1,
      'name' => $new_name,
      'title' => $new_title,
      'description' => $new_description,
      'weight' => 1,
      'definition' => $definition,
    ));

    BK_Utils::audit(print_r($result, TRUE));

    if ($result['is_error']) {  
      BK_Utils::audit("Result error");
      throw new Exception("Error creating case type template $case_type_name " . __FILE__ . ' ' . __METHOD__ . "\n");
    }
    else {
      BK_Utils::audit("Result no error");
      BK_Utils::audit("id is " . $result['values'][0]['id']);
      BK_Utils::audit(print_r($result, TRUE));
      return $result['values'][0]['id'];
    }
    BK_Utils::audit("Created template ");
  }
}




#BK_Temp::map_case_type_to_component( 123, BK_Constants::CIVIRECRUITMENT);
#BK_Temp::map_case_type_to_component( 123, BK_Constants::CIVISTUDY);


#BK_Temp::map_case_type_to_component( 123, 'saasdasdadad');
