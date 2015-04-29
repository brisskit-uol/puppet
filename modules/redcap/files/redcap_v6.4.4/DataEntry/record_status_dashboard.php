<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . "ProjectGeneral/form_renderer_functions.php";

// Return first arm that a given record exists in
function getFirstArmRecord($this_record, $recordsPerArm) {
	// Loop through arms till we find it. If not found, default to arm 1.
	foreach ($recordsPerArm as $arm=>$records) {
		if (isset($records[$this_record])) return $arm;
	}
	return '1';
}

// Get list of all records
$recordNames = Records::getRecordList(PROJECT_ID, $user_rights['group_id'], false);
$numRecords = count($recordNames);

// Remove records from $formStatusValues array based upon page number
$num_per_page = 100;
$limit_begin  = 0;
if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum']) && $_GET['pagenum'] > 1) {
	$limit_begin = ($_GET['pagenum'] - 1) * $num_per_page;
} elseif (!isset($_GET['pagenum'])) {
	$_GET['pagenum'] = 1;
}

// Do not slice array if showall flag is in query string
if ($_GET['pagenum'] != 'ALL' && $numRecords > $num_per_page) {
	$recordNamesThisPage = array_slice($recordNames, $limit_begin, $num_per_page, true);
} else {
	$recordNamesThisPage = array(); // Set to empty array because it will be used as a function input
}

// Get form status of just this page's records
$formStatusValues = Records::getFormStatus(PROJECT_ID, $recordNamesThisPage);
$numRecordsThisPage = count($formStatusValues);

// If this is a longitudinal project with multiple arms, then fill array denoting which arm that a record belongs to
$recordsPerArm = ($multiple_arms) ? Records::getRecordListPerArm($recordNamesThisPage) : array();

// Obtain custom record label & secondary unique field labels for ALL records.
if ($multiple_arms) {
	$extra_record_labels = array();
	foreach ($recordsPerArm as $this_arm=>$these_records) {
		$extra_record_labels_temp = Records::getCustomRecordLabelsSecondaryFieldAllRecords(array_keys($these_records), false, $this_arm);
		// Loop through the results and add each record's label (we loop because we may have one label per arm, and so this will concatenate them all together)
		foreach ($extra_record_labels_temp as $this_record=>$this_label) {
			if (!isset($extra_record_labels[$this_record])) {
				$extra_record_labels[$this_record] = $this_label;
			} else {
				$extra_record_labels[$this_record] .= " " . $this_label;
			}
		}
	}
	unset($extra_record_labels_temp);
} else {
	$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords();// Obtain custom record label & secondary unique field labels for ALL records.
}

## LOCKING & E-SIGNATURES
$displayLocking = $displayEsignature = false;
// Check if need to display this info at all
$sql = "select display, display_esignature from redcap_locking_labels 
		where project_id = $project_id and form_name in (".prep_implode(array_keys($Proj->forms)).")";
$q = db_query($sql);
if (db_num_rows($q) == 0) {
	$displayLocking = true;
} else {	
	$lockFormCount = count($Proj->forms);
	$esignFormCount = 0;
	while ($row = db_fetch_assoc($q)) {
		if ($row['display'] == '0') $lockFormCount--;
		if ($row['display_esignature'] == '1') $esignFormCount++;
	}
	if ($esignFormCount > 0) {
		$displayLocking = $displayEsignature = true;
	} elseif ($lockFormCount > 0) {
		$displayLocking = true;
	}
}
// Get all locked records and put into an array
$locked_records = array();
if ($displayLocking) {
	$sql = "select record, event_id, form_name from redcap_locking_data 
			where project_id = $project_id and record in (".prep_implode(array_keys($formStatusValues)).")";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		$locked_records[$row['record']][$row['event_id']][$row['form_name']] = true;
	}
}
// Get all e-signed records and put into an array
$esigned_records = array();
if ($displayEsignature) {
	$sql = "select record, event_id, form_name from redcap_esignatures
			where project_id = $project_id and record in (".prep_implode(array_keys($formStatusValues)).")";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		$esigned_records[$row['record']][$row['event_id']][$row['form_name']] = true;
	}
}

