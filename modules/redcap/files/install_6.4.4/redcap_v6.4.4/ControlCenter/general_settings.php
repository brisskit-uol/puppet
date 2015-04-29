<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

## HTTP COMPRESSION: If zlib PHP extension, not installed, then set $element_data['enable_http_compression'] to 0
error_reporting(0);
// Try to set compression to see if it sets it
ini_set('zlib.output_compression', 4096);
ini_set('zlib.output_compression_level', -1);
// Set boolean parameter if it is able to enable compression
$canEnableHttpCompression = (function_exists('ob_gzhandler') && ini_get('zlib.output_compression'));


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
// Make sure redcap_base_url has slash on end
if ($element_data['redcap_base_url'] != '' && substr($element_data['redcap_base_url'], -1) != '/') {
	$element_data['redcap_base_url'] .= '/';
}

// Set value of enable_http_compression to 0 if don't have Zlib library
if (!$canEnableHttpCompression) $element_data['enable_http_compression'] = '0';

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

<h3 style="margin-top: 0;"><?php echo RCView::img(array('src'=>'table_gear.png', 'class'=>'imgfix2')) . $lang['control_center_125'] ?></h3>

<form action='general_settings.php' enctype='multipart/form-data' target='_self' method='post' name='form' id='form'>
<?php
// Go ahead and manually add the CSRF token even though jQuery will automatically add it after DOM loads.
// (This is done in case the page is very long and user submits form before the DOM has finished loading.)
print "<input type='hidden' name='redcap_csrf_token' value='".getCsrfToken()."'>";
?>
<table style="border: 1px solid #ccc; background-color: #f0f0f0;">

