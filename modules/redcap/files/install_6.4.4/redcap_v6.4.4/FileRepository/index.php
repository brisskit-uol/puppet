<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Required files
require_once (APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php');

// Setup Variables
$page_instructions = "<br>";
$record_delete_option = true;  //used in decision to keep delete button.
$errors = array();

if ($edoc_storage_option == '1') {
	// Upload using WebDAV
	require_once (APP_PATH_CLASSES . "WebdavClient.php");
	require_once (APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php');
	$wdc = new WebdavClient();
	$wdc->set_server($webdav_hostname);
	$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
	$wdc->set_user($webdav_username);
	$wdc->set_pass($webdav_password);
	$wdc->set_protocol(1); // use HTTP/1.1
	$wdc->set_debug(FALSE); // enable debugging?
	if (!$wdc->open()) {
		$errors[] = $lang['control_center_206'];
	}
}

## Building Elements Array
$elements1[] = array();
if (isset($_GET['id']) && $_GET['id']) {
	$elements1[]=array('rr_type'=>'textarea', 'name'=>'docs_comment', 'label'=>$lang['docs_23'].' &ensp;', 'style'=>'width:250px;height:70px;font-family:Arial;font-size:12px;');
	$elements1[]=array('rr_type'=>'hidden', 'name'=>'docs_id');
	$elements1[]=array('rr_type'=>'static', 'name'=>'docs_date', 'label'=>$lang['docs_25']);
	$elements1[]=array('rr_type'=>'static', 'name'=>'docs_name', 'label'=>$lang['docs_26']);
	$elements1[]=array('rr_type'=>'static', 'name'=>'docs_size', 'label'=>$lang['docs_27']);
	$elements1[]=array('rr_type'=>'hidden', 'name'=>'docs_type');
} else {	
	$elements1[]=array('rr_type'=>'file2', 'name'=>'docs_file', 'label'=>$lang['docs_24']);
	$elements1[]=array('rr_type'=>'textarea', 'name'=>'docs_comment', 'label'=>$lang['docs_23'].' &ensp;', 'style'=>'width:250px;height:70px;font-family:Arial;font-size:12px;');
	$elements1[]=array('rr_type'=>'hidden', 'name'=>'docs_id');
	$elements1[]=array('rr_type'=>'hidden', 'name'=>'docs_date');
	$elements1[]=array('rr_type'=>'hidden', 'name'=>'docs_name');
	$elements1[]=array('rr_type'=>'hidden', 'name'=>'docs_size');
	$elements1[]=array('rr_type'=>'hidden', 'name'=>'docs_type');
	$record_delete_option = false;
}
if ($project_language == 'English') {
	// ENGLISH
	$context_msg_update = "{$lang['docs_22']} {fetched} {$lang['docs_07']}";
	$context_msg_insert = "{$lang['docs_22']} {$lang['docs_08']}";
	$context_msg_delete = "{$lang['docs_22']} {$lang['docs_09']}";
	$context_msg_cancel = "{$lang['docs_22']} {$lang['docs_10']}";
} else {
	// NON-ENGLISH
	$context_msg_update = ucfirst($lang['docs_22'])."{fetched} {$lang['docs_07']}";
	$context_msg_insert = ucfirst($lang['docs_22'])." {$lang['docs_08']}";
	$context_msg_delete = ucfirst($lang['docs_22'])." {$lang['docs_09']}";
	$context_msg_cancel = ucfirst($lang['docs_22'])." {$lang['docs_10']}";
}
$context_msg_edit =   "<div class='blue'><img src='".APP_PATH_IMAGES."pencil.png' class='imgfix'> {$lang['docs_11']} {$lang['docs_22']}</div>";
$context_msg_add =    "<div class='darkgreen'><img src='".APP_PATH_IMAGES."add.png' class='imgfix'> <b>{$lang['docs_13']} {$lang['docs_22']}</b></span></div>";


################################################################################
##HTML Page Rendering
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

print "<script type='text/javascript'>var delete_doc_msg = \"{$lang['docs_46']}\";</script>";

################################################################################
## Processing Returned Results
# Control is left with user to customize this section.
# However, the default works nicely for most save, delete, cancel operations.
if (isset($_POST['submit'])) {
	
	// print_array($_POST);
	// print_array($_FILES);

	$fetched = isset($_POST['docs_id']) ? $_POST['docs_id'] : '';
	
	switch ($_POST['submit'])
	{
		case 'Upload File':
		
			$database_success = FALSE;
			$upload_success = FALSE;
			$dummy = $_FILES['docs_file'];
			$errors = array();
			
			if (($dummy['size']/1024/1024) > maxUploadSizeFileRespository())
			{
				// Delete uploaded file from server
				unlink($dummy['tmp_name']);
				// Set error msg
				$errors[] = $lang['sendit_03'] . ' (' . round_up($dummy['size']/1024/1024) . ' MB) ' . 
							 $lang['sendit_04'] . ' ' . maxUploadSizeFileRespository() . ' MB ' . $lang['sendit_05'];
			}
			if (strlen($dummy['tmp_name']) > 0 && empty($errors)) 
			{
				$dummy_tmp_name  = $dummy['tmp_name'];
				$dummy_file_size = $dummy['size'];
				$dummy_file_type = $dummy['type'];
				$dummy_file_name = $dummy['name'];
				$dummy_file_name = preg_replace("/[^a-zA-Z-._0-9]/","_",$dummy_file_name);
				$dummy_file_name = str_replace("__","_",$dummy_file_name);
				$dummy_file_name = str_replace("__","_",$dummy_file_name);
				$file_extension = getFileExt($dummy_file_name);
				$stored_name = date('YmdHis') . "_pid" . $project_id . "_" . generateRandomHash(6) . getFileExt($dummy_file_name, true);
				
				if ($edoc_storage_option == '1') 
				{
					// Webdav
					$dummy_file_content = file_get_contents($dummy['tmp_name']);
					$upload_success = ($wdc->put($webdav_path . $stored_name, $dummy_file_content) == '201');
				} 
				elseif ($edoc_storage_option == '2') 
				{
					// S3
					$s3 = new S3($amazon_s3_key, $amazon_s3_secret, SSL);
					$upload_success = ($s3->putObjectFile($dummy['tmp_name'], $amazon_s3_bucket, $stored_name, S3::ACL_PUBLIC_READ_WRITE));
				}
				else 
				{
					// Local
					$upload_success = move_uploaded_file($dummy_tmp_name, EDOC_PATH . $stored_name);
				}
				
				if ($upload_success === TRUE) {
				
					$sql = "INSERT INTO redcap_docs (project_id,docs_date,docs_name,docs_size,docs_type,docs_comment,docs_rights) 
							VALUES ($project_id,CURRENT_DATE,'$dummy_file_name','$dummy_file_size','$dummy_file_type',
									'".prep($_POST['docs_comment'])."',NULL)";
					if (db_query($sql)) {
						$docs_id = db_insert_id();
						$sql = "INSERT INTO redcap_edocs_metadata (stored_name,mime_type,doc_name,doc_size,file_extension,project_id,stored_date)
								VALUES('".$stored_name."','".$dummy_file_type."','".$dummy_file_name."','".$dummy_file_size."',
									   '".$file_extension."','".$project_id."','".date('Y-m-d H:i:s')."');";
						if (db_query($sql)) {
							$doc_id = db_insert_id();
							$sql = "INSERT INTO redcap_docs_to_edocs (docs_id,doc_id) VALUES ('".$docs_id."','".$doc_id."');";
							if (db_query($sql)) {
								// Logging
								log_event("","redcap_docs","MANAGE",$docs_id,"docs_id = $docs_id","Upload document to file repository");
								$context_msg = str_replace('{fetched}', '', $context_msg_insert);
								$database_success = TRUE;
							} else {
								/* if this failed, we need to roll back redcap_edocs_metadata and redcap_docs */
								db_query("DELETE FROM redcap_edocs_metadata WHERE doc_id='".$doc_id."';");
								db_query("DELETE FROM redcap_docs WHERE docs_id='".$docs_id."';");
								delete_repository_file($stored_name);
							}
						} else {
							/* if we failed here, we need to roll back redcap_docs */
							db_query("DELETE FROM redcap_docs WHERE  docs_id='".$docs_id."';");
							delete_repository_file($stored_name);
						}
					} else {
						/* if we failed here, we need to delete the file */
						delete_repository_file($stored_name);
					}

					/* legacy code saves to the database, we're going to set this aside in favor of the new way
					$sql = "INSERT INTO redcap_docs (project_id,docs_date,docs_name,docs_size,docs_type,docs_file,docs_comment,docs_rights) 
							VALUES ($project_id,CURRENT_DATE,'$dummy_file_name','$dummy_file_size','$dummy_file_type','".addslashes($data)."',
									'".prep($_POST['docs_comment'])."',NULL)";
					*/
				}
				
				if ($database_success === FALSE) {
					$context_msg = "<b>{$lang['global_01']}{$lang['colon']} {$lang['docs_47']}</b><br>" .
									$lang['docs_65'] . ' ' . maxUploadSizeFileRespository().'MB'.$lang['period'];
					if ($super_user) {
						$context_msg .= '<br><br>' . $lang['system_config_69'];
					}
				}
			}
			break;		
		
		case 'Save Changes':
			$sql = "UPDATE redcap_docs SET docs_comment = '" . prep($_POST['docs_comment']) . "' 
			        WHERE docs_id='" . (int)$_POST['docs_id'] . "' AND project_id='".$project_id."';";
			if (db_query($sql))
			{
				// Logging
				log_event($sql,"redcap_docs","MANAGE",$_POST['docs_id'],"doc_id = ".$_POST['docs_id'],"Edit document in file repository");
			}
			$context_msg = str_replace('{fetched}', '', $context_msg_update);
			break;

		case '--  Cancel --':
		    $context_msg = str_replace('{fetched}', $fetched, $context_msg_cancel);
			break;

		case 'Delete File':
			$sql = "SELECT d.docs_id,e.doc_id,m.stored_name
			        FROM redcap_docs d
					LEFT JOIN redcap_docs_to_edocs e ON e.docs_id = d.docs_id
					LEFT JOIN redcap_edocs_metadata m ON m.doc_id = e.doc_id
					WHERE d.docs_id='".(int)$_POST['docs_id']."'
					  AND d.project_id='".$project_id."';";
			$result = db_query($sql);
			if ($result) {
				$data = db_fetch_object($result);
				db_query("DELETE FROM redcap_docs WHERE docs_id='".$data->docs_id."' AND project_id='".$project_id."';");
				if ($data->doc_id != NULL) {
					db_query("DELETE FROM redcap_edocs_metadata WHERE doc_id='".$data->doc_id."';");
					db_query("DELETE FROM redcap_docs_to_edocs WHERE docs_id='".$data->docs_id."' AND doc_id='".$data->doc_id."';");
					delete_repository_file($data->stored_name);
				}
				// Logging
				log_event($sql,"redcap_docs","MANAGE",$_POST['docs_id'],"doc_id = ".$_POST['docs_id'],"Delete document from file repository");
				$context_msg = str_replace('{fetched}', '', $context_msg_delete);
			}
			break;

	}
}

//Delete file when user clicks on red X
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
	$sql = "SELECT d.docs_id,e.doc_id,m.stored_name
			FROM redcap_docs d
			LEFT JOIN redcap_docs_to_edocs e ON e.docs_id = d.docs_id
			LEFT JOIN redcap_edocs_metadata m ON m.doc_id = e.doc_id
			WHERE d.docs_id='".(int)$_GET['delete']."'
			  AND d.project_id = $project_id";
	$result = db_query($sql);
	if ($result) {
		$data = db_fetch_object($result);
		db_query("DELETE FROM redcap_docs WHERE docs_id='".$data->docs_id."' AND project_id='".$project_id."';");
		if ($data->doc_id != NULL) {
			db_query("DELETE FROM redcap_edocs_metadata WHERE doc_id='".$data->doc_id."';");
			db_query("DELETE FROM redcap_docs_to_edocs WHERE docs_id='".$data->docs_id."' AND doc_id='".$data->doc_id."';");
			delete_repository_file($data->stored_name);
		}
		// Logging
		log_event($sql,"redcap_docs","MANAGE",$_POST['docs_id'],"doc_id = ".$_POST['docs_id'],"Delete document from file repository");
		$context_msg = str_replace('{fetched}', '', $context_msg_delete);
	}
}







################################################################################
## Setting up Recordset for Display (Edit or Add)
if (isset($_GET['id'])) {

	$hidden_edit = 1;
	$fetched = $_GET['id'];
	$file_exists = false;
	
	if (is_numeric($_GET['id'])) {
		$sql = "select 1 from redcap_docs where docs_id = $fetched";	
		$q = db_query($sql);
		if (db_num_rows($q)) {
			$file_exists = true;
		}
	}
	
	if ($file_exists) {
		$context_msg = str_replace('{fetched}', $fetched, $context_msg_edit);
	} else {
		$hidden_edit = 0;
        $context_msg = str_replace('{fetched}', $fetched, $context_msg_add);
	}
	
}




// If user-uploading is disabled for File Repository, then default to Data Export Files as default tab
if (!$file_repository_enabled && (!isset($_GET['type']) || isset($_GET['id']))) 
{
	redirect(PAGE_FULL . "?pid=$project_id&type=export");
}





renderPageTitle("<img src='".APP_PATH_IMAGES."page_white_stack.png' class='imgfix'> {$lang['app_04']}");

//Instructions at top of page
print "<p>{$lang['docs_28']}</p>";


// If file was uploaded and exceeded server limits for file size, reset some variables
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_FILES) && !isset($_POST['docs_comment']))
{
	?>
	<div class="red" style="margin:20px 0;font-weight:bold;">
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png" class="imgfix"> 
		<?php echo $lang['global_01'] ?>: <?php echo $lang['docs_49'] ?> <?php echo $lang['docs_63'] ?>
	</div>
	<?php
}


