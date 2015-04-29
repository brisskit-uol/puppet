$(function(){
	// Enable editing of participant list email/identifier
	enableEditParticipant();
	// Reset participant list editing after sorting it by clicking header
	$('div#participant_table table th').click(function(){
		setTimeout(function(){ enableEditParticipant() },100);
	});
	// Survey Reminder related setup
	initSurveyReminderSettings();
	// Enable sendtime datetime picker
	$('#emailSendTimeTS').datetimepicker({
		onClose: function(dateText, inst){ $('#'+$(inst).attr('id')).blur(); },
		buttonText: 'Click to select a date', yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
		hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time', 
		showOn: 'button', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'hh:mm', constrainInput: false
	});
});

// In email survey inviations pop-up, pre-select checkboxes based on action selected
function emailPartPreselect(val) {
	if (val.length < 1) return;
	if (val == 'check_all') {
		// Check all
		$('#participant_table_email input.chk_part').prop('checked',true);
	} else {
		// Uncheck all first
		$('#participant_table_email input.chk_part').prop('checked',false);
		// Now check specifically
		if (val == 'check_sent') {
			$('#participant_table_email input.part_sent').prop('checked',true);	
		} else if (val == 'check_unsent') {
			$('#participant_table_email input.part_unsent').prop('checked',true);	
		} else if (val == 'check_sched') {
			$('#participant_table_email input.sched').prop('checked',true);
		} else if (val == 'check_unsched') {
			$('#participant_table_email input.unsched').prop('checked',true);
		} else if (val == 'check_unsent_unsched') {
			$('#participant_table_email input.unsched.part_unsent').prop('checked',true);
		} else if (val == 'check_resp_partial') {
			$('#participant_table_email input.part_resp_partial').prop('checked',true);
		} else if (val == 'check_resp_full') {
			$('#participant_table_email input.part_resp_full').prop('checked',true);
		} else if (val == 'check_not_resp') {
			$('#participant_table_email input.part_not_resp').prop('checked',true);
		} else if (val == 'check_resp') {
			$('#participant_table_email input.part_resp_full, #participant_table_email input.part_resp_partial').prop('checked',true);
		}
	}
}

// Load/reload the participant list via ajax
function loadPartList(survey_id,event_id,pagenum,callback_msg,callback_title) {
	if (pagenum == null) pagenum = 1;
	showProgress(1);
	$.get(app_path_webroot+'Surveys/participant_list.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id+'&pagenum='+pagenum, function(data){
		$('#partlist_outerdiv').html(data);
		showProgress(0,0);
		// Only make table editable if on initial survey
		//if (survey_id == firstFormSurveyId && event_id == firstEventId) 
		enableEditParticipant();		
		// Initialize all buttons in participant list
		initWidgets();
		resizeMainWindow();		
		if (callback_msg != null) {
			var rndm = Math.random()+"";
			var dlgloadpartid = 'dlgloadpartid_'+rndm.replace('.','');
			simpleDialog(callback_msg,callback_title,dlgloadpartid);
			setTimeout(function(){	
				$('#'+dlgloadpartid).dialog('option', 'hide', {effect:'fade', duration: 500}).dialog('close');
				// Destroy the dialog so that fade effect doesn't persist if reopened
				setTimeout(function(){
					$('#'+dlgloadpartid).dialog('destroy').remove();
				},500);
			},2000);
		}
	});
}

// Retrieve short url and display for user
function getShortUrl(hash,survey_id) {
	if ($('#shorturl').val().length < 1) {
		$('#shorturl_div').hide();
		$('#shorturl_loading_div').show('fade','fast');
		$.get(app_path_webroot+'Surveys/shorturl.php', { pid: pid, hash: hash, survey_id: survey_id }, function(data) {
			if (data != '0') {
				$('#shorturl_loading_div').hide();
				$('#shorturl').val(data);
				$('#shorturl_div').show('fade','fast');
				$('#shorturl_div').effect('highlight', 'slow');
			}
		});
	} else {
		$('#shorturl_div').effect('highlight', 'slow');
	}
}

