<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// Make sure two-factor is enabled and that we have the verification code
if ($two_factor_auth_enabled && isset($_POST['code']) && is_numeric(str_replace(' ', '', $_POST['code']))
	&& Authentication::verifyTwoFactorCode($_POST['code'])) 
{
	// Set session variable to denote that user has performed two factor auth
	$_SESSION['two_factor_auth'] = "1";
	// Return success flag
	print "1";
} 
else 
{
	// Return error flag
	print "0";
}