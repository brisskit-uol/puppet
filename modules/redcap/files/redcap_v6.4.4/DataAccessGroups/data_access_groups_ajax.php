<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

//If action is provided in AJAX request, perform action.
if (isset($_GET['action'])) 
{
	switch ($_GET['action']) 
	{
		case "delete":
			//Before deleting, make sure no users are in the group. If there are, don't delete.
			if (!is_numeric($_GET['item'])) exit('ERROR!');
			$gcount = db_result(db_query("select count(1) from redcap_user_rights where project_id = $project_id and group_id = {$_GET['item']}"),0);
			$gcount2 = db_result(db_query("select count(1) from redcap_user_roles where project_id = $project_id and group_id = {$_GET['item']}"),0);
			if ($gcount+$gcount2 < 1) 
			{	
				// Get group name
				$group_name = $Proj->getGroups($_GET['item']);
				// Delete from DAG table
				$sql = "delete from redcap_data_access_groups where group_id = ".$_GET['item'];
				$q = db_query($sql);
				// Also delete any instances of records being attributed to the DAG in the data table
				$sql2 = "delete from redcap_data where project_id = $project_id and field_name = '__GROUPID__' 
						and value = '".prep($_GET['item'])."'";
				$q = db_query($sql2);
				// Logging
				if ($q) log_event("$sql;\n$sql2","redcap_data_access_groups","MANAGE",$_GET['item'],"group_id = ".$_GET['item'],"Delete data access group");
				print  "<div class='red dagMsg hidden' style='max-width:700px;text-align:center;'>
						<img src='".APP_PATH_IMAGES."cross.png' class='imgfix'> 
						{$lang['global_78']} \"<b>$group_name</b>\" {$lang['data_access_groups_ajax_28']} 
						</div>";
			} else {
				print  "<div class='red dagMsg hidden' style='max-width:700px;'>
						<img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <b>{$lang['global_01']}{$lang['colon']}</b><br>
						{$lang['data_access_groups_ajax_35']}
						</div>";
			}
			## What happens to the associated records that belong to a group that is deleted?
			break;
		case "add":
			$new_group_name = strip_tags(html_entity_decode(trim($_GET['item']), ENT_QUOTES));
			if ($new_group_name != "") {
				$sql = "insert into redcap_data_access_groups (project_id, group_name) values ($project_id, '" . prep($new_group_name) . "')";
				$q = db_query($sql);
				// Logging
				if ($q) {
					$dag_id = db_insert_id();
					log_event($sql,"redcap_data_access_groups","MANAGE",$dag_id,"group_id = $dag_id","Create data access group");
				}
				print  "<div class='darkgreen dagMsg hidden' style='max-width:700px;text-align:center;'>
						<img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> 
						{$lang['global_78']} \"<b>$new_group_name</b>\" {$lang['data_access_groups_ajax_29']}</div>";
			}
			break;
		case "rename":
			$group_id = substr($_GET['group_id'],4);
			if (!is_numeric($group_id)) exit('ERROR!');
			//exit(rawurldecode($_GET['item']));
			$new_group_name = trim(strip_tags(utf8_urldecode(html_entity_decode($_GET['item'], ENT_QUOTES))));
			if ($new_group_name != "") {
				$sql = "update redcap_data_access_groups set group_name = '" . prep($new_group_name) . "' where group_id = $group_id";
				$q = db_query($sql);
				// Logging
				if ($q) log_event($sql,"redcap_data_access_groups","MANAGE",$group_id,"group_id = ".$group_id,"Rename data access group");
			}
			exit($new_group_name);
			break;
		case "add_user":
			if (!is_numeric($_GET['group_id']) && $_GET['group_id'] != '') exit('ERROR!');
			if ($_GET['group_id'] == "") {
				$assigned_msg = $lang['data_access_groups_ajax_31'];
				$_GET['group_id'] = "NULL";
				$logging_msg = "Remove user from data access group";
				// Get group name for user BEFORE we unassign them
				$this_user_rights = UserRights::getPrivileges($project_id, $_GET['user']);
				$this_user_rights = $this_user_rights[$project_id][strtolower($_GET['user'])];
				$group_name = $Proj->getGroups($this_user_rights['group_id']);
			} else {
				// Get group name
				$group_name = $Proj->getGroups($_GET['group_id']);
				$assigned_msg = "{$lang['data_access_groups_ajax_30']} \"<b>$group_name</b>\"{$lang['exclamationpoint']}";
				$logging_msg = "Assign user to data access group";
			}
			$sql = "update redcap_user_rights set group_id = {$_GET['group_id']} where username = '".prep($_GET['user'])."' and project_id = $project_id";
			$q = db_query($sql);
			// Logging
			if ($q) log_event($sql,"redcap_user_rights","MANAGE",$_GET['user'],"user = '{$_GET['user']}',\ngroup = '$group_name'",$logging_msg);
			print  "<div class='darkgreen dagMsg hidden'  style='max-width:700px;text-align:center;'>
					<img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> 
					{$lang['global_17']} \"<b>".remBr(RCView::escape($_GET['user']))."</b>\" $assigned_msg
					</div>";
			// If flag is set to display the User Rights table, then return its html and stop here
			if (isset($_GET['return_user_rights_table']) && $_GET['return_user_rights_table']) {
				exit(UserRights::renderUserRightsRolesTable());
			}
			break;
		case "select_group":
			$group_id = db_result(db_query("select group_id from redcap_user_rights where username = '".prep($_GET['user'])."' and project_id = $project_id"),0);
			exit($group_id);
			break;
		case "select_role":
			$group_id = db_result(db_query("select group_id from redcap_user_roles where role_id = '".prep($_GET['role_id'])."' and project_id = $project_id"),0);
			exit($group_id);
			break;
	}

}


// Reset groups in case were just modified above
$Proj->resetGroups();

// Render groups table and options to designated users/roles to groups
print UserRights::renderDataAccessGroupsTable();
