<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// Check if the URL is pointing to the correct version of REDCap specified for this project. If not, redirect to correct version.
check_version();

//If user is not a super user, go back to Home page
if (!$super_user) redirect(APP_PATH_WEBROOT);

// Check for any extra whitespace from config files that would mess up lots of things
$prehtml = ob_get_contents();


// Initialize page display object
$objHtmlPage = new HtmlPage();
$objHtmlPage->addExternalJS(APP_PATH_JS . "base.js");
$objHtmlPage->addExternalJS(APP_PATH_JS . "yui_charts.js");
$objHtmlPage->addExternalJS(APP_PATH_JS . "underscore-min.js");
$objHtmlPage->addExternalJS(APP_PATH_JS . "backbone-min.js");
$objHtmlPage->addExternalJS(APP_PATH_JS . "RedCapUtil.js");
$objHtmlPage->addStylesheet("smoothness/jquery-ui-".JQUERYUI_VERSION.".custom.css", 'screen,print');
$objHtmlPage->addStylesheet("style.css", 'screen,print');
$objHtmlPage->addStylesheet("home.css", 'screen,print');
$objHtmlPage->PrintHeader();

?>

<style type='text/css'>
.cc_label {
	padding: 10px; font-weight: bold; vertical-align: top; line-height: 16px; width: 40%;
}
.cc_data {
	padding: 10px; width: 60%; vertical-align: top;
}
.label, .data {
	background:#F0F0F0 url('<?php echo APP_PATH_IMAGES ?>label-bg.gif') repeat-x scroll 0 0;
	border:1px solid #CCCCCC;
	font-size:12px;
	font-weight:bold;
	font-family:arial;
	padding:5px 10px;
}
.label a:link, .label a:visited, .label a:active, .label a:hover { font-size:12px; font-family: Arial; }
.notesbox {
	width: 380px;
}
.form_border { width: 100%;	}
#sub-nav { font-size:60%; }
form#form .imgfix { top:-2px; vertical-align:middle; }
</style>

<?php renderHomeHeaderLinks() ?>

<table cellspacing=0 width=100%">
<tr valign=top>
	<td>
		<img src="<?php echo APP_PATH_IMAGES ?>redcaplogo.gif">
	</td>
	<td valign="bottom">
		<div style="text-align:right;color:#800000;font-size:34px;font-weight:bold;font-family:verdana;"><?php echo $lang['global_07'] ?></div>
		<!-- Hide the Control Center video until a more recent one is recorded
		<div style="text-align:right;">
			<img src="<?php echo APP_PATH_IMAGES ?>video_small.png" class="imgfix">
			<a onclick="popupvid('redcap_control_center01.flv','The REDCap Control Center')" href="javascript:;" style="font-size:11px;text-decoration:underline;font-weight:normal;"
			><?php echo $lang['control_center_103'] ?></a>
		</div>
		-->
	</td>
