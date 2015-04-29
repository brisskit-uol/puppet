<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

$isPromisInstrument = false;
if (isset($_GET['survey_id']))
{
	// Detect if any branching logic exists in survey. If so, disable question auto numbering.
	$hasBranching = Design::checkSurveyBranchingExists($Proj->surveys[$_GET['survey_id']]['form_name']);
	if ($hasBranching) $question_auto_numbering = false;
	// Determine if this survey is a PROMIS CAT
	$isPromisInstrument = PROMIS::isPromisInstrument($Proj->surveys[$_GET['survey_id']]['form_name']);
}

// Get current time zone, if possible
$timezoneText = "{$lang['survey_296']} <b>".getTimeZone()."</b>{$lang['survey_297']}<br/><b>" . DateTimeRC::format_user_datetime(NOW, 'Y-M-D_24', null, true) . "</b>{$lang['period']}";

// If there is an email attachment, then get the uploaded filename of the attachment
if (is_numeric($confirmation_email_attachment)) {
	$q = db_query("select doc_name, doc_size from redcap_edocs_metadata 
				   where delete_date is null and doc_id = ".prep($confirmation_email_attachment));
	// Set file size in MB
	$confirmation_email_attachment_size = round_up(db_result($q, 0, 'doc_size') / 1024 / 1024);
	$confirmation_email_attachment_filename = db_result($q, 0, 'doc_name') . " &nbsp;($confirmation_email_attachment_size MB)";
} else {
	$confirmation_email_attachment_filename = "";
}
?>

<form action="<?php echo $_SERVER['REQUEST_URI'] . ((isset($_GET['redirectInvite']) && $_GET['redirectInvite']) ? "&redirectInvite=1" : "") ?>" method="post" enctype="multipart/form-data">
	<table cellspacing="3" style="width:100%;">
		<?php if (PAGE == 'Surveys/edit_info.php') { ?>
		<!-- Make survey active or offline (only when editing surveys) -->
		<tr>
			<td colspan="3">
				<div id="survey_enabled_div" class="<?php echo($survey_enabled ? 'darkgreen' : 'red') ?>" style="max-width:800px;margin: -5px -10px 0px;font-size:12px;">
					<div style="float:left;width:214px;font-weight:bold;padding:5px 0 0 25px;">
						<?php echo $lang['survey_374'] ?>
					</div>
					<div style="float:left;">
						<img id="survey_enabled_img" class="imgfix" style="margin-right:5px;" src="<?php echo APP_PATH_IMAGES . ($survey_enabled ? "accept.png" : "delete.png") ?>">
						<select name="survey_enabled" class="x-form-text x-form-field" style="padding-right:0;height:22px;margin-bottom:3px;"
							onchange="if ($(this).val()=='1'){ $('#survey_enabled_img').attr('src',app_path_images+'accept.png');$('#survey_enabled_div').removeClass('red').addClass('darkgreen'); } else { $('#survey_enabled_img').attr('src',app_path_images+'delete.png');$('#survey_enabled_div').removeClass('darkgreen').addClass('red'); }">
							<option value="1" <?php echo ( $survey_enabled ? 'selected' : '') ?>><?php echo $lang['survey_376'] ?></option>
							<option value="0" <?php echo (!$survey_enabled ? 'selected' : '') ?>><?php echo $lang['survey_375'] ?></option>
						</select><br>
						<span class="newdbsub" style="margin-left:26px;"><?php echo $lang['survey_377'] ?></span>
					</div>
					<div class="clear"></div>
				</div>
			</td>
		</tr>
		<?php } ?>
		<tr>
			<td colspan="3">
				<div class="header" style="padding:7px 10px 5px;margin:-5px -10px 10px;"><?php echo $lang['survey_291'] ?></div>
			</td>
		</tr>
		<tr>
			<td valign="top" style="width:20px;">
			</td>
			<td valign="top" style="width:220px;font-weight:bold;">
				<?php echo $lang['survey_49'] ?>
			</td>
			<td valign="top" style="padding-left:15px;padding-bottom:5px;">
				<input name="title" type="text" value="<?php echo str_replace('"', '&quot;', label_decode($title)) ?>" class="x-form-text x-form-field" style="width:80%;" onkeydown="if(event.keyCode==13){return false;}">
				<div class="newdbsub">
					<?php echo $lang['survey_50'] ?>
				</div>
			</td>
		</tr>
		
		<!-- Logo -->
		<tr>
			<td valign="top" style="width:20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>picture.png">
			</td>
			<td valign="top" style="width:220px;font-weight:bold;padding:0 0 10px 0;">
				<?php echo $lang['survey_59'] ?>
				<div style="font-weight:normal;">
					<i><?php echo $lang['survey_60'] ?></i>
				</div>
			</td>
			<td valign="top" style="padding:0 0 10px 0;padding-left:15px;padding-bottom:25px;">	
				<input type="hidden" name="old_logo" id="old_logo" value="<?php echo $logo ?>">
				<div id="old_logo_div" style="font-family:tahoma;color:#555;font-size:11px;display:<?php echo (!empty($logo) ? "block" : "none") ?>">
					<?php echo $lang['survey_61'] ?> &nbsp;
					<a href="javascript:;" style="font-family:tahoma;font-size:10px;color:#800000;text-decoration:none;" onclick='
						if (confirm("<?php echo cleanHtml(cleanHtml2($lang['survey_757'])) ?>")) {
							$("#new_logo_div").css({"display":"block"});
							$("#old_logo_div").css({"display":"none"});
							$("#old_logo").val("");
						}
					'>[X] <?php echo $lang['survey_62'] ?></a>
					<br>
					<img src="<?php echo APP_PATH_WEBROOT ?>DataEntry/image_view.php?pid=<?php echo $project_id ?>&doc_id_hash=<?php echo Files::docIdHash($logo) ?>&id=<?php echo $logo ?>" alt="[IMAGE]" title="[IMAGE]" style="max-width:500px; expression(this.width > 500 ? 500 : true);">
				</div>
				<div id="new_logo_div" style="font-family:tahoma;color:#555;font-size:11px;display:<?php echo (empty($logo) ? "block" : "none") ?>">
					<?php echo $lang['survey_63'] ?><br>
					<input type="file" name="logo" id="logo_id" size="50" onchange="checkLogo(this.value);">
					<div style="color:#777;font-family:tahoma;font-size:10px;padding:2px 0 0;">
						<?php echo $lang['design_198'] ?>
					</div>
				</div>
				<div id="hide_title_div" style="font-size:11px;padding-top:2px;">
					<input type="checkbox" name="hide_title" id="hide_title" class="imgfix2" <?php echo ($hide_title ? "checked" : "") ?>> 
					<?php echo $lang['survey_64'] ?>
				</div>
			</td>
		</tr>
		
		<!-- Instructions -->
		<tr>
			<td valign="top" style="width:20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>page_white_text.png">
			</td>
			<td valign="top" style="width:220px;font-weight:bold;">
				<?php echo $lang['survey_65'] ?>
				<div style="font-weight:normal;">
					<i><?php echo $lang['survey_66'] ?></i>
				</div>
			</td>
			<td valign="top" style="padding-left:15px;padding-bottom:15px;">
				<textarea style="width:90%;height:180px;" name="instructions"><?php echo $instructions ?></textarea>
				<!-- Piping link -->
				<div style="margin:5px 0 0;">
					<img src="<?php echo APP_PATH_IMAGES ?>pipe.png" class="imgfix">
					<a href="javascript:;" style="font-weight:normal;color:#3E72A8;text-decoration:underline;" onclick="pipingExplanation();"><?php echo $lang['design_463'] ?></a>
				</div>
			</td>
		</tr>
		
		
		
		<!-- Survey Customizations -->
		<tr>
			<td colspan="3">
				<div class="header" style="padding:7px 10px 5px;margin:0 -10px 10px;"><?php echo $lang['survey_647'] ?></div>
			</td>
		</tr>
		<?php 
		if ($isPromisInstrument) {
			print	RCView::tr(array(),
						RCView::td(array('colspan'=>3, 'style'=>'padding:5px 0 10px;'),
							RCView::div(array('colspan'=>3, 'class'=>'darkgreen', 'style'=>'margin:0 20px;padding:5px 8px 8px;'),
								RCView::div(array('style'=>'font-weight:bold;margin:3px 0;color:green;'),
									RCView::img(array('src'=>'flag_green.png', 'style'=>'vertical-align:middle;')) .
									RCView::span(array('style'=>'vertical-align:middle;'), $lang['survey_557'])
								) .
								RCView::div(array('style'=>'margin-bottom:10px;'),
									$lang['data_entry_220']
								) .
								RCView::div(array('style'=>'font-weight:bold;'),
									$lang['survey_556'] .
									RCView::select(array('class'=>'x-form-text x-form-field', 'style'=>'margin:0 4px 0 30px;padding-right:0;height:22px;', 'name'=>'promis_skip_question'),
										array(0=>$lang['design_99'], 1=>$lang['design_100']),
										$promis_skip_question
									) .
									RCView::span(array('style'=>'font-weight:normal;font-size:10px;font-family:tahoma;'),
										$lang['survey_558']
									)
								)
							)
						)
					);
		} 
		?>
		
		<tr id="question_auto_numbering-tr">
			<td valign="top" style="width:20px;">
			</td>
			<td valign="top" style="width:220px;font-weight:bold;">
				<?php echo $lang['survey_51'] ?>
			</td>
			<td valign="top" style="padding-left:15px;padding-bottom:15px;">
				<select name="question_auto_numbering" <?php if ($hasBranching) echo "disabled" ?> class="x-form-text x-form-field" style="padding-right:0;height:22px;">
					<option value="1" <?php echo ( $question_auto_numbering ? 'selected' : '') ?>><?php echo $lang['survey_52'] ?></option>
					<option value="0" <?php echo (!$question_auto_numbering ? 'selected' : '') ?>><?php echo $lang['survey_53'] ?></option>
				</select>
				<?php if ($hasBranching) { ?>
					<div style="color:red;font-size:9px;font-family:tahoma;">
						<?php echo $lang['survey_06'] ?>
					</div>
				<?php } ?>
			</td>
		</tr>
		
		<tr id="question_by_section-tr">
			<td valign="top" style="width:20px;">
			</td>
			<td valign="top" style="width:220px;font-weight:bold;">
				<?php echo $lang['survey_54'] ?>
				<div style="font-weight:normal;"><i><?php echo $lang['survey_645'] ?></i></div>
			</td>
			<td valign="top" style="padding-left:15px;padding-bottom:10px;">
				<select name="question_by_section" class="x-form-text x-form-field" style="padding-right:0;height:22px;" onchange="
					// Uncheck edit completed response checkbox if set to No
					if (this.value == '0') {
						$('input[name=display_page_number], input[name=hide_back_button]').prop('checked', false);
						$('#display_page_number-div, #hide_back_button-div').addClass('opacity35');
					} else {
						$('#display_page_number-div, #hide_back_button-div').removeClass('opacity35');
					}
				">
					<option value="0" <?php echo (!$question_by_section ? 'selected' : '') ?>><?php echo $lang['survey_55'] ?></option>
					<option value="1" <?php echo ( $question_by_section ? 'selected' : '') ?>><?php echo $lang['survey_56'] . " " . $lang['survey_646'] ?></option>
				</select>
				<?php
				// Display the page number?
				$display_page_number_checked = ($question_by_section && $display_page_number) ? "checked" : "";
				$display_page_number_opacity = ($question_by_section) ? "" : "opacity35";
				print 	RCView::div(array('id'=>'display_page_number-div', 'style'=>'margin:5px 0;color:#333;', 'class'=>$display_page_number_opacity),
							RCView::checkbox(array('name'=>'display_page_number', 'class'=>'imgfix2', $display_page_number_checked=>$display_page_number_checked)) .
							$lang['survey_644']
						);
				// Display the BACK button
				$hide_back_button_checked = ($question_by_section && $hide_back_button) ? "checked" : "";
				$hide_back_button_opacity = ($question_by_section) ? "" : "opacity35";
				print 	RCView::div(array('id'=>'hide_back_button-div', 'style'=>'margin:5px 0;color:#333;', 'class'=>$hide_back_button_opacity),
							RCView::checkbox(array('name'=>'hide_back_button', 'class'=>'imgfix2', $hide_back_button_checked=>$hide_back_button_checked)) .
							$lang['survey_750'] .
							RCView::div(array('style'=>'margin-left: 1.8em;color:#888;font-size:11px;'),
								$lang['survey_751']
							)
						);
				?>
			</td>
		</tr>
		
		<tr>
			<td valign="top" style="width:20px;">
			</td>
			<td valign="top" style="width:220px;font-weight:bold;">
				<?php echo $lang['survey_752'] ?>
			</td>
			<td valign="top" style="padding-left:15px;padding-bottom:15px;">
				<select name="show_required_field_text" class="x-form-text x-form-field" style="padding-right:0;height:22px;">
					<option value="0" <?php echo (!$show_required_field_text ? 'selected' : '') ?>><?php echo $lang['design_99'] ?></option>
					<option value="1" <?php echo ($show_required_field_text  ? 'selected' : '') ?>><?php echo $lang['design_100'] ?></option>
				</select>
				<div class="cc_info" style="">
					<?php echo $lang['survey_753'] ?>
					<span class="reqlbl">* <?php echo $lang['data_entry_39'] ?></span>
				</div>
			</td>
		</tr>
		
		<!-- View Results -->
		<?php if ($enable_plotting_survey_results) { ?>
		<tr id="view_results-tr">
			<td valign="top" style="width:20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>chart_bar.png">
			</td>
			<td valign="top" style="width:220px;font-weight:bold;padding:0 0 10px 0;">
				<?php echo $lang['survey_184'] ?>				
				<div style="margin-top:5px;color:#666;font-family:tahoma,arial;font-size:9px;font-weight:normal;">
					<?php echo $lang['survey_185'] ?>
				</div>
			</td>
			<td valign="top" style="padding:0 0 10px 0;padding-left:15px;padding-bottom:25px;">			
				<table cellpadding=0 cellspacing=0>
					<tr>
						<td colspan="2" valign="top" style="padding-bottom:15px;">
							<select id="view_results" name="view_results" class="x-form-text x-form-field" style="padding-right:0;height:22px;"
								onchange="if (this.value != '0' && $('#survey_termination_options_url').prop('checked')){ setTimeout(function(){ $('#view_results').val('0'); },10);simpleDialog('<?php echo cleanHtml2($lang['survey_303']) ?>','<?php echo cleanHtml2($lang['survey_302']) ?>');}">
								<option value="0" <?php echo ($view_results == '0' ? 'selected' : '') ?>><?php echo $lang['global_23'] ?></option>
								<!-- Plots only -->
								<option value="1" <?php echo ($view_results == '1' ? 'selected' : '') ?>><?php echo $lang['survey_203'] ?></option>
								<!-- Stats only -->
								<option value="2" <?php echo ($view_results == '2' ? 'selected' : '') ?>><?php echo $lang['survey_204'] ?></option>
								<!-- Plots + Stats -->
								<option value="3" <?php echo ($view_results == '3' ? 'selected' : '') ?>><?php echo $lang['survey_205'] ?></option>
							</select>
						</td>
					</tr>
					<tr class="view_results_options">
						<td valign="top" colspan="3" style="color:#444;font-weight:bold;padding:2px 0 3px;">
							<?php echo $lang['survey_188'] ?>
						</td>
					</tr>
					<tr class="view_results_options">
						<td valign="top" style="text-align:right;padding:5px 0;">
							<input name="min_responses_view_results" type="text" value="<?php echo $min_responses_view_results ?>" class="x-form-text x-form-field" style="width:20px;" maxlength="4" onkeydown="if(event.keyCode==13){return false;}" onblur="redcap_validate(this,'1','9999','soft_typed','int')">
						</td>
						<td valign="top" style="padding:5px 0;padding-left:15px;color:#444;">
							<?php echo $lang['survey_187'] ?>
						</td>
					</tr>
					<tr class="view_results_options">
						<td valign="top" style="text-align:right;">
							<input type="checkbox" name="check_diversity_view_results" id="check_diversity_view_results" <?php echo ($check_diversity_view_results ? "checked" : "") ?>> 
						</td>
						<td valign="top" style="padding-left:15px;color:#444;">
							<?php echo $lang['survey_186'] ?><br>
							(<a href="javascript:;" style="text-decoration:underline;font-size:10px;font-family:tahoma;" onclick="
								$('#diversity_explain').dialog({ bgiframe: true, modal: true, width: 500, 
									buttons: { Okay: function() { $(this).dialog('close'); } } 
								});
							"><?php echo $lang['survey_189'] ?></a>)
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<?php } ?>
		
		
		
		<!-- Survey Access -->
		<tr>
			<td colspan="3">
				<div class="header" style="padding:7px 10px 5px;margin:0 -10px 10px;"><?php echo $lang['survey_293'] ?></div>
			</td>
		</tr>
		
		<!-- Survey Expiration -->
		<tr>
			<td valign="top" style="width:20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>calendar_exclamation.png">
			</td>
			<td valign="top" style="width:220px;font-weight:bold;">
				<?php echo $lang['survey_294'] ?>
				<div style="font-weight:normal;">
					<i><?php echo $lang['survey_295'] ?></i> 
					<a href="javascript:;" onclick="simpleDialog('<?php echo cleanHtml($lang['survey_299']) ?>','<?php echo cleanHtml($lang['survey_294']) ?>')"><img src="<?php echo APP_PATH_IMAGES ?>help.png" style="vertical-align:middle;"></a>
				</div>
			</td>
			<td valign="top" style="padding:0 0 5px 15px;">
				<input id="survey_expiration" name="survey_expiration" type="text" style="width:103px;" class="x-form-text x-form-field" 
					onblur="redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter)" 
					value="<?php echo $survey_expiration ?>" 
					onkeydown="if(event.keyCode==13){return false;}"
					onfocus="this.value=trim(this.value); if(this.value.length == 0 && $('.ui-datepicker:first').css('display')=='none'){$(this).next('img').trigger('click');}">
				<span class='df'><?php echo DateTimeRC::get_user_format_label() ?> H:M</span>
				<div class="cc_info">
					<?php echo $timezoneText ?>
				</div>
			</td>
		</tr>
		
		<?php
		// If SURVEY LOGIN is enabled for SELECTED surveys, then give choice to use Survey Login for this survey
		if ($survey_auth_enabled && !$survey_auth_apply_all_surveys) { ?>										
		<!-- Survey Login -->
		<tr>
			<td valign="top" style="width:20px;padding:10px 0;">
				<img src="<?php echo APP_PATH_IMAGES ?>key.png">
			</td>
			<td valign="top" style="width:220px;font-weight:bold;padding:10px 0;">
				<?php echo $lang['survey_618'] ?>
				<div style="font-weight:normal;">
					<i><?php echo $lang['survey_638'] ?></i> 
				</div>
			</td>
			<td valign="top" style="padding:10px 0;padding-left:15px;">
				<select name="survey_auth_enabled_single" class="x-form-text x-form-field" style="padding-right:0;height:22px;" onchange="
					if (this.value == '1') {
						$('#survey-login-note-save-return').show('fade');
					} else {
						$('#survey-login-note-save-return').hide('fade');
					}
				">
					<option value="0" <?php echo (!$survey_auth_enabled_single ? 'selected' : '') ?>><?php echo $lang['design_99'] ?></option>
					<option value="1" <?php echo ($survey_auth_enabled_single  ? 'selected' : '') ?>><?php echo $lang['design_100'] ?></option>
				</select>
				<div class="cc_info">
					<?php echo $lang['survey_636'] ?>
				</div>
				<?php 
				// If this survey is the first survey, add reminder that Survey Login won't work for Public Surveys or if the record doesn't exist yet
				if (is_numeric($survey_id) && $survey_id == $Proj->firstFormSurveyId) { ?>
					<div class="cc_info" style="margin-top:8px;color:#800000;">
						<?php echo $lang['survey_639'] ?>
					</div>
				<?php } ?>
			</td>
		</tr>
		<?php } else { ?>
			<input type="hidden" name="survey_auth_enabled_single" value="<?php echo $survey_auth_enabled_single ?>">
		<?php } ?>
		
		<!-- SAVE AND RETURN LATER -->
		<tr id="save_and_return-tr">
			<td valign="top" style="width:20px;padding:10px 0;">
				<img src="<?php echo APP_PATH_IMAGES ?>arrow_circle_315.png">
			</td>
			<td valign="top" style="width:220px;font-weight:bold;padding:10px 0;">
				<?php echo $lang['survey_57'] ?>
				<div style="font-weight:normal;">
					<i><?php echo $lang['survey_304'] ?></i> 
					<a href="javascript:;" onclick="simpleDialog('<?php echo cleanHtml($lang['survey_637']) ?>','<?php echo cleanHtml($lang['survey_57']) ?>')"><img src="<?php echo APP_PATH_IMAGES ?>help.png" style="vertical-align:middle;"></a>
				</div>
			</td>
			<td valign="top" style="padding:10px 0;padding-left:15px;">
				<select name="save_and_return" class="x-form-text x-form-field" style="padding-right:0;height:22px;" onchange="
					// Uncheck edit completed response checkbox if set to No
					if (this.value == '0') {
						$('input[name=edit_completed_response]').prop('checked', false);
					}
				">
					<option value="0" <?php echo (!$save_and_return ? 'selected' : '') ?>><?php echo $lang['design_99'] ?></option>
					<option value="1" <?php echo ($save_and_return  ? 'selected' : '') ?>><?php echo $lang['design_100'] ?></option>
				</select>
				<?php
				// Allow respondents to edit completed responses?
				$edit_completed_response_checked = ($save_and_return && $edit_completed_response) ? "checked" : "";
				print 	RCView::div(array('style'=>'font-weight:bold;margin-top:10px;color:#333;'),
							RCView::checkbox(array('name'=>'edit_completed_response', 'class'=>'imgfix2', $edit_completed_response_checked=>$edit_completed_response_checked, "onclick"=>"
								if ($(this).prop('checked') && $('select[name=save_and_return]').val() == '0') {
									$(this).prop('checked', false);
									simpleDialog('".cleanHtml($lang['survey_660'])."');
								}
							")) .
							$lang['survey_640'] .
							RCView::a(array('href'=>'javascript:;', 'style'=>'margin-left:3px;', 'onclick'=>"simpleDialog('".prep($lang['survey_643'])."','".prep($lang['survey_640'])."');"), 
								RCView::img(array('src'=>'help.png', 'class'=>'imgfix'))
							)
						);
				// If Survey Login is enabled for ALL surveys or JUST this one, then put note that Survey Login will be used instead of Return Codes
				print 	RCView::div(array('id'=>'survey-login-note-save-return', 'style'=>(($survey_auth_enabled && ($survey_auth_apply_all_surveys || $survey_auth_enabled_single)) ? '' : 'display:none;').'color:#865200;margin-top:10px;text-indent:-1.7em;margin-left:1.8em;'),
							RCView::img(array('src'=>'key.png', 'class'=>'imgfix', 'style'=>'top:6px;')) .
							($survey_auth_apply_all_surveys ? $lang['survey_617'] : $lang['survey_635'])
						);
				?>
			</td>
		</tr>
		
		
		<tr>
			<td colspan="3">
				<div class="header" style="padding:7px 10px 5px;margin:0 -10px 10px;"><?php echo $lang['survey_290'] ?></div>
			</td>
		</tr>	
		
		
		<!-- End Survey Redirect URL -->
		<tr>
			<td valign="top" style="width:20px;">
				<input type="radio" id="survey_termination_options_url" name="survey_termination_options" value="url" <?php echo ($end_survey_redirect_url != '' ? 'checked' : '') ?>
					onclick="$('#end_survey_redirect_url').focus();">
			</td>
			<td valign="top" style="width:220px;font-weight:bold;padding-bottom:3px;">
				<?php echo $lang['survey_288'] ?>
				<div style="font-weight:normal;">
					<i><?php echo $lang['survey_292'] ?></i>
				</div>
			</td>
			<td valign="top" style="padding-left:15px;">
				<input id="end_survey_redirect_url" name="end_survey_redirect_url" type="text" onblur="isUrlError(this);if(this.value==''){$('#survey_termination_options_text').prop('checked',true);}else if($('#view_results').val() != '0'){ $('#view_results').val('0');simpleDialog('<?php echo cleanHtml2($lang['survey_301']) ?>','<?php echo cleanHtml2($lang['survey_300']) ?>','',600); }" onfocus="$('#survey_termination_options_url').prop('checked',true);" value="<?php echo str_replace('"', '&quot;', label_decode($end_survey_redirect_url)) ?>" class="x-form-text x-form-field" style="width:88%;" onkeydown="if(event.keyCode==13){return false;}">
				<div class="cc_info" style="margin:0;color:#777;">
					<?php echo $lang['survey_289'] ?>
				</div>
				<!-- Piping link -->
				<div style="margin:5px 0 0;">
					<img src="<?php echo APP_PATH_IMAGES ?>pipe_small.gif" class="imgfix">
					<a href="javascript:;" style="font-size:11px;font-weight:normal;color:#3E72A8;text-decoration:underline;" onclick="pipingExplanation();"><?php echo $lang['design_463'] ?></a>
				</div>
			</td>
		</tr>
		
		<!-- OR -->
		<tr>
			<td valign="top" colspan="3" style="padding:2px 0px 12px 8px;color:#777;">
				&mdash; <?php echo $lang['global_46'] ?> &mdash;
			</td>
		</tr>
		
		<!-- Acknowledgement -->
		<tr>
			<td valign="top" style="width:20px;">
				<input type="radio" id="survey_termination_options_text" name="survey_termination_options" value="text" <?php echo ($end_survey_redirect_url == '' ? 'checked' : '') ?>>
			</td>
			<td valign="top" style="width:220px;font-weight:bold;">
				<?php echo $lang['survey_747'] ?>
				<div style="font-weight:normal;">
					<i><?php echo $lang['survey_748'] ?></i>
				</div>
			</td>
			<td valign="top" style="padding-left:15px;padding-bottom:20px;">
				<textarea style="width:90%;height:180px;" name="acknowledgement"><?php echo $acknowledgement ?></textarea>
				<!-- Piping link -->
				<div style="margin:5px 0 0;">
					<img src="<?php echo APP_PATH_IMAGES ?>pipe.png" class="imgfix">
					<a href="javascript:;" style="font-weight:normal;color:#3E72A8;text-decoration:underline;" onclick="pipingExplanation();"><?php echo $lang['design_463'] ?></a>
				</div>
			</td>
		</tr>
		
		<!-- Survey confirmation email -->
		<tr>
			<td colspan="3" style="padding-bottom:15px;">
				<div class="spacer" style="border-color:#ddd;max-width:800px;"> </div>
			</td>
		</tr>
		<tr>
			<td valign="top" style="width:20px;">
				<img src="<?php echo APP_PATH_IMAGES ?>email_go.png">
			</td>
			<td valign="top" style="width:220px;font-weight:bold;padding:0 0 10px 0;">
				<?php echo $lang['survey_755'] ?>
				<div style="font-weight:normal;">
					<i><?php echo $lang['survey_756'] ?></i>
				</div>
			</td>
			<td valign="top" style="padding:0 0 10px 0;padding-left:15px;padding-bottom:30px;">	
				<div style="">
					<select id="confirmation_email_enable" class="x-form-text x-form-field" style="padding-right:0;height:22px;" onchange='
						if ($(this).val() == "1") {
							$("#confirmation_email_parent_div").show("fade");
						} else {
							var confirmEmailVal = $("#confirmation_email_subject").val().length 
								+ $("#confirmation_email_content").val().length
								+ $("#confirmation_email_attachment").val().length
								+ $("#old_confirmation_email_attachment").val().length;
							if (confirmEmailVal == 0 || (confirmEmailVal > 0 && confirm("<?php echo cleanHtml(cleanHtml2($lang['survey_761'])) ?>"))) {
								$("#confirmation_email_parent_div, #old_confirmation_email_attachment_div").hide();
								$("#confirmation_email_subject, #confirmation_email_content, #confirmation_email_attachment, #old_confirmation_email_attachment").val("");
								$("#confirmation_email_attachment_div").show();
							}
						}
					'>
						<option value="0" <?php echo ($confirmation_email_content == '' ? "selected" : "") ?>><?php echo $lang['design_99'] ?></option>
						<option value="1" <?php echo ($confirmation_email_content != '' ? "selected" : "") ?>><?php echo $lang['design_100'] ?></option>
					</select>
				</div>
				<div id="confirmation_email_parent_div" style="padding-top:10px;margin-top:10px;border-top:1px dashed #ccc;display:<?php echo ($confirmation_email_content != '' ? "block" : "none") ?>;">
					<div style="margin-bottom:18px;font-size:11px;color:#666;padding-right:10px;">
						<?php echo $lang['survey_760'] ?>
						<!-- Piping link -->
						<span style="margin:0 0 0 10px;">
							<img src="<?php echo APP_PATH_IMAGES ?>pipe_small.gif" class="imgfix">
							<a href="javascript:;" style="font-size:11px;font-weight:normal;color:#3E72A8;text-decoration:underline;" onclick="pipingExplanation();"><?php echo $lang['design_463'] ?></a>
						</span>
					</div>
					<div style="margin-bottom:8px;">
						<span style="vertical-align:middle;margin-right:20px;"><?php echo $lang['global_37'] ?></span>
						<?php print User::emailDropDownListAllUsers($confirmation_email_from, true, 'confirmation_email_from', 'confirmation_email_from'); ?>
					</div>
					<div style="margin-bottom:8px;">
						<span style="vertical-align:middle;margin-right:8px;"><?php echo $lang['email_users_10'] ?></span>
						<input style="vertical-align:middle;width:350px;" id="confirmation_email_subject" name="confirmation_email_subject" type="text" value="<?php echo str_replace('"', '&quot;', label_decode($confirmation_email_subject)) ?>" class="x-form-text x-form-field" onkeydown="if(event.keyCode==13){return false;}">
					</div>
					<textarea class="x-form-field notesbox tinyNoEditor" style="font-family:arial;height:85px;width:95%;" id="confirmation_email_content" name="confirmation_email_content"><?php echo $confirmation_email_content ?></textarea>
					<div id="confirmation_email_attachment_div" style="margin-top:5px;display:<?php echo ($confirmation_email_attachment == '' ? "block" : "none") ?>;">
						<span style="vertical-align:middle;margin-right:5px;">
							<img src="<?php echo APP_PATH_IMAGES ?>attach.png" class="imgfix">
							<?php echo $lang['design_205'] ?>
						</span>
						<input style="vertical-align:middle;" type="file" id="confirmation_email_attachment" name="confirmation_email_attachment" size="50">					
						<input type="hidden" id="old_confirmation_email_attachment" name="old_confirmation_email_attachment" value="<?php echo $confirmation_email_attachment ?>">
					</div>
					<div id="old_confirmation_email_attachment_div" style="margin-top:5px;display:<?php echo ($confirmation_email_attachment != '' ? "block" : "none") ?>;">
						<span style="vertical-align:middle;margin-right:5px;">
							<img src="<?php echo APP_PATH_IMAGES ?>attach.png" class="imgfix">
							<?php echo $lang['design_205'] ?>
						</span>
						<a target="_blank" href="<?php echo APP_PATH_WEBROOT . "DataEntry/file_download.php?pid=$project_id&doc_id_hash=".Files::docIdHash($confirmation_email_attachment)."&id=$confirmation_email_attachment" ?>" style="vertical-align:middle;text-decoration:underline;"><?php print $confirmation_email_attachment_filename ?></a>
						<a href="javascript:;" class="nowrap" style="vertical-align:middle;margin-left:15px;font-family:tahoma;font-size:10px;color:#800000;" onclick='
							if (confirm("<?php echo cleanHtml(cleanHtml2($lang['survey_758'])) ?>")) {
								$("#confirmation_email_attachment_div").show();
								$("#old_confirmation_email_attachment_div").hide();
								$("#old_confirmation_email_attachment").val("");
							}
						'>[X] <?php echo $lang['survey_759'] ?></a>
					</div>
				</div>
			</td>
		</tr>
		
		<!-- Save Button -->
		<tr>
			<td colspan="2" style="border-top:1px solid #ddd;"></td>
			<td valign="middle" style="border-top:1px solid #ddd;padding:30px 0 30px 15px;">
				<input type="submit" style="font-size:14px;padding:5px 10px;" value=" <?php echo cleanHtml2($lang['report_builder_28']) ?> " onclick='
					$("#confirmation_email_subject").val( trim($("#confirmation_email_subject").val()) );
					$("#confirmation_email_content").val( trim($("#confirmation_email_content").val()) );
					var confirmEmailVal = ($("#confirmation_email_subject").val() != "" &&  $("#confirmation_email_content").val() != "");					
					if ($("#confirmation_email_enable").val() == "1" && !confirmEmailVal) {
						simpleDialog("<?php echo cleanHtml(cleanHtml2($lang['survey_762'])) ?>",null,null,null,function(){ $("#confirmation_email_subject").focus(); });
						return false;
					} else if ($("#confirmation_email_enable").val() == "0" && confirmEmailVal) {
						$("#confirmation_email_subject").val("");
						$("#confirmation_email_content").val("");
						$("#confirmation_email_attachment").val("");
					}
					return true;
				'>
			</td>
		</tr>
		
		<!-- Cancel/Delete buttons -->
		<tr>
			<td colspan="2" style="border-top:1px solid #ddd;"></td>
			<td valign="middle" style="border-top:1px solid #ddd;padding:10px 0 20px 15px;">
				<input type="button" style="color:#800000;" onclick="history.go(-1)" value=" -- <?php echo cleanHtml2($lang['global_53']) ?>-- "><br>
				<?php if (PAGE == 'Surveys/edit_info.php' && !$isPromisInstrument) { ?>
					<!-- Option to delete the survey (only when editing surveys - do NOT allow this for CATs since they only work in survey mode) -->
					<div style="margin-top:25px;">
						<input type="button" style="font-size:11px;color:#C00000;" onclick="deleteSurvey(<?php echo $_GET['survey_id'] ?>);" value=" <?php echo cleanHtml2($lang['survey_379']) ?> ">
					</div>
					<!-- Info about what deleting a survey does -->
					<div style="margin-top:7px;font-size:11px;color:#777;line-height:11px;">
						<?php echo RCView::b($lang['survey_379'].$lang['colon']) . ' ' . $lang['survey_381'] ?>
					</div>
				<?php } ?>
			</td>
		</tr>
		
	</table>
</form>

<!-- Hidden div for explaining the graphical diversity restriction setting -->
<div id="diversity_explain" style="display:none;" title="<?php echo cleanHtml2($lang['survey_189']) ?>">
	<p><?php echo "{$lang['survey_190']} <b>{$lang['survey_208']} <i style='color:#666;'>\"{$lang['survey_202']}\"</i></b>" ?></p>
	<p><?php echo $lang['survey_207'] ?></p>
</div>

<!-- Javascript needed -->
<script type="text/javascript">
// Check if need to disable View Survey Results sub-options
$(function(){
	checkViewResults();
	$('#view_results').change(function(){
		checkViewResults();
	});
	$('#survey_expiration').datetimepicker({
		buttonText: 'Click to select a date', yearRange: '-10:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
		hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time', 
		showOn: 'both', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'hh:mm', constrainInput: false
	});
});
function checkViewResults() {
	if ($('#view_results').val() == '0') {
		$('.view_results_options').fadeTo(0,0.3);
		$('.view_results_options input').attr('disabled', true);
	} else {
		$('.view_results_options').fadeTo(500,1);
		$('.view_results_options input').attr('disabled', false);
		$('.view_results_options input').removeAttr('disabled');
	}
}
// Delete the survey
function deleteSurvey(survey_id) {
	simpleDialog('<?php echo cleanHtml(RCView::div(array('style'=>'font-weight:bold;margin-bottom:10px;'), $lang['survey_381']).RCView::div(array('style'=>'margin-top:10px;color:red;'), RCView::b($lang['global_03'].$lang['colon']) . " " . $lang['survey_382'])) ?>','<?php echo cleanHtml($lang['survey_380']) ?>',null,600,null,"Cancel","deleteSurveySave("+survey_id+");",'<?php echo cleanHtml($lang['survey_379']) ?>');
}
function deleteSurveySave(survey_id) {
	$.post(app_path_webroot+'Surveys/delete_survey.php?pid='+pid+'&survey_id=<?php echo $_GET['survey_id'] ?>',{ },function(data){
		if (data != '1') {
			alert(woops);
		} else {
			simpleDialog('<?php echo cleanHtml($lang['survey_385']) ?>','<?php echo cleanHtml($lang['survey_384']) ?>',null,null,"window.location.href='"+app_path_webroot+"Design/online_designer.php?pid="+pid+"';");
		}
	});
}

<?php if ($isPromisInstrument) { ?>
	// For PROMIS CATs, disable certain settings that aren't usable
	$('tr#save_and_return-tr, tr#view_results-tr, tr#question_by_section-tr, tr#question_auto_numbering-tr').fadeTo(0,0.3);
	$('select[name="save_and_return"], select[name="view_results"], select[name="question_by_section"], select[name="question_auto_numbering"]').prop('disabled', true);

<?php } ?>
</script>