// Click the enable/disable Participant Identifiers button to open dialog
function enablePartIdent(survey_id,event_id) {
	// First, fire JS to reorder table by Email (since the button to trigger this function ordered it by Identifier)
	setTimeout(function(){ SortTable('table-participant_table',0,'string'); },5);
	// Ajax request
	$.post(app_path_webroot+'Surveys/participant_list_enable.php?pid='+pid, { action: 'view' },function(data){
		if (data == "0") {
			alert(woops);
			return;
		}
		// Set dialog title/content
		var json_data = jQuery.parseJSON(data);
		$('#popupEnablePartIdent').prop("title",json_data.title);
		$('#popupEnablePartIdent').html(json_data.payload);
		var saveBtn = json_data.saveBtn;
		var successDialogContent = json_data.successDialogContent;
		// Open dialog
		$('#popupEnablePartIdent').dialog({ bgiframe: true, modal: true, width: 550, buttons: [{
				text: "Cancel",
				click: function () {
					$(this).dialog('close');
				}
			},{
				text: json_data.saveBtn,
				click: function () {
					// Save value via AJAX
					$.post(app_path_webroot+'Surveys/participant_list_enable.php?pid='+pid, { action: 'save' },function(data){
						if (data == "0") {
							alert(woops);
						} else {
							// Success!
							$('#popupEnablePartIdent').dialog('destroy');
							var pageNum = $('#pageNumSelect').val();
							if (!isNumeric(pageNum)) pageNum = 1;
							simpleDialog(successDialogContent,null,'',500,"loadPartList("+survey_id+","+event_id+","+pageNum+");");
						}
					});
				}
			}]
		});
	});
};

// Disable Participant Identifiers column in the List (prevent adding/editing)
function disablepartIdentColumn() {
	if ($('#enable_participant_identifiers').val() == '0') {
		// DISABLED
		// Set gray background for all cells in column
		$('.partIdentColDisabled').parent().parent().css('background-color','#E8E8E8');
		// Hide text on page relating to identifiers
		$('.partIdentInstrText').hide();
		// Pop-up tooltip: Give warning message to user if tries to edit identifier IF identifiers are DISABLED (not allowed)	
		$('.partIdentColDisabled').tooltip({
			tip: '#tooltipIdentDisabled',
			position: 'center right',
			offset: [10, -60],
			delay: 100,
			events: { def: "click,mouseout" }
		});
	} else {
		// ENABLED
		// Show text on page relating to identifiers
		$('.partIdentInstrText').show();		
	}
}

// Copy the public survey URL to the user's clipboard
function copyUrlToClipboard(id) {
	// Create progress element that says "Copied!" when clicked
	var rndm = Math.random()+"";
	var copyid = 'clip'+rndm.replace('.','');
	var clipSaveHtml = '<span class="clipboardSaveProgress" id="'+copyid+'">Copied!</span>';
	$('#flashObj_'+id).after(clipSaveHtml);	
	$('#'+copyid).toggle('fade','fast');
	setTimeout(function(){
		$('#'+copyid).toggle('fade','fast',function(){
			$('#'+copyid).remove();
		});
	},2000);
	// Save to clipboard
	var s = $('#'+id).val();
	if (window.clipboardData) {
		window.clipboardData.setData('text', s);
	} else {
		return s;
	}
}

// Pop-up tooltip: Give warning message to user if tries to click partial/complete icon to view response IF identifier is not defined
function noViewResponseTooltip() {
	$('.noviewresponse').tooltip({
		tip: '#tooltipViewResp',
		position: 'center left',
		offset: [30, -10],
		delay: 100,
		events: { def: "click,mouseout" }
	});
}