// Build drop-down list of page numbers
$num_pages = ceil($numRecords/$num_per_page);	
$pageNumDropdownOptions = array('ALL'=>'-- '.$lang['docs_44'].' --');
for ($i = 1; $i <= $num_pages; $i++) {
	$end_num   = $i * $num_per_page;
	$begin_num = $end_num - $num_per_page + 1;
	$value_num = $end_num - $num_per_page;
	if ($end_num > $numRecords) $end_num = $numRecords;
	$pageNumDropdownOptions[$i] = "\"".removeDDEending($recordNames[$begin_num-1])."\" {$lang['data_entry_216']} \"".removeDDEending($recordNames[$end_num-1])."\"";
}
if ($num_pages == 0) {
	$pageNumDropdownOptions[0] = "0";
}
$pageNumDropdown =  RCView::div(array('class'=>'chklist','style'=>'padding:8px 15px 7px;margin:5px 0 20px;max-width:770px;'),
						$lang['data_entry_177'] . 
						RCView::select(array('class'=>'x-form-text x-form-field','style'=>'margin-left:8px;margin-right:4px;padding-right:0;height:22px;',
							'onchange'=>"showProgress(1);window.location.href=app_path_webroot+page+'?pid='+pid+'&pagenum='+this.value;"), 
							$pageNumDropdownOptions, $_GET['pagenum'], 500) .
						$lang['survey_133'].
						RCView::span(array('style'=>'font-weight:bold;margin:0 4px;font-size:13px;'), 
							User::number_format_user($numRecords)
						) .
						$lang['data_entry_173']
					);

// Determine if records also exist as a survey response for some instruments
$surveyResponses = array();
if ($surveys_enabled) {
	$surveyResponses = Survey::getResponseStatus($project_id, array_keys($formStatusValues));
}

// Determine if Real-Time Web Service is enabled, mapping is set up, and that this user has rights to adjudicate
$showRTWS = ($DDP->isEnabledInSystem() && $DDP->isEnabledInProject() && $DDP->userHasAdjudicationRights());

// If RTWS is enabled, obtain the cached item counts for the records being displayed on the page
if ($showRTWS)
{
	// Collect records with cached data into array with record as key and last fetch timestamp as value
	$records_with_cached_data = array();
	$sql = "select r.record, r.item_count from redcap_ddp_records r
			where r.project_id = $project_id and r.record in (" . prep_implode(array_keys($formStatusValues)) . ")";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		if ($row['item_count'] === null) $row['item_count'] = ''; // Avoid null values because isset() won't work with it as an array value
		$records_with_cached_data[$row['record']] = $row['item_count'];
	}
}


/* 
DON'T ADD THIS FEATURE YET! Issues with floating clone table and also with drop-down list of records for paging (i.e this is complicated)

// If using Order Records By feature, then order records by that field's value instead of by record name
if (!$longitudinal && $order_id_by != '')
{
	// Get all values for the Order Records By field
	$order_id_by_records = Records::getData('array', array_keys($formStatusValues), $order_id_by);
	// Isolate values only into separate array
	$order_id_by_values = $recordList = array();
	foreach ($formStatusValues as $this_record=>$this_event_data) {
		// Add record names to array to deal with multisort reindexing numeric keys (!)
		$recordList[] = $this_record;
		// Loop through each event
		foreach ($this_event_data as $this_event_id=>$these_fields_data) {
			// If record has value, then add it, otherwise add blank value as placeholder
			if (isset($order_id_by_records[$this_record][$this_event_id][$order_id_by])) {
				$order_id_by_values[$this_record] = $order_id_by_records[$this_record][$this_event_id][$order_id_by];
			} else {
				$order_id_by_values[$this_record] = "";
			}
		}
	}
	// Now sort $formStatusValues by values in $order_id_by_values
	array_multisort($order_id_by_values, SORT_STRING, $recordList, SORT_STRING, $formStatusValues);
	// Fix the array indexes (since they got lost) and also move all blank values to very end of array (since they get ordered as first)
	$formStatusValues2 = $formStatusValuesEmptyOrderIdBy = array();
	foreach ($recordList as $key=>$this_record) {
		// Get all event data for this record
		$record_data = $formStatusValues[$key];
		// Remove record data from original array (since we are moving it)
		unset($formStatusValues[$key]);
		// If Order Records By value is blank, add to $formStatusValuesEmptyOrderIdBy
		if ($order_id_by_values[$key] == "") {
			$formStatusValuesEmptyOrderIdBy[$this_record] = $record_data;
		} else {
			// Add to new array
			$formStatusValues2[$this_record] = $record_data;
		}
	}
	// Merge arrays
	foreach ($formStatusValuesEmptyOrderIdBy as $this_record=>$record_data) {
		$formStatusValues2[$this_record] = $record_data;
		unset($formStatusValuesEmptyOrderIdBy[$this_record]);
	}
	// Remove all unnecessary arrays to clear up memory
	$formStatusValues = $formStatusValues2;
	unset($order_id_by_records, $order_id_by_values, $formStatusValuesEmptyOrderIdBy, $formStatusValues2);
}
 */
 

