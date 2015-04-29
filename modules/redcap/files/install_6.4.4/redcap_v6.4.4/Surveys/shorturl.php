<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
require_once APP_PATH_DOCROOT . "Surveys/survey_functions.php";


if (checkSurveyProject($_GET['survey_id']) && isset($_GET['hash']))
{
	// Retrieve shortened URL from URL shortener service
	$shorturl = Survey::getShortUrl(APP_PATH_SURVEY_FULL . '?s=' . $_GET['hash']);	
	// Ensure that we received a link in the expected format
	if ($shorturl !== false) exit($shorturl);
}
	
// If failed
exit("0");