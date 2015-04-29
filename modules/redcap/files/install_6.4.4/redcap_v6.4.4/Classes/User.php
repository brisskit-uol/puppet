<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/


/**
 * USER Class
 * Contains methods used with regard to users
 */
class User
{	
	## CONSTANTS
	// Time when 1st warning email is sent prior to user expiration (in days)
	const USER_EXPIRE_FIRST_WARNING_DAYS = 14;
	// Time when 2nd warning email is sent prior to user expiration (in days)
	const USER_EXPIRE_SECOND_WARNING_DAYS = 2;

	// Return array of ALL project usernames with key as username and value also as username (unless $appendFirstLastName=true)
	public static function getProjectUsernames($excludeUsernames=array(), $appendFirstLastName=false, $project_id=null)
	{
		global $lang;
		// Place all usernames in array to return
		$users = array();
		// Get project_id
		$project_id = (defined("PROJECT_ID")) ? PROJECT_ID : $project_id;
		// Get email addresses and names from table
		$sql = "select u.username, trim(concat(i.user_firstname, ' ', i.user_lastname)) as full_name 
				from redcap_user_rights u left join redcap_user_information i 
				on i.username = u.username where u.project_id = $project_id";
		if (!empty($excludeUsernames)) {
			$sql .= " and u.username not in (".prep_implode($excludeUsernames).")";
		}
		$sql .= " order by u.username";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Add user to array
			$users[$row['username']] = $row['username'] . (($appendFirstLastName && $row['full_name'] != '') ? " ({$row['full_name']})" : '');
		}
		// Return the array
		return $users;
	}
	
	// Return HTML for a select drop-down of ALL project users with ui_id as key
	public static function dropDownListAllUsernames($dropdownId, $selectedValue='', $excludeUsernames=array(), $onChangeJS='', $appendFirstLastName=true, $disabled=false)
	{
		global $lang;
		// Set disabled attribute
		$disabled = ($disabled) ? "disabled" : "";
		// Create select list of usernames
		$userOptions = array(''=>$lang['rights_133']);
		// Get email addresses and names from table
		$sql = "select i.ui_id, i.username, trim(concat(i.user_firstname, ' ', i.user_lastname)) as full_name
				from redcap_user_rights u, redcap_user_information i 
				where u.project_id = ".PROJECT_ID." and i.username = u.username order by i.username";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Exclude users
			if (in_array($row['username'], $excludeUsernames)) continue;
			// Add user to array
			$userOptions[$row['ui_id']] = $row['username'];
			// Add first/last name to array (if flag is set)
			if ($appendFirstLastName) {
				$userOptions[$row['ui_id']] .= " ({$row['full_name']})";
			}
		}
		// Set select box html
		$userSelect = RCView::select(array('id'=>$dropdownId,'class'=>'x-form-text x-form-field', $disabled=>$disabled,
						'style'=>'padding-right:0;height:22px;', 'onchange'=>$onChangeJS), $userOptions, $selectedValue, 100);
		// Return the HTML
		return $userSelect;
	}
	
	// Return HTML for a select drop-down of the current user's email addresses associated with
	// their REDCap account. If don't have a secondary/tertiary email listed, then let last option (if desired)
	// be a clickable trigger to open dialog for setting up a secondary/tertiary email.
	public static function emailDropDownList($appendAddEmailOption=true,$dropdownId='emailFrom',$dropdownName='emailFrom')
	{
		global $lang, $user_email, $user_email2, $user_email3;
		// Create select list for From email address (do not display any that are still pending approval)
		$fromEmailOptions = array('1'=>$user_email);
		if ($user_email2 != '') {
			$fromEmailOptions['2'] = $user_email2;
		}
		if ($user_email3 != '') {
			$fromEmailOptions['3'] = $user_email3;
		}
		// Add option to add more emails (if designated)
		if ($appendAddEmailOption && ($user_email2 == '' || $user_email3 == '')) {
			$fromEmailOptions['999'] = $lang['survey_763'];
		}
		// Set select box html
		$fromEmailSelect = RCView::select(array('id'=>$dropdownId,'name'=>$dropdownName,'class'=>'x-form-text x-form-field',
			'style'=>'padding-right:0;height:22px;',
			'onchange'=>"if(this.value=='999') { setUpAdditionalEmails(); this.value='1'; }"), $fromEmailOptions, '1', 100);
		// Return the HTML
		return $fromEmailSelect;
	}
	
	// Return HTML for a select drop-down of ALL project users' email addresses associated with
	// their REDCap account. If don't have a secondary/tertiary email listed, then let last option (if desired)
	// be a clickable trigger to open dialog for setting up a secondary/tertiary email.
	public static function emailDropDownListAllUsers($selectedValue=null,$appendAddEmailOption=true,$dropdownId='emailFrom',$dropdownName='emailFrom')
	{
		global $lang, $user_email, $user_email2, $user_email3;
		// Create select list for From email address of ALL project users (do not display any that are still pending approval)
		$fromEmailOptions = array();
		// If selected email doesn't belong to anyone on the project anymore, then keep it as an extra option
		if ($selectedValue != '' && !in_array($selectedValue, $fromEmailOptions)) {
			$fromEmailOptions[$selectedValue] = "??? ($selectedValue)";
		}
		// Get email addresses and names from table
		$sql = "select distinct x.email from (
				(select i.user_email as email from redcap_user_rights u, redcap_user_information i 
					where u.project_id = ".PROJECT_ID." and i.username = u.username and i.email_verify_code is null and i.user_email is not null) 
				union 
				(select i.user_email2 as email from redcap_user_rights u, redcap_user_information i 
					where u.project_id = ".PROJECT_ID." and i.username = u.username and i.email2_verify_code is null and i.user_email2 is not null) 
				union 
				(select i.user_email3 as email from redcap_user_rights u, redcap_user_information i 
					where u.project_id = ".PROJECT_ID." and i.username = u.username and i.email3_verify_code is null and i.user_email3 is not null)
				) x order by x.email";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Clean, just in case
			$row['email'] = label_decode($row['email']);
			$row['name'] = label_decode($row['name']);
			// Add to array
			$fromEmailOptions[$row['email']] = $row['email'];
		}
		// Add option to add more emails (if designated)
		if ($appendAddEmailOption && ($user_email2 == '' || $user_email3 == '')) {
			$fromEmailOptions['999'] = $lang['survey_763'];
		}
		// Set the default selected value (if none, then use current user's primary email)
		$selectedValue = ($selectedValue == '') ? $user_email : $selectedValue;
		// Set select box html
		$fromEmailSelect = RCView::select(array('id'=>$dropdownId,'name'=>$dropdownName,'class'=>'x-form-text x-form-field',
			'style'=>'padding-right:0;height:22px;',
			'onchange'=>"if(this.value=='999') { setUpAdditionalEmails(); this.value='".cleanHtml($selectedValue)."'; }"), $fromEmailOptions, $selectedValue, 100);
		// Return the HTML
		return $fromEmailSelect;
	}
	
	// Generate unique user verification code for their email account
	private static function generateUserVerificationCode()
	{
		do {
			// Generate a new random hash
			$code = generateRandomHash(20);
			// Ensure that the hash doesn't already exist in table
			$sql = "select 1 from redcap_user_information where (email_verify_code = '$code' 
					or email2_verify_code = '$code' or email3_verify_code = '$code') limit 1";
			$codeExists = (db_num_rows(db_query($sql)) > 0);
		} while ($codeExists);
		// Code is unique, so return it
		return $code;
	}
	
	
	// Set the user's user_access_dashboard_view timestamp to NOW when they view the project user access dashboard/summary page
	public static function setUserAccessDashboardViewTimestamp($user)
	{
		// Set timestamp in table for user
		$sql = "update redcap_user_information set user_access_dashboard_view = '" . NOW . "' 
				where username = '".prep($user)."'";
		return (db_query($sql));	
	}
	

	// Set user's email address (primary=1, secondary=2, or tertiary=3)
	// Provide user's ui_id and which email account this is for.
	public static function setUserEmail($ui_id, $email="", $email_account=1)
	{
		// Validate email
		if (!isEmail($email)) return false;
		// Determine which user_email field we're updating based upon $email_account
		$user_email_field = "user_email" . ($email_account > 1 ? $email_account : "");
		// Add code to table (if code already exists for this primary/secondary/tertiary email, then update the code with new value)
		$sql = "update redcap_user_information set $user_email_field = '" . prep($email) . "' 
				where ui_id = '".prep($ui_id)."'";
		return (db_query($sql));
	}

	// Remove a user's secondary=2 or tertiary=3 email address from their account
	public static function removeUserEmail($ui_id, $email_account=null)
	{
		if (!is_numeric($email_account)) return false;
		// Determine which user_email field we're updating based upon $email_account
		$user_email_field = "user_email{$email_account}";
		$user_verify_code_field = "email{$email_account}_verify_code";
		// Remove email from table
		$sql = "update redcap_user_information set $user_email_field = null, $user_verify_code_field = null 
				where ui_id = '".prep($ui_id)."'";
		$q = db_query($sql);
		if (!$q) return false;
		// If secondary email was removed, then if tertiary email exist, make it the secondary email (move value in table)
		if ($email_account == '2')
		{
			// Get user info
			$user_info = User::getUserInfo(USERID);
			// If it has a tertiary email, move to secondary position
			if ($user_info['user_email3'] != '') {
				$sql = "update redcap_user_information set user_email2 = user_email3, email2_verify_code = email3_verify_code,
						user_email3 = null, email3_verify_code = null where ui_id = '".prep($ui_id)."'";
				$q = db_query($sql);
			}
		}
		return true;
	}

	// Get unique user verification code for their email account
	// Provide user's ui_id and which email account this is for.
	public static function setUserVerificationCode($ui_id, $email_account=1)
	{
		// Generate a new random code
		$code = self::generateUserVerificationCode();
		// Determine which user_email field we're updating based upon $email_account
		$user_email_field = "email" . ($email_account > 1 ? $email_account : "") . "_verify_code";
		// Add code to table (if code already exists for this primary/secondary/tertiary email, then update the code with new value)
		$sql = "update redcap_user_information set $user_email_field = '$code' 
				where ui_id = '".prep($ui_id)."'";
		return (db_query($sql) ? $code : false);
	}
	
	// Email the user email verification code to the user
	public static function sendUserVerificationCode($new_email, $code, $email_account=1, $this_userid=null)
	{
		global $project_contact_email, $lang, $redcap_version, $user_email;
		// If $this_userid not provided, use USERID
		if ($this_userid == null) $this_userid = USERID;
		// Email the user (new users get their login info, existing users get notified if their email changes)
		$email = new Message();
		// Send the email From the user's primary address
		$email->setTo($new_email);
		$email->setFrom($project_contact_email);
		$email->setSubject('[REDCap] '.$lang['user_19']);
		if ($email_account == 1) {
			// Primary email account
			$emailContents = "{$lang['user_66']} \"<b>$this_userid</b>\"{$lang['user_67']}";
			// Set verification url
			$url = APP_PATH_WEBROOT_FULL . "index.php?user_verify=$code";
		} else {
			// Secondary or tertiary email account
			$emailContents = "{$lang['user_68']} \"<b>$this_userid</b>\"{$lang['user_69']}";
			// Set verification url
			$url = APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/Profile/additional_email_verify.php?user_verify=$code";
		}
		$emailContents .= '<br /><br /><a href="'.$url.'">'.$lang['user_21'].'</a><br /><br />'
						. $lang['survey_135'].'<br />'.$url.'<br /><br />'.$lang['survey_137'];
		$email->setBody($emailContents, true);
		if ($email->send()) {
			// Logging
			log_event("","redcap_user_information","MANAGE",$this_userid,"username = '$this_userid'","Send email address verification to user");
			return true;
		}			
		exit($email->getSendError());
	}
	
	// Remove a user's email verification code from the user_info table after their account has been verified
	public static function removeUserVerificationCode($userid, $email_account=1)
	{
		// Determine which user_email field we're updating based upon $email_account
		$user_email_field = "email" . ($email_account > 1 ? $email_account : "") . "_verify_code";
		// Query the table
		$sql = "update redcap_user_information set $user_email_field = null 
				where username = '".prep($userid)."' limit 1";
		$q = db_query($sql);
		return ($q && db_affected_rows() > 0);
	}
	
	// Verify a user's email verification code that they received in an email. 
	// Return the email account it corresponds to (1=primary,2=secondary,3=tertiary) or false if failed.
	public static function verifyUserVerificationCode($userid, $code)
	{
		// Query the table
		$sql = "select email_verify_code, email2_verify_code, email3_verify_code 
				from redcap_user_information where username = '".prep($userid)."' 
				and (email_verify_code = '".prep($code)."' or email2_verify_code = '".prep($code)."' 
				or email3_verify_code = '".prep($code)."') limit 1";
		$q = db_query($sql);
		if ($q && db_num_rows($q) > 0) {
			$row = db_fetch_assoc($q);
			// Determine which email account it corresponds to
			if ($row['email_verify_code'] == $code) {
				return '1';
			} elseif ($row['email2_verify_code'] == $code) {
				return '2';			
			} elseif ($row['email3_verify_code'] == $code) {
				return '3';
			}
			return false;
		} else {
			return false;
		}
	}
	
	// Verify a that an email verification code received in an email belongs to *SOME* user (we may not know who). 
	// Return boolean. True if code exists for some user, false if not for any user
	public static function verifyUserVerificationCodeAnyUser($code)
	{
		// Query the table
		$sql = "select 1 from redcap_user_information 
				where (email_verify_code = '".prep($code)."' or email2_verify_code = '".prep($code)."' 
				or email3_verify_code = '".prep($code)."') limit 1";
		$q = db_query($sql);
		return ($q && db_num_rows($q) > 0);
	}
	
	// Get all info for specified user from user_information table and return as array
	public static function getUserInfo($userid)
	{
		$sql = "select * from redcap_user_information where username = '".prep($userid)."' limit 1";
		$q = db_query($sql);
		return (($q && db_num_rows($q) > 0) ? db_fetch_assoc($q) : false);
	}
	
	// Get all info for specified user from user_information table by using user's UI_ID and return as array
	public static function getUserInfoByUiid($ui_id)
	{
		if (!is_numeric($ui_id)) return false;
		$sql = "select * from redcap_user_information where ui_id = $ui_id limit 1";
		$q = db_query($sql);
		return (($q && db_num_rows($q) > 0) ? db_fetch_assoc($q) : false);
	}
	
	// Update user_firstvisit value for specified user in user_information table
	public static function updateUserFirstVisit($userid)
	{
		$sql = "update redcap_user_information set user_firstvisit = '".NOW."' 
				where username = '".prep($userid)."' limit 1";
		return db_query($sql);
	}
	
	// Determine if specified username is a Table-based user (i.e. in redcap_auth)
	public static function isTableUser($userid)
	{
		// Query the table
		$sql = "select 1 from redcap_auth where username = '".prep($userid)."' limit 1";
		$q = db_query($sql);
		return ($q && db_num_rows($q) > 0);
	}

	// Check if an email address is acceptable regarding the "domain whitelist for user emails" (if enabled)
	public static function emailInDomainWhitelist($email='') {
		global $email_domain_whitelist;
		$email = trim($email);
		if ($email_domain_whitelist == '' || $email == '') return null;
		$email_domain_whitelist_array = explode("\n", str_replace("\r", "", $email_domain_whitelist));
		list ($emailFirstPart, $emailDomain) = explode('@', $email, 2);
		return (in_array($emailDomain, $email_domain_whitelist_array));
	}
	
	// Return array of users with Data Resolution "respond" privileges. Has UI_ID as key and username as value.
	public static function getUsersDataResRespond($appendFirstLastName=true)
	{
		global $lang, $user_rights;
		// Create select list of usernames
		$userOptions = array(''=>$lang['rights_133']);
		// Get array of all users and their rights
		$projectUsers = UserRights::getPrivileges(PROJECT_ID);
		// Loop through all users to filter out those who cannot do DRW respond
		$projectUsernamesDRWrespond = array();
		foreach ($projectUsers[PROJECT_ID] as $this_user=>$row) 
		{
			// Only add to array if data_resolution rights > 1 AND only if user is in user_information table
			if ($row['data_quality_resolution'] > 1) {			
				// Add user to array
				$projectUsernamesDRWrespond[] = $this_user;
			}
		}
		// Get email addresses and names from table (if user is in a DAG, return only users in their DAG and non-DAG users)
		$sql = "select u.username, i.ui_id, trim(concat(i.user_firstname, ' ', i.user_lastname)) as full_name
				from redcap_user_rights u, redcap_user_information i 
				where u.project_id = ".PROJECT_ID." and i.username = u.username 
				and i.username in (".prep_implode($projectUsernamesDRWrespond).")";
		if (is_numeric($user_rights['group_id'])) {
			$sql .= " and (u.group_id is null or u.group_id = '{$user_rights['group_id']}')";
		}
		$sql .= " order by u.username";
		//print $sql;
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Add user to array
			$userOptions[$row['ui_id']] = $row['username'];
			// Add first/last name to array (if flag is set)
			if ($appendFirstLastName) {
				$userOptions[$row['ui_id']] .= " ({$row['full_name']})";
			}
		}
		// Return the array of users
		return $userOptions;
	}
	
	
	// Display dashboard of table-based users that are on the old MD5 password hashing.
	// Give options to email the users to encourage them to log in OR to suspend them.
	public static function renderDashboardPasswordHashProgress()
	{
		global $lang, $homepage_contact_email;
		// Html
		$h = '';
		// Get count of un-suspended table-based users that have legacy hash
		$sql = "select count(1) from redcap_auth a, redcap_user_information i 
				where a.username = i.username and a.legacy_hash = 1 and i.user_suspended_time is null";
		$q = db_query($sql);
		$num_users_legacy_hash = db_result($q, 0);
		
		// Get count of un-suspended table-based users that have new hash
		$sql = "select count(1) from redcap_auth a, redcap_user_information i 
				where a.username = i.username and a.legacy_hash = 0 and i.user_suspended_time is null";
		$q = db_query($sql);
		$num_users_new_hash = db_result($q, 0);
		
		// Create yellow/green table displaying user counts
		$table_user_count = RCView::table(array('cellspacing'=>0, 'style'=>'border:1px solid #aaa;'),
			RCView::tr('',
				RCView::td(array('style'=>'background-color:#FFFF80;padding:5px;'),
					RCView::img(array('src'=>'exclamation_orange.png', 'style'=>'vertical-align:middle;')) .
					RCView::span(array('style'=>'vertical-align:middle;'), 
						$lang['rights_287']
					)
				) .
				RCView::td(array('style'=>'text-align:center;background-color:#FFFF80;padding:5px;font-weight:bold;font-size:14px;'),
					$num_users_legacy_hash
				)
			) .
			// Do NOT show the action buttons if 0 users using weaker hash
			($num_users_legacy_hash == 0 ? '' :
				RCView::tr('',
					RCView::td(array('colspan'=>'2', 'style'=>'text-align:center;background-color:#FFFF80;padding:0 7px 5px;'),
						// Remind users via email to log in soon
						RCView::button(array('id'=>'table_user_security_btn1', 'class'=>'jqbuttonsm', 'style'=>'font-family:arial;vertical-align:middle;', 'onclick'=>"
							simpleDialog(null,null,'table_user_security_remind_login_action',600,null,'".cleanHtml($lang['global_53'])."',
							function(){
								$.post(app_path_webroot+'ControlCenter/password_hash_actions.php',{action:'reminder'},function(data){
									if (data != '1') {
										alert(woops);
										return;
									}
									$('#table_user_security_btn1').button('disable');
									simpleDialog('".cleanHtml($lang['rights_295'])."','".cleanHtml($lang['setup_08'])."');
								});
							},
							'".cleanHtml($lang['rights_293'])."');
						"),
							$lang['rights_288']
						) .
						// Suspend users with weaker hash
						RCView::button(array('id'=>'table_user_security_btn1', 'class'=>'jqbuttonsm', 'style'=>'font-family:arial;vertical-align:middle;', 'onclick'=>"
							simpleDialog(null,null,'table_user_security_suspend_action',500,null,'".cleanHtml($lang['global_53'])."',
							function(){
								$.post(app_path_webroot+'ControlCenter/password_hash_actions.php',{action:'suspend'},function(data){
									if (data != '1') {
										alert(woops);
										return;
									}
									simpleDialog('".cleanHtml($lang['rights_296'])."','".cleanHtml($lang['setup_08'])."',null,null,
										function(){ window.location.href=app_path_webroot+'ControlCenter/create_user.php'; } );
								});
							},
							'".cleanHtml($lang['rights_298'])."');
						"),
							$lang['rights_289']
						)
					)
				)
			) .
			RCView::tr('',
				RCView::td(array('style'=>'background-color:#8BEA84;padding:5px;'),
					RCView::img(array('src'=>'tick_circle.png', 'style'=>'vertical-align:middle;')) .
					RCView::span(array('style'=>'vertical-align:middle;'), 
						$lang['rights_290']
					)
				) .
				RCView::td(array('style'=>'text-align:center;background-color:#8BEA84;padding:5px;font-weight:bold;font-size:14px;'),
					$num_users_new_hash
				)
			)
		);
		
		// Render hidden dialog divs
		$h .= RCView::div(array('id'=>'table_user_security_remind_login_action', 'class'=>'simpleDialog', 'title'=>$lang['rights_288']),
				$lang['rights_294'] .
				RCView::div(array('style'=>'margin-top:15px;padding:5px;border:1px solid #ddd;'),
					RCView::b("Subject:") . " " .$lang['rights_282'] . RCView::br() . RCView::br() .
					"{$lang['cron_02']}<br /><br />{$lang['rights_283']} \"<b>USERNAME</b>\"{$lang['period']}
					{$lang['rights_284']}<br /><br />{$lang['rights_285']} 
					<a style=\"text-decoration:underline;\" href=\"mailto:$homepage_contact_email\">$homepage_contact_email</a>{$lang['period']}<br /><br />
					<b>REDCap</b> - <a style=\"text-decoration:underline;\" href=\"".APP_PATH_WEBROOT_FULL."\">".APP_PATH_WEBROOT_FULL."</a>"
				)
			  );
		$h .= RCView::div(array('id'=>'table_user_security_suspend_action', 'class'=>'simpleDialog', 'title'=>$lang['rights_289']),
				$lang['rights_297']
			  );
		// Display the box
		// $h .= RCView::fieldset(array('style'=>'margin-bottom:20px;padding:5px;border:1px solid #ccc;'),
				// RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
					// RCView::img(array('src'=>'tick_shield_lock.png','class'=>'imgfix')) . 
					// $lang['rights_286']
				// ) .
				// RCView::div(array('style'=>'float:left;color:#333;font-size:11px;line-height:11px;width:340px;'),
					// $lang['rights_291'] . " " .
					// ($num_users_legacy_hash == 0 ? '' :
						// RCView::a(array('href'=>'javascript:;', 'id'=>'table_user_security_info_link', 'style'=>'text-decoration:underline;font-size:11px;', 'onclick'=>"$(this).hide();$('#table_user_security_info_div').show();"), $lang['scheduling_78']) .
						// RCView::div(array('id'=>'table_user_security_info_div', 'style'=>'margin-top:8px;display:none;'),
							// $lang['rights_292']
						// )
					// )
				// ) .
				// RCView::div(array('style'=>'float:right;'),
					// $table_user_count
				// ) .
				// RCView::div(array('class'=>'clear'), '')
			 // );
		// Return the HTML
		return $h;	
	}
	

	// Return array of all number format decimal options (to be used as options in drop-down)
	public static function getNumberDecimalFormatOptions()
	{
		global $lang, $number_format_decimal_formats;
		$options = array();
		// Loop through all options
		foreach ($number_format_decimal_formats as $option) 
		{
			$val = $option;
			if ($option == '.') {
				$option .= " " . $lang['user_85'];
			} elseif ($option == ',') {
				$option .= " " . $lang['user_86'];
			}
			$options[$val] = $option;
		}
		// Return options
		return $options;
	}
	

	// Return array of all number format thousands separator options (to be used as options in drop-down)
	public static function getNumberThousandsSeparatorOptions()
	{
		global $lang, $number_format_thousands_sep_formats;
		$options = array();
		// Loop through all options
		foreach ($number_format_thousands_sep_formats as $option) 
		{
			$val = $option;
			if ($option == '.') {
				$option .= " " . $lang['user_85'];
			} elseif ($option == ',') {
				$option .= " " . $lang['user_86'];
			} elseif ($option == 'SPACE') {
				$option = " " . $lang['user_87'];
			} elseif ($option == "'") {
				$option .= " " . $lang['user_88'];
			} elseif ($option == "") {
				$option .= " " . $lang['user_92'];
			}
			$options[$val] = $option;
		}
		// Return options
		return $options;
	}
	
	
	// Get user's number format for decimals
	public static function get_user_number_format_decimal()
	{
		global $number_format_decimal, $default_number_format_decimal_system;
		// Set destination format
		return ($number_format_decimal == null ? $default_number_format_decimal_system : $number_format_decimal);	
	}
	
	
	// Get user's number format for thousands separator
	public static function get_user_number_format_thousands_separator()
	{
		global $number_format_thousands_sep, $default_number_format_thousands_sep_system;
		// Set destination format
		return ($number_format_thousands_sep === null) 
				? $default_number_format_thousands_sep_system 
				: ($number_format_thousands_sep == 'SPACE' ? ' ' : $number_format_thousands_sep);	
	}
	
	
	// Format a number to a user's preferred display format for decimal and thousands separator
	public static function number_format_user($val, $decimals=0) 
	{
		return number_format($val, $decimals, self::get_user_number_format_decimal(), self::get_user_number_format_thousands_separator());
	}
	
}
