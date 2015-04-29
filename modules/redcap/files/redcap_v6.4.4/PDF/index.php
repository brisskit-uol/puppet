<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Must have PHP extention "mbstring" installed in order to render UTF-8 characters properly AND also the PDF unicode fonts installed
$pathToPdfUtf8Fonts = APP_PATH_WEBTOOLS . "pdf" . DS . "font" . DS . "unifont" . DS;
if (function_exists('mb_convert_encoding') && is_dir($pathToPdfUtf8Fonts)) {
	// Define the UTF-8 PDF fonts' path
	define("FPDF_FONTPATH",   APP_PATH_WEBTOOLS . "pdf" . DS . "font" . DS);
	define("_SYSTEM_TTFONTS", APP_PATH_WEBTOOLS . "pdf" . DS . "font" . DS);
	// Set contant
	define("USE_UTF8", true);
	// Use tFPDF class for UTF-8 by default
	if ($project_encoding == 'chinese_utf8') {
		require_once APP_PATH_CLASSES . "PDF_Unicode.php";
	} else {
		require_once APP_PATH_CLASSES . "tFPDF.php";
	}
} else {
	// Set contant
	define("USE_UTF8", false);
	// Use normal FPDF class
	require_once APP_PATH_CLASSES . "FPDF.php";
}
// If using language 'Japanese', then use MBFPDF class for multi-byte string rendering
if ($project_encoding == 'japanese_sjis') 
{
	require_once APP_PATH_CLASSES . "MBFPDF.php"; // Japanese
	// Make sure mbstring is installed
	if (!function_exists('mb_convert_encoding'))
	{
		exit("ERROR: In order for multi-byte encoded text to render correctly in the PDF, you must have the PHP extention \"mbstring\" installed on your web server.");
	}
}

// Include other files needed
require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';
require_once APP_PATH_DOCROOT . "PDF/functions.php"; // This MUST be included AFTER we include the FPDF class

// Set the text that replaces data for de-id fields
define("DEID_TEXT", "[*DATA REMOVED*]");


// Save fields into metadata array
$draftMode = false;
if (isset($_GET['page'])) {
	// Check if we should get metadata for draft mode or not
	$draftMode = ($status > 0 && isset($_GET['draftmode']));
	$metadata_table = ($draftMode) ? "redcap_metadata_temp" : "redcap_metadata";
	// Make sure form exists first
	if ((!$draftMode && !isset($Proj->forms[$_GET['page']])) || ($draftMode && !isset($Proj->forms_temp[$_GET['page']]))) {
		exit('ERROR!');
	}
	$Query = "select * from $metadata_table where project_id = $project_id and ((form_name = '{$_GET['page']}'
			  and field_name != concat(form_name,'_complete')) or field_name = '$table_pk') order by field_order";
} else {
	$Query = "select * from redcap_metadata where project_id = $project_id and
			  (field_name != concat(form_name,'_complete') or field_name = '$table_pk') order by field_order";
}
$QQuery = db_query($Query);
$metadata = array();
while ($row = db_fetch_assoc($QQuery)) 
{
	// If field is an "sql" field type, then retrieve enum from query result
	if ($row['element_type'] == "sql") {
		$row['element_enum'] = getSqlFieldEnum($row['element_enum']);
	}
	// If PK field...
	if ($row['field_name'] == $table_pk) {
		// Ensure PK field is a text field
		$row['element_type'] = 'text';
		// When pulling a single form other than the first form, change PK form_name to prevent it being on its own page
		if (isset($_GET['page'])) {
			$row['form_name'] = $_GET['page'];
		}
	}
	// Store metadata in array
	$metadata[] = $row;	
}


// In case we need to output the Draft Mode version of the PDF, set $Proj object attributes as global vars
if ($draftMode) {
	$ProjMetadata = $Proj->metadata_temp;
	$ProjForms = $Proj->forms_temp;
	$ProjMatrixGroupNames = $Proj->matrixGroupNamesTemp;
} else {
	$ProjMetadata = $Proj->metadata;
	$ProjForms = $Proj->forms;
	$ProjMatrixGroupNames = $Proj->matrixGroupNames;
}

// Initialize values
$Data = array();
$study_id_event = "";
$logging_description = "Download data entry form as PDF" . (isset($_GET['id']) ? " (with data)" : "");


// Check export rights
if ((isset($_GET['id']) || isset($_GET['allrecords'])) && $user_rights['data_export_tool'] == '0') {
	exit($lang['data_entry_233']);
}


// GET SINGLE RECORD'S DATA (ALL FORMS for ALL EVENTS or SINGLE EVENT if event_id provided)
if (isset($_GET['id']) && !isset($_GET['page'])) 
{
	// Set logging description
	$logging_description = "Download all data entry forms as PDF (with data)";
	// Get all data for this record
	$Data = Records::getData('array', $_GET['id'], array(), (isset($_GET['event_id']) ? $_GET['event_id'] : array()), $user_rights['group_id']);
	if (!isset($Data[$_GET['id']])) $Data = array();
}

