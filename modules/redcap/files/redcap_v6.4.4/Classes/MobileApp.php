<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/


/**
 * MOBILEAPP Class
 * Contains methods used with regard to the REDCap Mobile App
 */
class MobileApp
{

	// Return true if user has had at least one logged activity for the app (e.g. initialized the project in the app)
	public static function userHasInitializedProjectInApp($username, $project_id)
	{
		$sql = "select 1 from redcap_mobile_app_log l, redcap_log_event e
				where l.project_id = $project_id and l.project_id = e.project_id and e.log_event_id = l.log_event_id 
				and e.user = '".prep($username)."' limit 1";
		$q = db_query($sql);
		return (db_num_rows($q) == '1');
	}
	
	
	// Return array of all logged app activity for a given project
	public static function getAppActivity($project_id)
	{	
		$rows = array();
		$sql = "select e.ts, l.event, l.details, e.user, e.event as event_type 
				from redcap_mobile_app_log l, redcap_log_event e
				where l.project_id = $project_id and e.log_event_id = l.log_event_id 
				order by l.mal_id desc";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$rows[] = $row;
		}
		return $rows;
	}
	
	
	// Return array of all files associated with mobile app for a project (escape hatch, logging, etc.)
	public static function getAppArchiveFiles($project_id, $doc_id=null)
	{		
		// Get list of files
		$files = array();
		$subsql = (is_numeric($doc_id)) ? "and a.doc_id = $doc_id" : "";
		$sql = "select a.*, e.doc_name, e.stored_date, e.doc_size, i.username, concat(i.user_firstname, ' ', i.user_lastname) as name
				from redcap_edocs_metadata e, redcap_mobile_app_files a 
				left join redcap_user_information i on a.user_id = i.ui_id
				where e.project_id = $project_id and e.doc_id = a.doc_id and e.delete_date is null $subsql
				order by e.stored_date desc";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			if (is_numeric($doc_id)) {
				return $row;
			}
			$files[$row['af_id']] = $row;
		}
		// Return ALL files
		return $files;
	}
	
	
	// Return html table all app-related files in app file archive for a given project
	public static function displayAppFileArchiveTable($project_id)
	{
		global $lang;
		// Get list of all app-related files
		$appFiles = self::getAppArchiveFiles($project_id);
		// Loop through files
		$row_data = array();
		foreach ($appFiles as $attr) {
			$filesize_kb = 	round(($attr['doc_size'])/1024,2);
			$description = 	RCView::div(array('style'=>'font-size:12px;font-weight:bold;'),
								$attr['doc_name']
							) .
							RCView::div(array('style'=>'margin-top:3px;color:#777;'),
								$lang['docs_56'] . " " . 
								RCView::span(array('style'=>'color:#800000;'),
									DateTimeRC::format_ts_from_ymd($attr['stored_date'])
								)
							) .
							($attr['username'] == '' ? '' :
								RCView::div(array('style'=>'color:#777;'),
									$lang['mobile_app_15'] . " " . 
									RCView::span(array('style'=>'color:#800000;'),
										$attr['username'] . " " . 
										$lang['leftparen'] . $attr['name'] . $lang['rightparen']
									)
								)
							) .
							RCView::div(array('style'=>'color:#777;'),
								"{$lang['docs_57']} $filesize_kb KB"
							);
			if ($attr['type'] == 'LOGGING') {
				$download_icon = 'download_file_log.gif';
				$type_name = $lang['mobile_app_18'];
			} else {
				$download_icon = 'download_csvexcel_raw.gif';
				$type_name = $lang['mobile_app_19'];
			}
			$row_data[] = array($description, 
								RCView::div(array('class'=>'wrap', 'style'=>'color:#666;'), $type_name),
								RCView::div(array('style'=>'margin:5px 0;'),
									RCView::a(array('href'=>APP_PATH_WEBROOT."DataEntry/file_download.php?pid=$project_id&doc_id_hash=".Files::docIdHash($attr['doc_id'])."&id=".$attr['doc_id'], 'target'=>'_blank'),
										RCView::img(array('src'=>$download_icon))
									)
								)
						  );
		}
		// Display note if table is empty
		if (empty($row_data)) {
			$row_data[] = array(RCView::div(array('style'=>'margin:10px 0;color:#888;'), $lang['docs_42']), '', '');
		}
		// Render the file list as a table
		$width = 606;
		// Set table headers and attributes
		$headers = array(
			array(420, $lang['mobile_app_16']),
			array(90, $lang['mobile_app_17'], 'center'),
			array(60, $lang['design_121'], 'center')
		);
		// Title
		$title = RCView::div(array('style'=>'color:#800000;font-size:13px;padding:5px;'), $lang['mobile_app_14']);
		// Build table html
		return renderGrid("mobile_app_file_list", $title, $width, 'auto', $headers, $row_data, true, false, false);
	}	
	
	
	// Return html table all logged app activity for a given project
	public static function displayAppActivityTable($project_id)
	{
		global $lang;
		// Get all log entries for this project
		$row_data = array();
		foreach (self::getAppActivity($project_id) as $row) {
			// Convert event to text
			if ($row['event'] == 'INIT_PROJECT') {
				$thisevent = RCView::div(array('style'=>'color:green;'), $lang['mobile_app_25']);
			} elseif ($row['event'] == 'REINIT_PROJECT') {
				$thisevent = RCView::div(array('style'=>'color:green;'), $lang['mobile_app_26']);
			} elseif ($row['event'] == 'INIT_DOWNLOAD_DATA' || $row['event'] == 'REINIT_DOWNLOAD_DATA') {
				$thisevent = RCView::div(array('style'=>'color:#000066;'), $lang['mobile_app_27']);
			} elseif ($row['event'] == 'SYNC_DATA') {
				$detailsText = '';
				if (is_numeric($row['details'])) {
					$isNewRecord = ($row['event_type'] == 'INSERT');
					if ($row['details'] == '1') {
						$detailsText2 = ($isNewRecord ? $lang['mobile_app_32'] : $lang['mobile_app_30']);
					} else {
						$detailsText2 = ($isNewRecord ? $lang['mobile_app_31'] : $lang['mobile_app_29']);
					}
					$detailsText2 = " ({$row['details']} $detailsText2)";
				}
				$thisevent = RCView::div(array('style'=>'color:#800000;'), 
								$lang['mobile_app_28'] . $detailsText2
							 );
			}
			// Add row to array
			$row_data[] = array(DateTimeRC::format_ts_from_ymd(DateTimeRC::format_ts_from_int_to_ymd($row['ts'])), 
								RCView::div(array('style'=>'margin:2px 0;'), $row['user']),
								$thisevent
						  );
		}
		// Display note if table is empty
		if (empty($row_data)) {
			$row_data[] = array(RCView::div(array('class'=>'wrap', 'style'=>'margin:10px 0;color:#888;'), $lang['mobile_app_23']), '', '');
		}
		// Render the file list as a table
		$width = 606;
		// Set table headers and attributes
		$headers = array(
			array(120, $lang['reporting_19'], 'center'),
			array(120, $lang['global_11'], 'center'),
			array(330, $lang['dashboard_21'])
		);
		// Title
		$title = RCView::div(array('style'=>'color:#800000;font-size:13px;padding:5px;'), $lang['mobile_app_22']);
		// Build table html and return it
		return renderGrid("mobile_app_log", $title, $width, 'auto', $headers, $row_data, true, false, false);
	}
	
	
	// Return html for app init page
	public static function displayInitPage()
	{
		global $lang;		
		// Generate validation code for REDCap App
		$h = RCView::p(array('style'=>''),
					$lang['mobile_app_38']
				);
		// Check if user has an API token
		$db = new RedCapDB();
		if (strlen($db->getAPIToken(USERID, PROJECT_ID)) == 32) {	
			// Display QR code and manual method
			$h .= 	RCView::p(array('style'=>'padding:0 0 5px;'), 
						$lang['mobile_app_39']
					) .
					RCView::div(array('style'=>'font-size:13px;padding:20px 0 10px;;font-weight:bold;'), 
						$lang['mobile_app_13']
					) .
					RCView::div(array('style'=>'margin:0 0 0 100px;'), 
						"<img style='height:190px;' onload=\"$(this).css('height','auto');\" src='".APP_PATH_WEBROOT."API/project_api_ajax.php?pid=".PROJECT_ID."&action=getAppCode&qrcode=1'>"
					) .			
					// Alternate Option: Get init code from Vanderbilt server
					RCView::div(array('style'=>'margin:15px 0 0 100px;'), 
						RCView::a(array('style'=>'text-decoration:underline;font-size:13px;', 'href'=>'javascript:;', 
							'onclick'=>"getAppCode();$(this).hide();$('#appCodeAltDiv').show();"),
							$lang['mobile_app_06']
						)
					) .
					RCView::div(array('id'=>'appCodeAltDiv', 'class'=>'p', 'style'=>'margin-top:20px;background-color:#f5f5f5;border:1px solid #ddd;padding:15px;display:none;'),
						RCView::div(array('style'=>'font-size:13px;padding:0 0 6px;font-weight:bold;'), 
							$lang['mobile_app_07']
						) .  
						RCView::div(array('style'=>'padding:0 0 20px;'), 
							$lang['mobile_app_08'] . " " .
							RCView::span(array('style'=>'color:#C00000;'),
								$lang['mobile_app_09']
							)
						) .
						RCView::div(array('id'=>'app_user_codes_div', 'style'=>''),
							RCView::span(array('style'=>'font-weight:bold;line-height:24px;'), 
								$lang['mobile_app_10'] . " "
							) .
							RCView::text(array('id'=>'app_code', 'readonly'=>'readonly', 'class'=>'staticInput', 'onclick'=>"this.select();", 
								'style'=>'margin-left:10px;letter-spacing:1px;color:#111;padding:6px 8px;font-size:16px;width:130px;color:#C00000;',
								'value'=>$lang['design_160']))
						) .
						RCView::div(array('id'=>'app_user_codes_timer_div', 'class'=>'red', 'style'=>'display:none;'),
							$lang['mobile_app_11']
						)
					) .
					// DELETE TOKEN (only display if user has at least initialized a project)
					(!MobileApp::userHasInitializedProjectInApp(USERID, PROJECT_ID) ? '' :
						RCView::div(array('class'=>'p', 'style'=>'margin-top:50px;background-color:#f5f5f5;border:1px solid #ddd;padding:15px;'),
							RCView::div(array('style'=>'font-size:13px;padding:0 0 6px;font-weight:bold;'), 
								$lang['mobile_app_34']
							) .
							RCView::div(array('style'=>''), 
								$lang['mobile_app_35'] . " " . $lang['mobile_app_36']
							) .
							RCView::div(array('style'=>'margin-top:10px;'), 
								RCView::button(array('class'=>'jqbuttonmed', 'style'=>'color:#800000;', 'onclick'=>"simpleDialog(null,null,'deleteTokenDialog',500,null,'".cleanHtml($lang['global_53'])."','deleteToken()','".cleanHtml($lang['mobile_app_34'])."');"), $lang['mobile_app_34'])
							)
						) .
						// Hidden delete dialog
						RCView::div(array('id'=>'deleteTokenDialog', 'title'=>$lang['edit_project_111'], 'class'=>'simpleDialog'), 
							$lang['edit_project_112'].
							(!MobileApp::userHasInitializedProjectInApp(USERID, PROJECT_ID) ? '' : 
								RCView::div(array('style'=>'margin-top:10px;font-weight:bold;color:#C00000;'), $lang['mobile_app_36']))
						)
					);
		} else {
			// User doesn't have API token yet. Tell them to get one.
			$h .= 	RCView::div(array('class'=>'yellow', 'style'=>''),
						RCView::img(array('src'=>'exclamation_orange.png', 'class'=>'imgfix')) . 
						$lang['mobile_app_33'] .
						RCView::div(array('style'=>'margin:15px 0 5px;'),
							RCView::button(array('class'=>'jqbuttonmed', 'onclick'=>"requestToken();"), 
								RCView::img(array('src'=>'phone.png', 'style'=>'vertical-align:middle;')) . 
								RCView::span(array('style'=>'vertical-align:middle;'), $lang['api_03'])
							)
						)
					);
			
		}
		
		/* 		
		// Accept validation code as REDCap App
		print	RCView::div(array('class'=>'darkgreen', 'style'=>'margin-top:5px;padding:10px;'),
					RCView::span(array('style'=>'font-weight:bold;color:#C00000;'), 
						"[Simulated REDCap App Interface]"
					) . 
					RCView::div(array('style'=>'margin:10px 0 2px;font-weight:bold;'),
						"Initialize project in app:"
					) .
					RCView::div(array('style'=>'margin:10px 0;'),
						"Initialization code: " . RCView::SP . RCView::SP . RCView::SP . RCView::SP . 
						RCView::text(array('id'=>'app_validation_code', 'class'=>'x-form-text x-form-field', 'style'=>'width:150px;', $getAppCodeBtnDisabled=>$getAppCodeBtnDisabled)) .
						RCView::button(array('id'=>'app_validation_code_btn', 'class'=>'jqbuttonmed', $getAppCodeBtnDisabled=>$getAppCodeBtnDisabled, 'onclick'=>"validateAppCode($('#app_validation_code').val());"), 
							"Validate"
						)
					) .
					RCView::div(array('style'=>'margin:10px 0;'),
						"REDCap URL: " .
						RCView::span(array('id'=>'app_redcap_url', 'style'=>'font-family:verdana;font-size:15px;color:#C00000;'), '') .
						RCView::br() . 
						"API token: " .
						RCView::span(array('id'=>'app_redcap_token', 'style'=>'font-family:verdana;font-size:15px;color:#C00000;'), '') .
						RCView::br() . 
						"Username: " .
						RCView::span(array('id'=>'app_redcap_username', 'style'=>'font-family:verdana;font-size:15px;color:#C00000;'), '') .
						RCView::br() . 
						"Project ID: " .
						RCView::span(array('id'=>'app_redcap_project_id', 'style'=>'font-family:verdana;font-size:15px;color:#C00000;'), '') .
						RCView::br() . 
						"Project Title: " .
						RCView::span(array('id'=>'app_redcap_project_title', 'style'=>'font-family:verdana;font-size:15px;color:#C00000;'), '')
					)
				);
		*/
		
		// Return html
		return $h;
	}
	
}