// Set up in-line editing for email address and identifier
function enableEditParticipant() {
	// First, check if we should disabled the Identifier column in the table(if not enabled yet)
	disablepartIdentColumn();
	// Pop-up tooltip: Give warning message to user if tries to edit email/identifier IF response is partial/complete (not allowed)	
	$('.noeditidentifier').tooltip({
		tip: '#tooltipEdit',
		position: 'center right',
		offset: [10, -60],
		delay: 100,
		events: { def: "click,mouseout" }
	});
	// Pop-up tooltip: Give warning message to user if tries to click partial/complete icon to view response IF identifier is not defined
	noViewResponseTooltip();
	// Pop-up tooltip: Denote that user can click partial/complete icon to view response
	$('.viewresponse, .partLink').tooltip({
		position: 'center right',
		offset: [0, 10],
		delay: 100
	});	
	// If user clicks on Participant Email to add it for response from Public Survey, give tooltip explaining why this is not possible
	$('.noeditemailpublic').tooltip({
		tip: '#tooltipNoEditEmailPublic',
		position: 'center right',
		offset: [10, -60],
		delay: 100,
		events: { def: "click,mouseout" }
	});
	$('.noeditphonepublic').tooltip({
		tip: '#tooltipNoPhoneEmailPublic',
		position: 'center right',
		offset: [10, -60],
		delay: 100,
		events: { def: "click,mouseout" }
	});
	// Hide tooltips if they are clicked on
	$('#tooltipEdit, #tooltipViewResp, #tooltipIdentDisabled, #tooltipNoEditEmailPublic, #tooltipNoPhoneEmailPublic').click(function(){
		$(this).hide('fade');
	});	
	
	// For editing Twilio invitation preference
	if ($('.editinvpref').length) enableEditInvPref( $('.editinvpref') );
	
	if (!isFollowUpSurvey) {
		// INITIAL SURVEY
		// For editing email
		$('.editemail').hover(function(){
			// If already clicked		
			if ($(this).html().indexOf('<input') > -1) { 
				$(this).unbind('click');
				return;
			}
			$(this).css('cursor','pointer');
			$(this).addClass('edit_active');
			$(this).prop('title','Click to edit email');
		}, function() {
			$(this).css('cursor','');
			$(this).removeClass('edit_active');
			$(this).removeAttr('title');
		});
		$('.editemail').click(function(){
			// If already clicked		
			if ($(this).html().indexOf('<input') > -1) { 
				$(this).unbind('click');
				return;
			}
			// Undo css
			$(this).css('cursor','');
			$(this).removeClass('edit_active');
			$(this).removeAttr('title');
			$(this).unbind('click');
			var thisEmail = $(this).text();
			if (thisEmail.indexOf(')') > 0) { 
				var aaa = thisEmail.split(')');
				if (aaa[1].indexOf('@') > 0) {
					thisEmail = trim(aaa[1]);
				}
			}
			if (thisEmail.indexOf('(') > 0) { 
				var aaa = thisEmail.split('(');
				thisEmail = trim(aaa[0]);
			}
			var thisPartId = $(this).attr('part');
			$(this).html( '<input id="partNewEmail_'+thisPartId+'" onblur=\'redcap_validate(this,"","","soft_typed","email")\' type="text" class="x-form-text x-form-field" style="vertical-align:middle;width:70%;" value="'+thisEmail+'"> &nbsp;'
						+ '<button style="vertical-align:middle;" class="jqbuttonsm" onclick="editPartEmail('+thisPartId+');">'+langSave+'</button>');
		});
		// For editing identifier
		$('.editidentifier').hover(function(){
			// If already clicked		
			if ($(this).html().indexOf('<input') > -1) { 
				$(this).unbind('click');
				return;
			}
			$(this).css('cursor','pointer');
			$(this).addClass('edit_active');
			$(this).prop('title','Click to edit identifier');
		}, function() {
			$(this).css('cursor','');
			$(this).removeClass('edit_active');
			$(this).removeAttr('title');
		});
		$('.editidentifier').click(function(){
			// If already clicked		
			if ($(this).html().indexOf('<input') > -1) { 
				$(this).unbind('click');
				return;
			}
			// Undo css
			$(this).css('cursor','');
			$(this).removeClass('edit_active');
			$(this).removeAttr('title');
			$(this).unbind('click');
			var thisIdentifier = trim($(this).text().replace(/"/ig,'&quot;'));
			var thisPartId = $(this).attr('part');
			$(this).html( '<input id="partNewIdentifier_'+thisPartId+'" type="text" class="x-form-text x-form-field" style="vertical-align:middle;width:73%;" value="'+thisIdentifier+'"> &nbsp;'
						+ '<button style="vertical-align:middle;" class="jqbuttonsm" onclick="editPartIdentifier('+thisPartId+');">'+langSave+'</button>');
		});
		// For editing phone
		$('.editphone').hover(function(){
			// If already clicked		
			if ($(this).html().indexOf('<input') > -1) { 
				$(this).unbind('click');
				return;
			}
			$(this).css('cursor','pointer');
			$(this).addClass('edit_active');
			$(this).prop('title','Click to edit email');
		}, function() {
			$(this).css('cursor','');
			$(this).removeClass('edit_active');
			$(this).removeAttr('title');
		});
		$('.editphone').click(function(){
			// If already clicked		
			if ($(this).html().indexOf('<input') > -1) { 
				$(this).unbind('click');
				return;
			}
			// Undo css
			$(this).css('cursor','');
			$(this).removeClass('edit_active');
			$(this).removeAttr('title');
			$(this).unbind('click');
			var thisPhone = $(this).text();
			// Remove all but numbers
			thisPhone = thisPhone.replace(/[^0-9\.]+/g, '');
			var thisPartId = $(this).attr('part');
			$(this).html( '<input id="partNewPhone_'+thisPartId+'" onblur=\'redcap_validate(this,"","","soft_typed","int")\' type="text" class="x-form-text x-form-field" style="font-size:11px;vertical-align:middle;width:60%;" value="'+thisPhone+'"> &nbsp;'
						+ '<button style="vertical-align:middle;" class="jqbuttonsm" onclick="editPartPhone('+thisPartId+');">'+langSave+'</button>');
		});
	} else {
		// FOLLOW-UP SURVEY		
		// If user clicks on Participant Identifier to add it for follow-up survey, give tooltip explaining why this is not possible
		$('.editidentifier').tooltip({
			tip: '#tooltipNoEditIdentFollowup',
			position: 'center right',
			offset: [10, -60],
			delay: 100,
			events: { def: "click,mouseout" }
		});
		// If user clicks on Participant Email to add it for follow-up survey, give tooltip explaining why this is not possible
		$('.editemail').tooltip({
			tip: '#tooltipNoEditEmailFollowup',
			position: 'center right',
			offset: [10, -60],
			delay: 100,
			events: { def: "click,mouseout" }
		});
		// If user clicks on Participant Phone to add it for follow-up survey, give tooltip explaining why this is not possible
		$('.editphone').tooltip({
			tip: '#tooltipNoEditPhoneFollowup',
			position: 'center right',
			offset: [10, -60],
			delay: 100,
			events: { def: "click,mouseout" }
		});
		// Hide tooltips if they are clicked on
		$('#tooltipNoEditEmailFollowup, #tooltipNoEditIdentFollowup').click(function(){
			$(this).hide('fade');
		});
	}
}

// Open the "view email" dialog
function viewEmail(email_recip_id, ssq_id) {
	$.post(app_path_webroot+'Surveys/view_sent_email.php?pid='+pid,{ email_recip_id: email_recip_id, ssq_id: ssq_id }, function(data){
		if (data == "0") {
			alert(woops);
			return;
		}
		var json_data = jQuery.parseJSON(data);
		// Display dialog
		simpleDialog(json_data.content,json_data.title,null,600);
	});
}

// Reload the Survey Invitation Log for another "page" when paging the log
function loadInvitationLog(pagenum) {
	showProgress(1);
	window.location.href = app_path_webroot+page+'?pid='+pid+'&email_log=1&pagenum='+pagenum+
		'&filterBeginTime='+$('#filterBeginTime').val()+'&filterEndTime='+$('#filterEndTime').val()+
		'&filterInviteType='+$('#filterInviteType').val()+'&filterResponseType='+$('#filterResponseType').val()+
		'&filterSurveyEvent='+$('#filterSurveyEvent').val()+
		'&filterReminders='+($('#filterReminders').prop('checked') ? '1' : '0');
}

// Delete a scheduled survey invitation from invitation log
function deleteSurveyInvite(email_recip_id, reminder_num) {
	$.post(app_path_webroot+'Surveys/survey_invitation_ajax.php?pid='+pid,{ email_recip_id: email_recip_id, reminder_num: reminder_num, action: 'view_delete' }, function(data){
		if (data == "0") {
			alert(woops);
			return;
		}
		var json_data = jQuery.parseJSON(data);
		// Display dialog
		simpleDialog(json_data.content,json_data.title,null,500,null,'Cancel','deleteSurveyInviteDo('+email_recip_id+','+reminder_num+')','Delete invitation');
	});
}
function deleteSurveyInviteDo(email_recip_id, reminder_num) {
	$.post(app_path_webroot+'Surveys/survey_invitation_ajax.php?pid='+pid,{ email_recip_id: email_recip_id, reminder_num: reminder_num, action: 'delete' }, function(data){
		if (data == "0") {
			alert(woops);
			return;
		}
		var json_data = jQuery.parseJSON(data);
		// Display dialog
		simpleDialog(json_data.content,json_data.title,null,500,'showProgress(1);window.location.reload()');
	});
}

// Modify the send time for a scheduled survey invitation in the invitation log
function editSurveyInviteTime(email_recip_id, reminder_num) {
	$.post(app_path_webroot+'Surveys/survey_invitation_ajax.php?pid='+pid,{ email_recip_id: email_recip_id, reminder_num: reminder_num, action: 'view_edit_time' }, function(data){
		if (data == "0") {
			alert(woops);
			return;
		}
		var json_data = jQuery.parseJSON(data);
		// Display dialog
		simpleDialog(json_data.content,json_data.title,null,500,null,'Cancel','editSurveyInviteTimeDo('+email_recip_id+','+reminder_num+')','Change invitation time');
		initWidgets();
		window.newInviteTime = $('#newInviteTime').val();
		$('#newInviteTime').datetimepicker({
			buttonText: 'Click to select a date', yearRange: '-10:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
			hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time', 
			showOn: 'both', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'hh:mm', constrainInput: false
		});
	});
}
function editSurveyInviteTimeDo(email_recip_id, reminder_num) {
	if (window.newInviteTime == '') {
		simpleDialog("Please enter a date/time");
	} else {
		$.post(app_path_webroot+'Surveys/survey_invitation_ajax.php?pid='+pid,{ email_recip_id: email_recip_id, reminder_num: reminder_num, action: 'edit_time', newInviteTime: window.newInviteTime }, function(data){
			if (data == "0") {
				alert(woops);
				return;
			}
			var json_data = jQuery.parseJSON(data);
			// Display dialog
			simpleDialog(json_data.content,json_data.title,null,500,'showProgress(1);window.location.reload()');
		});
	}
}

// Open dialog for initiating Voice/SMS for surveys
function initCallSMS(hash,phone,format) {
	// Id of dialog
	var dlgid = 'VoiceSMSdialog';
	// Display dialog
	if (phone == null) {
		// Get content via ajax
		$.post(app_path_webroot+'Surveys/twilio_initiate_call_sms.php?pid='+pid+'&action=view&s='+hash,{  }, function(data){
			if (data == "0" || data == "") {
				alert(woops);
				return;
			}
			// Decode JSON
			var json_data = jQuery.parseJSON(data);
			// Add html
			initDialog(dlgid);
			$('#'+dlgid).html(json_data.content);
			// Display dialog
			$('#'+dlgid).dialog({ title: json_data.title, bgiframe: true, modal: true, width: 500, open:function(){ fitDialog(this); }, close:function(){ $(this).dialog('destroy'); } });
			// Init buttons
			initButtonWidgets();
		});
	} else {
		// Make sure numbers were entered
		phone = trim(phone);
		$('#'+dlgid+' #call_sms_to_number').val(phone);
		if (phone == '') {
			simpleDialog('You did not enter any phone numbers.',null,null,null,'$("#'+dlgid+' #call_sms_to_number").focus();');
			return;
		}
		showProgress(1);
		// Send SMS/voice call
		$.post(app_path_webroot+'Surveys/twilio_initiate_call_sms.php?pid='+pid+'&action=init&s='+hash+'&delivery_type='+format,{ phone: phone, sms_message: $('#'+dlgid+' #sms_message').val() }, function(data){
			showProgress(0,0);
			if (data == "0" || data == "") {
				alert(woops);
				$('#'+dlgid).dialog('close');
				initCallSMS(hash);
				return;
			}
			// Decode JSON
			var json_data = jQuery.parseJSON(data);
			// Add html
			initDialog(dlgid);
			$('#'+dlgid).html(json_data.content);
			// Display dialog
			$('#'+dlgid).dialog({ title: json_data.title, bgiframe: true, modal: true, width: 500, open:function(){ fitDialog(this); }, close:function(){ $(this).dialog('destroy'); } });
			// Init buttons
			initButtonWidgets();
		});
	}
}

// Show/hide the custom SMS message text box for Public Survey invitations
function showSmsCustomMessage() {
	var isVoiceCall = ($('#VoiceSMSdialog #delivery_type').val() == 'VOICE_INITIATE');
	if (isVoiceCall) {
		$('#VoiceSMSdialog #sms_message_div').hide();
	} else {
		$('#VoiceSMSdialog #sms_message_div').show();
	}
}

// Edit the participant's email address and identifier via ajax
function editPartEmail(thisPartId) {
	var email = trim($('#partNewEmail_'+thisPartId).val());
	if (email.length<1) {
		alert('Enter an email address');
		return;
	}
	$.post(app_path_webroot+'Surveys/edit_participant.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id, { email: email, participant_id: thisPartId }, function(data){
		var data2 = data;
		if (data.length<1) data2 = '&nbsp;';
		$('#editemail_'+thisPartId).html(data2);
		$('#editemail_'+thisPartId).addClass('edit_saved');
		setTimeout(function(){
			$('#editemail_'+thisPartId).removeClass('edit_saved');
		},2000);
		enableEditParticipant();
	});
}
function editPartIdentifier(thisPartId) {
	var identifier = trim($('#partNewIdentifier_'+thisPartId).val());
	$.post(app_path_webroot+'Surveys/edit_participant.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id, { identifier: identifier, participant_id: thisPartId }, function(data){
		var data2 = data;
		if (data.length<1) data2 = '&nbsp;';
		$('#editidentifier_'+thisPartId).html(data2);
		$('#editidentifier_'+thisPartId).addClass('edit_saved');
		setTimeout(function(){
			$('#editidentifier_'+thisPartId).removeClass('edit_saved');
		},2000);
		enableEditParticipant();
	});
}
function editPartPhone(thisPartId) {
	var phone = trim($('#partNewPhone_'+thisPartId).val());
	$.post(app_path_webroot+'Surveys/edit_participant.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id, { phone: phone, participant_id: thisPartId }, function(data){
		var data2 = data;
		if (data.length<1) data2 = '&nbsp;';
		$('#editphone_'+thisPartId).html(data2);
		$('#editphone_'+thisPartId).addClass('edit_saved');
		setTimeout(function(){
			$('#editphone_'+thisPartId).removeClass('edit_saved');
		},2000);
		enableEditParticipant();
	});
}