// GET SINGLE RECORD'S DATA (SINGLE FORM ONLY)
elseif (isset($_GET['id']) && isset($_GET['page'])) 
{
	$id = trim($_GET['id']);
	// Ensure the event_id belongs to this project, and additionally if longitudinal, can be used with this form
	if (isset($_GET['event_id'])) {
		if (!$Proj->validateEventId($_GET['event_id']) 
			// Check if form has been designated for this event
			|| !$Proj->validateFormEvent($_GET['page'], $_GET['event_id'])
			|| ($id == "") )
		{
			if ($longitudinal) {
				redirect(APP_PATH_WEBROOT . "DataEntry/grid.php?pid=" . PROJECT_ID);
			} else {
				redirect(APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID . "&page=" . $_GET['page']);
			}
		}
	}
	// Get all data for this record
	$Data = Records::getData('array', $id, array_merge(array($table_pk), array_keys($Proj->forms[$_GET['page']]['fields'])), 
			(isset($_GET['event_id']) ? $_GET['event_id'] : array()), $user_rights['group_id']);
	if (!isset($Data[$id])) $Data = array();
}

// GET ALL RECORDS' DATA
elseif (isset($_GET['allrecords']))
{
	// Set logging description
	$logging_description = "Download all data entry forms as PDF (all records)";
	// Get all data for this record
	$Data = Records::getData('array', array(), array(), array(), $user_rights['group_id']);
}

// BLANK PDF FOR SINGLE FORM OR ALL FORMS
else 
{
	$Data[''][''] = null;
	// Set logging description
	if (isset($_GET['page'])) {
		$logging_description = "Download data entry form as PDF";
	} else {
		$logging_description = "Download all data entry forms as PDF";
	}
}

## REFORMAT DATES AND/OR REMOVE DATA VALUES FOR DE-ID RIGHTS
if (!isset($Data[''])) {
	// Get all validation types to use for converting DMY and MDY date formats
	$valTypes = getValTypes();
	$dateTimeFields = $dateTimeValTypes = array();
	foreach ($valTypes as $valtype=>$attr) {
		if (in_array($attr['data_type'], array('date', 'datetime', 'datetime_seconds'))) {
			$dateTimeValTypes[] = $valtype;
		}
	}
	// Create array of MDY and DMY date/time fields
	$field_names = array();
	foreach ($metadata as $attr) {
		$field_names[] = $attr['field_name'];
		if (in_array($attr['element_validation_type'], $dateTimeValTypes)) {
			$dateFormat = substr($attr['element_validation_type'], -3);
			if ($dateFormat == 'mdy' || $dateFormat == 'dmy') {
				$dateTimeFields[$attr['field_name']] = $dateFormat;
			}
		}
	}
	// If user has de-id rights, then get list of fields
	$deidFieldsToRemove = ($user_rights['data_export_tool'] > 1) 
						? DataExport::deidFieldsToRemove($field_names, ($user_rights['data_export_tool'] == '3'))
						: array();
	$deidFieldsToRemove = array_fill_keys($deidFieldsToRemove, true);
	unset($field_names);
	// Set flags
	$checkDateTimeFields = !empty($dateTimeFields);
	$checkDeidFieldsToRemove = !empty($deidFieldsToRemove);
	// Loop through all data values
	if ($checkDateTimeFields || $checkDeidFieldsToRemove) {
		foreach ($Data as $this_record=>&$event_data) {
			foreach ($event_data as $this_event_id=>&$field_data) {
				foreach ($field_data as $this_field=>$this_value) {
					// If value is not blank
					if ($this_value != '') {
						// If a DMY or MDY datetime field, then convert value
						if ($checkDeidFieldsToRemove && isset($deidFieldsToRemove[$this_field])) {
							// If this is the Record ID field, then merely hash it IF the user has de-id or remove identifiers export rights
							if ($this_field == $Proj->table_pk) {
								if ($Proj->table_pk_phi) {
									$Data[$this_record][$this_event_id][$this_field] = md5($salt . $this_record . $__SALT__);
								}
							} else {
								$Data[$this_record][$this_event_id][$this_field] = DEID_TEXT;
							}
						}
						// If a DMY or MDY datetime field, then convert value
						elseif ($checkDateTimeFields && isset($dateTimeFields[$this_field])) {
							$Data[$this_record][$this_event_id][$this_field] = DateTimeRC::datetimeConvert($this_value, 'ymd', $dateTimeFields[$this_field]);
						}
					}
				}
			}
		}
	}
}

// If form was downloaded from Shared Library and has an Acknowledgement, render it here
$acknowledgement = getAcknowledgement($project_id, $_GET['page']);

// Loop through metadata and replace any &nbsp; character codes with spaces
foreach ($metadata as &$attr) {
	$attr['element_label'] = str_replace('&nbsp;', ' ', $attr['element_label']);
	$attr['element_enum'] = str_replace('&nbsp;', ' ', $attr['element_enum']);
	$attr['element_note'] = str_replace('&nbsp;', ' ', $attr['element_note']);
	$attr['element_preceding_header'] = str_replace('&nbsp;', ' ', $attr['element_preceding_header']);
}

// Logging (but don't do it if this script is being called via the API or via a plugin)
if (!defined("API") && !defined("PLUGIN")) {
	log_event("","redcap_metadata","MANAGE",$_GET['page'],"form_name = '{$_GET['page']}'",$logging_description);
}

// Render the PDF
renderPDF($metadata, $acknowledgement, strip_tags(label_decode($app_title)), $Data);
