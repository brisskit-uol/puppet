<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
		
// Get required file
require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';


//Redirect to index page if not supposed to be here
if (!$scheduling) redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");


include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

renderPageTitle("<table cellpadding=0 cellspacing=0 width='100%'><tr>
				 <td valign='top'>
					<img src='".APP_PATH_IMAGES."calendar_plus.png' class='imgfix2'> {$lang['global_25']}
				 </td>
				 <td valign='top' style='text-align:right;'>
					<img src='" . APP_PATH_IMAGES . "video_small.png' class='imgfix'> 
					<a onclick=\"window.open('https://redcap.vanderbilt.edu/consortium/videoplayer.php?video=scheduling02.flv&referer=".SERVER_NAME."&title=Scheduling','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');\" href=\"javascript:;\" style=\"font-size:12px;text-decoration:underline;font-weight:normal;\">{$lang['scheduling_85']} (7 {$lang['calendar_12']})</a>
				</td>
				 </tr></table>");

/**
 * TABS
 */
print  '<br><div id="sub-nav" style="margin-bottom:0;max-width:750px;"><ul>';
print  '<li';
if (!isset($_GET['record'])) print ' class="active"';
print  '><a style="font-size:13px;color:#393733;padding:8px 8px 3px 14px;" href="'.$_SERVER['PHP_SELF'].'?pid='.$project_id.'">'.$lang['scheduling_03'].'</a></li>';
print  '<li';
if (isset($_GET['record'])) print ' class="active"';
print  '><a style="font-size:13px;color:#393733;padding:8px 8px 3px 14px;" href="'.$_SERVER['PHP_SELF'].'?pid='.$project_id.'&record=">'.$lang['scheduling_04'].'</a></li>';
print  '</ul></div><br><br><br>';


// Obtain custom record label & secondary unique field labels for ALL records.
$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords();

	
/**
 * ADD UNSCHEDULED
 */
if (!isset($_GET['record'])) {

	## Instructions
	print  "<p>
				{$lang['scheduling_05']} 
				<a href='".APP_PATH_WEBROOT."Design/define_events.php?pid=$project_id' 
					style='text-decoration:underline;'>{$lang['global_16']}</a> {$lang['global_14']}.";
	
	//This page can only be used if multiple events have been defined (doesn't make sense otherwise)
	if (!$longitudinal) {
		print  "<br>
				<div class='red'>
					<b>{$lang['global_02']}:</b><br>
					{$lang['scheduling_09']} 
					<a href='".APP_PATH_WEBROOT."Design/define_events.php?pid=$project_id' 
						style='font-family:Verdana;text-decoration:underline;'>{$lang['scheduling_10']}</a>
					{$lang['scheduling_11']}
				</div>
			</p>";
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}
	print  "	{$lang['scheduling_12']} $table_pk_label {$lang['scheduling_13']}
				<a href='".APP_PATH_WEBROOT."Calendar/index.php?pid=$project_id' style='text-decoration:underline;'>{$lang['app_08']}</a>{$lang['scheduling_14']}
			</p>";

	//Query server to check if new record field should have validation
	$q = db_query("select element_validation_type, element_validation_min, element_validation_max from redcap_metadata 
					  where field_name = '$table_pk' and project_id = $project_id");
	$val = db_fetch_array($q);
	$text_val_string = ($val['element_validation_type'] == '') ? "" : "onblur=\"redcap_validate(this,'{$val['element_validation_min']}','{$val['element_validation_max']}','hard','{$val['element_validation_type']}')\"";
	
	// If using record auto-numbering, provide next number
	$new_record_name = ($auto_inc_set) ? getAutoId() : '';
	$new_record_name_disable = ($auto_inc_set) ? 'disabled' : '';
	
	//Participant ID drop-down
	print  "<p style='max-width:700px;'>
			<table cellspacing=12 cellpadding=0 style='margin-top:10px;border:1px solid #ddd;background-color:#f8f8f8;'>
			<tr valign='top'>
				<td style='padding:2px 20px 0 0;'>
					" . ($user_rights['record_create'] ? "<b>{$lang['scheduling_15']} $table_pk_label:</b>" : "") . "
				</td>
				<td style='padding-right:100px;'>
					" . ($user_rights['record_create'] ? "<span>" : "<span style='display:none;'>" ) . "
						<span onclick=\"if ($('#idnumber2').prop('disabled')) { $('#idnumber2').val( $('#idnumber2').attr('hiddenval') ); $('#idnumber').val('') }\">
						<input type='text' maxlength='50' class='x-form-text x-form-field' size='15' name='idnumber2' id='idnumber2' $text_val_string 
							onkeyup=\"if (this.value.length > 0) $('#idnumber').val('')\" value='".cleanHtml($new_record_name)."' hiddenval='".cleanHtml($new_record_name)."' $new_record_name_disable>
						</span>
						<span style='font-weight:bold;padding:0 4px 0 4px;'>&nbsp; {$lang['global_46']} &nbsp;</span>
					</span>
					<select name='idnumber' id='idnumber' class='x-form-text x-form-field' style='height:22px;padding-right:0;' 
						onchange=\"$('#idnumber2').val('');\">
					<option value=''> - {$lang['scheduling_16']} - </option>";
	// Retrieve record list of existing unscheduled participants (exclude non-DAG records if user is in a DAG)
	// Find records already scheduled so we can exclude them
	$exclude_records = array();
	$sql = "select distinct record from redcap_events_calendar where project_id = $project_id and event_id is not null and record is not null";	 
	$q2 = db_query($sql); 
	while ($row = db_fetch_assoc($q2)) {
		$exclude_records[$row['record']] = true;
	}
	// If using DDE, then set filter logic
	$ddeFilter = ($double_data_entry && $user_rights['double_data'] != 0) ? "ends_with([$table_pk], '--{$user_rights['double_data']}')" : false;
	// Loop through all record names and output those not scheduled yet
	foreach (array_keys(Records::getData('array', array(), $table_pk, array(), $user_rights['group_id'], false, false, false, $ddeFilter)) as $this_record) {
		if ($exclude_records[$this_record]) continue;
		print "<option value='".removeDDEending($this_record)."'>" . removeDDEending($this_record);
		if (isset($extra_record_labels[$this_record])) print " ".$extra_record_labels[$this_record];
		print "</option>";
	}
	print  "		</select>";
	print  "	</td>
			</tr>";
					
	//Enter Start Date
	print  "<tr valign='top'>
				<td style='padding-top:2px;'>
					<b>{$lang['scheduling_18']}</b> 
				</td>
				<td>
					<input type='text' id='startdate' class='x-form-text x-form-field cal3' onblur=\"redcap_validate(this,'','','hard','date_'+user_date_format_validation,1,1,user_date_format_delimiter);\" value='" . DateTimeRC::format_ts_from_ymd(TODAY) . "' name='startdate' style='width:70px;' maxlength='10'>
					<span class='df'>".DateTimeRC::get_user_format_label()."</span>
				</td>
			</tr>";
	//Select Arm
	$q = db_query("select arm_num, arm_name from redcap_events_arms where project_id = $project_id order by arm_num");
	if (db_num_rows($q) > 1) {
		print  "<tr valign='top'>
					<td style='padding-top:2px;'>
						<b>{$lang['scheduling_19']}</b>
					</td>
					<td>
						<select name='arm' id='arm' class='notranslate'>
						<option value=''> -- {$lang['scheduling_83']} -- </option>";
		while ($row = db_fetch_assoc($q)) {
			print "		<option value='{$row['arm_num']}'>{$lang['global_08']} {$row['arm_num']}: {$row['arm_name']}</option>";
		}
		print  "		</select>
					</td>
				</tr>";
	} else {
		//If only one Arm, make arm_num a hidden field
		$arm = getArm();
		print "<input type='hidden' name='arm' id='arm' value='$arm'>";
	}
	//Generate button
	print  "<tr valign='top'>
				<td></td>
				<td>
					<input type='button' value='Generate Schedule' id='genbtn' onclick=\"generateSched();\">
					&nbsp;&nbsp;
					<span id='progress' style='visibility:hidden;color:#555;'>
						<img src='".APP_PATH_IMAGES."progress_circle.gif' class='imgfix'> 
						{$lang['scheduling_20']}
					</span>
				</td>
			</tr>
		</table>
		</p>";
		
	//Div where table where be rendered
	print  "<br><div id='table'></div>";
	
}


/**
 * EDIT SCHEDULED
 */
if (isset($_GET['record'])) 
{
	// Append --# to record name if DDE usr
	$_GET['record'] = addDDEending($_GET['record']);

	## Instructions
	print  "<p>{$lang['scheduling_21']} $table_pk_label{$lang['scheduling_22']}</p>";

	// Participant ID drop-down
	print  "<p style='max-width:700px;'>
			<table cellspacing=12 cellpadding=0 style='margin-top:10px;border:1px solid #ddd;background-color:#f8f8f8;'>
			<tr valign='top'>
				<td valign='middle' style='padding:2px 20px 0 0;'>
					<b>{$lang['scheduling_23']} $table_pk_label:</b>
				</td>
				<td valign='middle' style='padding:2px 20px 0 0;'>
					<select id='record' class='x-form-text x-form-field' style='height:22px;padding-right:0;' onchange=\"
						if (this.value != '') {
							var this_arr = this.value.split('[_ARMREC_]');
							window.location.href = '{$_SERVER['PHP_SELF']}?pid='+pid+'&record='+this_arr[1]+'&arm='+this_arr[0];
						}
					\">
					<option value=''>{$lang['data_entry_91']}</option>";
	
	// Determine number of arms
	$num_arms = db_result(db_query("select count(1) from redcap_events_arms where project_id = $project_id"), 0);
	// Exclude non-DAG records if user is in a DAG
	$group_sql  = ""; 
	if ($user_rights['group_id'] != "") {
		$group_sql  = "and record in (" . pre_query("select record from redcap_data where project_id = $project_id and field_name = '__GROUPID__'
						and value = '" . $user_rights['group_id'] . "'") . ")"; 
	}
	$sql = "select distinct c.record, c.event_id, a.arm_num from redcap_events_calendar c, redcap_events_metadata m, redcap_events_arms a 
			where c.project_id = a.project_id and a.project_id = $project_id and m.arm_id = a.arm_id and c.event_id = m.event_id 
			$group_sql group by c.record order by abs(c.record), c.record, a.arm_num";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		$row['record'] = removeDDEending($row['record']);
		print "		<option value='{$row['arm_num']}[_ARMREC_]{$row['record']}' ";
		if ($_GET['record'] != "" && removeDDEending($_GET['record']) == $row['record']) print "selected";
		print ">{$row['record']}";
		if ($multiple_arms) print " (".$lang['global_08']." ".$Proj->eventInfo[$row['event_id']]['arm_num'].$lang['colon']." ".$Proj->eventInfo[$row['event_id']]['arm_name'].")"; 
		if (isset($extra_record_labels[$row['record']])) print " ".$extra_record_labels[$row['record']];
		print "</option>";
	}
	print  "		</select>
				</td>
			</tr>
			</table>
			</p>";
	
	//Use div if needed for dialog for asking to adjust ALL dates if changing one
	print  "<div id='adjustDatesDialog' style='font-family:arial;line-height:1.4em;display:none;padding:15px;'>
			{$lang['scheduling_24']} (<span style='color:#800000;'><span id='daydiff'>??</span> {$lang['scheduling_25']}</span>)?</b>
			({$lang['global_02']}{$lang['colon']} {$lang['scheduling_26']})
			</div>";

			
	// If record has been selected, load the table for viewing/editing calendar events for this record
	if (isset($_GET['record']) && $_GET['record'] != "") {
		print  "<br><div id='table'>";
		$_GET['arm'] = getArm();
		$_GET['action'] = 'edit_sched';
		include APP_PATH_DOCROOT . 'Calendar/scheduling_ajax.php';
		print  "</div>";
	}
}

//Use div if needed for dialog for alerting if a date that user changes is out of range
?>
<div id='alert_text' title='<?php echo cleanHtml($lang['scheduling_86']) ?>' style='font-family:arial;line-height:1.4em;display:none;padding:15px;'></div>
	
<br><br><br><br><br><br><br>

<script type="text/javascript">
$(function(){
	// Pop-up Calendar initialization
	initCal2Datepicker();
	if ($('.cal3').length) $('.cal3').datepicker({buttonText: 'Click to select a date', yearRange: '-100:+10', showOn: 'both',   buttonImage: app_path_images+'date.png', buttonImageOnly: true, changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery});
	// Pop-up time-select initialization
	$('.time').timepicker({hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a time', showOn: 'both', buttonImage: app_path_images+'timer.png', buttonImageOnly: true, timeFormat: 'hh:mm'});
});
</script>

<?php
callJSfile('Calendar.js');

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
