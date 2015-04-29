<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';


//Required files
require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';
require_once APP_PATH_DOCROOT  . 'Surveys/survey_functions.php';
// Include Calculate class
require_once APP_PATH_CLASSES . "Calculate.php";
$cp = new Calculate();
// Include BranchingLogic class
require_once APP_PATH_CLASSES . "BranchingLogic.php";
$bl = new BranchingLogic();

// Determine if being viewed from Mobile directory
$MobileSite = (strpos(PAGE, "Mobile/") !== false) ? '1' : '0';

// Initialize DAGs, if any are defined
$Proj->getGroups();


// FAILSAFE: If user was submitting data on form and somehow the auth session ends before it's supposed to, 
// take posted data, encrypt it, and carry it over after new login.
if (isset($_POST['redcap_login_post_encrypt_e3ai09t0y2'])) 
{
	$post_temp = unserialize(decrypt($_POST['redcap_login_post_encrypt_e3ai09t0y2']));
	if (is_array($post_temp)) 
	{
		// Replace login post values with submitted data values
		$_POST = $post_temp;
		unset($post_temp);
	}
}

// Alter how records are saved if project is Double Data Entry (i.e. add --# to end of Study ID)
$entry_num = ($double_data_entry && $user_rights['double_data'] != 0) ? "--".$user_rights['double_data'] : "";


// Set and clean the record name ($fetched)
if (isset($_POST['submit-action'])) {
	$fetched = strip_tags(label_decode($_POST[$table_pk]));
} elseif (isset($_GET['id'])) {
	$fetched = $_GET['id'] = strip_tags(label_decode($_GET['id']));
}

	
// Check if event_id exists in URL. If not, then this is not "longitudinal" and has one event, so retrieve event_id.
if (!isset($_GET['event_id']) || $_GET['event_id'] == "" || !is_numeric($_GET['event_id'])) 
{
	$_GET['event_id'] = getSingleEvent(PROJECT_ID);
}

// Ensure the event_id belongs to this project, and additionally if longitudinal, can be used with this form
if (!$Proj->validateEventId($_GET['event_id']) 
	// Check if form has been designated for this event
    || !$Proj->validateFormEvent($_GET['page'], $_GET['event_id'])
	// Reload page if event_id is not numeric or if id is a blank value
	|| (isset($_GET['id']) && trim($_GET['id']) == "") )
{
	if ($longitudinal) {
		redirect(APP_PATH_WEBROOT . "DataEntry/grid.php?pid=" . PROJECT_ID);
	} else {
		redirect(APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID . "&page=" . $_GET['page']);
	}
}

	
// Auto-number logic (pre- and post-submission of new record)
if ($auto_inc_set) 
{
	// If the auto-number record submitted/selected has already been created by another user, fetch the next one to prevent overlapping data
	if ((!isset($_POST['submit-action']) && isset($_GET['id']) && isset($_GET['auto'])) 
		|| (isset($_POST['submit-action']) && (substr($_POST['submit-action'], 0, 11) == 'submit-btn-' || substr($_POST['submit-action'], 0, 5) == 'save-')
			&& $_POST['hidden_edit_flag'] == 0)) 
	{
		if (recordExists($fetched)) {
			// Record already exists, so generate the next one
			$fetched = getAutoId();
			if (isset($_POST['submit-action'])) {
				// Change submitted record value
				$_POST[$table_pk] = $fetched;
			} else {
				// If record already exists, redirect to new page with this new record value
				redirect(PAGE_FULL . "?pid=$project_id&page={$_GET['page']}&event_id={$_GET['event_id']}&id=$fetched&auto=1");
			}
		}
	}
}

// Collect all form names usable for this Event in an array for later use
$all_forms  = $Proj->eventsForms[$_GET['event_id']];
$first_form = $all_forms[0];
$last_form  = $all_forms[count($all_forms)-1];


// Set up context messages to users for actions performed
$context_msg_update = "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> <span class='notranslate'>" . strip_tags(label_decode($table_pk_label)) . "</span> <b>$fetched</b> {$lang['data_entry_08']}</div>";
$context_msg_insert = "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png' class='imgfix'> <span class='notranslate'>" . strip_tags(label_decode($table_pk_label)) . "</span> <b>$fetched</b> {$lang['data_entry_09']}</div>";
$context_msg_delete = "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <span class='notranslate'>" . strip_tags(label_decode($table_pk_label)) . "</span> <b>$fetched</b> {$lang['data_entry_10']}</div>";
$context_msg_cancel = "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <span class='notranslate'>" . strip_tags(label_decode($table_pk_label)) . "</span> <b>$fetched</b> {$lang['data_entry_11']}</div>";
$context_msg_edit   = "<div class='blue'><img src='".APP_PATH_IMAGES."pencil.png' class='imgfix'> {$lang['data_entry_12']} <span class='notranslate'>" . strip_tags(label_decode($table_pk_label)) . "</span> <b>$fetched</b></div>";
$context_msg_add    = "<div class='darkgreen'><img src='".APP_PATH_IMAGES."add.png' class='imgfix'> {$lang['data_entry_14']} <span class='notranslate'>" . strip_tags(label_decode($table_pk_label)) . "</span> <b>$fetched</b></div>";
$context_msg_error_existing = "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <span class='notranslate'>" . strip_tags(label_decode($table_pk_label)) . "</span> <b>$fetched</b> {$lang['data_entry_08']}<br/><b>{$lang['data_entry_13']} <span class='notranslate'>" . strip_tags(label_decode($table_pk_label)) . "</span> {$lang['data_entry_15']}</b></div>";




