<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

include 'header.php';

// Twilio setting is dependent upon another Twilio setting
?>
<script type="text/javascript">
function setTwilioDisplayInfo() {
	if ($('select[name="twilio_enabled_by_super_users_only"]').val() == '0') {
		$('select[name="twilio_display_info_project_setup"]').val('0').prop('disabled', true);
		$('#twilio_display_info_project_setup-tr').fadeTo(0, 0.6);
	} else {
		$('select[name="twilio_display_info_project_setup"]').prop('disabled', false);
		$('#twilio_display_info_project_setup-tr').fadeTo(0, 1);
	}
}
$(function(){
	setTwilioDisplayInfo();
});
</script>
<?php

$changesSaved = false;

// If project default values were changed, update redcap_config table with new values
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	// Twilio setting is dependent upon another Twilio setting
	if ($_POST['twilio_enabled_by_super_users_only'] == '0') $_POST['twilio_display_info_project_setup'] = 0;
	
	$changes_log = array();
	$sql_all = array();
	foreach ($_POST as $this_field=>$this_value) {
		// Save this individual field value
		$sql = "UPDATE redcap_config SET value = '".prep($this_value)."' WHERE field_name = '$this_field'";
		$q = db_query($sql);
		
		// Log changes (if change was made)
		if ($q && db_affected_rows() > 0) {
			$sql_all[] = $sql;
			$changes_log[] = "$this_field = '$this_value'";
		}
	}

	// Log any changes in log_event table
	if (count($changes_log) > 0) {
		log_event(implode(";\n",$sql_all),"redcap_config","MANAGE","",implode(",\n",$changes_log),"Modify system configuration");
	}

	$changesSaved = true;
}

// Retrieve data to pre-fill in form
$element_data = array();

$q = db_query("select * from redcap_config");
while ($row = db_fetch_array($q)) {
		$element_data[$row['field_name']] = $row['value'];
}
?>

<?php
if ($changesSaved)
{
	// Show user message that values were changed
	print  "<div class='yellow' style='margin-bottom: 20px; text-align:center'>
			<img src='".APP_PATH_IMAGES."exclamation_orange.png' class='imgfix'>
			{$lang['control_center_19']}
			</div>";
}
?>

<h3 style="margin-top: 0;"><?php echo RCView::img(array('src'=>'brick.png', 'class'=>'imgfix2')) . $lang['control_center_114'] ?></h3>

<form action='modules_settings.php' enctype='multipart/form-data' target='_self' method='post' name='form' id='form'>
<?php
// Go ahead and manually add the CSRF token even though jQuery will automatically add it after DOM loads.
// (This is done in case the page is very long and user submits form before the DOM has finished loading.)
print "<input type='hidden' name='redcap_csrf_token' value='".getCsrfToken()."'>";
?>
<table style="border: 1px solid #ccc; background-color: #f0f0f0; width: 100%;">


<!-- Various modules/services -->
<tr>
	<td colspan="2">
	<h3 style="font-size:14px;padding:0 10px;color:#800000;"><?php echo $lang['system_config_150'] ?></h3>
	</td>
</tr>

