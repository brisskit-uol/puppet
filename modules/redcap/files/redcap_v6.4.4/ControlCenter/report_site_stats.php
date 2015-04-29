<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// Get server's IP address
$server_ip = getServerIP();

// Set alternative hostname if we know the domain name in the URL is internal (i.e. without dots)
$alt_hostname = (strpos(SERVER_NAME, ".") === false) ? SERVER_NAME : "";





/**
 * SEND BASIC STATS
 */

// Skip this section if sendinging stats manually (page is only needed for sending library via Post)
if ($auto_report_stats) 
{
	// Instantiate Stats object
	$Stats = new Stats();
	// Get project count
	$num_prototypes = 0;
	$num_production = 0;
	$num_inactive   = 0;
	$num_archived   = 0;
	$q = db_query("select status, count(status) as count from redcap_projects where project_id not in (".$Stats->getIgnoredProjectIds().") group by status");
	while ($row = db_fetch_assoc($q)) {
		if ($row['status'] == '0') $num_prototypes = $row['count'];
		if ($row['status'] == '1') $num_production = $row['count'];
		if ($row['status'] == '2') $num_inactive   = $row['count'];
		if ($row['status'] == '3') $num_archived   = $row['count'];
	}
	
	// Get counts of project purposes
	$purpose_other = 0;
	$purpose_research = 0; 
	$purpose_qualimprove = 0;
	$purpose_operational = 0; 
	$q = db_query("select purpose, count(purpose) as count from redcap_projects where project_id not in (".$Stats->getIgnoredProjectIds().") group by purpose");
	while ($row = db_fetch_array($q)) 
	{
		switch ($row['purpose']) 
		{
			case '1': $purpose_other = $row['count']; break;
			case '2': $purpose_research = $row['count']; break;
			case '3': $purpose_qualimprove = $row['count']; break;
			case '4': $purpose_operational = $row['count']; break;
		}	
	}
	
	// DTS: Get count of production projects utilizing DTS
	$dts_count = 0;
	if ($dts_enabled_global)
	{
		$q = db_query("select count(1) from redcap_projects where status > 0 and dts_enabled = 1 and project_id not in (".$Stats->getIgnoredProjectIds().")");
		$dts_count = db_result($q, 0);
	}

	// DDP: Get count of production projects utilizing DDP
	$ddp_count = $ddp_records_imported = 0;
	if ($realtime_webservice_global_enabled)
	{
		$q = db_query("select count(1) from redcap_projects where status > 0 and realtime_webservice_enabled = 1 and project_id not in (".$Stats->getIgnoredProjectIds().")");
		$ddp_count = db_result($q, 0);	
		// Get count of records that have had data imported from source system in DDP-enabled projects
		$sql = "select count(distinct(concat(p.project_id, '-', r.record))) 
				from redcap_projects p, redcap_ddp_records r, redcap_ddp_records_data d 
				where p.status > 0 and p.realtime_webservice_enabled = 1 and p.project_id = r.project_id and r.mr_id = d.mr_id 
				and p.project_id not in (".$Stats->getIgnoredProjectIds().")";
		$ddp_records_imported = db_result(db_query($sql), 0);	
	}
	
	// Randomization: Get count of production projects using the randomization module (and have a prod alloc table uploaded)
	$rand_count = Stats::randomizationCount();

	// Get user count
	$num_users = db_result(db_query("select count(1) from redcap_user_information"), 0);

	// Get count of projects using Double Data Entry module (production only)
	$sql = "select count(1) from redcap_projects where status > 0 and double_data_entry = 1 and project_id not in (".$Stats->getIgnoredProjectIds().")";
	$total_dde = db_result(db_query($sql), 0);

	// Count parent/child linkings (production only)
	$q = db_query("select count(1) from redcap_projects where status > 0 and is_child_of is not null and is_child_of != ''
				   and project_id not in (".$Stats->getIgnoredProjectIds().")");
	$parent_child_linkings = db_result($q,0);

	// Count CAT assessment responses (partial and completed)
	$cat_responses_dev = $cat_responses_prod = 0;
	$sql = "select p.status, count(1) as count 
			from redcap_library_map l, redcap_surveys s, redcap_projects p, redcap_surveys_participants sp, redcap_surveys_response r 
			where p.project_id not in (".$Stats->getIgnoredProjectIds().") and l.promis_key is not null 
			and l.promis_key != '' and s.project_id = l.project_id and p.project_id = s.project_id 
			and s.survey_id = sp.survey_id and sp.participant_id = r.participant_id 
			and r.first_submit_time is not null and s.form_name = l.form_name 
			group by p.status";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		if ($row['status'] == '0') {
			// Dev
			$cat_responses_dev += $row['count'];
		} else {
			// Prod (includes Inactive and Archived)
			$cat_responses_prod += $row['count'];
		}
	}

	// Send site stats to the REDCap Consortium and get response back
	$url = CONSORTIUM_WEBSITE."collect_stats.php?hostname=".SERVER_NAME."&ip=$server_ip"
		 . "&alt_hostname=$alt_hostname&hostkey_hash=".Stats::getServerKeyHash()
		 . "&num_prots=$num_prototypes&num_prods=$num_production&num_archived=$num_archived&rnd982g4078393ae839z1_auto"
		 . "&purposes=$purpose_other,$purpose_research,$purpose_qualimprove,$purpose_operational"
		 . "&num_inactive=$num_inactive&num_users=$num_users&auth_meth=$auth_meth&version=$redcap_version"
		 . "&activeusers1m=".Stats::getActiveUsers(30)."&activeusers6m=".Stats::getActiveUsers(183)."&activeuserstotal=".Stats::getActiveUsers()
		 . "&usersloggedin1m=".Stats::getUserLogins(30)."&usersloggedin6m=".Stats::getUserLogins(183)."&usersloggedintotal=".Stats::getUserLogins()
		 . "&hostlabel=" . urlencode($institution)
		 . "&homepage_contact=".urlencode($homepage_contact)."&homepage_contact_email=$homepage_contact_email"
		 . "&dts=$dts_count&ddp=$ddp_count&ddp_records=$ddp_records_imported&rand=$rand_count&dde=$total_dde&parentchild=$parent_child_linkings"
		 . "&cats_dev=$cat_responses_dev&cats_prod=$cat_responses_prod"
		 . "&full_url=".urlencode(APP_PATH_WEBROOT_FULL)."&site_org_type=".urlencode($site_org_type);
	$response = http_get($url);

	// If stats were accepted from approved site, change date for stats last sent in config table
	if ($response == "1") {
		db_query("update redcap_config set value = '" . date("Y-m-d") . "' where field_name = 'auto_report_stats_last_sent'");
	}

	// In order to continue to library stats reporting, make sure cURL is installed and that Library usage is enabled 
	// and that $response above was successful (1).
	if ((!$shared_library_enabled && !$pub_matching_enabled) || $response == "0") {
		exit($response);
	}

}




// SEND LIBRARY STATS (as separate Post request)
$libresponse = "1";
if ($shared_library_enabled) {
	$libresponse = Stats::sendSharedLibraryStats();
	if ($libresponse == "" || $libresponse === false) $libresponse = "0";
}

// SEND PUB MATCHING STATS (as separate Post request)
$pubstats_response = "1";
if ($pub_matching_enabled) {
	$pubstats_response = Stats::sendPubMatchList();
	if ($pubstats_response == "" || $pubstats_response === false) $pubstats_response = "0";
}

// Return response if called asynchronously, else redirect to Control Center
if ($auto_report_stats) {
	print ($libresponse && $pubstats_response) ? "1" : "0";
} else {
	redirect(APP_PATH_WEBROOT . "ControlCenter/index.php?" . $_SERVER['QUERY_STRING']);
}