################################################################################
# FORM WAS SUBMITTED - PROCESS RESULTS
if (isset($_POST['submit-action'])) 
{

	// ScrollTop setting for "Save and Continue" to scroll page to position
	$scrollTop = null;
	if (isset($_POST['scroll-top']) && is_numeric($_POST['scroll-top'])) {
		$scrollTop = $_POST['scroll-top'];
		unset($_POST['scroll-top']);
	}

	// ScrollTop setting for "Save and Continue" to scroll page to position
	$openDDP = null;
	if (isset($_POST['open-ddp'])) {
		$openDDP = $_POST['open-ddp'];
		unset($_POST['open-ddp']);
	}
	
	// Convert a normal "Save" into a "Save and Continue" so that form gets reloaded
	if (isset($_POST['save-and-continue'])) {
		unset($_POST['save-and-continue']);
		$_POST['submit-action'] = 'submit-btn-savecontinue';
	}
	
	// Process "Save and Redirect" so that page gets redirected
	if (isset($_POST['save-and-redirect'])) {
		$redirectUrl = $_POST['save-and-redirect'];
		unset($_POST['save-and-redirect']);
		$_POST['submit-action'] = 'save-and-redirect';
	}
	
	// DATA QUALITY DRW: If passed hidden field to reload DRW pop-up, get it and remove from Post
	$dqresfld = null;
	if (isset($_POST['dqres-fld']) && isset($Proj->metadata[$_POST['dqres-fld']])) {
		$dqresfld = $_POST['dqres-fld'];
		unset($_POST['dqres-fld']);
	}
	
	// Check for REQUIRED FIELDS: First, check for any required fields that weren't entered (checkboxes are ignored - cannot be Required)
	checkReqFields($fetched);
	
	switch ($_POST['submit-action']) 
	{		
		//SAVE RECORD
		case 'submit-btn-savecompresp':
			// Check if user has rights to do this (just in case)
			if ($enable_edit_survey_response && $user_rights['forms'][$_GET['page']] == '3') {
				// Form Status = Complete
				$_POST[$_GET['page'].'_complete'] = '2'; 
			} else {
				// Modify this
				$_POST['submit-action'] == 'submit-btn-saverecord';
			}
			
		case 'submit-btn-saverecord':
		case 'submit-btn-savecontinue':
		case 'submit-btn-savenextform':
		case 'save-and-redirect':
			
			// Set this survey response as complete in the surveys_response table
			if ($_POST['submit-action'] == "submit-btn-savecompresp") 
			{
				$sql = "update redcap_surveys_participants p, redcap_surveys_response r 
						set r.completion_time = '".NOW."' where p.survey_id = ".$Proj->forms[$_GET['page']]['survey_id']."
						and p.event_id = " . getEventId() . " and p.participant_id = r.participant_id
						and r.record = '" . prep($fetched) . "'";
				db_query($sql);
			}
			
			//Save the submitted data
			list ($fetched, $context_msg, $log_event_id) = saveRecord($fetched);

			// REDCap Hook injection point: Pass project_id and record name to method
			$group_id = (empty($Proj->groups)) ? null : Records::getRecordGroupId(PROJECT_ID, $fetched);
			if (!is_numeric($group_id)) $group_id = null;
			Hooks::call('redcap_save_record', array(PROJECT_ID, $fetched, $_GET['page'], $_GET['event_id'], $group_id, null, null));
			
			/**
			 * SET UP DATA QUALITY RUNS TO RUN IN REAL TIME WITH ANY DATA CHANGES ON FORM
			 */
			$dq_error_ruleids = '';
			// Obtain array of all user-defined DQ rules
			$dq = new DataQuality();
			// Check for any errors and return array of DQ rule_id's for those rules that were violated
			list ($dq_errors, $dq_errors_excluded) = $dq->checkViolationsSingleRecord($fetched, $_GET['event_id'], $_GET['page']);
			// If rules were violated, reload page and then display pop-up message about discrepancies
			if (!empty($dq_errors)) {
				// Build query string parameter
				$dq_error_ruleids = '&dq_error_ruleids=' . implode(",", array_merge($dq_errors, $dq_errors_excluded));
				// Set flag to reload the page
				$_POST['submit-action'] = "submit-btn-savecontinue";
			}

			//Adjust context_msg text if a Double Data Entry user
			$fetched_msg = ($entry_num == "") ? $fetched : substr($fetched, 0, -3);
			
			// Redirect to specified page
			if ($_POST['submit-action'] == "save-and-redirect" && !empty($redirectUrl)) 
			{
				redirect($redirectUrl);
			}
			// Redirect back to same page if user clicked "Save and Continue" button
			if ($_POST['submit-action'] == "submit-btn-savecontinue") 
			{
				redirect(PAGE_FULL . "?pid=$project_id&page={$_GET['page']}&id=$fetched_msg&event_id={$_GET['event_id']}" . (isset($_GET['child']) ? "&child=".$_GET['child'] : "") . ((isset($_GET['editresp']) && $_GET['editresp']) ? "&editresp=1" : "") . (empty($scrollTop) ? '' : "&scrollTop=$scrollTop") . (empty($openDDP) ? '' : "&openDDP=1") . $dq_error_ruleids);
			}
			// Redirect back to same page AND pop-up the Data Resolution Workflow dialog
			elseif ($dqresfld !== null) 
			{
				redirect(PAGE_FULL . "?pid=$project_id&page={$_GET['page']}&id=$fetched_msg&event_id={$_GET['event_id']}" . (isset($_GET['child']) ? "&child=".$_GET['child'] : "") . ((isset($_GET['editresp']) && $_GET['editresp']) ? "&editresp=1" : "") . (empty($scrollTop) ? '' : "&scrollTop=$scrollTop") . "&fldfocus=$dqresfld&dqresfld=$dqresfld");
			}
			// If in a longitudinal project in non-mobile view if user clicked "Save Record" button, redirect back to 
			elseif (($_POST['submit-action'] == 'submit-btn-saverecord' || $_POST['submit-action'] == 'submit-btn-savecompresp') 
				&& $longitudinal && PAGE == 'DataEntry/index.php') 
			{
				$msg = ($_POST['hidden_edit_flag']) ? 'edit' : 'add';
				if ($_POST['hidden_edit_flag'] && isset($_POST['__rename_failed__'])) $msg = '__rename_failed__';
				redirect(APP_PATH_WEBROOT . "DataEntry/grid.php?pid=$project_id&id=$fetched_msg" . ($multiple_arms ? "&arm=".getArm() : "") . "&msg=$msg");
			}	
			// If in a longitudinal project in mobile view if user clicked "Save Record" button, redirect back to 
			elseif ($_POST['submit-action'] == 'submit-btn-saverecord' && $longitudinal && PAGE == 'Mobile/data_entry.php') 
			{
				$msg = ($_POST['hidden_edit_flag']) ? 'edit' : 'add';
				redirect(APP_PATH_WEBROOT . "Mobile/choose_record.php?pid=$project_id&id=$fetched_msg&event_id={$_GET['event_id']}" . ($multiple_arms ? "&arm=".getArm() : "") . "&msg=$msg");
			}		
			//Redirect to the next form if user clicked "Save and go to Next Form"
			elseif ($_POST['submit-action'] == "submit-btn-savenextform") 
			{
				$next_form = getNextForm($_GET['page'], $_GET['event_id']);
				redirect(PAGE_FULL . "?pid=$project_id&page=$next_form&id=$fetched_msg&event_id={$_GET['event_id']}" . (isset($_GET['child']) ? "&child=".$_GET['child'] : ""));
			}
			
			break;
		
		//DELETE RECORD
		case 'submit-btn-delete':
			deleteRecord($fetched.$entry_num);
			// For mobile view in longitudinal projects, redirect back to choose record page
			if ($longitudinal && PAGE == 'Mobile/data_entry.php') 
			{
				redirect(APP_PATH_WEBROOT . "Mobile/choose_record.php?pid=$project_id" . ($multiple_arms ? "&arm=".getArm() : "") . "&msg=delete&iddelete=$fetched");
			}	
			$context_msg = $context_msg_delete;
			unset($fetched);
			break;
			
		//CANCEL
		case 'submit-btn-cancel':
			// If multiple Events exist, redirect back to Grid for this record
			if ($longitudinal) {
				redirect(APP_PATH_WEBROOT . "DataEntry/grid.php?pid=$project_id&id=$fetched&arm=" . getArm() . "&msg=cancel");
			}
			$context_msg = $context_msg_cancel;
			unset($fetched);
			break;
			
	}

}



//If project has been marked as OFFLINE in Control Center, then redirect to index.php now that data has been saved first.
if ($delay_kickout) 
{
	redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");
}








//Make sure "page" url variable exists, else redirect to index page
if (!isset($_GET['page']) || $_GET['page'] == "" || preg_match("/[^a-z_0-9]/", $_GET['page'])) 
{
	redirect(APP_PATH_WEBROOT . "index.php?pid=" . $project_id);
}

// Is this form designated for use as a survey?
$setUpAsSurvey = (isset($Proj->forms[$_GET['page']]['survey_id']));




################################################################################
# PAGE HEADER
if (!$MobileSite) include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Check if data entry has been disabled for Mobile REDCap server. If so, prevent page from loading.
if ($mobile_project == "2" && $disable_data_entry) 
{
	?>
	<div class="red" style="padding:15px;">
		<b><?php echo $lang['data_entry_40'] ?></b><br/><br/>
		<?php echo $lang['data_entry_41'] ?>
	</div>
	<?php
	if (!$MobileSite) include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	exit;
}


// Page header and title
if (!$MobileSite)
{
	$formMenuAppend = $modifyInstBtn = $shareInstBtn = "";
	// PROMIS: Determine if instrument is a PROMIS instrument downloaded from the Shared Library
	$isPromisInstrument = PROMIS::isPromisInstrument($_GET['page']);
	// Add button to edit the form if in Development (takes user to Online Designer)
	if ($status < 1 && isset($_GET['id']) && $user_rights['design'] && !$isPromisInstrument)
	{
		$modifyInstBtn = RCView::button(array('onclick'=>"window.location.href=app_path_webroot+'Design/online_designer.php?pid=$project_id&page={$_GET['page']}';", 'class'=>'jqbuttonmed'), 
							RCView::img(array('src'=>'blog_pencil.png', 'style'=>'vertical-align:middle;position:relative;top:-1px;')) .
							RCView::span(array('style'=>'vertical-align:middle;color:#444;'), $lang['data_entry_202'])
						 );
	}
	// Add button to share this instrument to the Shared Library (allow Brenda Minor to always see the Share Instrument button)
	if (($status > 0 && $user_rights['design'] && $shared_library_enabled) || (isVanderbilt() && USERID == 'minorbl'))
	{
		// Don't allow to share if currently in Draft Mode (give notice if so)
		if ($draft_mode > 0 && !(isVanderbilt() && SUPER_USER)) {
			$shareThisInstAction = "alert('".cleanHtml($lang['global_03']).'\n'.cleanHtml($lang['setup_71']." ".$lang['data_entry_129'])."');";
		} else {
			$shareThisInstAction = "window.location.href=app_path_webroot+'SharedLibrary/index.php?pid=$project_id&page={$_GET['page']}';";
		}
		$shareInstBtn = RCView::button(array('onclick'=>$shareThisInstAction, 'class'=>'jqbuttonmed'), 
							RCView::img(array('src'=>'share.png', 'style'=>'vertical-align:middle;position:relative;top:-1px;')) .
							RCView::span(array('style'=>'vertical-align:middle;color:#444;'), $lang['data_entry_201'])
						);
	}
	// Display chain link image if this is a child or parent from during project linking	
	if (isset($_GET['child'])) 
	{
		$formMenuAppend = "&nbsp;<img src='".APP_PATH_IMAGES."link.png' class='imgfix' title='".cleanHtml($lang['bottom_26'])."'>";
	}
	// Set language
	$pdfDownloadSurveyFormText = (isset($Proj->forms[$_GET['page']]['survey_id'])) ? $lang['data_entry_133'] : $lang['data_entry_132'];
	$pdfDownloadSurveyFormText2 = ($surveys_enabled) ? $lang['data_entry_136'] : $lang['data_entry_135'];
	// Set data entry form header text
	print	RCView::div(array('style'=>'max-width:700px;margin-top:-18px;'),
				RCView::div(array('style'=>'text-align:right;padding:0 5px 5px 0;'), 
					// VIDEO link
					RCView::img(array('src'=>'video_small.png', 'class'=>'imgfix')) .
					RCView::a(array('href'=>'javascript:;', 'style'=>'font-weight:normal;text-decoration:underline;', 'onclick'=>"window.open('".CONSORTIUM_WEBSITE."videoplayer.php?video=data_entry_overview_01.flv&referer=".SERVER_NAME."&title=Overview of Basic Data Entry','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');"), 
						"{$lang['global_80']} {$lang['data_entry_200']}"
					)
				) .
				RCView::div(array('style'=>''), 
					// "Actions:" text
					RCView::span(array('style'=>'color:#777;margin-right:6px;'), $lang['edit_project_29']) .
					// Modify Instrument button (if displayed)
					$modifyInstBtn .
					// PDF button
					RCView::button(array('id'=>'pdfExportDropdownTrigger', 'onclick'=>"showBtnDropdownList(this,event,'pdfExportDropdownDiv');", 'class'=>'jqbuttonmed'), 
						RCView::img(array('src'=>'pdf.gif', 'style'=>'vertical-align:middle;position:relative;top:-1px;')) .
						RCView::span(array('style'=>'vertical-align:middle;color:#800000;'), $lang['data_export_tool_158']) . 
						RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:2px;vertical-align:middle;position:relative;top:-1px;'))
					) .
					// Share Instrument button (if displayed)
					$shareInstBtn .
					// PDF button/drop-down options (initially hidden)
					RCView::div(array('id'=>'pdfExportDropdownDiv', 'style'=>'display:none;position:absolute;z-index:1000;'),
						RCView::ul(array('id'=>'pdfExportDropdown'), 								
							RCView::li(array(),
								RCView::a(array('href'=>'javascript:;', 'onclick'=>"window.location.href = app_path_webroot+'PDF/index.php?pid='+pid+'&page={$_GET['page']}';"), 
									RCView::img(array('src'=>'pdf.gif', 'class'=>'imgfix')) . 
									"$pdfDownloadSurveyFormText {$lang['data_entry_137']}"
								)
							) .
							(!(isset($_GET['id']) && $hidden_edit && $user_rights['data_export_tool'] > 0) ? '' :
								RCView::li(array(),
									RCView::a(array('href'=>'javascript:;', 'onclick'=>"window.location.href = app_path_webroot+'PDF/index.php?pid='+pid+'&page={$_GET['page']}&id={$_GET['id']}{$entry_num}&event_id={$_GET['event_id']}';"), 
										RCView::img(array('src'=>'pdf.gif', 'class'=>'imgfix')) . 
										"$pdfDownloadSurveyFormText {$lang['data_entry_134']}"
									)
								)
							) .
							((count($Proj->forms) <= 1) ? '' :
								RCView::li(array(),
									RCView::a(array('href'=>'javascript:;', 'onclick'=>"window.location.href = app_path_webroot+'PDF/index.php?pid='+pid+'&all';"), 
										RCView::img(array('src'=>'pdf.gif', 'class'=>'imgfix')) . 
										"$pdfDownloadSurveyFormText2 {$lang['data_entry_137']}"
									)
								) .
								(!(isset($_GET['id']) && $hidden_edit && $user_rights['data_export_tool'] > 0) ? '' :
									RCView::li(array(),
										RCView::a(array('href'=>'javascript:;', 'onclick'=>"window.location.href = app_path_webroot+'PDF/index.php?pid='+pid+'&id={$_GET['id']}{$entry_num}';"), 
											RCView::img(array('src'=>'pdf.gif', 'class'=>'imgfix')) . 
											"$pdfDownloadSurveyFormText2 {$lang['data_entry_134']}"
										)
									)
								)
							)
						)
					)
				) .
				RCView::div(array('style'=>'color:#800000;font-size:16px;font-weight:bold;padding:20px 0 5px;'),
					"<img src='".APP_PATH_IMAGES."blog.png' class='imgfix2'>
					{$Proj->forms[$_GET['page']]['menu']} $formMenuAppend"
				)
			);
	// Javascript
	?>
	<script type="text/javascript">
	$(function(){
		// Initialize button drop-down(s) for top of form
		$('#pdfExportDropdown, #SurveyActionDropDownUl').menu();
		$('#SurveyActionDropDownDiv ul li a').click(function(){
			$('#SurveyActionDropDownDiv').hide();
		});
		$('#pdfExportDropdownDiv ul li a').click(function(){
			$('#pdfExportDropdownDiv').hide();
		});
	});
	</script>
	<?php
}
	

	

