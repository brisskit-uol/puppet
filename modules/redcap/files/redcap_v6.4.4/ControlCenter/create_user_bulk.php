<?php
/* * ***************************************************************************************
 * *  REDCap is only available through ACADMEMIC USER LICENSE with Vanderbilt University
 * **************************************************************************************** */

/**
 * ADD USERS VIA BULK UPLOAD
 */

// Set header string for bulk upload file
$bulk_import_header = "Username, First name, Last name, Email address, Institution ID, Sponsor username, Expiration, Comments";


// Download CSV import template file
if (isset($_GET['download_template']))
{
	// Begin output to file
	$file_name = "UserImportTemplate.csv";
	header('Pragma: anytextexeptno-cache', true);
	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=$file_name");
	// Output the data
	exit($bulk_import_header);
}




// Page header, instructions, and tabs
include 'header.php';
print 	RCView::h3(array('style' => 'margin-top: 0;'), $lang['control_center_4427']) . 
		RCView::p(array('style'=>'margin-bottom:20px;'), $lang['control_center_411']);
// Display dashboard of table-based users that are on the old MD5 password hashing.
print User::renderDashboardPasswordHashProgress();
// Tabs
$tabs = array('ControlCenter/create_user.php'=>RCView::img(array('src'=>'user_add3.png','class'=>'imgfix')) . $lang['control_center_409'],
			  'ControlCenter/create_user_bulk.php'=>RCView::img(array('src'=>'xls.gif','class'=>'imgfix')) . $lang['control_center_410']);
renderTabs($tabs);

// Check if using email domain whitelist
$email_domain_whitelist_array = ($email_domain_whitelist == '') ? array() : explode("\n", strtolower(str_replace("\r", "", $email_domain_whitelist)));

