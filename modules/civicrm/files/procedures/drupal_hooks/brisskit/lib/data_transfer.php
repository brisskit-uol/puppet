<?php

/* 
  Bugs:
  =====
  
  A couple of exernally-defined date format constants are used:
    DATE_ATOM - http://php.net/manual/en/class.datetime.php
    DATE_FORMAT_ISO 

  The latter one is defined twice!!
    /var/local/brisskit/drupal/site/civicrm/sites/all/modules/date/date_api/date_api.module:define('DATE_FORMAT_ISO', "Y-m-d\TH:i:s");
    /var/local/brisskit/drupal/site/civicrm/sites/all/modules/civicrm/packages/Date.php:define('DATE_FORMAT_ISO', 1);

  We should probably use our own definitions, perhaps based on standard php ones.

  But it looks like that field isn't used by add_activity_to_case anyway - needs a bit more investigation

*/


$_SERVER['REMOTE_ADDR'] = '127.0.0.1';  // Or we get annoying messages TODO
ini_set("error_log", '~/bk_cron/errors.log');


require_once "init_drupal.php";
//require_once "core.php";
require_once "integration.php";
//require_once "constants.php";
require_once BK_EXTENSIONS_DIR . "/../../../brisskit_civicrm.settings.php";
require_once BK_EXTENSIONS_DIR . "/uk.ac.le.brisskit/CRM/Brisskit/BK_Constants.php";
require_once BK_EXTENSIONS_DIR . "/uk.ac.le.brisskit/CRM/Brisskit/BK_Utils.php";

#am I already running?
$cmd    =    'ps aux | grep "data_transfer.php" | grep -v "grep" | wc -l';
$result   =   (integer)exec($cmd);
BK_Utils::audit($result);

if ($result>2) { 
	error_log("The scheduled data transfer script is already running!"); exit;
}

date_default_timezone_set('UTC');

init_drupal();


$acs = get_scheduled_dt_activity_info();

foreach ($acs as $ac) {
	BK_Utils::audit("data transfer target:".$ac['target']."\n");
	$details = "";
	$cancelled = false;

	if ($ac['target']=="undefined") {
		$details = isset($ac['activity']['details']) ? $ac['activity']['details']."\n" :"";
		$ac['activity']['details']=$details.date(DATE_ATOM)." - the type of transfer should be defined in the subject as 'Transfer to X'";
		$ac['activity']['status']="Cancelled";
		$cancelled=true;
	}
	else {
		$details = isset($ac['activity']['details']) ? $ac['activity']['details']."\n" :"";
                $ac['activity']['details']=$details.date(DATE_ATOM)." - contact details sent to ". $ac['target'];
                $ac['activity']['status']="Pending";
	}
	#set date to null to prevent date resetting to 0 (no doesn't make sense to me either!)
	$ac['activity']['activity_date_time']=null;
 	# 3. Updates the activity status to Pending and adds a log to the activity ÔDetailsÕ
	BK_Utils::update_activity($ac['activity']);
	
	if ($cancelled) continue;	
	
	# 5. Sends PDO and the activity_id to the appropriate web-service
	try {
		//$method = "post_contact_to_".$ac['target'];
		//$method($ac['contact'],$ac['activity']['id']);

    post_contact_to_server($ac['contact'], $ac['activity']['id'], $ac['target']);
	}
	catch(Exception $ex) {
		$new_ac = $ac['activity'];
		$ac['activity']['details']=$details.date(DATE_ATOM)." - problem contacting ".$ac['target']." (".$ex->getMessage().")";
		$ac['activity']['status']="Unreachable";
		BK_Utils::update_activity($ac['activity']);
		$new_ac['case_id'] = $ac['case_id'];
		$new_ac['activity_type']=BK_Constants::ACTIVITY_DATA_TRANSFER;
		$new_ac['activity_date_time']=date(DATE_FORMAT_ISO);
		$new_ac['status']="Scheduled";
		$new_ac['id']=null;
		$new_ac['ignore_dups']=true;

		BK_Utils::add_activity_to_case($new_ac);
	}
}

function get_scheduled_dt_activity_info() {
	require_once "api/v3/Activity.php";
	require_once "api/v3/utils.php";
	require_once "CRM/Case/BAO/Case.php";

	$atid = BK_Utils::get_option_group_value("activity_type", BK_Constants::ACTIVITY_DATA_TRANSFER);
	$asid = BK_Utils::get_option_group_value("activity_status", BK_Constants::ACT_STATUS_SCHEDULED);
	BK_Utils::audit("atid:$atid, asid:$asid");
	$params = array('activity_type_id'=>$atid, 'status_id'=>$asid, 'is_deleted' => '0');

  print_r($params);

	$acts_json = civicrm_api3_activity_get($params);
  print_r($acts_json);
	if ($acts_json['count']==0) {
		return array();
	}
	
	$acts = $acts_json['values'];

  print ($acts);

	$acs = array();
	foreach ($acts as $id => $act) {
		$case_id = CRM_Case_BAO_Case::getCaseIdByActivityId($id);
		# 1. Extracts the case details, and the associated participant
		$contact = BK_Utils::get_case_contact_with_custom_values($case_id);
		
		# 2. Determines the target by using the text in the activity subject (e.g. Transfer to i2b2 or Transfer to caTissue)
		$subject = $act['subject'];
		preg_match('/[t|T]ransfer to (\w+)/',$subject,$matches);

		$target = strtolower(isset($matches[1]) ? $matches[1] : "undefined");

		$acs[]=array('activity'=>$act, 'contact'=>$contact, 'case_id'=>$case_id, 'target'=>$target);
	}
	return $acs; 
}
