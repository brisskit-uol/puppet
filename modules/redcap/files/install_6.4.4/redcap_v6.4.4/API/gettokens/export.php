<?php
global $format, $returnFormat, $post;

// Set DSN arrays for Table-based auth and/or LDAP auth
Authentication::setDSNs();

// Attempt to authenticate using username/password (this unfortunately does not work for certain authentication
// methods, such as Shibboleth, OpenID, etc. that require HTTP redirection).
if (checkUserPassword($post['username'], $post['password'])) {
	// Set userid
	define("USERID", $post['username']);
	// Return list of projects with tokens
	$sql = "select p.project_id, trim(p.app_title) as title, u.api_token from redcap_projects p, redcap_user_rights u 
			where p.project_id = u.project_id and u.username = '".prep(USERID)."' and u.api_token is not null 
			order by trim(p.app_title)";
	$q = db_query($sql);
	$tokens = array();
	while ($row = db_fetch_assoc($q)) {
		$tokens[] = array('project_id'=>$row['project_id'], 'project_title'=>strip_tags(nl2br(label_decode($row['title']))), 'token'=>$row['api_token']);
	}
	// Log this event
	log_event($sql,"redcap_user_rights","MANAGE",USERID,"userid = '".USERID."'","Export user API tokens (API)");
	// Output in specified format
	switch($format) {
		case 'json':
			$output = json_encode($tokens);
			break;
		case 'xml':
			$output = '<?xml version="1.0" encoding="UTF-8" ?>';
			$output .= "\n<tokens>\n";
			foreach ($tokens as $row) {
				$line = '';
				foreach ($row as $item => $value) {
					$line .= "<$item><![CDATA[$value]]></$item>";
				}
				$output .= "<item>$line</item>\n";
			}
			$output .= "</tokens>\n";
			break;
		case 'csv':		
			$output = array();
			foreach ($tokens as $row) {
				$row['project_title'] = '"' . str_replace('"', '""', $row['project_title']). '"';
				$output[] = implode(',', $row);
			}
			$headers = "project_id,project_title,token";
			$output = $headers . "\n" . implode("\n", $output);
			break;
	}
	# Send the response to the requestor
	RestUtility::sendResponse(200, $output, $format);
} else {
	// Failed to authenticate with username/password
	log_event("","","ERROR",'',json_encode(array('username'=>$post['username'],'password'=>$post['password'])),"Failed API request (invalid username/password)");
	die(RestUtility::sendResponse(401, 'The username and password provided are not valid'));
}