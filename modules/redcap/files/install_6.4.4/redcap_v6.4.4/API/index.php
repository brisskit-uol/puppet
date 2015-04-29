<?php

// Disable REDCap's authentication (will use API tokens for authentication)
define("NOAUTH", true);

// Set constant to denote that this is an API call
define("API", true);

// Config
require_once (dirname(dirname(__FILE__)) . '/Config/init_global.php');

// Increase memory limit in case needed for intensive processing
if (str_replace("M", "", ini_get('memory_limit')) < 1024) ini_set('memory_limit', '1024M');

/**
 * API FUNCTIONALITY
 */

# globals
$format = "xml";
$returnFormat = "xml";

# set format (default = xml)
$format = $_POST['format'];
switch ($format)
{
	case 'json':
		break;
	case 'csv':
		break;
	case 'xml':
	default:
		$format = "xml";
		break;
}
$_POST['format'] = $format;

# set returnFormat for outputting error messages and other stuff (default = xml)
$tempFormat = ($_POST['returnFormat'] != "") ? strtolower($_POST['returnFormat']) : strtolower($_POST['format']);
switch ($tempFormat)
{
	case 'json':
		$returnFormat = "json";
		break;
	case 'csv':
		$returnFormat = "csv";
		break;
	case 'xml':
	default:
		$returnFormat = "xml";
		break;
}

# check if the API is enabled first
if (!$api_enabled) RestUtility::sendResponse(503, $lang['api_01']);

# certain actions do NOT require a token
$tokenRequired = !(!isset($_POST['token']) && 
					// Advanced project bookmark
					(isset($_POST['authkey']) 
					// Get user tokens by supplying username/password
				 || ( isset($_POST['content']) && $_POST['content'] == 'gettokens') ));

# process the incoming request
$data = RestUtility::processRequest($tokenRequired);
# get all the variables sent in the request
$post = $data->getRequestVars();

# initialize array variables if they were NOT sent or if they are empty
if (!isset($post['records']) or $post['records'] == '') $post['records'] = array();
if (!isset($post['events']) or $post['events'] == '') $post['events'] = array();
if (!isset($post['fields']) or $post['fields'] == '') $post['fields'] = array();
if (!isset($post['forms']) or $post['forms'] == '') $post['forms'] = array();
if (!isset($post['arms']) or $post['arms'] == '') $post['arms'] = array();

if (!isset($post['mobile_app'])) $post['mobile_app'] = "0";
if (!isset($post['project_init'])) $post['project_init'] = "0";

if (!isset($post['format'])) $post['format'] = "";
if (!isset($post['type'])) $post['type'] = "";
if (!isset($post['rawOrLabel'])) $post['rawOrLabel'] = "";
if (!isset($post['rawOrLabelHeaders'])) $post['rawOrLabelHeaders'] = "";
if (!isset($post['overwriteBehavior'])) $post['overwriteBehavior'] = "";
if (!isset($post['action'])) $post['action'] = "";
if (!isset($post['returnContent'])) $post['returnContent'] = "";
if (!isset($post['event'])) $post['event'] = "";
if (!isset($post['armNumber'])) $post['armNumber'] = "";
if (!isset($post['armName'])) $post['armName'] = "";
if (!isset($post['dateFormat'])) {
	$post['dateFormat'] = "YMD";
} else {
	$post['dateFormat'] = ($post['dateFormat'] == 'DMY' ? 'DMY' : ($post['dateFormat'] == 'MDY' ? 'MDY' : 'YMD'));
}
$post['exportCheckboxLabel'] = (isset($post['exportCheckboxLabel']) && ($post['exportCheckboxLabel'] == '1' || strtolower($post['exportCheckboxLabel']."") === 'true'));

if (isset($post['authkey'])) $post['content'] = "authkey";

