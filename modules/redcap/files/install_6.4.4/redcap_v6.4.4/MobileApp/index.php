<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (!$api_enabled) redirectHome();

// Remove this line for production rollout
if (!(isDev() || isVanderbilt())) redirectHome();




// display the page
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
?>
<script type='text/javascript'>
function openAppCodeDialog() {
	var title = "<img src='"+app_path_images+"redcap_app_icon.gif' style='vertical-align:middle;'> " + 
				"<span style='vertical-align:middle;'>"+$('#app_codes_dialog').attr('title')+"</span>";
	simpleDialog(null,title,'app_codes_dialog',550);
	$('#app_codes_dialog').attr('title','');
}
function getAppCode() {
	$('#app_user_codes_div').show();
	$('#app_user_codes_timer_div').hide();
	var val = "" + Math.floor(Math.random() * 10000);
	while (val.length < 4) val = "0" + val;	
	// Get init code from Vanderbilt server
	$.get(app_path_webroot + "API/project_api_ajax.php?pid="+pid, { action: "getAppCode", user_code: val }, function (data) {
		$('#app_code').val(data+val).effect('highlight',{ },3000);
		// After X minutes of being displayed,
		setTimeout(function(){
			$('#app_user_codes_div').hide();
			$('#app_user_codes_timer_div').show();
		},600000); // 10 minutes
	});
}
function validateAppCode(validation_code) {
	$.ajax({
        type: 'POST',
        url: 'https://redcap.vanderbilt.edu/consortium/app/validate_code.php',
        crossDomain: true,
        data: { validation_code: validation_code },
        success: function(data) {
			var json_data = jQuery.parseJSON(data);
			// Get REDCap base URL, API token, project ID, and error message (if returns an error)
			var error_msg = json_data.error;
			var redcap_url = json_data.url;
			var redcap_api_token = json_data.token;
			var redcap_project_id = json_data.project_id;
			var redcap_project_title = json_data.project_title;
			var redcap_api_username = json_data.username;
			// Check for error
			if (error_msg != '') {
				alert(error_msg);
				return;
			}
			// Display
			$('#app_redcap_url').html(redcap_url);
			$('#app_redcap_token').html(redcap_api_token);
			$('#app_redcap_username').html(redcap_api_username);
			$('#app_redcap_project_id').html(redcap_project_id);
			$('#app_redcap_project_title').html(redcap_project_title);
        },
        error: function(e) {
            alert("ERROR: "+e.status+": "+e.statusText);
        }
    });
}
</script>
<?php

// If user is going to initialization tab but has init'd the project in the app, then take them to Dashboard instead
if (!isset($_GET['files']) && !isset($_GET['dashboard']) && !isset($_GET['init'])) {
	// If has app activity, then send to dashboard tab, else send to init tab
	if (MobileApp::userHasInitializedProjectInApp(USERID, PROJECT_ID)) {
		redirect(PAGE_FULL."?pid=$project_id&dashboard=1");
	} else {
		redirect(PAGE_FULL."?pid=$project_id&init=1");
	}
}

// Title
renderPageTitle(RCView::img(array('src' => 'redcap_app_icon.gif','class'=>'imgfix2')) . $lang['global_118']);

// TABS
$tabs = array();
$tabs['MobileApp/index.php?init=1'] = 	RCView::img(array('src'=>'phone.png', 'style'=>'vertical-align:middle;')) . 
								RCView::span(array('style'=>'vertical-align:middle;'), $lang['mobile_app_37']);
$tabs['MobileApp/index.php?dashboard=1'] = 	RCView::img(array('src'=>'table.png', 'style'=>'vertical-align:middle;')) . 
											RCView::span(array('style'=>'vertical-align:middle;'), $lang['mobile_app_02']);
$tabs['MobileApp/index.php?files=1'] = 	RCView::img(array('src'=>'page_white_stack.png', 'style'=>'vertical-align:middle;')) . 
										RCView::span(array('style'=>'vertical-align:middle;'), $lang['mobile_app_01']);
renderTabs($tabs);	


## DASHBOARD
if (isset($_GET['dashboard']))
{
	// Render instructions and table of all log entries for app for this project
	print 	RCView::div(array('class'=>'p', 'style'=>'margin-bottom:20px;'),
				$lang['mobile_app_24']
			) .
			MobileApp::displayAppActivityTable(PROJECT_ID);
}


## FILE ARCHIVE
elseif (isset($_GET['files']))
{
	// Render instructions and table
	print 	RCView::div(array('class'=>'p', 'style'=>'margin-bottom:20px;'),
				$lang['mobile_app_20']
			) .
			MobileApp::displayAppFileArchiveTable(PROJECT_ID);
}


## INITIALIZE PROJECT
else
{
	// Display init project page
	print MobileApp::displayInitPage();
}


// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';