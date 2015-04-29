<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

include 'header.php';

if (isset($_POST['start_date']) && $_POST['start_date']) {
	$start_date = (int)str_replace('-', '', DateTimeRC::format_ts_to_ymd($_POST['start_date']));
} else {
	$start_date = (int)str_replace('-', '', TODAY);
	$_POST['start_date'] = DateTimeRC::format_ts_from_ymd(TODAY);
}

if (isset($_POST['end_date']) && $_POST['end_date']) {
	$end_date = (int)str_replace('-', '', DateTimeRC::format_ts_to_ymd($_POST['end_date']));
} else {
	$end_date = (int)str_replace('-', '', TODAY);
	$_POST['end_date'] = DateTimeRC::format_ts_from_ymd(TODAY);
}

?>
<script type="text/javascript">
$(function(){
	projTitlePopup();
	// Append the project title pop-up action onto the onclick event for the table headers
	$('div#<?php echo $table_id ?> .hDivBox table tr th').each(function(){
		var onclick = $(this).attr('onclick') + "projTitlePopup();";
		$(this).attr('onclick',onclick);		
	});
	// Setu up datepickers on start/end date fields
	var dates = $( "#start-date, #end-date" ).datepicker({
		defaultDate: "+0w",
		changeMonth: true,
		numberOfMonths: 1,
		dateFormat: user_date_format_jquery,
		onSelect: function( selectedDate ) {
			var option = this.id == "start-date" ? "minDate" : "maxDate",
				instance = $( this ).data( "datepicker" ),
				date = $.datepicker.parseDate(
					instance.settings.dateFormat ||
					$.datepicker._defaults.dateFormat,
					selectedDate, instance.settings );
			dates.not( this ).datepicker( "option", option, date );
		}
	});
});
// Enable the project title pop-ups on mouseover
function projTitlePopup() {
	$(".gearsm").mouseover(function(){
		$(this).css('cursor','pointer');
		$.get(app_path_webroot+'ControlCenter/get_project_name.php?pid='+$(this).attr('pid'),{ }, function(data){
			$('#titleload').html(data);
		});
	});
	$(".gearsm").click(function(){
		var url = app_path_webroot+'index.php?pid='+$(this).attr('pid');
		window.open(url,'_blank','toolbar=1,location=1,directories=1,status=1,menubar=1,scrollbars=1,resizable=1');
	});
	$(".gearsm").tooltip({
		tip: '#tooltip',
		position: 'bottom right',
		offset: [7, 0],
		delay: 0,
		onHide: function() {
			$("#titleload").html('<img src="'+app_path_images+'progress_circle.gif" class="imgfix"> Loading...');
		}
	});
}
</script>
<?php

// Page title
echo '<h3 style="margin-top: 0;">'.RCView::img(array('src'=>'report_user.png', 'class'=>'imgfix2')) . $lang['control_center_206'].'</h3>';
// Start/end date selection
print '<div style="margin: 0px 25px 15px 0px;vertical-align:middle;">';
print '<form method="post" action="'.PAGE_FULL.'">';
print $lang['control_center_207'];
print ' <input type="text" id="start-date" name="start_date" value="'.$_POST['start_date'].'" class="x-form-text x-form-field" style="width:70px;" /> &nbsp; ';
print $lang['control_center_208'];
print ' <input type="text" id="end-date" name="end_date" value="'.$_POST['end_date'].'" class="x-form-text x-form-field" style="width:70px;" />';
print ' &nbsp; <input type="submit" name="Search" value="Display" /></form>';
print '</div>';
// Hidden pop-up div to display project name from mouseover
print 	"<div id='tooltip' class='tooltip' style='max-width:400px;padding:7px;z-index:9999;'>
			<b>{$lang['control_center_107']}&nbsp;</b>
			<span id='titleload'><img src='".APP_PATH_IMAGES."progress_circle.gif' class='imgfix'> Loading...</span>
		</div>";

// First, get list of all project_id's (in case some projects have been deleted, we don't need to show the gear icon)
$project_ids = array();
$sql = "select project_id from redcap_projects";
$q = db_query($sql);
while ($row = db_fetch_assoc($q))
{
	$project_ids[$row['project_id']] = true;
}


/**
 * All User Activity for the date range selected
 */
$dbQuery = "SELECT * FROM redcap_log_event WHERE ts >= ".$start_date."000000 
			AND ts <= ".$end_date."235959 order by ts DESC";
$q = db_query($dbQuery);
$num_activity_today = 0;
$activityToday = array();
while ($row = db_fetch_array($q)) 
{
	// Ignore auto-calc logging since they are just duplicates
	if (strpos($row['description'], "(Auto calculation)") !== false) continue;
	// Add to array
	$activityToday[] = array((!isset($project_ids[$row['project_id']]) ? "" : "<div pid='{$row['project_id']}' class='gearsm'>&nbsp;&nbsp;</div>"),
							 DateTimeRC::format_ts_from_ymd(DateTimeRC::format_ts_from_int_to_ymd($row['ts'])),
							 $row['user'],
							 $row['description']
							);
	// Increment row
	$num_activity_today++;
}
if ($num_activity_today == 0) {
	$activityToday[] = array('','',$lang['dashboard_02']);
}
$height = ($num_activity_today >= 26 ? 570 : "auto");
$col_widths_headers = array(
						array(10, ''),
						array(100, $lang['global_13']),
						array(130, $lang['global_17']),
						array(280, $lang['dashboard_21'])
					);

if ($start_date != $end_date) {
	$the_date = $_POST['start_date'] . " - ".$_POST['end_date'];
} else {
	$the_date = $_POST['start_date'];
}

if ($start_date == $end_date && $end_date == date('Ymd')) {
	$the_date = $lang['dashboard_32'];
}

// Render the table
$table_id = "todayActivityTable";
renderGrid($table_id, "{$lang['dashboard_03']} {$the_date}<span style='font-size:11px;margin-left:7px;'>(".User::number_format_user($num_activity_today, 0)." {$lang['dashboard_04']})", 550, $height, $col_widths_headers, $activityToday);
print "<br />";







/**
 * Daily aggregate table
 */
$sql = "SELECT description, count(1) as count FROM redcap_log_event 
		WHERE ts >= ".$start_date."000000 AND ts <= ".$end_date."235959 
		GROUP BY description ORDER BY count DESC";
$q = db_query($sql);
$aggrToday = array();
while ($row = db_fetch_array($q)) {
	// Ignore auto-calc logging since they are just duplicates
	if (strpos($row['description'], "(Auto calculation)") !== false) continue;
	// Add to array
	$aggrToday[] = array(User::number_format_user($row['count']), $row['description']);
}
if (!db_num_rows($q)) {
	$aggrToday[] = array('',$lang['dashboard_02']);	
}	
$height = (count($aggrToday) >= 18) ? 420 : "auto";
$col_widths_headers = array(
						array(60, $lang['dashboard_23'], "center", "int"),
						array(466, $lang['dashboard_21'])
					);
renderGrid("aggr_table", $lang['dashboard_91'] ." ". $the_date , 550, $height, $col_widths_headers, $aggrToday);



include 'footer.php';