// Detect if any DAG groups exist. If so, give note below (unless user-uploading is disabled)
if ($file_repository_enabled) 
{
	$dag_groups = db_result(db_query("select count(1) from redcap_data_access_groups where project_id = $project_id"), 0);
	if ($dag_groups > 0) {
		print  "<p style='color:#800000;'>{$lang['global_02']}: {$lang['docs_50']}</p>";
	}
}

//Show context message if file is deleted or uploaded
if (isset($_POST['submit']) && !empty($errors)) {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <b>{$lang['global_01']}{$lang['colon']}</b><br/>";
	foreach ($errors as $this_error) {
		print "$this_error<br/>";
	}
	print "</div><br>";
} elseif ($_POST['submit'] == "Delete File") {
	print "<div align='center' class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> $context_msg</div><br>";
} elseif ($_POST['submit'] == "Upload File") {
	print "<div align='center' class='darkgreen'><img src='".APP_PATH_IMAGES."accept.png' class='imgfix'> $context_msg</div><br>";
} elseif ($_POST['submit'] == "Save Changes") {
	print "<div align='center' class='darkgreen'><img src='".APP_PATH_IMAGES."accept.png' class='imgfix'> $context_msg</div><br>";
} elseif (isset($_GET['delete']) && $_GET['delete'] != "") {
	print "<div align='center' class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> $context_msg</div><br>";
}