//A child project cannot be using the Double Data Entry or Longitudinal modules. They are not compatible. Give warning if it is.
if ($is_child && ($double_data_entry || $longitudinal))
{
	if ($double_data_entry) 					$module = $lang['global_04'];
	if ($longitudinal) 							$module = $lang['data_entry_33'];
	if ($double_data_entry && $longitudinal) 	$module = $lang['global_04'] . ' ' . $lang['global_43'] . ' ' . $lang['data_entry_33'];
	print "<table width=480><tr><td class=\"red\"><font color=#800000><b>{$lang['global_48']}{$lang['colon']}</b><br/>
		{$lang['data_entry_36']} {$lang['data_entry_32']} $module {$lang['data_entry_37']}<br/><br/>
		{$lang['data_entry_38']}</font></td></tr></table>";
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';			
	exit;
}









## RENDERING THE RECORD DROP-DOWNS (RECORD IS NOT SELECTED YET)
if (!isset($_GET['id']))
{
	// Do not allow user to add new record unless on first form or if a child project's first form (when linked to a parent)
	// Do not allow user to add new record if user has Read-Only rights to first form
	// $first_form is the first form in the metadata table
	if ($first_form == $_GET['page'] && $is_child_of == "" && !$auto_inc_set && $user_rights['record_create'] && ($user_rights['forms'][$first_form] == '1' || $user_rights['forms'][$first_form] == '3')) 
	{
		$search_text_label = $lang['data_entry_31'] . " <span class='notranslate'>" . strip_tags(label_decode($table_pk_label)) . "</span>";
		$search_text_header_label = $lang['data_entry_03'] . "<span class='notranslate'>$table_pk_label</span> " . $lang['data_entry_04'];
	}
	
	// Create array to store all Form Status values for this record for this data entry form
	$record_dropdowns = array();
	if ($is_child) {
		//Child project (connected to parent)
		$sql = "(select record, field_name, value from redcap_data where project_id = $project_id 
				and field_name in ('$table_pk', '{$_GET['page']}_complete'))
				union
				(select record, field_name, value from redcap_data where project_id = $project_id_parent and field_name = '$table_pk')";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Exclude blank records
			if (trim($row['record']) == '') continue;
			// Default Form Status value if doesn't have value set yet
			if ($row['field_name'] == $table_pk && !isset($record_dropdowns[$row['record']])) {
				$record_dropdowns[$row['record']]['form_status'] = '0';
			} 
			// Give it real stored value for Form Status
			elseif ($row['field_name'] != $table_pk) {
				$record_dropdowns[$row['record']]['form_status'] = $row['value'];
			}
			// Set record as default label
			$record_dropdowns[$row['record']]['label'] = $row['record'];
		}
		db_free_result($q);
		natcaseksort($record_dropdowns);
	} else {
		// Normal project
		// If using DDE, then set filter logic
		$ddeFilter = ($double_data_entry && $user_rights['double_data'] != 0) ? "ends_with([$table_pk], '--{$user_rights['double_data']}')" : false;
		// Get the data
		$record_dropdowns_getData = Records::getData('array', array(), array($table_pk, "{$_GET['page']}_complete"), array(), $user_rights['group_id'],
										false, false, false, $ddeFilter);
		if (count($record_dropdowns_getData) == 1 && isset($record_dropdowns_getData[''])) $record_dropdowns_getData = array();
		// Loop through records returned and format into specific array
		foreach ($record_dropdowns_getData as $this_record=>$event_attr) {
			// Remove to save memory
			unset($record_dropdowns_getData[$this_record]);
			// Remove --# from record name if using DDE
			if ($ddeFilter !== false) $this_record = substr($this_record, 0, -3);
			// Add to array
			$record_dropdowns[$this_record] = array('form_status'=>$event_attr[$Proj->firstEventId]["{$_GET['page']}_complete"], 
													'label'=>$this_record);
		}
	}
	
	// Count the number of total records in project
	$num_records = ($user_rights['group_id'] != '') ? Records::getRecordCount() : count($record_dropdowns);
	
	// Adjust queries if in a DAG or using DDE
	$group_sql    = ""; 
	$group_sql_r  = "";
	if (!$is_child && $user_rights['group_id'] != "") {
		$group_prequery = prep_implode(array_keys($record_dropdowns));
		$group_sql    = "and record in ($group_prequery)"; 
		$group_sql_r  = "and r.record in ($group_prequery)"; 
	}
	
	// If a SURVEY and surveys are ENABLED, then append timestamp (and identifier, if exists) of all responses to record name in drop-down list of records
	if ($surveys_enabled && isset($Proj->forms[$_GET['page']]['survey_id']))
	{
		$sql = "select distinct r.record, r.first_submit_time, r.completion_time, p.participant_identifier 
				from redcap_surveys_participants p, redcap_surveys_response r, redcap_events_metadata m 
				where survey_id = " . $Proj->forms[$_GET['page']]['survey_id'] . " and r.participant_id = p.participant_id and 
				m.event_id = p.event_id and m.event_id = {$_GET['event_id']} $group_sql_r
				and r.first_submit_time is not null order by r.record, r.completion_time desc";
		$q = db_query($sql);
		// Count responses
		$num_survey_responses = 0;
		// Append timestamp (and identifier, if exists) to record in drop-down
		while ($row = db_fetch_assoc($q)) 
		{
			$row['record'] = removeDDEending($row['record']);
			// Make sure the record doesn't repeat (it really shouldn't though)
			if ($last_resp_rec == $row['record']) continue;
			// Add labels
			if ($row['participant_identifier'] != "") {
				$record_dropdowns[$row['record']]['label'] .= " (" . $row['participant_identifier'] . ")";
			}
			if ($row['completion_time'] == "") {
				$record_dropdowns[$row['record']]['label'] .= " - [not completed]"; // Do not abstruct this language because it appears in exports.
			} else {
				$record_dropdowns[$row['record']]['label'] .= " - " . DateTimeRC::format_ts_from_ymd($row['completion_time']);
			}
			// Set for next loop
			$last_resp_rec = $row['record'];
			// Increment counter
			$num_survey_responses++;
		}
		// Get last response time (either completed response or first submit time of partial response)
		$sql = "select if(first_submit_time>completion_time, first_submit_time, completion_time) as last_response_time 
				from (select max(if(r.first_submit_time is null,0,r.first_submit_time)) as first_submit_time, 
				max(if(r.completion_time is null,0,r.completion_time)) as completion_time 
				from redcap_surveys_participants p, redcap_surveys_response r, redcap_events_metadata m 
				where survey_id = " . $Proj->forms[$_GET['page']]['survey_id'] . " and r.participant_id = p.participant_id 
				and m.event_id = p.event_id and m.event_id = " . $_GET['event_id'] . ") as x";
		$q = db_query($sql);
		$last_response_time = $lang['data_entry_119']; // default value (i.e. no responses yet)
		if (db_num_rows($q) > 0) {
			$last_response_time_temp = db_result($q, 0);
			if (!empty($last_response_time_temp))
			{
				$last_response_time = DateTimeRC::format_ts_from_ymd($last_response_time_temp);
			}
		}
	}

	
	// Obtain custom record label & secondary unique field labels for ALL records.
	$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords(array_keys($record_dropdowns), true, getArm());
	foreach ($extra_record_labels as $this_record=>$this_label) {
		// Remove ending for DDE users
		if ($entry_num != '') $this_record = substr($this_record, 0, -3);
		// Add to array
		$record_dropdowns[$this_record]['label'] .= " $this_label";
	}
	unset($extra_record_labels);
	
	
	// Custom record ordering is set
	if ($order_id_by != "" && $order_id_by != $table_pk) 
	{
		$ordered_arr = array();
		// Check if ordering records by field in a Parent project if using Parent/Child linking
		if ($is_child) {
			$this_project_id = ($is_child ? $project_id_parent : PROJECT_ID);
			$this_event_id	 = ($is_child ? "" : "and event_id = {$_GET['event_id']}");
			// If order_id_by is specified, then order by that field's values (and then also by record name)
			$sql = "select distinct record from redcap_data where field_name = '$order_id_by' $group_sql and project_id = $this_project_id 
					$this_event_id and record != '' order by abs(value), value, abs(record), record";
			$q = db_query($sql);
			while($row = db_fetch_assoc($q)) 
			{
				//if ($row['value'] == '') continue;
				// Add record label and form status to newly ordered array and remove from previous
				$ordered_arr[$row['record']]['label'] = $record_dropdowns[$row['record']]['label'];
				$ordered_arr[$row['record']]['form_status'] = $record_dropdowns[$row['record']]['form_status'];
				// Remove record from $record_dropdowns so we'll know which ones are left over because they did not have a value for this field
				unset($record_dropdowns[$row['record']]);
			}
			db_free_result($q);
			// Loop through any remaining records that did not have a value for this field and add to ordered array
			foreach ($record_dropdowns as $this_record=>$vals) {
				$ordered_arr[$this_record] = $vals;
			}
		}
		else
		{
			// Normal project		
			$orderer_arr_getData = array();
			foreach (Records::getData('array', array(), $order_id_by, $_GET['event_id'], $user_rights['group_id']) as $this_record=>$event_data) {
				$orderer_arr_getData[$this_record] = $event_data[$_GET['event_id']][$order_id_by];
			}
			natcasesort($orderer_arr_getData);
			foreach ($orderer_arr_getData as $this_record=>$this_val) {
				$ordered_arr[$this_record]['label'] = $record_dropdowns[$this_record]['label'];
				$ordered_arr[$this_record]['form_status'] = $record_dropdowns[$this_record]['form_status'];
				// Remove record from $record_dropdowns so we'll know which ones are left over because they did not have a value for this field
				unset($record_dropdowns[$this_record], $orderer_arr_getData[$this_record]);
			}
			// Loop through any remaining records that did not have a value for this field and add to ordered array
			foreach ($record_dropdowns as $this_record=>$vals) {
				$ordered_arr[$this_record] = $vals;
			}
		}
		
		// Now set the ordered record array as the original and destroy the ordered one (no longer needed)
		$record_dropdowns = $ordered_arr;
		unset($ordered_arr);
	}
		
	// Loop through all records and place each into array for each drop-down, based upon form status value
	$record_dropdown1 = array();
	$record_dropdown2 = array();
	$record_dropdown3 = array();
	foreach ($record_dropdowns as $this_record=>$this_val)
	{
		// Set form status
		$this_status = $this_val['form_status'];
		// Replace any commas in the record or label to prevent issues when rendering the drop-down using render_dropdown()
		$this_label = str_replace(",", "&#44;", $this_record) . ", " . str_replace(",", "&#44;", $this_val['label']);
		// Put value in array based upon how many drop-downs are being show
		switch ($show_which_records) 
		{
			// Incomplete & Complete
			case '0':
				if ($this_status == '2') {
					$record_dropdown2[$this_record] = $this_label;
				} else {
					$record_dropdown1[$this_record] = $this_label;
				}
				break;
			// Incomplete, Unverified, & Complete
			case '1':
				if ($this_status == '2') {
					$record_dropdown3[$this_record] = $this_label;
				} elseif ($this_status == '1') {
					$record_dropdown2[$this_record] = $this_label;
				} else {
					$record_dropdown1[$this_record] = $this_label;
				}
				break;
			// All records in one drop-down
			case '2':
				$record_dropdown1[$this_record] = $this_label;
				break;		
		}
	}
		
	// Get extra record count in user's data access group, if they are in one
	if ($user_rights['group_id'] != "") 
	{
		$num_records_group = count($record_dropdowns);
	}
	
	// Remove the original array, as it's no longer needed
	unset($record_dropdowns);
	
	//Decide which pulldowns to display for user to choose Study ID (for single survey projects, use 'responses' instead of 'records')
	switch ($show_which_records) {
		case '0':
			$rs_select1_label = $setUpAsSurvey ? $lang['data_entry_88'] : $lang['data_entry_16'];
			$rs_select2_label = $setUpAsSurvey ? $lang['data_entry_89'] : $lang['data_entry_17'];
			break;
		case '1':
			$rs_select1_label = $setUpAsSurvey ? $lang['data_entry_88'] : $lang['data_entry_16'];
			$rs_select2_label = $setUpAsSurvey ? $lang['data_entry_98'] : $lang['data_entry_23'];
			$rs_select3_label = $setUpAsSurvey ? $lang['data_entry_89'] : $lang['data_entry_17'];
			break;
		case '2':
			$rs_select1_label = ($setUpAsSurvey ? $lang['data_entry_124'] : $lang['data_entry_24'] . " $table_pk_label");
			break;
	}
	
	
	//Show select boxes if appropriate (no subject selected - no 'id' in URL)
	if (!$longitudinal && !$MobileSite) 
	{
		//If this is a parent demographics project, then maintain menu as if this is the child (for continuity)
		$child = (isset($_GET['child']) ? "&child=" . $_GET['child'] : "");
		
		// Set the label for blank drop-down value
		$blankDDlabel = ($setUpAsSurvey ? remBr($lang['data_entry_92']) : remBr($lang['data_entry_91']));		
		
		// If more records than a set number exist, do not render the drop-downs due to slow rendering.
		if ($num_records > $maxNumRecordsHideDropdowns)
		{
			// Unset all the drop-downs
			unset($rs_select1_label);
			unset($rs_select2_label);
			unset($rs_select3_label);
			// If using auto-numbering, then bring back text box so users can auto-suggest to find existing records	.
			// The negative effect of this is that it also allows users to [accidentally] bypass the auto-numbering feature.
			if ($auto_inc_set) {
				$search_text_label = $lang['data_entry_121'] . " ".RCView::escape($table_pk_label);
			}
			// Give extra note about why drop-down is not being displayed
			$search_text_label .= RCView::div(array('style'=>'padding:10px 0 0;font-size:10px;font-weight:normal;color:#555;'), 
									$lang['global_03'] . $lang['colon'] . " " . $lang['data_entry_172'] . " " . 
									User::number_format_user($maxNumRecordsHideDropdowns, 0) . " " . 
									$lang['data_entry_173'] . $lang['period']
								);
		}
		
		// Should we show the auto-number button?
		$showAutoNumBtn = ($_GET['page'] == $first_form && $auto_inc_set);
		
		// If displaying "enter new record" text box, then check if record ID field should have validation
		if (isset($search_text_label))
		{			
			$text_val_string = "";
			if ($Proj->metadata[$table_pk]['element_type'] == 'text' && $Proj->metadata[$table_pk]['element_validation_type'] != '') 
			{
				// Apply validation function to field
				$text_val_string = "if(redcap_validate(this,'{$Proj->metadata[$table_pk]['element_validation_min']}','{$Proj->metadata[$table_pk]['element_validation_max']}','hard','".convertLegacyValidationType($Proj->metadata[$table_pk]['element_validation_type'])."',1)) ";
			}
		}
		
		
		
		// Page instructions and record selection table with drop-downs
		?>
		<p style="margin-bottom:20px;">
			<?php echo $lang['data_entry_95'] ?>
			<?php if ($showAutoNumBtn) echo $lang['data_entry_96'] ?>
			<?php if (isset($search_text_label)) echo $lang['data_entry_97'] ?>
		</p>		
		
		<style type="text/css">
		.data { padding: 7px; width: 350px; }
		</style>
		
		<table class="form_border" style="width:700px;">
		
			<!-- Header displaying record count -->
			<tr>
				<td class="header" colspan="2" style="font-weight:normal;padding:10px 5px;color:#800000;font-size:13px;">
					<?php echo $lang['graphical_view_22'] ?> <b><?php echo User::number_format_user($num_records) ?></b>
					<?php if (isset($num_survey_responses)) { ?>
						&nbsp;/&nbsp; <?php echo $lang['data_entry_102'] ?> <b><?php echo User::number_format_user($num_survey_responses) ?></b>
					<?php } ?>
					<?php if (isset($num_records_group)) { ?>
						&nbsp;/&nbsp; <?php echo $lang['data_entry_104'] ?> <b><?php echo User::number_format_user($num_records_group) ?></b>
					<?php } ?>
					<?php if (isset($last_response_time)) { ?>
						&nbsp;/&nbsp; <?php echo $lang['data_entry_120'] ?> <b><?php echo $last_response_time ?></b>
					<?php } ?>
				</td>
			</tr>
			
			<!-- Context msg (show if saved/deleted a record) -->
			<?php if ($context_msg != "") { ?>
				<tr>
					<td colspan="2" class="context_msg"><?php echo $context_msg ?></td>
				</tr>
			<?php } ?>
			
			<!-- Drop-down list #1 -->
			<?php if (isset($rs_select1_label)) { ?>
				<tr>
					<td class="label" style="width:275px;">
						<?php echo $rs_select1_label ?> &nbsp;<span style="font-weight:normal;color:#800000;">(<span id="record_select1_count"></span>)</span>
					</td>
					<td class="data">
						<select id="record_select1" class="x-form-text x-form-field notranslate" style="padding-right:0;height:22px;" 
							onchange="if(this.value.length>0){window.location.href=app_path_webroot+page+'?pid='+pid+'&page=<?php echo $_GET['page'] . $child ?>&id='+this.value+addGoogTrans();}">	
							<?php echo render_dropdown(implode("\n", $record_dropdown1), "", $blankDDlabel); ?>
						</select>
					</td>
				</tr>
			<?php } ?>
			
			<!-- Drop-down list #2 -->
			<?php if (isset($rs_select2_label)) { ?>
				<tr>
					<td class="label">
						<?php echo $rs_select2_label ?> &nbsp;<span style="font-weight:normal;color:#800000;">(<span id="record_select2_count"></span>)</span>
					</td>
					<td class="data">
						<select id="record_select2" class="x-form-text x-form-field notranslate" style="padding-right:0;height:22px;" 
							onchange="if(this.value.length>0){window.location.href=app_path_webroot+page+'?pid='+pid+'&page=<?php echo $_GET['page'] . $child ?>&id='+this.value+addGoogTrans();}">	
							<?php echo render_dropdown(implode("\n", $record_dropdown2), "", $blankDDlabel); ?>
						</select>
					</td>
				</tr>
			<?php } ?>
			
			<!-- Drop-down list #3 -->
			<?php if (isset($rs_select3_label)) { ?>
				<tr>
					<td class="label">
						<?php echo $rs_select3_label ?> &nbsp;<span style="font-weight:normal;color:#800000;">(<span id="record_select3_count"></span>)</span>
					</td>
					<td class="data">
						<select id="record_select3" class="x-form-text x-form-field notranslate" style="padding-right:0;height:22px;" 
							onchange="if(this.value.length>0){window.location.href=app_path_webroot+page+'?pid='+pid+'&page=<?php echo $_GET['page'] . $child ?>&id='+this.value+addGoogTrans();}">	
							<?php echo render_dropdown(implode("\n", $record_dropdown3), "", $blankDDlabel); ?>
						</select>
					</td>
				</tr>
			<?php } ?>
			
			<!-- Text box for entering new record ids -->
			<?php if (isset($search_text_label)) { ?>
				<tr>
					<td class="label"><?php echo $search_text_label ?></td>
					<td class="data">
						<input type="text" size="30" style="position: relative;" id="inputString" class="x-form-text x-form-field" autocomplete="off">
					</td>
				</tr>
			<?php } ?>
			
			<?php if ($Proj->metadata[$table_pk]['element_type'] != 'text') { ?>
			<!-- Error if first field is NOT a text field -->
				<tr>
					<td colspan="2" class="red"><?php echo RCView::b($lang['global_48'] .$lang['colon']) ." " .$lang['data_entry_180'] . " <b>$table_pk</b> (\"$table_pk_label\")".$lang['period'] ?></td>
				</tr>
			<?php } ?>
			
			<!-- Auto-number button(s) - if option is enabled -->
			<?php if ($showAutoNumBtn) { ?>
				<tr>
					<td class="label">&nbsp;</td>
					<td class="data">
						<!-- New record button -->
						<button onclick="window.location.href=app_path_webroot+page+'?pid='+pid+'&id=<?php echo getAutoId() . "&page=" . $_GET['page'] ?>&auto=1';return false;"><?php echo $lang['data_entry_46'] ?></button>
					</td>
				</tr>
			<?php } ?>
			
		</table>
		
		<script type="text/javascript">
		// Add counts of records next to labels for each record drop-down (count options in the drop-downs to determine)
		if (document.getElementById('record_select1') != null) {
			document.getElementById('record_select1_count').innerHTML = document.getElementById('record_select1').length - 1;
		}
		if (document.getElementById('record_select2') != null) {
			document.getElementById('record_select2_count').innerHTML = document.getElementById('record_select2').length - 1;
		}
		if (document.getElementById('record_select3') != null) {
			document.getElementById('record_select3_count').innerHTML = document.getElementById('record_select3').length - 1;
		}
		
		$(function(){
			// Enable validation and redirecting if hit Tab or Enter
			$('#inputString').keypress(function(e) {
				if (e.which == 13) {
					 $('#inputString').trigger('blur');
					return false;
				}
			});
			$('#inputString').blur(function() {
				var refocus = false;
				var idval = trim($('#inputString').val()); 
				if (idval.length < 1) {
					refocus = true;
					$('#inputString').val('');
				}
				if (idval.length > 100) {
					refocus = true;
					alert('<?php echo cleanHtml($lang['data_entry_186']) ?>'); 
				}
				if (refocus) {
					setTimeout(function(){document.getElementById('inputString').focus();},10);
				} else {
					$('#inputString').val(idval);
					<?php echo $text_val_string ?>
					setTimeout(function(){
						idval = $('#inputString').val();
						idval = idval.replace(/&quot;/g,''); // HTML char code of double quote
						// Don't allow pound signs in record names
						if (/#/g.test(idval)) {
							$('#inputString').val('');
							alert("Pound signs (#) are not allowed in record names! Please enter another record name.");
							$('#inputString').focus();
							return false;
						}
						// Don't allow apostrophes in record names
						if (/'/g.test(idval)) {
							$('#inputString').val('');
							alert("Apostrophes are not allowed in record names! Please enter another record name.");
							$('#inputString').focus();
							return false;
						}
						// Don't allow ampersands in record names
						if (/&/g.test(idval)) {
							$('#inputString').val('');
							alert("Ampersands (&) are not allowed in record names! Please enter another record name.");
							$('#inputString').focus();
							return false;
						}
						// Don't allow plus signs in record names
						if (/\+/g.test(idval)) {
							$('#inputString').val('');
							alert("Plus signs (+) are not allowed in record names! Please enter another record name.");
							$('#inputString').focus();
							return false;
						}
						// Redirect, but NOT if the validation pop-up is being displayed (for range check errors)
						if (!$('.simpleDialog.ui-dialog-content:visible').length)
							window.location.href = app_path_webroot+page+'?pid='+pid+'&page='+getParameterByName('page')+'&id=' + idval + addGoogTrans(); 
					},200);
				}
			});
		});
		</script>
		<?php
	}
	
	## RENDER PAGE INSTRUCTIONS (and any error messages) when not rendering full form
	if (!$MobileSite) 
	{	
		// Build html string to display page instructions
		$page_instructions = "";
		
		if (!$longitudinal) 
		{
			// If user is on last form, don't show the button "Save and go to Next Form"
			if (isset($fetched) && $_GET['page'] != $last_form) {
				$next_form = getNextForm($_GET['page'], $_GET['event_id']);
				print  "<div align='right' style='padding-top:10px;max-width:700px;'>
							<input type='button' onclick='window.location.href=\"".$_SERVER['PHP_SELF']."?pid=$project_id&page=$next_form&id=$fetched\";' value='".cleanHtml($lang['data_entry_175'])." ->' style='font-size:11px;'>
						</div>";
			}
			// Do not show link for single survey projects
			if ($show_which_records == '0') {
				print "<div style='text-align:right;max-width:700px;'><a href='".APP_PATH_WEBROOT."DataEntry/change_record_dropdown.php?pid=$project_id&page={$_GET['page']}&show_which_records=1' style='font-size:10px;text-decoration:underline;'>{$lang['data_entry_25']}</a></div>";
			} elseif ($show_which_records == '1') {
				print "<div style='text-align:right;max-width:700px;'><a href='".APP_PATH_WEBROOT."DataEntry/change_record_dropdown.php?pid=$project_id&page={$_GET['page']}&show_which_records=0' style='font-size:10px;text-decoration:underline;'>{$lang['data_entry_26']}</a></div>";
			}
		
			// Display search utility
			renderSearchUtility();
		}
		
		//Build html string to display LONGITUDINAL info on page after submitting form data	
		else 
		{
			// Display context message
			print $context_msg;
			$arm = getArm();
			$page_instructions =   "<br><span class='yellow' style='padding-right:15px;'>
										{$lang['global_10']}{$lang['colon']} 
										<span style='font-weight:bold;color:#800000;'>{$Proj->eventInfo[$_GET['event_id']]['name_ext']}</span>
									</span>
									<p style='padding:25px 0 20px;color:#666;'>" .
										($_POST['submit-action'] != 'submit-btn-delete' 
											?  "<button class='jqbutton' onclick=\"window.location.href=app_path_webroot+'DataEntry/grid.php?pid=$project_id&page=&arm=$arm&id=$fetched';\">
												<img src='" . APP_PATH_IMAGES . "arrow_left.png' class='imgfix'> {$lang['data_entry_55']} $table_pk_label <b>$fetched</b>
												</button>&nbsp;{$lang['global_46']}&nbsp; "
											:   ""
										) . "
										<button class='jqbutton' onclick=\"window.location.href=app_path_webroot+'DataEntry/grid.php?pid=$project_id';\">
											<img src='" . APP_PATH_IMAGES . "spacer.gif' style='height:16px;width:0px;' class='imgfix'>{$lang['data_entry_112']}
										</button>
									</p>";
		}
		
		
		//Using double data entry and auto-numbering for records at the same time can mess up how REDCap saves each record. 
		//Give warning to turn one of these features off if they are both turned on.
		if ($double_data_entry && $auto_inc_set) {
			$page_instructions .= "<div class='red'><b>{$lang['global_48']}</b><br>{$lang['data_entry_56']}</div>";
		}
		

		//If this is a parent or child project, make sure that the Primary Key of both are the same (otherwise they won't work together)
		if ($is_child) {
			//Display error message if PKs are different
			$child_pk  = $table_pk;
			$parent_pk = db_result(db_query("SELECT field_name FROM redcap_metadata WHERE project_id = $project_id_parent ORDER BY field_order LIMIT 1"),0);
			if ($child_pk != $parent_pk) {
				$page_instructions .= "<table width=480><tr><td class=\"red\"><b>{$lang['data_entry_57']}</b><br><br>
					  {$lang['data_entry_58']} (\"$child_pk\") {$lang['data_entry_59']} (\"$parent_pk\") 
					  {$lang['data_entry_60']} (\"$is_child_of\") {$lang['data_entry_61']} \"<b>$parent_pk</b>\".</td></tr></table>";
			}
			
		} elseif (isset($_GET['child'])) {
			//Display error message if PKs are different
			$parent_pk = $table_pk;
			$child_pk  = db_result(db_query("select m.field_name from redcap_metadata m, redcap_projects p where p.project_name = '{$_GET['child']}' and p.project_id = m.project_id order by field_order limit 1"),0);
			if ($child_pk != $parent_pk) {
				$page_instructions .= "<table width=480><tr><td class=\"red\"><b>{$lang['data_entry_57']}</b><br><br>
					 {$lang['data_entry_58']} (\"$child_pk\") {$lang['data_entry_59']} (\"$parent_pk\")
					  {$lang['data_entry_60']} (\"$app_name\") {$lang['data_entry_61']} \"<b>$parent_pk</b>\".</td></tr></table>";
			}	
		}
		
		//If project is a prototype, display notice for users telling them that no real data should be entered yet.
		if ($status < 1) {
			$page_instructions .=  "<br><br><div class='yellow' style='font-family:arial;width:550px;'>
										<img src='".APP_PATH_IMAGES."exclamation_orange.png' class='imgfix'> 
										<b style='font-size:14px;'>{$lang['global_03']}:</b><br>
										{$lang['data_entry_28']}
									</div>";
		}
		
		//Now render the page instructions (and any error messages)
		print $page_instructions;
		
	}


	## AUTO-COMPLETE: Render JavaScript for record selecting auto-complete/auto-suggest (but only for first form)
	?>
	<script type="text/javascript">
	$(function(){
		if ($('#inputString').length) $('#inputString').autocomplete({ serviceUrl: app_path_webroot+'DataEntry/auto_complete.php?pid='+pid+'&arm=<?php echo getArm() ?>', deferRequestBy: 0 });
	});
	</script>
	<?php

}










## RECORD IS SELECTED: BUILD FORM ELEMENTS
elseif (isset($_GET['id'])) 
{
	// Make sure record name in URL does not have trailing spaces
	$_GET['id'] = trim(urldecode($_GET['id']));	
	
	// ONLY for MOBILE data entry forms, get record information (will have already been gotten in header.php for normal data entry page)
	if (PAGE == "Mobile/data_entry.php") {
		list ($fetched, $hidden_edit, $entry_num) = getRecordAttributes();
	}
	
	// Make sure that there is a case sensitivity issue with the record name. Check value of id in URL with back-end value.
	// If doesn't match back-end case, then reload page using back-end case in URL.
	checkRecordNameCaseSensitive();
	
	// If this record has not been created yet, then do not allow record renaming (doesn't make sense to allow if not even created yet)
	if ($hidden_edit == 0) $user_rights['record_rename'] = 0;

	// Obtain form data for rendering
	list ($elements1, $calc_fields_this_form, $branch_fields_this_form, $chkbox_flds) = buildFormData($_GET['page']);
	
	// For all forms, create static element at top of page
	$elements1 = array_merge(array(array('rr_type'=>'static', 'field'=>$table_pk, 'name'=>'', 'label'=>$table_pk_label)), $elements1);
	
	// Show study_id field as hidden on all forms (unless already displayed as editable field on first form when can rename records)
	if ((!$user_rights['record_rename'] && $_GET['page'] == array_shift(array_keys($Proj->forms))) || $_GET['page'] != array_shift(array_keys($Proj->forms))) 
	{
		$elements1[] = array('rr_type'=>'hidden', 'field'=>$table_pk, 'name'=>$table_pk);
	}
	
	// Set tabindex value for Submit button at bottom of page
	$saveButtonTabIndex = count($Proj->forms[$_GET['page']]['fields']) + 1;
	// Also, loop through all fields on form and increment for EACH checkbox option
	foreach (array_keys($Proj->forms[$_GET['page']]['fields']) as $this_field) {
		if ($Proj->metadata[$this_field]['element_type'] == 'checkbox') {
			$this_chk_enum = $Proj->metadata[$this_field]['element_enum'];
			if ($this_chk_enum != '') {
				$saveButtonTabIndex = $saveButtonTabIndex - 1 + count(parseEnum($this_chk_enum));
			}
		}
	}

	//Custom page header note
	if (trim($custom_data_entry_note) != '') {
		print "<br><div class='green notranslate' style='font-size:11px;'>" . str_replace("\n", "<br>", $custom_data_entry_note) . "</div><br>";
	}

	//Adapt for Double Data Entry module
	if ($entry_num != "") {
		//This is #1 or #2 Double Data Entry person
		$fetched .= $entry_num;
	}
	
	// Check if record exists
	if ($hidden_edit) {
		//This record already exists
		$context_msg = render_context_msg($custom_record_label, $context_msg_edit);
	} else {
		//This record does not exist yet
        $context_msg = render_context_msg("", $context_msg_add);
		//Deny access if user has no create_records rights
		if (!$user_rights['record_create']) 
		{
			print  "<div class='red'>
						<img src='" . APP_PATH_IMAGES . "exclamation.png' class='imgfix'> 
						<b>{$lang['global_05']}</b>
					</div>";
			if (!$MobileSite) include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
			exit;
		}
	}
	
	// Determine if another user is on this form for this record for this project (do not allow the page to load, if so)
	$otherUserOnPage = checkSimultaneousUsers();
	if ($otherUserOnPage !== false)
	{
		// Obtain other user's email/name for display
		$q = db_query("select * from redcap_user_information where username = '" . prep($otherUserOnPage) . "'");
		$otherUserEmail = db_result($q, 0, "user_email");
		$otherUserName = db_result($q, 0, "user_firstname") . " " . db_result($q, 0, "user_lastname");
		// Display msg to user
		print  "<div class='yellow'>
					<div style='margin:10px 0;'>
						<img src='".APP_PATH_IMAGES."exclamation_orange.png' class='imgfix'>
						<b>{$lang['data_entry_77']}</b><br><br>
						{$lang['data_entry_78']} (<b>$otherUserOnPage</b> - <a href='mailto:$otherUserEmail'>$otherUserName</a>) 
						{$lang['data_entry_79']} (<b>{$_GET['id']}</b>){$lang['period']} {$lang['data_entry_80']}
					</div>
					<div id='errconflict' class='brown' style='display:none;margin:10px 0;'>
						{$lang['data_entry_81']} ($autologout_timer {$lang['data_entry_82']}){$lang['data_entry_83']}
					</div>
					<div style='margin:10px;'>					
						<table cellpadding=0 cellspacing=0 width=100%><tr>
						<td>
							<button onclick='window.location.reload();'>{$lang['data_entry_84']}</button>
						</td>
						<td style='text-align:right;'>
							<a href='javascript:;' onclick=\" this.innerHTML=''; $('#errconflict').show('fast');\" style='font-size:11px;'>{$lang['data_entry_85']}</a>
						</td>
						</tr></table>
					</div>
				</div>";
		if (!$MobileSite) include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}
		
	//Set context msg at top of table
	if ($context_msg != "") {
		$elements[] = array('rr_type'=>'header', 'css_element_class'=>'context_msg','value'=>$context_msg);
	}
	
	// Set hidden element that contains the button action being done for post-processing. Set to 'Save Record' as default.
	$elements[] = array('rr_type'=>'hidden', 'id'=>'submit-action', 'name'=>'submit-action', 'value'=>$lang['data_entry_206']);
	
	//If hidden_edit_flag == 1, then this record already exists. If 0, it is a new record.
	$elements[] = array('rr_type'=>'hidden', 'name'=>'hidden_edit_flag', 'value'=>$hidden_edit);
	
	// Primary Form Fields inserted here
	$elements = array_merge($elements, $elements1);
	
	// CALC FIELDS AND BRANCHING LOGIC: Add fields from other forms as hidden fields if involved in calc/branching on this form
	list ($elementsOtherForms, $chkbox_flds_other_forms, $jsHideOtherFormChkbox) = addHiddenFieldsOtherForms($_GET['page'], array_unique(array_merge($branch_fields_this_form, $calc_fields_this_form)));
	$elements 	 = array_merge($elements, $elementsOtherForms);
	$chkbox_flds = array_merge($chkbox_flds, $chkbox_flds_other_forms);
	
	// Don't show locking/e-signature for mobile view
	if (!$MobileSite) 
	{
		// LOCK RECORD FIELD: If user has right to lock a record, show locking field. If user doesn't have right, all fields are disabled, so can't submit and don't show.
		if ($user_rights['lock_record'] > 0) 
		{
			// If custom Locking text is set for this form
			$sql = "select label, display from redcap_locking_labels where project_id = $project_id and form_name = '{$_GET['page']}' limit 1";
			$q = db_query($sql);
			$inLabelTable = (db_num_rows($q) > 0);
			// Only show lock record option if display=1 OR if not in table
			if (($inLabelTable && db_result($q, 0, "display")) || !$inLabelTable) 
			{
				// Default Locking text (when not defined)
				$locklabel = (trim(db_result($q, 0, "label") != "")) 
					? '<div style="color:#A86700;padding:3px;">'.nl2br(db_result($q, 0, "label")).'</div>'
					: '<div style="color:#A86700;">'.$lang['data_entry_47'].'</div><div style="font-size:7pt;padding-top:7px;color:#555">'.$lang['data_entry_48'].'</div>';
				// Add lock record field to form elements
				$elements[] = array('rr_type'=>'lock_record', 'name'=>'__LOCKRECORD__', 'field'=>'__LOCKRECORD__', 'label'=>$locklabel);
			}
		}
		
		// Render buttons at bottom of page
		if ($user_rights['forms'][$_GET['page']] == '2') {
		
			//READ-ONLY MODE SAVE BUTTONS (disabled buttons)
			$elements[] = array('rr_type'=>'button', 'value'=>$lang['data_entry_206'], 'disabled'=>'disabled');
			$elements[] = array('rr_type'=>'button', 'value'=>$lang['data_entry_207'], 'disabled'=>'disabled');
			if ($user_rights['record_delete']) {
				$elements[] = array('rr_type'=>'button', 'value'=>$lang['data_entry_208'], 'disabled'=>'disabled');	
			}
		
		} else {
		
			//NORMAL SAVE BUTTONS
			
			//If user is on last form, don't show the button "Save and go to Next Form"
			$next_form_button = '';
			if ($_GET['page'] != $last_form) {
				$next_form_button = '<br><input type="button" name="submit-btn-savenextform" onclick="dataEntrySubmit(this);return false;" value="'.cleanHtml2($lang['data_entry_210']).'" style="font-size:11px;" tabindex="'.($saveButtonTabIndex+2).'"/>';
			}
			
			// If user has Edit Survey Response rights and is in edit mode, then give new button to make this response listed as complete (if not already)
			$comp_resp_button = '';
			if ($user_rights['forms'][$_GET['page']] == '3' && isset($_GET['editresp']))
			{
				// First, check if response is complete or not. If not, then render button.
				$comp_resp_button = '<br><input type="button" name="submit-btn-savecompresp" onclick="dataEntrySubmit(this);return false;" value="'.cleanHtml2($lang['data_entry_212']).'" style="font-weight:bold;font-size:11px;color:#800000;" tabindex="'.($saveButtonTabIndex+3).'"/>';
			}
			
			//Display SAVE, CANCEL, and DELETE buttons (and possibly hidden Calc fields and possibly "Save and go to Next Form" button)
			$elements[] = array('rr_type'=>'static', 'name'=>'__SUBMITBUTTONS__', 'label'=>'', 
				'value'=>'<div id="__SUBMITBUTTONS__-div">
						  <input type="button" name="submit-btn-saverecord" value="'.cleanHtml2($lang['data_entry_206']).'" onclick="dataEntrySubmit(this);return false;" style="font-weight:bold;" tabindex="'.$saveButtonTabIndex.'"/><br>
						  <input type="button" name="submit-btn-savecontinue" value="'.cleanHtml2($lang['data_entry_209']).'" onclick="dataEntrySubmit(this);return false;" style="font-size:11px;" tabindex="'.($saveButtonTabIndex+1).'"/>
						  '.$next_form_button.$comp_resp_button.'<br><br>
						  </div>');
			$elements[] = array('rr_type'=>'button', 'value'=>$lang['data_entry_207'], 'tabindex'=>($saveButtonTabIndex+4), 'name'=>"submit-btn-cancel", 'onclick'=>'dataEntrySubmit(this);return false;');
			if ($hidden_edit && $user_rights['record_delete']) 
			{
				// Customize prompt message for deleting record button
				$delAlertMsg = $lang['data_entry_188'];
				if ($longitudinal) {
					$delAlertMsg .= " <b>".$lang['data_entry_51'];
					if ($multiple_arms) {
						$delAlertMsg .= " ".$lang['data_entry_52'];
					}
					$delAlertMsg .= $lang['period']."</b>";
				} else {
					$delAlertMsg .= " <b>".$lang['data_entry_189']."</b>";
				}
				$delAlertMsg .= RCView::div(array('style'=>'margin-top:15px;color:#C00000;font-weight:bold;'), $lang['data_entry_190']);
				$elements[] = array('rr_type'=>'button', 'name'=>'submit-btn-delete', 'value'=>$lang['data_entry_208'], 'tabindex'=>($saveButtonTabIndex+4), 
									'onclick'=>"simpleDialog('".str_replace('"', '&quot;', cleanHtml("<div style='margin:10px 0;font-size:13px;'>$delAlertMsg</div>"))."','".str_replace('"', '&quot;', cleanHtml("{$lang['data_entry_49']} \"{$_GET['id']}\"{$lang['questionmark']}"))."',null,null,null,'".cleanHtml($lang['global_53'])."',function(){ dataEntrySubmit( document.getElementsByName('submit-btn-delete')[0] );return false; },'".cleanHtml($lang['data_entry_49'])."');return false;");
									
									//if(confirm('".str_replace('"', '&quot;', $delAlertMsg)."')) dataEntrySubmit(this);return false;");
			}
		}
	}
	
	
	/**
	 * RENDER FORM ELEMENTS or RECORD DROPDOWNS
	*/
	
	//Accomodate double data entry (if needed) by appending data entry number to record id
	if ($double_data_entry && $user_rights['double_data'] != 0) {
		$this_record = $_GET['id'] . "--" . $user_rights['double_data'];
	} else {
		$this_record = $_GET['id'];
	}
	//Build query for pulling existing data to render on top of form
	$datasql = "select field_name, value from redcap_data where	project_id = $project_id and event_id = {$_GET['event_id']} 
				and record = '".prep($this_record)."' and field_name in ('__GROUPID__', ";
	foreach ($elements as $fldarr) {
		if (isset($fldarr['field'])) $datasql .= "'".$fldarr['field']."', ";
	}
	$datasql = substr($datasql, 0, -2) . ")";
	//Execute query and put any existing data into an array to display on form
	$q = db_query($datasql);
	$element_data = array();
	while ($row_data = db_fetch_array($q)) {
		//Checkbox: Add data as array
		if (isset($chkbox_flds[$row_data['field_name']])) {
			$element_data[$row_data['field_name']][] = $row_data['value'];
		//Non-checkbox fields: Add data as string
		} else {
			$element_data[$row_data['field_name']] = $row_data['value'];
		}
	}
	// Add value for record identifier when creating new record
	$element_data[$table_pk] = $_GET['id'];
	
	// If using DAG + Longitudinal and the group_id is not listed for this event (when it exists for at least ONE event for this record), 
	// then query again to get existing Group_ID and save it for this event (because it should be there anyway).
	$dags = $Proj->getGroups();
	if ($longitudinal && !isset($element_data['__GROUPID__']) && !empty($dags)) 
	{
		// Get group_id value for record and insert for this event (but ONLY if the event has SOME data saved for it)
		$datasql = "select value from redcap_data where	project_id = $project_id and record = '".prep($this_record)."' 
					and field_name = '__GROUPID__' and value != '' limit 1";
		$q = db_query($datasql);
		if (db_num_rows($q) > 0) 
		{
			// Add group_id to $element_data so that the DAG drop-down gets pre-selected with this record's DAG
			$element_data['__GROUPID__'] = db_result($q, 0);
			// Only add group_id if ONLY the event has SOME data saved for it
			$sql = "select 1 from redcap_data where project_id = $project_id and event_id = {$_GET['event_id']} 
					and record = '".prep($this_record)."' limit 1";
			$q = db_query($sql);
			if (db_num_rows($q) > 0) { 
				// Add this group_id for this record-event (because it should already be there anyway)
				$sql = "INSERT INTO redcap_data VALUES ($project_id, {$_GET['event_id']}, '".prep($this_record)."', '__GROUPID__', '{$element_data['__GROUPID__']}')";
				db_query($sql);
			}
		}
	}
	
	
	// Set file upload dialog
	initFileUploadPopup(); 
	?>
	
	<style type="text/css">
	#form table.form_border {
		border: 1px solid #DDDDDD;
	}
	#form td.data, td.label, td.data_matrix, td.label_matrix { 
		background:#F3F3F3;
		border:0px;
		border-bottom:1px solid #DDDDDD;
		border-top:0px solid #F3F3F3;
	}
	#form td.header {
		border-left:0;
		border-right:0;
	}
	.resp_users_contribute { cursor:pointer;cursor:hand }
	<?php if (!$MobileSite) { ?>
		#form td.data { width: 310px; }
		#form td.label { width: 390px; }
	<?php } ?>
	</style>
	
	<!-- SECONDARY UNIQUE FIELD JAVASCRIPT -->
	<?php renderSecondaryIdJs() ?>
	
	<script type='text/javascript'>
	// Add hidden_edit/record_exists and record_exists as javascript variables
	var record_exists = <?php echo $hidden_edit ?>;
	var require_change_reason = <?php echo $require_change_reason ?>;
	// Set event_id
	var event_id = <?php echo $_GET['event_id'] ?>;
	// Language items
	var langSaveLeavePage = '<?php echo cleanHtml($lang['data_entry_197']) ?>';
	var langLeavePage = '<?php echo cleanHtml($lang['data_entry_191']) ?>';
	var langStayOnPage = '<?php echo cleanHtml($lang['data_entry_192']) ?>';
	var langDlgSaveDataTitle = '<?php echo cleanHtml($lang['data_entry_193']) ?>';
	var langDlgSaveDataTitleCaps = '<?php echo cleanHtml($lang['data_entry_199']) ?>';
	var langDlgSaveDataMsg = '<?php echo cleanHtml($lang['data_entry_198']) ?>';
	</script>
	<?php
	
	// Hidden dialog to REMIND USER TO SAVE DATA IF TRIES TO LEAVE PAGE
	print 	RCView::div(array('id'=>'stayOnPageReminderDialog', 'class'=>'simpleDialog', 'style'=>'display:none;'), 
				RCView::div(array('style'=>'font-size:13px;padding:8px 30px 8px 18px;line-height:1.5em;'), 
					$lang['data_entry_194'] . " " . RCView::b($lang['data_entry_195']) . " " . $lang['data_entry_196']
				)
			);
	
	// Call JavaScript file
	callJSfile('DataEntry.js');
	
	
	if (!$MobileSite) 
	{
		// Render form
		form_renderer($elements, $element_data);

		// Render fields and their values from other events as separate hidden forms
		if ($longitudinal) {
			print addHiddenFieldsOtherEvents();
		}
		
		// Generate JavaScript equations for Calculated Fields and Branching Logic
		print $cp->exportJS();
		print $bl->exportBranchingJS();
	
		// Print javascript that hides checkbox fields from other forms, which need to be hidden
		print $jsHideOtherFormChkbox;
	}
	
	?>
	
	<!-- Hidden field for checking if a validation error has been thrown. Used to prevent form submission. -->
	<input type="hidden" id="field_validation_error_state" value="0">
	
	<!-- Data history dialog pop-up -->
	<div id="data_history" style="display:none;">
		<p>
			<?php echo $lang['data_entry_66'] ?> "<b id="dh_var"></b>" <?php echo $lang['data_entry_67'] ?>
			<?php echo "$table_pk_label \"<b>" . 
					(($double_data_entry && isset($user_rights) && $user_rights['double_data'] != 0) ? substr($fetched, 0, -3) : $fetched) . 
					"</b>\"{$lang['period']} {$lang['dataqueries_276']}" ?>
		</p>
		<div id="data_history2" style="margin:15px 0px 20px;"></div>	
	</div>
	
	<?php	
	/**
	 * IF REQUIRING "CHANGE REASON" FOR ANY DATA CHANGES
	*/
	if ($require_change_reason) 
	{
		?>
		<!-- Change reason pop-up-->
		<div id="change_reason_popup" title="Please supply reason for data changes" style="display:none;margin-bottom:25px;">
			<p>
				<?php echo $lang['data_entry_68'] ?>
			</p>
			<div style="font-family:arial;font-weight:bold;padding:5px 0;"><?php echo $lang['data_entry_69'] ?></div>
			<!-- Textarea box for reason -->
			<div><textarea id="change_reason" onblur="charLimit('change_reason',200);" class="x-form-textarea x-form-field" style="font-family:arial;width:400px;height:120px;"></textarea></div>
			<!-- Hidden error message -->	
			<div id="change_reason_popup_error" class="red" style="display:none;margin-top:20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png" class="imgfix"> 
				<?php echo $lang['data_entry_70'] ?>
			</div>
		</div>
		<?php
	}
	
	
	/**
	 * FORM LOCKING POP-UP FOR E-SIGNATURE
	 * Only display it if user has rights AND the form is set to display the e-signature
	*/
	if ($user_rights['lock_record'] > 1) 
	{
		// Query table to determine if form is set to display the e-signature
		$sql = "select 1 from redcap_locking_labels where project_id = $project_id 	
				and form_name = '{$_GET['page']}' and display_esignature = 1 limit 1";
		$displayEsigOption = (db_num_rows(db_query($sql)) > 0);
		// Include file for the pop-up to be displayed
		if ($displayEsigOption) {
			include APP_PATH_DOCROOT . "Locking/esignature_popup.php";
		}
	}


	// REQUIRED FIELDS pop-up message (URL variable 'msg' has been passed)
	msgReqFields($fetched, $last_form);
	
	
	// DATA QUALITY RULES pop-up message (URL variable 'dq_error_ruleids' has been passed)
	if (isset($_GET['dq_error_ruleids']))
	{
		$dq = new DataQuality();
		$dq->displayViolationsSingleRecord(explode(",", $_GET['dq_error_ruleids']), $fetched, $_GET['event_id'], $_GET['page']);		
		// Div for pop-up tooltip
		print RCView::div(array('id'=>'dqRteFieldFocusTip', 'class'=>'tooltip4'), 
				$lang['dataqueries_128'] .
				RCView::div(array('style'=>'text-align:center;padding:10px 0 6px;'),
					RCView::button(array('onclick'=>"$('form#form input[name=\"submit-btn-savecontinue\"]').click();"), 
						$lang['data_entry_206']
					)
				)
			  );
	}


	// Put focus on a field if coming from Graphical Data View or have Required Fields not entered
	if (isset($_GET['fldfocus']) && isset($Proj->metadata[$_GET['fldfocus']])) 
	{
		?>
		<script type='text/javascript'>
		$(function() {
			setTimeout(function(){
				try {
					document.form.<?php echo $_GET['fldfocus'] ?>.focus();
				} catch(e) { }
			},500);
		});
		</script>
		<?php
	}
	
	// Floating "Save" button tooltip fixed at top-right of data entry page
	print RCView::div(array('id'=>'formSaveTip', 'class'=>'tooltipFormSave'), "");
	
	// Floating "Save" button tooltip for Data Resolution Workflow (Save + Open DRW dialog)
	print RCView::div(array('id'=>'tooltipDRWsave', 'class'=>'tooltip4left'), "");		
	
	// DATA RESOLUTION WORKFLOW: Auto open popup for given field
	if (isset($_GET['dqresfld']) && isset($Proj->metadata[$_GET['dqresfld']])) 
	{
		?>
		<script type='text/javascript'>
		$(function() {
			$('#dc-icon-<?php echo $_GET['dqresfld'] ?>').click();
		});
		</script>
		<?php
	}
	
	// DATA RESOLUTION WORKFLOW: Render the file upload dialog (when applicable)
	print DataQuality::renderDataResFileUploadDialog();	
	
	// If DDP is enabled AND user has access AND external id field is on page, then trigger popup via jQuery
	$DDP->renderJsAdjudicationPopup($fetched, $_GET['event_id'], $_GET['page']);
	
	// DDP auto open: Auto open popup for DDP after initial record creation
	if (isset($_GET['openDDP']) && $DDP->isEnabledInSystem() && $DDP->isEnabledInProject()) 
	{
		?>
		<script type='text/javascript'>
		$(function() {
			openAdjudicationDialog(getParameterByName('id'));
		});
		</script>
		<?php
	}
	
	// REDCap Hook injection point: Pass project/record/form attributes to method
	$group_id = (empty($Proj->groups)) ? null : Records::getRecordGroupId(PROJECT_ID, $fetched);
	if (!is_numeric($group_id)) $group_id = null;
	Hooks::call('redcap_data_entry_form', array(PROJECT_ID, ($hidden_edit ? $fetched : null), $_GET['page'], $_GET['event_id'], $group_id));
}



//Finish page by including 'bottom page code (contains menus)'
if (!$MobileSite) 
{
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
}
