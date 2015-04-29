<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
include_once APP_PATH_DOCROOT . "Logging/logging_functions.php";
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

?>
<script type="text/javascript">
$(function() {
	$('#beginTime, #endTime').datetimepicker({
		onClose: function(){ pageLoad() },
		buttonText: 'Click to select a date', yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
		hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time', 
		showOn: 'button', buttonImage: app_path_images+'date.png', buttonImageOnly: true, timeFormat: 'hh:mm', constrainInput: false
	});
});
function pageLoad(event) {
	if (event != null && event.keyCode != 13) {
		return;
	}
	showProgress(1);
	window.location.href=app_path_webroot+page+'?pid='+pid+'&beginTime='+$('#beginTime').val()+'&endTime='+$('#endTime').val()+'&usr='+$('#usr').val()+'&record='+$('#record').val()+'&logtype='+$('#logtype').val();
}
</script>
<?php

renderPageTitle("<div style='float:left;'>
					<img src='".APP_PATH_IMAGES."report.png'> ".$lang['app_07']."
				 </div>
				 <div style='float:right;'>
					<img src='" . APP_PATH_IMAGES . "xls.gif' class='imgfix'> 
					<a href='" . APP_PATH_WEBROOT . "Logging/csv_export.php?pid=$project_id' style='color:#004000;font-size:11px;text-decoration:underline;font-weight:normal;'>{$lang['reporting_01']}</a>
				 </div><br><br>");

print "<p>{$lang['reporting_02']}</p>";

//If user is in DAG, only show info from that DAG and give note of that
if ($user_rights['group_id'] != "") {
	print  "<p style='color:#800000;padding-bottom:10px;'>{$lang['global_02']}: {$lang['reporting_04']}</p>";
}

print "<div>
		<table border=0 cellpadding=0 cellspacing=0><tr><td class='blue'>
			<table border=0 cellpadding=0 cellspacing=3>";
	  
//FILTER by event type
print  "<tr><td style='text-align:right;padding-right:5px;'>{$lang['reporting_08']}</td><td> 
		<select id='logtype' class='x-form-text x-form-field' style='padding-right:0;height:22px;' onchange=\"window.location.href='".PAGE_FULL."?pid=$project_id&usr='+\$('#usr').val()+'&record='+\$('#record').val()+'&beginTime='+\$('#beginTime').val()+'&endTime='+\$('#endTime').val()+'&logtype='+this.value+addGoogTrans();\">
			<option value='' "; if ($_GET['logtype'] == '') print "selected"; print  ">{$lang['reporting_09']}</option>
			<option value='export' "; if ($_GET['logtype'] == 'export') print "selected"; print  ">{$lang['reporting_10']}</option>
			<option value='manage' "; if ($_GET['logtype'] == 'manage') print "selected"; print  ">{$lang['reporting_33']}</option>
			<option value='user' "; if ($_GET['logtype'] == 'user') print "selected"; print  ">{$lang['reporting_50']}</option>
			<option value='record' "; if ($_GET['logtype'] == 'record') print "selected"; print  ">{$lang['reporting_12']}</option>
			<option value='record_add' "; if ($_GET['logtype'] == 'record_add') print "selected"; print  ">{$lang['reporting_13']}</option>
			<option value='record_edit' "; if ($_GET['logtype'] == 'record_edit') print "selected"; print  ">{$lang['reporting_14']}</option>
			<option value='lock_record' "; if ($_GET['logtype'] == 'lock_record') print "selected"; print  ">{$lang['reporting_34']}</option>
			<option value='page_view' "; if ($_GET['logtype'] == 'page_view') print "selected"; print  ">{$lang['reporting_35']}</option>
		</select>
		</td></tr>";



// If user is in DAG, limit viewing to only users in their own DAG
$dag_users_array = getDagUsers($project_id, $user_rights['group_id']);
$dag_users = empty($dag_users_array) ? "" : "AND user in (" . prep_implode($dag_users_array) . ")";
	
// Obtain array of all Data Quality rules (in case need to reference them by name in logging display)
$dq = new DataQuality();
$dq_rules = $dq->getRules();

## FILTER by username
print  "<tr>
			<td style='text-align:right;padding-right:5px;'>
				{$lang['reporting_15']}
			</td>
		<td> 
			<select id='usr' class='x-form-text x-form-field' style='padding-right:0;height:22px;' onchange=\"window.location.href='".PAGE_FULL."?pid=$project_id&logtype='+\$('#logtype').val()+'&record='+\$('#record').val()+'&beginTime='+\$('#beginTime').val()+'&endTime='+\$('#endTime').val()+'&usr='+this.value+addGoogTrans();\">
				<option value='' " . ($_GET['usr'] == '' ? "selected" : "" ) . ">{$lang['reporting_16']}</option>";
//Get user names of ALL past and present users (some may no longer be current users)
$all_users = array();
//Call rights table for current users
$q = db_query("select username from redcap_user_rights where project_id = $project_id and username != ''");
while ($row = db_fetch_array($q)) {
	$all_users[] = $row['username'];
}
//Call log_event table for past users
$q = db_query("select distinct user from redcap_log_event where project_id = $project_id and user not in ('', 'ADMIN', 'SYSTEM')");
while ($row = db_fetch_array($q)) {
	$all_users[] = $row['user'];
}
$all_users = array_unique($all_users);
sort($all_users);
//Loop through all users
foreach ($all_users as $this_user) {
	// If in a DAG, ignore users not in their DAG
	if ($user_rights['group_id'] != "") {
		if (!in_array($this_user, $dag_users_array)) continue;
	}
	// Render option
	print "<option value='$this_user' "; 
	if ($_GET['usr'] == $this_user) print "selected"; 
	print ">$this_user</option>";
}
print  "</select>
		</td></tr>";


		

## FILTER BY RECORD
// If a non-record-type event is selected, then blank this drop-down because it wouldn't make sense to use it
if (strpos($_GET['logtype'], 'record') === false && $_GET['logtype'] != '') {
	$_GET['record'] = "";
}
print  "<tr>
			<td style='text-align:right;padding-right:5px;'>
				{$lang['reporting_36']}
			</td>
			<td> 
				<select id='record' class='x-form-text x-form-field' style='padding-right:0;height:22px;' onchange=\"window.location.href='".PAGE_FULL."?pid=$project_id&logtype='+\$('#logtype').val()+'&usr='+\$('#usr').val()+'&beginTime='+\$('#beginTime').val()+'&endTime='+\$('#endTime').val()+'&record='+this.value+addGoogTrans();\">
					<option value='' " . ($_GET['record'] == '' ? "selected" : "" ) . ">{$lang['reporting_37']}</option>";
// Retrieve list of all records
$q = db_query("select distinct record from redcap_data where project_id = $project_id and field_name = '$table_pk'");
$records = array();
while ($row = db_fetch_array($q)) {
	$records[] = $row['record'];
}
natcasesort($records);
foreach ($records as $this_record) {
	// Render option
	print "<option value='$this_record' " 
		. (($_GET['record'] == $this_record) ? "selected" : "") 
		. ">$this_record</option>";
}
unset($records);
print  "		</select>
			</td>
		</tr>";


		
		
// Set filter to specific user's logging actions
$filter_user = (isset($_GET['usr']) && $_GET['usr'] != '') ? "AND user = '".$_GET['usr']."'" : "";

// Set filter for logged event type
$filter_logtype = setEventFilterSql($_GET['logtype']);

// Sections results into multiple pages of results by limiting to 100 per page. $begin_limit is record to begin with.
$begin_limit = (isset($_GET['limit']) && $_GET['limit'] != '') ? $_GET['limit'] : 0;

// Set filter for record name
$filter_record = (isset($_GET['record']) && $_GET['record'] != '') ? "AND object_type in ('','redcap_data','redcap_locking_data','redcap_esignatures','redcap_edocs_metadata') and event in ('MANAGE','ESIGNATURE','LOCK_RECORD','UPDATE','INSERT','DELETE','DOC_UPLOAD','DOC_DELETE','OTHER') and pk = '".prep($_GET['record'])."'" : '';


# FILTER BY BEGIN AND END TIME
// Prep begin and end times
$beginTime_userPref = (isset($_GET['beginTime']) && $_GET['beginTime'] != "") ? strip_tags(label_decode(urldecode($_GET['beginTime']))) : '';
$endTime_userPref   = (isset($_GET['endTime']) && $_GET['endTime'] != "") ? strip_tags(label_decode(urldecode($_GET['endTime']))) : '';
// Convert to Y-M-D timestamps
$beginTime_YMDts = DateTimeRC::format_ts_to_ymd($beginTime_userPref);
if ($beginTime_YMDts != '') $beginTime_YMDts .= ":00";
$beginTime_YMDint = preg_replace('/[^\d]/', '', $beginTime_YMDts);
$endTime_YMDts = DateTimeRC::format_ts_to_ymd($endTime_userPref);
if ($endTime_YMDts != '') $endTime_YMDts .= ":00";
$endTime_YMDint = preg_replace('/[^\d]/', '', $endTime_YMDts);


//Show dropdown for displaying pages at a time
print  "<tr>
			<td style='text-align:right;padding-right:5px;'>
				{$lang['reporting_17']}
			</td>
			<td> 
				<select name='pages' class='x-form-text x-form-field' style='padding-right:0;height:22px;' onchange='window.location.href=\"".PAGE_FULL."?pid=$project_id&logtype=".htmlspecialchars(strip_tags(label_decode($_GET['logtype'])), ENT_QUOTES)."&usr=".htmlspecialchars(strip_tags(label_decode($_GET['usr'])), ENT_QUOTES)."&record=".htmlspecialchars(strip_tags(label_decode($_GET['record'])), ENT_QUOTES)."&limit=\"+this.value+addGoogTrans();'>";
## Calculate number of pages of results for dropdown
// Page view logging only
if ($_GET['logtype'] == 'page_view') {
	if ($filter_user == '' && $filter_record == '' && $dag_users == '') {
		$sql = "SELECT count(1) FROM redcap_log_view WHERE project_id = $project_id $filter_logtype";
	} else {
		$sql = "SELECT count(1) FROM redcap_log_view WHERE project_id = $project_id $filter_logtype $filter_user $dag_users";
	}
	if ($beginTime_YMDts != "") $sql .= " AND ts >= '".prep($beginTime_YMDts)."' ";
	if ($endTime_YMDts != "") $sql .= " AND ts <= '".prep($endTime_YMDts)."' ";
// Regular logging view
} else {
	if ($filter_logtype == '' && $filter_user == '' && $filter_record == '' && $dag_users == '') {
		$sql = "SELECT count(1) FROM redcap_log_event force index (user_project) WHERE project_id = $project_id";	
	} else {
		$sql = "SELECT count(1) FROM redcap_log_event WHERE project_id = $project_id $filter_logtype $filter_user $filter_record $dag_users";	
	}
	if ($beginTime_YMDint != "") $sql .= " AND ts >= '".prep($beginTime_YMDint)."' ";
	if ($endTime_YMDint != "") $sql .= " AND ts <= '".prep($endTime_YMDint)."' ";
}
$num_total_files = db_result(db_query($sql),0);
$num_pages = ceil($num_total_files/100);
//Loop to create options for "Displaying files" dropdown
for ($i = 1; $i <= $num_pages; $i++) 
{
	$end_num = $i * 100;
	$begin_num = $end_num - 99;
	$value_num = $end_num - 100;
	if ($end_num > $num_total_files) $end_num = $num_total_files;
	print "<option value='$value_num'" . (($_GET['limit'] == $value_num) ? " selected " : "") . ">$begin_num - $end_num</option>";
}
print  "		</select>
			</td>
		</tr>";

# FILTER BY BEGIN AND END TIME
//Show dropdown for displaying Begin time
print  "<tr>
			<td style='padding-top:6px;' colspan='2'>
				<span style='margin-right:4px;'>{$lang['reporting_51']}</span>
				<input type='text' onfocus=\"$(this).next('img').trigger('click');\" class='x-form-text x-form-field' style='width:103px;' id='beginTime' onblur=\"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);\" value=\"".cleanHtml2($beginTime_userPref)."\"    onkeypress=\"pageLoad(event)\">
				<span style='margin:0 4px 0 6px;'>{$lang['data_access_groups_ajax_14']}</span>
				<input type='text' onfocus=\"$(this).next('img').trigger('click');\" class='x-form-text x-form-field' style='width:103px;' id='endTime' onblur=\"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);\" value=\"".cleanHtml2($endTime_userPref)."\"  onkeypress=\"pageLoad(event)\">
			</td>
		</tr>";
print  "
	</table>
</td></tr>
</table>
</div><br>";

/** 
 * QUERY FOR TABLE DISPLAY
 */
// Page view logging only
if ($_GET['logtype'] == 'page_view') {
	if ($filter_user == '' && $filter_record == '' && $dag_users == '') {
		$SQL_STRING = "SELECT ts*1 as ts, user, '0' as legacy, full_url, event, page, event_id, record, form_name 
					   FROM redcap_log_view ".(db_get_version() > 5.0 ? "force index for order by (PRIMARY)" : "")."
					   WHERE project_id = $project_id $filter_logtype ";
	} else {
		$SQL_STRING = "SELECT ts*1 as ts, user, '0' as legacy, full_url, event, page, event_id, record, form_name 
					   FROM redcap_log_view ".(db_get_version() > 5.0 ? "force index for order by (PRIMARY)" : "")."
					   WHERE project_id = $project_id $filter_logtype $filter_user $dag_users ";
	}
	if ($beginTime_YMDts != "") $SQL_STRING .= " AND ts >= '".prep($beginTime_YMDts)."' ";
	if ($endTime_YMDts != "") $SQL_STRING .= " AND ts <= '".prep($endTime_YMDts)."' ";
	$SQL_STRING .= " ORDER BY log_view_id DESC LIMIT $begin_limit,100";
// Regular logging view
} else {
	if ($filter_logtype == '' && $filter_user == '' && $filter_record == '' && $dag_users == '') {
		$SQL_STRING = "SELECT * FROM redcap_log_event force index (user_project) 
					   ".(db_get_version() > 5.0 ? "force index for order by (PRIMARY)" : "")." 
					   WHERE project_id = $project_id ";
	} else {
		$SQL_STRING = "SELECT * FROM redcap_log_event 
					  ".(db_get_version() > 5.0 ? "force index for order by (PRIMARY)" : "")."
					   WHERE project_id = $project_id $filter_logtype $filter_user $filter_record $dag_users ";
	}
	if ($beginTime_YMDint != "") $SQL_STRING .= " AND ts >= '".prep($beginTime_YMDint)."' ";
	if ($endTime_YMDint != "") $SQL_STRING .= " AND ts <= '".prep($endTime_YMDint)."' ";
	$SQL_STRING .= " ORDER BY log_event_id DESC LIMIT $begin_limit,100";	
}
$QSQL_STRING = db_query($SQL_STRING);

if (db_num_rows($QSQL_STRING) < 1) {

	print "<div align='center' style='padding:20px 20px 20px 20px;width:100%;max-width:700px;'>
		   <span class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png' class='imgfix'> {$lang['reporting_18']}</span>
		   </div>";

} else {

	// Obtain names of Events (for Longitudinal projects) and put in array
	$event_ids = array();
	if ($longitudinal) {
		// Query list of event names
		$sql = "select e.event_id, e.descrip, a.arm_name, a.arm_num from redcap_events_metadata e, redcap_events_arms a where 
				a.arm_id = e.arm_id and a.project_id = " . PROJECT_ID;
		$q = db_query($sql);
		// More than one arm, so display arm name
		if ($multiple_arms) 
		{	
			// Loop through events
			while ($row = db_fetch_assoc($q)) 
			{
				$event_ids[$row['event_id']] = $row['descrip'] . " - {$lang['global_08']} " . $row['arm_num'] . "{$lang['colon']} " . $row['arm_name'];
			}
		}
		// Only one arm, so only display event name
		else
		{
			// Loop through events
			while ($row = db_fetch_assoc($q)) 
			{
				$event_ids[$row['event_id']] = $row['descrip'];
			}
		}
	}

	//Display table
	print "<div style='max-width:700px;'>
	<table class='form_border' width=100%><tr>
		<td class='header' style='text-align:center;padding:2px 4px 2px 4px;width:150px;'>{$lang['reporting_19']}</td>
		<td class='header' style='text-align:center;padding:2px 4px 2px 4px;width:90px;'>{$lang['global_11']}</td>
		<td class='header' style='text-align:center;padding:2px 4px 2px 4px;width:120px;'>{$lang['reporting_21']}</td>	
		<td class='header' style='text-align:center;padding:2px 4px 2px 4px;'>{$lang['reporting_22']}</td>";
		// If project-level flag is set, then add "reason changed" to row data
		if ($require_change_reason)
		{
			print  "<td class='header' style='text-align:center;padding:2px 4px 2px 4px;width:120px;'>{$lang['reporting_38']}</td>";
		}
		print  "</tr>";

	while ($row = db_fetch_assoc($QSQL_STRING)) 
	{
		// Get values for this row
		$newrow = renderLogRow($row);		
		// Render row values
		print  "<tr>
					<td class='logt' style='width:150px;'>
						{$newrow[0]}
					</td>
					<td class='logt' style='width:90px;'>
						{$newrow[1]}
					</td>
					<td class='logt' style='width:120px;'>
						{$newrow[2]}
					</td>
					<td class='logt' style='text-align:left;'>
						".nl2br(htmlspecialchars(label_decode($newrow[3]), ENT_QUOTES))."
					</td>";
		// If project-level flag is set, then add "reason changed" to row data
		if ($require_change_reason)
		{
			print  "<td class='logt' style='text-align:left;width:120px;'>
						{$newrow[4]}
					</td>";
		}
		print  "</tr>";
	}	
	print "</table></div>";

}


include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