# determine if a valid content parameter was passed in
switch ($post['content'])
{
	case 'record':
		$post['exportSurveyFields'] = (isset($post['exportSurveyFields']) && ($post['exportSurveyFields'] == '1' || strtolower($post['exportSurveyFields']."") === 'true'));
		$post['exportDataAccessGroups'] = (isset($post['exportDataAccessGroups']) && ($post['exportDataAccessGroups'] == '1' || strtolower($post['exportDataAccessGroups']."") === 'true'));
		break;
	case 'metadata':
	case 'file':
	case 'filesize': // currently only used for mobile app usage to determine file size for API File Export
	case 'instrument':
	case 'event':
	case 'arm':
	case 'user':
	case 'gettokens':
	case 'report':
	case 'authkey':
	case 'version':
	case 'pdf':
	case 'surveyLink':
	case 'surveyQueueLink':
	case 'surveyReturnCode':
	case 'participantList':
	case 'exportFieldNames':
	case 'appRightsCheck':
	case 'formEventMapping':
	case 'fieldValidation':
		break;
	default:
		die(RestUtility::sendResponse(400, 'The value of the parameter "content" is not valid'));
		break;
}

# If content = file, determine if a valid action was passed in 
if ($post['content'] == "file" || $post['content'] == "filesize")
{
	switch (strtolower($post['action']))
	{
		case 'export':
		case 'import':
		case 'import_app':
		case 'delete':
			break;
		default:
			die(RestUtility::sendResponse(400, 'The value of the parameter "action" is not valid'));
			break;
	}
}
if ($post['content'] == 'version' || $post['content'] == 'event' || $post['content'] == "arm" || $post['content'] == "gettokens" || $post['content'] == "authkey")
{
	if ($post['action'] == "") $post['action'] = "export";
}

# set the import action option
if (strtolower($post['overwriteBehavior']) != 'normal' && strtolower($post['overwriteBehavior']) != 'overwrite') $post['overwriteBehavior'] = 'normal';

# set the type
if (strtolower($post['type']) != 'eav' && strtolower($post['type']) != 'flat') $post['type'] = 'flat';

# what content to return when importing data
switch (strtolower($post['returnContent']))
{
	case 'ids':
	case 'nothing':
	case 'count':
		break;
	default:
		$post['returnContent'] = 'count';
		break;
}

# set the type of content to be returned for a field that has data/value pairs
switch (strtolower($post['rawOrLabel']))
{
	case 'raw':
	case 'label':
		break;
	default:
		$post['rawOrLabel'] = 'raw';
		break;
}
switch (strtolower($post['rawOrLabelHeaders']))
{
	case 'raw':
	case 'label':
		break;
	default:
		$post['rawOrLabelHeaders'] = 'raw';
		break;
}

# set the event name option (if not set, use rawOrLabel option)
// eventName is a deprecated feature, so align it with rawOrLabel value for EAV only (since EAV is only place it still deals with it in old code)
$post['eventName'] = ($post['rawOrLabel'] == 'raw') ? 'unique' : 'label';


# determine if we are exporting, importing, or deleting data
if ($post['content'] == "file" || $post['content'] == 'event' || $post['content'] == "arm" || $post['content'] == "gettokens" || $post['content'] == "authkey") {
	$action = $post['action'];
}
else {
	$action = (!isset($post['data'])) ? 'export' : 'import';
}

# determine if the user has the correct user rights
if ($tokenRequired) {
	if ($action == "export") {
		if ($post['api_export'] != 1) {
			// Logging
			log_event('',"redcap_user_rights","ERROR",'',json_encode($_POST),"Failed API request (user rights invalid)");
			die(RestUtility::sendResponse(403, "You do not have API data export rights"));
		}
	}
	elseif ($action == "import") {
		if ($post['api_import'] != 1) {
			// Logging
			log_event('',"redcap_user_rights","ERROR",'',json_encode($_POST),"Failed API request (user rights invalid)");
			die(RestUtility::sendResponse(403, "You do not have API data import/change/delete rights"));
		}
	}
}

// For content=filesize, set content as file (method only used for mobile app to detect size of file)
if ($post['content'] == "filesize") {
	$post['content'] = "file";
	$post['filesize'] = 1;
}

# include the necessary file, based off of content type and whether the "data" field was passed in
include ($post['content'] . "/$action.php");
