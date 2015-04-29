<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';


// If user clicked verification code link in their email, validation the code and complete the setup process
if (isset($_GET['user_verify'])) 
{
	// Display header
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->addStylesheet("smoothness/jquery-ui-".JQUERYUI_VERSION.".custom.css", 'screen,print');
	$objHtmlPage->addStylesheet("style.css", 'screen,print');
	$objHtmlPage->PrintHeaderExt();
	// Display link on far right to log out
	print 	RCView::div(array('style'=>'text-align:right;'),
				RCView::a(array('href'=>'javascript:;','onclick'=>"deleteCookie('authchallenge');deleteCookie('PHPSESSID');window.location.reload();",'style'=>'text-decoration:underline;'), $lang['bottom_02'])
			);
			
	// Get user info
	$user_info = User::getUserInfo($userid);
	
	// Verify the code provided
	$emailAccount = User::verifyUserVerificationCode($userid, $_GET['user_verify']);
	if ($emailAccount !== false) {
		## Verified
		// Activate the new email by removing the verification code
		User::removeUserVerificationCode($userid, $emailAccount);
		// Log the event
		defined("USERID") or define("USERID", $userid);
		log_event("", "redcap_user_information", "MANAGE", $userid, "username = '$userid'", "Verify user email address");
		// Confirmation that account has been activated
		print 	RCView::h3(array('style'=>'margin:10px 0 25px;color:green;'), 
					RCView::img(array('src'=>'tick.png','class'=>'imgfix2')) . $lang['user_29']
				) .
				RCView::div(array('class'=>'darkgreen','style'=>'padding:10px;margin-bottom:30px;'),
					$lang['user_30'] .
					RCView::div(array('style'=>'padding:10px;text-align:center;'),
						RCView::button(array('class'=>'jqbutton','onclick'=>"window.location.href=app_path_webroot;"), $lang['global_88'])
					)
				);
	} else {
		## Error: code could not be verified
		// Check to see if the verification code actually belongs to another user
		if (User::verifyUserVerificationCodeAnyUser($_GET['user_verify'])) {
			// Code belongs to ANOTHER user. Output error message.		
			// Code doesn't belong to ANY user. Output error message.
			print 	RCView::h3(array('style'=>'margin:10px 0 25px;color:#800000;'), 
						RCView::img(array('src'=>'delete.png','class'=>'imgfix2')) . $lang['user_31'] . RCView::SP . 
						$lang['user_65'] . RCView::SP . "\"" . USERID . "\"" . $lang['exclamationpoint']
					) .
					RCView::div(array('class'=>'red','style'=>'padding:10px;margin-bottom:30px;'),
						$lang['user_63'] . RCView::SP . "(" . RCView::b(USERID) . ")" . $lang['period'] . RCView::SP . $lang['user_64'] . RCView::SP . 
						RCView::a(array('href'=>"mailto:".$project_contact_email,'style'=>'text-decoration:underline;'), $project_contact_name) . 
						$lang['period']
					);
		} else {		
			// Code doesn't belong to ANY user. Output error message.
			print 	RCView::h3(array('style'=>'margin:10px 0 25px;color:#800000;'), 
						RCView::img(array('src'=>'delete.png','class'=>'imgfix2')) . $lang['user_31']
					) .
					RCView::div(array('class'=>'red','style'=>'padding:10px;margin-bottom:30px;'),
						$lang['user_32'] . RCView::SP . 
						RCView::a(array('href'=>"mailto:".$project_contact_email,'style'=>'text-decoration:underline;'), $project_contact_name) . 
						$lang['period']
					);
		}
	}
	
	// Footer
	$objHtmlPage->PrintFooterExt();
	exit;
}

redirectHome();