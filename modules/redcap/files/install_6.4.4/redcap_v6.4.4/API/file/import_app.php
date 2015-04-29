<?php
defined("PROJECT_ID") or define("PROJECT_ID", $post['projectid']);

# get project information
$Proj = new Project();
$longitudinal = $Proj->longitudinal;
$primaryKey = $Proj->table_pk;
$project_id = $post['projectid'];

# check to see if a file was uploaded
if (count($_FILES) == 0) RestUtility::sendResponse(400, "No valid file was uploaded");

# make sure there were no errors associated with the uploaded file
if ($_FILES['file']['error'] != 0) RestUtility::sendResponse(400, "There was a problem with the uploaded file");

// Prevent data writes for projects in inactive or archived status
if ($Proj->project['status'] > 1) {
	if ($Proj->project['status'] == '2') {
		$statusLabel = "Inactive";
	} elseif ($Proj->project['status'] == '3') {
		$statusLabel = "Archived";
	} else {
		$statusLabel = "[unknown]";
	}
	die(RestUtility::sendResponse(403, "The file cannot be uploaded because the project is in $statusLabel status."));
}

# get file information
$fileData = $_FILES['file'];

$docName = str_replace("'", "", html_entity_decode(stripslashes($fileData['name']), ENT_QUOTES));
$docSize = $fileData['size'];

# Check if file is larger than max file upload limit
if (($docSize/1024/1024) > maxUploadSizeEdoc() || $fileData['error'] != UPLOAD_ERR_OK) {
	RestUtility::sendResponse(400, "The uploaded file exceeded the maximum file size limit of ".maxUploadSize()." MB");
}

# Upload the file and return the doc_id from the edocs table
$docId = uploadFile($fileData);

# Update tables if file was successfully uploaded
if ($docId != 0)
{	
	// Get USERID for logging, etc.
	$query = "SELECT username FROM redcap_user_rights WHERE api_token = '" . $post['token'] . "'";
	defined("USERID") or define("USERID", db_result(db_query($query), 0));
	// Detect type of file
	$docTypesAll = array('ESCAPE_HATCH', 'LOGGING');
	$docType = (in_array($post['file_type'], $docTypesAll)) ? $post['file_type'] : 'ESCAPE_HATCH';
	// Add to mobile app files table
	$sql = "INSERT INTO redcap_mobile_app_files (doc_id, type, user_id)
			VALUES ('$docId', '$docType', (select ui_id from redcap_user_information where username = '".prep(USERID)."'))";
	db_query($sql);
	// Log file upload
	log_event($sql,"redcap_mobile_app_files","DOC_UPLOAD",$docId,"doc_id = $docId","Upload document to mobile app archive");
}
else {
	RestUtility::sendResponse(400, "A problem occurred while trying to save the uploaded file");
}

# Send the response to the requester
RestUtility::sendResponse(200);
