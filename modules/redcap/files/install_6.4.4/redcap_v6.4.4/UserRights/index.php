<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
// Tabs
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";


## DISPLAY USER LIST OR ENTER NEW USER NAME

// Get all roles
$roles = UserRights::getRoles();

// Page instructions
print "<p>{$lang['rights_216']}";
//If user is in DAG, only show info from that DAG and give note of that
if ($user_rights['group_id'] != "") {
	print  " <span style='color:#C00000;'>{$lang['global_02']}{$lang['colon']} {$lang['rights_92']}</span>";
}
print "</p>";

// Display main user rights table
print 	RCView::div(array('id'=>'user_rights_roles_table_parent', 'style'=>'margin:0 20px 20px 0;'), 
			UserRights::renderUserRightsRolesTable()
		);
// Background overlay for modals
print  	RCView::div(array('id'=>'overlay', 'class'=>'ui-widget-overlay', 'style'=>'background-color:#555;z-index:999;display:none;'), '');
// Hidden pop-up to add or edit user/role
print 	RCView::div(array('id'=>'editUserPopup', 'class'=>'simpleDialog'), '');
// API explanation pop-up
print 	RCView::div(array('id' => 'apiHelpDialogId', 'title' => $lang['rights_141'], 'style' => 'display: none;'),
			RCView::p(array('style' => 'font-family:arial;'),
							$lang['system_config_114'] . ' ' . $lang['edit_project_142'] . $lang['period'] . '<br/><br/>' .
							RCView::a(array('href' => APP_PATH_WEBROOT_PARENT . 'api/help/', 'style' => 'text-decoration:underline;', 'target' => '_blank'),
							$lang['setup_45'] . ' ' . $lang['edit_project_142'])
			)
		);
// Mobile App explanation pop-up
print 	RCView::div(array('id' => 'appHelpDialogId', 'title' => $lang['global_118'], 'class' => 'simpleDialog'),
			RCView::b($lang['rights_308']) . RCView::br() . $lang['rights_310'] .
			RCView::br() . RCView::br() .
			RCView::b($lang['rights_311']) . RCView::br() . $lang['rights_312']
		);
// Mobile App enable confirmation pop-up
print 	RCView::div(array('id' => 'mobileAppEnableConfirm', 'title' => $lang['rights_303'], 'class' => 'simpleDialog'),
			$lang['rights_304']
		);
		

		
