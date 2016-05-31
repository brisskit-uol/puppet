<?php

require_once 'brisskit.civix.php';
require_once 'CRM/Brisskit/BK_Constants.php';
require_once 'CRM/Brisskit/BK_Utils.php';
require_once 'CRM/Brisskit/BK_Temp.php';
require_once 'CRM/Brisskit/BK_Component.php';

require_once 'our_hooks/brisskit_ts.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function brisskit_civicrm_config(&$config) {
  $config->customTranslateFunction = 'brisskit_ts';

  BK_Utils::audit("_brisskit_civix_civicrm_config");

  $our_hooks_dir = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'our_hooks/';
  $include_path = $our_hooks_dir . PATH_SEPARATOR . get_include_path( );
  set_include_path( $include_path );

  _brisskit_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * This loads the xml that provides routing for our recruitment 
 * (& optionally study) "components". Hook is called either by a 
 * menu/rebuild&reset=1 url call or at extension enable time  in 
 * response to a System flush API call.
 *
 * If the file does not exist, the (partial) install will succeed but a warning message displayed in the backend
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function brisskit_civicrm_xmlMenu(&$files) {
  _brisskit_civix_civicrm_xmlMenu($files);
  $files[] = dirname(__FILE__) . DIRECTORY_SEPARATOR . "xml" . DIRECTORY_SEPARATOR . "Case.xml";
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function brisskit_civicrm_install() {
  _brisskit_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function brisskit_civicrm_uninstall() {
  _brisskit_civix_civicrm_uninstall();

  if ($upgrader = _brisskit_civix_upgrader()) {
    return $upgrader->onUninstall();
  }
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function brisskit_civicrm_enable() {
  _brisskit_civix_civicrm_enable();
//  civicrm_initialize();
  try {
//    init_required_fields();

    BK_Utils::set_status("BRISSkit extension for CiviCRM was setup successfully");

  }
  catch(Exception $ex) {
    BK_Utils::set_status("An unexpected error occured during the BRISSkit extension setup: ".$ex->getMessage(), "error");
  }

}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function brisskit_civicrm_disable() {
  _brisskit_civix_civicrm_disable();
	CRM_Core_Session::setStatus(ts(''));
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function brisskit_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _brisskit_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function brisskit_civicrm_managed(&$entities) {
  _brisskit_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function brisskit_civicrm_caseTypes(&$caseTypes) {
  _brisskit_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function brisskit_civicrm_angularModules(&$angularModules) {
_brisskit_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function brisskit_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _brisskit_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Functions below this ship commented out. Uncomment as required.
 *

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
*/
function brisskit_civicrm_preProcess($formName, &$form) {

  BK_Utils::audit ('Preprocess: ' . $formName);
  BK_Utils::audit (print_r($form, TRUE));

}

function brisskit_civicrm_links( $op, $objectName, $objectId, &$links, &$mask, &$values ) {
  //http://br-civi-recruitment.cloudapp.net/civicrm/index.php?q=civicrm/recruitment

  BK_Utils::audit('@@@@@@@');
  BK_Utils::audit(print_r($links, TRUE));
  $current_q = $_REQUEST['q'];  // civicrm/recruitment
  $new_links = array();
  foreach ($links as $link) {
    if (isset($link['qs'])) {
      $old_qs = $link['qs'];
    }
    else {
      $old_qs = '';
    }
    $link['qs'] = $old_qs . '&bkref=' . $current_q;
    $new_links[] = $link;
  }
  
  $links = $new_links;
  
  return $new_links;
}


/* 
 *
 * Start of brisskit functionality proper
 *
 */ 


/**
 * Module containing core BRISSkit logic by implementing CiviCRM hook pre and post database writing.
 * within this logic it invokes BRISSkit specific hooks for particular stages of the study enrolment process
 * these include:
 *  
 *  - participant_available - once participant has passed the automatic/manual check that they are not deceased (ie. the Check participant is available activity is complete) and their status is Available
 *    - in this module a set of 'contact participant' activities are added including 'Positive reply received'
 *  
 *  - letter_response - once participant has responded positively to the letter (ie. the Positive reply received activity is complete)
 *    - in this module the participant stub is sent to the i2b2 web service
 *    - for the 2 consent modules (brisskit_tissue and brisskit_datacol) each creates a Consent to.. activity (provided the case implements that ActivityType) with status pending
 * 
 *  - consent_success - once participant has given 'Accepted' as response to particular Consent to.. activity
 *    - in the brisskit_tissue module this triggers the participant stub to be sent to the caTissue web service and the recording of this with a 'Data transfer' activity
 *    - in the brisskit_datacol module this triggers the creation of an 'Phone Call' activity to 'Organise data collection appointment with participant' 
 */

 include_once "CRM/Brisskit/BK_Core.php";

//function brisskit_civicrm_alterContent(  &$content, $context, $tplName, &$object ) {
function brisskit_civicrm_alterTemplateFile($formName, &$form, $context, &$tplName) {
  BK_Utils::audit ('TPL name: ' . $tplName); 

// get all assigned template vars
/*
  global $smarty;
$all_tpl_vars = $smarty->get_template_vars();

// take a look at them
BK_Utils::audit (print_r($all_tpl_vars, TRUE));
*/

   BK_Utils::audit (print_r($form, TRUE));
}

/**
 * #implement civi's civicrm_buildForm hook
 * #prevent users from adding participants to cases unless they have permission to contact participant
 */
function brisskit_civicrm_buildForm($formName, &$form) {
  BK_Utils::audit ('Form name: ' . $formName); 

/*
  if ($formName == 'CRM_Contact_Form_Contact') {
  }
*/

  //
  // On the add case form we have a dropdown of case types. Here we want to show only the pertinent type according to
  // whether we're in CiviCase or CiviRecruitment.
  //
  // Note that Civi forms are based on PEAR classes - here we're looking at the HTML_QuickForm_select class derived from
  // HTML_QuickForm_element
  //
  // Dropdown options are held as a simple array, all other attributes of the element stay the same.
  // 
  //
  if ($formName == 'CRM_Case_Form_Case') {
    if ( $form->elementExists( 'case_type_id' ) ) {
      $element =& $form->getElement('case_type_id');    // Return PEAR HTML_QuickForm_element object

      if ($element->getType() == 'select') {
        $options =& $element->_options;                 // Yuck!! But couldn't find method to return what we need.
        $new_options = array();                         // We will work on a copy then replace the options array when we're done
        foreach ($options as $option) {
          if ( preg_match("/ Template$/", $option['text'], $matches)) {
          }
          else if (BK_Component::is_study() && preg_match("/^Study:/", $option['text'], $matches) ) {
          }
          else if (BK_Component::is_recruitment() && !preg_match("/^Study:/", $option['text'], $matches) ) {
          }
          else {
            $new_options[] = $option;
          }
        }
        $element->_options = $new_options;
      }
    }
  }

/*
           [16] => HTML_QuickForm_select Object
                (
                    [_options] => Array
                        (
                            [0] => Array
                                (
                                    [text] => Clinical Trial
                                    [attr] => Array
                                        (
                                            [value] => 61
                                        )

                                )

                            [1] => Array
                                (
                                    [text] => Comparison studies Template
                                    [attr] => Array
                                        (
*/



  # 2. The brisskit extension checks that permission has been given to do this before the form has been generated
  if (!BK_Component::is_recruitment()) {
    BK_Utils::audit ('Component: ' . 'study'); 
    return;
  }

  BK_Utils::audit ('Component: ' . 'recruit'); 

  // The code from here is based on original drupal module
  if ($formName == 'CRM_Case_Form_Case') {
    if ($form->getAction() == CRM_Core_Action::ADD) {
    	if ($form->controller->_actionName) {
	    	$contact_id = $form->_currentlyViewedContactId;
	    	if (!$contact_id) return;
	        $contact = BK_Utils::get_contact_with_custom_values($contact_id);
	        
	        if (!isset($contact['permission_given']) || $contact['permission_given']==0) {
	        	BK_Utils::set_status("Sorry, a participant cannot be enrolled in any studies until 'Permission to contact participant' has been set to 'Yes'","error");
	        	drupal_goto("civicrm/contact/view",array("reset"=>1,"cid"=>$contact_id));
	        }
    	}
    }
  }
}


/**
 * implement civi's civicrm_pre db write hook
 * 
 * 1) If a contact is being added to a case, increment the contact's recruitment count by 1
 * 2) Run old drupal code, for CiviRecruitment only
 */
function brisskit_civicrm_pre($op, $objectName, $id, &$params) {
  BK_Utils::audit("brisskit_civicrm_pre: $op, $objectName, $id, " .  print_r($params, TRUE) );

  /*
    If a recruitment is being created update our read-only custom recruitment count field.
  */
	if ($objectName=='Case') {
    $case_id = $id;
    
    BK_Utils::audit("Checking is recuitment and creating");
    if (BK_Component::is_recruitment()) {
      if ($op==BK_Constants::ACTION_CREATE) {

        BK_Utils::audit("Is recruitment and creating - true");

        $contactId = $params['client_id'][0];
        $case_type_id = $params['case_type_id'];

        // Currently we just use the built-in caseCount function.  Future versions may check participant status/activities completed before
        // setting this count.
        BK_Utils::audit("Before setting recruitment count");
        $result = BK_Utils::set_recruitment_count ($contactId, CRM_Case_BAO_Case::caseCount($contactId, TRUE)+1);
        BK_Utils::audit("After setting recruitment count");
  
        BK_Temp::add_patient_to_group ($contactId, $case_type_id);
      }
      else if ($op==BK_Constants::ACTION_DELETE) {
        $contactId = BK_Utils::single_value_query("SELECT contact_id FROM civicrm_case_contact WHERE case_id = ?", $case_id);
        $case_type_id = BK_Utils::single_value_query("SELECT case_type_id FROM civicrm_case WHERE id = ?", $case_id);
        BK_Temp::remove_patient_from_group ($contactId, $case_type_id);
      }
    }
  }
	else if ($objectName=='CaseType') {
    if ($op == 'delete') {
			_case_type_delete($objectId);
    }
  }

  // The code from here is based on original drupal module
  BK_Utils::audit ("pre hook op $op name $objectName");
  BK_Utils::audit ('params:'.print_r($params, TRUE));
	global $prev_stat_id;
	
	#if only viewing or deleting don't do anything
	if ($op=="view" || $op=="delete") return;
	
	# check if object is Individual or GroupContact

	#2. When the participant is saved the brisskit extension is triggered
	if ($objectName=="GroupContact" || $objectName=='Individual') {
		#try/catch will produce a nice drupal style message if there is a problem
		try {
			#check whether contact has permission or not
			$permission = BK_Core::is_permission_given_to_contact($params);
			
			if ($permission) {
				# 5. If the permission flag has been set properly the individual is pseudonymised
				#if so then pseudonymise the individual
				$bkid = BK_Core::pseudo_individual($params);
				BK_Utils::set_status("Permission to contact the individual was given by GP/clinician - participant has now been pseudonymised (ID:".$bkid.")");
			}
			if ($op=="edit") {
				 ###axa20151207 fix wsod caused by calling get_contact_with_custom_values() without class prefix BK_Utils::
                                 $contact = BK_Utils::get_contact_with_custom_values($params['contact_id']);
				 $prev_stat_id = isset($contact['status']) ? $contact['status'] : null;
			}
			
		}
		catch(Exception $ex) {
			BK_Utils::set_status($ex->getMessage(),"error");	
		}
	}

  BK_Utils::audit("Checking is recuitment");
  BK_Utils::audit(print_r($params, TRUE));
  if (!BK_Component::is_recruitment()) {
    BK_Utils::audit("Not recruitment");
    return;
  }
  BK_Utils::audit("Is recruitment");

	
	global $triggered;
	if ($objectName=='Activity') {
		#try/catch will produce a nice drupal style message if there is a problem

    BK_Utils::set_status("Inserting activity");
    # BK_Utils::set_status(print_r($params, TRUE));
    

		try {
			#check if activity has already had workflow triggered
			if (BK_Utils::is_triggered($params)) return;
			
			#check if contact has been added to case type previously (result of 'Open Case' activity)
			$case_type = BK_Core::is_added_to_duplicate_case($op, $_POST, $params);
			
			if ($case_type) {
				BK_Utils::set_status("Sorry you can only add a contact to a study once. This contact has already been added to the '$case_type' Study","error");
				drupal_goto("civicrm/contact/view",array("reset"=>1,"cid"=>$params['target_contact_id']));
			}
			
			#check if participant available has just been set
			if (BK_Core::is_participant_available($params)) {
				#invoke the BRISSkit 'participant_available' hook
				$results = module_invoke_all("participant_available",$params,$id);
				$triggered = BK_Utils::check_results($results);
			}
			#if participant has just replied invoke the BRISSkit 'letter_response' hook
			if (BK_Core::is_participant_reply_positive($params)) {
				BK_Utils::set_status("Potential participant replied");
				$results = module_invoke_all("letter_response",$params);
				$triggered = BK_Utils::check_results($results);
			}
			
			#if consent was given for this Activity (ie. status is Accepted)
			if (BK_Core::is_consent_level_accepted($params)) {
				#check that the ActivityType is part of the case definition if not exit hook
				$activity_type = BK_Utils::get_activity_type_name($params['activity_type_id']);
                                #Tell the user whats happened
				if (!BK_Core::case_allows_activity($params['case_id'], $activity_type)) {
                                  BK_Utils::set_status("Case does not allow this activity");
                                  return;
                                }
				BK_Utils::set_status("'$activity_type' was Accepted");
				
				#invoke the BRISSkit 'consent_success' hook
				$results = module_invoke_all("consent_success",$activity_type,$params);
				$triggered = BK_Utils::check_results($results);
				
			}
		}
		catch(Exception $ex) {
			BK_Utils::set_status($ex->getMessage(),"error");	
		}
	}
}

/*
 * 	Case Types are created in one of 2 ways:
 * 	1) 	Through the standard admin backend, in which case it's for CiviStudy and represents a class of study
 * 			In this situation the context will be "CiviStudy"
 * 	2) 	Automatically after a Case (study) is created in CiviStudy. In this case it's for CiviRecruitment, and represents a specific study
 * 			In this situation the context will also be "CiviStudy"
 * 			This could cause a problem as we need to distinguish between the 2. We therefore pass a global variable to do this.
 * 
 * 	It's the CaseType - Component mapping row which identifies that a case type belongs to a particular component. For 2) the mapping row is 
 * 	created before the Case Type so we can use the existance or otherwise of this row to branch for case 1) or 2)
 */
function _case_type_created($objectId, &$objectRef) {
	global $study_created_flag;

  // $objectId represents the case type we have created
  $caseTypeId = $objectId;

  $component_id = BK_Component::get_component_id_by_case_type_id($caseTypeId);

  if ($component_id > 0) {
    $component_name = BK_Component::get_component_name_by_id($component_id);
  }
  else {
    $component_name = BK_Constants::CIVISTUDY;
    $component_id = BK_Component::get_component_id_by_name($component_name);
  }

  if ($component_name == BK_Constants::CIVIRECRUITMENT) {
    BK_Utils::audit("No creation of CiviRecruitment case type required");
    // No action
  }
  else if ($component_name == BK_Constants::CIVISTUDY) {
    if ($study_created_flag) {
      // i.e. a study was created earlier, and now we're creating a case type for the study, that recruitments can be attached to
      $recruitment_component_id = BK_Component::get_component_id_by_name(BK_Constants::CIVIRECRUITMENT);
      BK_Utils::audit(__FUNCTION__ . "creating CiviRecruitment case type mapping for $caseTypeId, $recruitment_component_id");
      BK_Component::create_case_type_mapping($caseTypeId, $recruitment_component_id);
    }
    else {
			// We need to check whether we're creating a case type representing the normal study type, or a case type holding a template 
			// We can identify the latter by the description
			//
			// For the former we want to automatically create a CT of the latter type
			//


      $result = civicrm_api3('CaseType', 'get', array(
        'sequential' => 1,
        'id' => $caseTypeId,
      ));

      BK_Utils::audit(print_r($result, true));

      if ($result['is_error']) {
        BK_Utils::audit("Error retrieving case type $caseTypeId " . __FILE__ . ' ' . __METHOD__ . "\n");
        throw new Exception("Error retrieving case type $caseTypeId " . __FILE__ . ' ' . __METHOD__ . "\n");
      }
      else {
        if ($result['count'] != 1) {
          BK_Utils::audit("$caseTypeId is not a valid case type id in " . __FILE__ . ' ' . __METHOD__ . "\n");
          throw new Exception("$caseTypeId is not a valid case type id in " . __FILE__ . ' ' . __METHOD__ . "\n");
        }
        else {
          BK_Utils::audit("OK");
        }
      }
			$case_type = $result['values'][0];
      BK_Utils::audit("Case type is " . print_r($case_type, TRUE));

			BK_Utils::audit('xxxxxxxxxxxxx');
			if (isset($case_type['description'])) {
  			$description = $case_type['description'];
			}
			else {
				$description = '';
			}
			BK_Utils::audit("Description is $description");
      BK_Utils::audit("prefix is " . BK_Constants::STUDY_TEMPLATE_PREFIX);
	
			if (strpos($description, BK_Constants::STUDY_TEMPLATE_PREFIX) === FALSE) {

				// BK_Utils::audit(__FUNCTION__ . print_r($objectRef, TRUE));


    		# $new_case_type_id = BK_Temp::create_case_type_template( $objectRef);
      	BK_Utils::audit(__FUNCTION__ . "creating CiviStudy case type template for $caseTypeId");
    		$new_case_type_id = BK_Temp::create_case_type_template( $objectId);
      	BK_Utils::audit(__FUNCTION__ . "creating CiviStudy case type mapping for $caseTypeId, $component_id");
      	BK_Component::create_case_type_mapping($caseTypeId, $component_id);
			}

/*
			const STUDY_TYPE_PREFIX = 'Study Type: ';
			const STUDY_TEMPLATE_PREFIX = 'Template for studies of type #';
*/


    }
  }
}

function _case_type_deleted($objectId) {
  // $objectId represents the case type we have deleted
  $caseTypeId = $objectId;

  BK_Utils::audit("Deleting case type mapping for $caseTypeId");
  BK_Component::delete_case_type_mapping($caseTypeId);
}

function _case_type_delete($objectId) {
  // Delete the template associated with the case type
  
  $result = civicrm_api3('CaseType', 'get', array(
    'sequential' => 1,
    'id' => $objectId,
  ));

  if ($result['is_error']) {
    throw new Exception("Error retrieving case type $caseTypeId " . __FILE__ . ' ' . __METHOD__ . "\n");
  }
  else {
    if ($result['count'] != 1) {
      throw new Exception("$caseTypeId is not a valid case type id in " . __FILE__ . ' ' . __METHOD__ . "\n");
    }
    else {
    }
  }

  BK_Utils::audit(print_r($result, TRUE));
  
  $template_name = $result['values'][0]['name'] . '_template';

  $result = civicrm_api3('CaseType', 'get', array(
    'sequential' => 1,
    'name' => $template_name,
    'api.CaseType.delete' => 1,
  ));
  BK_Utils::audit(print_r($result, TRUE));
}

function _case_created($objectId, &$objectRef) {
  // Called from _post

	global $study_created_flag;
  $caseId = $objectId;    // $objectId represents the case we have created

  // Cases are always created via the admin backend, as "Add Study" or "Add Recruitment" 
  // We may have come here via a json request, in which case we do not know from the url whether we're dealing with a case
  // of type 'study' or one of 'recruitment'. 
  // Luckily we can determine the component via the associated Case Type

  $case_type_id = $objectRef->case_type_id;
  $component_id = BK_Component::get_component_id_by_case_type_id($case_type_id);
  $component_name = BK_Component::get_component_name_by_id($component_id);
  BK_Utils::audit("case id and case_type_id are $caseId $case_type_id $component_id $component_name");

  if ($component_name == BK_Constants::CIVIRECRUITMENT) {
    BK_Utils::audit("creating CiviRecruitment case mapping for $caseId, $component_id");
    BK_Component::create_case_mapping($caseId, $component_id);

  }
  else if ($component_name == BK_Constants::CIVISTUDY) {
    $study_created_flag = TRUE;   // So when we later create a case type we know the case type is for a recruitment

    BK_Utils::audit("creating CiviStudy case mapping for $caseId, $component_id");
    BK_Component::create_case_mapping($caseId, $component_id);

    //
    // We also need to create a Case Type for the study, to be used in CiviRecruitment
    //
    BK_Utils::audit("creating CiviRecruitment case type for $caseId, $component_id");
    $new_case_type_id = BK_Temp::create_study_case_type_in_recruitment ( $objectRef);


/* Not sure if we need this  !!!! Shouldn't it be a case mapping????? Or will it be created automatically anyway???
    BK_Utils::audit(__FUNCTION__ . "creating CiviRecruitment case type mapping for $new_case_type_id, $caseId, $component_id");
    BK_Component::create_case_type_mapping ($new_case_type_id, $component_id);
*/

    /* We also want to create groups so we can create ACLs */

    BK_Temp::create_contact_groups_for_study($new_case_type_id);
  }
}



function _case_deleted($objectId) {
  // $objectId represents the case we have deleted
  $caseId = $objectId;

  BK_Utils::audit("Deleting case mapping for $caseId");
  BK_Component::delete_case_mapping($caseId);
}



function brisskit_civicrm_post( $op, $objectName, $objectId, &$objectRef ) {
  global $study_created_flag; // Set when we create a study

  BK_Utils::audit("brisskit_civicrm_post: $op, $objectName, $objectId");
  if ($objectName=="CaseType") {
    if ($op == 'create') {
			BK_Utils::audit(__FUNCTION__ . print_r($objectRef, TRUE));
			_case_type_created($objectId, $objectRef);
    }

    else if ($op == 'delete') {
			_case_type_deleted($objectId);
    }
  }
  // When a user creates a study in CiviStudy, we will create an associated Case Type for CiviRecruitment
  else if ($objectName=="Case") {
    if ($op == 'create') {
			BK_Utils::audit(__FUNCTION__ . print_r($objectRef, TRUE));
			_case_created($objectId, $objectRef);
    }
    else if ($op == 'delete') {
			_case_deleted($objectId);
    }
  }

  // The code from here is based on original drupal module
	global $prev_stat_id;
	if ($objectName=='Individual') {
    if (!empty($prev_stat_id)) {
      BK_Core::log_status_if_required($objectId,$op,$prev_stat_id);
    }
	}
	
	if ($objectName=="GroupContact") {
	}
	
  if (!BK_Component::is_recruitment()) {
    return;
  }

	#when work flow has been triggered need to set the wf_trigger flag to 1
	global $triggered;
	if ($triggered) {
		set_activity_triggered($objectId);
		$triggered=false;
	}
}

#implement BRISSkit participant available to be contacted hook
# 3.1. Automatically creates activities defined in the contact_participant ActivitySet of the civicase XML 
function brisskit_participant_available($params, $activity_id) {
  if (!BK_Component::is_recruitment()) {
    return;
  }

  // The code from here is based on original drupal module
	#add activities related to contacting participant to case
	
	# 3.2. Changes participant status to ÔIn studyÕ
	if (BK_Core::add_activity_set_to_case($params['case_id'],"contact_participant",$params['source_contact_id'])) {
		$case_id = $params['case_id'];
		$case_type = BK_Utils::get_case_type($case_id);
		BK_Core::set_contact_status_via_case($case_id, "In study","Status changed to 'In study' ($case_type) when availability confirmed.");

		BK_Utils::set_status("Activities now scheduled to contact potential participant re. study enrolment");
		BK_Utils::set_status("Participant status changed to 'In study'");
		return true;   
	}
}

#implement civi's civicrm_import hook (called following import of each individual)
function brisskit_civicrm_import( $object, $usage, &$objectRef, &$params ) {
  if (!BK_Component::is_recruitment()) {
    return;
  }

  // The code from here is based on original drupal module
	require_once "api/v3/utils.php";
	require_once "api/v3/Case.php";
	
	#determine if an initial study has been supplied in the import fields and if so add the participant to that initial study
	if (BK_Core::is_participant_in_initial_study($params)) {
		BK_Utils::set_custom_field("permission_given",1,$params);
		BK_Core::add_participant_to_initial_study($params);
	}
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @param $params array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
 * We are going to add Recruitment to the navigation menu.  Note that routing is taken
 * care of by the xmlMenu hook.  This hook only adds items in memory - it does not write 
 * to the database.  Consequently, these items cannot be edited via the Administer GUI.
 */
function brisskit_civicrm_navigationMenu(&$params) {
  // We'll add two sub menus, one for recruitments and one for studies
  // Passing name (unique), label, plural label
  //
  // These will appear after the normal position of the 'Cases' menu, in reverse order in which they are added
  // Note that the name affects the url so must match the values in Case.xml
  //
  _add_menu($params, 'recruitment', 'Recruitment', 'Recruitments');
  _add_menu($params, 'study', 'Study', 'Studies');
}

function _add_menu(&$params, $name, $label, $plural_label) {

  // Have we already added menu with this name?
  $menu_item_search = array('url' => "civicrm/$name");
  $menu_items = array();
  CRM_Core_BAO_Navigation::retrieve($menu_item_search, $menu_items);
 
  if ( ! empty($menu_items) ) { 
    return;  //already added, return
  }

  /*
    Build our own compact array for the new item(s).
    This allows us to define only the elements we're going to change &
    makes it easy to add other nav menu entries in the future (e.g. Study
    if we don't want to hijack Case).
  */
  $bk_menu_items[]= array 
    (
      'label' => $label,
      'name' => $label,
      'url' =>null, 
      'permission' => _getNavigationPermission ($name),
      'child' => array 
       (
        'label' => 'Dashboard',
        'name' => 'Dashboard',
        'url' => "civicrm/$name",
        'permission' => _getNavigationPermission ($name),
       ),
      'child2' => array 
       (
        'label' => "New $label",
        'name' => "New $label",
        'url' => "civicrm/$name/add?reset=1&action=add&context=standalone",
        'permission' => _getNavigationPermission ($name),
       ),
      'child3' => array 
       (
        'label' => "Find $plural_label",
        'name' => "Find $plural_label",
        'url' => "civicrm/$name/search?reset=1",
        'permission' => _getNavigationPermission ($name),
       )
  );

  // Call our helper function to do the actual add
  #$params=_build_menu_items ($bk_menu_items, $params);
  _build_menu_items ($bk_menu_items, $params);
}

/**
 * Helper function to build actual data structure used by navigationMenu.
 *
 * @param $bk_menu_items (array), $params array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function _build_menu_items ($bk_menu_items, &$params) {
  BK_Utils::audit ('params:'.print_r($params, TRUE));

  //  Get the maximum key of $params
  $maxKey = _getMenuKeyMax($params);

  if (!is_integer($maxKey)) {
    return $params;
  }

  $new_params = array();

  // These are tyhe elements we will add top the original params
  // Loop through items our caller wants added & build up the data structure.
  foreach ($bk_menu_items as $bk_menu) {
    $maxKey++;
    $new_params[$maxKey] = array (
      'attributes' => array (
          'label' => $bk_menu['label'],
          'name' => $bk_menu['name'],
          'url' =>$bk_menu['url'], 
          'permission' => $bk_menu['permission'],
          'operator' => 'OR',
          'separator' => 0,
          'parentID' =>null,
          'navID' => $maxKey,
          'active' => 1
        ),
      'child' => array
        (
          '1' => array
            (
              'attributes' => array
                (
                  'label' => $bk_menu['child']['label'],
                  'name' => $bk_menu['child']['name'],
                  'url' => $bk_menu['child']['url'],
                  'permission' => $bk_menu['child']['permission'],
                  'operator' => 'OR',
                  'separator' => 0,
                  'parentID' => $maxKey,
                  'navID' => 1,
                  'active' => 1
                ),

              'child' =>null 
            ),
          '2' => array
            (
              'attributes' => array
                (
                  'label' => $bk_menu['child2']['label'],
                  'name' => $bk_menu['child2']['name'],
                  'url' => $bk_menu['child2']['url'],
                  'permission' => $bk_menu['child2']['permission'],
                  'operator' => 'OR',
                  'separator' => 0,
                  'parentID' => $maxKey,
                  'navID' => 1,
                  'active' => 1
                ),

              'child' =>null 
            ),
          '3' => array
            (
              'attributes' => array
                (
                  'label' => $bk_menu['child3']['label'],
                  'name' => $bk_menu['child3']['name'],
                  'url' => $bk_menu['child3']['url'],
                  'permission' => $bk_menu['child3']['permission'],
                  'operator' => 'OR',
                  'separator' => 0,
                  'parentID' => $maxKey,
                  'navID' => 1,
                  'active' => 1
                ),

              'child' =>null 
            )
        )
    );
  }

  // Find the position of Cases item 
  // Params[]['attributes']['name'] == 'Cases'

  $idx = 0;
  foreach ($params as $key=>$value) {
    if ($value['attributes']['name'] == 'Cases') {
      break;
    }
    $idx++;
  }

  $idx++;

  $params = array_merge ( array_slice($params, 0, $idx, true), 
                          $new_params,
                          array_slice($params, $idx, count($params)-$idx, true));

  BK_Utils::audit (print_r($params, TRUE));
  # return $params;
}

/**
 * Helper function to find next available key in navigation menu array.
 *
 * @param $menuArray array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function _getMenuKeyMax($menuArray) {
  $max = array(max(array_keys($menuArray)));
  foreach($menuArray as $v) { 
    if (!empty($v['child'])) {
      $max[] = _getMenuKeyMax($v['child']); 
    }
  }
  return max($max);
}

/**
 * Helper function to return permission strings used in navigation menu array.
 *
 * @param $research_type (string) either recruitment or study
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function _getNavigationPermission ($research_type) {
  return 'access my cases and activities,access all cases and activities';
/* TODO
  if ($research_type=='recruitment') {
    return 'access my recruitments and activities,access all recruitments and activities';
  }
  else {
    return 'access my cases and activities,access all cases and activities';
  }
*/
}

function brisskit_civicrm_queryObjects(&$queryObjects, $type) {
  BK_Utils::audit ("queryObj hook");
  BK_Utils::audit ('qo:'.print_r($queryObjects, TRUE));
  BK_Utils::audit ('type:'.print_r($type, TRUE));

}

function brisskit_civicrm_pageRun(&$page) {
  $pageName = $page->getVar('_name');
  BK_Utils::audit ("pageRun hook $pageName");
  BK_Utils::audit ('Page: ' . print_r($page, TRUE));

  if ($pageName == 'CRM_Case_Page_DashBoard') {
    $template = $page->getTemplate();   // Returns the Smarty object
    BK_Utils::audit ('Template: ' . print_r($template, TRUE));
    $cases_summary = $template->get_template_vars('casesSummary');
    BK_Utils::audit ('Cases: ' . print_r($cases_summary, TRUE));

    //
    // Only include case types pertinent to CiviStudy or CiviRecruitment as applicable
    // Never include the "template" case types
    //

    $case_types = $cases_summary['rows'];
    $new_rows = array();

    foreach ($case_types as $case_type_name => $case_type_id) {
      if ( preg_match("/ Template$/", $case_type_name, $matches)) {
      }
      else if (BK_Component::is_study() && preg_match("/^Study:/", $case_type_name, $matches) ) {
      }
      else if (BK_Component::is_recruitment() && !preg_match("/^Study:/", $case_type_name, $matches) ) {
      }
      else {
        $new_rows[$case_type_name] = $case_type_id;
      }
    }
    $cases_summary['rows'] = $new_rows;
    $page->getTemplate()->assign('casesSummary', $cases_summary);
  }
}

function brisskit_civicrm_aclWhereClause( $type, &$tables, &$whereTables, &$contactID, &$where ) {
  BK_Utils::audit ("aclWhereClause hook type $type where $where");

}

/**
 * Implements hook_civicrm_permission().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_permission
 * 
 * Note: $permissions array changed for versions >=4.6.  Here we handle 
 * <4.6 one way and >4.6 the new way. Note: will not deal with <4.3.
 */
function brisskit_civicrm_permission(&$permissions) {
  // Firstly call the API to get our version number
  $version='';
  $result = civicrm_api3('Domain', 'get', array(
    'sequential' => 1,
    'return' => "version",
    'id' => 1,
  ));

  if ($result['count']==1) {
    $version=$result['values'][0]['version'];
  }

  // If we didn't get a version, do it the <4.6 way.
  if (empty($version)) {
    $permissions= _pre46_permissions($permissions);
    return;
  }

  // If major version is < 4 or minor version < 6 do it the old way
  $versioncomponents = explode('.', $version);
  if (($versioncomponents[0] < 4)
  || ($versioncomponents[1] < 6)) {
    $permissions= _pre46_permissions($permissions);
    return;
  }

  // Here major version is >= 4 and minor version >=6
  $permissions = array(
    'delete in custom_CiviRecruitment' => array(
      ts('delete in custom_CiviRecruitment'),
      ts('Delete custom_recruitments'),
    ),
    'administer custom_CiviRecruitment' => array(
      ts('administer custom_ CiviRecruitment'),
    ),
    'access my custom_recruitments and activities' => array(
      ts('access my custom_recruitments and activities'),
    ),
    'access all custom_recruitments and activities' => array(
      ts('access all custom_recruitments and activities'),
    ),
    'add custom_recruitments' => array(
      ts('add custom_recruitments'),
    ),
  );
}

/* this is the < 4.6 way of setting permissions */
function _pre46_permissions ($permissions) {
  $prefix = ts('CiviCRM Recruitment') . ': '; // name of extension or module
  $permissions['add Recruitments']        = $prefix . ts('add recruitments');
  $permissions['administer Recruitment']  = $prefix . ts('administer recruitment');
  $permissions['access my recruitments and activities'] = $prefix . ts('access my recruitments and activities');
  $permissions['access all recruitments and activities'] = $prefix . ts('access all recruitments and activities');
  $permissions['delete in Recruitment']   = $prefix . ts('delete recruitment');
  return $permissions;
}

function brisskit_civicrm_getCaseActivity ($caseID, &$params, $contactID, $context, $userID) {
  BK_Utils::audit ("getCaseActivity hook case $caseID, contact $contactID, user $userID context $context |");
}

/*
  TODO rewrite using roles and proper ACLs for now check they're in the 
  'View Case Activities Group' before granting access to activities.
*/
function brisskit_civicrm_control_access ($contactID, $userID, $access) {
  global $user;

  $contact = civicrm_api3('UFMatch', 'get', array(
    'sequential' => 1,
    'return' => "contact_id",
    'uf_id' => $user->uid,
  ));

  $groups = civicrm_api3('GroupContact', 'get', array(
    'sequential' => 1,
    'contact_id' => $contact['values'][0]['contact_id'],
  ));


  if ($groups['count'] > 0) {
    foreach ($groups['values'] as $group_value_set) {
      if (check_access ($group_value_set['title']) ) {
        return TRUE;
      }
    }
    #self::activityForm($this, $aTypes);
  }
  return FALSE;
}

function check_access ($group) {
  if ($group=='View Case Activities Group') {
    return TRUE;
  }
  else {
    return FALSE;
  }
}