// Obtain a list of all instruments used for all events (used to iterate over header rows and status rows)
$formsEvents = array();
// Loop through each event and output each where this form is designated
foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
	// Loop through forms
	foreach ($these_forms as $form_name) {
		// If user does not have form-level access to this form, then do not display it
		if (!isset($user_rights['forms'][$form_name]) || $user_rights['forms'][$form_name] < 1) continue;
		// Add to array
		$formsEvents[] = array('form_name'=>$form_name, 'event_id'=>$this_event_id, 'form_label'=>$Proj->forms[$form_name]['menu']);
	}
}



// HEADERS: Add all row HTML into $rows. Add header to table first.
$hdrs = RCView::th(array('class'=>'header', 'style'=>'text-align:center;color:#800000;padding:5px 10px;vertical-align:bottom;'), $table_pk_label);
// If RTWS is enabled, then display column for it
if ($showRTWS) {
	$hdrs .= RCView::th(array('id'=>'rtws_rsd_hdr', 'class'=>'wrap darkgreen','style'=>'line-height:10px;width:100px;font-size:11px;text-align:center;padding:5px;white-space:normal;vertical-align:bottom;'), 
				RCView::div(array('style'=>'font-weight:bold;font-size:12px;margin-bottom:7px;'), 
					RCView::img(array('src'=>'databases_arrow.png', 'class'=>'imgfix')) . 
					$lang['ws_30']
				) .
				$lang['ws_06'] . RCView::SP . $DDP->getSourceSystemName()
			);
}
foreach ($formsEvents as $attr) {
	// Add column
	$hdrs .= RCView::th(array('class'=>'header','style'=>'font-size:11px;text-align:center;width:35px;padding:5px;white-space:normal;vertical-align:bottom;'), 
				$attr['form_label'] .
				(!$longitudinal ? "" : RCView::div(array('style'=>'font-weight:normal;color:#800000;'), $Proj->eventInfo[$attr['event_id']]['name_ext']))
			);
}
$rows = RCView::thead('', RCView::tr('', $hdrs));


// IF NO RECORDS EXIST, then display a single row noting that
if (empty($formStatusValues))
{
	$rows .= RCView::tr('', 
				RCView::td(array('class'=>'data','colspan'=>count($formsEvents)+($showRTWS ? 1 : 0)+1,'style'=>'font-size:12px;padding:10px;color:#555;'), 
					$lang['data_entry_179']
				)
			);
}