//Tabs
print '<div id="sub-nav" style="margin:0;max-width:700px;"><ul>';
if ($_GET['type'] != 'export' && !isset($_GET['id'])) {
	//Uploaded Files tab
	if ($file_repository_enabled) {
		print '<li class="active"><a style="font-size:14px;color:#393733" href="'.$_SERVER['PHP_SELF'].'?pid='.$project_id.'"><img src="'.APP_PATH_IMAGES.'group.png" class="imgfix"> '.$lang['docs_29'].'</a></li>';
	}
		print '<li><a href="'.$_SERVER['PHP_SELF'].'?pid='.$project_id.'&type=export" style="font-size:14px;color:#393733"><img src="'.APP_PATH_IMAGES.'documents_arrow.png" class="imgfix"> '.$lang['docs_30'].'</a></li>';
	if ($file_repository_enabled) {
		print '<li><a href="'.$_SERVER['PHP_SELF'].'?pid='.$project_id.'&id=" style="font-size:14px;color:#393733"><img src="'.APP_PATH_IMAGES.'attach.png" class="imgfix"> '.$lang['docs_31'].'</a></li>';
	}
} elseif (isset($_GET['type'])) {
	//Data Export Files tab
	if ($file_repository_enabled) {
		print '<li><a style="font-size:14px;color:#393733" href="'.$_SERVER['PHP_SELF'].'?pid='.$project_id.'"><img src="'.APP_PATH_IMAGES.'group.png" class="imgfix"> '.$lang['docs_29'].'</a></li>';
	}
		print '<li class="active"><a href="'.$_SERVER['PHP_SELF'].'?pid='.$project_id.'&type=export" style="font-size:14px;color:#393733"><img src="'.APP_PATH_IMAGES.'documents_arrow.png" class="imgfix"> '.$lang['docs_30'].'</a></li>';
	if ($file_repository_enabled) {
		print '<li><a href="'.$_SERVER['PHP_SELF'].'?pid='.$project_id.'&id=" style="font-size:14px;color:#393733"><img src="'.APP_PATH_IMAGES.'attach.png" class="imgfix"> '.$lang['docs_31'].'</a></li>';
	}
} else {
	//Upload New File tab
	if ($file_repository_enabled) {
		print '<li><a style="font-size:14px;color:#393733" href="'.$_SERVER['PHP_SELF'].'?pid='.$project_id.'"><img src="'.APP_PATH_IMAGES.'group.png" class="imgfix"> '.$lang['docs_29'].'</a></li>';
	}
		print '<li><a href="'.$_SERVER['PHP_SELF'].'?pid='.$project_id.'&type=export" style="font-size:14px;color:#393733"><img src="'.APP_PATH_IMAGES.'documents_arrow.png" class="imgfix"> '.$lang['docs_30'].'</a></li>';
	//Determine if editing file or uploading file
	if ($file_repository_enabled) {
		if ($_GET['id'] == '') $third_tab = '<img src="'.APP_PATH_IMAGES.'attach.png" class="imgfix">  '.$lang['docs_31']; else $third_tab = '<img src="'.APP_PATH_IMAGES.'pencil.png" class="imgfix"> '.$lang['docs_32'];
		print '<li class="active"><a style="font-size:14px;color:#393733">'.$third_tab.'</a></li>';
	}
}		
print '</ul></div><br><br><br>';

	
	
	
	
	
	
