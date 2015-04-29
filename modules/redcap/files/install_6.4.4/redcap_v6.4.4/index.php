<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/


require_once 'Config/init_project.php';
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
callJSfile('Calendar.js');

// Determine if project is being used as a template project
$templateInfo = ProjectTemplates::getTemplateList($project_id);
$isTemplate = (!empty($templateInfo));
if ($isTemplate) {
	// Edit/remove template
	$templateTxt =  RCView::img(array('src'=>($templateInfo[$project_id]['enabled'] ? 'star.png' : 'star_empty.png'),'class'=>'imgfix')) . 
					RCView::span(array('style'=>'margin-right:10px;'), $lang['create_project_91']) .
					RCView::a(array('href'=>'javascript:;','style'=>'text-decoration:none;','onclick'=>"projectTemplateAction('prompt_addedit',$project_id)"), 
						RCView::img(array('src'=>'pencil.png','class'=>'imgfix','title'=>$lang['create_project_90']))
					) .
					RCView::SP .
					RCView::a(array('href'=>'javascript:;','style'=>'text-decoration:none;','onclick'=>"projectTemplateAction('prompt_delete',$project_id)"), 
						RCView::img(array('src'=>'cross.png','class'=>'imgfix','title'=>$lang['create_project_93']))
					);
	$templateClass = 'yellow';
} else {
	// Add as template
	$templateTxt =  RCView::img(array('src'=>'star_empty.png','class'=>'imgfix')) . 
					RCView::span(array('style'=>'margin-right:10px;'), $lang['create_project_92']) .
					RCView::button(array('class'=>'jqbuttonsm','onclick'=>"projectTemplateAction('prompt_addedit',$project_id)"), 
						$lang['design_171'] . RCView::SP
					);
	$templateClass = 'chklist';
}
$templateTxt = RCView::div(array('class'=>$templateClass,'style'=>'margin:0;padding:2px 10px 4px 8px;float:right;'), $templateTxt);
?>


