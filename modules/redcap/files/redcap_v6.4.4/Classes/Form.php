<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/


/**
 * FORM Class
 * Contains methods used with regard to forms or general data entry
 */
class Form
{
	// Render data history log
	public static function renderDataHistoryLog($record, $event_id, $field_name)
	{
		global $lang, $require_change_reason;
		// Do URL decode of name (because it original was fetched from query string before sent via Post)
		$record = urldecode($record);
		// Get data history log
		$time_value_array = self::getDataHistoryLog($record, $event_id, $field_name);
		// Get highest array key
		$max_dh_key = count($time_value_array)-1;
		// Loop through all rows and add to $rows
		foreach ($time_value_array as $key=>$row)
		{
			$rows .= RCView::tr(array('id'=>($max_dh_key == $key ? 'dh_table_last_tr' : '')),			
						RCView::td(array('class'=>'data', 'style'=>'padding:5px 8px;text-align:center;width:150px;'),
							DateTimeRC::format_ts_from_ymd($row['ts']) .
							// Display "lastest change" label for the last row
							($max_dh_key == $key ? RCView::div(array('style'=>'color:#C00000;font-size:11px;padding-top:5px;'), $lang['dataqueries_277']) : '')
						) . 		
						RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ddd;padding:3px 8px;text-align:center;width:100px;word-wrap:break-word;'),
							$row['user']
						) . 		
						RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ddd;padding:3px 8px;'),
							$row['value']
						) .
						($require_change_reason 
							? 	RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ddd;padding:3px 8px;'),
									$row['change_reason']
								)
							: 	""
						)
					);
		}
		// If no data history log exists yet for field, give message
		if (empty($time_value_array))
		{
			$rows .= RCView::tr('',			
						RCView::td(array('class'=>'data', 'colspan'=>($require_change_reason ? '4' : '3'), 'style'=>'border-top: 1px #ccc;padding:6px 8px;text-align:center;'),
							$lang['data_history_05']
						)
					);
		}
		// Output the table headers as a separate table (so they are visible when scrolling)
		$table = RCView::table(array('class'=>'form_border', 'style'=>'table-layout:fixed;border:1px solid #ddd;width:97%;'),
					RCView::tr('',			
						RCView::td(array('class'=>'label_header', 'style'=>'padding:5px 8px;width:150px;'),
							$lang['data_history_01']
						) . 		
						RCView::td(array('class'=>'label_header', 'style'=>'padding:5px 8px;width:100px;'),
							$lang['global_17']
						) . 		
						RCView::td(array('class'=>'label_header', 'style'=>'padding:5px 8px;'),
							$lang['data_history_03']
						) .
						($require_change_reason 
							? 	RCView::td(array('class'=>'label_header', 'style'=>'padding:5px 8px;'),
									$lang['data_history_04']
								)
							: 	""
						)
					)
				);
		// Output table html
		$table .= RCView::div(array('id'=>'data_history3', 'style'=>'overflow:auto;'),
					RCView::table(array('id'=>'dh_table', 'class'=>'form_border', 'style'=>'table-layout:fixed;border:1px solid #ddd;width:97%;'),
						$rows
					)
				  );
		// Return html
		return $table;
	}
	

	// Get log of data history (returns in chronological ASCENDING order)
	public static function getDataHistoryLog($record, $event_id, $field_name)
	{
		global $double_data_entry, $user_rights, $longitudinal, $Proj;	
		
		// Set field values
		$field_type = $Proj->metadata[$field_name]['element_type'];
		
		// Determine if a multiple choice field (do not include checkboxes because we'll used their native logging format for display)
		$isMC = ($Proj->isMultipleChoice($field_name) && $field_type != 'checkbox');
		if ($isMC) {
			$field_choices = parseEnum($Proj->metadata[$field_name]['element_enum']);
		}

		// Format the field_name with escaped underscores for the query
		$field_name_q = str_replace("_", "\\_", $field_name);
		// Fashion the LIKE part of the query appropriately for the field type
		$field_name_q = ($field_type == "checkbox") ?  "%$field_name_q(%) = %checked%" : "%$field_name_q = \'%";
		// Set the 2nd query field (for "file" fields, it will be different)
		$qfield2 = ($field_type == "file") ? "description" : "data_values";
		
		// Adjust record name for DDE
		if ($double_data_entry && isset($user_rights) && $user_rights['double_data'] != 0) {
			$record .= "--" . $user_rights['double_data'];
		}
			
		// Default
		$time_value_array = array();

		// Retrieve history and parse field data values to obtain value for specific field
		$sql = "SELECT user, timestamp(ts) as ts, $qfield2 as values1, change_reason FROM redcap_log_event WHERE 
				project_id = " . PROJECT_ID . " 
				and pk = '" . prep($record) . "' 
				and (event_id = $event_id " . ($longitudinal ? "" : "or event_id is null") . ")
				and legacy = 0 
				and 
				(
					(
						event in ('INSERT', 'UPDATE') 
						and description in ('Create record', 'Update record', 'Update record (import)', 
							'Create record (import)', 'Merge records', 'Update record (API)', 'Create record (API)', 
							'Update record (DTS)', 'Update record (DDP)', 'Erase survey responses and start survey over',
							'Update survey response', 'Create survey response', 'Update record (Auto calculation)', 
							'Update survey response (Auto calculation)')
						and data_values like '$field_name_q'
					) 
					or 
					(event in ('DOC_UPLOAD', 'DOC_DELETE') and data_values = '$field_name')
				)
				order by ts";
		$q = db_query($sql);
		// Loop through each row from log_event table. Each will become a row in the new table displayed.
		while ($row = db_fetch_assoc($q))
		{
			// Flag to denote if found match in this row
			$matchedThisRow = false;
			// Get timestamp
			$ts = $row['ts'];	
			// Get username
			$user = $row['user'];
			// Decode values
			$value = html_entity_decode($row['values1'], ENT_QUOTES);
			// All field types (except "file")
			if ($field_type != "file")
			{
				// Default return string
				$this_value = "";
				// Split each field into lines/array elements.
				// Loop to find the string match
				foreach (explode(",\n", $value) as $this_piece)
				{
					// Does this line match the logging format?
					$matched = self::dataHistoryMatchLogString($field_name, $field_type, $this_piece);
					// print "<div style='text-align:left;'>LINE: $this_piece<br>Matched: ".($matched === false ? 'false' : 'true')."</div>";
					if ($matched !== false)
					{
						// Set flag that match was found
						$matchedThisRow = true;
						// Stop looping once we have the value (except for checkboxes)
						if ($field_type != "checkbox") 
						{
							$this_value = $matched;
							break;
						}
						// Checkboxes may have multiple values, so append onto each other if another match occurs
						else
						{
							$this_value .= $matched . "<br>";
						}
					}
				}
				
				// If a multiple choice question, give label AND coding
				if ($isMC && $this_value != "")
				{
					$this_value = filter_tags(label_decode($field_choices[$this_value])) . " ($this_value)";
				}
			}
			// "file" fields
			else
			{
				// Flag to denote if found match in this row
				$matchedThisRow = true;
				// Set value
				$this_value = $value;
			}		
			
			// Add to array (if match was found in this row)
			if ($matchedThisRow) {
				$time_value_array[] = array('ts'=>$ts, 'value'=>nl2br(htmlspecialchars(br2nl(label_decode($this_value)), ENT_QUOTES)), 
											'user'=>$user, 'change_reason'=>nl2br($row['change_reason']));
			}
			
		}
		// Return data history log
		return $time_value_array;
	}
	

	// Determine if string matches REDCap logging format (based upon field type)
	public static function dataHistoryMatchLogString($field_name, $field_type, $string)
	{
		// If matches checkbox logging
		if ($field_type == "checkbox" && substr($string, 0, strlen("$field_name(")) == "$field_name(") // && preg_match("/^($field_name\()([a-zA-Z_0-9])(\) = )(checked|unchecked)$/", $string))
		{
			return $string;
		}
		// If matches logging for all fields (excluding checkboxes)
		elseif ($field_type != "checkbox" && substr($string, 0, strlen("$field_name = '")) == "$field_name = '")
		{
			// Remove apostrophe from end (if exists)
			if (substr($string, -1) == "'") $string = substr($string, 0, -1);
			$value = substr($string, strlen("$field_name = '"));
			return ($value === false ? '' : $value);
		}
		// Did not match this line
		else
		{
			return false;
		}
	}
	

	// Parse the element_enum column into the 3 slider labels (if only 1 assume Left; if 2 asssum Left&Right)
	public static function parseSliderLabels($element_enum)
	{
		// Explode into array, where strings should be delimited with pipe |
		$slider_labels  = array();
		$slider_labels2 = array('left'=>'','middle'=>'','right'=>'');
		foreach (explode("|", $element_enum, 3) as $label)
		{
			$slider_labels[] = trim($label);
		}
		// Set keys
		switch (count($slider_labels))
		{
			case 1:
				$slider_labels2['left']   = $slider_labels[0];
				break;
			case 2:
				$slider_labels2['left']   = $slider_labels[0];
				$slider_labels2['right']  = $slider_labels[1];
				break;
			case 3:
				$slider_labels2['left']   = $slider_labels[0];
				$slider_labels2['middle'] = $slider_labels[1];
				$slider_labels2['right']  = $slider_labels[2];
				break;
		}
		// Return array
		return $slider_labels2;
	}
	
	
	// Get all options for drop-down displaying all project fields
	public static function getFieldDropdownOptions($removeCheckboxFields=false)
	{
		global $Proj, $lang;
		// Build an array of drop-down options listing all REDCap fields
		$rc_fields = array(''=>'-- '.$lang['random_02'].' --');
		foreach ($Proj->metadata as $this_field=>$attr1) {
			// Skip descriptive fields
			if ($attr1['element_type'] == 'descriptive') continue;
			// Skip checkbox fields if flag is set
			if ($removeCheckboxFields && $attr1['element_type'] == 'checkbox') continue;
			// Add to fields/forms array. Get form of field.
			$this_form_label = $Proj->forms[$attr1['form_name']]['menu'];
			// Truncate label if long
			if (strlen($attr1['element_label']) > 65) {
				$attr1['element_label'] = trim(substr($attr1['element_label'], 0, 47)) . "... " . trim(substr($attr1['element_label'], -15));
			}
			$rc_fields[$this_form_label][$this_field] = "$this_field \"{$attr1['element_label']}\"";
		}
		// Return all options
		return $rc_fields;
	}
	
	
	// Return boolean if a calc field's equation in Draft Mode is being changed AND that field contains some data
	public static function changedCalculationsWithData()
	{
		global $Proj, $status;
		// On error, return false
		if ($status < 1 || empty($Proj->metadata_temp)) return false;
		// Add field to array if has a calculation change
		$calcs_changed = array();
		// Loop through drafted changes
		foreach ($Proj->metadata_temp as $this_field=>$attr1) {
			// Skip non-calc fields
			if ($attr1['element_type'] != 'calc') continue;
			// If field does not yet exist, then skip
			if (!isset($Proj->metadata[$this_field])) continue;
			// Compare the equation for each
			if (trim(label_decode($attr1['element_enum'])) != trim(label_decode($Proj->metadata[$this_field]['element_enum']))) {
				$calcs_changed[] = $this_field;
			}
		}
		// Return false if no calculations changed
		if (empty($calcs_changed)) return false;
		// Query to see if any data exists for any of these changed calc fields
		$sql = "select 1 from redcap_data where project_id = ".PROJECT_ID." 
				and field_name in (".prep_implode($calcs_changed).") and value != '' limit 1";
		$q = db_query($sql);
		// Return true if any calc fields that were changed have data in them
		return (db_num_rows($q) > 0);
	}
	
	
}