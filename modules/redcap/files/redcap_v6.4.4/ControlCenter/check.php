<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

if (isset($_GET['upgradeinstall'])) {
	require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
	// Check for any extra whitespace from config files that would mess up lots of things
	$prehtml = ob_get_contents();
	// Header
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->PrintHeaderExt();
} else {
	// Header
	include 'header.php';
}

?>
<script type="text/javascript">
function whyComponentMissing() {
	var msg = "Because some components are added to REDCap at a specific version and are never modified thereafter, they are not included in every "
			+ "version of REDCap in the upgrade zip file. Since such components are only added once, it does not make sense to include them in every "
			+ "upgrade file, so instead they are thus only included in the version that first utilizes them. This triggers the "
			+ "error you see here, which prompts you to go download them now. This is not ideal, but it is the best approach for now. "
			+ "Sorry for any inconvenience.";
	alert(msg);
}
</script>
<?php

############################################################################################

//PAGE HEADER
print RCView::h3(array('style'=>'margin-top:0;'), RCView::img(array('src'=>'view-task.png', 'class'=>'imgfix1')) . $lang['control_center_443']);
print  "<p>
			This page will test your current REDCap configuration to determine if any errors exist
			that might prevent it from functioning properly.
		</p>";


## Basic tests
print "<p style='padding-top:10px;color:#800000;font-weight:bold;font-family:verdana;font-size:13px;'>Basic tests</p>";