// If adding new Table-based user, add new user to redcap_user_information and redcap_auth tables
if ($_SERVER['REQUEST_METHOD'] == 'POST') 
{
	// Set value if can create/copy new projects
	$allow_create_db = (isset($_POST['allow_create_db']) && $_POST['allow_create_db'] == "on") ? 1 : 0;
	
	#Process uploaded CSV file
	$uploadedfile_name = $_FILES['fname']['tmp_name'];
	$updateitems = csv_to_bulk($uploadedfile_name);
	
	foreach ($updateitems as $key => $item) 
	{
		if (!empty($item[0])) 
		{
			$item[0] = trim($item[0]);
			$item[1] = trim($item[1]);
			$item[2] = trim($item[2]);
			$item[3] = trim($item[3]);
			$item[4] = trim($item[4]);
			$item[5] = trim($item[5]);
			$item[6] = trim($item[6]);
			$item[7] = trim($item[7]);
			
			// Validate the username
			if (!preg_match('/^([a-zA-Z0-9_\.\-\@])+$/', $item[0])) {
				print  "<div class='red' style='margin:5px 0;'>
							<img src='" . APP_PATH_IMAGES . "exclamation.png' class='imgfix'>
							{$lang['global_01']}{$lang['colon']} {$lang['control_center_412']} {$lang['control_center_45']} 
							{$lang['control_center_427']} \"<b>" . $item[0] . "</b>\" 
						</div>";
				continue;
			}
			
			// Validate the email
			if ($item[3] == '') {
				print  "<div class='red' style='margin:5px 0;'>
							<img src='" . APP_PATH_IMAGES . "exclamation.png' class='imgfix'>
							{$lang['global_01']}{$lang['colon']} {$lang['control_center_413']} \"<b>" . $item[0] . "</b>\"
						</div>";
				continue;
			} elseif (!isEmail($item[3])) {
				print  "<div class='red' style='margin:5px 0;'>
							<img src='" . APP_PATH_IMAGES . "exclamation.png' class='imgfix'>
							{$lang['global_01']}{$lang['colon']} {$lang['control_center_414']} \"<b>" . $item[0] . "</b>\" 
							{$lang['control_center_415']} <b>{$item[3]}</b>
						</div>";
				continue;
			} elseif (trim($email_domain_whitelist) != '') {
				// If using the email domain whitelist, make sure the email's domain is in the whitelist
				list ($nothing, $email_domain) = explode('@', $item[3]);
				if (!in_array(strtolower($email_domain), $email_domain_whitelist_array)) {
					print  "<div class='red' style='margin:5px 0;'>
								<img src='" . APP_PATH_IMAGES . "exclamation.png' class='imgfix'>
								{$lang['global_01']}{$lang['colon']} {$lang['control_center_4494']} \"<b>{$item[0]}</b>\" (<b>{$item[3]}</b>)
								{$lang['control_center_4493']} \"<b>".implode("</b>\", \"<b>", $email_domain_whitelist_array)."</b>\"{$lang['period']}
							</div>";
					continue;
				}
			}

			
			// Validate sponsor username
			if ($item[5] != '') {
				$sponsorUserInfo = User::getUserInfo($item[5]);
				if ($sponsorUserInfo === false) {
					print  "<div class='red' style='margin:5px 0;'>
								<img src='" . APP_PATH_IMAGES . "exclamation.png' class='imgfix'>
								{$lang['global_01']}{$lang['colon']} {$lang['control_center_4404']} \"<b>" . $item[0] . "</b>\"{$lang['period']} 
								{$lang['control_center_4405']} \"<b>{$item[5]}</b>\"{$lang['period']}
							</div>";
					continue;
				}
			}
			
			// Validate user expiration
			if ($item[6] != '') {
				// Break up into date and time
				list ($thisdate, $thistime) = explode(' ', $item[6], 2);
				// Assume American format (mm/dd/yyyy) if contains forward slash, else assume yyyy-mm-dd format
				if (strpos($item[6], "/") !== false) {
					list ($month, $day, $year) = explode("/", $thisdate);
				} else {
					list ($year, $month, $day) = explode("-", $thisdate);
				}
				// Pad time, if needed
				if (strlen($thistime) == 0) $thistime = "00:00:00";
				if (strlen($thistime) == 4 || strlen($thistime) == 7) $thistime = "0".$thistime;
				if (strlen($thistime) == 5) $thistime .= ":00";
				// Make sure year is 4 digits
				if (strlen($year) == 2) {
					$year = ($year < (date('y')+10)) ? "20".$year : "19".$year;
				}
				$item[6] = sprintf("%04d-%02d-%02d", $year, $month, $day) . ' ' . $thistime;
			}
		
			// Get random value for temporary password
			$pass = generateRandomHash(8);
			$password_salt = Authentication::generatePasswordSalt();
			$hashed_password = Authentication::hashPassword($pass, $password_salt);
			// Add to table
			$sql = "INSERT INTO redcap_auth (username, password, password_salt, temp_pwd) 
					VALUES ('" . prep($item[0]) . "', '" . prep($hashed_password) . "', '" . prep($password_salt) . "', 1)";
			$q = db_query($sql);
			// Send email to new user with username/password
			if ($q) {
				// Logging
				log_event($sql, "redcap_auth", "MANAGE", $item[0], "user = '" . prep($item[0]) . "'", "Create username");
				// Set up email
				$email = new Message ();
				$email->setTo($item[3]);
				$email->setFrom($user_email);
				$email->setSubject('REDCap '.$lang['control_center_101']);
				$emailContents = $lang['control_center_95'].'<br /><br />
					<b>REDCap</b> - '.APP_PATH_WEBROOT_FULL.' <br /><br />
					'.$lang['control_center_97'].'<br /><br />
					'.$lang['global_11'].$lang['colon'].' <b>'.$item[0].'</b><br />
					'.$lang['global_32'].$lang['colon'].' <b>'.$pass.'</b><br /><br />
					'.$lang['control_center_96'];
				// If the user had an expiration time set, then let them know when their account will expire.
				if ($item[6] != '') {
					$daysFromNow = floor((strtotime($item[6]) - strtotime(NOW)) / (60*60*24));
					$emailContents .= " ".$lang['control_center_4402']."<b>".DateTimeRC::format_ts_from_ymd($item[6])
									. " -- $daysFromNow " . $lang['control_center_4438']
									. "</b>".$lang['control_center_4403'];
				}
				// If "auto-suspend due to inactivity" feature is enabled, the notify user generally that users may 
				// get suspended if don't log in for a long time.
				if ($suspend_users_inactive_type != '') {
					$emailContents .= " ".$lang['control_center_4424'];
				}
				// Send the email
				$email->setBody($emailContents, true);
				if (!$email->send()) print $email->getSendError ();
			}

			## Add user's info to redcap_user_information table
			$sql = "insert into redcap_user_information (username, user_email, user_firstname, user_lastname, user_inst_id, 
					allow_create_db, user_creation, user_sponsor, user_expiration, user_comments, 
					datetime_format, number_format_decimal, number_format_thousands_sep) values 
					(" . checkNull($item[0]) . ", " . checkNull($item[3]) . ", " . checkNull($item[1]) . ", " . 
					checkNull($item[2]) . ", " . checkNull($item[4]) . ", $allow_create_db, '".NOW."', 
					" . checkNull($item[5]) . ", " . checkNull($item[6]) . ", " . checkNull($item[7]) . ", 
					'".prep($default_datetime_format)."', '".prep($default_number_format_decimal)."', 
					'".prep($default_number_format_thousands_sep)."')";
			if (!db_query($sql)) {
				// Failure to add user
				print 	"<div class='red' style='margin:5px 0;'>
							<img src='" . APP_PATH_IMAGES . "exclamation.png' class='imgfix'>
							{$lang['global_01']}{$lang['colon']} {$lang['control_center_424']}
							" . (db_errno() == 1062 ? "{$lang['control_center_426']} \"<b>" . $item[0] . "</b>\"" : "") . "
						</div>";
			} else {
				// Display confirmation message that user was added successfully
				print 	"<div class='darkgreen'>
							<img src='" . APP_PATH_IMAGES . "tick.png' class='imgfix'>
							{$lang['control_center_425']} \"<b>" . $item[0] . "</b>\"
							(<a href='mailto:{$item[3]}'>{$item[3]}</a>){$lang['period']}
						</div>";
			}
		}
	}
	// Give error message if uploaded file was empty
	if (empty($updateitems))
	{
		print 	RCView::div(array('class'=>'red'),
					RCView::img(array('src'=>'exclamation.png','class'=>'imgfix')) .
					"{$lang['global_01']}{$lang['colon']} {$lang['control_center_423']}"
				);
	}
	// Display "start over" button
	print 	RCView::div(array('style'=>'margin:30px 0 10px;'),
				RCView::button(array('onclick'=>"window.location.href=app_path_webroot+page;"), "&lt;- {$lang['control_center_422']}")
			);
} 
else 
{
	/**
	 * ADD TABLE-BASED USERS
	 */
		print 	"<p>{$lang['control_center_4406']}</p>
				<p style='font-weight:bold;margin-bottom:0;'>{$lang['control_center_419']}</p>
				<p class='pre' style='width:750px;margin-top:2px;margin-bottom:20px;border:1px solid #ddd;padding:3px 3px 3px 10px;background-color:#f5f5f5;'>$bulk_import_header</p>
				<p>
					<a href='".PAGE_FULL."?download_template=1' style='text-decoration:underline;color:green;'><img src='".APP_PATH_IMAGES."xls.gif' class='imgfix'> {$lang['control_center_421']}</a>
				</p>
				<form method='post' name='bulk_upload' action='{$_SERVER['PHP_SELF']}?view=user_controls' style='border:1px solid #ddd;padding:10px;margin:30px 0 10px;' enctype='multipart/form-data'>
					<b>{$lang['control_center_418']}</b><br><br>
					<input type='file' name='fname' size='50'><br><br>
					<input type='checkbox' name='allow_create_db' class='imgfix' ".($allow_create_db_default ? "checked" : "").">
					".($superusers_only_create_project 
						? RCView::b($lang['control_center_416']). RCView::div(array('style'=>'margin-left:22px;'), $lang['control_center_321'])
						: RCView::b($lang['control_center_417']) )."
					<div style='text-align:center;padding:10px;'>
						<input name='submit' type='submit' value='".cleanHtml($lang['design_127'])."' onclick=\"
							if (document.forms['bulk_upload'].elements['fname'].value.length < 1) {
								simpleDialog('".cleanHtml($lang['data_import_tool_114'])."');
								return false;
							}
						\"> &nbsp;
					</div>
				</form>";
}


include 'footer.php';


// Convert CSV file of users to array (ignore first row)
function csv_to_bulk($csvfilepath) 
{
    $new_users = array();
    $file = fopen($csvfilepath, "r");
    $row = 0;
    while (($data = fgetcsv($file)) !== FALSE) {
        $item_count = count($data);
        if ($row > 0) {/* skip the first row, it contains only a header */
            for ($i = 0; $i < $item_count; $i++) {
                $new_users[$row - 1][$i] = $data[$i];
            }
        }
        $row++;
    }
    fclose($file);
	unlink($csvfilepath);
    return $new_users;
}
