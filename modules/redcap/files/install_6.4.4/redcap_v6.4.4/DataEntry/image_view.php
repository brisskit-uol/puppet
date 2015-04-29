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


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

// If ID is not in query_string, then return error
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) exit("{$lang['global_01']}!");

// Confirm the hash of the doc_id
if (!isset($_GET['doc_id_hash']) || (isset($_GET['doc_id_hash']) && $_GET['doc_id_hash'] != Files::docIdHash($_GET['id']))) {
	exit("{$lang['global_01']}!");
}

// Ensure that this file belongs to this project
$sql = "select * from redcap_edocs_metadata where doc_id = " . prep($_GET['id']). " and project_id = $project_id 
		and delete_date is null limit 1";
$q = db_query($sql);
$edoc_info = db_fetch_assoc($q);
$isValidFile = db_num_rows($q);

if (!$isValidFile) 
{
	## Give error message
	header('Content-type: image/png');
} 
else 
{
	## Display image
	
	// If missing mime-type, then try to add it manually (especially for PNGs from jSignature)
	if ($edoc_info['mime_type'] == '') {
		$edoc_info['mime_type'] = 'image/'.strtolower(getFileExt($edoc_info['doc_name']));
	}
	
	if ($edoc_storage_option == '0') {
		
		//Download from "edocs" folder (use default or custom path for storage)
		$local_file = EDOC_PATH . $edoc_info['stored_name'];
		if (file_exists($local_file) && is_file($local_file)) 
		{			
			// If using IE6-8 and viewing a JPEG, check if it's a CMYK-color JPG. If so, IE6-8 cannot display it, 
			// so replace it with an informational image that explains why it can't be displayed.
			if ($isIE && vIE() < 9 && $edoc_info['mime_type'] == 'image/jpeg')
			{
				// Get image properties to determine if CMYK color is being used (as opposed to RGB)
				$imgProp = getimagesize($local_file);
				if ($imgProp['channels'] == 4) {
					// The JPEG is CMYK, so replace it with our stock PNG image ie_cannot_display_jpg.png
					$edoc_info['mime_type'] = 'image/png';
					$local_file = APP_PATH_DOCROOT . "Resources" . DS . "images" . DS . "ie_cannot_display_jpg.png";
				}
			}			
			// Set image header
			header('Content-type: ' . $edoc_info['mime_type']);
			// Output image data
			ob_end_flush();
			readfile_chunked($local_file);
		} 
		else 
		{
			## Give error message
			header('Content-type: image/png');
		}
		
	} elseif ($edoc_storage_option == '2') {
		// S3
		$s3 = new S3($amazon_s3_key, $amazon_s3_secret, SSL);
		if ($object = $s3->getObject($amazon_s3_bucket, $edoc_info['stored_name'])) {
		  // Set image header
			header('Content-type: ' . $edoc_info['mime_type']);
			// Output image data
			print $object->body;
			flush();
		} else {
			## Give error message
			header('Content-type: image/png');
		}
		
	} else {
		
		//Download using WebDAV
		include (APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php');
		$wdc = new WebdavClient();
		$wdc->set_server($webdav_hostname);
		$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
		$wdc->set_user($webdav_username);
		$wdc->set_pass($webdav_password);
		$wdc->set_protocol(1); //use HTTP/1.1
		$wdc->set_debug(false);
		if (!$wdc->open()) {
			## Give error message
			header('Content-type: image/png');
		}
		$http_status = $wdc->get($webdav_path . $edoc_info['stored_name'], $contents); //$contents is produced by webdav class
		$wdc->close();
		
		//Send file headers and contents
		header('Content-type: ' . $edoc_info['mime_type']);
		print $contents;
		flush();
		
	}
	
}