<tr  id="system_offline-tr" sq_id="system_offline">
	<td class="cc_label">
		<img src="<?php echo APP_PATH_IMAGES ?>off.png" class="imgfix">
		<?php echo $lang['system_config_02'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_03'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="system_offline">
			<option value='0' <?php echo ($element_data['system_offline'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_05'] ?></option>
			<option value='1' <?php echo ($element_data['system_offline'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_04'] ?></option>
		</select>
		<div class="cc_info" style="margin-top:15px;font-weight:bold;">
			<?php echo $lang['system_config_240'] ?>
		</div>
		<textarea style='height:45px;' class='x-form-field notesbox' id='system_offline_message' name='system_offline_message'><?php echo $element_data['system_offline_message'] ?></textarea>
		<div id='system_offline_message-expand' style='text-align:right;'>
			<a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
				onclick="growTextarea('system_offline_message')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_195'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['pub_105'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='redcap_base_url' value='<?php echo $element_data['redcap_base_url'] ?>' size="60" onblur="
			var a = dirname(dirname(dirname(document.URL)))+'/'; 
			if (a != this.value && a != this.value+'/') {
				simpleDialog('<?php print cleanHtml($lang['control_center_4439']) ?><br><br><?php print cleanHtml($lang['control_center_4440']) ?> <b>'+a+'</b>');
			}
		"><br/>
		<div class="cc_info">
			<?php echo $lang['pub_110'] ?>
		</div>
		<script type="text/javascript">
		$(function(){
			var old_base_url = '<?php print cleanHtml($element_data['redcap_base_url']) ?>';
			var a = dirname(dirname(dirname(document.URL)))+'/'; 
			if (a != old_base_url && a != old_base_url+'/') {
				$('#base_url_error_msg').show();
			}
		});
		</script>
		<div id="base_url_error_msg" class="<?php echo ($redcap_base_url_display_error_on_mismatch ? "red" : "yellow") ?>" style="display:none;margin-top:5px;font-size:11px;">
			<?php if ($redcap_base_url_display_error_on_mismatch) { ?>
				<img src="<?php echo APP_PATH_IMAGES ?>bullet_delete.png" class="imgfix">
				<b><?php echo $lang['global_48'].$lang['colon'] ?></b> 
			<?php } else { ?>
				<b><?php echo $lang['global_02'].$lang['colon'] ?></b> 
			<?php } ?>
			<?php echo $lang['control_center_318'] ?>
			<b><?php echo APP_PATH_WEBROOT_FULL ?></b><br><?php echo $lang['control_center_319'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['system_config_187'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_239'] ?>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='proxy_hostname' value='<?php echo $element_data['proxy_hostname'] ?>' size="60" /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_188'] ?><br>(e.g., https://10.151.18.250:211)
		</div>
	</td>
</tr>


<tr id="auto_report_stats-tr">
	<td class="cc_label"><?php echo $lang['system_config_28'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="auto_report_stats">
			<option value='0' <?php echo ($element_data['auto_report_stats'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_30'] ?></option>
			<option value='1' <?php echo ($element_data['auto_report_stats'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_31'] ?></option>
		</select>
		&nbsp;&nbsp;
		<a href="javascript:;" style="padding-left:5px;font-size:10px;font-family:tahoma;text-decoration:underline;" onclick="simpleDialog('<?php echo cleanHtml($lang['dashboard_94']." ".$lang['dashboard_101']) ?>','<?php echo cleanHtml($lang['dashboard_77']) ?>');"><?php echo $lang['dashboard_77'] ?></a>
		<div class="cc_info">
			<?php echo $lang['dashboard_90'] ?>
		</div>
	</td>
</tr>





<tr  id="project_contact_name-tr" sq_id="project_contact_name">
	<td class="cc_label"><?php echo $lang['system_config_91'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='project_contact_name' value='<?php echo $element_data['project_contact_name'] ?>' size="40" /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_92'] ?>
		</div>
	</td>
</tr>
<tr  id="project_contact_email-tr" sq_id="project_contact_email">
	<td class="cc_label"><?php echo "{$lang['system_config_93']} {$lang['system_config_91']}" ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field '  type='text' name='project_contact_email' value='<?php echo $element_data['project_contact_email'] ?>'
			onblur="redcap_validate(this,'','','hard','email')" size='40' /><br/>
	</td>
</tr>
<tr  id="project_contact_prod_changes_name-tr" sq_id="project_contact_prod_changes_name">
	<td class="cc_label"><?php echo $lang['system_config_94'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='project_contact_prod_changes_name' value='<?php echo $element_data['project_contact_prod_changes_name'] ?>' size="40" /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_95'] ?>
		</div>
	</td>
</tr>
<tr  id="project_contact_prod_changes_email-tr" sq_id="project_contact_prod_changes_email">
	<td class="cc_label"><?php echo "{$lang['system_config_93']} {$lang['system_config_96']}" ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field '  type='text' name='project_contact_prod_changes_email' value='<?php echo $element_data['project_contact_prod_changes_email'] ?>'
			onblur="redcap_validate(this,'','','hard','email')" size='40' /><br/>
	</td>
</tr>
<tr  id="institution-tr" sq_id="institution">
	<td class="cc_label"><?php echo $lang['system_config_97'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='institution' value='<?php echo $element_data['institution'] ?>' size="60" /><br/>
	</td>
</tr>
<tr  id="site_org_type-tr" sq_id="site_org_type">
	<td class="cc_label"><?php echo $lang['system_config_98'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='site_org_type' value='<?php echo $element_data['site_org_type'] ?>' size="60" /><br/>
	</td>
</tr>
<tr  id="grant_cite-tr" sq_id="grant_cite">
	<td class="cc_label"><?php echo $lang['system_config_313'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='grant_cite' value='<?php echo $element_data['grant_cite'] ?>' size="40" /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_100'] ?>
		</div>
	</td>
</tr>
<tr  id="headerlogo-tr" sq_id="headerlogo">
	<td class="cc_label"><?php echo $lang['system_config_312'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='headerlogo' value='<?php echo $element_data['headerlogo'] ?>' size="60" /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_102'] ?>
		</div>
	</td>
</tr>


<tr>
	<td class="cc_label">
		<?php echo $lang['system_config_325'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_327'] ?>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='from_email' value='<?php echo $element_data['from_email'] ?>' onblur="redcap_validate(this,'','','hard','email')" size="40" /><br/>
		<div class="cc_info">
			<?php echo "{$lang['system_config_64']} <span style='color:#800000;'>no-reply@vanderbilt.edu, donotreply@" . SERVER_NAME ?>
		</div>
		<div class="cc_info" style="margin:10px 0 0;">
			<?php echo $lang['system_config_326'] ?>
		</div>
	</td>
</tr>


<!-- Field Comment Log default -->
<tr >
	<td class="cc_label">
		<img src="<?php echo APP_PATH_IMAGES ?>balloons.png" class="imgfix">
		<?php echo $lang['system_config_328'] ?>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="field_comment_log_enabled_default">
			<option value='0' <?php echo ($element_data['field_comment_log_enabled_default'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['field_comment_log_enabled_default'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['system_config_329'] ?>
		</div>
	</td>
</tr>

<!-- Path of custom functions PHP script  -->
<tr id="hook_functions_file-tr">
	<td class="cc_label">
		<img src="<?php echo APP_PATH_IMAGES ?>hook.png" class="imgfix">
		<?php echo $lang['system_config_299'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_301'] ?>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field' type='text' name='hook_functions_file' value='<?php echo $element_data['hook_functions_file'] ?>' size="60" />
		<div class="cc_info">
			<?php echo $lang['system_config_302'] ?>
		</div>
		<div class="cc_info" style="margin:10px 0 15px;">
			<?php echo "{$lang['system_config_64']} <span style='color:#800000;'>".dirname(dirname(dirname(__FILE__))).DS."hooks.php</span>" ?>
		</div>
	</td>
</tr>

<tr  id="language_global-tr" sq_id="language_global">
	<td class="cc_label"><?php echo $lang['system_config_112'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="language_global"
			onchange="alert('<?php echo $lang['global_02'] ?>:\n<?php echo cleanHtml($lang['system_config_113']) ?>');">
			<?php
			$languages = getLanguageList();
			foreach ($languages as $language) {
				$selected = ($element_data['language_global'] == $language) ? "selected" : "";
				echo "<option value='$language' $selected>$language</option>";
			}
			?>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_107'] ?>
			<a href="<?php echo APP_PATH_WEBROOT ?>LanguageUpdater/" target='_blank' style='text-decoration:underline;'>Language File Creator/Updater</a>
			<?php echo $lang['system_config_108'] ?>
			<a href='https://iwg.devguard.com/trac/redcap/wiki/Languages' target='_blank' style='text-decoration:underline;'>REDCap wiki Language Center</a>.
			<br/><br/><?php echo $lang['system_config_109']." ".dirname(APP_PATH_DOCROOT).DS."languages".DS ?>
		</div>
	</td>
</tr>

<!-- Enable HTTP Compression -->
<tr >
	<td class="cc_label">
		<?php echo $lang['system_config_259'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_260'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="enable_http_compression">
			<option value='0' <?php echo ($element_data['enable_http_compression'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_http_compression'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<div class="cc_info">
			<b><?php echo $lang['system_config_261'] ?></b><br><?php echo $lang['system_config_262'] ?>
			<a href="http://en.wikipedia.org/wiki/Transport_Layer_Security#CRIME_attack_and_BREACH_attack" target="_blank" style="text-decoration:underline;">BREACH attack</a><?php echo $lang['period'] ?>
			<?php echo $lang['system_config_263'] ?>
		</div>
		<?php if (!$canEnableHttpCompression) { ?>
		<div class="red cc_info" style="color:#C00000;">
			<?php echo $lang['system_config_264'] ?>
			<a href="http://www.php.net/manual/en/book.zlib.php" target="_blank" style="text-decoration:underline;">Zlib extension</a><?php echo $lang['period'] ?>
		</div>
		<?php } ?>
	</td>
</tr>


<!-- Data Entry Trigger enable -->
<tr>
	<td class="cc_label">
		<?php echo $lang['edit_project_136'] ?>
		<div class="cc_info">
			<?php echo $lang['edit_project_137'] ?> 
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="padding-right:0; height:22px;" name="data_entry_trigger_enabled">
			<option value='0' <?php echo ($element_data['data_entry_trigger_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['data_entry_trigger_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['edit_project_123'] ?> 
			<a href="javascript:;" onclick="simpleDialog(null,null,'dataEntryTriggerDialog',650);" class="nowrap" style="text-decoration:underline;"><?php echo $lang['edit_project_127'] ?></a>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['system_config_226'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_227'] ?>
		</div>
	</td>
	<td class="cc_data">
		<textarea class='x-form-field notesbox' name='helpfaq_custom_text'><?php echo $element_data['helpfaq_custom_text'] ?></textarea><br/>
		<div id='helpfaq_custom_text-expand' style='text-align:right;'>
			<a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
				onclick="growTextarea('helpfaq_custom_text')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
		</div>
	</td>
</tr>

<tr  id="certify_text_create-tr" sq_id="certify_text_create">
	<td class="cc_label"><?php echo $lang['system_config_38'] ?></td>
	<td class="cc_data">
		<textarea class='x-form-field notesbox' id='certify_text_create' name='certify_text_create'><?php echo $element_data['certify_text_create'] ?></textarea><br/>
		<div id='certify_text_create-expand' style='text-align:right;'>
			<a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
				onclick="growTextarea('certify_text_create')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_39'] ?>
		</div>
	</td>
</tr>
<tr  id="certify_text_prod-tr" sq_id="certify_text_prod">
	<td class="cc_label"><?php echo $lang['system_config_40'] ?></td>
	<td class="cc_data">
		<textarea class='x-form-field notesbox' id='certify_text_prod' name='certify_text_prod'><?php echo $element_data['certify_text_prod'] ?></textarea><br/>
		<div id='certify_text_prod-expand' style='text-align:right;'>
			<a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
				onclick="growTextarea('certify_text_prod')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_41'] ?>
		</div>
	</td>
</tr>
<tr  id="identifier_keywords-tr" sq_id="identifier_keywords">
	<td class="cc_label"><img src="<?php echo APP_PATH_IMAGES ?>find.png" class="imgfix"> <?php echo "{$lang['identifier_check_01']} - {$lang['system_config_115']}" ?></td>
	<td class="cc_data">
		<textarea class='x-form-field notesbox' id='identifier_keywords' name='identifier_keywords'><?php echo $element_data['identifier_keywords'] ?></textarea><br/>
		<div id='identifier_keywords-expand' style='text-align:right;'>
			<a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
				onclick="growTextarea('identifier_keywords')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
		</div>
		<div class="cc_info">
			<?php echo "{$lang['system_config_116']} {$lang['identifier_check_01']}{$lang['period']}
				{$lang['system_config_117']}<br><br>
				<b>{$lang['system_config_64']}</b><br>$identifier_keywords_default" ?>
		</div>
	</td>
</tr>
</table><br/>
<div style="text-align: center;"><input type='submit' name='' value='Save Changes' /></div><br/>
</form>

<?php
// Data Entry Trigger explanation - hidden dialog
print RCView::simpleDialog($lang['edit_project_123']."<br><br>".$lang['edit_project_128'] .
	RCView::div(array('style'=>'padding:12px 0 2px;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('project_id')." - ".$lang['edit_project_129']). 
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('instrument')." - ".$lang['edit_project_130']). 
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('record')." - ".$lang['edit_project_131'].$lang['period']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('redcap_event_name')." - ".$lang['edit_project_132']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('redcap_data_access_group')." - ".$lang['edit_project_133']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('[instrument]_complete')." - ".$lang['edit_project_134']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('redcap_url')." - ".$lang['edit_project_144']."<br>i.e., ".APP_PATH_WEBROOT_FULL).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('project_url')." - ".$lang['edit_project_145']."<br>i.e., ".APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/index.php?pid=XXXX").
	RCView::div(array('style'=>'padding:20px 0 5px;color:#C00000;'), $lang['global_02'].$lang['colon'].' '.$lang['edit_project_135'])
	,$lang['edit_project_122'],'dataEntryTriggerDialog');

// Footer
include 'footer.php';