// TOOLTIP div when click USER'S EXPIRATION DATE in table
print 	RCView::div(array('id'=>'userClickExpiration', 'class'=>'tooltip4left','style'=>'position:absolute;padding-left:30px;'), 
			RCView::div(array('style'=>'margin:5px 0 6px;font-weight:bold;font-size:13px;'), $lang['rights_203']) .
			// Set new expiration
			RCView::text(array('id'=>'tooltipExpiration', 'class'=>'x-form-text x-form-field', 'style'=>'width:65px;', 'maxlength'=>'10', 
				'onblur'=>"redcap_validate(this,'','','hard','date_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
			// Date format
			RCView::span(array('class'=>'df', 'style'=>'font-size:11px;padding-left:5px;'), '('.DateTimeRC::get_user_format_label().')') .
			// Hidden input where username is store for the user just clicked, which opened this tooltip (so we know which was clicked)
			RCView::hidden(array('id'=>'tooltipExpirationHiddenUsername')) .
			RCView::div(array('style'=>'margin:3px 0 0;'),
				RCView::button(array('id'=>'tooltipExpirationBtn', 'class'=>'jqbuttonmed','onclick'=>"setExpiration();"), $lang['designate_forms_13']) .
				RCView::a(array('id'=>'tooltipExpirationCancel', 'href'=>'javascript:;', 'style'=>'margin-left:2px;color:#bbb;font-size:11px;text-decoration:underline;', 'onclick'=>"$('#userClickExpiration').hide();"), $lang['global_53']) .
				// Hidden progress save message
				RCView::span(array('id'=>'tooltipExpirationProgress', 'style'=>'margin:3px 0 0 10px;font-size:13px;color:#fff;font-weight:bold;', 'class'=>'hidden'), 
					$lang['design_243']
				)
			)
		);
		
// TOOLTIP div when click USER'S DATA ACCESS GROUP in table
$groups = $Proj->getGroups();
print 	RCView::div(array('id'=>'userClickDagName', 'class'=>'tooltip4left','style'=>'position:absolute;padding-left:30px;'), 
			RCView::div(array('style'=>'margin:5px 0 6px;font-weight:bold;font-size:13px;'), $lang['data_access_groups_ajax_32']) .
			// Hidden input where username is store for the user just clicked, which opened this tooltip (so we know which was clicked)
			RCView::hidden(array('id'=>'tooltipDagHiddenUsername')) .
			// DAG drop-down
			RCView::select(array('id'=>'userClickDagSelect', 'class'=>'x-form-text x-form-field', 'style'=>'height:22px;padding-right:0;'), 
				(array(''=>"[{$lang['data_access_groups_ajax_16']}]") + $groups), '') .
			RCView::div(array('style'=>'margin:3px 0 0;'),
				// Select DAG
				RCView::button(array('id'=>'tooltipDagBtn', 'class'=>'jqbuttonmed','onclick'=>"assignUserDag();"), $lang['rights_181']) .
				RCView::a(array('id'=>'tooltipDagCancel', 'href'=>'javascript:;', 'style'=>'margin-left:2px;color:#bbb;font-size:11px;text-decoration:underline;', 'onclick'=>"$('#userClickDagName').hide();"), $lang['global_53']) .
				// Hidden progress save message
				RCView::span(array('id'=>'tooltipDagProgress', 'style'=>'margin:3px 0 0 10px;font-size:13px;color:#fff;font-weight:bold;', 'class'=>'hidden'), 
					$lang['design_243']
				)
			)
		);

?>
<!-- Data Quality explanation pop-up -->
<div style="display:none;margin:15px 0;line-height:16px;" id="explainDataQuality"><?php echo $lang['dataqueries_101'] ?></div>
<!-- Data Resolution Workflow explanation pop-up -->
<div class="simpleDialog" id="explainDRW"><?php echo $lang['dataqueries_156']." ".$lang['dataqueries_144']."<br><br>".$lang['dataqueries_157'] ?></div>
<!-- Randomization explanation pop-up -->
<div style="display:none;" id="randHelpDialogId" title="<?php echo cleanHtml2($lang['rights_145']) ?>">
	<p><?php echo $lang['random_01'] ?></p>
	<p><?php echo $lang['create_project_63'] ?></p>
</div>
<!-- DDP explanation pop-up -->
<div class="simpleDialog" id="explainDDP" title="<?php echo cleanHtml2($lang['ws_28']) ?>"><?php echo $lang['ws_31'] ?></div>
<!-- Custom javascript -->
<script type="text/javascript">
// Display "saved" msg for a few seconds
function displayUserSavedMsg(displayTimeUserSaveMsgSeconds) {
	var msg_ob = $('#user_rights_roles_table_parent div.userSaveMsg');
	if (msg_ob.length) {
		$('#overlay').height( $(document).height() ).width( $(document).width() ).show();
		msg_ob.show('fade','fast').position({ my: "center", at: "center", of: window });
		setTimeout(function(){
			$('#overlay').hide();
			msg_ob.hide('fade',1200);
		},(displayTimeUserSaveMsgSeconds*1000));
	}
}
// Save user form via ajax
function saveUserFormAjax() {
	// Display progress bar
	showProgress(1);
	$('#editUserPopup').dialog('destroy');
	// Serialize form inputs into a JSON object to send via Ajax
	var form_vars = $('form#user_rights_form').serializeObject();
	$.post(app_path_webroot+'UserRights/edit_user.php?pid='+pid, form_vars, function(data){
		showProgress(0,0);
		$('#user_rights_roles_table_parent').html(data);
		displayUserSavedMsg(2);
		enablePageJS();
	});

}
// Copy the role
function copyRoleName(role_name) {
	var copyRoleAction = function(){
		$('form#user_rights_form input[name=\'submit-action\']').val('copy_role');
		$('form#user_rights_form input[name=\'role_name_edit\']').val( trim($('#role_name_copy').val()) );
		saveUserFormAjax();
	};
	simpleDialog('<?php echo cleanHtml($lang['rights_212'].RCView::div(array('style'=>'margin:20px 0 0;font-weight:bold;'), $lang['rights_213'] . RCView::text(array('id'=>'role_name_copy', 'class'=>'x-form-text x-form-field', 'style'=>'margin-left:10px;width:150px;')))) ?>','<?php echo cleanHtml($lang['rights_211'].$lang['questionmark']) ?>',null,null,null,'<?php echo cleanHtml($lang['global_53']) ?>',copyRoleAction,'<?php echo cleanHtml($lang['rights_211']) ?>');
	$('#role_name_copy').val(role_name);
}

// Assign user to DAG (via ajax)
function assignUserDag() {	
	var this_group_id = $('#userClickDagSelect').val();
	$('#userClickDagSelect').prop('disabled',true);
	$('#tooltipDagBtn').button('disable');
	$('#tooltipDagCancel').hide();
	$('#tooltipDagProgress').show();
	$.get(app_path_webroot+'DataAccessGroups/data_access_groups_ajax.php?pid='+pid+'&action=add_user&return_user_rights_table=1&user='+$('#tooltipDagHiddenUsername').val()+'&group_id='+this_group_id,{ },function(data){
		$('#user_rights_roles_table_parent').html(data);
		$('.dagMsg').addClass('userSaveMsg');
		displayUserSavedMsg(2);
		setTimeout(function(){
			$('#userClickDagName').hide();
			$('#userClickDagSelect').prop('disabled',false);
			$('#tooltipDagBtn').button('enable');
			$('#tooltipDagCancel').show();
			$('#tooltipDagProgress').hide();
		},400);
		enablePageJS();
	});
}
// Assign user to role (via ajax)
function assignUserRole(username,role_id) {
	showProgress(1);
	// Ajax request
	$.post(app_path_webroot+'UserRights/assign_user.php?pid='+pid, { username: username, role_id: role_id }, function(data){
		if (data == '') { alert(woops); return; }
		$('#user_rights_roles_table_parent').html(data);
		showProgress(0,0);
		displayUserSavedMsg(2);
		enablePageJS();
		setTimeout(function(){
			if (role_id == '0') {
				simpleDialog('<?php echo cleanHtml($lang['rights_215']) ?>','<?php echo cleanHtml($lang['global_03'].$lang['colon']." ".$lang['rights_214']) ?>');
			}
		},3200);
	});
}
// Open "add user/role" dialog
function openAddUserPopup(username,role_id) {
	// Set vars
	if (role_id == null) role_id = '';
	// Ajax request
	$.post(app_path_webroot+'UserRights/edit_user.php?pid='+pid, { username: username, role_id: role_id }, function(data){
		if (data=='') { alert(woops); return; }
		// Add content to div
		$('#editUserPopup').html(data);
		// Enable expiration datepicker
		$('#expiration').datepicker({yearRange: '-10:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery});
		// If select "edit response" checkbox, then set form-level rights radio button to View & Edit
		$('table#form_rights input[type="checkbox"]').click(function(){
			if ($(this).prop('checked')) {
				var form = $(this).attr('id').substring(14);
				// Deselect all, then select View & Edit
				$('table#form_rights input[name="form-'+form+'"][value="0"]').prop('checked',false);
				$('table#form_rights input[name="form-'+form+'"][value="2"]').prop('checked',false);
				$('table#form_rights input[name="form-'+form+'"][value="1"]').prop('checked',true);
			}
		});
		// Set dialog buttons
		eval($('#editUserPopup div#submit-buttons').html());
		// Set dialog title
		if ($('#editUserPopup #dialog_title').length) {
			var title = $('#editUserPopup #dialog_title').html();
			// Open dialog
			$('#editUserPopup').dialog({ bgiframe: true, modal: true, width: 800, 
				open: function(){
					// Put bold on the Save button and set focus on it
					$('.ui-dialog-buttonpane').find('button:last').css({'font-weight':'bold','color':'#222'}).focus();
					// Stylize the delete and copy buttons (if displayed)
					if ($('.ui-dialog-buttonpane button').length > 2) {
						if ($('.ui-dialog-buttonpane button').length == 3) {
							// Stylize the delete button
							$('.ui-dialog-buttonpane').find('button:eq(0)').css({'color':'#C00000','font-size':'11px','margin':'9px 0 0 40px'});
						} else {
							// Stylize the delete button AND copy button	
							$('.ui-dialog-buttonpane').find('button:eq(0)').css({'color':'#C00000','font-size':'11px','margin':'9px 0 0 5px'});
							$('.ui-dialog-buttonpane').find('button:eq(1)').css({'color':'#000066','font-size':'11px','margin':'9px 0 0 40px'});
						}
					}
					// Fit to screen
					fitDialog(this);
				}, 
				title: title, buttons: add_user_dialog_btns, close: function(){ $('#editUserPopup').html('') }
			});
		} else {
			// Error
			simpleDialog(data,'Alert');
		}
	});
}

// Set new user expiration
function setExpiration() {
	// Ajax request save
	$('#tooltipExpirationBtn').button('disable');
	$('#tooltipExpiration').prop('disabled',true);
	$('#tooltipExpirationCancel').hide();
	$('#tooltipExpirationProgress').show();
	$.post(app_path_webroot+'UserRights/set_user_expiration.php?pid='+pid, { username: $('#tooltipExpirationHiddenUsername').val(), expiration: $('#tooltipExpiration').val()},function(data){
		if (data == '0') {
			alert(woops);
		} else {		
			$('#user_rights_roles_table_parent').html(data);			
			setTimeout(function(){
				$('#tooltipExpiration').prop('disabled',false);
				$('#tooltipExpirationBtn').button('enable');
				$('#tooltipExpirationCancel').show();
				$('#tooltipExpirationProgress').hide();
				$('#userClickExpiration').hide();
			},400);	
			enablePageJS();
		}
	});
}

// Initialize jQuery triggers on page
function enablePageJS() {

	initWidgets();
	
	// Hide the user assignment drop-down if off-click it
	$(window).click(function(){
		$('#assignUserDropdownDiv').hide();
		$('#userClickTooltip').hide();
	});
	
	// Auto-height fix for username, expiration, and DAG name divs to maintain vertical alignment across several columns
	var this_eq = 0;
	var hasDags = ($('table#table-user_rights_roles_table .dagNameLinkDiv').length);
	$('table#table-user_rights_roles_table .userNameLinkDiv').each(function(){
		// Get corresponding div elements for other columns
		if (hasDags) var dag_ob = $('table#table-user_rights_roles_table .dagNameLinkDiv').eq(this_eq);
		var exp_ob = $('table#table-user_rights_roles_table .expireLinkDiv').eq(this_eq);
		// Get height of this div and corresponding divs for this user
		var user_h = $(this).height();
		var dag_h  = (hasDags ? dag_ob.height() : 0);
		var exp_h  = exp_ob.height();
		// Get max
		var this_max = max(user_h, dag_h, exp_h);		
		// Apply max height to all columns
		$(this).height(this_max);
		exp_ob.height(this_max);
		if (hasDags) dag_ob.height(this_max);
		// Increment eq counter
		this_eq++;		
	});
	
	// If user clicks user's DAG name link
	$('.dagNameLinkDiv a').click(function(event){	
		// Prevent $(window).click() from hiding this
		try {
			event.stopPropagation();
		} catch(err) {
			window.event.cancelBubble=true;
		}		
		// Get username and group_id of user just clicked
		var this_username = $(this).attr('uid');
		var this_group_id = $(this).attr('gid');
		// If already open for this user, then close it
		if ($('#userClickDagName').css('display') != 'none' && this_username == $('#tooltipDagHiddenUsername').val()) {
			$('#userClickDagName').hide();
			return;
		}
		// Place username in hidden input inside tooltip to keep context of who we're editing
		$('#tooltipDagHiddenUsername').val( this_username );
		// Set tooltip position and display it
		$('#userClickDagName').show().position({
			my: "left center",
			at: "right center",
			of: this
		});
		// Enable expiration datepicker and set expire value
		$('#userClickDagSelect').val(this_group_id);
	
	});
	
	// If user clicks user's expiration date link
	$('.userRightsExpire, .userRightsExpireN, .userRightsExpired').click(function(event){	
		// Prevent $(window).click() from hiding this
		try {
			event.stopPropagation();
		} catch(err) {
			window.event.cancelBubble=true;
		}
		// Get username and expiration of user just clicked
		var this_username = $(this).attr('userid');
		var this_expiration = $(this).attr('expire');
		// If already open for this user, then close it
		if ($('#userClickExpiration').css('display') != 'none' && this_username == $('#tooltipExpirationHiddenUsername').val()) {
			$('#userClickExpiration').hide();
			return;
		}
		// Place username in hidden input inside tooltip to keep context of who we're editing
		$('#tooltipExpirationHiddenUsername').val( this_username );
		// Set tooltip position and display it
		$('#userClickExpiration').show().position({
			my: "left center",
			at: "right center",
			of: this
		});
		// Enable expiration datepicker and set expire value
		$('#tooltipExpiration').datepicker({yearRange: '-10:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery});
		$('#tooltipExpiration').val(this_expiration);
	});

	// If user clicks to create NEW ROLE
	$('#createRoleBtn').click(function(){
		// Validate role name
		var this_user = $('#new_rolename').trigger('focus').val();
		if (this_user.length == 0) {
			simpleDialog('<?php echo cleanHtml($lang['rights_161']) ?>',null,null,null,"$('#new_rolename').trigger('focus')");
			return false;
		}
		// Open dialog to add new user with custom rights
		openAddUserPopup($('#new_rolename').val(),0);
	});

	// If user selects to ADD new user
	$('#addUserBtn').click(function(){
		// Validate username
		var this_user = $('#new_username').trigger('focus').val();
		if (this_user.length == 0) {
			simpleDialog('<?php echo cleanHtml($lang['rights_163']) ?>',null,null,null,"$('#new_username').trigger('focus')");
			return false;
		}
		if (!chk_username(document.getElementById('new_username'))) {
			simpleDialog('<?php echo cleanHtml($lang['rights_35']) ?>',null,null,null,"$('#new_username').trigger('focus')");
			return;
		}
		// Open dialog to add new user with custom rights
		openAddUserPopup($('#new_username').val());
	});
	
	// Auto-suggest for adding new users
	$('#new_username').ui_autocomplete({
		source: app_path_webroot+"UserRights/search_user.php?ignoreExistingUsers=1&pid="+pid,
		minLength: 2,
		delay: 150,
		select: function( event, ui ) {
			$(this).val(ui.item.value);
			return false;
		}
	})
	.data('ui_autocomplete')._renderItem = function( ul, item ) {
		return $("<li></li>")
			.data("item", item)
			.append("<a>"+item.label+"</a>")
			.appendTo(ul);
	};
	$('#new_username_assign').ui_autocomplete({
		source: app_path_webroot+"UserRights/search_user.php?ignoreExistingUsers=1&pid="+pid,
		minLength: 2,
		delay: 150,
		select: function( event, ui ) {
			$(this).val(ui.item.value);
			return false;
		}
	})
	.data('ui_autocomplete')._renderItem = function( ul, item ) {
		return $("<li></li>")
			.data("item", item)
			.append("<a>"+item.label+"</a>")
			.appendTo(ul);
	};
	
	// If user clicks on icon to edit user/role, open popup
	$("[id^=rightsTableUserLinkId_]").click(function() {
		var idUsername = $(this).attr('id').substring("rightsTableUserLinkId_".length);
		openAddUserPopup('',idUsername);
	});	
	
	// Tooltip to appear when click username in a role in table
	$('.userLinkInTable').click(function(event) {
		// Prevent $(window).click() from hiding this
		try {
			event.stopPropagation();
		} catch(err) {
			window.event.cancelBubble=true;
		}
		// Get username of user just clicked
		var this_username = $(this).attr('userid');
		// If already open for this user, then close it
		if ($('#userClickTooltip').css('display') != 'none' && this_username == $('#tooltipHiddenUsername').val()) {
			$('#userClickTooltip').hide();
			return;
		}
		// Place username in hidden input inside tooltip to keep context of who we're editing
		$('#tooltipHiddenUsername').val( this_username );
		// Hide buttons based upon if user is in role or not
		if ($(this).attr('inrole') == '1') {
			// User is in a role
			$('#tooltipBtnSetCustom').hide();
			$('#tooltipBtnRemoveRole').show();
			$('#tooltipBtnAssignRole').hide();
			$('#tooltipBtnReassignRole').show();
		} else {
			// User is NOT in a role
			$('#tooltipBtnSetCustom').show();
			$('#tooltipBtnRemoveRole').hide();
			$('#tooltipBtnAssignRole').show();
			$('#tooltipBtnReassignRole').hide();
		}
		// Set tooltip position and display it
		$('#userClickTooltip').show().position({
			my: "left center",
			at: "right center",
			of: this
		});
	});

	// If user selects to ASSIGN new user
	$('#assignUserBtn, #assignUserBtn2, #assignUserBtn3').click(function(event){
		// Prevent $(window).click() from hiding this
		try {
			event.stopPropagation();
		} catch(err) {
			window.event.cancelBubble=true;
		}
		// If no roles have been created yet, give message to create some
		if ($('#assignUserDropdownDiv ul li').length == 0) {
			simpleDialog('<?php echo cleanHtml($lang['rights_186']) ?>', '<?php echo cleanHtml($lang['global_03']) ?>');
			return;
		}
		// Set drop-down div object
		var ddDiv = $('#assignUserDropdownDiv');
		// If drop-down is already visible, then hide it and stop here
		if (ddDiv.css('display') != 'none') {
			ddDiv.hide();
			return;
		}
		// Set width
		if (ddDiv.css('display') != 'none') {
			var ebtnw = $(this).width();
			var eddw  = ddDiv.width();
			if (eddw < ebtnw) ddDiv.width( ebtnw );
		}
		// Set position
		var btnPos = $(this).offset();
		ddDiv.show().offset({ left: btnPos.left, top: (btnPos.top+$(this).outerHeight()) });
	});
	// Add style to drop-down list
	$('#assignUserDropdown').menu();
	// Add click action to each choice in drop-down
	$('#assignUserDropdown').children('li').click(function(){
		// Check if we're adding a new user or re-assigning an existing one
		if ($('#userClickTooltip').css('display') == 'none') {
			// Validate username in Assign New User text box
			var this_user = $('#new_username_assign').trigger('focus').val();
			if (this_user.length == 0) {
				simpleDialog('<?php echo cleanHtml($lang['rights_163']) ?>',null,null,null,"$('#new_username_assign').trigger('focus')");
				return false;
			}			
			if (!chk_username(document.getElementById('new_username_assign'))) {
				simpleDialog('<?php echo cleanHtml($lang['rights_35']) ?>',null,null,null,"$('#new_username_assign').trigger('focus')");
				return;
			}
		} else {
			// Obtain username from hidden input inside tooltip
			var this_user = $('#tooltipHiddenUsername').val();			
		}
		// Obtain role_id
		var this_roleid = $(this).attr('id').substring("assignUserRoleId_".length);
		// Assign user to role
		assignUserRole(this_user, this_roleid);
	});
}

$(function(){	
	// Initialize page's JavaScript
	enablePageJS();		
});
</script>
<?php

// REDCap Hook injection point: Pass project_id to method
Hooks::call('redcap_user_rights', array(PROJECT_ID));


include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