<!-- Enable/disable the use of surveys in projects -->
<tr>
	<td class="cc_label"><img src="<?php echo APP_PATH_IMAGES ?>survey_participants.gif" class="imgfix"> <?php echo $lang['system_config_237'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="enable_projecttype_singlesurveyforms">
			<option value='0' <?php echo ($element_data['enable_projecttype_singlesurveyforms'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_projecttype_singlesurveyforms'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
	</td>
</tr>

<tr  id="enable_url_shortener-tr" sq_id="enable_url_shortener">
	<td class="cc_label"><img src="<?php echo APP_PATH_IMAGES ?>link.png" class="imgfix"> <?php echo $lang['system_config_132'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="enable_url_shortener">
			<option value='0' <?php echo ($element_data['enable_url_shortener'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_url_shortener'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_238'] ?>
		</div>
	</td>
</tr>

<!-- Randomization -->
<tr>
	<td class="cc_label"><img src="<?php echo APP_PATH_IMAGES ?>arrow_switch.png" class="imgfix"> <?php echo $lang['app_21'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="randomization_global">
			<option value='0' <?php echo ($element_data['randomization_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['randomization_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_225'] ?>
		</div>
	</td>
</tr>

<!-- Shared Library -->
<tr  id="shared_library_enabled-tr" sq_id="shared_library_enabled">
	<td class="cc_label"><img src="<?php echo APP_PATH_IMAGES ?>blogs_arrow.png" class="imgfix"> REDCap Shared Library</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="shared_library_enabled">
			<option value='0' <?php echo ($element_data['shared_library_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['shared_library_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_110'] ?>
			<a href="<?php echo SHARED_LIB_PATH ?>" style='text-decoration:underline;' target='_blank'>REDCap Shared Library</a>
			<?php echo $lang['system_config_111'] ?>
		</div>
	</td>
</tr>

<!-- API -->
<tr >
	<td class="cc_label"><img src="<?php echo APP_PATH_IMAGES ?>computer.png" class="imgfix"> REDCap API</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="api_enabled">
			<option value='0' <?php echo ($element_data['api_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['api_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_114'] ?>
			<a href='<?php echo APP_PATH_WEBROOT_FULL ?>api/help/' style='text-decoration:underline;' target='_blank'>REDCap API help page</a><?php echo $lang['period'] ?>
		</div>
	</td>
</tr>

<?php if (isDev(true)) { ?>
<!-- REDCap Mobile App -->
<tr>
	<td class="cc_label"><img src="<?php echo APP_PATH_IMAGES ?>redcap_app_icon.gif" class="imgfix"> <?php echo $lang['global_118'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="mobile_app_enabled">
			<option value='0' <?php echo ($element_data['mobile_app_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['mobile_app_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_330'] ?>
		</div>
	</td>
</tr>
<?php } ?>


<!-- CATs -->
<tr >
	<td class="cc_label">
		<div style="margin:3px 0;">
			<a href='https://www.assessmentcenter.net/' style='text-decoration:underline;' target='_blank'><img src="<?php echo APP_PATH_IMAGES ?>assessmentcenter.gif" class="imgfix"></a><br>
		</div>
		<div style="margin:3px 0;">
			<a href='http://www.nihpromis.org/' style='text-decoration:underline;' target='_blank'><img src="<?php echo APP_PATH_IMAGES ?>promis.png" class="imgfix"></a>
		</div>
		<img src="<?php echo APP_PATH_IMAGES ?>flag_green.png" class="imgfix"> 
		<?php echo $lang['system_config_305'] ?>
		<div class="cc_info">
			<?php 
			echo "{$lang['system_config_307']} <a href='http://www.nihpromis.org/' style='text-decoration:underline;' target='_blank'>{$lang['system_config_314']}</a>
				  {$lang['system_config_316']} <a href='https://www.assessmentcenter.net/' style='text-decoration:underline;' target='_blank'>Assessment Center API</a>{$lang['period']}";
			?>
		</div>
	</td>
	<td class="cc_data">
		<table cellspacing=0 width=100%>
			<tr>
				<td valign="top">
					<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="promis_enabled">
						<option value='0' <?php echo ($element_data['promis_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
						<option value='1' <?php echo ($element_data['promis_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
					</select>
				</td>
				<td style="padding-left:15px;">
					<div style="margin:0 0 3px;">
						<?php echo $lang['system_config_317'] ?> 
						&nbsp;&nbsp;<button class="jqbuttonmed" onclick="testUrl('<?php echo $promis_api_base_url ?>','post','');return false;"><?php echo $lang['edit_project_138'] ?></button>
					</div>
					<div style="margin:3px 0;font-size:11px;color:#777;line-height:11px;">
						<?php echo $lang['system_config_318'] . " " . RCView::span(array('style'=>'color:#C00000;'), $promis_api_base_url) ?>
					</div>
				</td>
			</tr>
		</table>
		<div class="cc_info" style="color:#800000;margin-bottom:10px;">
			<?php echo "{$lang['system_config_315']} <a href='https://www.assessmentcenter.net/' style='text-decoration:underline;' target='_blank'>Assessment Center</a>{$lang['period']}
						{$lang['system_config_322']}" ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_306'] ?>
			<a href='http://www.nihpromis.org/' style='text-decoration:underline;' target='_blank'>NIH PROMIS</a><?php echo $lang['period'] ?>
		</div>
	</td>
</tr>

<tr  id="dts_enabled_global-tr" sq_id="dts_enabled_global">
	<td class="cc_label"><img src="<?php echo APP_PATH_IMAGES ?>databases_arrow.png" class="imgfix"> <?php echo $lang['rights_132'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="dts_enabled_global">
			<option value='0' <?php echo ($element_data['dts_enabled_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['dts_enabled_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_124'] ?>
			<a href='https://iwg.devguard.com/trac/redcap/wiki/DTS' style='text-decoration:underline;' target='_blank''>REDCap DTS wiki page</a><?php echo $lang['period'] ?>
		</div>
	</td>
</tr>




<?php if (isDev(true)) { ?>
<!-- Twilio -->
<tr>
	<td colspan="2">
	<hr size=1>
	<h3 style="font-size:14px;padding:0 10px;color:#800000;">
		<img src="<?php echo APP_PATH_IMAGES ?>twilio.gif" class="imgfix">
		<?php echo $lang['survey_913'] ?>
	</h3>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['survey_847'] ?>
		<div class="cc_info">
			<?php echo $lang['survey_848'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="twilio_enabled_global">
			<option value='0' <?php echo ($element_data['twilio_enabled_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['twilio_enabled_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<span style="margin-left:12px;">
			<?php echo $lang['system_config_317'] ?> 
			&nbsp;&nbsp;<button class="jqbuttonmed" onclick="testUrl('https://api.twilio.com','post','');return false;"><?php echo $lang['edit_project_138'] ?></button>
		</span>
		<div class="cc_info">
			<?php echo $lang['survey_712'] ?>
			<b>https://api.twilio.com</b><?php echo $lang['period'] ?>
			<?php echo $lang['survey_853']." ".$lang['survey_713'] ?>
			<a href='https://www.twilio.com' style='text-decoration:underline;' target='_blank'>https://www.twilio.com</a><?php echo $lang['period'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['survey_908'] ?>
	</td>
	<td class="cc_data">
		<select onchange="setTwilioDisplayInfo()" class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="twilio_enabled_by_super_users_only">
			<option value='0' <?php echo ($element_data['twilio_enabled_by_super_users_only'] == 0) ? "selected" : "" ?>><?php echo $lang['survey_909'] ?></option>
			<option value='1' <?php echo ($element_data['twilio_enabled_by_super_users_only'] == 1) ? "selected" : "" ?>><?php echo $lang['survey_910'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['survey_911'] ?>
		</div>
	</td>
</tr>

<tr id="twilio_display_info_project_setup-tr">
	<td class="cc_label">
		<?php echo $lang['survey_849'] ?>
		<div class="cc_info" style="color:#800000;">
			<?php echo $lang['survey_912'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="twilio_display_info_project_setup">
			<option value='0' <?php echo ($element_data['twilio_display_info_project_setup'] == 0) ? "selected" : "" ?>><?php echo $lang['survey_850'] ?></option>
			<option value='1' <?php echo ($element_data['twilio_display_info_project_setup'] == 1) ? "selected" : "" ?>><?php echo $lang['survey_851'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['survey_852'] ?>
		</div>
	</td>
</tr>
<?php } ?>

<tr>
	<td colspan="2">
	<hr size=1>
	<h3 style="font-size:14px;padding:0 10px;color:#800000;">
		<img src="<?php echo APP_PATH_IMAGES ?>chart_bar.png" class="imgfix"> 
		<?php echo $lang['system_config_172'] ?>
	</h3>
	</td>
</tr>
<tr  id="enable_plotting-tr" sq_id="enable_plotting">
	<td class="cc_label">
		<?php echo $lang['system_config_175'] ?>
		<div class="cc_info" style="font-weight:normal;"><?php echo $lang['system_config_323'] ?></div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="enable_plotting">
			<option value='0' <?php echo ($element_data['enable_plotting'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='2' <?php echo ($element_data['enable_plotting'] == 2) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>		
		<div class="cc_info" style="color:#800000;font-weight:normal;"><?php echo $lang['system_config_174'] ?></div>
	</td>
</tr>
<tr  id="enable_plotting_survey_results-tr" sq_id="enable_plotting_survey_results">
	<td class="cc_label">
		<?php echo $lang['system_config_176'] ?>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="enable_plotting_survey_results">
			<option value='0' <?php echo ($element_data['enable_plotting_survey_results'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_plotting_survey_results'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_171'] ?>
		</div>
	</td>
</tr>
</table><br/>
<div style="text-align: center;"><input type='submit' name='' value='Save Changes' /></div><br/>
</form>

<?php include 'footer.php'; ?>