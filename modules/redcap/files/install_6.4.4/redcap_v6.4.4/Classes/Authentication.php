<?php

/**
 * Authentication
 * This class is used for authentication-centric activities.
 */
class Authentication
{	
	// Return array of available security questions with Primary Key as array key.
	// If provide qid key, then only return question text for that one question.
	static function getSecurityQuestions($qid="")
	{
		// Check qid value first
		if ($qid != "" && !is_numeric($qid)) return false;
		$sqlQid = (is_numeric($qid)) ? "where qid = ".prep($qid) : "";
		// Query table to question text
		$sql = "select * from redcap_auth_questions $sqlQid order by qid";
		$q = db_query($sql);
		if (!$q || db_num_rows($q) < 1) {
			return false;
		} elseif (is_numeric($qid)) {
			// Return single question text
			return db_result($q, 0, 'question');
		} else {
			// Return all questions as array
			$questions = array();
			while ($row = db_fetch_assoc($q)) {
				$questions[$row['qid']] = $row['question'];
			}
			// Return array
			return $questions;
		}
	}
	
	// Clean and convert security answer to MD5 hash
	static function hashSecurityAnswer($answer_orig)
	{
		// Trim and remove non-alphanumeric characters (but keep spaces and keep lower-case)
		$answer = trim($answer_orig);	
		// Replace non essential characters
		$answer_repl = preg_replace("/[^0-9a-z ]/", "", strtolower($answer));
		// If answer is not ASCII encoded and also results with a blank string after the string replacement, then leave as-is before hashing.
		if (!(function_exists('mb_detect_encoding') && mb_detect_encoding($answer) != 'ASCII' && $answer_repl == '')) {
			$answer = $answer_repl;
		}
		// Return MD5 hashed answer
		return md5($answer);	
	}
	