<!-- QUICK TASKS PANEL -->
<?php if (!empty($user_rights)) { ?>
<div class="round chklist" style="padding:10px 20px;margin:20px 0;">

	<table cellspacing="4" width="100%">
	
		<!-- Header -->
		<tr>
			<td valign="middle" class="chklisthdr" style="color:#800000;width:140px;">
				<?php echo $lang['index_58'] ?>
			</td>
			<td valign="middle" style="text-align:right;padding-right:10px;">
				<?php if ($super_user) echo $templateTxt; ?>
			</td>
		</tr>
		
		<!-- Codebook -->
		<tr>
			<td valign="middle" style="width:145px;">
				<button class="jqbuttonmed" onclick="window.location.href=app_path_webroot+'Design/data_dictionary_codebook.php?pid='+pid;"
					><img src='<?php echo APP_PATH_IMAGES ?>codebook.png' style='vertical-align:middle;'> <span style='vertical-align:middle;'><?php echo $lang['design_482'] ?></span></button>
			</td>
			<td valign="middle">
				<?php echo $lang['design_505'] ?>
			</td>
		</tr>
		
		<?php if ($surveys_enabled && $user_rights['participants']) { ?>
		<!-- Invite participants -->
		<tr>
			<td valign="middle" style="width:145px;">
				<button class="jqbuttonmed" style="text-align:left;font-size:11px;width:120px;" onclick="window.location.href=app_path_webroot+'Surveys/invite_participants.php?pid='+pid;"
					><img src='<?php echo APP_PATH_IMAGES ?>survey_participants.gif' style='vertical-align:middle;'> <span style='vertical-align:middle;'><?php echo $lang['app_22'] ?></span></button>
			</td>
			<td valign="middle">
				<?php echo $lang['index_59'] ?>
			</td>
		</tr>
		<?php } ?>
		
		<?php if ($user_rights['data_export_tool'] > 0) { ?>
		<!-- Export data -->
		<tr>
			<td valign="middle" style="width:145px;">
				<button class="jqbuttonmed" onclick="window.location.href=app_path_webroot+'DataExport/index.php?pid='+pid;"
					><img src='<?php echo APP_PATH_IMAGES ?>application_go.png' style='vertical-align:middle;'> <span style='vertical-align:middle;'><?php echo $lang['index_60'] ?></span></button>
			</td>
			<td valign="middle">
				<?php echo $lang['index_61'] ?>
			</td>
		</tr>
		<?php } ?>
	
		<?php if ($status < 2 && $user_rights['reports']) { ?>
		<!-- Create a report -->
		<tr>
			<td valign="middle" style="width:145px;">
				<button class="jqbuttonmed" onclick="window.location.href=app_path_webroot+'Reports/report_builder.php?pid='+pid;"
					><img src='<?php echo APP_PATH_IMAGES ?>layout.png' style='vertical-align:middle;'> <span style='vertical-align:middle;'><?php echo $lang['index_62'] ?></span></button>
			</td>
			<td valign="middle">
				<?php echo $lang['index_63'] ?>
			</td>
		</tr>
		<?php } ?>
	
		<?php if ($status < 2 && ($user_rights['data_quality_design'] || $user_rights['data_quality_execute'])) { ?>
		<!-- Data Quality -->
		<tr>
			<td valign="middle" style="width:145px;">
				<button class="jqbuttonmed" onclick="window.location.href=app_path_webroot+'DataQuality/index.php?pid='+pid;"
					><img src='<?php echo APP_PATH_IMAGES ?>checklist.png' style='position:relative;top:-1px;vertical-align:middle;'> <span style='vertical-align:middle;'><?php echo $lang['dataqueries_43'] ?></span></button>
			</td>
			<td valign="middle">
				<?php echo $lang['dataqueries_42'] ?>
			</td>
		</tr>
		<?php } ?>
	
		<?php if ($user_rights['user_rights']) { ?>
		<!-- User rights -->
		<tr>
			<td valign="middle" style="width:145px;">
				<button class="jqbuttonmed" onclick="window.location.href=app_path_webroot+'UserRights/index.php?pid='+pid;"
					><img src='<?php echo APP_PATH_IMAGES ?>user.png' style='vertical-align:middle;'> <span style='vertical-align:middle;'><?php echo $lang['app_05'] ?></span></button>
			</td>
			<td valign="middle">
				<?php echo $lang['index_64'] ?>
			</td>
		</tr>
		<?php } ?>
	
		<?php if ($user_rights['design']) { ?>
		<!-- Modify instruments -->
		<tr>
			<td valign="middle" style="width:150px;">
				<button class="jqbuttonmed" style="width:140px;font-size:11px;" onclick="window.location.href=app_path_webroot+'Design/online_designer.php?pid='+pid;"
					><img src='<?php echo APP_PATH_IMAGES ?>blog_pencil.png' style='vertical-align:middle;'> <span style='vertical-align:middle;'><?php echo $lang['bottom_31'] ?></span></button>
			</td>
			<td valign="middle">
				<?php echo $lang['index_65'] ?>
				<a href="javascript:;" onclick="downloadDD(0,<?php echo $Proj->formsFromLibrary() ?>);"
					style="font-size:12px;text-decoration:underline;"><?php echo "{$lang['design_119']} {$lang['global_09']}" ?></a>
				<?php if ($status > 0 && $draft_mode > 0) { ?>
					<?php echo $lang["global_46"] ?>
					<a href="javascript:;" onclick="downloadDD(1,<?php echo $Proj->formsFromLibrary() ?>);"
						style="font-size:12px;text-decoration:underline;"><?php echo "{$lang['design_121']} {$lang['global_09']} {$lang['design_122']}" ?></a>
				<?php } ?>
			</td>
		</tr>
		<?php } ?>
	
		<?php if (($user_rights['design'] && $allow_create_db) || $super_user) { ?>
		<!-- Copy project -->
		<tr>
			<td valign="middle" style="width:145px;">
				<button class="jqbuttonmed" onclick="window.location.href=app_path_webroot+'ProjectGeneral/copy_project_form.php?pid='+pid;"
					><img src='<?php echo APP_PATH_IMAGES ?>page_copy.png' style='vertical-align:middle;'> <span style='vertical-align:middle;'><?php echo $lang['index_66'] ?></span></button>
			</td>
			<td valign="middle">
				<?php echo $lang['index_67'] ?>
			</td>
		</tr>
		<?php } ?>
	
		<?php if ($user_rights['data_access_groups']) { ?>
		<!-- DAGs -->
		<tr>
			<td valign="middle" style="width:145px;">
				<button class="jqbuttonmed" style="font-size:11px;width:140px;" onclick="window.location.href=app_path_webroot+'DataAccessGroups/index.php?pid='+pid;"
					><img src='<?php echo APP_PATH_IMAGES ?>group.png' style='vertical-align:middle;'> <span style='vertical-align:middle;'><?php echo $lang['global_22'] ?></span></button>
			</td>
			<td valign="middle">
				<?php echo $lang['index_68'] ?>
			</td>
		</tr>
		<?php } ?>
		
	</table>

</div>
<?php } ?>

