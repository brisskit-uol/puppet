<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/


/**
 * UserRights Class
 * Contains methods used with regard to user privileges
 */
class UserRights
{
	
	// Map pages to user_rights table values to determine rights for a given page (e.g., PAGE=>field from user_rights table)
	private $page_rights = array(	
		// Export & Reports
		"DataExport/data_export_tool.php"=>"data_export_tool",
		"DataExport/data_export_csv.php"=>"data_export_tool",
		"DataExport/file_export_zip.php"=>"data_export_tool",
		"DataExport/data_export_ajax.php"=>"data_export_tool",
		"DataExport/report_order_ajax.php"=>"reports",
		"DataExport/report_edit_ajax.php"=>"reports",
		"DataExport/report_delete_ajax.php"=>"reports",
		"DataExport/report_user_access_list.php"=>"reports",
		"DataExport/report_copy_ajax.php"=>"reports",
		"DataExport/report_filter_ajax.php"=>"reports",
		"Reports/report.php"=>"reports",
		"Reports/report_builder.php"=>"reports",
		"Reports/report_export.php"=>"reports",
		// Import
		"DataImport/index.php"=>"data_import_tool",
		"DataImport/import_tool.php"=>"data_import_tool",
		// Data Comparison Tool
		"DataComparisonTool/index.php"=>"data_comparison_tool",
		// Logging
		"Logging/index.php"=>"data_logging",
		"Logging/csv_export.php"=>"data_logging",
		// File Repository
		"FileRepository/index.php"=>"file_repository",
		// User Rights
		"UserRights/index.php"=>"user_rights",
		"UserRights/search_user.php"=>"user_rights",
		"UserRights/assign_user.php"=>"user_rights",
		"UserRights/edit_user.php"=>"user_rights",
		// DAGs
		"DataAccessGroups/index.php"=>"data_access_groups",
		// Graphical & Stats
		"Graphical/index.php"=>"graphical",
		"Graphical/pdf.php"=>"graphical",
		"DataExport/plot_chart.php"=>"graphical",
		"DataExport/stats_highlowmiss.php"=>"graphical",
		// Calendar
		"Calendar/index.php"=>"calendar",
		// Locking records
		"Locking/locking_customization.php"=>"lock_record_customize",
		"Locking/esign_locking_management.php"=>"lock_record",
		// DTS
		"DTS/index.php"=>"dts",
		// Invite survey participants
		"Surveys/add_participants.php"=>"participants",
		"Surveys/invite_participants.php"=>"participants",
		"Surveys/delete_participant.php"=>"participants",
		"Surveys/edit_participant.php"=>"participants",
		"Surveys/participant_export.php"=>"participants",
		"Surveys/shorturl.php"=>"participants",
		"Surveys/participant_list.php"=>"participants",
		"Surveys/participant_list_enable.php"=>"participants",
		"Surveys/view_sent_email.php"=>"participants",
		"Surveys/get_access_code.php"=>"participants",
		"Surveys/invite_participant_popup.php"=>"participants",
		// Data Quality
		"DataQuality/execute_ajax.php"=>"data_quality_execute",
		"DataQuality/edit_rule_ajax.php"=>"data_quality_design",
		// Randomization
		"Randomization/index.php"=>"random_setup",
		"Randomization/upload_allocation_file.php"=>"random_setup",
		"Randomization/download_allocation_file.php"=>"random_setup",
		"Randomization/download_allocation_file_template.php"=>"random_setup",
		"Randomization/check_randomization_field_data.php"=>"random_setup",
		"Randomization/delete_allocation_file.php"=>"random_setup",
		"Randomization/save_randomization_setup.php"=>"random_setup",
		"Randomization/dashboard.php"=>"random_dashboard",
		"Randomization/dashboard_all.php"=>"random_dashboard",
		"Randomization/randomize_record.php"=>"random_perform",
		// Setup & Design
		"ProjectGeneral/copy_project_form.php"=>"design",
		"ProjectGeneral/change_project_status.php"=>"design",
		"Design/define_events.php"=>"design",
		"Design/designate_forms.php"=>"design",
		"Design/data_dictionary_upload.php"=>"design",
		"Design/data_dictionary_download.php"=>"design",
		"ProjectGeneral/edit_project_settings.php"=>"design",
		"ProjectGeneral/modify_project_setting_ajax.php"=>"design",
		"ProjectGeneral/delete_project.php"=>"design",
		"Design/delete_form.php"=>"design",
		"ProjectGeneral/erase_project_data.php"=>"design",
		"ProjectSetup/other_functionality.php"=>"design",
		"ProjectSetup/project_revision_history.php"=>"design",
		"IdentifierCheck/index.php"=>"design",
		"Design/online_designer.php"=>"design",
		"SharedLibrary/index.php"=>"design",
		"SharedLibrary/receiver.php"=>"design",
		"ProjectSetup/checkmark_ajax.php"=>"design",
		"Surveys/edit_info.php"=>"design",
		"Surveys/create_survey.php"=>"design",
		"Surveys/survey_online.php"=>"design",
		"Surveys/delete_survey.php"=>"design",
		"Design/draft_mode_review.php"=>"design",
		"Design/draft_mode_enter.php"=>"design",
		"Design/draft_mode_notified.php"=>"design",
		"Design/draft_mode_cancel.php"=>"design",		
		"ExternalLinks/index.php"=>"design",		
		"ExternalLinks/edit_resource_ajax.php"=>"design",
		"ExternalLinks/save_resource_users_ajax.php"=>"design",	
		"Design/calculation_equation_validate.php"=>"design",	
		"Design/branching_logic_builder.php"=>"design",
		"Design/survey_login_setup.php"=>"design",
		"Design/existing_choices.php"=>"design",
		"Surveys/automated_invitations_setup.php"=>"design",
		"Surveys/survey_queue_setup.php"=>"design",
		// Dynamic Data Pull (DDP)
		"DynamicDataPull/setup.php"=>"realtime_webservice_mapping",
		"DynamicDataPull/fetch.php"=>"realtime_webservice_adjudicate",
		"DynamicDataPull/save.php"=>"realtime_webservice_adjudicate",
		"DynamicDataPull/exclude.php"=>"realtime_webservice_adjudicate",
		"DynamicDataPull/purge_cache.php"=>"design"
		// Mobile App page
		// isDev() UNCOMMENT THIS ,"MobileApp/index.php"=>"mobile_app"
	);
	
	// Double Data Entry (only): DDE Person will have no rights to certain pages that display data.
	// List the restricted pages in an array
	private $pagesRestrictedDDE = array(
		"Calendar/index.php", "DataExport/data_export_tool.php", "DataImport/index.php", 
		"DataComparisonTool/index.php", "Logging/index.php", "FileRepository/index.php", "DataQuality/field_comment_log.php", 
		"Locking/esign_locking_management.php", "Graphical/index.php", "DataQuality/index.php", "Reports/report.php"
	);
	

