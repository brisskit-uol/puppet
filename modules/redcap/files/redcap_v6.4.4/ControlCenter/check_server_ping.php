<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

// Disable authentication IF "noauthkey" is passed in the query string with the correct value (MD5 hash of $salt and today's date+hour)
if (isset($_GET['noauthkey'])) {
	// Get $salt from database.php
	require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'database.php';
	// Validate $salt
	if (!isset($salt)) exit;
	// Validate "noauthkey"
	if ($_GET['noauthkey'] == md5($salt . date('YmdH'))) {
		// Disable authentication
		define("NOAUTH", true);
	} else {
		// Failed, so stop here
		exit;
	}
}

// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

// Set page to call on consoritium server
$page_to_ping = CONSORTIUM_WEBSITE . 'ping.php';

// Use HTTP Post method
if (isset($_GET['type']) && $_GET['type'] == 'post')
{
	print http_post($page_to_ping);
}

// Use HTTP GET method
else
{
	print http_get($page_to_ping);
}