	// Authenticate the user using Vanderbilt's custom C4 cookie-based authentication
	static function authenticateC4Cookie()
	{
		// Include database.php again in order to get secret C4 auth variables
		include dirname(APP_PATH_DOCROOT) . DS . 'database.php';	
		
		// Make sure we have all the requisite variables
		if (!isset($c4_auth_cookiename) || !isset($c4_auth_iv) || !isset($c4_auth_key)) {
			exit("ERROR! Could not find the following variables in your database.php file: \$c4_auth_cookiename, \$c4_auth_iv, \$c4_auth_key
			 $c4_auth_cookiename, $c4_auth_iv, $c4_auth_key");
		}
		
		// Check to make sure that the Mcrypt PHP extension is loaded
		if (!mcrypt_loaded(true)) return false;
		
		// Get cookie value
		if (!isset($_COOKIE[$c4_auth_cookiename])) return false;
		$cookieValue = $_COOKIE[$c4_auth_cookiename];
		
		// Decode cookie value to get username
		$username = rtrim(mcrypt_decrypt(MCRYPT_BLOWFISH, md5($c4_auth_key), base64_decode($cookieValue), MCRYPT_MODE_CBC, base64_decode($c4_auth_iv)));
		
		// Since ALL usernames should be email addresses, make sure it's a valid email
		if (!preg_match("/^([_a-z0-9-']+)(\.[_a-z0-9-']+)*@([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $username)) return false; 
		
		// If all is well, return decoded cookie value
		return $username;
	}
	

	// Display notice that password will expire soon (if utilizing $password_reset_duration for Table-based authentication)
	static function displayPasswordExpireWarningPopup()
	{
		global $lang;
		// If expiration time is in session, then display pop-up
		if (isset($_SESSION['expire_time']) && !empty($_SESSION['expire_time']))
		{
			?>
			<div id="expire_pwd_msg" style="display:none;" title="<?php echo cleanHtml2($lang['pwd_reset_20']) ?>">
				<p><?php echo "{$lang['pwd_reset_19']} (<b>{$_SESSION['expire_time']}</b>){$lang['period']} {$lang['pwd_reset_21']}" ?></p>
			</div>
			<script type="text/javascript">
			$(function(){
				$('#expire_pwd_msg').dialog({ bgiframe: true, modal: true, width: 450, buttons: {
					'Later': function() { $(this).dialog('destroy'); },
					'Change my password': function() { 
						$.get(app_path_webroot+'ControlCenter/user_controls_ajax.php', { action: 'reset_password_as_temp' }, function(data) { 
							if (data != '0') {
								window.location.reload(); 
							} else { 
								alert(woops); 
							} 
						});
					}
				}});
			});
			</script>
			<?php
			// Remove variable from session so that the user doesn't keep getting prompted
			unset($_SESSION['expire_time']);
		}
	}
	
	
	// Check if need to display pop-up dialog to SET UP SECURITY QUESTION for table-based users
	static function checkSetUpSecurityQuestion()
	{
		global $lang, $user_email;
		// Display pop-up dialog to set up security question
		if (defined("SET_UP_SECURITY_QUESTION")) 
		{
			// Display drop-down of security questions
			$dd_questions = array(""=>RCView::escape(" - ".$lang['pwd_reset_22']." - "));
			foreach (self::getSecurityQuestions() as $qid=>$question) {
				$dd_questions[$qid] = RCView::escape($question);
			}
			$securityQuestionDD = RCView::select(array('id'=>'securityQuestion','class'=>'x-form-text x-form-field','style'=>'padding-right:0;height:22px'), $dd_questions, "", 400);
			// Instructions and form
			$html = RCView::div(array('id'=>'setUpSecurityQuestionDiv'),
						RCView::p(array(), $lang['pwd_reset_37']) . 
						RCView::div(array('id'=>'setUpSecurityQuestionDiv','style'=>'max-width:700px;margin:20px 3px 20px;padding:15px 20px 5px;border:1px solid #ccc;background-color:#f5f5f5;'),
							RCView::div(array('style'=>'font-weight:bold;padding-bottom:10px;'), 
								RCView::span(array(), $lang['pwd_reset_34']) . RCView::SP . RCView::SP . 
								$securityQuestionDD
							) .
							RCView::div(array('style'=>'font-weight:bold;padding-bottom:10px;'), 
								RCView::span(array(), $lang['pwd_reset_35']) . RCView::SP . RCView::SP . 
								"<input type='text' id='securityAnswer' class='x-form-text x-form-field' style='width:200px;' autocomplete='off'>"  . RCView::SP . RCView::SP . 
								RCView::span(array('style'=>'color:#666;font-size:11px;font-family:tahoma;font-weight:normal;'), $lang['pwd_reset_50'])
							) .
							RCView::div(array('style'=>'padding:20px 0 10px;'), 
								RCView::span(array(), $lang['pwd_reset_48']) . RCView::SP . RCView::SP . 
								"<input type='text' id='user_email' class='x-form-text x-form-field' style='width:200px;' value='".cleanHtml($user_email)."' autocomplete='off'>"  . 
								RCView::div(array('style'=>'color:#666;font-size:11px;font-family:tahoma;padding-top:3px;'), $lang['pwd_reset_49'])
							)
						) . 
						RCView::div(array('style'=>'margin:15px 15px 20px;'), 
							RCView::submit(array('class'=>'jqbutton','value'=>$lang['designate_forms_13'],'style'=>'font-family:verdana;line-height:25px;font-size:13px;','onclick'=>'setUpSecurityQuestionAjax();')) .
							RCView::span(array('style'=>'margin-left:30px;'), RCView::a(array('href'=>'javascript:;','style'=>'color:#800000;text-decoration:underline;','onclick'=>"$('#setUpSecurityQuestion').dialog('close');"), $lang['pwd_reset_46']))
						)
					);
			?>
			<!-- Div for dialog content -->
			<div id="setUpSecurityQuestion" style="display:none;" title="<?php echo cleanHtml2($lang['pwd_reset_36']) ?>"><?php echo $html ?></div>
			<!-- Javascript for dialog -->
			<script type="text/javascript">
			$(function(){
				$('#setUpSecurityQuestion').dialog({ bgiframe: true, modal: true, width: 700, 
					close: function() { setSecurityQuestionReminder(); }
				});
			});
			// Remind question/answer in 2 days
			function setSecurityQuestionReminder() {
				// Ajax request
				$.post(app_path_webroot+'Authentication/password_recovery_setup.php',{ setreminder: '1' }, function(data){
					$('#setUpSecurityQuestion').dialog('destroy');
					if (data == '1') {
						simpleDialog('<?php echo cleanHtml($lang['pwd_reset_47']) ?>');
					}
				});
			}
			// Submit question/answer
			function setUpSecurityQuestionAjax() {
				// Check values
				$('#securityAnswer').val(trim($('#securityAnswer').val()));
				$('#user_email').val(trim($('#user_email').val()));
				var user_email = $('#user_email').val();
				var answer = $('#securityAnswer').val();
				var question = $('#securityQuestion').val();
				if (answer.length < 1 || question.length < 1 || user_email.length < 1) {
					simpleDialog('<?php echo cleanHtml($lang['pwd_reset_38']) ?>');
					return false;
				}
				// Ajax request
				$.post(app_path_webroot+'Authentication/password_recovery_setup.php',{ answer: answer, question: question, user_email: user_email }, function(data){
					$('#setUpSecurityQuestionDiv').html(data);
					initWidgets();
				});
			}
			</script>
			<?php
		}
	}
	
		
	/**
	 * AUTHENTICATE THE USER
	 */
	static function authenticate() 
	{
		global $auth_meth, $app_name, $username, $password, $hostname, $db, $institution, $double_data_entry, 
			   $project_contact_name, $autologout_timer, $lang, $isMobileDevice, $password_reset_duration, $enable_user_whitelist,
			   $homepage_contact_email, $homepage_contact, $isAjax, $rc_autoload_function, $two_factor_auth_enabled;

		// Check if authentication was manually disabled for the current page. If so, exit this function.
		if (defined("NOAUTH")) return true;	
		
		// Start the session before PEAR Auth does so we can check if auth session was lost or not (from load balance issues)
		if (!session_id()) @session_start();
		
		// Set default value to determine later if we need to make left-hand menu disappear so user has access to nothing
		$GLOBALS['no_access'] = 0;
		
		// If logging in, trim the username to prevent confusion and accidentally creating a new user
		if (isset($_POST['redcap_login_a38us_09i85']) && $auth_meth != "none")
		{
			$_POST['username'] = trim($_POST['username']);
			// Make sure it's not longer than 255 characters to prevent attacks via hitting upper bounds
			if (strlen($_POST['username']) > 255) {
				$_POST['username'] = substr($_POST['username'], 0, 255);
			}
		}

		## AUTHENTICATE and GET USERNAME: Determine method of authentication
		// No authentication is used
		if ($auth_meth == 'none') {
			$userid = 'site_admin'; //Default user
		}
		// Vanderbilt authentication specific to mc.vanderbilt.edu server
		elseif ($auth_meth == 'local') {
			$userid = $_SESSION['userid'];
		}
		// Vanderbilt authentication using custom C4 cookie-based that is specific to https://redcap.ctsacentral.org
		elseif ($auth_meth == 'c4') {
			// Check userid from cookie
			$userid = self::authenticateC4Cookie();
			if ($userid === false) {
				// For no obvious reason, we need to output something first or else EVERYTHING in the /redcap directory will not load. (WHY?)
				print " ";
				// If not logged in yet, then redirect to C4 login page
				redirect("https://".SERVER_NAME."/plugins/auth/?redirectUrl=".urlencode($_SERVER['REQUEST_URI']));
			}
		}
		// Hand off to plugin for authentication
		elseif (stripos($auth_meth, 'urn:rcauthplugin:') === 0) {
			error_reporting(E_ALL);
			// Is user currently logged in (according to REDCap's session)?
			if (!isset($_SESSION['username'])) {
				$pluginAuth = RCAuthPlugin::createAuth($auth_meth);
				if ($pluginAuth->isAuthenticated()) {
					$userid = $pluginAuth->getUsername();
				} else {
					$pluginAuth->authenticate();
				}
			} else {
				// Set the REDCap userid
				$userid = $_SESSION['username'];
			}
			error_reporting(0);
		}
		// RSA SecurID two-factor authentication (using PHP Pam extension)
		elseif ($auth_meth == 'rsa') {
			// If username in session doesn't exist and not on login page, then force login
			if (!isset($_SESSION['rsa_username']) && !isset($_POST['redcap_login_a38us_09i85'])) {
				loginFunction();
			}
			// User is attempting to log in, so try to authenticate them using PAM
			elseif (isset($_POST['redcap_login_a38us_09i85'])) 
			{
				// Make sure RSA password is not longer than 14 characters to prevent attacks via hitting upper bounds 
				// (8 char max for PIN + 6-digit tokencode)
				if (strlen($_POST['password']) > 14) {
					$_POST['password'] = substr($_POST['password'], 0, 14);
				}
				// If PHP PECL package PAM is not installed, then give error message
				if (!function_exists("pam_auth")) {
					if (isDev()) {
						// For development purposes only, allow passthru w/o valid authentication
						$userid = $_SESSION['username'] = $_SESSION['rsa_username'] = $_POST['username'];
					} else {
						// Display error
						renderPage(
							RCView::div(array('class'=>'red'),
								RCView::div(array('style'=>'font-weight:bold;'), $lang['global_01'].$lang['colon']) .
								"The PECL PAM package in PHP is not installed! The PAM package must be installed in order to use
								the pam_auth() function in PHP to authenticate tokens via RSA SecurID. You can find the offical 
								documentation on PAM at <a href='http://pecl.php.net/package/PAM' target='_blank'>http://pecl.php.net/package/PAM</a>."
							)
						);
					}
				} 
				// If have logged in, then try to authenticate the user
				elseif (pam_auth($_POST['username'], $_POST['password'], $err, false) === true) {
					$userid = $_SESSION['username'] = $_SESSION['rsa_username'] = $_POST['username'];
					// Log that they successfully logged in in log_view table
					addPageView("LOGIN_SUCCESS", $userid);
					// Set the user's last_login timestamp
					self::setUserLastLoginTimestamp($userid);
				} 
				// Error
				else {
					// Render error message and show login screen again
					print   RCView::div(array('class'=>'red','style'=>'max-width:100%;width:100%;font-weight:bold;'),
								RCView::img(array('src'=>'exclamation.png','class'=>'imgfix')) .
								"{$lang['global_01']}{$lang['colon']} {$lang['config_functions_49']}"
							);
					loginFunction();
				}
			}
			// If already logged in, the just set their username
			elseif (isset($_SESSION['rsa_username'])) {
				$userid = $_SESSION['username'] = $_SESSION['rsa_username'];
			}
		}
		// Shibboleth authentication (Apache module)
		elseif ($auth_meth == 'shibboleth') {
			// Check is custom username field is set for Shibboleth. If so, use it to determine username.
			$GLOBALS['shibboleth_username_field'] = trim($GLOBALS['shibboleth_username_field']);
			if (isDev()) {
				// For development purposes only, allow passthru w/o valid authentication
				$userid = $_SESSION['username'] = 'taylorr4';
			} elseif (strlen($GLOBALS['shibboleth_username_field']) > 0) {
				// Custom username field
				$userid = $_SESSION['username'] = $_SERVER[$GLOBALS['shibboleth_username_field']];
			} else {
				// Default value	
				$userid = $_SESSION['username'] = $_SERVER['REMOTE_USER'];			
			}
			// Update user's "last login" time if not yet updated for this session (for Shibboleth only since we can't know when users just logged in).
			// Only do this if coming from outside REDCap.
			if (!isset($_SERVER['HTTP_REFERER']) || (isset($_SERVER['HTTP_REFERER']) 
				&& substr($_SERVER['HTTP_REFERER'], 0, strlen(APP_PATH_WEBROOT_FULL)) != APP_PATH_WEBROOT_FULL)
			) {
				self::setLastLoginTime($userid);
			}
		}
		// SAMS authentication (specifically used by the CDC)
		elseif ($auth_meth == 'sams') {
			// Hack for development testing
			// if (isDev() && isset($_GET['sams'])) {
				// $_SERVER['HTTP_EMAIL'] = 'rob.taylor@vanderbilt.edu';
				// $_SERVER['HTTP_FIRSTNAME'] = 'Rob';
				// $_SERVER['HTTP_LASTNAME'] = 'Taylor';
				// $_SERVER['HTTP_USERACCOUNTID'] = '0014787563';
			// }
			// Make sure we have all 4 HTTP headers from SAMS
			$http_headers = get_request_headers();
			if (isset($http_headers['Useraccountid']) && isset($http_headers['Email']) && isset($http_headers['Firstname']) && isset($http_headers['Lastname'])) {
				// If we have the SAMS headers, add the sams user account id to PHP Session (to keep throughout this user's session to know they've already authenticated)
				$userid = $_SESSION['username'] = $_SESSION['redcap_userid'] = $http_headers['Useraccountid'];
				// Log that they successfully logged in in log_view table
				addPageView("LOGIN_SUCCESS", $userid);
				// Set the user's last_login timestamp
				self::setUserLastLoginTimestamp($userid);
			} elseif (isset($_SESSION['redcap_userid']) && !empty($_SESSION['redcap_userid'])) {
				// Set the userid as the SAMS useraccountid value from the session
				$userid = $_SESSION['username'] = $_SESSION['redcap_userid'];
			} else {
				// Error: Could not find an existing session or the SAMS headers
				exit("{$lang['global_01']}{$lang['colon']} Your SAMS authentication session has ended!");
			}
		}
		// OpenID
		elseif ($auth_meth == 'openid' || $auth_meth == 'openid_google') {
			// Authenticate via OpenID provider
			$userid = self::authenticateOpenID();
			// Now redirect back to our original page in order to remove all the "openid..." parameters in the query string
			if (isset($_GET['openid_return_to'])) redirect(urldecode($_GET['openid_return_to']));
		}
		// Error was made in Control Center for authentication somehow
		elseif ($auth_meth == '') {
			if ($userid == '') {
				// If user is navigating directing to a project page but hasn't created their account info yet, redirect to home page.
				redirect(APP_PATH_WEBROOT_FULL);
			} else {
				// Project has no authentication somehow, which needs to be fixed in the Control Center.
				exit("{$lang['config_functions_20']} 
					  <a target='_blank' href='". APP_PATH_WEBROOT . "ControlCenter/edit_project.php?project=".PROJECT_ID."'>REDCap {$lang['global_07']}</a>.");
			}
		}
		// Table-based and/or LDAP authentication
		else {
			// Set DSN arrays for Table-based auth and/or LDAP auth
			self::setDSNs();
			// This variable sets the timeout limit if server activity is idle
			$autologout_timer = ($autologout_timer == "") ? 0 : $autologout_timer;
			// In case of users having characters in password that were stripped out earlier, restore them (LDAP only)
			if (isset($_POST['password'])) $_POST['password'] = html_entity_decode($_POST['password'], ENT_QUOTES);
			// Check if user is logged in
			self::checkLogin("", $auth_meth);
			// Set username variable passed from PEAR Auth
			$userid = $_SESSION['username'];
			// Check if table-based user has a temporary password. If so, direct them to page to set it.
			if ($auth_meth == "table" || $auth_meth == "ldap_table") 
			{
				$q = db_query("select * from redcap_auth where username = '".prep($userid)."'");
				$isTableBasedUser = db_num_rows($q);
				// User is table-based user
				if ($isTableBasedUser) 
				{
					// Get values from auth table
					$temp_pwd 					= db_result($q, 0, 'temp_pwd');
					$password_question 			= db_result($q, 0, 'password_question');
					$password_answer 			= db_result($q, 0, 'password_answer');
					$password_question_reminder = db_result($q, 0, 'password_question_reminder');
					$legacy_hash 				= db_result($q, 0, 'legacy_hash');
					$hashed_password			= db_result($q, 0, 'password');
					$password_salt 				= db_result($q, 0, 'password_salt');
					
					// Check if need to trigger setup for SECURITY QUESTION (only on My Projects page or project's Home/Project Setup page)
					$myProjectsUri = "/index.php?action=myprojects";
					$pagePromptSetSecurityQuestion = (substr($_SERVER['REQUEST_URI'], strlen($myProjectsUri)*-1) == $myProjectsUri || PAGE == 'index.php' || PAGE == 'ProjectSetup/index.php');
					$conditionPromptSetSecurityQuestion = (!isset($_POST['redcap_login_a38us_09i85']) && !$isAjax && empty($password_question) && (empty($password_question_reminder) || NOW > $password_question_reminder));
					if ($pagePromptSetSecurityQuestion && $conditionPromptSetSecurityQuestion)
					{
						// Set flag to display pop-up dialog to set up security question
						define("SET_UP_SECURITY_QUESTION", true);
					}
					
					// If using table-based auth and enforcing password reset after X days, check if need to reset or not
					if (isset($_POST['redcap_login_a38us_09i85']) && !empty($password_reset_duration))
					{
						// Also add to auth_history table
						$sql = "select timestampdiff(MINUTE,timestamp,'".NOW."')/60/24 as daysExpired, 
								timestampadd(DAY,$password_reset_duration,timestamp) as expirationTime from redcap_auth_history 
								where username = '$userid' order by timestamp desc limit 1";
						$q = db_query($sql);
						$daysExpired = db_result($q, 0, "daysExpired");
						$expirationTime = db_result($q, 0, "expirationTime");
							
						// If the number of days expired has passed, then redirect them to the password reset page
						if (db_num_rows($q) > 0 && $daysExpired > $password_reset_duration)
						{
							// Set the temp password flag to prompt them to enter new password
							db_query("UPDATE redcap_auth SET temp_pwd = 1 WHERE username = '$userid'");
							// Redirect to password reset page with flag set
							redirect(APP_PATH_WEBROOT . "Authentication/password_reset.php?msg=expired");
						} 
						// If within 7 days of expiring, then give a notice on next page load.
						elseif ($daysExpired > $password_reset_duration-7)
						{
							// Put expiration time in session in order to prompt user on next page load
							$_SESSION['expire_time'] = DateTimeRC::format_ts_from_ymd($expirationTime);
						}
					}
					// If temporary password flag is set, then redirect to allow user to set new password
					if ($temp_pwd == '1' && PAGE != "Authentication/password_reset.php") 
					{
						redirect(APP_PATH_WEBROOT . "Authentication/password_reset.php" . ((isset($app_name) && $app_name != "") ? "?pid=" . PROJECT_ID : ""));
					}
					
					// UPDATE LEGACY PASSWORD HASH: If table-based user is logging in (successfully) and is using a legacy hashed password, 
					// then update password to newer salted hash.
					if (isset($_POST['redcap_login_a38us_09i85']) && $legacy_hash && md5($_POST['password'].$password_salt) == $hashed_password)
					{
						// Generate random salt for this user
						$new_salt = self::generatePasswordSalt();
						// Create the one-way hash for this new password
						$new_hashed_password = self::hashPassword($_POST['password'], $new_salt);
						// Update a table-based user's hashed password and salt
						self::setUserPasswordAndSalt($userid, $new_hashed_password, $new_salt);
					}
				}
			}
		}
		
		// Reset autoload function in case one of the authentication frameworks changed it
		spl_autoload_register($rc_autoload_function);
		
		// If $userid is somehow blank (e.g., authentication server is down), then prevent from accessing.
		if (trim($userid) == '') 
		{
			// If using Shibboleth authentication and user is on API Help page but somehow lost their username 
			// (or can't be used in /api directory due to Shibboleth setup), then just redirect to the target page itself.
			if ($auth_meth == 'shibboleth' && strpos(PAGE_FULL, '/api/help/index.php') !== false) {
				redirect(APP_PATH_WEBROOT . "API/help.php");
			}
			// Display error message
			$objHtmlPage = new HtmlPage();
			$objHtmlPage->addStylesheet("style.css", 'screen,print');
			$objHtmlPage->addStylesheet("home.css", 'screen,print');
			$objHtmlPage->PrintHeader();
			print RCView::br() . RCView::br()
				. RCView::errorBox($lang['config_functions_82']." <a href='mailto:$homepage_contact_email'>$homepage_contact</a>{$lang['period']}")
				. RCView::button(array('onclick'=>"window.location.href='".APP_PATH_WEBROOT_FULL."index.php?logout=1';"), "Try again");
			$objHtmlPage->PrintFooter();
			exit;
		}
		
		// LOGOUT: Check if need to log out
		self::checkLogout();
		
		// USER WHITELIST: If using external auth and user whitelist is enabled, the validate user as in whitelist
		if ($enable_user_whitelist && $auth_meth != 'none' && $auth_meth != 'table')
		{
			// The user has successfully logged in, so determine if they're an external auth user
			$isExternalUser = ($auth_meth != "ldap_table" || ($auth_meth == "ldap_table" && isset($isTableBasedUser) && !$isTableBasedUser));
			// They're an external auth user, so make sure they're in the whitelist
			if ($isExternalUser)
			{
				$sql = "select 1 from redcap_user_whitelist where username = '" . prep($userid) . "'";
				$inWhitelist = db_num_rows(db_query($sql));
				// If not in whitelist, then give them error page
				if (!$inWhitelist)
				{					
					// Give notice that user cannot access REDCap
					$objHtmlPage = new HtmlPage();
					$objHtmlPage->addStylesheet("style.css", 'screen,print');
					$objHtmlPage->addStylesheet("home.css", 'screen,print');
					$objHtmlPage->PrintHeader();
					print  "<div class='red' style='margin:40px 0 20px;padding:20px;'>
								{$lang['config_functions_78']} \"<b>$userid</b>\"{$lang['period']} 
								{$lang['config_functions_79']} <a href='mailto:$homepage_contact_email'>$homepage_contact</a>{$lang['period']}
							</div>
							<button onclick=\"window.location.href='".APP_PATH_WEBROOT_FULL."index.php?logout=1';\">Go back</button>";
					$objHtmlPage->PrintFooter();
					exit;
				}
			}
		}
		
		// If logging in, update Last Login time in user_information table
		// (but NOT if they are suspended - could be confusing if last login occurs AFTER suspension)
		if (isset($_POST['redcap_login_a38us_09i85']))
		{
			self::setUserLastLoginTimestamp($userid);
		}

		// If just logged in, redirect back to same page to avoid $_POST confliction on certain pages.
		// Do NOT simply redirect if user lost their session when saving data so that their data will be resurrected.
		if (isset($_POST['redcap_login_a38us_09i85']) && !isset($_POST['redcap_login_post_encrypt_e3ai09t0y2']))
		{	
			## REDIRECT PAGE
			// Redirect any logins via mobile devices to Mobile directory 
			// (unless user is on a plugin page OR is super user going straight to the production changes page)
			if ($isMobileDevice && !isset($_GET['pid']) && !defined("PLUGIN") && !(SUPER_USER && PAGE == 'Design/project_modifications.php'))
			{
				redirect(APP_PATH_WEBROOT . "Mobile/");
			}
			// Redirect to mobile project page if mobile device with project-level URL
			elseif ($isMobileDevice && isset($_GET['pid']) && !defined("PLUGIN") && !(SUPER_USER && PAGE == 'Design/project_modifications.php'))
			{
				redirect(APP_PATH_WEBROOT . "Mobile/choose_record.php?pid=".$_GET['pid']);
			}
			// Redirect back to this same page in non-mobile view
			else
			{
				redirect($_SERVER['REQUEST_URI']);
			}
		}
		
		// CHECK USER INFO: Make sure that we have the user's email address and name in redcap_user_information. If not, prompt user for it.
		if (PAGE != "Profile/user_info_action.php" && PAGE != "Authentication/password_reset.php") {
			// Set super_user default value
			$super_user = 0;
			// Get user info
			$row = User::getUserInfo($userid);
			// If user has no email address or is not in user_info table, then prompt user for their name and email
			if (empty($row) || $row['user_email'] == "" || ($row['user_email'] != "" && $row['email_verify_code'] != "")) {
				// Prompt user for values
				include APP_PATH_DOCROOT . "Profile/user_info.php";
				exit;	
			} else {
				// Define user's name and email address for use throughout the application
				$user_email 	= $row['user_email'];
				$user_firstname = $row['user_firstname'];
				$user_lastname 	= $row['user_lastname'];
				$super_user 	= $row['super_user'];
				$user_firstactivity = $row['user_firstactivity'];
				$user_lastactivity = $row['user_lastactivity'];
				$user_firstvisit = $row['user_firstvisit'];
				$user_lastlogin = $row['user_lastlogin'];
				$user_access_dashboard_view = $row['user_access_dashboard_view'];
				$allow_create_db 	= $row['allow_create_db'];
				$datetime_format 	= $row['datetime_format'];
				$number_format_decimal = $row['number_format_decimal'];
				// If thousands separator is blank, then assume a space (since MySQL cannot do a space for an ENUM data type)
				$number_format_thousands_sep = ($row['number_format_thousands_sep'] == 'SPACE') ? ' ' : $row['number_format_thousands_sep'];
				// Do not let the secondary/tertiary emails be set unless they have been verified first
				$user_email2 	= ($row['user_email2'] != '' && $row['email2_verify_code'] == '') ? $row['user_email2'] : "";
				$user_email3 	= ($row['user_email3'] != '' && $row['email3_verify_code'] == '') ? $row['user_email3'] : "";
			}
			// TWO-FACTOR AUTHENTICATION: Add user's two factor auth secret hash
			if ($two_factor_auth_enabled && $row['two_factor_auth_secret'] == "") 
			{
				$row['two_factor_auth_secret'] = self::createTwoFactorSecret($userid);
			}
			// If we have not recorded time of user's first visit, then set it
			if ($row['user_firstvisit'] == "") 
			{
				User::updateUserFirstVisit($userid);
			}
			// If we have not recorded time of user's last login, then set it based upon first page view of current session
			if ($row['user_lastlogin'] == "") 
			{
				self::setLastLoginTime($userid);
			}
			// Check if user account has been suspended
			if ($row['user_suspended_time'] != "") 
			{
				// Give notice that user cannot access REDCap
				global $homepage_contact_email, $homepage_contact;
				$objHtmlPage = new HtmlPage();
				$objHtmlPage->addStylesheet("style.css", 'screen,print');
				$objHtmlPage->addStylesheet("home.css", 'screen,print');
				$objHtmlPage->PrintHeader();
				$user_firstlast = ($user_firstname == "" && $user_lastname == "") ? "" : " (<b>$user_firstname $user_lastname</b>)";
				print  "<div class='red' style='margin:40px 0 20px;padding:20px;'>
							{$lang['config_functions_75']} \"<b>$userid</b>\"{$user_firstlast}{$lang['period']} 
							{$lang['config_functions_76']} <a href='mailto:$homepage_contact_email'>$homepage_contact</a>{$lang['period']}
						</div>
						<button onclick=\"window.location.href='".APP_PATH_WEBROOT_FULL."index.php?logout=1';\">Go back</button>";
				$objHtmlPage->PrintFooter();
				exit;
			}
			
		}
		
		//Define user variables
		defined("USERID") or define("USERID", $userid);
		define("SUPER_USER", $super_user);
		$GLOBALS['userid'] = $userid;
		$GLOBALS['super_user'] = $super_user;
		$GLOBALS['user_email'] = $user_email;
		$GLOBALS['user_email2'] = $user_email2;
		$GLOBALS['user_email3'] = $user_email3;
		$GLOBALS['user_firstname'] = $user_firstname;
		$GLOBALS['user_lastname'] = $user_lastname;
		$GLOBALS['user_firstactivity'] = $user_firstactivity;
		$GLOBALS['user_access_dashboard_view'] = $user_access_dashboard_view;
		$GLOBALS['allow_create_db'] = $allow_create_db;
		$GLOBALS['datetime_format'] = $datetime_format;
		$GLOBALS['number_format_decimal'] = $number_format_decimal;
		$GLOBALS['number_format_thousands_sep'] = $number_format_thousands_sep;
		
		
		## DEAL WITH COOKIES
		// Remove authchallenge cookie created by Pear Auth because it's not necessary
		if (isset($_COOKIE['authchallenge'])) {
			unset($_COOKIE['authchallenge']);
			deletecookie('authchallenge');
		}
		## This section has been removed temporarily (hopefully) since it causes issues with reverse proxies (loses the session right after login).
		// If using SSL, make sure we set the PHPSESSID cookie's "secure" attribute to true
		// if (isset($_COOKIE['PHPSESSID']) && defined("SSL") && SSL) {
			// setcookie('PHPSESSID', session_id(), 0, '/', '', true, true);  
		// }
		
		// TWO FACTOR AUTHENTICATION: Enforce it here if enabled and user has not authenticated via two factor
		$two_factor_allowable_pages = array("Authentication/generate_qrcode.php", "Authentication/two_factor_verify_code.php");
		if ($two_factor_auth_enabled && !isset($_SESSION['two_factor_auth']) && !in_array(PAGE, $two_factor_allowable_pages)) {
			// Display the two-factor login screen
			Authentication::renderTwoFactorLoginPage($row['two_factor_auth_secret']);
		}
		
		// Stop here if user is on a non-project level page (e.g., My Projects, Home, Control Center)
		if (!isset($_GET['pnid']) && !isset($_GET['pid'])) return true;
		
		// PROJECT-LEVEL USER PRIVILEGES: Determine the user's rights for each page/module
		$ur = new UserRights();
		return $ur->checkPrivileges(APP_NAME);
	}
	
	
	// Create user's two factor authentication secret
	public static function createTwoFactorSecret($userid)
	{
		// Generate secret value		
		$ga = new GoogleAuthenticator();
		$two_factor_auth_secret = $ga->createSecret();
		// Update table with secret value
		$sql = "update redcap_user_information set two_factor_auth_secret = '".prep($two_factor_auth_secret)."'
				where username = '".prep($userid)."'";
		$q = db_query($sql);
		return $two_factor_auth_secret;	
	}
	
	
	// Verify a user's two-factor auth verification code that they submittted
	public static function verifyTwoFactorCode($code)
	{
		// Get user info
		$user_info = User::getUserInfo(USERID);
		// Remove all non-numerals from code
		$code = preg_replace("/[^0-9]/", '', $code);
		if ($code == '') return false;
		// Verify the code and return boolean regarding success
		$ga = new GoogleAuthenticator();
		return $ga->verifyCode($user_info['two_factor_auth_secret'], $code, 4);    // 2 = 2*30sec clock tolerance
	}
	
	
	// Display the two-factor login screen
	public static function renderTwoFactorLoginPage()
	{
		global $lang;
		// Get user info
		$user_info = User::getUserInfo(USERID);
		// Get REDCap server's domain name
		$parse = parse_url(APP_PATH_WEBROOT_FULL);
		$redcap_server_hostname = $parse['host'];
		// Generate human readable name of REDCap server for the 2FA app
		$app_name = USERID . "@" . $redcap_server_hostname;
		// Generate string to be converted into QR code to enable 2FA in an app
		$otpauth = 'otpauth://totp/'.$app_name.'?secret='.$user_info['two_factor_auth_secret'];
		// Output page with login dialog
		$HtmlPage = new HtmlPage();
		$HtmlPage->PrintHeaderExt();
		?>
		<style type="text/css">div#outer { display:none; } </style>
		<script type="text/javascript">
		var lang2FA_01 = '<?php print cleanHtml($lang['system_config_343']) ?>';
		var lang2FA_02 = '<?php print cleanHtml($lang['system_config_331']) ?>';
		var lang2FA_03 = '<?php print cleanHtml($lang['system_config_347']) ?>';
		$(function(){
			display2FALoginDialog();
		});
		</script>
		<?php
		print 	
				// Main dialog (choose two factor method to use)
				RCView::div(array('id'=>'two_factor_login_dialog', 'class'=>'simpleDialog', 'style'=>'font-size:14px;'), 
					// Instructions
					RCView::div(array('style'=>''), 
						$lang['system_config_334']
					) .
					// Options header
					RCView::div(array('style'=>'font-weight:bold;color:#C00000;margin-top:25px;margin-bottom:10px;'), 
						$lang['system_config_335']
					) .
					// TOTP app option
					RCView::div(array('class'=>'hang', 'style'=>'margin-bottom:12px;'), 
						RCView::radio(array('name'=>'two_factor_option', 'value'=>'app', 'checked'=>'checked', 'class'=>'imgfix2')) .
						RCView::b($lang['system_config_341']) . " " . $lang['system_config_342'] . " " .
						RCView::a(array('style'=>'font-size:14px;text-decoration:underline;', 'href'=>'javascript:;', 'onclick'=>"simpleDialog(null,null,'two_factor_totp_setup');"), $lang['system_config_333']) . " " .
						$lang['system_config_344']
					) .
					// SMS option
					RCView::div(array('class'=>'hang', 'style'=>'margin-bottom:12px;'), 
						RCView::radio(array('name'=>'two_factor_option', 'value'=>'sms', 'class'=>'imgfix2')) .
						RCView::b($lang['system_config_336']) . " " . $lang['system_config_337'] .
						RCView::div(array('style'=>'margin-top:5px;margin-left: 1.8em;'), 
							RCView::text(array('name'=>'two_factor_option_phone', 'class'=>'x-form-text x-form-field', 
								'style'=>'width:110px;font-size:13px;', 'value'=>formatPhone($user_info['user_phone']))) .
							RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:13px;margin:0 0 0 5px;font-family:arial;', 
								'onclick'=>""), $lang['system_config_348'])
						)
					) .
					// Email option
					RCView::div(array('class'=>'hang', 'style'=>'margin-bottom:12px;'), 
						RCView::radio(array('name'=>'two_factor_option', 'value'=>'email', 'class'=>'imgfix2')) .
						RCView::b($lang['system_config_338']) . " " . $lang['system_config_339'] .
						RCView::div(array('style'=>'margin-top:5px;margin-left: 1.8em;'), 
							RCView::text(array('name'=>'two_factor_option_phone', 'class'=>'x-form-text x-form-field', 
								'style'=>'width:200px;font-size:13px;', 'value'=>$user_info['user_email'])) .
							RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:13px;margin:0 0 0 5px;font-family:arial;', 
								'onclick'=>""), $lang['database_mods_150'])
						)
					) .
					// Verify the code
					RCView::div(array('style'=>'font-weight:bold;color:#C00000;margin-top:25px;margin-bottom:10px;'), 
						$lang['system_config_345']
					) .
					// Text input for verification code to be entered
					RCView::div(array('style'=>'margin-bottom:30px;margin-left: 1.8em;'), 
						$lang['system_config_346'] .
						RCView::div(array('style'=>'margin-top:12px;'), 
							RCView::text(array('id'=>'two_factor_verification_code', 'class'=>'x-form-text x-form-field', 
								'style'=>'width:110px;font-size:15px;font-size:15px;padding: 3px 8px;', 'onkeydown'=>"if(event.keyCode == 13) $('#two_factor_verification_code_btn').click();")) .
							RCView::button(array('id'=>'two_factor_verification_code_btn', 'class'=>'jqbuttonmed', 'style'=>'color:#333;margin:0 0 0 5px;font-family:arial;', 
								'onclick'=>"verify2FAcode($('#two_factor_verification_code').val());"), $lang['system_config_332'])
						)
					)
				) .
				// QR code dialog for TOTP apps
				RCView::div(array('id'=>'two_factor_totp_setup', 'class'=>'simpleDialog', 'style'=>''), 
					// Instructions
					
					
					// Display QR code 
					"<img src='".APP_PATH_WEBROOT."Authentication/generate_qrcode.php?value=".urlencode($otpauth)."'>"
				);
		$HtmlPage->PrintFooterExt();
		exit;
	}
	
	
	/**
	 * SET USER'S "LAST LOGIN" TIME IF NOT SET (MAINLY FOR SHIBBOLETH SINCE WE CAN'T KNOW WHEN USERS JUST LOGGED IN)
	 */
	static function setLastLoginTime($userid)
	{
		// Get session id
		if (!session_id()) return false;
		// Only get first 32 chars (in case longer in some systems)
		$session_id = substr(session_id(), 0, 32);
		// Set last login time for user
		$sql = "update redcap_user_information i, (select min(ts) as ts, user from redcap_log_view 
				where user = '" . prep($userid) . "' and session_id = '$session_id') l 
				set i.user_lastlogin = l.ts where i.username = l.user and l.ts is not null";
		$q = db_query($sql);
	}
	
		
	/**
	 * RESET USER'S PASSWORD TO A RANDOM TEMPORARY VALUE AND RETURN THE PASSWORD THAT WAS SET
	 */
	static function resetPassword($username,$loggingDescription="Reset user password")
	{
		// Set new temp password valkue
		$pass = generateRandomHash(8);
		// Update table with new password
		$sql = "update redcap_auth set password = '" . Authentication::hashPassword($pass, '', $username) . "', 
				temp_pwd = 1 where username = '" . prep($username) . "'";
		$q = db_query($sql);
		if ($q) {
			// For logging purposes, make sure we've got a username to attribute the logging to
			defined("USERID") or define("USERID", $username);
			// Logging
			log_event($sql,"redcap_auth","MANAGE",$username,"username = '" . prep($username) . "'",$loggingDescription);
			// Return password
			return $pass;
		}
		// Return false if failed
		return false;
	}
	
		
	/**
	 * CHECK IF USER IS LOGGED IN
	 */
	static function checkLogin($action="",$auth_meth) 
	{
		global $mysqldsn, $ldapdsn, $autologout_timer, $isMobileDevice, $logout_fail_limit, $logout_fail_window, 
			   $lang, $project_contact_email, $project_contact_name;
		
		// Start the session
		if (!session_id()) @session_start();
		
		// Check to make sure user hasn't had a failed login X times in Y minutes (based upon Control Center values)
		if (isset($_POST['redcap_login_a38us_09i85']) && $auth_meth != "none" && $logout_fail_limit != "0" 
			&& $logout_fail_window != "0" && trim($_POST['username']) != '')
		{
			// Get window of time to query
			$YminAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-$logout_fail_window,date("s"),date("m"),date("d"),date("Y")));
			// Get timestamp of last successful login in our window of time
			$sql = "select log_view_id from redcap_log_view
					".(db_get_version() > 5.0 ? "force index for order by (PRIMARY)" : "")." 
					where ts >= '$YminAgo' and user = '" . prep($_POST['username']) . "' 
					and event = 'LOGIN_SUCCESS' order by log_view_id desc limit 1";
			$tsLastSuccessfulLogin = db_result(db_query($sql), 0);
			$subsql = ($tsLastSuccessfulLogin == '') ? "" : "and log_view_id > '$tsLastSuccessfulLogin'";
			// Get count of failed logins in window of time
			$sql = "select count(1) from redcap_log_view where ts >= '$YminAgo' and user = '" . prep($_POST['username']) . "' 
					and event = 'LOGIN_FAIL' $subsql";			
			$failedLogins = db_result(db_query($sql), 0);
			// If failed logins in window of time exceeds set limit
			if ($failedLogins >= $logout_fail_limit)
			{
				// Give user lock-out message
				$objHtmlPage = new HtmlPage();
				$objHtmlPage->addExternalJS(APP_PATH_JS . "base.js");
				$objHtmlPage->addStylesheet("smoothness/jquery-ui-".JQUERYUI_VERSION.".custom.css", 'screen,print');
				$objHtmlPage->addStylesheet("style.css", 'screen,print');
				$objHtmlPage->addStylesheet("home.css", 'screen,print');
				$objHtmlPage->PrintHeader();			
				print  "<div class='red' style='margin:60px 0;'>
							<b>{$lang['global_05']}</b><br><br>
							{$lang['config_functions_69']} (<b>$logout_fail_window {$lang['config_functions_72']}</b>){$lang['period']} 
							{$lang['config_functions_70']}<br><br>
							{$lang['config_functions_71']}
							<a href='mailto:$project_contact_email'>$project_contact_name</a>{$lang['period']}
						</div>";			
				$objHtmlPage->PrintFooter();
				exit;
			}
		}
			
		// Set time for auto-logout
		$auto_logout_minutes = ($autologout_timer == "") ? 0 : $autologout_timer;
		
		// Default
		$dsn = array();
		
		// LDAP with Table-based roll-over
		if ($auth_meth == "ldap_table") 
		{
			$dsn[] = array('type'=>'DB',   'dsnstuff'=>$mysqldsn);
			if (is_array(end($ldapdsn))) {
				// Loop through all LDAP configs and add
				foreach ($ldapdsn as $this_ldapdsn) {
					$dsn[] = array('type'=>'LDAP', 'dsnstuff'=>$this_ldapdsn);
				}
			} else {
				// Add single LDAP config
				$dsn[] = array('type'=>'LDAP', 'dsnstuff'=>$ldapdsn);		
			}
		}
		// LDAP
		elseif ($auth_meth == "ldap") 
		{
			if (is_array(end($ldapdsn))) {
				// Loop through all LDAP configs and add
				foreach ($ldapdsn as $this_ldapdsn) {
					$dsn[] = array('type'=>'LDAP', 'dsnstuff'=>$this_ldapdsn);
				}
			} else {
				// Add single LDAP config
				$dsn[] = array('type'=>'LDAP', 'dsnstuff'=>$ldapdsn);		
			}
		}
		// Table-based
		elseif ($auth_meth == "table") 
		{
			$dsn[] = array('type'=>'DB',   'dsnstuff'=>$mysqldsn);
		}
		
		// Default
		$GLOBALS['authFail'] = 0;
			
		//if ldap and table authentication Loop through the available servers & authentication methods
		foreach ($dsn as $key=>$dsnvalue) 
		{		
			if (isset($a)) unset($a);
			$GLOBALS['authFail'] = 1;
			$a = new Auth($dsnvalue['type'], $dsnvalue['dsnstuff'], "loginFunction");
			
			// Expiration settings
			$oneDay = 86400; // in seconds
			$auto_logout_minutes = ($auto_logout_minutes == 0) ? ($oneDay/60) : $auto_logout_minutes; // if 0, set to 24 hour logout
			
			$a->setExpire($oneDay);
			$a->setIdle(round($auto_logout_minutes * 60));
			
			// DEBUGGING
			// print "<br>Seconds until it would have logged you out: ".($a->idle+$a->session['idle']-time());
			// print "<br> Idle time: ".(time()-$a->session['idle']);
			// print "<br> 2-min warning at: ".date("H:i:s", mktime(date("H"),date("i"),date("s")+$a->idle-120,date("m"),date("d"),date("Y")));
			// print "<div style='text-align:left;'>";print_array($dsnvalue['dsnstuff']);print "</div>";
			
			$a->start();  	// If authentication fails the loginFunction is called and since
							// the global variable $authFail is true the loginFunction will
							// return control to this point again 
			if ($a->getAuth()) 
			{
				//print "<div style='text-align:left;'>";print_array($a);print "</div>";
				$_SESSION['username'] = $a->getUsername();
				// Make sure password is not left blank AND check for logout
				if ($action == "logout" || (isset($_POST['redcap_login_a38us_09i85']) && isset($_POST['password']) && trim($_POST['password']) == ""))
				{
					$GLOBALS['authFail'] = 0;
					$a->logout();
					$a->start();
				} 
				// Log the successful login
				elseif (isset($_POST['redcap_login_a38us_09i85'])) 
				{			
					addPageView("LOGIN_SUCCESS", $_SESSION['username']);
				}
				return 1;
			} else {
				//print  "<div class='red' style='max-width:100%;width:100%;font-weight:bold;'>FAIL</div>";
			}
		}
			
		// The user couldn't be authenticated on any server so set global variable $authFail to false
		// and let the loginFunction be called to display the login form 
		if (!$isMobileDevice) // don't show for mobile devices because it prevents reload of login form
		{
			print   RCView::div(array('class'=>'red','style'=>'max-width:100%;width:100%;font-weight:bold;'),
						RCView::img(array('src'=>'exclamation.png','class'=>'imgfix')) .
						"{$lang['global_01']}{$lang['colon']} {$lang['config_functions_49']}"
					);
		}
		//Log the failed login
		addPageView("LOGIN_FAIL",$_POST['username']);
		
		$GLOBALS['authFail'] = 0;
		$a->start();
		return 1;	
	}


	// If logout variable exists in URL, destroy the session
	// and reset the $userid variable to remove all user context
	static function checkLogout()
	{
		global $auth_meth;
		if (isset($_GET['logout']) && $_GET['logout']) 
		{			
			// Log the logout
			addPageView("LOGOUT", $_SESSION['username']);
			// Destroy session and erase userid
			$_SESSION = array();
			session_unset();
			session_destroy();
			// Destroy PHPSESSID cookie (although it appears to cause issues with SAMS authentication)
			if ($auth_meth != 'sams') {
				deletecookie('PHPSESSID');
			}
			// Default value (remove 'logout' from query string, if exists)
			$logoutRedirect = str_replace(array("logout=1&","&logout=1","logout=1","&amp;"), array("","","","&"), $_SERVER['REQUEST_URI']);
			if (substr($logoutRedirect, -1) == '&' || substr($logoutRedirect, -1) == '?') $logoutRedirect = substr($logoutRedirect, 0, -1);
			// If using Shibboleth, redirect to Shibboleth logout page
			if ($auth_meth == 'shibboleth' && strlen($GLOBALS['shibboleth_logout']) > 0) {
				$logoutRedirect = $GLOBALS['shibboleth_logout'];
			}
			// If using SAMS, redirect to SAMS logout page
			elseif ($auth_meth == 'sams' && strlen($GLOBALS['sams_logout']) > 0) {
				$logoutRedirect = $GLOBALS['sams_logout'];
			}
			// C4 cookie-based authentication
			elseif ($auth_meth == 'c4') {
				// Redirect to C4 logout page
				$logoutRedirect = "https://www.ctsacentral.org/authenticated-user-portal/logout";
			}
			// Reload same page or redirect to login page
			redirect($logoutRedirect);
		}
	}
	

	/**
	 * SEARCH REDCAP_AUTH TABLE FOR USER (return boolean)
	 */
	public static function isTableUser($user) 
	{
		$q = db_query("select 1 from redcap_auth where username = '".prep($user)."' limit 1");
		return ($q && db_num_rows($q) > 0);
	}
	
		
	// If logging in, update Last Login time in user_information table
	// (but NOT if they are suspended - could be confusing if last login occurs AFTER suspension)
	public static function setUserLastLoginTimestamp($userid)
	{
		$sql = "update redcap_user_information set user_lastlogin = '" . NOW . "'
				where username = '" . prep($userid) . "' and user_suspended_time is null";
		db_query($sql);
	}
	
	
	// Authenticate via OpenID provider
	private static function authenticateOpenID()
	{
		// Get global vars
		global  $auth_meth, $login_logo, $institution, $login_custom_text, $homepage_contact_email, $homepage_contact,
				$openid_provider_url, $openid_provider_name, $lang;
		// Initialize $userid
		$userid = '';
		// If using OpenID for Google specifically, then manually set the provider URL and name
		if ($auth_meth == 'openid_google') {
			$openid_provider_name = "Google";
			$openid_provider_url = "https://www.google.com/accounts/o8/id";
		}
		// Check session first				
		if (isset($_SESSION['redcap_userid']) && !empty($_SESSION['redcap_userid'])) {
			// If redcap_userid exists in the session, then user is authenticated and set it as their REDCap username
			$userid = $_SESSION['username'] = $_SESSION['redcap_userid'];
		} else {
			// User is logging in with OpenID
			try {
				// Double check the OpenID provider URL and name values
				if ($openid_provider_url == "") {
					exit("ERROR: OpenID provider URL has not been defined in the Control Center!");
				}
				if ($openid_provider_name == "") $openid_provider_name = "[OpenID provider]";
				// Instantiate openid object
				$openid = new LightOpenID(SERVER_NAME);
				
				if (!$openid->mode) {
					// If just clicked button to navigate to OpenID provider, then redirect them to the provider
					if (isset($_POST['redcap_login_openid_Re8D2_8uiMn'])) {
						$openid->identity = $openid_provider_url;
						$openid->required = array('contact/email', 'namePerson');
						$openid->optional = array('namePerson/friendly');
						redirect($openid->authUrl());
					}
					// If just coming into REDCap, give notice page that they'll need to authenticate via OpenID
					else {
						// Header
						$objHtmlPage = new HtmlPage();
						$objHtmlPage->PrintHeaderExt();
						// If using OpeID for Google specifically, then display Google logo
						$openid_logo = "";
						if ($auth_meth == 'openid_google') {
							$openid_logo = RCView::img(array('src'=>'google_logo.png', 'style'=>'vertical-align:bottom;margin-right:5px;'));
						}
						// Logo and "log in" text
						print 	RCView::div('', RCView::img(array('src'=>'redcaplogo.gif')));
						print 	RCView::h3(array('style'=>'margin:20px 0;padding:3px;border-bottom:1px solid #AAAAAA;color:#000000;font-weight:bold;'), 
									$openid_logo . $lang['config_functions_45']
								);
						// Institutional logo (optional)
						if (trim($login_logo) != "") {
							print RCView::div(array('style'=>'margin-bottom:20px;text-align:center;'), 
									"<img src='$login_logo' title=\"".cleanHtml2($institution)."\" alt=\"".cleanHtml2($institution)."\" style='max-width:850px; expression(this.width > 850 ? 850 : true);'>"
								  );
						}
						// Show custom login text (optional)
						if (trim($login_custom_text) != "") {
							print RCView::div(array('style'=>'border:1px solid #ccc;background-color:#f5f5f5;margin:15px 10px 15px 0;padding:10px;'), nl2br(filter_tags(label_decode($login_custom_text))));
						}
						// Login instructions
						print 	RCView::p(array('style'=>'margin:10px 0 30px;'),
									$lang['config_functions_84'] . " " .
									RCView::span(array('style'=>'color:#800000;font-weight:bold;font-size:13px;'), $openid_provider_name) . $lang['period'] . " " .
									$lang['config_functions_86'] . " " .
									$lang['config_functions_83'] . " " .
									RCView::a(array('style'=>'font-size:12px;text-decoration:underline;', 'href'=>"mailto:$homepage_contact_email"), $homepage_contact) . 
									$lang['period']
								);
						// Form with submit button
						print 	RCView::form(array('method'=>'post','action'=>PAGE_FULL,'style'=>'text-align:center;margin:20px 0 40px;'), 
									RCView::input(array('class'=>'jqbuttonmed', 'style'=>'padding:5px 10px !important;font-size:13px;', 'type'=>'submit', 'id'=>'redcap_login_openid_Re8D2_8uiMn', 'name'=>'redcap_login_openid_Re8D2_8uiMn', 'value'=>$lang['config_functions_85']." $openid_provider_name"))
								);
						// Footer
						print "<script type='text/javascript'> $(function(){ $('#outer #footer').show() }); </script>";
						$objHtmlPage->PrintFooterExt();
						exit;
					}
				} elseif($openid->mode == 'cancel') {
					// echo 'User has canceled authentication!';
				} elseif ($openid->validate()) {
					//echo 'User ' . ($openid->validate() ? $openid->identity . ' has ' : 'has not ') . 'logged in.';
					// print_array($openid->getAttributes());
					// print_array($openid->data);
					// exit;
					$openidAttr = $openid->getAttributes();
					// Set email address as REDCap username and add it to the session
					if (isset($openidAttr['contact/email'])) {
						$userid = $_SESSION['username'] = $_SESSION['redcap_userid'] = $openidAttr['contact/email'];
					} else {
						// If did not return an email address, then use substring of end of openid_identity as a unique id
						$userid = $_SESSION['username'] = $_SESSION['redcap_userid'] = "user_" . substr($openid->data['openid_identity'], -10);
					}
					// Log that they successfully logged in in log_view table
					addPageView("LOGIN_SUCCESS", $userid);
					// Set the user's last_login timestamp
					self::setUserLastLoginTimestamp($userid);
				}
			} catch(ErrorException $e) {
				// Error message if failed
				echo RCView::div(array('style'=>'padding:20px;'),
						RCView::b("OpenID authentication error: ").$e->getMessage()
					 );
			}
		}
		// Return userid
		return $userid;
	}
	

	/**
	 * HASH A USER'S PASSWORD USING SET PASSWORD ALGORITHM IN REDCAP_CONFIG
	 * Input the password and EITHER the salt or userid of an existing user (in which we can fetch the salt from the redcap_auth table).
	 * Returns the hashed string of the password.
	 */
	public static function hashPassword($password, $salt='', $userid='') 
	{
		global $password_algo;
		// If missing necessary components, return false
		if ($salt == '' && $userid == '') return false;
		// If have userid, then get salt from table
		if ($salt == '' && $userid != '') {
			// Salted SHA hash used
			$salt = self::getUserPasswordSalt($userid);
			// Determine if a user's password is using the old legacy hash or a newer salted hash
			if (self::isUserPasswordLegacy($userid)) {
				// Unsalted MD5 hash used, so simply return the MD5 of the password
				return md5($password . $salt);
			}
		}
		// Return hash by inputing password+salt
		return hash($password_algo, $password . $salt);
	}
	

	/**
	 * Retrieve the password salt for a specific table-based user
	 */
	public static function getUserPasswordSalt($userid) 
	{
		$sql = "select password_salt from redcap_auth where username = '".prep($userid)."'";
		$q = db_query($sql);
		// Return salt
		return db_result($q, 0);
	}
	

	/**
	 * Determine if a user's password is using the old legacy hash or a newer salted hash.
	 * Returns boolean (true if using old legacy hash).
	 */
	public static function isUserPasswordLegacy($userid) 
	{
		$sql = "select legacy_hash from redcap_auth where username = '".prep($userid)."'";
		$q = db_query($sql);
		// Return salt
		return ($q && db_result($q, 0) == '1');
	}
	

	/**
	 * Generate a random password salt and return it
	 */
	public static function generatePasswordSalt() 
	{
		$num_chars = 100;
		return generateRandomHash($num_chars, true);
	}
	

	/**
	 * Determine the highest SHA-based hashing algorithm for the server (up to SHA-512).
	 */
	public static function getBestHashAlgo() 
	{
		// Put algos in an array
		$algos = hash_algos();
		// Put SHA algos in an array by number
		$sha_algos = array();
		// Loop through algos
		foreach ($algos as $algo) {
			// Ignore if not a SHA-based algo
			if (substr($algo, 0, 3) != 'sha') continue;
			// Get SHA number
			$sha_num = substr($algo, 3)*1;
			// If higher than 512, then skip
			if ($sha_num > 512) continue;
			// Add SHA number to array
			$sha_algos[] = $sha_num;
		}
		// Return the highest SHA-based algorithm found (up to 512)
		return 'sha' . max($sha_algos);
	}
	

	/**
	 * Update a table-based user's hashed password and salt
	 */
	public static function setUserPasswordAndSalt($user, $hashed_password, $salt, $legacy_hash=0) 
	{
		$sql = "update redcap_auth set password = '".prep($hashed_password)."', password_salt = '".prep($salt)."',
				legacy_hash = '".prep($legacy_hash)."' where username = '".prep($user)."'";
		return db_query($sql);
	}
	
	/**
	 * Set DSN arrays for Table-based auth and/or LDAP auth
	 */
	public static function setDSNs()
	{
		global $lang, $auth_meth, $username, $password, $hostname, $db, $redcap_version;
		// PEAR Auth
		if (!include_once 'Auth.php') {
			exit("{$lang['global_01']}{$lang['colon']} {$lang['config_functions_22']}");
		}
		// Set the Pear cryptType based on REDCap version, which changed in REDCap version 5.8.0
		list ($one, $two, $three) = explode(".", $redcap_version);
		$redcap_version_numeral = $one . sprintf("%02d", $two) . sprintf("%02d", $three);
		$cryptType = ($redcap_version_numeral < 50800) ? 'md5' : 'hashPasswordPearAuthLogin';
		// Table info for redcap_auth
		$GLOBALS['mysqldsn'] = array(	'table' 	  => 'redcap_auth',
										'usernamecol' => 'username',
										'passwordcol' => 'password',
										'cryptType'    => $cryptType,
										'debug' 	  => false,
										'dsn' 		  => "mysqli://$username:$password@$hostname/$db");
		// LDAP Connection Information for your Institution
		$GLOBALS['ldapdsn'] = array();
		if ($auth_meth == "ldap" || $auth_meth == "ldap_table") {
			include APP_PATH_WEBTOOLS . 'ldap/ldap_config.php';
		}
	}
}