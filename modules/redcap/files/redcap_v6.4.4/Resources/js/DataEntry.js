// On pageload
$(function(){

	// Fixes for CSS issues in IE
	if (isIE && vIE() <= 7) {
		// For IE6&7, deal with table cell width issues.
		var dtable = document.getElementById('form_table');
		var dtableWidth = $('#form_table').width();
		for (var i=0; i<dtable.rows.length; i++) {
			var thistrow = dtable.rows[i];
			if (thistrow.cells.length < 2) {
				var targetcell = thistrow.cells.length - 1;
				$(thistrow.cells[targetcell]).width(dtableWidth);
			}
		}
	}
	
	// Make all text fields submit form when click Enter on them
	$(':input').keydown(function(e) {
		if (this.type == 'checkbox' && e.which == 13) {
			return false;
		} else if (this.type == 'text' && e.which == 13) {
			// First check secondary id field (if exists on page) and don't allow form submission since we need to wait for ajax response
			if (secondary_pk != '' && $('#form input[name="'+secondary_pk+'"]').length && this.name == secondary_pk) {
				$('#form input[name="'+secondary_pk+'"]').trigger('blur');
				return false;
			} else {
				// Make sure we validate the field first, if has validation, before submitting the form. This will not fix the value in
				// all cases if the value has incorrect format, but it will sometimes.
				$(this).trigger('blur');
				// Submit form normally when pressing Enter key in text field
				if ($('#field_validation_error_state').val() == '0') {
					dataEntrySubmit($('form#form input[name="submit-btn-saverecord"]'));
				}
			}
		}
	});
	
	// Survey responses: Add 'tooltip' popup for user list of those who contributed to a survey response		
	$('.resp_users_contribute').tooltip({
		tip: '#tooltip',
		tipClass: 'tooltip4',
		position: 'top center',
		delay: 0
	});
		
	// Scroll to position on page if scrollTop provided in query string
	var scrollTopNum = getParameterByName('scrollTop');
	if (isNumeric(scrollTopNum)) $(window).scrollTop(scrollTopNum);
	
	// Enable green row highlight for data entry form table
	enableDataEntryRowHighlight();
	
	// Open Save button tooltip	fixed at top-right of data entry forms
	displayFormSaveBtnTooltip();
	
	// PUT FOCUS ON FIRST FIELD IN FORM (but not if we're putting focus on another field first)
	setTimeout(function(){ // Do a slight delay to deal with some issues where jQuery will be moved fields around after this (e.g., randomization button)
		// Do not do this for the mobile view since it causes the keyboard to open on text fields
		if (page != 'Mobile/data_entry.php' && getParameterByName('fldfocus') == '') {
			$('form#form input:visible, form#form textarea:visible, form#form select:visible, form#form a.fileuploadlink:visible').each(function(){
				var thisfld = $(this);
				// Skip the DAG drop-down and skip calc fields
				if (thisfld.attr('name') != '__GROUPID__' && !(thisfld.attr('type') == 'text' && thisfld.attr('readonly') == 'readonly')) {
					try {
						$(this).trigger('focus');
					} catch (e) { }
					return false;
				}
			});
		}
	},10);
	
	// Hide or disable fields if using special annotation
	triggerInstrumentAnnotations();
	
	// If user modifies any values on the data entry form, set flag to TRUE
	$('form#form').change(function(e){
		dataEntryFormValuesChanged = true;
	});	
	
	// If user tries to navigate off page after modifying any values on the data entry form, then stop and prompt user if they really want to leave page
	$('a').click(function(e){
		// If form values have changed...
		if (dataEntryFormValuesChanged) {
			// Ignore if has 'rc_attach' class, which is an attachment link
			if ($(this).hasClass('rc_attach')) {
				// Temporarily set to false, then back to true right afterward (to allow us to bypass the window.onbeforeunload function that would otherwise catch it)
				dataEntryFormValuesChanged = false;
				setTimeout(function(){
					dataEntryFormValuesChanged = true;
				}, 1000);
				return true;
			}
			// If is not a proper link but is mailto: or javascript:, then stop here
			var link = this;
			var href = trim(link.href.toLowerCase());
			var target = ($(link).attr('target') == null) ? '' : trim($(link).attr('target').toLowerCase());
			if (href != '#' && href.indexOf(window.location.href.toLowerCase()+'#') !== 0 && href.indexOf('javascript:') !== 0 
				&& href.indexOf('mailto:') !== 0 && target != '_blank') {
				// Prevent navigating to page
				e.preventDefault();
				// Display confirmation dialog
				$('#stayOnPageReminderDialog').dialog({ bgiframe: true, modal: true, width: 600, 
					title: '<img src="'+app_path_images+'exclamation_red.png" style="vertical-align:middle;"> <span style="color:#800000;vertical-align:middle;">'+langDlgSaveDataTitle+'</span>',
					buttons: [{
						text: langStayOnPage,
						click: function() { $(this).dialog("close"); }
					},{
						text: langLeavePage,
						"class": 'dataEntryLeavePageBtn',
						click: function() { 
							// Disable the onbeforeunload so that we don't get an alert before we leave
							window.onbeforeunload = function() { }
							// Redirect to next page
							window.location.href = link.href;
						}
					},{
						text: langSaveLeavePage,
						"class": 'dataEntrySaveLeavePageBtn',
						click: function() { 
							// Add element to form to denote how to redirect after saving
							appendHiddenInputToForm('save-and-redirect',link.href);
							// Save form
							dataEntrySubmit($('form#form input[name="submit-btn-savecontinue"]'));
							return false;
						}
					}]
				});
			}
		}
	});	
		
});

// If user tries to close the page after modifying any values on the data entry form, then stop and prompt user if they really want to leave page
window.onbeforeunload = function() {
	// If form values have changed...
	if (dataEntryFormValuesChanged) {
		var separator = "#########################################\n";
		// Prompt user with confirmation
		return separator + langDlgSaveDataTitleCaps + "\n\n" + langDlgSaveDataMsg + "\n" + separator;
	}
}