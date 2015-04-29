<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

include 'header.php';

$changesSaved = false;

// If project default values were changed, update redcap_config table with new values
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
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

<h3 style="margin-top: 0;"><?php echo RCView::img(array('src'=>'group_gear.png', 'class'=>'imgfix2')) . $lang['system_config_156'] ?></h3>

<form enctype='multipart/form-data' target='_self' method='post' name='form' id='form' onSubmit="return validateEmailDomainWhitelist();">
<?php
// Go ahead and manually add the CSRF token even though jQuery will automatically add it after DOM loads.
// (This is done in case the page is very long and user submits form before the DOM has finished loading.)
print "<input type='hidden' name='redcap_csrf_token' value='".getCsrfToken()."'>";
?>
<table style="border: 1px solid #ccc; background-color: #f0f0f0;">

<tr>
	<td colspan="2">
		<h3 style="font-size:14px;padding:0 10px;color:#800000;"><?php echo $lang['system_config_292'] ?></h3>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['system_config_12'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="superusers_only_create_project">
			<option value='0' <?php echo ($element_data['superusers_only_create_project'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_15'] ?></option>
			<option value='1' <?php echo ($element_data['superusers_only_create_project'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_14'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_13'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label"><?php echo $lang['system_config_16'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;width:350px;" name="superusers_only_move_to_prod">
			<option value='0' <?php echo ($element_data['superusers_only_move_to_prod'] == '0') ? "selected" : "" ?>><?php echo $lang['system_config_146'] ?></option>
			<option value='1' <?php echo ($element_data['superusers_only_move_to_prod'] == '1') ? "selected" : "" ?>><?php echo $lang['system_config_18'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_17'] ?>
		</div>
	</td>
</tr>

<!-- Set time of inactivity after which users get auto-suspended -->
<tr>
	<td class="cc_label"><?php echo $lang['control_center_4391'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;max-width:350px;" name="suspend_users_inactive_type">
			<option value='' <?php echo ($element_data['suspend_users_inactive_type'] == '') ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<?php if ($auth_meth_global == 'ldap_table') { ?><option value='table' <?php echo ($element_data['suspend_users_inactive_type'] == 'table') ? "selected" : "" ?>><?php echo $lang['control_center_4389'] ?></option><?php } ?>
			<option value='all' <?php echo ($element_data['suspend_users_inactive_type'] == 'all') ? "selected" : "" ?>><?php echo $lang['control_center_4390'] ?></option>
		</select>
		<div style="padding-top:5px;">
			<span style="font-weight:bold;vertical-align:middle;margin-right:5px;"><?php echo $lang['control_center_4393'] ?></span>
			<input class='x-form-text x-form-field '  type='text' name='suspend_users_inactive_days' value='<?php echo $element_data['suspend_users_inactive_days'] ?>'
				onblur="redcap_validate(this,'1','','hard','int')" style="width:30px;vertical-align:middle;" />
			<span style="vertical-align:middle;"><?php echo $lang['define_events_31'] ?></span>
		</div>
		<div style="padding-bottom:5px;">
			<span style="font-weight:bold;vertical-align:middle;margin-right:5px;"><?php echo $lang['control_center_4422'] ?></span>
			<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="suspend_users_inactive_send_email">
				<option value='0' <?php echo ($element_data['suspend_users_inactive_send_email'] == '0') ? "selected" : "" ?>><?php echo $lang['design_99'] ?></option>
				<option value='1' <?php echo ($element_data['suspend_users_inactive_send_email'] == '1') ? "selected" : "" ?>><?php echo $lang['design_100'] ?></option>
			</select>
		</div>
		<div class="cc_info">
			<?php echo $lang['control_center_4423'] . " " . $lang['control_center_4425'] ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['global_02'].$lang['colon']." ".$lang['control_center_4394'] ?>
		</div>
	</td>
</tr>

<!-- User Access Dashboard settings -->
<tr id="tr-user_access_dashboard_enable">
	<td class="cc_label">
		<?php echo $lang['rights_226'] ?>
		<div class="cc_info">
			<?php echo $lang['rights_245'] ?>
		</div>
		<div class="cc_info" style="margin-top:15px;">
			<?php echo $lang['rights_254'] ?>
		</div>
		<div class="cc_info" style="margin-top:15px;">
			<?php echo RCView::b($lang['rights_261'])."<br>".$lang['rights_262']." ".
				RCView::span(array('style'=>'color:#800000;'), "\"".$lang['rights_242']."\"") ?>
		</div>
	</td>
	<td class="cc_data">
		<div style="margin:0 0 1px;"><?php echo $lang['rights_260'] ?></div>
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;width:350px;" name="user_access_dashboard_enable">
			<option value='0' <?php echo ($element_data['user_access_dashboard_enable'] == '0') ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['user_access_dashboard_enable'] == '1') ? "selected" : "" ?>><?php echo $lang['rights_246'] ?></option>
			<option value='2' <?php echo ($element_data['user_access_dashboard_enable'] == '2') ? "selected" : "" ?>><?php echo $lang['rights_247'] ?></option>
			<option value='3' <?php echo ($element_data['user_access_dashboard_enable'] == '3') ? "selected" : "" ?>><?php echo $lang['rights_248'] ?></option>
		</select>
		<div>
			<div style="margin:12px 0 1px;"><?php echo $lang['rights_259'] ?></div>
			<textarea class='x-form-field notesbox' style='height:50px;' id='user_access_dashboard_custom_notification' name='user_access_dashboard_custom_notification'><?php echo $element_data['user_access_dashboard_custom_notification'] ?></textarea><br/>
			<div id='user_access_dashboard_custom_notification-expand' style='text-align:right;'>
				<a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
					onclick="growTextarea('user_access_dashboard_custom_notification')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
			</div>
		</div>
		<div class="cc_info" style="margin:0;">
			<b><?php echo $lang['rights_249'] ?></b><br>
			<u><?php echo $lang['global_23'] ?></u> - <?php echo $lang['rights_250'] ?><br>
			<u><?php echo $lang['rights_246'] ?></u> - <?php echo $lang['rights_251'] ?><br>
			<u><?php echo $lang['rights_247'] ?></u> - <?php echo $lang['rights_252'] ?><br>
			<u><?php echo $lang['rights_248'] ?></u> - <?php echo $lang['rights_253'] ?>
		</div>
	</td>
</tr>

<!-- Allow users to edit survey responses -->
<tr>
	<td class="cc_label"><?php echo $lang['system_config_185'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="enable_edit_survey_response">
			<option value='0' <?php echo ($element_data['enable_edit_survey_response'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_edit_survey_response'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_186'] ?>
		</div>
	</td>
</tr>

<!-- Auto production changes -->
<tr id="tr-auto_prod_changes">
	<td class="cc_label">
		<?php echo $lang['system_config_198'] ?>
		<div class="cc_info" style="font-weight:normal;">
			<?php echo $lang['system_config_199'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;width:350px;" name="auto_prod_changes">
			<option value='0' <?php echo ($element_data['auto_prod_changes'] == '0') ? "selected" : "" ?>><?php echo $lang['system_config_200'] ?></option>
			<option value='2' <?php echo ($element_data['auto_prod_changes'] == '2') ? "selected" : "" ?>><?php echo $lang['system_config_201'] ?></option>
			<option value='3' <?php echo ($element_data['auto_prod_changes'] == '3') ? "selected" : "" ?>><?php echo $lang['system_config_203'] ?></option>
			<option value='4' <?php echo ($element_data['auto_prod_changes'] == '4') ? "selected" : "" ?>><?php echo $lang['system_config_204'] ?></option>
			<option value='1' <?php echo ($element_data['auto_prod_changes'] == '1') ? "selected" : "" ?>><?php echo $lang['system_config_202'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_205'] ?>
		</div>
		<div class="cc_info">
			<?php echo "<b style='font-size:12px;'>{$lang['system_config_220']}</b><br>{$lang['system_config_221']}" ?>
		</div>
	</td>
</tr>

<!-- Add/edit events while in production -->
<tr>
	<td class="cc_label">
		<?php echo $lang['system_config_190'] ?>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;width:350px;" name="enable_edit_prod_events">
			<option value='0' <?php echo ($element_data['enable_edit_prod_events'] == '0') ? "selected" : "" ?>><?php echo $lang['system_config_192'] ?></option>
			<option value='1' <?php echo ($element_data['enable_edit_prod_events'] == '1') ? "selected" : "" ?>><?php echo $lang['system_config_193'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_191'] ?>
		</div>
		<div class="cc_info" style="font-weight:normal;">
			<?php echo $lang['system_config_197'] ?>
		</div>
	</td>
</tr>

<!-- Domain whitelist for user email addresses -->
<tr>
	<td class="cc_label">
		<?php echo $lang['system_config_232'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_233'] ?>
		</div>
	</td>
	<td class="cc_data">
		<textarea class='x-form-field notesbox' id='email_domain_whitelist' name='email_domain_whitelist'><?php echo $element_data['email_domain_whitelist'] ?></textarea><br/>
		<div id='email_domain_whitelist-expand' style='text-align:right;'>
			<a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
				onclick="growTextarea('email_domain_whitelist')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_234'] ?>
		</div>
		<div class="cc_info" style="padding:2px;border:1px solid #ccc;width:200px;">
			vanderbilt.edu<br>
			mc.vanderbilt.edu<br>
			mmc.edu<br>
		</div>
	</td>
</tr>


<tr>
	<td class="cc_label"><?php echo $lang['system_config_103'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="my_profile_enable_edit">
			<option value='0' <?php echo ($element_data['my_profile_enable_edit'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_105'] ?></option>
			<option value='1' <?php echo ($element_data['my_profile_enable_edit'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_106'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_104'] ?>
		</div>
	</td>
</tr>

<!-- Default settings for new users -->
<tr>
	<td colspan="2">
		<hr size=1>
		<h3 style="font-size:14px;padding:0 10px;color:#800000;"><?php echo $lang['system_config_291'] ?></h3>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['system_config_163'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;font-family:tahoma;" name="allow_create_db_default">
			<option value='0' <?php echo ($element_data['allow_create_db_default'] == 0) ? "selected" : "" ?>><?php echo $lang['design_99'] ?></option>
			<option value='1' <?php echo ($element_data['allow_create_db_default'] == 1) ? "selected" : "" ?>><?php echo $lang['design_100'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['system_config_142'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['user_82'] ?></td>
	<td class="cc_data">
		<?php echo RCView::select(array('name'=>'default_datetime_format', 'class'=>'x-form-text x-form-field', 'style'=>'font-family:tahoma;padding-right:0;height:22px;'), 
					DateTimeRC::getDatetimeDisplayFormatOptions(), $element_data['default_datetime_format']) ?>
		<div style='color:#800000;font-size:11px;padding-top:3px;'>(e.g., 12/31/2004 22:57 or 31/12/2004 10:57pm)</div>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['user_83'] ?></td>
	<td class="cc_data">
		<?php echo RCView::select(array('name'=>'default_number_format_decimal', 'class'=>'x-form-text x-form-field', 'style'=>'font-family:tahoma;padding-right:0;height:22px;'), 
					User::getNumberDecimalFormatOptions(), $element_data['default_number_format_decimal']) ?>
		<div style='color:#800000;font-size:11px;padding-top:3px;'>(e.g., 3.14 or 3,14)</div>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['user_84'] ?></td>
	<td class="cc_data">
		<?php echo RCView::select(array('name'=>'default_number_format_thousands_sep', 'class'=>'x-form-text x-form-field', 'style'=>'font-family:tahoma;padding-right:0;height:22px;'), 
					User::getNumberThousandsSeparatorOptions(), $element_data['default_number_format_thousands_sep']) ?>
		<div style='color:#800000;font-size:11px;padding-top:3px;'>(e.g., 1,000,000 or 1.000.000 or 1 000 000)</div>
	</td>
</tr>

</table><br/>
<div style="text-align: center;"><input type='submit' value='Save Changes'/></div><br/>
</form>

<script type="text/javascript">
// Validate the domain names submitted for the email_domain_whitelist field
function validateEmailDomainWhitelist() {
	// First, trim the value
	$('#email_domain_whitelist').val( trim($('#email_domain_whitelist').val()));
	// If it's blank, then ignore and just submit the form
	var domainWhitelist = $('#email_domain_whitelist').val();
	if (domainWhitelist.length < 1) return true;
	// Loop through each domain (i.e. each line)
	var domainWhitelistArray = domainWhitelist.split("\n");
	var failedDomains = new Array();
	var passedDomains = new Array();
	var k = 0;
	var h = 0;
	for (var i=0; i<domainWhitelistArray.length; i++) {
		var thisDomain = trim(domainWhitelistArray[i]);
		if (thisDomain != '') {
			if (!isDomainName(thisDomain)) {
				failedDomains[k] = thisDomain;
				k++;
			} else {
				passedDomains[h] = thisDomain;
				h++;
			}
		}
	}
	// Display error message for the invalid domains
	if (k > 0) {
		simpleDialog('<?php echo cleanHtml($lang['system_config_235']) ?><br><br><?php echo cleanHtml($lang['system_config_236']) ?><br><b>'+failedDomains.join('<br>')+'</b>','<?php echo cleanHtml($lang['global_01']) ?>',null,null,"$('#email_domain_whitelist').focus();");
		return false;
	}
	// Set field's value with new cleaned value (trimmed and removed blank lines)
	$('#email_domain_whitelist').val( passedDomains.join("\n") );
	return true;
}
</script>

<?php include 'footer.php'; ?>