</tr>
<tr valign=top>
	<td colspan=2>
		<?php include APP_PATH_DOCROOT . 'Home/tabs.php'; ?>

		<table cellspacing=0 style="width:100%;table-layout: fixed;">
		<tr>
			<td valign="top" style="width:210px;">
				<div id="control_center_menu" style="border:1px solid #ddd;background-color:#fafafa;color:#000;padding:5px;">
					<!-- Control Center Home -->
					<b style="position:relative;"><?php echo $lang['control_center_129'] ?></b><br/>
					<span style="position: relative; float: left; left: 4px;">
						<img src="<?php echo APP_PATH_IMAGES ?>information_frame.png" class="imgfix">&nbsp; <a href="index.php"><?php echo $lang['control_center_117'] ?></a><br/>
					</span>
					<div style="clear: both;padding-bottom:6px;margin:0 -6px 3px;border-bottom:1px solid #ddd;"></div>
					<!-- Dashboard -->
					<b style="position:relative;"><?php echo $lang['control_center_03'] ?></b><br/>
						<span style="position: relative; float: left; left: 4px;">
							<img src="<?php echo APP_PATH_IMAGES ?>table.png" class="imgfix">&nbsp; <a href="system_stats.php"><?php echo $lang['dashboard_48'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>report_user.png" class="imgfix">&nbsp; <a href="todays_activity.php"><?php echo $lang['control_center_206'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>chart_bar.png" class="imgfix">&nbsp; <a href="graphs.php"><?php echo $lang['control_center_4395'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>map_marker_blue.png" class="imgfix">&nbsp; <a href="google_map_users.php"><?php echo $lang['control_center_386'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>coins.png" class="imgfix">&nbsp; <a href="user_api_tokens.php"><?php echo $lang['control_center_245'] ?></a><br/>
						</span>
					<div style="clear: both;padding-bottom:6px;margin:0 -6px 3px;border-bottom:1px solid #ddd;"></div>
					<!-- Projects -->
					<b style="position:relative;"><?php echo $lang['control_center_134'] ?></b><br/>
						<span style="position: relative; float: left; left: 4px;">
							<img src="<?php echo APP_PATH_IMAGES ?>folders_stack.png" class="imgfix">&nbsp; <a href="view_projects.php"><?php echo $lang['control_center_110'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>folder_pencil.png" class="imgfix">&nbsp; <a href="edit_project.php"><?php echo $lang['control_center_4396'] ?></a><br/>
						</span>
					<div style="clear: both;padding-bottom:6px;margin:0 -6px 3px;border-bottom:1px solid #ddd;"></div>
					<!-- Users -->
					<b style="position:relative;"><?php echo $lang['control_center_132'] ?></b><br/>
						<span style="position: relative; float: left; left: 4px;">
							<img src="<?php echo APP_PATH_IMAGES ?>users3.png" class="imgfix">&nbsp; <a href="view_users.php"><?php echo $lang['control_center_109'] ?></a><br/>
							<?php if (in_array($auth_meth_global, array('none', 'table', 'ldap_table'))) { ?><img src="<?php echo APP_PATH_IMAGES ?>user_add3.png" class="imgfix">&nbsp; <a href="create_user.php"><?php echo $lang['control_center_4426'] ?></a><br/><?php } ?>
							<img src="<?php echo APP_PATH_IMAGES ?>super_user.png" class="imgfix">&nbsp; <a href="superusers.php"><?php echo $lang['control_center_35'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>user_list.png" class="imgfix">&nbsp; <a href="user_white_list.php"><?php echo $lang['control_center_162'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>email_go.png" class="imgfix">&nbsp; <a href="email_users.php"><?php echo $lang['email_users_02'] ?></a><br/>
						</span>
					<div style="clear: both;padding-bottom:6px;margin:0 -6px 3px;border-bottom:1px solid #ddd;"></div>
					<!-- Misc modules -->
					<b style="position:relative;"><?php echo $lang['control_center_4399'] ?></b><br/>
						<span style="position: relative; float: left; left: 4px;">
							<img src="<?php echo APP_PATH_IMAGES ?>application_link.png" class="imgfix">&nbsp; <a href="external_links_global.php"><?php echo $lang['extres_55'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>newspaper_arrow.png" class="imgfix">&nbsp; <a href="pub_matching_settings.php"><?php echo $lang['control_center_4370'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>databases_arrow.png" class="imgfix">&nbsp; <a href="ddp_settings.php"><?php echo $lang['ws_63'] ?></a><br/>
						</span>
					<div style="clear: both;padding-bottom:6px;margin:0 -6px 3px;border-bottom:1px solid #ddd;"></div>
					<!-- Technical / Developer Tools -->
					<b style="position:relative;"><?php echo $lang['control_center_442'] ?></b><br/>
						<span style="position: relative; float: left; left: 4px;">
							<img src="<?php echo APP_PATH_IMAGES ?>database_table.png" class="imgfix">&nbsp; <a href="mysql_dashboard.php"><?php echo $lang['control_center_4457'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>computer.png" class="imgfix">&nbsp; <a href="<?php echo APP_PATH_WEBROOT_PARENT ?>api/help/index.php"><?php echo $lang['control_center_445'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>plug.png" class="imgfix">&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>Plugins/index.php"><?php echo $lang['control_center_4435'] ?></a><br/>
						</span>
					<div style="clear: both;padding-bottom:6px;margin:0 -6px 3px;border-bottom:1px solid #ddd;"></div>
					<!-- System Configuration -->
					<b style="position:relative;"><?php echo $lang['control_center_131'] ?></b><br/>
						<span style="position: relative; float: left; left: 4px;">
							<img src="<?php echo APP_PATH_IMAGES ?>view-task.png" class="imgfix">&nbsp; <a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/check.php"><?php echo $lang['control_center_443'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>table_gear.png" class="imgfix">&nbsp; <a href="general_settings.php"><?php echo $lang['control_center_125'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>key.png" class="imgfix">&nbsp; <a href="security_settings.php"><?php echo $lang['control_center_113'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>group_gear.png" class="imgfix">&nbsp; <a href="user_settings.php"><?php echo $lang['control_center_315'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>upload.png" class="imgfix">&nbsp; <a href="file_upload_settings.php"><?php echo $lang['system_config_214'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>brick.png" class="imgfix">&nbsp; <a href="modules_settings.php"><?php echo $lang['control_center_114'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>validated.png" class="imgfix">&nbsp; <a href="validation_type_setup.php"><?php echo $lang['control_center_150'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>home_pencil.png" class="imgfix">&nbsp; <a href="homepage_settings.php"><?php echo $lang['control_center_4397'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>star.png" class="imgfix">&nbsp; <a href="project_templates.php"><?php echo $lang['create_project_79'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>folder_plus.png" class="imgfix">&nbsp; <a href="project_settings.php"><?php echo $lang['control_center_136'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>bottom_arrow.png" class="imgfix">&nbsp; <a href="footer_settings.php"><?php echo $lang['control_center_4398'] ?></a><br/>
							<img src="<?php echo APP_PATH_IMAGES ?>clock_frame.png" class="imgfix">&nbsp; <a href="cron_jobs.php"><?php echo $lang['control_center_287'] ?></a><br/>
						</span>
					<div style="clear: both;padding-bottom:6px;margin:0 -6px;border-bottom:0;"></div>
				</div>
			</td>
			<td valign="top" style="padding-left:20px;">