// Submit the email form
function sendEmailsSubmit(survey_id,event_id) {
	var dlg_id = 'invites_sent_confirm_dialog';
	showProgress(1);
	$.post(app_path_webroot+"Surveys/email_participants.php?pid="+pid+"&survey_id="+survey_id+"&event_id="+event_id, $('#emailPartForm').serializeObject(), function(data) {
		// Hide email form dialog
		$('#emailPart').dialog('close');
		$('#reschedule-reminder-dialog').dialog('close');
		showProgress(0,0);
		// Start reloading the participant list underneath dialog
		loadPartList(survey_id, event_id, $('#pageNumSelect').val());
		// Display confirmation dialog
		initDialog(dlg_id);
		$('#'+dlg_id).html(data);
		// Take title from inside of dialog and remove it from dialog content
		var dlg_title = $('#'+dlg_id+' h3:first').html();
		$('#'+dlg_id+' h3:first').remove();
		simpleDialog(null,dlg_title,dlg_id,550);
		// Remove the hidden input field that was added to the form right before submission
		$('#emailPartForm input[name="participants"]').remove();
	});
}

// Enable editing of Invitation Preference	
function enableEditInvPref(ob) {
	ob.hover(function(){
		$(this).css('cursor','pointer');
		$(this).addClass('edit_active');
		$(this).prop('title','Click to edit preference');
	}, function() {
		$(this).css('cursor','');
		$(this).removeClass('edit_active');
		$(this).removeAttr('title');
	});
	ob.click(function(){
		// Undo css
		$(this).css('cursor','');
		$(this).removeClass('edit_active');
		$(this).removeAttr('title');
		$(this).unbind('click');	
		// Show/hide things and set values of hidden elements
		$('#partInvPrefSaved').hide();
		$('#partInvPrefPartId').val($(this).attr('part'));
		$('#partInvPrefRecord').val($(this).attr('rec'));
		$('#partInvPref').val($(this).attr('pref')).prop('disabled', false);
		$('#invPrefPopup button').button('enable');
		$('#invPrefPopup a').removeClass('opacity35');	
		// Determine where to put the box and then display it
		var cell = $(this).parent().parent();
		var cellpos = cell.offset();
		var invPrefPopup = $('#invPrefPopup');
		invPrefPopup.css({ 'left': cellpos.left - (invPrefPopup.outerWidth(true) - cell.outerWidth(true))/2, 'top': cellpos.top + cell.outerHeight(true) - 6 });
		invPrefPopup.fadeIn('slow');
	});
}

// Change participant's delivery preference for Twilio voice/SMS
function changeInvPref(survey_id, event_id) {
	var participant_id = $('#partInvPrefPartId').val();
	var delivery_preference = $('#partInvPref').val();
	$.post(app_path_webroot+"Surveys/change_delivery_preference.php?pid="+pid,{ delivery_preference: delivery_preference, 
		record: $('#partInvPrefRecord').val(), participant_id: participant_id, survey_id: survey_id, event_id: event_id },function(data) {
		if (data == '' || data == '0') {
			alert(woops);
		} else {
			var this_image = $('#editinvpref_'+participant_id);
			this_image.html(data).attr('pref', delivery_preference);
			enableEditInvPref(this_image);
			$('#partInvPrefSaved').show();
			setTimeout(function(){
				$('#invPrefPopup').fadeOut('slow');
			},1000);
			$('#partInvPref').prop('disabled', true);
			$('#invPrefPopup button').button('disable');
			$('#invPrefPopup a').addClass('opacity35');
		}
	});
}