// ADD ROWS: Get form status values for all records/events/forms and loop through them
foreach ($formStatusValues as $this_record=>$rec_attr) 
{		
	// For each record (i.e. row), loop through all forms/events
	$this_row = RCView::td(array('class'=>'data','style'=>'font-size:12px;padding:0 10px;'), 
					// For longitudinal, create record name as link to event grid page
					($longitudinal 
						? RCView::a(array('href'=>APP_PATH_WEBROOT . "DataEntry/grid.php?pid=$project_id&arm=".getFirstArmRecord($this_record, $recordsPerArm)."&id=".removeDDEending($this_record), 'style'=>'text-decoration:underline;'), removeDDEending($this_record))
						: removeDDEending($this_record)
					) .
					// Display custom record label or secondary unique field (if applicable)
					(isset($extra_record_labels[$this_record]) ? '&nbsp;&nbsp;' . $extra_record_labels[$this_record] : '')
				);
	// If RTWS is enabled, then display column for it
	if ($showRTWS) {
		// If record already has cached data, then obtain count of unadjudicated items for this record
		if (isset($records_with_cached_data[$this_record])) {
			// Get number of items to adjudicate and the html to display inside the dialog
			if ($records_with_cached_data[$this_record] != "") {
				$itemsToAdjudicate = $records_with_cached_data[$this_record];
			} else {
				list ($itemsToAdjudicate, $newItemsTableHtml) 
					= $DDP->fetchAndOutputData($this_record, null, array(), $realtime_webservice_offset_days, $realtime_webservice_offset_plusminus, 
												false, true, false, false);
			}
		} else {
			// No cached data for this record
			$itemsToAdjudicate = 0;
		}
		// Set display values
		if ($itemsToAdjudicate == 0) {
			$rtws_row_class = "darkgreen";
			$rtws_item_count_style = "color:#999;font-size:10px;";
			$num_items_text = $lang['dataqueries_259'];
		} else {
			$rtws_row_class = "data statusdashred";
			$rtws_item_count_style = "color:red;font-size:15px;font-weight:bold;";
			$num_items_text = $itemsToAdjudicate;
		}
		// Display row
		$this_row .= RCView::td(array('class'=>$rtws_row_class, 'id'=>'rtws_new_items-'.$this_record, 'style'=>'font-size:12px;padding:0 5px;text-align:center;'), 
						'<div style="float:left;width:50px;text-align:center;'.$rtws_item_count_style.'">'.$num_items_text.'</div>
						<div style="float:right;"><a href="javascript:;" onclick="triggerRTWSmappedField(\''.cleanHtml2($this_record).'\',true);" style="font-size:10px;text-decoration:underline;">'.$lang['dataqueries_92'].'</a></div>
						<div style="clear:both:height:0;"></div>'
					);
	}
	// Loop through each column
	$lockimgStatic  = RCView::img(array('class'=>'lock', 'style'=>'display:none;', 'src'=>'lock_small.png'));
	$esignimgStatic = RCView::img(array('class'=>'esign', 'style'=>'display:none;', 'src'=>'tick_shield_small.png'));
	foreach ($formsEvents as $attr) 
	{
		// If a longitudinal project with multiple arms, do NOT display the icon if record does NOT belong to this arm
		if ($multiple_arms && !isset($recordsPerArm[$Proj->eventInfo[$attr['event_id']]['arm_num']][$this_record])) {
			$td = '';
		} else {	
			// If it's a survey response, display different icons
			if (isset($surveyResponses[$this_record][$attr['event_id']][$attr['form_name']])) {			
				//Determine color of button based on response status
				switch ($surveyResponses[$this_record][$attr['event_id']][$attr['form_name']]) {
					case '2':
						$img = 'tick_circle_frame.png';
						break;
					default:
						$img = 'circle_orange_tick.png';
				}
			} else {	
				// Set image HTML
				if ($rec_attr[$attr['event_id']][$attr['form_name']] == '2') {
					$img = 'circle_green.png';
				} elseif ($rec_attr[$attr['event_id']][$attr['form_name']] == '1') {
					$img = 'circle_yellow.png';
				} elseif ($rec_attr[$attr['event_id']][$attr['form_name']] == '0') {
					$img = 'circle_red.gif';
				} else {
					$img = 'circle_gray.png';
				}
			}
			// If locked and/or e-signed, add icon
			$lockimg = (isset($locked_records[$this_record][$attr['event_id']][$attr['form_name']])) ? $lockimgStatic : "";
			$esignimg = (isset($esigned_records[$this_record][$attr['event_id']][$attr['form_name']])) ? $esignimgStatic : "";
			// Add cell
			$td = 	RCView::a(array('href'=>APP_PATH_WEBROOT."DataEntry/index.php?pid=$project_id&id=".removeDDEending($this_record)."&page={$attr['form_name']}&event_id={$attr['event_id']}"),
						RCView::img(array('src'=>$img, 'class'=>'fstatus imgfix2')) .
						$lockimg . $esignimg
					);
		}
		// Add column to row
		$this_row .= RCView::td(array('class'=>'data nowrap', 'style'=>'text-align:center;height:20px;'), $td);
	}
	$rows .= RCView::tr('', $this_row);
}





