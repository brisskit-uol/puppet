<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/


// Turn off error reporting
ini_set('display_errors', 1); // Enable this here so that we can easily turn on error reporting with error_reporting(E_ALL); if needed.
error_reporting(0); // To turn on error reporting, change to error_reporting(E_ALL);
// Prevent caching
header("Expires: 0");
header("cache-control: no-store, no-cache, must-revalidate"); 
header("Pragma: no-cache");
// Set header to allow any domain to send requests (this will be especially needed for the API in certain cases)
header("Access-Control-Allow-Origin: *");
## SET NECESSARY SERVER-LEVEL SETTINGS
// Set this PHP value, which is used when reading uploaded CSV files
ini_set('auto_detect_line_endings', true);
// Increase memory limit in case needed for intensive processing
if (str_replace("M", "", ini_get('memory_limit')) < 512) ini_set('memory_limit', '512M');
// Increase initial server value to account for a lot of processing
ini_set('max_execution_time', 1200);
set_time_limit(1200);
// Make sure the character set is UTF-8
ini_set('default_charset', 'UTF-8');
## DEFINE NECESSARY CONSTANTS AND GLOBAL VARIABLES
// Get current date/time to use for all database queries (rather than using MySQL's clock with now())
define("SCRIPT_START_TIME", microtime(true));
defined("NOW") 	 or define("NOW", date('Y-m-d H:i:s'));
defined("TODAY") or define("TODAY", date('Y-m-d'));
defined("today") or define("today", TODAY); // The lower-case version of the TODAY constant allows for use in Data Quality rules (e.g., datediff)
// Current jQuery UI version
defined("JQUERYUI_VERSION") or define("JQUERYUI_VERSION", "1.8.12");
// Set time delay (in days) for deleting projects after they have been scheduled for deletion
defined("PROJECT_DELETE_DAY_LAG") or define("PROJECT_DELETE_DAY_LAG", 30);
// Set API key for Google Maps API v3
defined("GOOGLE_MAP_KEY") or define("GOOGLE_MAP_KEY", "AIzaSyCN9Ih8gzAxfPmvijTP8HsE0PAKU8X1Nt0");
// Define DIRECTORY_SEPARATOR as DS for less typing
defined("DS") or define("DS", DIRECTORY_SEPARATOR);
// Add constant if doesn't exists (it only exists in PHP 5.3+)
if (!defined('ENT_IGNORE')) define('ENT_IGNORE', 0);
// Add constant if doesn't exists (it only exists in PHP 5.4+)
if (!defined('ENT_SUBSTITUTE')) define('ENT_SUBSTITUTE', ENT_IGNORE);
// Detect if a mobile device (don't consider tablets mobile devices)
$mobile_detect = new Mobile_Detect();
$isTablet = $mobile_detect->isTablet();
$isMobileDevice = ($mobile_detect->isMobile() && !$isTablet);
// Detect if using iOS (or an iPad specifically)
$isIOS = ($mobile_detect->is('iOS'));
$isIpad = ($mobile_detect->is('iPad'));	
// Check if using Internet Explorer
$isIE = (isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'Trident') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false));
// Detect if the current request is an AJAX call (via $_SERVER['HTTP_X_REQUESTED_WITH'])
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
// Determine if the web server running PHP is any type of Windows OS (boolean)
$isWindowsServer = ((defined('PHP_OS') && strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') || (strtoupper(substr(php_uname('s'), 0, 3)) == 'WIN'));
// Set interval at which REDCap will turn on a listener for any clicking, typing, or mouse movent (used for auto-logout) 
$autologout_resettime = 3; // After X minutes, it will call ProjectGeneral/login_reset.php
// Default keywords used for querying identifiers in Identifier Check module
$identifier_keywords_default = "name, street, address, city, county, precinct, zip, postal, date, phone, fax, mail, ssn, "
							 . "social security, mrn, dob, dod, medical, record, id, age";
// Set up array of pages to ignore for logging page views and counting page hits
$noCountPages = array(	
	"DataEntry/auto_complete.php", "DataEntry/search.php", "ControlCenter/report_site_stats.php", "Calendar/calendar_popup_ajax.php", 
	"Reports/report_builder_ajax.php", "ControlCenter/check.php", "DataEntry/image_view.php", "ProjectGeneral/project_stats_ajax.php", 
	"SharedLibrary/image_loader.php", "DataExport/plot_chart.php"
);
// List of specific pages where a file is downloaded (rather than a webpage displayed) where GZIP should be disabled
$fileDownloadPages = array( "DataExport/data_export_csv.php", "DataExport/sas_pathway_mapper.php", "DataExport/spss_pathway_mapper.php",
							"DataImport/import_template.php", "Design/data_dictionary_download.php", "Design/data_dictionary_demo_download.php",
							"FileRepository/file_download.php", "Locking/esign_locking_management.php", "Logging/csv_export.php",
							"Randomization/download_allocation_file.php", "Randomization/download_allocation_file_template.php",
							"Reports/report_export.php", "SendIt/download.php", "Surveys/participant_export.php", "DataEntry/file_download.php", 
							"PDF/index.php", "ControlCenter/pub_matching_ajax.php", "ControlCenter/create_user_bulk.php",
							"DataQuality/data_resolution_file_download.php", "DataQuality/field_comment_log_export.php",
							"DataExport/file_export_zip.php",
							// The pages below aren't used for file downloads, but we need to disable GZIP on them anyway 
							// (often because their output is so large that it uses too much memory to keep in buffer).
							"DataExport/index.php"
						  );
// Reserved field names that cannot be used as project field names/variables
$reserved_field_names = array(	
	// These variables are forbidden because they are used internally by REDCap
	"redcap_event_name"=>"Event Name", "redcap_csrf_token"=>"REDCap CSRF Token", 
	"redcap_survey_timestamp"=>"Survey Timestamp", "redcap_survey_identifier"=>"Survey Identifier", 
	"redcap_survey_return_code"=>"Survey Return Code", "redcap_data_access_group"=>"Data Access Group",
	"hidden_edit_flag"=>"hidden_edit_flag",
	// These variables are forbidden because some web browsers (mostly IE) throw errors when they are using in branching logic or calculations
	"submit"=>"submit", "new"=>"new", "return"=>"return", "continue"=>"continue", "case"=>"case", "switch"=>"switch",
	"class"=>"class", "enum"=>"enum", "catch"=>"catch", "throw"=>"throw", "document"=>"document", "super"=>"super", 
	"focus"=>"focus", "elements"=>"elements"
);
// Array of all date formats
$default_datetime_format_system = 'M/D/Y_12';
$datetime_formats = array(	'M-D-Y_24', 'M-D-Y_12', 'M/D/Y_24', 'M/D/Y_12', 'M.D.Y_24', 'M.D.Y_12', 
							'D-M-Y_24', 'D-M-Y_12', 'D/M/Y_24', 'D/M/Y_12', 'D.M.Y_24', 'D.M.Y_12', 
							'Y-M-D_24', 'Y-M-D_12', 'Y/M/D_24', 'Y/M/D_12', 'Y.M.D_24', 'Y.M.D_12');
// Array of all number formats for decimal and thousands separator
$default_number_format_decimal_system = '.';
$number_format_decimal_formats = array('.', ',');
$default_number_format_thousands_sep_system = ',';
$number_format_thousands_sep_formats = array(',', '.', "'", 'SPACE', '');
// Array of config fields that will overwrite project-level fields of the same name if the project-level fields are blank
$overwritableGlobalVars = array('project_contact_name', 'project_contact_email', 'project_contact_prod_changes_name', 
								'project_contact_prod_changes_email', 'institution', 'site_org_type', 'grant_cite', 'headerlogo');
// Set maximum number of records before the record selection drop-downs disappear on Data Entry Forms
$maxNumRecordsHideDropdowns = 25000;
// Set the HTML tags that are allowed for use in user-defined labels/text (e.g., field labels, survey instructions)
define('ALLOWED_TAGS', '<label><pre><p><a><br><br/><center><font><b><i><u><h3><h2><h1><hr><table><tr><th><td><img><span><div><em><strong><acronym>');
// Set class autoload function
$rc_autoload_function = '__autoload';
spl_autoload_register($rc_autoload_function);
// Set error handler
set_error_handler('myErrorHandler');
// Register all functions to be run at shutdown of script
register_shutdown_function('update_log_view_request_time');
register_shutdown_function('session_write_close');
register_shutdown_function('fatalErrorShutdownHandler');
// Set session handler functions
session_set_save_handler("on_session_start", "on_session_end", "on_session_read", "on_session_write", "on_session_destroy", "on_session_gc");
// Set session cookie parameters to make sure that HttpOnly flag is set as TRUE for all cookies created server-side
session_set_cookie_params(0, '/', '', false, true); 
// Enable output to buffer
ob_start();
// Make sure dot is added to include_path in case it is missing. Also add path to Classes/PEAR inside REDCap.
set_include_path('.' . PATH_SEPARATOR . 
				 dirname(dirname(__FILE__)) . DS . 'Classes' . DS . 'PEAR' . DS . PATH_SEPARATOR . 
				 get_include_path());
// Make initial database connection
$rc_connection;
db_connect();
// Clean $_GET and $_POST to prevent XSS and SQL injection
cleanGetPost();
// Pull values from redcap_config table and set as global variables
setConfigVals();
// Make sure the PHP version is compatible
check_php_version();