	/**
	 * Set SUPER USER privileges in $user_rights array. Returns true always.
	 */
	private function getSuperUserPrivileges($this_project_id) 
	{
		global $data_resolution_enabled, $Proj, $DDP, $mobile_app_enabled;
		// Manually set $user_rights array
		$user_rights = array('username'=>USERID, 'expiration'=>'', 'group_id'=>'', 'role_id'=>'',
							 'lock_record'=>2, 'lock_record_multiform'=>1, 'lock_record_customize'=>1,
							 'data_export_tool'=>1, 'data_import_tool'=>1, 'data_comparison_tool'=>1, 'data_logging'=>1, 'file_repository'=>1,
							 'user_rights'=>1, 'data_access_groups'=>1, 'design'=>1, 'calendar'=>1, 'reports'=>1, 'graphical'=>1, 
							 'double_data'=>0, 'record_create'=>1, 'record_rename'=>1, 'record_delete'=>1, 'api_token'=>'', 'dts'=>1,
							 'participants'=>1, 'data_quality_design'=>1, 'data_quality_execute'=>1, 
							 'data_quality_resolution'=>($data_resolution_enabled == '2' ? 3 : 0), 
							 'api_export'=>1, 'api_import'=>1, 'mobile_app'=>((isDev(true) && $mobile_app_enabled && $api_enabled) ? 1 : 0), 
							 'mobile_app_download_data'=>((isDev(true) && $mobile_app_enabled && $api_enabled) ? 1 : 0), 
							 'random_setup'=>1, 'random_dashboard'=>1, 'random_perform'=>1,
							 'realtime_webservice_mapping'=>(is_object($DDP) && $DDP->isEnabledInSystem() && $DDP->isEnabledInProject()), 
							 'realtime_webservice_adjudicate'=>(is_object($DDP) && $DDP->isEnabledInSystem() && $DDP->isEnabledInProject())
							);
		
		// Set form-level rights
		if ($this_project_id != PROJECT_ID) {
			// Parent/child: If accessing a parent via a child project, then get form-level rights for parent
			$sql = "select distinct m.form_name, s.survey_id from redcap_metadata m left outer join redcap_surveys s 
					on m.form_name = s.form_name and s.project_id = m.project_id where m.project_id = $this_project_id 
					order by m.field_order";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				// If this form is used as a survey, give super user level 3 (survey response editing), else give level 1 for form-level edit rights
				$user_rights['forms'][$row['form_name']] = (!empty($row['survey_id'])) ? '3' : '1';
			}
		} else {
			// Normal project
			foreach ($Proj->forms as $this_form=>$attr) {
				// If this form is used as a survey, give super user level 3 (survey response editing), else give level 1 for form-level edit rights
				$user_rights['forms'][$this_form] = (isset($attr['survey_id'])) ? '3' : '1';
			}
		}
		
		// Put user_rights into global scope
		$GLOBALS['user_rights'] = $user_rights;

