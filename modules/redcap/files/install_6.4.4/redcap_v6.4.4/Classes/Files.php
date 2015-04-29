<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/


/**
 * FILES Class
 * Contains methods used with regard to uploaded files
 */
class Files
{
	/**
	 * DETERMINE IF WE'RE ON A VERSION OF PHP THAT SUPPORTS ZIPARCHIVE (PHP 5.2.0)
	 * Returns boolean.
	 */
	public static function hasZipArchive()
	{
		return (class_exists('ZipArchive'));
	}
	
	
	/**
	 * DETERMINE IF PROJECT HAS ANY "FILE UPLOAD" FIELDS IN METADATA	
	 * Returns boolean.
	 */
	public static function hasFileUploadFields()
	{
		global $Proj;
		return $Proj->hasFileUploadFields;
	}
	
	
	/**
	 * MOVES FILE FROM EDOC STORAGE LOCATION TO REDCAP'S TEMP DIRECTORY	
	 * Returns full file path in temp directory, or FALSE if failed to move it to temp.
	 */
	public static function copyEdocToTemp($edoc_id, $prependHashToFilename=false)
	{
		global $edoc_storage_option, $amazon_s3_key, $amazon_s3_secret, $amazon_s3_bucket;
		
		if (!is_numeric($edoc_id)) return false;
		
		// Get filenames from edoc_id
		$q = db_query("select doc_name, stored_name from redcap_edocs_metadata where delete_date is null and doc_id = ".prep($edoc_id));
		if (!db_num_rows($q)) return false;
		$edoc_orig_filename = db_result($q, 0, 'doc_name');
		$stored_filename = db_result($q, 0, 'stored_name');
		
		// Set full file path in temp directory. Replace any spaces with underscores for compatibility.
		$filename_tmp = APP_PATH_TEMP . ($prependHashToFilename ? substr(md5(rand()), 0, 8) . '_' : '') 
					  . str_replace(" ", "_", $edoc_orig_filename);
		
		if ($edoc_storage_option == '0') {
			// LOCAL
			if (file_put_contents($filename_tmp, file_get_contents(EDOC_PATH . $stored_filename))) {
				return $filename_tmp;
			}
			return false;
		} elseif ($edoc_storage_option == '2') {
			// S3
			$s3 = new S3($amazon_s3_key, $amazon_s3_secret, SSL);
			if (($object = $s3->getObject($amazon_s3_bucket, $stored_filename, $filename_tmp)) !== false) {
				return $filename_tmp;
			}
			return false;
		} else {			
			//  WebDAV
			include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php';
			$wdc = new WebdavClient();
			$wdc->set_server($webdav_hostname);
			$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
			$wdc->set_user($webdav_username);
			$wdc->set_pass($webdav_password);
			$wdc->set_protocol(1); //use HTTP/1.1
			$wdc->set_debug(false);
			if (substr($webdav_path,-1) != '/') {
				$webdav_path .= '/';
			}
			$http_status = $wdc->get($webdav_path . $stored_filename, $contents); //$contents is produced by webdav class
			$wdc->close();
			if (file_put_contents($filename_tmp, $contents)) {
				return $filename_tmp;
			}
			return false;
		}
		return false;
	}
	
	
	/**
	 * DETERMINE IF PROJECT HAS AT LEAST ONE FILE ALREADY UPLOADED FOR A "FILE UPLOAD" FIELD	
	 * Returns boolean.
	 */
	public static function hasUploadedFiles()
	{
		global $user_rights;
		// If has no file upload fields, then return false
		if (!self::hasFileUploadFields()) return false;
		// If user is in a DAG, limit to only records in their DAG	
		$group_sql = "";
		if ($user_rights['group_id'] != "") {
			$group_sql  = "and d.record in (" . pre_query("select record from redcap_data where project_id = ".PROJECT_ID." 
						  and field_name = '__GROUPID__' and value = '" . $user_rights['group_id'] . "'") . ")"; 
		}
		// Check if there exists at least one uploaded file
		$sql = "select 1 from redcap_data d, redcap_metadata m where m.project_id = ".PROJECT_ID." 
				and m.project_id = d.project_id and d.field_name = m.field_name $group_sql
				and m.element_type = 'file' and d.value != '' limit 1";
		$q = db_query($sql);
		// Return true if one exists
		return (db_num_rows($q) > 0);
	}
	
	
	/**
	 * RETURN HASH OF DOC_ID FOR A FILE IN THE EDOCS_METADATA TABLE
	 * This is used for verifying files, especially when uploaded when the record does not exist yet.
	 * Also to protect from people randomly discovering other people's uploaded files by modifying the URL.
	 */
	public static function docIdHash($doc_id)
	{
		global $salt, $__SALT__;
		return sha1($salt . $doc_id . $__SALT__);
	}
	
}