// EDITING/UPLOADING NEW FILE
if (isset($_GET['id'])) 
{	
	//Context message
	$elements[] = array('rr_type'=>'header', 'css_element_class'=>'context_msg','value'=>$context_msg);	
	//Primary Form Fields inserted here
	$elements = $elements + $elements1;
	//Finishing buttons
	$upload_button_text = ($_GET['id'] == "") ?	'Upload File' : 'Save Changes';
	$elements[] = array('rr_type'=>'submit', 'css_element_class'=>'notranslate', 'name'=>'submit', 'value'=>$upload_button_text, 'onclick'=>'if(docs_comment.value.length==0 || docs_file.value.length==0) { alert (\'Please select a file and provide a name/label\');return false; }');
	if ($record_delete_option){
		$elements[] = array('rr_type'=>'submit', 'css_element_class'=>'notranslate', 'name'=>'submit', 'value'=>'Delete File');
	}
	$elements[] = array('rr_type'=>'submit', 'css_element_class'=>'notranslate', 'name'=>'submit', 'value'=>'--  Cancel --');
	$elements[] = array('rr_type'=>'hidden', 'name'=>'hidden_edit_flag', 'value'=>$hidden_edit);
	
	// Add extra text if uploading new file
	if ($_GET['id'] == "") 
	{
		print "<p style='padding-bottom:10px;'>{$lang['docs_33']} \"$upload_button_text\" {$lang['docs_34']}</p>";
	}
	// Get data for this existing file
	else
	{
		$sql = "select docs_id, docs_comment, docs_date, docs_name, docs_size, docs_type from redcap_docs where docs_id = " . $_GET['id'];
		$q = db_query($sql);
		foreach (db_fetch_assoc($q) as $field=>$value) {
			if ($field == "docs_date") {
				$value = DateTimeRC::format_ts_from_ymd($value);
			} elseif ($field == "docs_size") {
				$value = round_up($value/1024/1024);
			}
			$element_data[$field] = $value;
		}
	}
	
	print "<div style='padding-left:40px;'>";
	
	//Render form
	form_renderer($elements,$element_data);
	
	print "</div>";
}
	
	
// Show either USER FILES or DATA EXPORT FILES
if (!isset($_GET['id'])) {
	
	//If user is in DAG, only show info from that DAG and give note of that
	if ($user_rights['group_id'] != "") {
		// Data Export Files
		if ($_GET['type'] == "export") {
			print  "<p style='color:#800000;'>{$lang['global_02']}: {$lang['docs_51']}</p>";
		}
	}
	
	## DATA EXPORT FILES (cannot view - error message)
	if ($_GET['type'] == 'export' && $user_rights['data_export_tool'] != '1') 
	{
		// If user does not have full export rights, let them know that they cannot view this tab
		print 	RCView::div(array('class'=>'yellow','style'=>'clear:both;margin-top:20px;padding:15px;'),
					RCView::img(array('src'=>'exclamation_orange.png','class'=>'imgfix')) .
					RCView::b($lang['global_03'].$lang['colon']) . " " . $lang['docs_64']
				);
		
	}
	
	## DATA EXPORT FILES
	// Query for either user uploaded files or data export files
	elseif ($_GET['type'] == 'export' && $user_rights['data_export_tool'] > 0) 
	{		
		//Filter by export type
		$limit_export = '';
		if (isset($_GET['export'])) {
			switch ($_GET['export']) {
				case "spss": $limit_export = "AND right(docs_name,4) = '.sps'"; break;
				case "sas": $limit_export = "AND right(docs_name,4) = '.sas'"; break;
				case "r": $limit_export = "AND right(docs_name,2) = '.r'"; break;
				case "stata": $limit_export = "AND right(docs_name,3) = '.do'"; break;
				case "excel": $limit_export = "AND right(docs_name,4) = '.csv'";
			}
		}
		
		//If user is in a Data Access Group, only show exported files from users within that group
		if ($user_rights['group_id'] != "") {
			//Get list of users in this group
			$group_sql = "and (";
			$q = db_query("select username from redcap_user_rights where project_id = $project_id and 
							  group_id = {$user_rights['group_id']}");
			$i = 0;
			while ($row = db_fetch_assoc($q)) {
				if ($i != 0) $group_sql .= " or ";
				$i++;
				$group_sql .= "docs_comment like '% created by {$row['username']} on %'";
			}
			$group_sql .= ")";
		} else {
			$group_sql = "";
		}
									  
		// Set the WHERE clause for the main query
		$rs_pubs_where = "where project_id = $project_id and export_file = 1 and temp = 0 $group_sql $limit_export";
		
		//Section of results into multiple pages of results by limiting to 100 per page. $begin_limit is record to begin with.
		if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
			$begin_limit = $_GET['limit'] . ",100";
		} else {
			$begin_limit = "0,100";
		}
		
		// Get "limit" for query: Obtain count of files in the last export so we can display just those for Last Export
		$rs_pubs_count = 5;
		if (!isset($_GET['export']) || (isset($_GET['export']) && $_GET['export'] == '')) 
		{
			$sql = "select count(1) from redcap_docs $rs_pubs_where and right(docs_comment, 19) like '%20__-__-__-__-__-__%' 
					group by right(docs_comment, 19) order by docs_id desc limit 1";
			$q = db_query($sql);
			if (db_num_rows($q)) {
				$rs_pubs_count = db_result($q, 0);			
			}
			$begin_limit = (is_numeric($rs_pubs_count)) ? "0,$rs_pubs_count" : "0,5";
		}
		
		// Document query
		$rs_pubs_sql = "select docs_id, project_id, docs_date, docs_name, docs_size, docs_type, docs_comment, docs_rights, export_file 
						from redcap_docs $rs_pubs_where order by docs_id desc limit $begin_limit";
		$qrs_pubs = db_query($rs_pubs_sql);
		
		if (db_num_rows($qrs_pubs) < 1) {
		
			print "<div align='center' style='padding:20px;width:100%;max-width:700px;'>
					<span class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png' class='imgfix'> {$lang['docs_35']}</span>
				   </div>";
		
		} else {
		
			$page_instructions .=  "<div style='max-width:700px;'>
									<table class='dt2' style='width:100%;'>
										<tr class='grp2'>
											<td colspan='2' style='font-family:Verdana;font-size:12px;text-align:right;font-weight:normal;'>
												{$lang['docs_36']} 
												<select name='filetypes' onchange='location.href=\"".$_SERVER['PHP_SELF']."?pid=$project_id&type=export&export=\"+this.value;'>
												<option value=''"; if ($_GET['export'] == '') $page_instructions .= " selected";
			$page_instructions .=  ">{$lang['docs_37']}</option>";
			$page_instructions .=  "<option value='all'"; if ($_GET['export'] == 'all') $page_instructions .= " selected";
			$page_instructions .=  ">{$lang['docs_38']}</option>";
			$page_instructions .=  "<option value='excel'"; if ($_GET['export'] == 'excel') $page_instructions .= " selected";
			$page_instructions .=  ">Microsoft Excel (CSV)</option>";
			$page_instructions .=  "<option value='r'"; if ($_GET['export'] == 'r') $page_instructions .= " selected";
			$page_instructions .=  ">R</option>";
			$page_instructions .=  "<option value='sas'"; if ($_GET['export'] == 'sas') $page_instructions .= " selected";
			$page_instructions .=  ">SAS</option>";
			$page_instructions .=  "<option value='spss'"; if ($_GET['export'] == 'spss') $page_instructions .= " selected";
			$page_instructions .=  ">SPSS</option>";
			$page_instructions .=  "<option value='stata'"; if ($_GET['export'] == 'stata') $page_instructions .= " selected";
			$page_instructions .=  ">Stata</option>";
			$page_instructions .=  "</select><br>{$lang['docs_39']} 
									<select name='filetypes' onchange='window.location.href=\"".PAGE_FULL."?pid=$project_id&type=export&export=".$_GET['export']."&limit=\"+this.value;'>";

			// Calculate number of pages of results for dropdown
			if (!isset($_GET['export']) || $_GET['export'] == '') {
				$num_total_files = $rs_pubs_count;
			} else {
				$sql = "select count(1)	from redcap_docs $rs_pubs_where";
				$num_total_files = db_result(db_query($sql),0);
			}
			$num_pages = ceil($num_total_files/100);
			
			//Loop to create options for "Displaying files" dropdown
			for ($i = 1; $i <= $num_pages; $i++) {
				$end_num = $i * 100;
				$begin_num = $end_num - 99;
				$value_num = $end_num - 100;
				if ($end_num > $num_total_files) $end_num = $num_total_files;
				//if ($begin_num == 1) $begin_num = "00" . $begin_num;
				$page_instructions .=  "<option value='$value_num'"; 
				if ($_GET['limit'] == $value_num) $page_instructions .= " selected";
				$page_instructions .=  ">$begin_num - $end_num</option>";
			}
									
			$page_instructions .=  "</select> 
											</td>
											<td colspan='2' style='font-family:Verdana;font-size:12px;text-align:center;width:100px;'>
												{$lang['docs_52']}
											</td>
										</tr>";
			
			//Loop through each element from query
			$export_rows = array();
			while ($rs_pubs = db_fetch_assoc($qrs_pubs)) 
			{				
				// Set docs_type
				$rs_pubs['docs_type'] = $docs_type = strtoupper(substr($rs_pubs['docs_comment'],0,strpos($rs_pubs['docs_comment']," ")));
				// Parse the comment to get username+timestamp to use as array key (it represents the unique export event)
				$comment_array = explode(' ', $rs_pubs['docs_comment']);
				// Get last and 3rd to last array values
				$last_key = max(array_keys($comment_array));
				$export_rows_key = $comment_array[$last_key-2] . "_" . $comment_array[$last_key];				
				// Add row to array
				$export_rows[$export_rows_key][] = $rs_pubs;
			}
			
			//print_array($export_rows);
					
			// Set string to use to filter out Labels CSV and NoHdrs CSV using filename when looping through files
			$search_phrasel = "_DATA_LABELS_20";
			$search_phrasel_legacy_prefix = "DATA_LABELS_".strtoupper($app_name)."_";
			$search_phrasenh = "_DATA_NOHDRS_20";
			$search_phrasenh_legacy_prefix = "DATA_".strtoupper($app_name)."_";
			
			// Loop through each row
			$oldUserTs = "";
			$i = 0;
			foreach ($export_rows as $userTs=>$export_group) 
			{
				foreach ($export_group as $export_group_key=>$rs_pubs) 
				{
					// Always ignore the CSV Raw NoHdrs file and CSV Labels file (because we'll do Labels with the Raw w/ Hdrs file)
					$isLabelsFile = false;
					if ($rs_pubs['docs_type'] == 'DATA') {
						$isLabelsFile = (strpos($rs_pubs['docs_name'], $search_phrasel_legacy_prefix) === 0 
										|| 	strpos($rs_pubs['docs_name'], $search_phrasel) !== false);
						// Don't ignore the Labels CSV file if it is the ONLY file in the group
						if (($isLabelsFile && count($export_group) > 1) 
							|| 	strpos($rs_pubs['docs_name'], $search_phrasenh_legacy_prefix) === 0 
							|| 	strpos($rs_pubs['docs_name'], $search_phrasenh) !== false ) {
							continue;
						}
						// If previous row as R, then skip
						if (count($export_group) == 2 && $export_group[0]['docs_type'] == 'R') continue;
						
					}
					
					// Increment counter for row striping
					$i++;					
					$evenOrOdd = ($i%2) == 0 ? 'even' : 'odd';
					
					//Set up display variables
					$docs_comment = $rs_pubs['docs_comment'];
					$docs_type = $rs_pubs['docs_type'];
					$docs_name = $rs_pubs['docs_name'];			
					$docs_id = $rs_pubs['docs_id'];
					//$filesize_kb = round(($rs_pubs['docs_size'))/1024,1);	
					$substr1 = strpos($docs_comment, ' created by ') + strlen(' created by ');
					$substr2 = strpos($docs_comment, ' on 20', $substr1+2);
					$docs_user = trim(substr($docs_comment, $substr1, $substr2-$substr1));
					$docs_date = $rs_pubs['docs_date'];
					//Set up timestamp display
					$docs_ts = DateTimeRC::format_ts_from_ymd(DateTimeRC::format_ts_from_int_to_ymd(str_replace("-", "", substr($docs_comment, -19))));
					
					//Check if we're beginning a new set of files when showing ALL types
					if (($_GET['export'] == 'all' || !isset($_GET['export']) || $_GET['export'] == '') && $oldUserTs != $userTs) {
						$oldUserTs = $userTs;
						$show_divider = true;
						$page_instructions .= "<tr>
							<td valign='top' colspan='3' style='border:1px solid #aaaaaa;border-top:2px solid #aaaaaa;padding:4px;background-color:#FFFFE0;font-size:11px;font-family:Verdana;font-weight:bold;'>
								<table cellpadding=1 cellspacing=0>
									<tr><td valign=top style='padding-right:5px;'>{$lang['docs_40']}</td><td valign=top><font color=#800000>$docs_ts</font></td></tr>
									<tr><td valign=top style='padding-right:5px;'>{$lang['docs_41']}</td><td valign=top> <font color=#800000>$docs_user</font></td></tr>
								</table>
							</td>
							</tr>";
					} else {
						$show_divider = false;
					}
					
					/* 
					if (count($export_group)%7 == 0 || count($export_group)%6 == 0) {
						// Old export (pre-v5.13.0) with 7 files in group (or 6 files for REALLY old exports)
						$isLegacyGroupExport = true;
					} else {
						// Newer export with only data file (label or raw) [and 1 syntax file]
						$isLegacyGroupExport = false;
					}
					*/
					
					// Set row values based on export file type
					switch ($docs_type) 
					{
						case "SPSS": 	
							$docs_header = $lang['data_export_tool_07'];
							$docs_id_csv = DataExport::getDataFileDocId($docs_type, $export_group);
							$docs_logo = "spsslogo_small.png";
							$instr = $lang['data_export_tool_08'].'<br>
									<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$("#spss_detail").toggle("fade");\'>'.$lang['data_export_tool_08b'].'</a>
									<div style="display:none;border-top:1px solid #aaa;margin-top:5px;padding-top:3px;" id="spss_detail">'.
										$lang['data_export_tool_08c'].' C:\folder\otherfolder<br><br>'.
										$lang['data_export_tool_08d'].'
										<br><font color=green>FILE HANDLE data1 NAME=\'DATA.CSV\' LRECL=10000.</font><br><br>'.
										$lang['data_export_tool_08e'].'<br>
										<font color=green>FILE HANDLE data1 NAME=\'<font color=red>C:\folder\otherfolder\</font>DATA.CSV\' LRECL=10000.</font><br><br>'.
										$lang['data_export_tool_08f'].'
									</div>';
							break;
						case "SAS": 	
							$docs_header = $lang['data_export_tool_11']; 
							$docs_id_csv = DataExport::getDataFileDocId($docs_type, $export_group);
							$docs_logo = "saslogo_small.png";
							$instr = $lang['data_export_tool_130'].'<br>
									<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$("#sas_detail").toggle("fade");\'>'.$lang['data_export_tool_08b'].'</a>
									<div style="display:none;border-top:1px solid #aaa;margin-top:5px;padding-top:3px;" id="sas_detail">
										<b>'.$lang['data_export_tool_131'].'</b><br>'.
										$lang['data_export_tool_132'].' <font color="green">/folder/subfolder/</font> (e.g., /Users/administrator/documents/)<br><br>'.
										$lang['data_export_tool_133'].'
										<br>... <font color=green>infile \'DATA.CSV\' delimiter = \',\' MISSOVER DSD lrecl=32767 firstobs=1 ;</font><br><br>'.
										$lang['data_export_tool_08e'].'<br>
										... <font color=green>infile \'<font color=red>/folder/subfolder/</font>DATA.CSV\' delimiter = \',\' MISSOVER DSD lrecl=32767 firstobs=1 ;</font><br><br>'.
										$lang['data_export_tool_134'].'
									</div>';
							break;
						case "STATA": 	
							$docs_header = $lang['data_export_tool_187'];
							$docs_id_csv = DataExport::getDataFileDocId($docs_type, $export_group);
							$docs_logo = "statalogo_small.png";	
							$instr = $lang['data_export_tool_14'];
							break;
						case "R": 		
							$docs_header = $lang['data_export_tool_09'];
							$docs_id_csv = DataExport::getDataFileDocId($docs_type, $export_group);
							$docs_logo = "rlogo_small.png";
							$instr = $lang['data_export_tool_10'];
							break;
						case "DATA":
							$docs_header = $lang['data_export_tool_172'];
							$docs_logo = "excelicon.gif";
							$instr = "{$lang['data_export_tool_118']}<br><br><i>{$lang['global_02']}{$lang['colon']} {$lang['data_export_tool_17']}</i>";
					}
					
					//Display table row
					$page_instructions .= "<tr class='$evenOrOdd'>
							<td valign='top' style='text-align:center;width:60px;padding-top:10px;border:0px;'>
								<img src='".APP_PATH_IMAGES."$docs_logo' title='".cleanHtml($docs_header)."'>
							</td>
							<td align='left' valign='top' style='font-size:11px;font-family:Verdana;padding:10px;'>
								<b>$docs_header</b>";
					//Display only when showing individual types
					if ($_GET['export'] == 'r' || $_GET['export'] == 'spss' || $_GET['export'] == 'stata' || $_GET['export'] == 'excel' || $_GET['export'] == 'sas') {
						$page_instructions .= "<br>
								<span style='font-size:10px;color:#555555;'>
								{$lang['docs_40']} <font color=#800000>$docs_ts</font>
								<br>{$lang['docs_41']} <font color=#800000>$docs_user</font>
								</span>";
					}
					//Display export instructions if showing Last Export
					if ($_GET['export'] == '' || !isset($_GET['export'])) {
						$page_instructions .= "<br>$instr";
					}
					
					//Set the CSV icon to date-shifted look if the data in these files were date shifted
					if ($rs_pubs['docs_rights'] == "DATE_SHIFT") {
						$csv_img = "download_csvdata_ds.gif";
						$csvexcel_img = "download_csvexcel_raw_ds.gif";
						$csvexcellabels_img = "download_csvexcel_labels_ds.gif";
					} else {
						$csv_img = "download_csvdata.gif";
						$csvexcel_img = "download_csvexcel_raw.gif";
						$csvexcellabels_img = "download_csvexcel_labels.gif";
					}
		
					// If Send-It is not enabled for Data Export and File Repository, then hide the link to utilize Send-It
					$sendItLinkDisplay = ($sendit_enabled == '1' || $sendit_enabled == '3') ? "" : "display:none;";
					
					if ($docs_type == "DATA") {
						//EXCEL
						if ($isLabelsFile) {
							// Label file only
							$docs_id_csv_labels = $docs_id;
							$show_csv_labels_icon = "visible";
							$show_csv_icon = "hidden";
						} else {
							// Raw file with possible Label file
							$show_csv_icon = "visible";							
							$docs_id_csv_labels = DataExport::getDataFileDocId($docs_type, $export_group, true);
							$show_csv_labels_icon = ($docs_id_csv_labels == '') ? "hidden" : "visible";
						}
						// display row
						$page_instructions .=  "</td>
												<td valign='top' style='text-align:right;width:100px;padding-top:10px;'>
													<a href='" . APP_PATH_WEBROOT . "FileRepository/file_download.php?pid=".$project_id."&id=$docs_id_csv_labels' style='visibility:$show_csv_labels_icon;text-decoration:none;'>
														<img src='".APP_PATH_IMAGES.$csvexcellabels_img."' title='{$lang['docs_58']}' alt='{$lang['docs_58']}'>
													</a> &nbsp;
													<a href='" . APP_PATH_WEBROOT . "FileRepository/file_download.php?pid=".$project_id."&id=$docs_id' style='visibility:$show_csv_icon;text-decoration:none;'>
														<img src='".APP_PATH_IMAGES.$csvexcel_img."' title='{$lang['docs_58']}' alt='{$lang['docs_58']}'>
													</a>												
													<div style='text-align:left;padding:5px 0 1px;$sendItLinkDisplay'>
														<div style='line-height:5px;'>
															<img src='".APP_PATH_IMAGES."mail_small.png' style='position: relative; top: 5px;'><a 
																href='javascript:;' style='color:#666;font-size:10px;text-decoration:underline;' onclick=\"displaySendItExportFile($docs_id);\">{$lang['docs_53']}</a>
														</div>
														<div id='sendit_$docs_id' style='display:none;padding:4px 0 4px 6px;'>
															<div style='visibility:$show_csv_labels_icon;'>
																&bull; <a href='javascript:;' onclick='popupSendIt($docs_id_csv_labels,2);' style='font-size:10px;'>{$lang['data_export_tool_120']}</a>
															</div>
															<div style='visibility:$show_csv_icon;'>
																&bull; <a href='javascript:;' onclick='popupSendIt($docs_id,2);' style='font-size:10px;'>{$lang['data_export_tool_119']}</a>
															</div>
														</div>
													</div>
												</td>
												</tr>";
					} else {
						//STATS PACKAGES
						$page_instructions .=  "<td valign='top' style='text-align:right;width:100px;padding-top:10px;'>
													<a href='" . APP_PATH_WEBROOT . "FileRepository/file_download.php?pid=".$project_id."&id=$docs_id' style='text-decoration:none;'>
														<img src='".APP_PATH_IMAGES."download_".strtolower($docs_type).".gif' title='{$lang['docs_58']}' alt='{$lang['docs_58']}'>
													</a> &nbsp; 
													<a href='" . APP_PATH_WEBROOT . "FileRepository/file_download.php?pid=".$project_id."&id=$docs_id_csv&exporttype=$docs_type' style='text-decoration:none;'>	
														<img src='".APP_PATH_IMAGES.$csv_img."' title='{$lang['docs_58']}' alt='{$lang['docs_58']}'>
													</a>";
						// Display Pathway Mapper icon for SPSS or SAS only							
						if ($docs_type == "SPSS") {
							$page_instructions .=  "<div style='padding-left:11px;text-align:left;'>
														<a href='".APP_PATH_WEBROOT."DataExport/spss_pathway_mapper.php?pid=$project_id'
														><img src='".APP_PATH_IMAGES."download_pathway_mapper.gif'></a> &nbsp; 
													</div>";
						} else if ($docs_type == "SAS") {
							$page_instructions .=  "<div style='padding-left:11px;text-align:left;'>
														<a href='".APP_PATH_WEBROOT."DataExport/sas_pathway_mapper.php?pid=$project_id'
														><img src='".APP_PATH_IMAGES."download_pathway_mapper.gif'></a> &nbsp; 
													</div>";
						}
						$page_instructions .=  "<div style='text-align:left;padding:5px 0 1px;$sendItLinkDisplay'>
													<div style='line-height:5px;'>
														<img src='".APP_PATH_IMAGES."mail_small.png' style='position: relative; top: 5px;'><a 
															href='javascript:;' style='color:#666;font-size:10px;text-decoration:underline;' onclick=\"displaySendItExportFile($docs_id);\">{$lang['docs_53']}</a>
													</div>
													<div id='sendit_$docs_id' style='display:none;padding:4px 0 4px 6px;'>
														<div>
															&bull; <a href='javascript:;' onclick='popupSendIt($docs_id,2);' style='font-size:10px;'>{$lang['docs_55']}</a>
														</div>
														<div>
															&bull; <a href='javascript:;' onclick='popupSendIt($docs_id_csv,2);' style='font-size:10px;'>{$lang['docs_54']}</a>
														</div>
													</div>
												</div>
												</td>
												</tr>";
					}
					
				}
			}
			$page_instructions .= "</table></div><br><br>";		
		}
	
	}
	
	//USER FILES
	elseif ($_GET['type'] != 'export') 
	{	
		//Build string if need to filter by file type
		if (isset($_GET['filetype']) && $_GET['filetype'] != '') {
			$filter_by_ext = "AND right(docs_name,".strlen($_GET['filetype']).") = '".$_GET['filetype']."'";
		} else {
			$filter_by_ext = '';
		}	

		//Document query
		$rs_pubs_sql = "select docs_id, project_id, docs_date, docs_name, docs_size, docs_type, docs_comment, docs_rights, export_file
						from redcap_docs WHERE project_id = $project_id AND export_file = 0 $filter_by_ext ORDER BY docs_id DESC";
		$qrs_pubs = db_query($rs_pubs_sql);
		
		if (db_num_rows($qrs_pubs) < 1) {
		
			print "<div align='center' style='padding:20px;width:100%;max-width:700px;'>
					<span class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png' class='imgfix'> {$lang['docs_42']}</span>
				   </div>";
		
		} else {
			
			$page_instructions .=  "<form method='post' action='".PAGE_FULL."?pid=$project_id'>
									<div style='max-width:700px;'>
									<table class='dt2' style='width:100%;'>
									<tr class='grp2'>
										<td style='font-family:Verdana;font-size:12px;text-align:right;font-weight:normal;'>
											{$lang['docs_43']}
											<select name='filetypes' onchange='location.href=\"".$_SERVER['PHP_SELF']."?pid=$project_id&filetype=\"+ this.value+addGoogTrans();'>
											<option value=''>{$lang['docs_44']}</option>";
											
			//Show dropdown to filter file types								
			$q = db_query("select * from redcap_docs WHERE project_id = $project_id  AND export_file = 0 ORDER BY docs_id DESC");
			$file_ext_array = array();
			while ($row = db_fetch_array($q)) {
				$file_ext_array[] = substr($row['docs_name'],strrpos($row['docs_name'],".")+1,strlen($row['docs_name']));
			}
			$file_ext_array = array_unique($file_ext_array);
			sort($file_ext_array);
			foreach ($file_ext_array as $this_ext) {			
				$page_instructions .= "<option value='$this_ext'";
				if ($_GET['filetype'] == $this_ext) $page_instructions .= " selected";
				$page_instructions .= ">$this_ext</option>";		
			}						
											
			$page_instructions .=  "</select>
										</td>
										<td colspan='2' style='font-family:Verdana;font-size:12px;text-align:center;'>
											{$lang['docs_45']}
										</td>
									</tr>";
		
		
			$i = 0;
			while ($rs_pubs = db_fetch_assoc($qrs_pubs)) {
			
				$i++;
			
				$evenOrOdd = ($i%2) == 0 ? 'even' : 'odd';
				
				$filesize_kb = round(($rs_pubs['docs_size'])/1024,1);
				$docs_comment = $rs_pubs['docs_comment'];
				$docs_name = $rs_pubs['docs_name'];
				$docs_date = DateTimeRC::format_user_datetime($rs_pubs['docs_date'], 'Y-M-D_24');
				$docs_id = $rs_pubs['docs_id'];			
				$file_ext = strtolower(substr($rs_pubs['docs_name'],strrpos($rs_pubs['docs_name'],".")+1,strlen($rs_pubs['docs_name'])));
				switch ($file_ext) {
					case "htm":	
					case "html": 
						$icon = "html.png"; break;
					case "csv":	
						$icon = "csv.gif"; break;
					case "xls":	
					case "xlsx":
						$icon = "xls.gif"; break;
					case "doc":			
					case "docx":
						$icon = "doc.gif"; break;
					case "pdf":	
						$icon = "pdf.gif"; break;
					case "ppt":
					case "pptx":
						$icon = "ppt.gif"; break;
					case "jpg": 
					case "gif": 
					case "png": 
					case "bmp": 
						$icon = "picture.png"; break;
					case "zip": 
						$icon = "zip.png"; break;				
					default:	
						$icon = "txt.gif"; break;		
				}
				
				$page_instructions .= "<tr class='$evenOrOdd'>
						<td align='left' valign='top' style='font-size:11px;font-family:Verdana;padding:10px;padding-left:2.5em;text-indent:-1.5em;margin-left:2em;'>
							<img src='".APP_PATH_IMAGES.$icon."' class='imgfix'> 
							<b>$docs_comment</b><br>
							<span style='font-size:10px;color:#555555;'>
							{$lang['docs_19']} <b>$docs_name</b><br>{$lang['docs_56']} $docs_date<br>{$lang['docs_57']} $filesize_kb KB
							</span>
						</td>
						<td valign='top' style='width:42px;padding-top:10px;text-align:center;'>
							<a href='" . APP_PATH_WEBROOT . "FileRepository/file_download.php?pid=".$project_id."&id=$docs_id ' style='text-decoration:none;'>
								<img src='".APP_PATH_IMAGES."download_file.gif' title='{$lang['docs_58']}' alt='{$lang['docs_58']}'></a>
						</td>
						<td valign='top' style='width:18px;padding-top:10px;'>
							<a href='".PAGE_FULL."?pid=$project_id&id=$docs_id'>
								<img src='".APP_PATH_IMAGES."pencil.png' title='{$lang['global_27']}' alt='{$lang['global_27']}'></a>
							<p><a href='javascript:;'><img src='".APP_PATH_IMAGES."cross.png' title='{$lang['global_19']}' alt='{$lang['global_19']}' onclick='delete_doc($docs_id);return false;'></a></p>
							".(($sendit_enabled == 1 || $sendit_enabled == 3) ? "<p><a onclick=\"popupSendIt($docs_id,2);\" href=\"javascript:;\"><img src=\"".APP_PATH_IMAGES."mail_arrow.png\" title=\"{$lang['docs_61']}\" alt=\"{$lang['docs_61']}\" /></a></p>" : "")."
						</td>
						</tr>";
						
			}
			$page_instructions .= "</table></div></form><br><br>";
	
		}
		
	}
	
	
	print $page_instructions;
}

################################################################################
##HTML Closeout Information
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';


function delete_repository_file($file) 
{
	global $edoc_storage_option,$wdc,$webdav_path;
	if ($edoc_storage_option == '1') {
		// Webdav
		$wdc->delete($webdav_path . $file);
	} elseif ($edoc_storage_option == '2') {
		// S3
		global $amazon_s3_key, $amazon_s3_secret, $amazon_s3_bucket;
		$s3 = new S3($amazon_s3_key, $amazon_s3_secret, SSL);
		$s3->deleteObject($amazon_s3_bucket, $file);
	} else {
		// Local
		@unlink(EDOC_PATH . $file);
	}
}