		// Return as true
		return true;
	}

	/**
	 * GET USER PRIVILEGES
	 * 
	 */
	public function getPrivileges($project_id=null, $userid=null) 
	{
		// Put rights in array
		$user_rights = array();
		// Set subquery
		$sqlsub = "";
		if ($project_id != null || $userid != null) {
			$sqlsub = "where";
			if ($project_id != null) {
				$sqlsub .= " r.project_id = $project_id";
			}
			if ($project_id != null && $userid != null) {
				$sqlsub .= " and";
			}
			if ($userid != null) {
				$sqlsub .= " r.username = '" . prep($userid) . "'";
			}
		}
		// Check if a user for this project
		$sql = "select r.*, u.* from redcap_user_rights r left outer join redcap_user_roles u 
				on r.role_id = u.role_id $sqlsub order by r.project_id, r.username";
		$q = db_query($sql);
		// Set $user_rights array, which will carry all rights for current user.
		while ($row = db_fetch_array($q, MYSQLI_NUM)) 
		{
			// Get current project_id and user to use as array keys
			$this_project_id = $row[0];
			$this_user = strtolower($row[1]); // Deal with case-sentivity issues
			// Loop through fields using numerical indexes so we don't overwrite user values with NULLs if not in a role.
			foreach ($row as $this_field_num=>$this_value) {
				// Get name of field
				$this_field = db_field_name($q, $this_field_num);
				// If we hit the project_id again (from user_roles table) and it is null, then stop here so we don't overwrite 
				// users values with NULLs since they are not in a role.
				if (isset($user_rights[$this_project_id][$this_user][$this_field]) && $user_rights[$this_project_id][$this_user][$this_field] != null && $this_value == null) continue;
				// Make sure username is lower case, for consistency
				if ($this_field == 'username') $this_value = strtolower($this_value);
				// Add value to array
				$user_rights[$this_project_id][$this_user][$this_field] = $this_value;
			}
		}
		// Return array
		return $user_rights;
	}

	/**
	 * CHECK USER PRIVILEGES IN A GIVEN PROJECT
	 * Checks if user has rights to see this page
	 */
	public function checkPrivileges($this_app_name) 
	{
		global $Proj, $data_resolution_enabled, $lang;
		
		// Initialize $user_rights as global variable as array
		global $user_rights;
		$user_rights = array();
		
		// If accessing a parent via a child project, then replace project_id with parent project_id
		if ($this_app_name != APP_NAME) {
			$this_project_id = db_result(db_query("select project_id from redcap_projects where project_name = '$this_app_name'"), 0);
		} else {
			$this_project_id = PROJECT_ID;
		}
		
		// If a SUPER USER, then manually set rights to full/max for all things
		if (SUPER_USER) {
			return $this->getSuperUserPrivileges($this_project_id);
		}
		
		## NORMAL USERS
		// Check if a user for this project
		$user_rights_proj_user = $this->getPrivileges($this_project_id, USERID);
		$user_rights = $user_rights_proj_user[$this_project_id][strtolower(USERID)];
		unset($user_rights_proj_user);
		// Kick out if not a user and not a Super User
		if (count($user_rights) < 1) {	
			//Still show menu if a user from a child/linked project
			$GLOBALS['no_access'] = (isset($_GET['child']) ? 0 : 1);
			return false;
		}	
		
		// Check user's expiration date (if exists)
		if ($user_rights['expiration'] != "" && $user_rights['expiration'] <= TODAY) 
		{
			$GLOBALS['no_access'] = 1;
			// Instead of returning 'false', return '2' specifically so we can note to user that the password has expired
			return '2';
		}
		
		// Data resolution workflow: disable rights if module is disabled
		if ($data_resolution_enabled != '2') $user_rights['data_quality_resolution'] = '0';
		
		// SET FORM-LEVEL RIGHTS: Loop through data entry listings and add each form as a new sub-array element
		$this->setFormLevelPrivileges($this_project_id);
		
		// Remove array elements no longer needed
		unset($user_rights['data_entry'], $user_rights['project_id']);
		
		// Chec page-level privileges: Return true if has access to page, else false.
		return $this->checkPageLevelPrivileges();
	}		
	
	
	/**
	 * OBTAIN USER RIGHTS INFORMATION FOR ALL USERS IN THIS PROJECT
	 * Also includes users' first and last name and email address
	 * Return array with username as key (sorted by username)
	 */
	public static function getRightsAllUsers($enableDagLimiting=true) 
	{
		global $Proj, $lang, $user_rights;
		// Pull all user/role info for this project
		$users = array();
		$group_sql = ($enableDagLimiting && $user_rights['group_id'] != "") ? "and u.group_id = '".$user_rights['group_id']."'" : "";
		$sql = "select u.*, i.user_firstname, i.user_lastname, trim(concat(i.user_firstname, ' ', i.user_lastname)) as user_fullname 
				from redcap_user_rights u left outer join redcap_user_information i on i.username = u.username 
				where u.project_id = " . PROJECT_ID . " $group_sql order by u.username";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Set username so we can set as key and remove from array values
			$username = $row['username'];
			unset($row['username']);
			// Add to array
			$users[$username] = $row;
		}
		// Return array
		return $users;
	}
	
	
	/**
	 * OBTAIN ALL USER ROLES INFORMATION FOR THIS PROJECT (INCLUDES SYSTEM-LEVEL ROLES)
	 * Return array with role_id as key (sorted with project-level roles first, then system-level roles)
	 */
	public static function getRoles() 
	{
		// Pull all user/role info for this project
		$roles = array();
		$sql = "select * from redcap_user_roles where project_id = " . PROJECT_ID . "
				order by project_id desc, role_name";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Set role_id so we can set as key and remove from array values
			$role_id = $row['role_id'];
			unset($row['role_id']);
			// Add to array
			$roles[$role_id] = $row;
		}
		// Return array
		return $roles;
	}
	
	
	/**
	 * SET FORM-LEVEL PRIVILEGES
	 * Loop through data entry listings and add each form as a new sub-array element
	 * Does not return anything
	 */
	public function setFormLevelPrivileges($this_project_id) 
	{
		global $Proj, $user_rights;
		
		// User is NOT in a system-level role (i.e. user is either not in a role OR is in project-level role)
		$allForms = explode("][", substr(trim($user_rights['data_entry']), 1, -1));
		foreach ($allForms as $forminfo) 
		{
			list($this_form, $this_form_rights) = explode(",", $forminfo, 2);
			$user_rights['forms'][$this_form] = $this_form_rights;
		}
		
		// AUTO FIX FORM-LEVEL RIGHTS: Double check to make sure that the form-level rights are all there
		if ($this_project_id == PROJECT_ID) {
			$this->autoFixFormLevelPrivileges($this_project_id);
		}
	}
	
	
	/**
	 * AUTO FIX FORM-LEVEL PRIVILEGES (IF NEEDED)
	 * Double check to make sure that the form-level rights are all there (old bug would sometimes cause 
	 * them to go missing, thus disrupting things).
	 * Does not return anything
	 */
	private function autoFixFormLevelPrivileges($this_project_id) 
	{
		global $Proj, $user_rights;
		// Loop through all forms and check user rights for each
		foreach (array_keys($Proj->forms) as $this_form)
		{
			if (!isset($user_rights['forms'][$this_form])) {
				// Add to user_rights table (give user Full Edit rights to the form as default, if missing)
				if ($user_rights['role_id'] == '') {
					$sql = "update redcap_user_rights set data_entry = concat(data_entry,'[$this_form,1]') 
							where project_id = $this_project_id and username = '" . USERID . "'";
				} else {
					$sql = "update redcap_user_roles set data_entry = concat(data_entry,'[$this_form,1]') 
							where role_id = ".$user_rights['role_id'];
				}
				$q = db_query($sql);
				if (db_affected_rows() < 1) {
					// Must have a NULL as data_entry value, so fix it
					if ($user_rights['role_id'] == '') {
						$sql = "update redcap_user_rights set data_entry = '[$this_form,1]' 
								where project_id = $this_project_id and username = '" . USERID . "'";
					} else {
						$sql = "update redcap_user_roles set data_entry = '[$this_form,1]' 
								where role_id = ".$user_rights['role_id'];
					}
					$q = db_query($sql);
				}
				// Also add to $user_rights array
				$user_rights['forms'][$this_form] = '1';
			}
		}
	}
	

	/**
	 * CHECK A USER'S PAGE-LEVEL USER PRIVILEGES
	 * Return true if they have access to the current page, else return false if they do not.
	 */
	private function checkPageLevelPrivileges() 
	{
		global $user_rights, $double_data_entry, $Proj;
		
		// Check Data Entry page rights (edit/read-only/none), if we're on that page
		if (PAGE == 'DataEntry/index.php' || PAGE == 'Mobile/data_entry.php') 
		{
			// If 'page' is not a valid form, then redirect to home page
			if (isset($_GET['page']) && !isset($Proj->forms[$_GET['page']])) {
				redirect(APP_PATH_WEBROOT . "index.php?pid=" . PROJECT_ID);
			}
			// If user does not have rights to this form, then return false
			if (!isset($user_rights['forms'][$_GET['page']])) {
				return false;
			}
			// If user has no access to form, kick out; otherwise set as full access or disabled
			if (isset($user_rights['forms'][$_GET['page']])) {
				return ($user_rights['forms'][$_GET['page']] != "0");
			}	
		}		
		
		// DDE Person will have no rights to certain pages that display data 
		if ($double_data_entry && $user_rights['double_data'] != 0 && in_array(PAGE, $this->pagesRestrictedDDE)) { 
			return false; 
		}
		
		// Determine if user has rights to current page
		if (isset($this->page_rights[PAGE]) && isset($user_rights[$this->page_rights[PAGE]])) 
		{
			// Does user have access to this page (>0)?
			return ($user_rights[$this->page_rights[PAGE]] > 0);
		}
		
		// If you got here, then you're on a page not dictated by rights in the $user_rights array, so allow access
		return true;
	}
	

	/**
	 * RENDER DATA ACCESS GROUPS TABLE
	 * Return html for table to be displayed
	 */
	public static function renderDataAccessGroupsTable() 
	{
		global  $user_rights, $lang, $Proj, $table_pk;
		
		// Add DAGs to array
		$groups = $Proj->getGroups();
		
		// Display table (if at least one DAG exists)
		
		## DAG RECORD COUNT
		// Determine which records are in which group
		$recordsInDags = array();
		$recordDag = array();
		$sql = "select record, field_name, value from redcap_data where project_id = ".PROJECT_ID." 
				and field_name in ('$table_pk', '__GROUPID__') group by record, field_name 
				order by abs(record), record, field_name desc";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			if (!isset($recordDag[$row['record']]) && $row['field_name'] != '__GROUPID__') {
				$recordDag[$row['record']] = 0;
			} elseif ($row['field_name'] == '__GROUPID__') {
				$recordDag[$row['record']] = (isset($groups[$row['value']]) ? $row['value'] : 0);
			}
		}
		// Get count of all records in each group
		foreach ($recordDag as $record=>$group_id) 
		{
			if (!isset($recordsInDags[$group_id])) {
				$recordsInDags[$group_id] = 1;
			} else {
				$recordsInDags[$group_id]++;
			}
		}
		unset($recordDag);
		
		// Get array of project users
		$projectUsers = User::getProjectUsernames(array(), true);
					
		// Get array of group users with first/last names appended
		$groupUsers = array();
		foreach ($Proj->getGroupUsers(null, true) as $this_group_id=>$these_users) {
			foreach ($these_users as $this_user) {
				// Put username+first/last in individual's group
				$groupUsers[$this_group_id][] = $projectUsers[$this_user];
			}
		}
		
		// Now remove current user from $projectUsers so they don't get added to the Select User drop-down
		unset($projectUsers[USERID]);
				
		// Set html before the table
		$html = RCView::div(array('style'=>'margin:20px 0;font-family:arial;font-size:12px;font-weight:normal;padding:10px;border:1px solid #ccc;background-color:#eee;max-width:680px;'),
					// Create new DAG
					RCView::div(array('style'=>'color:#444;'), 
						RCView::span(array('style'=>'font-weight:bold;font-size:13px;color:#000;margin-right:5px;'), $lang['rights_182']) . 
						" " .$lang['rights_183']
					) .
					RCView::div(array('style'=>'margin:8px 0 0 29px;'),
						RCView::img(array('src'=>'group_add.png', 'class'=>'imgfix')) .
						RCView::text(array('size'=>30, 'maxlength'=>100, 'id'=>'new_group', 'class'=>'x-form-text x-form-field', 
							'style'=>'color:#999;margin-left:4px;',
							'onclick'=>"if(this.value=='".cleanHtml($lang['rights_179'])."'){this.value='';this.style.color='#000';}", 
							'onfocus'=>"if(this.value=='".cleanHtml($lang['rights_179'])."'){this.value='';this.style.color='#000';}", 
							'onblur'=>"if(this.value==''){this.value='".cleanHtml($lang['rights_179'])."';this.style.color='#999';}", 
							'onkeydown'=>"if(event.keyCode==13) add_group();",
							'value'=>$lang['rights_179']
						)) .
						// Add Group button
						RCView::button(array('id'=>'new_group_button', 'class'=>'jqbuttonmed', 'onclick'=>'add_group();'), $lang['rights_180']) .
						// Hidden progress img
						RCView::span(array('id'=>'progress_img', 'style'=>'visibility:hidden;'), 
							RCView::img(array('src'=>'progress_circle.gif', 'class'=>'imgfix'))
						)
					) .
					// Assign user to DAG
					RCView::div(array('style'=>'color:#444;margin-top:20px;'), 
						RCView::span(array('style'=>'font-weight:bold;font-size:13px;color:#000;margin-right:5px;'), $lang['data_access_groups_ajax_32']) . 
						" " .$lang['data_access_groups_ajax_36']
					) .
					RCView::div(array('style'=>'margin:8px 0 0 29px;'),
						RCView::img(array('src'=>'user2.png', 'class'=>'imgfix')) . '&nbsp;' .
						$lang['data_access_groups_ajax_12'] .
						// Drop-down of users (do NOT display users that are in a role because that would be confusing since their role's DAG assignment overrides their individual DAG assignment)
						RCView::select(array('id'=>'group_users', 'onchange'=>'select_group(this.value);', 'class'=>'x-form-text x-form-field', 'style'=>'margin:0 5px 0 7px;height:22px;padding-right:0;'), 
							array(''=>"-- {$lang['data_access_groups_ajax_13']} --")+$projectUsers, '') .
						$lang['data_access_groups_ajax_14'] .
						RCView::select(array('id'=>'groups', 'class'=>'x-form-text x-form-field', 'style'=>'margin:0 10px 0 6px;height:22px;padding-right:0;'), 
							(array(''=>"[{$lang['data_access_groups_ajax_16']}]") + $groups), '') .
						RCView::button(array('id'=>'user_group_button', 'class'=>'jqbuttonmed', 'onclick'=>"add_user_to_group();"), $lang['rights_181']) .
						// Hidden progress img
						RCView::span(array('id'=>'progress_img_user', 'style'=>'visibility:hidden;'), 
							RCView::img(array('src'=>'progress_circle.gif', 'class'=>'imgfix'))
						)
					)
				);
		
		// Set table hdrs
		$hdrs = array(
			array(170, 	RCView::div(array('class'=>'wrap','style'=>'font-size:13px;font-weight:bold;'), 
							$lang['global_22'])),
			array(160, 	RCView::div(array('class'=>'wrap','style'=>'font-weight:bold;'), 
							$lang['data_access_groups_ajax_08']
						)),
			array(65, RCView::span(array('class'=>'wrap'), $lang['data_access_groups_ajax_25']), 'center', 'int'),
			array(120, RCView::span(array('class'=>'wrap'), 
				"{$lang['data_access_groups_ajax_18']}
				<a href='javascript:;' onclick=\"alert('".cleanHtml($lang['data_access_groups_ajax_19'])."');\"><img title=\"".cleanHtml2($lang['form_renderer_02'])."\" src='".APP_PATH_IMAGES."help.png' class='imgfix'></a><br>
				{$lang['define_events_66']}")),
			array(40, RCView::span(array('class'=>'wrap'), $lang['data_access_groups_ajax_09']), 'center')
		);
		
		// Loop through each group and render as row
		$rows = array();
		foreach ($groups as $group_id=>$group_name)
		{
			// Set values for row
			$rows[] = array(
				RCView::span(array('id'=>"gid_{$group_id}", 'class'=>'wrap editText', 'title'=>$lang['data_access_groups_06'], 'style'=>'cursor:pointer;cursor:hand;display:block;color:#000066;font-weight:bold;font-size:12px;'), $group_name),
				RCView::div(array('class'=>'wrap'), "<div>".implode(",</div><div>", $groupUsers[$group_id])."</div>"),
				(isset($recordsInDags[$group_id]) ? $recordsInDags[$group_id] : 0),
				RCView::span(array('id'=>"ugid_{$group_id}", 'class'=>'wrap', 'style'=>'color:#777;'), $Proj->getUniqueGroupNames($group_id)),
				RCView::a(array('href'=>'javascript:;'), 
					RCView::img(array('src'=>'cross.png', 'class'=>'imgfix2', 'onclick'=>"del_msg('$group_id','".cleanHtml($group_name)."')"))
				)
			);
		}
		// Add last row of unassigned users
		$rows[] = array(
			RCView::span(array('style'=>'color:#800000;font-size:12px;'), $lang['data_access_groups_ajax_24']),
			RCView::div(array('class'=>'wrap'), "<div>".implode(",</div><div>", $groupUsers[0]) . (empty($groupUsers[0]) ? "" : RCView::div(array('style'=>'color:#C00000;'), $lang['data_access_groups_ajax_26']))."</div>"),
			$recordsInDags[0],
			"",
			""
		);
		
		// Return the html for displaying the table
		return $html . renderGrid("dags_table", $title, 616, "auto", $hdrs, $rows, true, true, false);
	}
	

	/**
	 * RENDER COMPREHENSIVE USER RIGHTS/ROLES TABLE
	 * Return true if they have access to the current page, else return false if they do not.
	 */
	public static function renderUserRightsRolesTable() 
	{
		global  $user_rights, $lang, $Proj, $double_data_entry, $dts_enabled_global, $dts_enabled, $mobile_app_enabled,
				$api_enabled, $randomization, $enable_plotting, $data_resolution_enabled, $DDP;
				
		// Check if DAGs exist and retrieve as array
		$dags = $Proj->getGroups();	
		
		// Set image variables
		$imgYes = RCView::img(array('src' => 'tick.png'));
		$imgNo = RCView::img(array('src' => 'cross.png'));
		$imgShield = RCView::img(array('src' => 'tick_shield.png'));
		
		// Set default column width in table
		$defaultColWidth = 65;
		
		// Set up array of all possible headers for the table (some columns will be hidden depending on project or system settings)
		$rightsHdrs = array(
			'role_name' => array('hdr' => RCView::span(array('style'=>'font-weight:bold;font-size:13px;'), $lang['rights_148']).RCView::div(array('style'=>'padding-top:3px;color:#888;'), $lang['rights_206']), 'enabled' => true, 'width'=>150, 'align'=>'left'),
			'username' => array('hdr' => RCView::span(array('style'=>'font-weight:bold;font-size:13px;'), $lang['global_11'])." ".$lang['rights_150'].RCView::div(array('style'=>'padding-top:3px;color:#888;'), $lang['rights_174']), 'enabled' => true, 'width'=>250, 'align'=>'left'),
			'expiration' => array('hdr' => RCView::span(array('style'=>'font-weight:bold;font-size:12px;'), $lang['rights_95']).RCView::div(array('style'=>'padding-top:3px;color:#888;'), $lang['rights_209']), 'enabled' => true, 'width'=>80),
			'group_id' => array('hdr' => RCView::span(array('style'=>'font-weight:bold;font-size:12px;'), $lang['global_78']).
				($user_rights['group_id'] != '' ? '' : RCView::div(array('style'=>'padding-top:3px;color:#888;'), $lang['rights_210'])), 
				'enabled' => !empty($dags), 'width'=>130),
			'design' => array('hdr' => RCView::b($lang['rights_135']), 'enabled' => true, 'width'=>60),
			'user_rights' => array('hdr' => RCView::b($lang['app_05']), 'enabled' => true, 'width'=>40),
			'data_access_groups' => array('hdr' => RCView::b($lang['global_22']), 'enabled' => true),
			'data_export_tool' => array('hdr' => RCView::b($lang['app_03']), 'enabled' => true, 'width'=>75),
			'reports' => array('hdr' => RCView::b($lang['rights_96']), 'enabled' => true),
			'graphical' => array('hdr' => RCView::b($lang['app_13']), 'enabled' => $enable_plotting > 0),
			'participants' => array('hdr' => RCView::b($lang['app_22']), 'enabled' => !empty($Proj->surveys), 'width'=>65),
			'calendar' => array('hdr' => RCView::b($lang['app_08']), 'enabled' => true, 'width'=>50),
			'data_import_tool' => array('hdr' => RCView::b($lang['app_01']), 'enabled' => true, 'width'=>60),
			'data_comparison_tool' => array('hdr' => RCView::b($lang['app_02']), 'enabled' => true, 'width'=>70),
			'data_logging' => array('hdr' => RCView::b($lang['app_07']), 'enabled' => true, 'width'=>45),
			'file_repository' => array('hdr' => RCView::b($lang['app_04']), 'enabled' => true, 'width'=>60),
			'double_data' => array('hdr' => RCView::b($lang['rights_50']), 'enabled' => $double_data_entry),
			'lock_record_customize' => array('hdr' => RCView::b($lang['app_11']), 'enabled' => true, 'width'=>80),
			'lock_record' => array('hdr' => RCView::b($lang['rights_97']), 'enabled' => true, 'width'=>70),
			'randomization' => array('hdr' => RCView::b($lang['app_21']), 'enabled' => $randomization, 'width'=>80),
			'data_quality_design' => array('hdr' => RCView::b($lang['dataqueries_38']), 'enabled' => true),
			'data_quality_execute' => array('hdr' => RCView::b($lang['dataqueries_39']), 'enabled' => true),
			'data_quality_resolution' => array('hdr' => RCView::b($lang['dataqueries_137']), 'enabled' => ($data_resolution_enabled == '2')),
			'api' => array('hdr' => RCView::b($lang['setup_77']), 'enabled' => $api_enabled, 'width'=>40),
			'mobile_app' => array('hdr' => RCView::b($lang['global_118']), 'enabled' => (isDev(true) && $mobile_app_enabled && $api_enabled), 'width'=>40),
			'realtime_webservice_mapping' => array('hdr' => RCView::b("{$lang['ws_51']} {$DDP->getSourceSystemName()}<div style='font-weight:normal;'>({$lang['ws_19']})</div>"), 'enabled' => (is_object($DDP) && $DDP->isEnabledInSystem() && $DDP->isEnabledInProject())),
			'realtime_webservice_adjudicate' => array('hdr' => RCView::b("{$lang['ws_51']} {$DDP->getSourceSystemName()}<div style='font-weight:normal;'>({$lang['ws_20']})</div>"), 'enabled' => (is_object($DDP) && $DDP->isEnabledInSystem() && $DDP->isEnabledInProject())),
			'dts' => array('hdr' => RCView::b($lang['rights_132']), 'enabled' => $dts_enabled_global && $dts_enabled),
			'record_create' => array('hdr' => RCView::b($lang['rights_99']), 'enabled' => true, 'width'=>45),
			'record_rename' => array('hdr' => RCView::b($lang['rights_100']), 'enabled' => true, 'width'=>45),
			'record_delete' => array('hdr' => RCView::b($lang['rights_101']), 'enabled' => true, 'width'=>45)
		);
		
		// Get all user rights as array
		$rightsAllUsers = self::getRightsAllUsers();
		
		// Get all suspended users in project (so we can note which are currently suspended)
		$suspendedUsers = self::getSuspendedUsers();
		
		// Get all user roles as array
		$roles = self::getRoles();
		
		// Loop through $roles and add a sub-array of users to each role that are assigned to it
		foreach ($rightsAllUsers as $this_username=>$attr) {
			// If has role_id value, then add username to that role in $roles
			if (is_numeric($attr['role_id'])) {
				$roles[$attr['role_id']]['role_users_assigned'][] = $this_username;
			}
		}
		//print_array($rightsAllUsers);
		//print_array($roles);
		
		// Set table width (loop through headers and calculate)
		$tableColPadding = 12;	
		$tableWidth = 0;	
		
		// Set up the table headers
		$hdrs = array();
		foreach ($rightsHdrs as $this_colname=>$attr) {
			// If this column is not enabled, skip it
			if (!$attr['enabled']) continue;
			// Determine col width
			$this_width = (isset($attr['width'])) ? $attr['width'] : $defaultColWidth;
			// Increment the table width
			$tableWidth += ($this_width + $tableColPadding);
			// Determine col alignment
			$this_align = (isset($attr['align'])) ? $attr['align'] : 'center';
			// Add to $hdrs array to be displayed
			$hdrs[] = array($this_width, RCView::span(array('class'=>'wrap','style'=>'line-height:10px;'), $attr['hdr']), $this_align);
		}
		
		## ADD TABLE ROWS
		// Add rows of users/roles (start with users not in a role, then go role by role listing users in each role)
		$rows = array();
		$rowkey = 0;
		foreach ($rightsAllUsers as $this_username=>$row) {
			// If has role_id value, then skip. We'll handle users in roles later.
			if (is_numeric($row['role_id'])) continue;
			// Add to $rows array
			$rows[$rowkey] = array();
			// Loop through each column
			foreach ($rightsHdrs as $rightsKey => $r) 
			{
				// If this column is not enabled, skip it
				if (!$r['enabled']) continue;				
				// Initialize vars
				$cellContent = '';
				// Output column's content (depending on which column we're on)
				if ($rightsKey == 'username') {
					// Set icon if has API token
					$apiIcon = ($row['api_token'] == '' ? '' : 
							RCView::span(array('class'=>'nowrap', 'style'=>'color:#A86700;font-size:11px;margin-left:8px;'),
								RCView::img(array('src'=>'coin.png', 'style'=>'vertical-align:middle;')) . 
								RCView::span(array('style'=>'vertical-align:middle;'),
									$lang['control_center_333']
								)
							)
						);
					// Set text if user's account is suspended
					$suspendedText = (in_array(strtolower($this_username), $suspendedUsers)) 
									? RCView::span(array('class'=>'nowrap', 'style'=>'color:red;font-size:11px;margin-left:8px;'), 
										$lang['rights_281']
									  )
									: "";
					$this_username_name = RCView::b($this_username) . ($row['user_fullname'] == '' ? '' : " ({$row['user_fullname']})");
					$cellContent = 	RCView::div(array('class'=>'userNameLinkDiv'), 
										RCView::a(array('href'=>'javascript:;', 'style'=>'vertical-align:middle;font-size:12px;', 'title'=>$lang['rights_178'],
											'class'=>'userLinkInTable', 'inrole'=>'0', 'userid'=>$this_username), $this_username_name) .
										$suspendedText . $apiIcon
									);
				}
				elseif ($rightsKey == 'role_name') {
					$cellContent = RCView::div(array('style'=>'color:#999;'), "&mdash;");
				}
				elseif ($rightsKey == 'expiration') {
					$this_class = ($row['expiration'] == "" ? 'userRightsExpireN' 
						: (str_replace("-","",$row['expiration']) < date('Ymd') ? 'userRightsExpired' : 'userRightsExpire'));
					$cellContent = 	RCView::div(array('class'=>'expireLinkDiv'),
										RCView::a(array('href'=>'javascript:;', 'class'=>$this_class, 'title'=>$lang['rights_201'], 
											'userid'=>$this_username, 
											'expire'=>($row['expiration'] == "" ? "" : DateTimeRC::format_ts_from_ymd($row['expiration']))),
											($row['expiration'] == "" ? $lang['rights_171'] : DateTimeRC::format_ts_from_ymd($row['expiration']))
										)
									);
				}
				elseif ($rightsKey == 'group_id') {
					// Display the DAG of this user
					if ($row['group_id'] == '') {
						$this_link_label = '&mdash;';
						$this_link_style = 'color:#999;';
					} else {
						$this_link_label = $dags[$row['group_id']];
						$this_link_style = 'color:#008000;';
					}
					if ($user_rights['group_id'] == '') {
						$cellContent = 	RCView::div(array('class'=>'dagNameLinkDiv'),
											RCView::a(array('href'=>'javascript:;', 'style'=>$this_link_style, 'title'=>$lang['rights_149'], 
												'gid'=>$row['group_id'], 'uid'=>$this_username), $this_link_label)
										);
					} else {
						$cellContent = 	RCView::div(array('class'=>'dagNameLinkDiv', 'style'=>$this_link_style), $this_link_label);
					}
				}
				elseif ($rightsKey == 'realtime_webservice_mapping') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;				
				}
				elseif ($rightsKey == 'realtime_webservice_adjudicate') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;				
				}
				elseif ($rightsKey == 'data_export_tool') {
					if ($row[$rightsKey] == "0") $cellContent = $imgNo;
					elseif ($row[$rightsKey] == "1") $cellContent = $lang['rights_49'];
					elseif ($row[$rightsKey] == "3") $cellContent = $lang['data_export_tool_182'];
					else $cellContent = $lang['rights_48'];
				}
				elseif ($rightsKey == 'data_quality_resolution') {
					if ($row[$rightsKey] == "0") $cellContent = $imgNo;
					elseif ($row[$rightsKey] == "1") $cellContent = $lang['dataqueries_143'];
					elseif ($row[$rightsKey] == "4") $cellContent = $lang['dataqueries_289'];
					elseif ($row[$rightsKey] == "5") $cellContent = $lang['dataqueries_290'];
					elseif ($row[$rightsKey] == "2") $cellContent = $lang['dataqueries_138'];
					elseif ($row[$rightsKey] == "3") $cellContent = $lang['dataqueries_139'];
				}
				elseif ($rightsKey == 'double_data') {
					$cellContent = ($row[$rightsKey] > 0) ? 'DDE Person #'.$row[$rightsKey] : $lang['rights_51'];
				}
				elseif ($rightsKey == 'lock_record_customize') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
				}
				elseif ($rightsKey == 'lock_record') {
					$cellContent = ($row[$rightsKey] > 0) ? (($row[$rightsKey] == 1) ? $imgYes : $imgShield) : $imgNo;
				}
				elseif ($rightsKey == 'api') {
					// Set text
					if ($row['api_export'] == 1 && $row['api_import'] == 1)
						$cellContent = $lang['global_71'] . RCView::br() . $lang['global_72'];
					elseif ($row['api_export'] == 1) $cellContent = $lang['global_71'];
					elseif ($row['api_import'] == 1) $cellContent = $lang['global_72'];
					else $cellContent = $imgNo;
					
				}
				elseif ($rightsKey == 'randomization') {
					if ($row['random_setup'] == 1) $cellContent .= $lang['rights_142'] . RCView::br();
					if ($row['random_dashboard'] == 1) $cellContent .= $lang['rights_143'] . RCView::br();
					if ($row['random_perform'] == 1) $cellContent .= $lang['rights_144'];
					if ($cellContent == '') $cellContent = $imgNo;
				}
				else {
					$cellContent = ($row[$rightsKey] == 1) ? $imgYes : $imgNo;
				}
				// Render table cell for this column
				$rows[$rowkey][] = RCView::div(array('class'=>'wrap'), $cellContent);
			}			
			// Increment rowkey
			$rowkey++;
		}
		// Now add roles
		foreach ($roles as $role_id=>$row) {
			// Add to $rows array
			$rows[$rowkey] = array();
			// Loop through each column
			foreach ($rightsHdrs as $rightsKey => $r) 
			{
				// If this column is not enabled, skip it
				if (!$r['enabled']) continue;				
				// Initialize vars
				$cellContent = '';
				// Output column's content (depending on which column we're on)
				if ($rightsKey == 'username') {
					if (empty($row['role_users_assigned'])) {
						$this_role_userlist = RCView::div(array('style'=>'color:#aaa;font-size:11px;'), 
												(($rightsAllUsers[USERID]['group_id'] == '' || SUPER_USER) ? $lang['rights_151'] : $lang['rights_222'])
											  );
					} else {
						$these_username_names = array();
						$i = 0;
						foreach ($row['role_users_assigned'] as $this_user_assigned) 
						{						
							// Set icon if has API token
							$apiIcon = ($rightsAllUsers[$this_user_assigned]['api_token'] == '' ? '' : 
									RCView::span(array('class'=>'nowrap', 'style'=>'color:#A86700;font-size:11px;margin-left:8px;'),
										RCView::img(array('src'=>'coin.png', 'style'=>'vertical-align:middle;')) . 
										RCView::span(array('style'=>'vertical-align:middle;'),
											$lang['control_center_333']
										)
									)
								);
							// Set text if user's account is suspended
							$suspendedText = (in_array(strtolower($this_user_assigned), $suspendedUsers)) 
											? RCView::span(array('class'=>'nowrap', 'style'=>'color:red;font-size:11px;margin-left:8px;'), 
												$lang['rights_281']
											  )
											: "";
							$this_username_name = RCView::b($this_user_assigned) . ($rightsAllUsers[$this_user_assigned]['user_fullname'] == '' ? '' : " ({$rightsAllUsers[$this_user_assigned]['user_fullname']})");
							$these_username_names[] = 	
								RCView::div(array('class'=>'userNameLinkDiv', 'style'=>($i==0 ? '' : 'border-top:1px solid #eee;')), 
									RCView::a(array('href'=>'javascript:;', 'style'=>'vertical-align:middle;font-size:12px;', 'title'=>$lang['rights_217'],
										'class'=>'userLinkInTable', 'inrole'=>'1', 'userid'=>$this_user_assigned), $this_username_name) .
									$suspendedText . $apiIcon
								);
							$i++;
						}
						$this_role_userlist = implode("", $these_username_names);
					}
					$cellContent = 	RCView::div(array('style'=>'color:#800000;'),
										$this_role_userlist
									);
				}
				elseif ($rightsKey == 'role_name') {
					// Set different color for system-level roles
					$cellContent = RCView::a(array('href'=>'javascript:;', 'style'=>'color:#800000;font-weight:bold;font-size:12px;', 
										'title'=>$lang['rights_152'], 'id'=>'rightsTableUserLinkId_' . $role_id), 
										$row['role_name']
									);
				}
				elseif ($rightsKey == 'expiration') {
					$these_rows = array();
					$i = 0;
					foreach ($row['role_users_assigned'] as $this_user_assigned) {
						$this_expiration = $rightsAllUsers[$this_user_assigned]['expiration'];
						$this_class = ($this_expiration == "" ? 'userRightsExpireN' 
							: (str_replace("-","",$this_expiration) < date('Ymd') ? 'userRightsExpired' : 'userRightsExpire'));
						$these_rows[] = 
							RCView::div(array('class'=>'expireLinkDiv', 'style'=>($i==0 ? '' : 'border-top:1px solid #eee;')),
								RCView::a(array('href'=>'javascript:;', 'class'=>$this_class, 'title'=>$lang['rights_201'], 
									'userid'=>$this_user_assigned, 
									'expire'=>($this_expiration == "" ? "" : DateTimeRC::format_ts_from_ymd($this_expiration))),
									($this_expiration == "" ? $lang['rights_171'] : DateTimeRC::format_ts_from_ymd($this_expiration))
								)
							);
						$i++;
					}
					$cellContent = implode("", $these_rows);
				}
				elseif ($rightsKey == 'group_id') {
					// Display the DAGs of all users in this role
					$these_dagnames = array();
					$i = 0;
					foreach ($row['role_users_assigned'] as $this_user_assigned) {
						$this_group_id = $rightsAllUsers[$this_user_assigned]['group_id'];
						if ($rightsAllUsers[$this_user_assigned]['group_id'] == '') {
							$this_link_label = '&mdash;';
							$this_link_style = 'color:#999;';
						} else {
							$this_link_label = $dags[$this_group_id];
							$this_link_style = 'color:#008000;';
						}						
						if ($user_rights['group_id'] == '') {
							$these_dagnames[] = RCView::div(array('class'=>'dagNameLinkDiv', 'style'=>($i==0 ? '' : 'border-top:1px solid #eee;')),
													RCView::a(array('href'=>'javascript:;', 'style'=>$this_link_style, 'title'=>$lang['rights_149'], 
														'gid'=>$this_group_id, 'uid'=>$this_user_assigned), $this_link_label)
												);
						} else {
							$these_dagnames[] = RCView::div(array('class'=>'dagNameLinkDiv', 'style'=>$this_link_style.($i==0 ? '' : 'border-top:1px solid #eee;')),
													$this_link_label
												);
						}
						$i++;
					}
					$cellContent = implode("", $these_dagnames);
				}
				elseif ($rightsKey == 'realtime_webservice_mapping') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;				
				}
				elseif ($rightsKey == 'realtime_webservice_adjudicate') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;				
				}
				elseif ($rightsKey == 'data_export_tool') {
					if ($row[$rightsKey] == "0") $cellContent = $imgNo;
					elseif ($row[$rightsKey] == "1") $cellContent = $lang['rights_49'];
					elseif ($row[$rightsKey] == "3") $cellContent = $lang['data_export_tool_182'];
					else $cellContent = $lang['rights_48'];
				}
				elseif ($rightsKey == 'data_quality_resolution') {
					if ($row[$rightsKey] == "0") $cellContent = $imgNo;
					elseif ($row[$rightsKey] == "1") $cellContent = $lang['dataqueries_143'];
					elseif ($row[$rightsKey] == "4") $cellContent = $lang['dataqueries_289'];
					elseif ($row[$rightsKey] == "5") $cellContent = $lang['dataqueries_290'];
					elseif ($row[$rightsKey] == "2") $cellContent = $lang['dataqueries_138'];
					elseif ($row[$rightsKey] == "3") $cellContent = $lang['dataqueries_139'];
				}
				elseif ($rightsKey == 'double_data') {
					$cellContent = ($row[$rightsKey] > 0) ? 'DDE Person #'.$row[$rightsKey] : $lang['rights_51'];
				}
				elseif ($rightsKey == 'lock_record_customize') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
				}
				elseif ($rightsKey == 'lock_record') {
					$cellContent = ($row[$rightsKey] > 0) ? (($row[$rightsKey] == 1) ? $imgYes : $imgShield) : $imgNo;
				}
				elseif ($rightsKey == 'api') {
					if ($row['api_export'] == 1 && $row['api_import'] == 1)
						$cellContent = $lang['global_71'] . RCView::br() . $lang['global_72'];
					elseif ($row['api_export'] == 1) $cellContent = $lang['global_71'];
					elseif ($row['api_import'] == 1) $cellContent = $lang['global_72'];
					else $cellContent = $imgNo;
				}
				elseif ($rightsKey == 'randomization') {
					if ($row['random_setup'] == 1) $cellContent .= $lang['rights_142'] . RCView::br();
					if ($row['random_dashboard'] == 1) $cellContent .= $lang['rights_143'] . RCView::br();
					if ($row['random_perform'] == 1) $cellContent .= $lang['rights_144'];
					if ($cellContent == '') $cellContent = $imgNo;
				}
				else {
					$cellContent = ($row[$rightsKey] == 1) ? $imgYes : $imgNo;
				}
				// Render table cell for this column
				$rows[$rowkey][] = RCView::div(array('class'=>'wrap'), $cellContent);
			}			
			// Increment rowkey
			$rowkey++;
		}
		
		// Create "add new user" text box
		$usernameTextboxJsFocus = "$('#new_username_assign').val('".cleanHtml($lang['rights_160'])."').css('color','#999'); 
									if ($(this).val() == '".cleanHtml($lang['rights_154'])."') { 
									$(this).val(''); $(this).css('color','#000'); 
								  }";
		$usernameTextboxJsBlur = "$(this).val( trim($(this).val()) );
								  if ($(this).val() == '') { 
									$(this).val('".cleanHtml($lang['rights_154'])."'); $(this).css('color','#999'); 
								  }";
		$usernameTextbox = RCView::text(array('id'=>'new_username', 'class'=>'x-form-text x-form-field', 'maxlength'=>'255', 
							'style'=>'margin-left:4px;width:200px;color:#999;','value'=>$lang['rights_154'],
							'onkeydown'=>"if(event.keyCode==13) $('#addUserBtn').click();",
							'onfocus'=>$usernameTextboxJsFocus,'onblur'=>$usernameTextboxJsBlur));
							
		// Create "assign new user" text box
		$usernameTextboxJsFocusAssign = "$('#new_username').val('".cleanHtml($lang['rights_154'])."').css('color','#999'); 
										 if ($(this).val() == '".cleanHtml($lang['rights_160'])."') { 
											$(this).val(''); $(this).css('color','#000'); 
										  }";
		$usernameTextboxJsBlurAssign =  "$(this).val( trim($(this).val()) );
										  if ($(this).val() == '') { 
											$(this).val('".cleanHtml($lang['rights_160'])."'); $(this).css('color','#999'); 
										  }";
		$usernameTextboxAssign = RCView::text(array('id'=>'new_username_assign', 'class'=>'x-form-text x-form-field', 'maxlength'=>'255', 
							'style'=>'margin-left:4px;width:200px;color:#999;','value'=>$lang['rights_160'],
							'onkeydown'=>"if(event.keyCode==13) $('#assignUserBtn').click();",
							'onfocus'=>$usernameTextboxJsFocusAssign,'onblur'=>$usernameTextboxJsBlurAssign));
		
		// Create "new role name" text box
		$userroleTextboxJsFocus = "if ($(this).val() == '".cleanHtml($lang['rights_155'])."') { 
									$(this).val(''); $(this).css('color','#000'); 
								  }";
		$userroleTextboxJsBlur = "$(this).val( trim($(this).val()) );
								  if ($(this).val() == '') { 
									$(this).val('".cleanHtml($lang['rights_155'])."'); $(this).css('color','#999'); 
								  }";
		$userroleTextbox = RCView::text(array('id'=>'new_rolename', 'class'=>'x-form-text x-form-field', 'maxlength'=>'150', 
							'style'=>'margin-left:4px;width:200px;color:#999;','value'=>$lang['rights_155'],
							'onkeydown'=>"if(event.keyCode==13) $('#createRoleBtn').click();",
							'onfocus'=>$userroleTextboxJsFocus,'onblur'=>$userroleTextboxJsBlur));
		
		// Set html before the table
		$html = RCView::div(array('id'=>'addUsersRolesDiv', 'style'=>'margin:20px 0;font-family:arial;font-size:12px;font-weight:normal;padding:10px;border:1px solid #ccc;background-color:#eee;max-width:630px;'),
					// Add new user with custom rights
					RCView::div(array('style'=>'color:#444;'), 
						RCView::span(array('style'=>'font-weight:bold;font-size:13px;color:#000;margin-right:5px;'), $lang['rights_168']) . 
						" " .$lang['rights_162']
					) .
					RCView::div(array('style'=>'margin:8px 0 0 29px;'),
						RCView::img(array('src'=>'user_add2.png', 'class'=>'imgfix')) .
						$usernameTextbox .
						// Add User button
						RCView::button(array('id'=>'addUserBtn', 'class'=>'jqbuttonmed'), $lang['rights_165'])
					) .
					// - OR -					
					RCView::div(array('style'=>'margin:2px 0 1px 60px;color:#999;'),
						"&#8212; {$lang['global_46']} &#8212;"
					) .
					// Add new user - assign to role
					RCView::div(array('style'=>'margin:0 0 0 10px;'), 
						RCView::img(array('src'=>'user_add2.png', 'class'=>'imgfix')) .
						RCView::img(array('src'=>'vcard.png', 'class'=>'imgfix')) .
						$usernameTextboxAssign .
						// Assign User button
						RCView::button(array('id'=>'assignUserBtn', 'class'=>'jqbuttonmed', 'style'=>'margin-top:2px;'), 
							RCView::span(array('style'=>'vertical-align:middle;'), $lang['rights_156']) . 
							RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:5px;vertical-align:middle;position:relative;top:-1px;'))
						)
					) .
					// Create new user role
					RCView::div(array('style'=>'margin:20px 0 0;color:#444;'),  
						RCView::span(array('style'=>'font-weight:bold;font-size:13px;color:#000;margin-right:5px;'), $lang['rights_170']) . 
						" " .$lang['rights_169']
					) .
					RCView::div(array('style'=>'margin:8px 0 0 27px;font-weight:bold;color:#2C5178;'),  
						RCView::img(array('src'=>'vcard_add.png', 'class'=>'imgfix', 'style'=>'')) .
						$userroleTextbox .
						RCView::button(array('id'=>'createRoleBtn', 'class'=>'jqbuttonmed'), $lang['rights_158'])
					) .
					RCView::div(array('style'=>'margin:2px 0 0 52px;font-size:11px;color:#888;'),  
						$lang['rights_218']
					)
				);
				
		// Create DROP-DOWN OF USER ROLES to choose from
		$roleDropdownOptions = '';
		foreach ($roles as $role_id=>$attr) {
			$roleDropdownOptions .= RCView::li(array('id'=>"assignUserRoleId_$role_id"),
										RCView::a(array('href'=>'#'), 
											RCView::img(array('src'=>'arrow_right.gif', 'class'=>'imgfix')) . $attr['role_name'])
									);
		}
		$html .= RCView::div(array('id'=>'assignUserDropdownDiv', 'style'=>'display:none;position:absolute;z-index:1000;'),
					RCView::ul(array('id'=>'assignUserDropdown'), $roleDropdownOptions)
				);
				
				
		// TOOLTIP div when CLICK USERNAME IN TABLE
		$html .= RCView::div(array('id'=>'userClickTooltip', 'class'=>'tooltip4left','style'=>'position:absolute;padding-left:30px;'), 
					RCView::div(array('style'=>'padding-bottom:5px;font-weight:bold;font-size:13px;'), $lang['rights_172']) .
					// Set custom rights button
					RCView::div(array('id'=>'tooltipBtnSetCustom', 'style'=>'clear:both;padding-bottom:2px;', 'onclick'=>"openAddUserPopup( $('#tooltipHiddenUsername').val());"), 
						RCView::button(array('class'=>'jqbuttonmed'), $lang['rights_153'])
					) .
					// Remove from Role button
					RCView::div(array('id'=>'tooltipBtnRemoveRole', 'style'=>'padding-bottom:2px;', 'onclick'=>"assignUserRole( $('#tooltipHiddenUsername').val(),0)"), 
						RCView::button(array('class'=>'jqbuttonmed'), $lang['rights_175'])
					) . 
					// Assign User button
					RCView::div(array('id'=>'tooltipBtnAssignRole'), 
						RCView::button(array('id'=>'assignUserBtn2', 'class'=>'jqbuttonmed'), 
							RCView::span(array('style'=>'vertical-align:middle;'), $lang['rights_156']) . 
							RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:5px;vertical-align:middle;position:relative;top:-1px;'))
						)
					) . 
					// Re-assign User button
					RCView::div(array('id'=>'tooltipBtnReassignRole'), 
						RCView::button(array('id'=>'assignUserBtn3', 'class'=>'jqbuttonmed'), 
							RCView::span(array('style'=>'vertical-align:middle;'), $lang['rights_173']) . 
							RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:5px;vertical-align:middle;position:relative;top:-1px;'))
						)
					) .
					// Hidden input where username is store for the user just clicked, which opened this tooltip (so we know which was clicked)
					RCView::hidden(array('id'=>'tooltipHiddenUsername'))
				);
		
		// Return the html for displaying the table
		return $html . renderGrid("user_rights_roles_table", '', $tableWidth, "auto", $hdrs, $rows, true, true, false);
	}
	
	// Get list of all suspended users in the current project
	public static function getSuspendedUsers()
	{
		$suspendedUsers = array();
		// Query to get suspended users in project
		$sql = "select i.username from redcap_user_information i, redcap_user_rights u 
				where u.username = i.username and u.project_id = ".PROJECT_ID." and i.user_suspended_time is not null 
				and i.user_suspended_time <= '".NOW."'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$suspendedUsers[] = strtolower($row['username']);
		}
		return $suspendedUsers;
	}
	
	// Detect if a single user has User Rights privileges in *any* project (i.e. is a project owner) - includes roles that user is in
	public static function hasUserRightsPrivileges($user)
	{
		// Query to see if have User Rights privileges in at least one project (consider roles rights in this)
		$sql = "select 1 from redcap_user_rights u left join redcap_user_roles r 
				on r.role_id = u.role_id where u.username = '".prep($user)."' 
				and ((u.user_rights = 1 and r.user_rights is null) or r.user_rights = 1) limit 1";
		$q = db_query($sql);
		return ($q && db_num_rows($q) > 0);
	}
	
	
}
