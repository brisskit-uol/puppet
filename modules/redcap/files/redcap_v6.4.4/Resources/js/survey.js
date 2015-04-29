
// For IE8, even though large images are resized, table spills off page, so manually resize picture, which fixes table.
function resizeImgIE() {
	$('#form image').each(function(){
		if ($(this).prop('src').indexOf('__passthru=') > -1) {
			var width = $(this).width();
			// For some reason, images may get initially set to 75 pixels and get stuck there, so ignore them so that
			// they display at their native size.
			if (width > 0 && width != 75) $(this).width(width);
		}
	});
}

$(function () 
{
	// Make section headers into toolbar CSS
	$('.header').addClass('toolbar');
	
	// Prevent any auto-filling of text fields by browser methods
	$(':input[type="text"]').prop("autocomplete","off");
	
	// Remove ability to submit form via Enter button on keyboard
	$(':input').keypress(function(e) {
		if ((this.type == 'checkbox' || this.type == 'text') && e.which == 13) {
			return false;			
		}
	});	

	// Fixes for CSS issues in IE
	if (isIE) {
		if (vIE() > 7) {	
			// For IE8, even though large images are resized, table spills off page, so manually resize picture, which fixes table.
			resizeImgIE();
			// Re-run this again after 2 and 6 seconds (in case images load slowly)
			setTimeout("resizeImgIE()",2000);
			setTimeout("resizeImgIE()",6000);
		} else {
			// For IE6&7, deal with table cell width issues.
			var dtable = document.getElementById('form_table');
			for (var i=0; i<dtable.rows.length; i++) {
				var thistrow = dtable.rows[i];
				if (!$(thistrow).hasClass('hide')) {
					if (thistrow.cells.length < 3) {
						var targetcell = thistrow.cells.length - 1;
						$(thistrow.cells[targetcell]).width(750);
					}
				}
			}
		}
	}
	
	// Hide or disable fields if using special annotation
	triggerInstrumentAnnotations();
	
	// Bubble pop-up for Return Code widget
	if ($('.bubbleInfo').length) {
		$('.bubbleInfo').each(function () {
			var distance = 10;
			var time = 250;
			var hideDelay = 500;
			var hideDelayTimer = null;
			var beingShown = false;
			var shown = false;
			var trigger = $('.trigger', this);
			var info = $('.popup', this).css('opacity', 0);
			$([trigger.get(0), info.get(0)]).mouseover(function (e) {
				if (hideDelayTimer) clearTimeout(hideDelayTimer);
				if (beingShown || shown) {
					// don't trigger the animation again
					return;
				} else {
					// reset position of info box
					beingShown = true;
					info.css({
						top: 0,
						right: 0,
						width: 300,
						display: 'block'
					}).animate({
						top: '+=' + distance + 'px',
						opacity: 1
					}, time, 'swing', function() {
						beingShown = false;
						shown = true;
					});
				}
				return false;
			}).mouseout(function () {
				if (hideDelayTimer) clearTimeout(hideDelayTimer);
				hideDelayTimer = setTimeout(function () {
					hideDelayTimer = null;
					info.animate({
						top: '-=' + distance + 'px',
						opacity: 0
					}, time, 'swing', function () {
						shown = false;
						info.css('display', 'none');
					});

				}, hideDelay);

				return false;
			});
		});
	}
});

// Display the Survey Login dialog (login form)
function displaySurveyLoginDialog() {
	$('#survey_login_dialog').dialog({ bgiframe: true, modal: true, width: (isMobileDevice ? $('body').width() : 670), open:function(){fitDialog(this);}, 
		close:function(){ window.location.href=window.location.href; },
		title: '<img src="'+app_path_images+'lock_big.png" style="vertical-align:middle;margin-right:2px;"><span style="color:#A86700;font-size:18px;vertical-align:middle;">'+langSurveyLoginForm4+'</span>', buttons: [
		{ text: langSurveyLoginForm1, click: function () { 
			// Make sure enough inputs were entered
			var numValuesEntered = 0;
			$('#survey_auth_form input[type="text"]').each(function(){
				var thisval = trim($(this).val());
				if (thisval != '') numValuesEntered++;
			});
			// If not enough values entered, give error message
			if (numValuesEntered < survey_auth_min_fields) {
				simpleDialog(langSurveyLoginForm2, langSurveyLoginForm3);
				return;
			}
			// Submit form
			$('#survey_auth_form').submit(); 
		} }] });
	// If there are no login fields displayed in the dialog, then remove the "Log In" button
	if ($('#survey_auth_form table.form_border tr').length == 0) {
		$('#survey_login_dialog').parent().find('div.ui-dialog-buttonpane').hide();
	} 
	// Add extra style to the "Log In" button
	else {
		$('#survey_login_dialog').parent().find('div.ui-dialog-buttonpane button').css({'font-weight':'bold','color':'#444','font-size':'15px'});
	}
}

// Send confirmation message to respondent after they provide their email address
function sendConfirmationEmail(record) {
	showProgress(1,100);
	$.post(dirname(dirname(app_path_webroot))+"/surveys/index.php?s="+getParameterByName('s')+"&__passthru="+encodeURIComponent("Surveys/email_participant_confirmation.php"),{ record: record, email: $('#confirmation_email_address').val() },function(data){
		showProgress(0,0);
		if (data == '0') {
			alert(woops);
		} else {
			simpleDialog(data,null,null,350);
			$('#confirmation_email_sent').show();
		}
	});
}