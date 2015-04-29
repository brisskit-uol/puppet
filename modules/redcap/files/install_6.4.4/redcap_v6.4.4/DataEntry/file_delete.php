<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/


// Check if coming from survey or authenticated form
if (isset($_GET['s']) && !empty($_GET['s']))
{
	// Call config_functions before config file in this case since we need some setup before calling config
	require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
	// Survey functions needed
	require_once dirname(dirname(__FILE__)) . "/Surveys/survey_functions.php";
	// Validate and clean the survey hash, while also returning if a legacy hash
	$hash = $_GET['s'] = Survey::checkSurveyHash();
	// Set all survey attributes as global variables
	Survey::setSurveyVals($hash);
	// Now set $_GET['pid'] before calling config
	$_GET['pid'] = $project_id;
	// Set flag for no authentication for survey pages
	define("NOAUTH", true);
}


// Required files
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';


// Surveys only: Perform double checking to make sure the survey participant has rights to this file
if (isset($_GET['s']) && !empty($_GET['s']))
{
	checkSurveyFileRights();
	$field_label_page = APP_PATH_SURVEY . "index.php?pid=$project_id&__passthru=".urlencode("Design/get_fieldlabel.php");
}
// Non-surveys: Check form-level rights and DAGs to ensure user has access to this file
elseif (!isset($_GET['s']) || empty($_GET['s']))
{
	checkFormFileRights();
	$field_label_page = APP_PATH_WEBROOT . "Design/get_fieldlabel.php?pid=$project_id";	
}


if (is_numeric($_GET['event_id']) && is_numeric($_GET['id']) && isset($Proj->metadata[$_GET['field_name']]))
{
	// If user is a double data entry person, append --# to record id when saving
	if (isset($user_rights) && $double_data_entry && $user_rights['double_data'] != 0) 
	{
		$_GET['record'] .= "--" . $user_rights['double_data'];
	}

	// Set the file as "deleted" in redcap_edocs_metadata table, but don't really delete the file or the table entry.
	// NOTE: If *somehow* another record has the same doc_id attached to it (not sure how this would happen), then do NOT 
	// set the file to be deleted (hence the left join of d2).
	// $edoc_q = "UPDATE redcap_edocs_metadata SET delete_date = '" . NOW . "' WHERE doc_id = " . $_GET['id'];
	$edoc_q =  "update redcap_edocs_metadata e, redcap_data d left join redcap_data d2 
				on d2.project_id = d.project_id and d2.value = d.value and d2.field_name = d.field_name and d2.record != d.record 
				set e.delete_date = '".NOW."' 
				where e.project_id = " . PROJECT_ID . " and e.project_id = d.project_id
				and d.field_name = '{$_GET['field_name']}' and d.value = e.doc_id and d.record = '" . prep($_GET['record']) . "' 
				and e.delete_date is null and d2.project_id is null and e.doc_id = " . $_GET['id'];
	$q = db_query($edoc_q);

	// Delete data for this field from data table
	$sql = "DELETE FROM redcap_data WHERE record = '" . prep($_GET['record']) . "' AND field_name = '{$_GET['field_name']}' 
			AND project_id = $project_id AND event_id = {$_GET['event_id']}";
	$q = db_query($sql);
	log_event($sql,"redcap_data","doc_delete",$_GET['record'],$_GET['field_name'],"Delete uploaded document");
	
	// Boolean if a signature file upload type
	$signature_field = ($Proj->metadata[$_GET['field_name']]['element_validation_type'] == 'signature') ? '1' : '0';
	
	// Link text
	$file_link_text = ($Proj->metadata[$_GET['field_name']]['element_validation_type'] == 'signature') ? $lang['form_renderer_31'] : $lang['form_renderer_23'];
	
	// Send back HTML for uploading a new file (since this one has been removed)
	print  '<img src="'.APP_PATH_IMAGES.'add.png" class="imgfix"> 
			<a href="javascript:;" style="text-decoration:underline;font-size:12px;color:green;font-family:Arial;" onclick="filePopUp(\''.$_GET['field_name'].'\',\''.$field_label_page.'\','.$signature_field.');return false;">'.$file_link_text.'</a>';

}