$testInitMsg = "<b>TEST 1: Establish basic REDCap file structure</b>
				<br>Search for necessary files and folders that should be located in the main REDCap folder 
				(i.e. \"".dirname(APP_PATH_DOCROOT)."\").";
$missing_files = 0;
if (substr(basename(APP_PATH_DOCROOT),0,8) != "redcap_v" && basename(APP_PATH_DOCROOT) != "codebase"){ 
	exit (RCView::div(array('class'=>'red'), "$testInitMsg<br> &bull; redcap_v?.?.? - <b>MISSING!<p>ERROR! - This file (ControlCenter/check.php) should be located in a folder named with the following format:
	/redcap/redcap_v?.?.?/. Find this folder and place the ControlCenter/check.php file in it, and run this test again.</b>")); 
	$missing_files = 1; 
}
if (!is_dir(dirname(APP_PATH_DOCROOT)."/temp")) { 
	$testInitMsg .= "<br> &bull; temp - <b>MISSING!</b>";
	$missing_files = 1; 
}
if (!is_dir(dirname(APP_PATH_DOCROOT)."/edocs")) {
	$testInitMsg .= "<br> &bull; edocs - <b>MISSING!</b>";
	$missing_files = 1; 
}
if (!is_file(dirname(APP_PATH_DOCROOT)."/database.php")) {
	$testInitMsg .= "<br> &bull; database.php - <b>MISSING!</b>";
	$missing_files = 1; 
}
if (is_dir(dirname(APP_PATH_DOCROOT)."/webtools2")) { 
	// See if the webdav folder is in correct location
	if (!is_dir(dirname(APP_PATH_DOCROOT)."/webtools2/webdav")) {
		$testInitMsg .= "<p><b>ERROR! - The sub-folder named \"webdav\" is missing from the \"webtools2\" folder.</b>
		<br>Find this folder and place it in the \"webtools2\" folder. Then run this test again.";
		$missing_files = 1;  
	}
	// LDAP folder
	if (!is_file(dirname(APP_PATH_DOCROOT)."/webtools2/ldap/ldap_config.php")) {
		$testInitMsg .= "<br> &bull; webtools2/ldap/ldap_config.php - <b>MISSING!</b>";
		$missing_files = 1; 
	}
	// TinyMCE folder
	if (!is_dir(dirname(APP_PATH_DOCROOT)."/webtools2/tinymce_".TINYMCE_VERSION)) { 
		$testInitMsg .= "<br> &bull; webtools2/tinymce_".TINYMCE_VERSION." - <b>MISSING!</b> - Must be obtained from install/upgrade zip file from version 4.9.7. 
				See the <a href='https://iwg.devguard.com/trac/redcap/wiki/RedcapDownloadArea' style='text-decoration:underline;' target='_blank'>REDCap download page</a>.
				(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>Why is this missing?</a>)";
		$missing_files = 1; 
	}
	
} else { 
	$testInitMsg .= "<br> &bull; webtools2 - <b>MISSING! &nbsp; <font color=#800000>This folder needs to be in the folder named \"".dirname(APP_PATH_DOCROOT)."\".</font></b>";
	$missing_files = 1; 
}
if (!is_dir(dirname(APP_PATH_DOCROOT)."/languages")) { 
	$testInitMsg .= "<br> &bull; languages - <b>MISSING!</b> - Must be obtained from install/upgrade zip file from version 3.2.0. 
			See the <a href='https://iwg.devguard.com/trac/redcap/wiki/RedcapDownloadArea' style='text-decoration:underline;' target='_blank'>REDCap download page</a>.
			(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>Why is this missing?</a>)";
	$missing_files = 1; 
}
if (!is_dir(dirname(APP_PATH_DOCROOT)."/api")) { 
	$testInitMsg .= "<br> &bull; api - <b>MISSING!</b> - Must be obtained from install/upgrade zip file from version 3.3.0. 
			See the <a href='https://iwg.devguard.com/trac/redcap/wiki/RedcapDownloadArea' style='text-decoration:underline;' target='_blank'>REDCap download page</a>.
			(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>Why is this missing?</a>)";
	$missing_files = 1; 
}
if (!is_dir(dirname(APP_PATH_DOCROOT)."/api/help")) { 
	$testInitMsg .= "<br> &bull; api/help - <b>MISSING!</b> - Must be obtained from install/upgrade zip file from version 3.3.0. 
			See the <a href='https://iwg.devguard.com/trac/redcap/wiki/RedcapDownloadArea' style='text-decoration:underline;' target='_blank'>REDCap download page</a>.
			(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>Why is this missing?</a>)";
	$missing_files = 1; 
}
if (!is_dir(dirname(APP_PATH_DOCROOT)."/surveys")) { 
	$testInitMsg .= "<br> &bull; surveys - <b>MISSING!</b> - Must be obtained from install/upgrade zip file from version 4.0.0. 
			See the <a href='https://iwg.devguard.com/trac/redcap/wiki/RedcapDownloadArea' style='text-decoration:underline;' target='_blank'>REDCap download page</a>.
			(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>Why is this missing?</a>)";
	$missing_files = 1; 
}

if ($missing_files == 1){ 
	exit(RCView::div(array('class'=>'red'), "$testInitMsg<br><br><b><font color=red>ERROR!</font> - One or more of the files/folders listed above could not be found 
			in the folder named \"".dirname(APP_PATH_DOCROOT)."\". Please locate those files/folders in the Install/Upgrade zip file
			that you downloaded from the REDCap wiki, then add them to the correct location on your server and run this test again.")); 
} else { 
	$testInitMsg .= "<br><br><img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> <b>SUCCESSFUL!</b> - All necessary files and folders were found."; 
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'), $testInitMsg);
}





$testMsg3 = "<b>TEST 2: Connect to the table named \"redcap_config\"</b><br><br>";
$QQuery = db_query("SHOW TABLES FROM `$db` LIKE 'redcap_config'");
if (db_num_rows($QQuery) == 1) {
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'), "$testMsg3 <img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> <b>SUCCESSFUL!</b> - The table \"redcap_config\" in the MySQL database named <b>".$db."</b>
			was accessed successfully.");
} else {
	exit (RCView::div(array('class'=>'red'), "$testMsg3<b>ERROR! - The database table named \"redcap_config\" could NOT be accessed.</b>
	<br>This error may have resulted if there was an error during the install/upgrade process. Please make sure that the
	\"redcap_config\" table is located in the MySQL project <b>".$db."</b>. 
	If it is not, you will need to re-install/re-upgrade REDCap, and then run this test again."));
}



## Check if REDCap database structure is correct
$testMsg = "<b>TEST 3: Check REDCap database table structure</b><br><br>";
$tableCheck = new SQLTableCheck();
// Use the SQL from install.sql compared with current table structure to create SQL to fix the tables
$sql_fixes = $tableCheck->build_table_fixes();
if ($sql_fixes != '') {
	// If there are fixes to be made, then display text box with SQL fixes
	print 	RCView::div(array('class'=>'red', 'style'=>''),
				RCView::img(array('src'=>'exclamation.png', 'class'=>'imgfix')) .
				"$testMsg<b>ERROR: Your REDCap database structure is incorrect!</b><br>
				Your current database table structure does not match REDCap's expected table structure, which means that 
				database tables and/or parts of tables are missing. This can happen if an issue occurs during an upgrade
				and is not corrected afterward.	Copy the SQL in the box below and execute it in the MySQL database 
				named `<b>$db</b>` where the REDCap database tables are stored. Once the SQL has been executed, reload this page
				to run this check again." .
				RCView::div(array('id'=>'sql_fix_div', 'style'=>'margin:10px 0;'),
					RCView::textarea(array('class'=>'x-form-field notesbox', 'style'=>'height:60px;font-size:11px;width:97%;height:100px;', 'readonly'=>'readonly', 'onclick'=>'this.select();'),
						"-- SQL TO REPAIR REDCAP TABLES\nSET FOREIGN_KEY_CHECKS = 0;\n$sql_fixes\nSET FOREIGN_KEY_CHECKS = 1;"
					)
				)
			);
} else {
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'), "$testMsg <img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> 
			<b>SUCCESSFUL!</b> - Your REDCap database structure is correct!");
}



## Check if cURL is installed
$testMsg = "<b>TEST 4: Check if PHP cURL extension is installed</b><br><br>";
// cURL is installed
if (function_exists('curl_init')) 
{
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'), $testMsg." <img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> <b>SUCCESSFUL!</b> - The cURL extension is installed.<br>");
}
// cURL not installed
else
{
	?>
	<div class="yellow">
		<?php echo $testMsg ?>
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation_orange.png" class="imgfix">
		<b>Your web server does NOT have the PHP library cURL installed.</b> cURL is NOT necessary to run REDCap normally, but it is highly 
		recommended. cURL is used for some optional functionality in REDCap, such as in the REDCap Shared Library, in the 
		Graphical Data View & Stats module, and is used when reporting site stats using the "automatic reporting" method. 
		To use cURL in REDCap, you will need to download cURL/libcurl, and then install and configure it with PHP on your web server. You will find 
		<a href='http://curl.haxx.se/libcurl/php/' target='_blank' style='font-family:arial;text-decoration:underline;'>instructions for cURL/libcurl installation here</a>. 
		Other documentation on cURL can be found at 
		<a href='http://us.php.net/manual/en/book.curl.php' target='_blank' style='font-family:arial;text-decoration:underline;'>http://us.php.net/manual/en/book.curl.php</a>.
	</div>
	<?php
	// If cURL is not installed AND allow_url_fopen is not enabled, then will not be able make outside requests
	if (ini_get('allow_url_fopen') != '1')
	{
		?>
		<div class="red">
			<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png" class="imgfix"> 
			<b>Your web server also does NOT have the PHP setting "allow_url_fopen" enabled.</b> 
			Without cURL installed AND without "allow_url_fopen" being enabled, REDCap will not be able to perform certain processes. 
			It is highly recommended that you either install cURL (as described above) or at least enable "allow_url_fopen" on the web server.
			To enable "allow_url_fopen", simply open your web server's PHP.INI file for editing and change the value of "allow_url_fopen" to 
			<b>On</b>. Then reboot your web server.
		</div>
		<?php
	}
}



## Check if can communicate with REDCap Consortium server (for reporting stats)
$testMsg = "<b>TEST 5: Checking communication with REDCap Consortium server</b> (".CONSORTIUM_WEBSITE.")<br>
			(used to report weekly site stats and connect to Shared Library)<br><br>";
// Send request to consortium server using cURL via an ajax request (in case it loads slowly)
?>
<div id="server_ping_response_div_parent" class="grayed" style="color:#333;background:#eee;">
	<?php echo $testMsg ?>
	<div id="server_ping_response_div">
		<img src="<?php echo APP_PATH_IMAGES ?>progress_circle.gif" class="imgfix"> 
		<b>Communicating with server... please wait</b>
	</div>
</div>
<script type="text/javascript">
var resp = "<b>FAILED!</b> - Could NOT communicate with the REDCap Consortium server. "
		 + "You will NOT be able to report your institutional REDCap statistics using the \"automatic reporting\" method, but you may try "
		 + "the \"manual reporting\" method instead (see the General Configuration page in the Control Center for this setting).";
var respClass = 'red';
var respColor = '#800000';
$(function(){
	// Ajax request
	var thisAjax = $.get(app_path_webroot+'ControlCenter/check_server_ping.php', { }, function(data) {
		if (data.length > 0 && trim(data) == "1") {
			resp = "<img src='"+app_path_images+"tick.png' class='imgfix'> <b>SUCCESSFUL!</b> - Communicated successfully to the REDCap Consortium server. "
					 + "You WILL be able to use the \"automatic reporting\" method to report your site stats, as well as use the REDCap Shared Library.";
			respClass = 'darkgreen';
			respColor = 'green';
		}
		$('#server_ping_response_div').html(resp);
		$('#server_ping_response_div_parent').removeClass('grayed').css('background','').addClass(respClass).css('color',respColor);
	});
	// Check after 10s to see if communicated with server, in case it loads slowly. If not after 10s, then assume cannot be done.
	var resptimer = resp;
	var maxAjaxTime = 10; // seconds
	setTimeout(function(){
		if (thisAjax.readyState == 1) {
			thisAjax.abort();
			$('#server_ping_response_div').html(resptimer);
			$('#server_ping_response_div_parent').removeClass('grayed').css('background','').addClass(respClass).css('color',respColor);
		}
	},maxAjaxTime*1000);
});
</script>
<?php




## Check if REDCap Cron Job is running
$testMsg = "<b>TEST 6: Check if REDCap Cron Job is running</b><br><br>";
if (Cron::checkIfCronsActive()) {
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'), $testMsg." <img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> <b>SUCCESSFUL!</b> - REDCap Cron Job is running properly.<br>");
} else {
	print RCView::div(array('class'=>'red'), 
		$testMsg .
		RCView::img(array('src'=>'exclamation.png','class'=>'imgfix')) . 
		RCView::b($lang['control_center_288']) . RCView::br() . $lang['control_center_289'] . RCView::br() . RCView::br() . 
		RCView::a(array('href'=>'javascript:;','style'=>'font-family:arial;','onclick'=>"window.location.href=app_path_webroot+'ControlCenter/cron_jobs.php';"), $lang['control_center_290'])
	);
}




/**
 * SECONDARY TESTS
 */
print "<p style='padding-top:15px;color:#800000;font-weight:bold;font-family:verdana;font-size:13px;'>Secondary tests</p>";


// Check for SSL
if (SSL || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')) {
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> <b style='color:green;'>Using SSL</b></div>";
} else {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <b>NOT using SSL 
			- CRITICAL:</b> It is HIGHLY recommended that you use SSL (i.e. https) on your web server when hosting REDCap. Otherwise, 
			data security could be compromised. If your server does not already have an SSL certificate, you will need to obtain one.</div>";
}

// Check for PHP 5.1.2 or higher
if (version_compare(PHP_VERSION, '5.1.2', '>=')) {
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> <b style='color:green;'>Using PHP 5.1.2 or higher</b></div>";
} else {
	print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png' class='imgfix'> <b>NOT using PHP 5.1.2 or higher 
			- RECOMMENDED:</b> It is recommended that you upgrade your web server to a more recent supported version of PHP (you are currently running PHP ".PHP_VERSION.").
			Some functionality within REDCap may not be functional on PHP versions prior to PHP 5.1.2.</div>";
}

// Check for MySQL 5 or higher (or MariaDB 10 or higher)
$q = db_query("select version()");
$mysql_version = db_result($q, 0);
list ($mysql_version_main, $nothing) = explode(".", $mysql_version, 2);
if ($mysql_version_main >= 10) {
	// MariaDB 10+
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> <b style='color:green;'>Using MariaDB 10 or higher</b></div>";
} elseif ($mysql_version_main >= 5) {
	// MySQL 5+
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> <b style='color:green;'>Using MySQL 5 or higher</b></div>";
} else {
	print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png' class='imgfix'> <b>NOT using MySQL 5 
			- RECOMMENDED:</b> It is recommended that you upgrade your database server to MySQL 5 (you are currently running MySQL $mysql_version).
			Some functionality within REDCap may not be functional on MySQL versions prior to MySQL 5.</div>";
}


## Check for GD library (version 2 and up)
if (gd2_enabled()) {
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'), 
			"<img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> <b>GD library (version 2 or higher) is installed</b>"
		);
} else {
	print RCView::div(array('class'=>'yellow'),
			RCView::img(array('src'=>'exclamation_orange.png','class'=>'imgfix')) . 
			"<b>GD Library (version 2 or higher) is not installed 
			- RECOMMENDED:</b> It is recommended that you <a target='_blank' href='http://php.net/manual/en/image.installation.php'>install the GD2 Library</a> in PHP 
			on your web server.	Some features in REDCap will not be available without GD2 installed, such as the ability for users to generate QR codes
			for survey links."
		);
}


/**
 * CHECK IF USING SSL WHEN THE REDCAP BASE URL DOES NOT BEGIN WITH "HTTPS"
 */
if (substr($redcap_base_url, 0, 5) == "http:") {
	?>
	<div id="ssl_base_url_check" class="red" style="display:none;padding-bottom:15px;font-family:arial;">
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png" class="imgfix">
		<b><?php echo $lang['control_center_4436'] ?></b><br>
		<?php echo $lang['control_center_4437'] ?>
	</div>
	<script type="text/javascript">
	if (window.location.protocol == "https:") {
		document.getElementById('ssl_base_url_check').style.display = 'block';
	}
	</script>
	<?php
}


// Check if mcrypt PHP extension is loaded
if (!mcrypt_loaded()) {
	print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png' class='imgfix'> <b>Mcrypt extenstion not installed 
			- RECOMMENDED:</b> It is recommended that the \"mcrypt\" PHP extension be installed on your web server since certain application 
			functions depend upon it. After installing Mcrypt, reboot your web server, and then reload this page.</div>";
}

// ZIP export support for downloading uploaded files in ZIP (Check for PHP 5.2.0+ and ZipArchive)
if (!Files::hasZipArchive()) {
	print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png' class='imgfix'> <b>ZipArchive is not installed 
			- RECOMMENDED:</b> It is recommended that you <a target='_blank' href='http://us2.php.net/downloads.php'>upgrade PHP</a> 
			on your web server to PHP 5.2.0 or higher
			(you are currently running PHP ".PHP_VERSION.") or alternatively that you install the 
			<a target='_blank' href='http://pecl.php.net/package/zip'>PECL Zip extension</a>, which comes with ZipArchive for
			PHP versions prior to 5.2.0.
			Some features in REDCap will not be available without ZipArchive installed, such as the feature in the
			Data Export Tool that allows users to download a ZIP file of all their uploaded files for records in a project.</div>";
}

// Check if emails can be sent via SMTP
$test_email_address = 'redcapemailtest@gmail.com';
$emailContents = "This email was sent when the user <b>".USERID."</b> opened the Configuration Check page for <b>".APP_PATH_WEBROOT_FULL."</b>
				(REDCap version $redcap_version).";
$email = new Message();
$email->setTo($test_email_address);
$email->setFrom($test_email_address);
$email->setSubject('REDCap Configuration Check: '.APP_PATH_WEBROOT_FULL);
$email->setBody($emailContents,true);
if ($email->send()) {
	print  "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> 
			<b style='color:green;'>REDCap is able to send emails</b></div>";
} else {
	print  "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <b>REDCap is not able to send emails
			- CRITICAL:</b> 
			It appears that your SMTP configuration (email-sending functionality) is either not set up or not configured correctly on the web server.
			It is HIGHLY recommended that you configure your email/SMTP server correctly in your web server's PHP.INI configuration file
			or else emails will not be able to be sent out from REDCap. REDCap requires email-sending capabilities for many 
			vital application functions. For more details on configuring email-sending capabilities on your web server, visit 
			<a href='http://php.net/manual/en/mail.configuration.php' target='_blank'>PHP's mail configuration page.</a></div>";
}

// Check if any whitespace has been output to the buffer unne
if ($prehtml !== false && strlen($prehtml) > 0) {
	print  "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <b>Error inside a REDCap configuration file:</b> 
			It appears that in one or more of REDCap's configuration files (e.g., database.php, webtools2/ldap/ldap_config.php, 
			webtools2/webdav/webdav_connection.php) there is some extra \"whitespace\", such as trailing spaces or empty lines that
			occur either before the opening PHP \"&lt;?php\" tag or after the closing PHP \"?&gt;\" tag. 
			Please make sure all preceding spaces, trailing spaces, and empty lines are removed from before or after the PHP tags in those files.
			Certain things in REDCap will not work correctly until this is fixed.</div>";
}


// Check if InnoDB engine is enabled in MySQL
if (!$tableCheck->innodb_enabled()) {
	print  "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <b>InnoDB engine is NOT enabled in MySQL
			- CRITICAL:</b> 
			It appears that your MySQL database server does not have the InnoDB table engine enabled, which is required for REDCap 
			to run properly. To enable it, open your my.cnf (or my.ini) configuration file for MySQL
			and remove all instances of \"--innodb=OFF\" and \"--skip-innodb\". Then restart MySQL, and then reload this page.
			If that does not work, then see the official MySQL documentation for how to enable InnoDB for your specific version of MySQL.</div>";
}


// Check max_input_vars for PHP 5.3.9+ (although it's been seen on PHP 5.3.3 - how?)
$max_input_vars = ini_get('max_input_vars');
$max_input_vars_min = 10000;
if (is_numeric($max_input_vars) && $max_input_vars < $max_input_vars_min) 
{
	// Give recommendation to increase max_input_vars
	print  "<div class='yellow'>
				<img src='".APP_PATH_IMAGES."exclamation_orange.png' class='imgfix'> 
				<b>'max_input_vars' could be larger - RECOMMENDED:</b> 
				It is highly recommended that you change your value for 'max_input_vars' in your PHP.INI configuration file to 
				a value of $max_input_vars_min or higher. If not increased, then REDCap might not be able to successfully save data when entered on a very long survey
				or data entry form.	You can modify this setting in your server's PHP.INI configuration file.
				If 'max_input_vars' is not found in your PHP.INI file, you should add it as <i style='color:#800000;'>max_input_vars = $max_input_vars_min</i>.
				Once done, restart your web server for the changes to take effect.
			</div>";
}

// Make sure 'upload_max_filesize' and 'post_max_size' are large enough in PHP so files upload properly
$maxUploadSize = maxUploadSize();
if ($maxUploadSize <= 2) { // <=2MB
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> 
			<b>'upload_max_filesize' and 'post_max_size' are too small:</b> 
			It is HIGHLY recommended that you change your value for both 'upload_max_filesize' and 'post_max_size' in PHP to a higher value, preferably 
			greater than 10MB (e.g., 32M). You can modify this in your server's PHP.INI configuration file, then restart your web server.
			At such small values, your users will likely have issues uploading files if you do not increase these.</div>";
} elseif ($maxUploadSize <= 10) { // <=10MB
	print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png' class='imgfix'> 
			<b>'upload_max_filesize' and 'post_max_size' could be larger
			- RECOMMENDED:</b> It is recommended that you change your value for both 'upload_max_filesize' and 'post_max_size' in PHP to a higher value, preferably 
			greater than 10MB (e.g., 32M). You can modify this in your server's PHP.INI configuration file, then restart your web server.
			At such small values, your users could potentially have issues uploading files if you do not increase these.</div>";
}

// Check if the PDF UTF-8 fonts are installed (otherwise cannot render special characters in PDFs)
$pathToPdfUtf8Fonts = APP_PATH_WEBTOOLS . "pdf" . DS . "font" . DS . "unifont" . DS;
if (!is_dir($pathToPdfUtf8Fonts)) 
{
	print  "<div class='yellow'>
				<img src='".APP_PATH_IMAGES."exclamation_orange.png' class='imgfix'> 
				<b>Missing UTF-8 fonts for PDF export - RECOMMENDED:</b> 
				In REDCap version 4.5.0, the capability was added for rendering special UTF-8 characters in PDF files
				exported from REDCap. This feature is not necessary but is good to have, especially for any international projects
				that might want to enter data or create field labels using special non-English characters. 
				Without this feature installed, some special characters might appear jumbled and unreadable in a PDF export.
				In order to utilize this capability, the UTF-8 fonts must be installed in REDCap. To do this, simply 
				<a href='https://iwg.devguard.com/trac/redcap/browser/misc/webtools2-pdf.zip?format=raw'>download the zip file of the UTF-8 fonts here</a>, 
				and then extract the contents of the zip file into the /webtools2 subfolder in the main REDCap folder on your web server. 
				The file structure should then be /webtools2/pdf/fonts/unifont. Overwrite any existing files or folders there.
				In addition, to utilize this feature, you must also have the 
				<a href='http://www.php.net/manual/en/mbstring.setup.php' target='_blank'>PHP extension \"mbstring\"</a> 
				installed on your web server. If not installed, install it, then reboot your web server.
			</div>";
}

// Must have PHP extension "mbstring" installed in order to render UTF-8 characters properly
if (!function_exists('mb_convert_encoding'))
{
	print  "<div class='yellow'>
				<img src='".APP_PATH_IMAGES."exclamation_orange.png' class='imgfix'> 
				<b>PHP extension \"mbstring\" not installed - RECOMMENDED:</b> 
				This extension is not necessary for REDCap but is good to have, especially for any international projects
				that might want to enter data or create field labels using special non-English characters. 
				Without this extension installed, some special characters might appear jumbled and unreadable in a PDF export.
				To utilize this feature, you must install the 
				<a href='http://www.php.net/manual/en/mbstring.setup.php' target='_blank'>PHP extension \"mbstring\"</a> 
				on your web server. Once installed, reboot your web server.
			</div>";
}

// Make sure 'innodb_buffer_pool_size' is large enough in MySQL
$q = db_query("SHOW VARIABLES like 'innodb_buffer_pool_size'");
if ($q && db_num_rows($q) > 0)
{
	while ($row = db_fetch_assoc($q)) {
		$innodb_buffer_pool_size = $row['Value'];
	}	
	$total_mysql_space = 0;
	$q = db_query("SHOW TABLE STATUS from `$db` like 'redcap_%'");
	while ($row = db_fetch_assoc($q)) {
		if (strpos($row['Name'], "_20") === false) { // Ignore timestamped archive tables
			$total_mysql_space += $row['Data_length'] + $row['Index_length'];
		}	
	}
	// Set max buffer pool size that anyone would probably need
	$innodb_buffer_pool_size_max_neccessary = 1*1024*1024*1024; // 1 GB
	// Compare
	if ($innodb_buffer_pool_size <= ($innodb_buffer_pool_size_max_neccessary*0.95) && $innodb_buffer_pool_size < ($total_mysql_space*1.1))
	{
		// Determine severity (red/severe is < 20% of total MySQL space)
		$class = ($innodb_buffer_pool_size < ($total_mysql_space*.2)) ? "red" : "yellow";
		$img   = ($class == "red") ? "exclamation.png" : "exclamation_orange.png";
		// Set recommend pool size
		$recommended_pool_size = ($total_mysql_space*1.1 < $innodb_buffer_pool_size_max_neccessary) ? $total_mysql_space*1.1 : $innodb_buffer_pool_size_max_neccessary;
		// Give recommendation
		print "<div class='$class'><img src='".APP_PATH_IMAGES."$img' class='imgfix'> <b>'innodb_buffer_pool_size' could be larger
				- RECOMMENDED:</b> It is recommended that you change your value for 'innodb_buffer_pool_size' in MySQL to a higher value.
				It is generally recommended that it be set to 10% larger than the size of your database, which is currently
				".round($total_mysql_space/1024/1024)."MB in size. So ideally <b>'innodb_buffer_pool_size' 
				should be set to at least ".round($recommended_pool_size/1024/1024)."MB</b> if possible 
				(it is currently ".round($innodb_buffer_pool_size/1024/1024)."MB). 
				Also, it is recommended that the size of 'innodb_buffer_pool_size' <b>not exceed 80% of your total RAM (memory)
				that is allocated to MySQL</b> on your database server.
				You can modify this in your MY.CNF configuration file (or MY.INI for Windows), then restart MySQL.
				If you do not increase this value, you may begin to see performance issues in MySQL.</div>";
	}
}


// Make sure magic_quotes in PHP is turned on
if (function_exists('get_magic_quotes_runtime') && get_magic_quotes_runtime()) {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <b>'magic_quotes_runtime' should be turned off:</b> 
			It is HIGHLY recommended that you change your value for 'magic_quotes_runtime' in PHP to 'Off'. 
			You can modify this in your server's PHP.INI configuration file, then restart your web server. REDCap will not function correctly with
			'magic_quotes_runtime' set to 'On'.</div>";
}

// Check if /temp is writable
$temp_dir = dirname(APP_PATH_DOCROOT) . DS . "temp" . DS;
// Try to make it writable, if it's not
if (!isDirWritable($temp_dir)) {
	@chmod($temp_dir, 0777);
	@system("chmod 777 $temp_dir");
}
if (isDirWritable($temp_dir)) {
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> <b style='color:green;'>\"temp\" directory is writable<br>Location: $temp_dir</b></div>";
} else {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <b>\"temp\" directory is NOT writable
			- CRITICAL:</b> It is HIGHLY recommended that you modify the REDCap \"temp\" folder (located at $temp_dir) so that it is
			writable for all server users. Some functionality within REDCap will not be functional until this folder is writable.</div>";
}

// Check if /edocs is writable
if ($edoc_storage_option == '0') {
	// LOCAL STORAGE
	$edocs_dir = EDOC_PATH;
	// Try to make it writable, if it's not
	if (!isDirWritable($edocs_dir)) {
		@chmod($edocs_dir, 0777);
		@system("chmod 777 $edocs_dir");
	}
	if (isDirWritable($edocs_dir)) {
		print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> <b style='color:green;'>File upload directory is writable<br>Location: ".EDOC_PATH."</b></div>";
	} else {
		print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <b>File upload directory is NOT writable
				- CRITICAL:</b> It is HIGHLY recommended that you modify the REDCap \"edocs\" folder (located at $edocs_dir) so that it is 
				writable for all server users. Some functionality within REDCap will not be functional until this folder is writable.</div>";
	}
	// Check if using default .../redcap/edocs/ folder for file uploads (not recommended)
	if (trim($edoc_path) == "")
	{
		print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> 
				<b>Directory that stores user-uploaded documents is exposed to the web:</b><br> 
				It is HIGHLY recommended that you change your location where user-uploaded files are stored. 
				Currently, they are being stored in REDCap's \"edocs\" directory, which is the default location and is completely accessible to the web.
				Although it is extremely unlikely that anyone could successfully retrieve a file from that location on the server via the web,
				it is still a potential security risk, especially if the documents contain sensitive information. 
				<br><br>
				It is recommend that you go to the Modules page in the Control Center and set a new path for your user-uploaded documents 
				(i.e. \"Enable alternate internal storage of uploaded files rather than default 'edocs' folder\"), and set it to
				a path on your web server that is NOT accessible from the web. Once you have
				changed that value, go to the 'edocs' directory and copy all existing files in that folder to the new location you just set.
				</div>";
	}
} elseif ($edoc_storage_option == '2') {
	// AMAZON S3 STORAGE
	// Try to write a file to that directory and then delete
	$test_file_name = date('YmdHis') . '_test.txt';
	$test_file_content = "test";
	$s3 = new S3($amazon_s3_key, $amazon_s3_secret, SSL);
	if ($s3->putObject($test_file_content, $amazon_s3_bucket, $test_file_name, S3::ACL_PUBLIC_READ_WRITE)) {
		// Success
		print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> 
				<b style='color:green;'>File upload directory is writable on Amazon S3 server in bucket \"$amazon_s3_bucket\"</b></div>";
		// Now delete the file we just created
		$s3->deleteObject($amazon_s3_bucket, $test_file_name);
	} else {
		// Failed
		print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <b>File upload directory is NOT writable on Amazon S3 server
				- CRITICAL:</b> It is HIGHLY recommended that you investigate your Amazon S3 connection info on the File Upload Settings page
				in the Control Center. REDCap is not able to successfully store files in the Amazon S3 bucket named \"$amazon_s3_bucket\". 
				Please make sure all the connection values are correct and also that the directory is writable. 
				Some functionality within REDCap will not be functional until files can be written to that bucket.</div>";
	}	
} else {
	// WEBDAV STORAGE
	// Try to write a file to that directory and then delete
	$test_file_name = date('YmdHis') . '_test.txt';
	$test_file_content = "test";
	// Store using WebDAV
	require_once (APP_PATH_CLASSES . "WebdavClient.php");
	require (APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php');
	$wdc = new WebdavClient();
	$wdc->set_server($webdav_hostname);
	$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
	$wdc->set_user($webdav_username);
	$wdc->set_pass($webdav_password);
	$wdc->set_protocol(1); // use HTTP/1.1
	$wdc->set_debug(false); // enable debugging?
	if (!$wdc->open()) {
		// Send error response
		return false;
	}
	if (substr($webdav_path,-1) != '/') {
		$webdav_path .= '/';
	}
	$http_status = $wdc->put($webdav_path . $test_file_name, $test_file_content);
	if ($http_status == '201') {
		// Success
		print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> 
				<b style='color:green;'>File upload directory is writable on WebDAV server \"$webdav_hostname\" at path \"$webdav_path\"</b></div>";
		// Now delete the file we just created
		$http_status = $wdc->delete($webdav_path . $test_file_name);
	} else {
		// Failed
		print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <b>File upload directory is NOT writable on WebDAV server
				- CRITICAL:</b> It is HIGHLY recommended that you modify your WebDAV server configuration or your WebDAV connection info in 
				<b>".dirname(APP_PATH_DOCROOT).DS."webtools2".DS."webdav".DS."webdav_connection.php</b>. The current connection info is attempting to communicate with
				WebDAV server \"$webdav_hostname\" at path \"$webdav_path\", and it is failing. Please make sure all the connection
				values are correct and also that the directory is writable. Some functionality within REDCap will not be functional until that WebDAV directory is writable.</div>";
	}	
	$wdc->close();
}








/** 
 * CONGRATULATIONS!
 */
if (isset($_GET['upgradeinstall']))
{
	print  "<p><br><hr><p><h3 style='font-size:20px;color:#800000;font-family:Arial;'>
			<img src='".APP_PATH_IMAGES."star.png'> CONGRATULATIONS! <img src='".APP_PATH_IMAGES."star.png'></h3>
			<p><b>It appears that the REDCap software has been correctly installed/upgraded and configured on your system. ";

	print  "It is ready for use.</b>
			You may begin using REDCap by first visiting the REDCap home page at the link below. 
			(It may be helpful to bookmark this link.)";

	print  "<div class='blue' style='padding:10px;'>
			<b>REDCap home page:</b>&nbsp;
			<a style='text-decoration:underline;'  href=\"".APP_PATH_WEBROOT_FULL."\">".APP_PATH_WEBROOT_FULL."</a>
			</div>";

	// Check global auth_meth value
	if ($auth_meth_global == "none")
	{
		print "<p><b>Currently, REDCap is using the authentication method \"None (Public)\"</b>, 
		which is utilized solely by a generic user named \"<b>site_admin</b>\". This authentication method is best to use if you are using a
		development server or if you have not yet worked out all issues with user authentication on your system. Once you have your site's authentication working
		properly, you may go into the Control Center to the Security & Authentication page to change the 
		authentication method to the one you will be implementing on 
		your system (i.e. LDAP, Table-based, RSA SecurID, LDAP/Table combination, Shibboleth). 		
		<b>If you decide to switch from \"None\" authentication to \"Table-based\"</b>, 
		please be sure to add yourself as a new Table-based user (on the User Control tab in the Control Center)
		before you switch over the authentication method, otherwise you won't be able to log in.";
	}
	
	print "<div style='margin-bottom:100px;'> </div>";
}

if (isset($_GET['upgradeinstall'])) {
	$objHtmlPage->PrintFooterExt();
} else {
	include 'footer.php';
}