<!-- PROJECT DASHBOARD -->
<div class="round chklist" style="padding:10px 20px;margin:20px 0;">

	<div class="chklisthdr" style="color:#800000;"><?php echo $lang['index_69'] ?></div>
	
	<p>
		<?php echo $lang['index_70'] ?>
	</p>
	
<?php

print "<br>";

print "<table cellpadding=0 cellspacing=0 border=0><tr><td valign=top align=left>";


/**
 * USER TABLE
 */
// Loop through user rights
$user_list = $proj_users = array();
$user_rights_all = UserRights::getPrivileges($project_id);
$user_rights_all = $user_rights_all[$project_id];
foreach ($user_rights_all as $this_user=>$attr) {
	$proj_users[] = $this_user = strtolower($this_user);
	$user_list[$this_user]['expiration']  = $attr['expiration'];
	$user_list[$this_user]['double_data'] = $attr['double_data'];
}


// Get users' email, name, and suspension status
$user_info = array();
$q = db_query("select username, user_email, user_firstname, user_lastname, if(user_suspended_time is null, '0', '1') as suspended
			   from redcap_user_information where username in (".prep_implode($proj_users).")");
while ($row = db_fetch_array($q)) {
	$row['username'] = strtolower($row['username']);
	$user_info[$row['username']]['user_email'] 		= $row['user_email'];
	$user_info[$row['username']]['user_firstlast'] 	= $row['user_firstname'] . " " . $row['user_lastname'];
	$user_info[$row['username']]['suspended'] 		= $row['suspended'];
}
//Loop through user list to render each row of users table
$i = 0;
foreach ($user_list as $this_user=>$row) {
	//Render expiration date, if exists (expired users will display in red)
	if ($row['expiration'] == '') {
		$row['expiration'] = "<span style=\"color:gray\">{$lang['index_37']}</span>";
	} else {
		if (str_replace("-","",$row['expiration']) < date('Ymd')) {
			$row['expiration'] = "<span style=\"color:red\">".DateTimeRC::format_user_datetime($row['expiration'], 'Y-M-D_24')."</span>";
		} else {
			$row['expiration'] = DateTimeRC::format_user_datetime($row['expiration'], 'Y-M-D_24');
		}
	}
	// Add text if user is suspended
	$suspendedText = (!$user_info[$this_user]['suspended']) ? '' :
						RCView::div(array('style'=>'color:red;'), 
							$lang['rights_281']
						);					  
	//If user's name and email are recorded, display their name and email
	if (isset($user_info[$this_user])) {
		$name_email = "<br>(<a style=\"font-size:11px\" 
			href=\"mailto:".$user_info[$this_user]['user_email']."\">".cleanHtml($user_info[$this_user]['user_firstlast'])."</a>)";
	} else {
		$name_email = "";
	}
	$row_data[$i][0] = $this_user . $name_email . $suspendedText;
	$row_data[$i][1] = $row['expiration'];
	if ($double_data_entry) { 
		if ($row['double_data'] == 0) $double_data_label = $lang['rights_51']; else $double_data_label = "#" . $row['double_data'];
		$row_data[$i][2] = $double_data_label;
	}
	$i++;
}

$title = "<div style=\"padding:0;\"><img src=\"".APP_PATH_IMAGES."user.png\" class=\"imgfix\"> 
	<span style=\"color:#000066;\">{$lang['index_19']}</span></div>";
$width = 200;
$col_widths_headers = array( 
						array(102, $lang['global_17'], "left"), 
						array(73,  $lang['index_35'], "center") 
					  );
if ($double_data_entry) 
{
	$dde_col_width = 90;
	$col_widths_headers[] = array($dde_col_width, $lang['index_36'], "left");
	$width += $dde_col_width+12;
}
renderGrid("user_list", $title, $width, 'auto', $col_widths_headers, $row_data);

	
print "</td><td valign='top' align='left' style='padding-left:30px;'>";


/**
 * PROJECT STATISTICS TABLE
 */
 // Project status
if ($status == 0) {
	$status_label = "<img src='".APP_PATH_IMAGES."page_white_edit.png' class='imgfix'> <span style='color:#888;'>{$lang['global_29']}</span>";
} elseif ($status == 1) {
	$status_label = "<img src='".APP_PATH_IMAGES."accept.png' class='imgfix'> <span style='color:green;'>{$lang['global_30']}</span>"; 
} elseif ($status == 2) {
	$status_label = "<img src='".APP_PATH_IMAGES."delete.png' class='imgfix'> <span style='color:#800000;'>{$lang['global_31']}</span>";
} elseif ($status == 3) {
	$status_label = "<img src='".APP_PATH_IMAGES."bin_closed.png' class='imgfix'> <span style='color:#800000;'>{$lang['global_26']}</span>";
}

$title = '<div style="padding:0;"><img src="'.APP_PATH_IMAGES.'clipboard_text.png" class="imgfix"> ' . $lang['index_27'] . '</div>';

$file_space_usage_text = "<span style='cursor:pointer;cursor:hand;' onclick=\"\$('#fileuse_explain').toggle('blind','fast');\">{$lang['index_56']}</span>
						  <div id='fileuse_explain'>
								{$lang['index_51']}
						  </div>";	

// Set column widths
$col1_width = 137;
$col2_width = 138;	

$col_widths_headers = array( 
						array($col1_width, '', "left"), 
						array($col2_width,  '', "center") 
					  );
$row_data = array(
				array($lang['index_22'], "<span id='projstats1'><span style='color:#888;'>{$lang['data_entry_64']}</span></span>"),
				//array($lang['index_23'], $num_data_exports),
				//array($lang['index_24'], $num_logged_events),
				array($lang['index_25'], "<span id='projstats2'>".($last_logged_event != "" ? DateTimeRC::format_user_datetime($last_logged_event, 'Y-M-D_24') : "<span style='color:#888;'>{$lang['data_entry_64']}</span>")."</span>"),
				array($file_space_usage_text, "<span id='projstats3'><span style='color:#888;'>{$lang['data_entry_64']}</span></span>")
			);
if ($double_data_entry) 
{
	$row_data[] = array($lang['global_04'], $lang['index_30']);
}
$row_data[] = array($lang['index_26'], $status_label);

// Render the table
renderGrid("stats_table", $title, 300, 'auto', $col_widths_headers, $row_data, false);	



/**
 * Survey Response Summary
 */
// if ($surveys_enabled)
// {
	// require_once APP_PATH_DOCROOT . "Surveys/survey_functions.php";
	// $_GET['survey_id'] = getSurveyId();
	// if (checkSurveyProject($_GET['survey_id']))
	// {
		// require APP_PATH_DOCROOT . "Surveys/response_summary_table.php";
	// }
// }


?>
<script type="text/javascript">
// AJAX call to fetch the stats table values
$(function(){
	$.get(app_path_webroot+'ProjectGeneral/project_stats_ajax.php', { pid: pid }, function(data){
		if (data!='0') {
			var json = jQuery.parseJSON(data);
			$('#projstats1').html(json[0]);
			$('#projstats2').html(json[1]);
			$('#projstats3').html(json[2]);
		}
	});
});
</script>
<?php
 
print "<br>";


/**
 * UPCOMING EVENTS TABLE
 * List any events scheduled on the calendar in the next 7 days (if any)
 */
// Do not show the calendar events if don't have access to calendar page
if (!$user_rights['calendar']) return;

$sql = "select * from redcap_events_metadata m right outer join redcap_events_calendar c on c.event_id = m.event_id 
		where c.project_id = " . PROJECT_ID . " and c.event_date >= '" . date("Y-m-d") . "' and 
		c.event_date <= '" . date("Y-m-d", mktime(0, 0, 0, date("m"), date("d")+7, date("Y"))) . "' 
		" . (($user_rights['group_id'] == "") ? "" : "and c.group_id = " . $user_rights['group_id']) . " 
		order by c.event_date, c.event_time";
$q = db_query($sql);

$cal_list = array();

if (db_num_rows($q) > 0) {
	
	while ($row = db_fetch_assoc($q)) 
	{
		$caldesc = "";
		// Set image to load calendar pop-up
		$popup = "<a href=\"javascript:;\" onclick=\"popupCal({$row['cal_id']},800);\">"
				 . "<img src=\"".APP_PATH_IMAGES."magnifier.png\" style=\"vertical-align:middle;\" title=\"".cleanHtml2($lang['scheduling_80'])."\" alt=\"".cleanHtml2($lang['scheduling_80'])."\"></a> ";
		// Trim notes text
		$row['notes'] = trim($row['notes']);		
		// If this calendar event is tied to a project record, display record and Event
		if ($row['record'] != "") {
			$caldesc .= $row['record'];
		}
		if ($row['event_id'] != "") {
			$caldesc .= " (" . $row['descrip'] . ") ";
		}
		if ($row['group_id'] != "") {
			$caldesc .= " [" . $Proj->getGroups($row['group_id']) . "] ";
		}
		if ($row['notes'] != "") {
			if ($row['record'] != "" || $row['event_id'] != "") {
				$caldesc .= " - ";
			}
			$caldesc .= $row['notes'];
		}
		// Add to table
		$cal_list[] = array(cleanHtml($popup), DateTimeRC::format_ts_from_ymd($row['event_time']), DateTimeRC::format_ts_from_ymd($row['event_date']), cleanHtml("<span class=\"notranslate\">$caldesc</span>"));
	}
	
} else {

	$cal_list[] = array('', '', '', $lang['index_52']);

}

$height = (count($cal_list) < 9) ? "auto" : 220;
$title = "<div style=\"padding:0;\"><img src=\"".APP_PATH_IMAGES."date.png\" class=\"imgfix\"> 
	<span style=\"color:#800000;\">{$lang['index_53']} &nbsp;<span style=\"font-weight:normal;\">{$lang['index_54']}</span></span></div>";
$col_widths_headers = array( 
						array(16, '', 'center'), 
						array(40,  $lang['global_13']), 
						array(60,  $lang['global_18']), 
						array(313, $lang['global_20']) 
					  );
renderGrid("cal_table", $title, 450, $height, $col_widths_headers, $cal_list);

print "</td></tr></table>";

print "<br><br>";

print "</div>";

// If project is INACTIVE OR ARCHIVED, do not show full menus in order to give limited functionality
if ($status > 1) 
{
	print RCView::simpleDialog($lang['bottom_50'],$lang['global_03'],"status_note");
	?>			
	<script type="text/javascript">
	$(function(){
		simpleDialog(null,null,'status_note');
	});
	</script>
	<?php
}

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