// Page header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
// Page title
renderPageTitle("<img src='".APP_PATH_IMAGES."application_view_icons.png' class='imgfix2'> {$lang['global_91']} {$lang['bottom_61']}");
// Instructions and Legend for colored status icons
print	RCView::table(array('style'=>'width:800px;table-layout:fixed;','cellspacing'=>'0'),
			RCView::tr('',
				RCView::td(array('style'=>'padding:10px 30px 10px 0;','valign'=>'top'),
					// Instructions
					$lang['data_entry_176']
				) .
				RCView::td(array('valign'=>'top','style'=>'width:300px;'),
					// Legend
					RCView::div(array('class'=>'chklist','style'=>'background-color:#eee;border:1px solid #ccc;'),
						RCView::table(array('style'=>'','cellspacing'=>'2'),
							RCView::tr('',
								RCView::td(array('colspan'=>'2', 'style'=>'font-weight:bold;'),
									$lang['data_entry_178']
								)
							) .
							RCView::tr('',
								RCView::td(array('class'=>'nowrap', 'style'=>'padding-right:5px;'),
									RCView::img(array('src'=>'circle_red.gif','class'=>'imgfix')) . $lang['global_92']
								) .
								RCView::td(array('class'=>'nowrap', 'style'=>''),
									RCView::img(array('src'=>'circle_gray.png','class'=>'imgfix')) . $lang['global_92'] . " " . $lang['data_entry_205'] .
									RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'title'=>$lang['global_58'], 'onclick'=>"simpleDialog('".cleanHtml($lang['data_entry_232'])."','".cleanHtml($lang['global_92'] . " " . $lang['data_entry_205'])."');"), '?')
								)
							) .
							RCView::tr('',
								RCView::td(array('class'=>'nowrap', 'style'=>'padding-right:5px;'),
									RCView::img(array('src'=>'circle_yellow.png','class'=>'imgfix')) . $lang['global_93']
								) .
								RCView::td(array('class'=>'nowrap', 'style'=>''),
									(!$surveys_enabled ? "" :
										RCView::img(array('src'=>'circle_orange_tick.png','class'=>'imgfix')) . $lang['global_95']
									)
								)
							) .
							RCView::tr('',
								RCView::td(array('class'=>'nowrap', 'style'=>'padding-right:5px;'),
									RCView::img(array('src'=>'circle_green.png','class'=>'imgfix')) . $lang['survey_28']
								) .
								RCView::td(array('class'=>'nowrap', 'style'=>''),
									(!$surveys_enabled ? "" :
										RCView::img(array('src'=>'tick_circle_frame.png','class'=>'imgfix')) . $lang['global_94']
									)
								)
							)
						)
					)
				)
			)
		);
// Table of records
print	$pageNumDropdown .
		// Options to view locking and/or esignature status
		(!($displayLocking || $displayEsignature) ? '' : 
			RCView::div(array('style'=>'margin-bottom:10px;color:#888;'),
				RCView::span(array('style'=>'font-weight:bold;margin-right:10px;color:#000;'), $lang['data_entry_225']) .
				// Instrument status only
				RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_selected', 'onclick'=>"changeLinkStatus(this);$('.esign').hide();$('.lock').hide();$('.fstatus').show();"), 
					 $lang['data_entry_226']) .	
				// Lock only
				(!$displayLocking ? '' : 
					RCView::SP . " | " . RCView::SP .
					RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_unselected', 'onclick'=>"changeLinkStatus(this);$('.fstatus').hide();$('.esign').hide();$('.lock').show();"), 
						 $lang['data_entry_227'])
					) .	
				// Esign only
				(!$displayEsignature ? '' : 
					RCView::SP . " | " . RCView::SP .		
					RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_unselected', 'onclick'=>"changeLinkStatus(this);$('.fstatus').hide();$('.lock').hide();$('.esign').show();"), 
						 $lang['data_entry_228'])
				) .	
				// Esign + Locking
				(!($displayLocking && $displayEsignature) ? '' : 
					RCView::SP . " | " . RCView::SP .		
					RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_unselected', 'onclick'=>"changeLinkStatus(this);$('.fstatus').hide();$('.lock').show();$('.esign').show();"), 
						 $lang['data_entry_230'])
				) .	
				// All types
				RCView::SP . " | " . RCView::SP .		
				RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_unselected', 'onclick'=>"changeLinkStatus(this);$('.fstatus').show();$('.lock').show();$('.esign').show();"), 
					 $lang['data_entry_229'])
			)
		) .
		RCView::table(array('id'=>'record_status_table', 'class'=>'form_border'), $rows) .
		($numRecordsThisPage > 30 ? $pageNumDropdown : "");

// JavaScript for setting floating table headers
?>
<script type="text/javascript">
// Replace all record "fetch" buttons with spinning progress icon
var recordProgressIcon = '<img src="'+app_path_images+'progress_circle.gif" class="imgfix2">';
$(function(){
	// Enable fixed table headers for event grid
	enableFixedTableHdrs('record_status_table');
	// Also set it to run again if the page is resized
	$(window).resize(function() {
		enableFixedTableHdrs('record_status_table'); 
	});
});
function changeLinkStatus(ob) {
	$(ob).parents('div:first').find('a').removeClass('statuslink_selected').addClass('statuslink_unselected');
	$(ob).removeClass('statuslink_unselected').addClass('statuslink_selected');
}
</script>
<style type="text/css">
a.statuslink_selected { color:#777; }
a.statuslink_unselected { text-decoration:underline; }
</style>
<?php

// If RTWS is enabled, then display column for it
if ($showRTWS) {
	$DDP->renderJsAdjudicationPopup('');
}

// Page footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';