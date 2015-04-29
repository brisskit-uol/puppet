<?php
global $format, $returnFormat, $post;

defined("PROJECT_ID") or define("PROJECT_ID", $post['projectid']);

# get all the records to be exported
$result = getItems();

# structure the output data accordingly
switch($format)
{
	case 'json':
		$content = json_encode($result);
		break;
	case 'xml':
		$content = xml($result);
		break;
	case 'csv':
		$content = csv($result);
		break;
}

/************************** log the event **************************/
$query = "SELECT username FROM redcap_user_rights WHERE api_token = '" . prep($post['token']) . "'";
defined("USERID") or define("USERID", db_result(db_query($query), 0));

# Logging
log_event("", "redcap_user_rights", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export users (API)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function xml($dataset)
{
	$output = '<?xml version="1.0" encoding="UTF-8" ?>';
	$output .= "\n<records>\n";

	foreach ($dataset as $row)
	{
		$data  = ($row['username'] != "") ? "<username>" . $row['username'] . "</username>" : "<username/>";
		$data .= ($row['email'] != "") ? "<email>" . $row['email'] . "</email>" : "<email/>";
		$data .= ($row['firstname'] != "") ? "<firstname><![CDATA[" . $row['firstname'] . "]]></firstname>" : "<firstname/>";
		$data .= ($row['lastname'] != "") ? "<lastname><![CDATA[" . $row['lastname'] . "]]></lastname>" : "<lastname/>";
		$data .= ($row['expiration'] != "") ? "<expiration><![CDATA[" . $row['expiration'] . "]]></expiration>" : "<expiration/>";
		$data .= ($row['data_access_group'] != "") ? "<data_access_group>" . $row['data_access_group'] . "</data_access_group>" : "<data_access_group/>";
		$data .= ($row['data_export'] != "") ? "<data_export>" . $row['data_export'] . "</data_export>" : "<data_export/>";

		$data .= "<forms>";
		foreach ($row["forms"] as $form => $right) {
			$data .= "<$form>$right</$form>";
		}
		$data .= "</forms>";

		$output .= "<item>$data</item>\n";
	}
	$output .= "</records>\n";

	return $output;
}

function csv($dataset)
{
	$output = "";
	$firstRun = true;

	foreach ($dataset as $index => $user) {
		$output .= '"'.$user['username'].'","'.$user['email'].'","'.
				   str_replace('"', '""', $user['firstname']).'","'.
				   str_replace('"', '""', $user['lastname']).'","'.
				   $user['expiration'].'",'.$user['data_access_group'].','.$user['data_export'];

		foreach($user["forms"] as $form => $right) {
			$output .= ",$right";
		}
		$output .= "\n";

		if ($firstRun) {
			$fieldList = implode(",", array_keys($user["forms"]));
			$firstRun = false;
		}
	}

	$fieldList = "username,email,firstname,lastname,expiration,data_access_group,data_export,".$fieldList;
	$output = $fieldList . "\n" . $output;

	return $output;
}

function getItems()
{
	global $post;
	
	// Get all user's rights (includes role's rights if they are in a role)
	$user_priv = UserRights::getPrivileges($post['projectid']);
	$user_priv = $user_priv[$post['projectid']];

	# get user information (does NOT include role-based rights for user)
	$sql = "SELECT ur.*, ui.user_email, ui.user_firstname, ui.user_lastname
			FROM redcap_user_rights ur
			LEFT JOIN redcap_user_information ui ON ur.username = ui.username
			WHERE ur.project_id = ".PROJECT_ID;
	$users = db_query($sql);
	$result = array();	
	while ($row = db_fetch_assoc($users))
	{
		// Decode and set any nulls to ""
		foreach ($row as &$val) {
			if (is_array($val)) continue;
			if ($val == null) $val = '';
			$val = html_entity_decode($val, ENT_QUOTES);
		}
		
		// Convert username to lower case to prevent case sensitivity issues with arrays
		$row["username"] = strtolower($row["username"]);
		
		// Parse data entry rights
		$dataEntryArr = explode("][", substr(trim($user_priv[$row["username"]]['data_entry']), 1, -1));
		$forms = array();
		foreach ($dataEntryArr as $keyval)
		{
			list($key, $value) = explode(",", $keyval, 2);			
			if ($key == '') continue;
			$forms[$key] = $value;
		}
		
		// Set array entry for this user
		$result[] = array("username" 			=> $row["username"],
						  "email" 				=> $row["user_email"],
						  "firstname" 			=> $row["user_firstname"],
						  "lastname"	 		=> $row["user_lastname"],
						  "expiration" 			=> $row["expiration"],
						  "data_access_group" 	=> $row["group_id"],
						  // Rights that might be governed by roles
						  "data_export" 		=> $user_priv[$row["username"]]["data_export_tool"],
						  "forms"				=> $forms);
	}

	return $result;
}