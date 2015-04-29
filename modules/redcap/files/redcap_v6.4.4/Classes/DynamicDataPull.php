<?php

/**
 * DynamicDataPull
 * This class is used for setup and execution of the real-time web service for extracting
 * data from external systems and importing into REDCap.
 */
class DynamicDataPull
{
	// Encryption salt for DDP source values
	const DDP_ENCRYPTION_KEY = "ds9p2PGh#hK4aV@GVH-YbPrtpWp*7SpeBW+RTujYHj%q35aOrQO/aCSVIFMKifl!S6Ql~JV";
	
	// Cron's record fetch limit per batch
	const FETCH_LIMIT_PER_BATCH = 20;
	
	// Cron's record limit per query to the log_event when checking the last time a record was modified
	const RECORD_LIMIT_PER_LOG_QUERY = 20;
	
	// Set min/max values for range for Day Offset and Default Day Offset
	const DAY_OFFSET_MIN = 0.01;
	const DAY_OFFSET_MAX = 365;
	
	// Current project_id for this object
	public $project_id = null;
	
	// Variable to store this project's field mappings as an array
	public $field_mappings = null;
	

	/**
	 * CONSTRUCTOR
	 */
	public function __construct($this_project_id=null) 
	{
		// Set project_id for this object
		if ($this_project_id == null) {
			if (defined("PROJECT_ID")) {
				$this->project_id = PROJECT_ID;
			} else {
				throw new Exception('No project_id provided!');
			}
		} else {
			$this->project_id = $this_project_id;
		}
	}
	

	/**
	 * RENDER THE REAL-TIME WEB SERVICE SETUP PAGE IN THE PROJECT
	 */
	public function renderSetupPage()
	{
		global $lang;
		// Instructions
		$html =	RCView::p(array('style'=>'max-width:800px;'), 
					"{$lang['ws_53']} {$lang['ws_13']} {$lang['ws_54']} " .
					RCView::a(array('href'=>'javascript:;', 'onclick'=>'ddpExplainDialog();', 'style'=>'text-decoration:underline;'), $lang['global_58'])
				);
		// If a POST request, process the submission and save it
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['map_fields'])) {
			$html .= $this->saveFieldMappings();
		}
		// Display tree of external fields for user to choose prior to mapping
		if ((!$this->isMappingSetUp() && !isset($_POST['select_fields'])) || isset($_GET['add_fields'])) {
			$html .= $this->renderExternalSourceFieldTree();
		}
		// Display table of mappable fields from external source
		if (!isset($_GET['add_fields'])) {
			$html .= $this->renderExternalSourceFieldTable();
		}
		// Return html
		return RCView::div(array('style'=>'max-width:1000px;margin:20px 0;'), $html);
	}
	

	/**
	 * SAVE MAPPINGS OF EXTERNAL SOURCE FIELDS TO REDCAP FIELDS FROM POST
	 */
	private function saveFieldMappings()
	{
		global $longitudinal, $Proj, $realtime_webservice_offset_days, $realtime_webservice_offset_plusminus, $lang;
		
		// Get days offset values, overwrite global variables, and them remove them from Post array
		$realtime_webservice_offset_days = $_POST['rtws_offset_days'];
		$realtime_webservice_offset_plusminus = $_POST['rtws_offset_plusminus'];
		unset($_POST['rtws_offset_days'], $_POST['rtws_offset_plusminus']);
		
		// Get the external source fields already mapped in this project
		$existing_mappings = $this->getMappedFields();
		// Initially set array as existing mappings and remove them as we go so that in the end only the ones to delete are left
		$mappings_to_delete = $existing_mappings;
		
		// Process any preview fields designated
		$preview_fields = array();
		$preview_field_counter = 1;
		if (isset($_POST['preview_field'])) {
			// Add to other array
			foreach ($_POST['preview_field'] as $this_preview_field) {
				$preview_fields['field'.$preview_field_counter++] = $this_preview_field;
			}
			// Remove from Post
			unset($_POST['preview_field']);
		}
		
		// Loop through all Post values and place mappings into array
		$mappings = $mappings_delete = array();
		foreach ($_POST as $key=>$val) {
			// Only look at the checkbox fields of each pair
			if (substr($key, 0, 4) != 'ddf-') continue;
			// Get src field and mapping value
			$src_field = substr($key, 4);
			// Loop through sub-array
			foreach ($val as $key2=>$src_mapping_field) {
				// Is this field the record identifier?
				$is_record_identifier = (isset($_POST['id-'.$src_field][$key2])) ? "1" : "";
				// Get mapping event_id
				if ($longitudinal) {
					$src_mapping_event_id = (isset($_POST['dde-'.$src_field][$key2])) ? $_POST['dde-'.$src_field][$key2] : "";
					if (empty($src_mapping_event_id)) $src_mapping_event_id = $Proj->firstEventId;
				} else {
					$src_mapping_event_id = $Proj->firstEventId;
				}
				// Get mapped temporal field (if the src field is temporal)
				$rc_temporal_field = (isset($_POST['ddt-'.$src_field][$key2])) ? $_POST['ddt-'.$src_field][$key2] : "";
				// Get mapped pre-selection option (if the src field is temporal)
				$rc_preselect = (isset($_POST['ddp-'.$src_field][$key2]) && $_POST['ddp-'.$src_field][$key2] != 'NULL') 
								? $_POST['ddp-'.$src_field][$key2] : "";
				if ($src_mapping_field != "") {
					// Determine if already exists in table
					$this_action = (isset($existing_mappings[$src_field][$src_mapping_event_id][$src_mapping_field])) ? 'update' : 'insert';
					// Add to array for saving in table
					$mappings[$src_field][] = array('field_name'=>$src_mapping_field, 'event_id'=>$src_mapping_event_id, 
													'is_record_identifier'=>$is_record_identifier, 'temporal_field'=>$rc_temporal_field,
													'preselect'=>$rc_preselect, 'action'=>$this_action);
					// Remove from "delete" array
					unset($mappings_to_delete[$src_field][$src_mapping_event_id][$src_mapping_field]);
				}
			}
		}
		//print_array($mappings_to_delete);
		## Now find ones that need to be deleted
		// Loop through $mappings_to_delete and remove empty arrays so we can tell what's really left
		$mappings_to_delete_mapids = array();
		foreach ($mappings_to_delete as $src_field=>$event_array) {
			foreach ($event_array as $this_event_id=>$field_array) {
				if (empty($field_array)) unset($mappings_to_delete[$src_field][$this_event_id]);
			}
			if (empty($mappings_to_delete[$src_field])) unset($mappings_to_delete[$src_field]);
		}
		// Get map_id's of mappings to delete
		foreach ($mappings_to_delete as $src_field=>$event_array) {
			foreach ($event_array as $this_event_id=>$field_array) {
				foreach ($field_array as $src_mapping_field=>$attr) {
					// Add map_id
					$mappings_to_delete_mapids[] = $attr['map_id'];
				}
			}
		}
		// print_array($existing_mappings);
		// print_array($mappings);
		
		## SAVE MAPPINGS
		// Set "identifier" col to 0 for all to prevent key constraint error as we go updating rows
		$sql = "update redcap_ddp_mapping set is_record_identifier = null where project_id = ".$this->project_id;
		$q = db_query($sql);
		// Now go through and delete any map_ids ready to delete
		if (!empty($mappings_to_delete_mapids)) {
			$sql = "delete from redcap_ddp_mapping where map_id in (" . prep_implode($mappings_to_delete_mapids) . ")";
			$q = db_query($sql);
		}		
		// Now loop through mappings and insert/update each
		foreach ($mappings as $src_field=>$attr_array) {
			foreach ($attr_array as $attr) {
				if ($attr['action'] == 'insert') {
					// Insert
					$sql = "insert into redcap_ddp_mapping (project_id, field_name, event_id, external_source_field_name, 
							is_record_identifier, temporal_field, preselect)
							values (".$this->project_id.", '".prep($attr['field_name'])."', '".prep($attr['event_id'])."', 
							'".prep($src_field)."', ".checkNull($attr['is_record_identifier']).", 
							".checkNull($attr['temporal_field']).", ".checkNull($attr['preselect']).")";
				} else {
					// Update
					$sql = "update redcap_ddp_mapping
							set	is_record_identifier = ".checkNull($attr['is_record_identifier']).", 
							temporal_field = ".checkNull($attr['temporal_field']).", preselect = ".checkNull($attr['preselect'])."
							where project_id = ".$this->project_id." and field_name = '".prep($attr['field_name'])."'
							and event_id = '".prep($attr['event_id'])."' and external_source_field_name = '".prep($src_field)."'";
				}
				$q = db_query($sql);
				// if (db_error() != "") print "<br><b>MySQL error " . db_errno() . ":</b><br>" . db_error() . "<br><br><b>Failed query:</b><br>$sql";
			}
		}
		
		// Add day offset defaults to redcap_projects
		$sql = "update redcap_projects set realtime_webservice_offset_days = '".prep($realtime_webservice_offset_days)."',
				realtime_webservice_offset_plusminus = '".prep($realtime_webservice_offset_plusminus)."' where project_id = ".$this->project_id;
		$q = db_query($sql);
		
		## Preview Fields: Add to table
		// First, remove from table
		$sql = "delete from redcap_ddp_preview_fields where project_id = ".$this->project_id;
		$q = db_query($sql);
		// Now re-add to table if existed before
		if (!empty($preview_fields)) {
			// Remove row from table
			$sql = "insert into redcap_ddp_preview_fields (project_id, ".implode(", ", array_keys($preview_fields)).") 
					values (".$this->project_id.", ".prep_implode($preview_fields).")";
			$q = db_query($sql);
		}
		
		// Now reset the field mapping array now that it's been modified
		$this->field_mappings = null;
		
		// Log this event
		$loggedValues = json_encode(array('offset_days'=>$realtime_webservice_offset_days, 'offset_plusminus'=>$realtime_webservice_offset_plusminus, 
							'preview_fields'=>$preview_fields, 'mappings'=>$mappings));
		log_event("", "redcap_ddp_mapping", "MANAGE", $this->project_id, $loggedValues, "Map source fields (DDP)");
		
		## RETURN "SAVED" MSG
		return 	RCView::div(array('class'=>'darkgreen msgrt', 'style'=>'text-align:center;margin:20px 0;'),
					RCView::img(array('src'=>'tick.png', 'class'=>'imgfix')) . 
					$lang['ws_100']
				);
	}
	
	
	/**
	 * RENDER THE TREE OF EXTERNAL FIELDS FOR USER TO CHOOSE PRIOR TO MAPPING
	 */
	private function renderExternalSourceFieldTree()
	{
		global $lang;
		
		// HTML
		$html = '';
		
		// Get the external source fields in array format
		$external_fields_all = $this->getExternalSourceFields();
		
		// Get the external id field name
		$external_id_field = '';
		foreach ($external_fields_all as $attr) {
			if (isset($attr['identifier']) && $attr['identifier'] == '1') {
				$external_id_field = $attr['field'];
			}
		}
		
		// Get the external source fields already mapped in this project
		$external_fields_mapped = $this->getMappedFields();
		
		// Javascript
		?>
		<script type="text/javascript">
		// Get the external id field name
		var external_id_field = '<?php echo cleanHtml($external_id_field) ?>';
		</script>
		<?php
		// Call javascript file
		callJSfile('DynamicDataPullMapping.js');
		
		## Collect categories and subcategories into an array
		$categories = array();
		foreach ($external_fields_all as $key=>$attr) 
		{
			// Set array key as the field name so we can find their attributes faster
			unset($external_fields_all[$key]);
			$external_fields_all[$attr['field']] = $attr;
			
			// For those not in a category
			if ($attr['category'] == '') {
				$categories[''][] = $attr['field'];
			} 
			// For those in a category
			else {
				if (!isset($categories[$attr['category']])) $categories[$attr['category']] = array();
				if ($attr['subcategory'] != '') {
					// Add to subcategory
					if (!isset($categories[$attr['category']][$attr['subcategory']])) $categories[$attr['category']][$attr['subcategory']] = array();
					$categories[$attr['category']][$attr['subcategory']][] = $attr['field'];
				} else {
					// Add to category
					$categories[$attr['category']][''][] = $attr['field'];
				}
			}
		}
		
		//print_array($categories);
		
		// Loop through categories and subcats and build li/ul html
		$tree = '';
		$num_fields_checked = 0;
		foreach ($categories as $cat=>$catattr) 
		{
			$cat_html = '';
			$num_fields_checked_cat = 0;
			// If no cat
			if ($cat == '') {
				// Loop through fields
				foreach ($catattr as $this_field) {
					$checked = '';
					$div_color = '';
					if ($this_field == $external_id_field || isset($external_fields_mapped[$this_field])) {
						$checked = 'checked';
						$num_fields_checked++;
						$num_fields_checked_cat++;
						// Set color for source id field
						if ($this_field == $external_id_field) $div_color = 'font-weight:bold;color:#C00000;';
					}
					$cat_html .= RCView::div(array('class'=>'extsrcfld', 'style'=>$div_color), 
									RCView::checkbox(array('name'=>$this_field, 'style'=>'vertical-align:middle;', $checked=>$checked)) .
									$this_field . " (" . $external_fields_all[$this_field]['label'] . ")" . RCView::SP . 
									RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'title'=>$external_fields_all[$this_field]['description']), '?')
								 );
				}
			} 
			// If in a cat
			else {
				// Loop through subcats
				$subcat_html = '';
				// Count subcats
				$num_subcats = 0;
				foreach ($catattr as $subcat=>$subcatattr) {
					// Loop through fields
					$subcat_fields_html = '';
					$num_fields_checked_subcat = 0;
					foreach ($subcatattr as $this_field) {
						$checked = '';
						$div_color = '';
						if ($this_field == $external_id_field || isset($external_fields_mapped[$this_field])) {
							$checked = 'checked';
							$num_fields_checked++;
							$num_fields_checked_cat++;
							$num_fields_checked_subcat++;
							// Set color for source id field
							if ($this_field == $external_id_field) $div_color = 'font-weight:bold;color:#C00000;';
						}
						$subcat_fields_html .= 	RCView::div(array('class'=>'extsrcfld', 'style'=>$div_color), 
													RCView::checkbox(array('name'=>$this_field, 'style'=>'vertical-align:middle;', $checked=>$checked)) .
													$this_field . " (" . $external_fields_all[$this_field]['label'] . ") " . RCView::SP . 
													RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'title'=>$external_fields_all[$this_field]['description']), '?')
												);
					}
					//print "CAT: $cat, SUBCAT: $subcat<br>$subcat_fields_html";
					// If no subcat
					if ($subcat != '') {
						$num_subcats++;
						$displaySubcat = ($num_fields_checked_subcat == 0) ? "" : "display:block;";
						$displaySubcatSelectAll = ($num_fields_checked_subcat == 0) ? "display:none;" : "";
						$subcatIcon = ($num_fields_checked_subcat == 0) ? 'expand.png' : 'collapse.png';
						$subcat_html .= RCView::div(array('class'=>'rc_ext_subcat_name'), 
											RCView::img(array('src'=>$subcatIcon, 'style'=>'vertical-align:middle;')) .
											RCView::a(array('href'=>'javascript:;'), $subcat) .
											// Display "select all" link
											RCView::a(array('href'=>'javascript:;', 'class'=>'selectalllink', 'style'=>$displaySubcatSelectAll.'font-size:11px;text-decoration:underline;font-weight:normal;margin-left:25px;'), $lang['ws_35']) .
											RCView::span(array('class'=>'selectalllink_sep', 'style'=>$displaySubcatSelectAll.'color:#aaa;margin:0 5px;'), "|") . 
											// Display "deselect all" link
											RCView::a(array('href'=>'javascript:;', 'class'=>'deselectalllink', 'style'=>$displaySubcatSelectAll.'font-size:11px;text-decoration:underline;font-weight:normal;'), $lang['ws_55'])
										) .
										RCView::div(array('class'=>'rc_ext_subcat', 'style'=>$displaySubcat), $subcat_fields_html);
					} else {
						$subcat_html .= $subcat_fields_html;
					}
				}
				// Render this cat
				$displayCat = ($num_fields_checked_cat == 0) ? "" : "display:block;";
				$displayCatSelectAll = ($num_fields_checked_subcat == 0) ? "display:none;" : "";
				$catIcon = ($num_fields_checked_cat == 0) ? 'expand.png' : 'collapse.png';
				$cat_html .= RCView::div(array('class'=>'rc_ext_cat_name'), 
								RCView::img(array('src'=>$catIcon, 'style'=>'vertical-align:middle;')) .
								RCView::a(array('href'=>'javascript:;'), $cat) .
								// If cat does not have subcats, then display "select all" link
								($num_subcats > 0 ? "" : RCView::a(array('href'=>'javascript:;', 'class'=>'selectalllink', 'style'=>$displayCatSelectAll.'font-size:11px;text-decoration:underline;font-weight:normal;margin-left:25px;'), $lang['ws_35'])) .
								($num_subcats > 0 ? "" : RCView::span(array('class'=>'selectalllink_sep', 'style'=>$displayCatSelectAll.'color:#aaa;margin:0 5px;'), "|")) . 
								// If cat does not have subcats, then display "deselect all" link
								($num_subcats > 0 ? "" : RCView::a(array('href'=>'javascript:;', 'class'=>'deselectalllink', 'style'=>$displayCatSelectAll.'font-size:11px;text-decoration:underline;font-weight:normal;'), $lang['ws_55']))
							 ) .
							 RCView::div(array('class'=>'rc_ext_cat', 'style'=>$displayCat), $subcat_html);
			}
			// Add cat to tree
			$tree .= $cat_html;
		}
		
		// Set search textbox for search input
		$searchTextbox = 	RCView::span(array('style'=>'margin:0 0 5px;text-align:right;'), 
								"Filter:" . 
								RCView::text(array('id'=>'source_field_search', 'class'=>'x-form-text x-form-field', 'onkeydown'=>"if(event.keyCode == 13) return false;",
									'style'=>'margin-left:5px;width:300px;color:#999;', 'value'=>$lang['ws_07'],
									'onfocus'=>"if ($(this).val() == '".cleanHtml($lang['ws_07'])."') { $(this).val(''); $(this).css('color','#000'); }",
									'onblur'=>"$(this).val( trim($(this).val()) ); if ($(this).val() == '') { $(this).val('".cleanHtml($lang['ws_07'])."'); $(this).css('color','#999'); }"
								))
							);
		
		// Render the div container for the tree
		$html .= RCView::div(array('id'=>'ext_field_tree'),
					RCView::form(array('method'=>'post', 'id'=>'select_mapping_fields', 'action'=>PAGE_FULL."?pid=".$this->project_id, 'enctype'=>'multipart/form-data'),
						RCView::div(array('style'=>'font-size:15px;font-weight:bold;padding-bottom:8px;margin-bottom:8px;'),
							$lang['ws_101']
						) .
						RCView::div(array('style'=>'margin-bottom:5px;'), $lang['ws_102']) .
						RCView::div(array('class'=>'blue', 'style'=>'margin:20px 0 0;'),
							$lang['ws_34'] . 
							RCView::span(array('id'=>'rc_ext_num_selected2', 'style'=>'font-weight:bold;padding-left:5px;'), 
								count($external_fields_mapped)
							) .
							RCView::button(array('onclick'=>"$('#select_mapping_fields').submit();", 
								'class'=>'jqbuttonmed', 'style'=>'margin-left:125px;color:#111;font-size:13px;'),
								$lang['ws_33']
							) .
							RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:12px;margin-left:10px;text-decoration:underline;', 
								'onclick'=>"window.location.href = app_path_webroot+'".($this->isMappingSetUp() ? "DynamicDataPull/setup.php" : "ProjectSetup/index.php")."?pid=".$this->project_id."';"),
								$lang['global_53']
							) .
							// 
							RCView::div(array('style'=>'margin:25px 0 0;'), 
								RCView::div(array('style'=>'float:left;vertical-align:middle;font-weight:bold;font-size:15px;'), "Source Fields List") . 
								RCView::div(array('style'=>'float:right;vertical-align:middle;padding-bottom:2px;'), $searchTextbox) . 
								RCView::div(array('class'=>'clear'), '')
							)
						) .
						RCView::div(array('id'=>'ext_field_tree_fields', 'style'=>'clear:both;padding:10px 6px;background-color:#fff;border:1px solid #ccc;'),
							$tree
						) .
						RCView::div(array('class'=>'blue', 'style'=>'margin:0 0 20px;'),
							$lang['ws_34'] . 
							RCView::span(array('id'=>'rc_ext_num_selected', 'style'=>'font-weight:bold;padding-left:5px;'), 
								count($external_fields_mapped)
							) .
							RCView::hidden(array('name'=>'select_fields', 'value'=>'1')) .
							RCView::button(array('onclick'=>"$('#select_mapping_fields').submit();", 
								'class'=>'jqbuttonmed', 'style'=>'margin-left:125px;color:#111;font-size:13px;'),
								$lang['ws_32']
							) .
							RCView::a(array('href'=>'javascript:;', 'style'=>'margin-left:10px;text-decoration:underline;', 
								'onclick'=>"window.location.href = app_path_webroot+'".($this->isMappingSetUp() ? "DynamicDataPull/setup.php" : "ProjectSetup/index.php")."?pid=".$this->project_id."';"),
								$lang['global_53']
							)
						)
					)
				 );
				
		// Return html
		return $html;
	}
	

	/**
	 * RENDER THE TABLE FOR MAPPING EXTERNAL SOURCE FIELDS
	 */
	private function renderExternalSourceFieldTable()
	{
		// Get global vars
		global $Proj, $lang, $longitudinal, $realtime_webservice_offset_days, $realtime_webservice_offset_plusminus;
		
		// If fields are not mapped AND we've not just selected some to map, then don't display table
		if (!$this->isMappingSetUp() && !isset($_POST['select_fields'])) return '';
		
		// Call javascript file
		callJSfile('DynamicDataPullMapping.js');
		
		// Get the external source fields in array format
		$external_fields_all = $this->getExternalSourceFields();
		 
		// Now filter out the ones NOT selected
		$external_id_field_attr = array();
		$src_preview_fields_all = array(''=>'');
		$external_id_field = '';
		foreach ($external_fields_all as $key=>$attr) {
			// Remove from original array via its key
			unset($external_fields_all[$key]);
			// If has already been mapped or user just selected fields to map
			if (!isset($_POST['select_fields']) || (isset($_POST['select_fields']) && isset($_POST[$attr['field']]))) {
				// Re-add to array but with field as key
				$external_fields_all[$attr['field']] = $attr;
				// If this is the source identifier field, then store separately and later prepend to array
				if (isset($attr['identifier']) && $attr['identifier'] == '1') {
					$external_id_field_attr[$attr['field']] = $attr;
					$external_id_field = $attr['field'];
				}
			}
			// Add all non-temporal fields to array for Preview feature
			if (!(isset($attr['temporal']) && $attr['temporal'] == '1') && !(isset($attr['identifier']) && $attr['identifier'] == '1')) {
				$src_preview_fields_all[$attr['field']] = "{$attr['field']} \"{$attr['label']}\"";
			}
		}
		
		// SORT SOURCE FIELDS: Although the fields will already be sorted within their subcategories, we need to now sort them by their category.
		$srcCatFieldSort = array();
		// Shift off Source ID field, then re-add after sort (to keep in at the beginning)
		$sourceIdArray = array_shift($external_fields_all);
		foreach ($external_fields_all as &$attr) {
			// Now add category+field to sorting array
			$srcCatFieldSort[] = $attr['category'].'---'.$attr['field'];
		}
		// Now sort $external_fields_all according to cat+field value
		array_multisort($srcCatFieldSort, SORT_REGULAR, $external_fields_all);
		// Now add Source ID field back to beginning
		$external_fields_all = array($sourceIdArray['field']=>$sourceIdArray) + $external_fields_all;
		unset($srcCatFieldSort);
		
		// Get the external source fields already mapped in this project
		$external_fields_mapped = $this->getMappedFields();
		
		## Find any many-to-one mappings (many source fields to same REDCap field)
		// Remove map_id attribute from $external_fields_mapped and put in new array
		$many_to_one_external_fields = $many_to_one_keys = $external_fields_mapped_wo_mapid = array();
		$temporal_field_list = array(''=>'-- Choose other source field --');
		foreach ($external_fields_mapped as $this_ext_field=>$attr1) {
			foreach ($attr1 as $this_event_id=>$these_fields) {
				foreach ($these_fields as $this_field=>$field_attr) {
					// Remove map_id
					unset($field_attr['map_id']);
					// Add to new array
					$external_fields_mapped_wo_mapid[$this_ext_field][$this_event_id][$this_field] = $field_attr;
					// If source field is a temporal field, then add to temporal field list array
					if ($field_attr['temporal_field'] != '') {
						$temporal_field_list[$this_ext_field] = $this_ext_field." \"{$external_fields_all[$this_ext_field]['label']}\"";
					}
				}
			}
		}
		// Now loop through post fields (if just selected fields from the tree) to add them to temporal field list
		if (isset($_POST['select_fields'])) {
			foreach ($_POST as $this_ext_field=>$is_on) {
				if ($this_ext_field == 'select_fields' || $is_on != 'on') continue;
				if ($external_fields_all[$this_ext_field]['temporal']) {
					$temporal_field_list[$this_ext_field] = $this_ext_field." \"{$external_fields_all[$this_ext_field]['label']}\"";
				}
			}
		}
		
		// Sort $temporal_field_list alphabetically by source variable name
		ksort($temporal_field_list);
		// Now loop through the fields again in $external_fields_mapped_wo_mapid and note any with same exact mappings (i.e. are many-to-one)
		$i = 0;
		foreach ($external_fields_mapped_wo_mapid as $this_ext_field=>$this_attr) {
			// Loop through the entire mapping AGAIN (ignoring this_ext_field) so that we loop through EVERY field for EACH field
			foreach ($external_fields_mapped_wo_mapid as $this_loop_ext_field=>$this_loop_attr) {
				// Skip if same field
				if ($this_ext_field != $this_loop_ext_field && $this_attr === $this_loop_attr) {
					// Serialize array and make it the array key with the source fields as sub-array values (best way to group them)
					$key_serialized = serialize($this_attr);
					if (!isset($many_to_one_external_fields[$key_serialized])) {
						// If the first field to have the same attributes, then put this one field in array to note to us where to begin
						$many_to_one_keys[$this_ext_field] = $key_serialized;
					}
					// Add to array
					$many_to_one_external_fields[$key_serialized][] = $this_ext_field;
					// Keep the subarrays unique
					$many_to_one_external_fields[$key_serialized] = array_values(array_unique($many_to_one_external_fields[$key_serialized]));
				}
			}
		}
		// Loop through fields once more, and as we loop, if any fields are many-to-one and should be grouped together, 
		// then remove from array and resplice them together in array
		$mapped_wo_mapid_temp = $mapped_wo_mapid_ignore = array();
		// Copy array to rework it as we go 
		$external_fields_all_regrouped = $external_fields_all;
		foreach ($external_fields_mapped_wo_mapid as $this_ext_field=>$this_attr) {
			// Does this field exist in the $many_to_one_keys array?
			if (!isset($many_to_one_keys[$this_ext_field])) continue;
			// If so, get serialized key to connect it to the fields in array $many_to_one_external_fields
			$these_many_to_one_fields = $many_to_one_external_fields[$many_to_one_keys[$this_ext_field]];
			// Shift off first field in array
			$this_first_many_to_one = array_shift($these_many_to_one_fields);
			// Add group fields to new array with attributes
			$these_many_to_one_fields_attr = array($this_first_many_to_one=>$external_fields_all[$this_first_many_to_one]);
			// Loop through other fields in this group and remove them from $external_fields_all_regrouped 
			// (because we'll add them back later in the correct position)
			foreach ($these_many_to_one_fields as $this_other_many_to_one) {
				// Remove from array
				unset($external_fields_all_regrouped[$this_other_many_to_one]);
				// Add field to field group array containing attributes
				$these_many_to_one_fields_attr[$this_other_many_to_one] = $external_fields_all[$this_other_many_to_one];
			}
			// Get the array position of the first field in $these_many_to_one_fields so we know where to splice $external_fields_mapped_wo_mapid
			$key_position = array_search($this_first_many_to_one, array_keys($external_fields_all_regrouped));
			if ($key_position !== false && !empty($these_many_to_one_fields_attr)) {
				$first_half  = array_slice($external_fields_all_regrouped, 0, $key_position, true);
				$second_half = array_slice($external_fields_all_regrouped, $key_position+1, null, true);
				// Add all 3 array segments back together and PUT MANY-TO-ONE FIELDS AT END
				$external_fields_all_regrouped = $first_half + $these_many_to_one_fields_attr + $second_half;
			}
		}
		//print "<br>count: ".count($external_fields_all);
		//print "<br>count: ".count($external_fields_all_regrouped);
		// print_array(array_keys($external_fields_all_regrouped));
		//print_array(array_diff(array_keys($external_fields_all), array_keys($external_fields_all_regrouped)));
		// Replace original array with the resorted one
		$external_fields_all = $external_fields_all_regrouped;
		// Remove unneeded arrays
		unset($external_fields_all_regrouped);
		
		// Build an array of drop-down options listing all REDCap fields
		$rc_fields = $rc_date_fields = array(''=>'');
		foreach ($Proj->metadata as $this_field=>$attr1) {
			// Ignore SQL fields and checkbox fields and Form Status fields
			if ($attr1['element_type'] == 'sql' || $attr1['element_type'] == 'checkbox' 
				|| $attr1['form_name'].'_complete' == $this_field) continue;
			// Add to fields/forms array. Get form of field.
			$this_form_label = $Proj->forms[$attr1['form_name']]['menu'];
			$rc_fields[$this_form_label][$this_field] = "$this_field \"{$attr1['element_label']}\"";
			// If a date field, then add to array
			if ($attr1['element_type'] == 'text' && 
				(in_array($attr1['element_validation_type'], array('date', 'date_ymd', 'date_mdy', 'date_dmy'))
				|| substr($attr1['element_validation_type'], 0, 8) == 'datetime')) 
			{
				$rc_date_fields[$this_form_label][$this_field] = "$this_field \"{$attr1['element_label']}\"";
			}
		}
		
		// If longitudinal, build an array of drop-down options listing all REDCap event names
		if ($longitudinal) {
			$rc_events = array(''=>'');
			foreach ($Proj->eventInfo as $this_event_id=>$attr) {
				// Add to array
				$rc_events[$this_event_id] = $attr['name_ext'];
			}
		}
		
		$rows = '';
				
		// Set table headers		
		$rows .= RCView::tr('',
					RCView::td(array('class'=>'header', 'style'=>'padding:10px;width:200px;font-size:13px;'),
						$lang['ws_103'] .
						RCView::div(array('style'=>'color:#800000;'),
							"(" . $this->getSourceSystemName() . ")"
						)
					) .
					(!$longitudinal ? '' :
						RCView::td(array('class'=>'header', 'style'=>'padding:10px;width:170px;font-size:13px;'),
							$lang['ws_104']
						)
					) .
					RCView::td(array('class'=>'header', 'style'=>'padding:10px;font-size:13px;'),
						$lang['ws_105']
					) .
					RCView::td(array('class'=>'header', 'style'=>'padding:10px;width:200px;font-size:13px;'),
						RCView::div(array('class'=>'nowrap'),
							$lang['ws_106']
						) .
						RCView::div(array('style'=>'margin-top:5px;font-weight:normal;color:#777;line-height:10px;font-size:11px;'),
							$lang['ws_107']
						)
					) .
					RCView::td(array('class'=>'header', 'style'=>'padding:10px 5px;text-align:center;font-weight:normal;width:55px;'),
						$lang['ws_108']
					)
				);
				
		
		// print_array($external_fields_mapped);
		// print_array($external_fields_all);
		//print_array($_POST);
		
		// Loop through all fields to create each as a table row
		$prev_src_field = $prev_mapped_to_rc_event = $prev_src_cat = $prev_src_subcat = $true_prev_src_field = ''; // defaults
		$nonDisplayedManyToOneMappingsJs = '';
		$catsAlreadyDisplayed = array();
		foreach ($external_fields_all as $src_field=>&$attr)
		{
			$src_label  = $attr['label'];
			$src_cat    = $attr['category'];
			
			// Field neither mapped nor selected from source field tree (do NOT display)
			if (!isset($external_fields_mapped[$src_field]) && !isset($_POST['select_fields'])) {
				continue;
			}
			
			// Is this field already mapped? If so, put REDCap fields mapped to this source field into array for looping
			if (isset($external_fields_mapped[$src_field])) {
				// Set flag if part of a many-to-one group (but is not the first field)
				$isManyToOne = ($external_fields_mapped_wo_mapid[$src_field] === $external_fields_mapped_wo_mapid[$true_prev_src_field]);
				// Add mappings to array that we will loop through
				$external_fields_mapped_field_list = $external_fields_mapped[$src_field];
				// If temporary fields in the mappings array have the same source field and RC field BUT do NOT have same temporal date field,
				// then separate into multiple arrays so that they don't get bunched together and thus overwrite the date field selection.
				$external_fields_mapped_field_list_super = array();
				$super_key = 0;
				foreach ($external_fields_mapped_field_list as $mapped_to_rc_event=>&$mapped_to_rc_event_array) {				
					// Set defaults
					$prev_temporal_field = $prev_temporal_preselect = '';
					foreach ($mapped_to_rc_event_array as $mapped_to_rc_field=>$mapped_attr) {
						// If has different temporal field or preselect option, then add as new table row
						if ($prev_temporal_field != $mapped_attr['temporal_field'] || $prev_temporal_preselect != $mapped_attr['preselect']) {
							$super_key++;
						}
						// Add to super array
						$external_fields_mapped_field_list_super[$super_key][$mapped_to_rc_event][$mapped_to_rc_field] = $mapped_attr;
						// Set for next loop
						$prev_temporal_field = $mapped_attr['temporal_field'];
						$prev_temporal_preselect = $mapped_attr['preselect'];
					}
					// Increment super key
					$super_key++;
				}				
			} else {
				// New fields to be added
				$external_fields_mapped_field_list_super = array(array(''=>array($src_field=>array())));
				$isManyToOne = false;
			}
			
			// print "$src_field, \"$src_label\"";
			// print_array($external_fields_mapped_field_list);
			// print_array($external_fields_mapped_field_list_super);
			
			// Loop through all REDCap fields mapped to this one source field (one-to-many relationship)
			foreach ($external_fields_mapped_field_list_super as &$external_fields_mapped_field_list)
			{
				$firstEventOfItem = true;
				$prev_temporal_preselect = $prev_temporal_field = '';
				foreach ($external_fields_mapped_field_list as $mapped_to_rc_event=>$mapped_to_rc_event_array)
				{
					// MULTI-EVENT MANY-TO-ONE MAPPING
					// In this instance where there is a many-to-one mapping over multiple events, it's really hard to loop sequentially and
					// get the table to display correctly. So instead, collect the source fields on the non-last events in the mapping,
					// and use jQuery to display them after the table is loaded. Not perfect but it works.
					if ($firstEventOfItem && 
						// Is field a many-to-one parent?
						isset($many_to_one_keys[$src_field]) && 
						// Is many-to-one parent used in multiple events?
						count($external_fields_mapped_field_list) > 1) 
					{
						// Build arrays of events/fields to loop through in order to construct the javascript
						$theseManyToOneFieldsNonLastEvent = $many_to_one_external_fields[$many_to_one_keys[$src_field]];
						unset($theseManyToOneFieldsNonLastEvent[0]); // Remove first field (not needed)
						$theseManyToOneEvents = array_keys($external_fields_mapped_field_list);
						array_pop($theseManyToOneEvents); // Remove last event_id (not needed)					
						// Loop through events/fields to build javascript
						foreach ($theseManyToOneEvents as $thisMtoEventId) {
							foreach ($theseManyToOneFieldsNonLastEvent as $thisMtoField) {
								$nonDisplayedManyToOneMappingsJs .= 
									"var addMtoDD = $('table#src_fld_map_table select[name=\"dde-{$src_field}[]\"] option[value=\"$thisMtoEventId\"]:selected').parents('tr:first').find('.manytoone_dd');" .
									"addMtoDD.val('".cleanHtml($thisMtoField)."');copyRowManyToOneDD(addMtoDD,false);";
							}
						}
					}
					// Skip all non-last events if a many-to-one child row (because they will be added via javascript when page loads)
					if (!$firstEventOfItem && $isManyToOne) {
						continue;
					}
					
					foreach ($mapped_to_rc_event_array as $mapped_to_rc_field=>$mapped_attr)
					{
						// Is this field already mapped? If so, check the checkbox and highlight as green
						if ($mapped_to_rc_event != '') {
							// Field already mapped
							$highlight_class = "";
							$chkbox_identifier = ($mapped_attr['is_record_identifier'] == '1') ? "checked" : ""; 
							$mapped_to_rc_date_field = $mapped_attr['temporal_field'];
						} elseif (isset($_POST['select_fields'])) {
							// Field selected from the source field tree
							$chkbox_identifier = $mapped_to_rc_date_field = $mapped_to_rc_field = "";
							// Do green highlight if we just selected new fields to map
							$highlight_class = "blue"; 
						}
						
						// If longitudinal, add event drop-down
						$event_dd = "";
						if ($longitudinal) {
							$event_dd = RCView::div(array('style'=>'font-weight:bold;'),
											RCView::select(array('class'=>'evtfld', 'style'=>'max-width:100%;', 'name'=>'dde-'.$src_field.'[]'), $rc_events, $mapped_to_rc_event, 40)
										);
						}
						
						// If a temporal field, add date field drop-down to select
						$temporal_dd = $deleteFieldLink = $mapAnotherFieldLink = $mapAnotherEventLink = $temporal_field_dd_many_to_one = $copySrcRow = '';
						if ($attr['temporal'] && $src_field != $external_id_field) 
						{
							$temporal_dd = 	RCView::div(array('style'=>'font-weight:bold;'),
												//"Map to REDCap date field:" . RCView::SP . RCView::SP .
												RCView::div(array('style'=>'font-size:11px;line-height:8px;'),
													$lang['ws_109']
												) .
												RCView::select(array('class'=>'temfld', 'style'=>'max-width:100%;', 'name'=>'ddt-'.$src_field.'[]'), $rc_date_fields, $mapped_to_rc_date_field, 45) .
												// Preselection option
												RCView::div(array('style'=>'font-size:11px;line-height:8px;margin-top:8px;'),
													$lang['ws_110']
												) .
												RCView::select(array('class'=>'presel', 'preval'=>$mapped_attr['preselect'], 'onchange'=>"preselectConform(this);", 'style'=>'max-width:100%;', 'name'=>'ddp-'.$src_field.'[]'), 
													array('NULL'=>'-- '.$lang['database_mods_81'].' --', 'MIN'=>$lang['ws_15'], 
														  'MAX'=>$lang['ws_16'], 'FIRST'=>$lang['ws_17'], 'LAST'=>$lang['ws_18'],
														  'NEAR'=>$lang['ws_194']), $mapped_attr['preselect'])
											);
							$deleteFieldLink = RCView::a(array('href'=>'javascript:;', 'onclick'=>"deleteMapField(this);"), 
													RCView::img(array('src'=>'cross_small2.png', 'title'=>$lang['scheduling_57']))
												 );
							$mapAnotherFieldLink = 	RCView::div(array('id'=>'rcAddDD-'.$src_field, 'style'=>'text-align:right;line-height:10px;padding:3px 16px 0 0;'), 
														RCView::img(array('src'=>'plus_small2.png')) .
														RCView::a(array('href'=>'javascript:;', 'style'=>'color:green;font-size:10px;text-decoration:underline;', 'onclick'=>"mapOtherRedcapField(this,'$src_field');"), 
															$lang['ws_111']
														)
													);
							$copySrcRow = 	RCView::a(array('title'=>$lang['ws_112'], 'href'=>'javascript:;', 'class'=>'copySrcRowTemporal', 'style'=>'margin-right:5px;', 'onclick'=>"copySrcRowTemporal(this,'$src_field');"), 
												RCView::img(array('src'=>'page_copy.png', 'class'=>'imgfix2'))
											);
							// Allow to copy row to map src field to fields on another event (except for source id field)
							if ($longitudinal) {
								$mapAnotherEventLink = 	RCView::div(array('style'=>'margin-top:10px;'),
															RCView::img(array('src'=>'plus_small2.png')) .
															RCView::a(array('href'=>'javascript:;', 'style'=>'color:green;font-size:10px;text-decoration:underline;', 'onclick'=>"copyMappingOtherEvent(this);"), 
																$lang['ws_113']
															)
														);
							}
							// Add drop-down of temporal fields for performing many-to-one mappings (only display for first field in many-to-one group)
							//if (!$isManyToOne) {
								// Create drop-down list unique to this source field so that itself is not included in the drop-down
								$this_temporal_field_list = $temporal_field_list;
								unset($this_temporal_field_list[$src_field]);
								// Set link with hidden drop-down
								$temporal_field_dd_many_to_one = 
									RCView::div(array(),
										// Preselection option
										RCView::div(array('class'=>'manytoone_showddlink', 'style'=>'margin:10px 0 5px;'),
											RCView::img(array('src'=>'plus_small2.png')) .
											RCView::a(array('href'=>'javascript:;', 'style'=>'color:green;font-size:10px;text-decoration:underline;', 'onclick'=>"mappingShowManyToOneDD($(this))"), 
												$lang['ws_114']
											)
										) .
										RCView::select(array('class'=>'manytoone_dd', 'style'=>'margin-top:8px;display:none;max-width:100%;font-size:11px;', 'onchange'=>'copyRowManyToOneDD($(this));'), $this_temporal_field_list, '', 45)
									);
							//}
						}
						// Set dropdown(s) for REDCap fields to be selected
						$this_rc_field_dropdown = RCView::div(array('style'=>'padding-bottom:2px;white-space:nowrap;'),
													RCView::select(array('class'=>'mapfld', 'onchange'=>($attr['temporal'] ? "preselectConform(this);" : ""), 'style'=>'max-width:94%;', 'name'=>'ddf-'.$src_field.'[]'), $rc_fields, $mapped_to_rc_field, 45) . 
													$deleteFieldLink
												  );
						if ($prev_src_field == $src_field && $prev_mapped_to_rc_event == $mapped_to_rc_event
							&& $prev_temporal_field == $mapped_attr['temporal_field'] && $prev_temporal_preselect == $mapped_attr['preselect']) {
							$rc_field_dropdown .= $this_rc_field_dropdown;
						} else {
							$rc_field_dropdown = $this_rc_field_dropdown;
						}
						// Set for next loop
						$prev_src_field = $src_field;
						$prev_mapped_to_rc_event = $mapped_to_rc_event;
						$prev_temporal_field = $mapped_attr['temporal_field'];
						$prev_temporal_preselect = $mapped_attr['preselect'];
					}
					
					// If this is the source id field, then add a special header
					$external_id_field_special_label = '';
					$hiddenInputSourceIdMarker = '';
					$removeMappingIcon = RCView::a(array('title'=>$lang['ws_115'], 'class'=>'delMapRow', 'href'=>'javascript:;', 'onclick'=>"deleteMapRow(this);"), 
											RCView::img(array('src'=>'cross.png', 'class'=>'imgfix2'))
										 );
					if ($src_field == $external_id_field) {
						$external_id_field_special_label = 	RCView::div(array('style'=>'color:#96740E;margin:2px 0 5px;'), 
																RCView::img(array('src'=>'star.png', 'class'=>'imgfix')) .
																RCView::span(array('style'=>''), $lang['ws_116'])
															);
						$hiddenInputSourceIdMarker = RCView::hidden(array('name'=>"id-{$external_id_field}[]", 'value'=>'1'));
						$removeMappingIcon = '';
					}
					
					// If starting a new category, then display cat name as section header
					if ($prev_src_cat != $src_cat && !isset($catsAlreadyDisplayed[$src_cat]) 
						// If this field is part of a many-to-one group and it is NOT the first field in the group, then do NOT display a cat header
						&& !$isManyToOne
					) {
						$catsAlreadyDisplayed[$src_cat] = true;
						$rows .= RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'colspan'=>($longitudinal ? 5 : 4), 'style'=>'font-weight:bold;color:#800000;border:1px solid #aaa;background-color:#ccc;padding:5px 10px;font-size:13px;'),
										$src_cat
									)
								 );
					}
					
					// print "<hr>$src_field";
					// print_array($external_fields_mapped_wo_mapid[$src_field]);
					// print "<br>$true_prev_src_field";
					// print_array($external_fields_mapped_wo_mapid[$true_prev_src_field]);
					
					// If a many-to-one child, then add special row attribute to denote this
					$manyToOneAttr = ($isManyToOne ? $src_field : '');
					$manyToOneAttrName = ($isManyToOne ? 'manytoone' : '');
					
					// Add row
					$rows .= RCView::tr(array($manyToOneAttrName=>$manyToOneAttr),
								RCView::td(array('valign'=>'top', 'class'=>"data $highlight_class", 
									'style'=>'border-bottom:0;padding:5px 10px 2px;width:170px;'.($isManyToOne ? 'border-top:0;' : '')),
									// If a many-to-one mapping, then display "OR" before the variable/label
									RCView::div(array('class'=>'manytoone_or', 'style'=>'margin:0 0 4px 10px;color:#999;'.($isManyToOne ? '' : 'display:none;')), 
										"&mdash; ".$lang['global_46']. " &mdash;"
									) .
									// Display variable/label
									RCView::div(array('class'=>'source_var_label'),
										RCView::b($src_field) . " &nbsp;\"" . $src_label . "\""
									) .
									$external_id_field_special_label .
									// Option to perform many-to-one mapping (temporal fields only)
									$temporal_field_dd_many_to_one
								) .
								(!$longitudinal ? '' :
									RCView::td(array('valign'=>'top', 'class'=>"data $highlight_class", 'style'=>'border-bottom:0;padding:5px 10px;'.($isManyToOne ? 'border-top:0;' : '')),
										($isManyToOne ? '' : 
											RCView::div(array('class'=>'td_container_div'),
												$event_dd .
												$mapAnotherEventLink
											)
										)
									)
								) .
								RCView::td(array('valign'=>'top', 'class'=>"data $highlight_class", 'style'=>'border-bottom:0;padding:5px 10px;'.($isManyToOne ? 'border-top:0;' : '')),
									($isManyToOne ? '' : 
										RCView::div(array('class'=>'td_container_div'),
											$rc_field_dropdown .
											$hiddenInputSourceIdMarker .
											$mapAnotherFieldLink
										)
									)
								) .
								RCView::td(array('valign'=>'top', 'class'=>"data $highlight_class", 'style'=>'border-bottom:0;padding:5px 10px;'.($isManyToOne ? 'border-top:0;' : '')),
									($isManyToOne ? '' : 
										RCView::div(array('class'=>'td_container_div'),
											$temporal_dd
										)
									)
								) .
								RCView::td(array('valign'=>'top', 'class'=>"data $highlight_class", 'style'=>'border-bottom:0;padding-top:5px;text-align:center;width:55px;'),
									$copySrcRow . $removeMappingIcon
								)
							);
					// Set flag for all non-initial events in the subarray
					$firstEventOfItem = false;
				}
			}
			// Set for next loop
			$prev_src_cat = $src_cat;
			$true_prev_src_field = $src_field;
		}
		
		## PREVIEW FIELDS
		// Get source "preview" fields as array
		$preview_fields = $this->getPreviewFields();		
		// Html to delete the associated Preview field drop-down
		$deletePreviewFieldLink = 	RCView::a(array('href'=>'javascript:;', 'onclick'=>"deletePreviewField(this);"), 
										RCView::img(array('src'=>'cross_small2.png', 'title'=>$lang['scheduling_57']))
									);
		// Loop through preview fields and build html for drop-downs/divs
		if (empty($preview_fields)) {
			// No preview fields are designated, so render blank drop-down
			$preview_fields_html = 	RCView::div(array('class'=>'rtws_preview_field'),
										RCView::select(array('style'=>'max-width:100%;', 'name'=>'preview_field[]'), $src_preview_fields_all, '', 60) .
										$deletePreviewFieldLink
									);
		} else {
			// Render all preview field drop-downs
			$preview_fields_html = "";
			foreach ($preview_fields as $this_preview_field) {
				$preview_fields_html .=	RCView::div(array('class'=>'rtws_preview_field'),
											RCView::select(array('style'=>'max-width:100%;', 'name'=>'preview_field[]'), $src_preview_fields_all, $this_preview_field, 60) .
											$deletePreviewFieldLink
										);
			}
		}
		
		// Set pixel width of table/divs
		$tableWidth = ($longitudinal ? 958 : 798);
		
		// Set table html
		$table = 
				// Settings header
				RCView::div(array('style'=>'width:'.$tableWidth.'px;font-weight:bold;color:#fff;border:1px solid #ccc;border-bottom:0;background-color:#555;padding:8px 10px;font-size:13px;'),
					$lang['ws_117']
				) .
				// Settings - Preview fields
				RCView::div(array('class'=>"data", 'style'=>'width:'.$tableWidth.'px;background-image:none;background-color:#eee;padding:10px;'),
					RCView::table(array('cellspacing'=>'0', 'style'=>'width:100%;table-layout:fixed;'),
						RCView::tr(array(),
							RCView::td(array('valign'=>'top', 'style'=>'width:450px;padding-right:30px;color:#555;line-height:13px;'),
								RCView::div(array('style'=>'font-weight:bold;font-size:14px;color:#800000;line-height:14px;margin-bottom:5px;'),
									$lang['ws_118']
								) . 
								RCView::div(array('style'=>'font-size:11px;line-height:11px;'),
									$lang['ws_119']." (e.g. {$external_id_field_attr[$external_id_field]['label']}) ".$lang['ws_120']
								)
							) .
							RCView::td(array('valign'=>'top'),
								RCView::div(array('style'=>'font-weight:bold;margin-bottom:5px;'),
									$lang['ws_121'] . " " . $this->getSourceSystemName() . " " . 
									RCView::span(array('style'=>'color:#800000'), $lang['ws_122']) .
									$lang['colon']
								) . 
								// Preview field(s)
								$preview_fields_html .
								// "Add" new Preview field button
								RCView::div(array('class'=>'rtws_add_preview_field', 'style'=>'margin:4px 0 0 6px;'),
									RCView::img(array('src'=>'plus_small2.png')) .
									RCView::a(array('href'=>'javascript:;', 'style'=>'color:green;font-size:10px;text-decoration:underline;', 'onclick'=>"addPreviewField();"), 
										$lang['ws_123']
									)
								)
							)
						)
					)
				) .
				// Settings - Default Day Offset
				RCView::div(array('class'=>"data", 'style'=>'width:'.$tableWidth.'px;background-image:none;background-color:#eee;padding:10px;'),
					RCView::table(array('cellspacing'=>'0', 'style'=>'width:100%;table-layout:fixed;'),
						RCView::tr(array(),
							RCView::td(array('valign'=>'top', 'style'=>'width:450px;padding-right:30px;color:#555;line-height:13px;'),
								RCView::div(array('style'=>'font-weight:bold;font-size:14px;color:#800000;line-height:14px;margin-bottom:5px;'),
									$lang['ws_124']
								) .
								RCView::div(array('style'=>'font-size:11px;line-height:11px;'),
									$lang['ws_125']
								)
							) .
							RCView::td(array('valign'=>'top', 'style'=>'padding-top:15px;'),
								RCView::span(array('style'=>'font-size:13px;font-weight:bold;margin-right:5px;'),
									$lang['ws_126']
								) . 
								// Dropdown of day offset plus/minus
								"<select name='rtws_offset_plusminus' style='font-size:12px;'>
									<option value='+-' ".($realtime_webservice_offset_plusminus == '+-' ? 'selected' : '').">&plusmn;</option>
									<option value='+' ".($realtime_webservice_offset_plusminus == '+' ? 'selected' : '').">+</option>
									<option value='-' ".($realtime_webservice_offset_plusminus == '-' ? 'selected' : '').">-</option>
								</select>" .
								// Text box for day offset value
								RCView::text(array('name'=>'rtws_offset_days', 'value'=>$realtime_webservice_offset_days, 
									'onblur'=>"redcap_validate(this,'".self::DAY_OFFSET_MIN."','".self::DAY_OFFSET_MAX."','hard','float')", 'class'=>'', 
									'style'=>'width:25px;font-size:12px;padding:0 3px;')) .
								RCView::span(array('style'=>'font-size:13px;'),
									$lang['scheduling_25']
								) .
								RCView::div(array('style'=>'margin:10px 0 0;color:#888;font-size:11px;'),
									$lang['ws_192']." ".self::DAY_OFFSET_MIN." ".$lang['ws_191']." ".self::DAY_OFFSET_MAX." ".$lang['scheduling_25']
								)
							)
						)
					)
				) .
				// Field-mapping header
				RCView::div(array('style'=>'margin-top:15px;width:'.$tableWidth.'px;font-weight:bold;color:#fff;border:1px solid #ccc;border-bottom:0;background-color:#555;padding:8px 10px;font-size:13px;'),
					$lang['ws_130']
				) .
				// Field-mapping table
				RCView::table(array('id'=>'src_fld_map_table', 'class'=>'form_border', 
								'style'=>'border-bottom:1px solid #ccc;table-layout:fixed;width:'.($tableWidth+22).'px;'), $rows);
		// Add Save/Cancel buttons
		if (!empty($external_fields_all)) {
			$table .= RCView::div(array('style'=>'padding:30px 0;text-align:center;width:800px;'),
						RCView::hidden(array('name'=>'map_fields', 'value'=>'1')) . 
						RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:14px;', 'id'=>'map_fields_btn', 'onclick'=>"
							$(this).button('disable'); 
							$('#rtws_mapping_cancel').css('visibility','hidden');
							$('#rtws_mapping_table').submit();
							return false;
						"),
							$lang['ws_131']
						) . 
						RCView::a(array('id'=>'rtws_mapping_cancel', 'href'=>'javascript:;', 'style'=>'margin-left:10px;text-decoration:underline;', 'onclick'=>"window.location.href=app_path_webroot+'DynamicDataPull/setup.php?pid='+pid;"), $lang['global_53'])
					);
		}
		// Set html to return
		$html = '';
		// Add "add more source fields" button
		if (isset($_POST['select_fields'])) {
			$html .= RCView::div(array('style'=>'width:'.$tableWidth.'px;margin:20px 0 5px;padding-top:10px;border-top:1px solid #ccc;'),
						RCView::div(array('style'=>'font-size:15px;font-weight:bold;padding-bottom:8px;margin-bottom:8px;'),
							$lang['ws_132']
						) .
						$lang['ws_133']
					);
		} else {
			$html .= RCView::div(array('style'=>'padding:10px 0 5px;'),
						RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:13px;', 'onclick'=>"confirmLeaveMappingChanges(modifiedMapping);"), 
							RCView::img(array('src'=>'add.png', 'style'=>'vertical-align:middle;')) .
							RCView::span(array('style'=>'vertical-align:middle;color:green;'), $lang['ws_134'])
						)
					);
		}
		// Add form html
		$html .= RCView::form(array('id'=>'rtws_mapping_table', 'method'=>'post', 'action'=>PAGE_FULL."?pid=".$this->project_id, 'enctype'=>'multipart/form-data',
				'onsubmit'=>"
				if (hasMappedAllSelections()) {
					copyItemsManyToOneChildren();
					return !hasDuplicateFieldMapping(); 
				} else { 
					simpleDialog('".cleanHtml($lang['ws_05'])."');
					enableMappingSubmitBtn();
					return false; 
				}",
				'style'=>'margin:20px 0 0;'), $table);
		// If there is any javascript for adding multi-event many-to-one mappings, then display it here
		if ($nonDisplayedManyToOneMappingsJs != '') {
			$html .= "<script type='text/javascript'>$(function(){ $nonDisplayedManyToOneMappingsJs });</script>";
		}
		// Return html
		return $html;
	}
	

	/**
	 * CALL "DATA" WEB SERVICE AND DISPLAY IN TABLE FOR ADJUDICATION
	 */
	public function fetchAndOutputData($record=null, $event_id=null, $form_data=array(), $day_offset=0, $day_offset_plusminus='+-', 
									   $output_html=true, $record_exists='1', $show_excluded=false, $forceDataFetch=true)
	{
		global $reserved_field_names, $Proj, $lang, $isAjax;
		// Validate $day_offset. If not valid, set to 0.
		if (!is_numeric($day_offset)) $day_offset = 0;
		if ($day_offset_plusminus != '-' && $day_offset_plusminus != '+') $day_offset_plusminus = '+-';
		// Get the REDCap field name and event_id of the external identifier		
		list ($rc_field, $rc_event) = $this->getMappedIdRedcapFieldEvent();
		// Obtain the value of the record identifier (e.g., mrn) 
		$rc_data = Records::getData('array', $record, $rc_field, $rc_event);
		// If form values were sent (which might be non-saved values), add them on top of $rc_data
		if (!empty($form_data) && is_numeric($event_id)) {
			// Loop through vars and remove all non-real fields
			foreach ($form_data as $key=>$val) 
			{
				// If begin with double underscore (this ignores checkboxes, which we can't use for the RTWS)
				if (substr($key, 0, 2) == '__') { unset($form_data[$key]); continue; }
				// If end with ___radio
				if (substr($key, -8) == '___radio') { unset($form_data[$key]); continue; }
				// If contains a hyphen
				if (strpos($key, '-') !== false) { unset($form_data[$key]); continue; }
				// If is a reserved field name
				if (isset($reserved_field_names[$key])) { unset($form_data[$key]); continue; }
				
				// If a date[time] field that's not in YMD format, then convert to YMD
				// ONLY do this if being called via AJAX (i.e. form post from data entry form, where dates may not be in YMD format)
				if ($isAjax) {
					$thisValType = $Proj->metadata[$key]['element_validation_type'];
					if (substr($thisValType, 0, 4) == 'date' && (substr($thisValType, -4) == '_mdy'|| substr($thisValType, -4) == '_dmy')) {
						$form_data[$key] = $val = DateTimeRC::datetimeConvert($val, substr($thisValType, -3), 'ymd');
					}
				}
				
				// If this is the REDCap field mapped to the external id field, then overwrite it's value
				if ($key == $rc_field && $event_id == $rc_event) {
					// Overwrite value
					$rc_data[$record][$rc_event][$rc_field] = $val;
				}
			}
		}
		// Get the value of the external id field (e.g., the MRN value)
		$record_identifier_external = $rc_data[$record][$rc_event][$rc_field];
		// If doesn't have a record identifer external for this record, give error message
		if ($record_identifier_external == '') {
			return 	array(0, RCView::div(array('class'=>"red", 'style'=>'margin:20px 0;max-width:100%;padding:10px;'),
								RCView::img(array('src'=>'exclamation.png','class'=>'imgfix')) .
								RCView::b($lang['global_01'].$lang['colon'])." ".
								"{$lang['global_49']} <b>$record</b> {$lang['ws_135']} \"$rc_field\"{$lang['period']} {$lang['ws_136']}"
							) .
							// Set hidden div so that jQuery knows to hide the dialog buttons
							RCView::div(array('id'=>'adjud_hide_buttons', 'class'=>'hidden'), '1')
					);
		}
		
		// Get data via web service and return as array
		list ($response_data_array, $request_field_array) = 
			$this->fetchData($record, $event_id, $record_identifier_external, $day_offset, $day_offset_plusminus, $form_data, $forceDataFetch, $record_exists);
		
		// Return html for adjudication table
		return $this->renderAdjudicationTable($record, $event_id, $day_offset, $day_offset_plusminus, $response_data_array, $request_field_array, $form_data, $output_html, $record_exists, $show_excluded);
	}
	
	
	/**
	 * CALL "DATA" WEB SERVICE TO OBTAIN DATA FROM RECORD PASSED TO IT
	 * Return data as array with unique field name as array keys and data values as array values.
	 * If not JSON encoded, then return FALSE.
	 */
	public function fetchData($record_identifier_rc, $event_id, $record_identifier_external, $day_offset, 
							  $day_offset_plusminus, $form_data=array(), $forceDataFetch=true, $record_exists='1', $returnCachedValues=true,
							  $project_id=null)
	{
		// Get global var
		global $realtime_webservice_url_data, $isAjax, $realtime_webservice_data_fetch_interval, $lang;
		
		// Determine project_id (either as PROJECT_ID or as passed parameter)
		if ($project_id == null) {
			if (defined("PROJECT_ID")) {
				$project_id = PROJECT_ID;
			} else {
				throw new Exception('No project_id provided!');
			}
		}
		
		// Make sure we have a value for $realtime_webservice_data_fetch_interval
		if (!(is_numeric($realtime_webservice_data_fetch_interval) && $realtime_webservice_data_fetch_interval >= 1)) {
			$realtime_webservice_data_fetch_interval = 24;
		}
		
		// Get mappings external=>REDCap
		$mappings = $this->getMappedFields();
		
		// If we have temporal fields, then retrieve REDCap data for them to send in the request
		$temporal_event_ids = $temporal_fields = array();
		foreach ($mappings as $this_src_field=>$this_evt_array) {
			foreach ($this_evt_array as $this_event_id=>$these_rc_fields) {
				foreach ($these_rc_fields as $this_rc_field=>$rc_field_attr) {
					// Add temporal field/event_id for data pull later
					if ($rc_field_attr['temporal_field'] != '') {
						// Get temporal field names and event_id's for the data pull
						$temporal_event_ids[] = $this_event_id;
						$temporal_fields[] = $rc_field_attr['temporal_field'];
					}
				}
			}
		}
		$temporal_fields = array_unique($temporal_fields);
		$temporal_event_ids = array_unique($temporal_event_ids);
		
		// Get data for temporal fields
		$temporal_data = array();
		if (!empty($temporal_fields)) {
			// Get data from backend
			$temporal_data = Records::getData($project_id, 'array', $record_identifier_rc, $temporal_fields, $temporal_event_ids);
			// If form values were sent (which might be non-saved values), add them on top of $rc_existing_data
			if (!empty($form_data) && is_numeric($event_id)) {
				// Loop through vars and remove all non-real fields
				foreach ($form_data as $key=>$val) {
					// If a temporal field
					if (in_array($key, $temporal_fields)) {
						// ADD to $temporal_data if we made it this far
						$temporal_data[$record_identifier_rc][$event_id][$key] = $val;
					}
				}
			}
		}
		
		// Set flag if we're on a data entry form. If so, then filter data to ONLY fields on this form.
		$onDataEntryForm = (!empty($form_data));		
		
		// Get mr_id for this record (assuming the record exists)
		$mr_id = ($record_exists) ? $this->getMrId($record_identifier_rc) : null;
		
		## CHECK IF RECORD HAS ANY DATA CACHED (if not, then force a data fetch)
		if (!$forceDataFetch && $record_exists)
		{
			$sql = "select 1 from redcap_ddp_mapping m, redcap_ddp_records_data d, redcap_ddp_records r 
					where m.map_id = d.map_id and d.mr_id = r.mr_id and m.project_id = " . $this->project_id . " 
					and m.project_id = r.project_id and r.record = '".prep($record_identifier_rc)."' limit 1";
			$q = db_query($sql);
			if (db_num_rows($q) == 0) {
				// Now check if we've ever pulled data for this record in the past X hours, and if not, then set it to force a data fetch
				$lastFetchTimeText = $this->getLastFetchTime($record_identifier_rc);
				// If data has never been fetched OR it's time for it to be fetched, then set to fetch it
				if ($lastFetchTimeText == '' || ((strtotime(NOW)-strtotime($lastFetchTimeText)) > (3600*$realtime_webservice_data_fetch_interval))) {
					$forceDataFetch = '1';
				}
			}
		}
		
		// Now loop through mapped fields again to build temporal CSV string ($field_event_info is same as $field_info but with event_id)
		$field_info = $field_event_info = $map_ids = array();
		$recordIdIsMapped = false;
		foreach ($mappings as $this_src_field=>$this_evt_array) {
			foreach ($this_evt_array as $this_event_id=>$these_rc_fields) {
				foreach ($these_rc_fields as $this_rc_field=>$rc_field_attr) {
					// Skip the record ID field
					if ($rc_field_attr['is_record_identifier']) {
						// Set flag and begin next loop
						$recordIdIsMapped = true;
						continue;					
					}
					
					// If on data entry form and NOT forcing a fresh data fetch (i.e. will get cached data), 
					// then skip any fields not on this form/event
					//if ($onDataEntryForm && !$forceDataFetch && (!isset($form_data[$this_rc_field]) || $event_id != $this_event_id)) continue;
					
					// Add map_id to array for later when pulling cached data (if applicable)
					$map_ids[] = $rc_field_attr['map_id'];
					// Get temporal data
					if ($rc_field_attr['temporal_field'] != '' && $temporal_data[$record_identifier_rc][$this_event_id][$rc_field_attr['temporal_field']] != '') 
					{
						// Determine min/max timestamps using $day_offset
						$this_timestamp = $temporal_data[$record_identifier_rc][$this_event_id][$rc_field_attr['temporal_field']];
						// Determine min timestamp
						if ($day_offset_plusminus == '+') {
							$this_timestamp_min = date('Y-m-d H:i:s', strtotime($this_timestamp));
						} else {
							$this_timestamp_min = date('Y-m-d H:i:s', strtotime("-$day_offset days", strtotime($this_timestamp)));
						}
						// Determine max timestamp
						if ($day_offset_plusminus == '-') {
							$this_timestamp_max = date('Y-m-d H:i:s', strtotime($this_timestamp));
						} else {
							$this_timestamp_max = date('Y-m-d H:i:s', strtotime("+$day_offset days", strtotime($this_timestamp)));
						}
						// Add to array to send to data web service
						$field_info[] = array('field'=>$this_src_field, 'timestamp_min'=>$this_timestamp_min, 'timestamp_max'=>$this_timestamp_max);
						// Add to array for REDCap to use for adjudication setup
						$field_event_info[] = array('src_field'=>$this_src_field, 'rc_field'=>$this_rc_field, 'event_id'=>$this_event_id, 
													'timestamp'=>$temporal_data[$record_identifier_rc][$this_event_id][$rc_field_attr['temporal_field']],
													'preselect'=>$rc_field_attr['preselect']);
					} 
					// Either no data or not a temporal field
					elseif ($rc_field_attr['temporal_field'] == '') 
					{
						// Add to array to send to data web service
						$field_info[] = array('field'=>$this_src_field);
						// Add to array for REDCap to use for adjudication setup
						$field_event_info[] = array('src_field'=>$this_src_field, 'rc_field'=>$this_rc_field, 'event_id'=>$this_event_id);
					}
				}
			}
		}
		
		// Remove any duplicates in $field_info so that we don't send superfluous data to the data web service
		$field_info = array_values(array_map("unserialize", array_unique(array_map("serialize", $field_info))));
		
		if (empty($field_info) && !$recordIdIsMapped) {
			## No fields to send to web service, so return 0 items
			if (!$isAjax) {
				return false;
			} else {
				print json_encode(array('item_count'=>0, 'html'=>''));
				exit;
			}
		}
		
		// Collect the md_id for each data point in an array to map it to the key in $response_data_array 
		// (so we can set each saved value as "adjudicated=1" in the mapping_data table.
		$response_data_array_keymap_mdid = array();
		
		// Call the data web service if the record doesn't exist OR if we're forcing a data fetch
		if ($forceDataFetch || !$record_exists)
		{
			## CALL DATA WEB SERVICE			
			// First, set the record's fetch status as QUEUED in case the user leaves the page before it finishes 
			// (so they don't have to wait 24 hours for it to be fetched again).
			$sql = "update redcap_ddp_records set fetch_status = 'QUEUED' where mr_id = $mr_id";
			$q = db_query($sql);
			// Set params to send in POST request (all JSON-encoded in single parameter 'body')
			// $params = array('user'=>(defined('USERID') ? USERID : ''), 'project_id'=>$this->project_id, 'redcap_url'=>APP_PATH_WEBROOT_FULL, 
							// 'body' => json_encode(array('id'=>$record_identifier_external, 'fields'=>$field_info))
							// );
			$params = array('user'=>(defined('USERID') ? USERID : ''), 'project_id'=>$this->project_id, 'redcap_url'=>APP_PATH_WEBROOT_FULL, 
							'id'=>$record_identifier_external, 'fields'=>$field_info);
			// Call the URL as POST request
			// $response_json = http_post($realtime_webservice_url_data, $params, 30);
			$response_json = http_post($realtime_webservice_url_data, $params, 30, 'application/json');
			// if (USERID == 'taylorr4' || isDev()) 
				// exit(json_encode(array('item_count'=>-2, 'html'=>"JSON being SENT TO the data web service:<br><br>".json_encode($params).
				// "<br><hr><br>JSON being RETURNED FROM the data web service:<br><br>$response_json")));			
			// Decode json into array
			$response_data_array = json_decode($response_json, true);
			
			// Display an error if the web service can't be reached or if the response is not JSON encoded
			if (!$response_json || !is_array($response_data_array)) {
				$error_msg = $lang['ws_137']."<br><br>";
				if ($response_json !== false && !is_array($response_data_array)) {
					$error_msg .=  $lang['ws_138']."<div style='color:#C00000;margin-top:10px;'>$response_json</div>";
				} elseif ($response_json === false) {
					$error_msg .= $lang['ws_139']." $realtime_webservice_url_data.";
				}
				if (!$isAjax) {
					return false;
				} else {
					print json_encode(array('item_count'=>-2, 'html'=>$error_msg));
					exit;
				}
			}
			
			## CACHE THE FETCHED DATA IN THE TABLE (but only if the record already exists - could cause issue with naming)
			if ($record_exists) 
			{
				// Loop through all fetched data and get map_id's for each data point returned.
				foreach ($response_data_array as $item_key=>$this_item) {
					// Loop through mappings of this data value's field
					foreach ($mappings[$this_item['field']] as $this_event_id=>$event_array) {
						foreach ($event_array as $this_rc_field=>$rc_field_array) {
							// Get map_id
							$this_map_id = $rc_field_array['map_id'];
							// Clean the timestamp in case ends with ".0" or anything else
							$this_timestamp = ($this_item['timestamp'] == '') ? 'null' : "timestamp('".substr($this_item['timestamp'], 0, 19)."')";
							// Make sure value gets trimmed before storing it (just in case of whitespace padding)
							$this_item['value'] = trim($this_item['value']);
							// Set the encrypted value
							$this_encrypted_value = encrypt($this_item['value'], self::DDP_ENCRYPTION_KEY);
							// Check if this timestamp-value already exists. If not, then add.
							$sql = "select 1 from redcap_ddp_records_data 
									where map_id = $this_map_id and mr_id = $mr_id 
									and source_timestamp ".($this_item['timestamp'] == '' ? 'is null' : "= $this_timestamp")." 
									and source_value = '".prep($this_encrypted_value)."' limit 1";
							$q = db_query($sql);
							if (db_num_rows($q) < 1) {
								// Add value to values table
								$sql = "insert into redcap_ddp_records_data (map_id, mr_id, source_timestamp, source_value)
										values ($this_map_id, $mr_id, $this_timestamp, '".prep($this_encrypted_value)."')";
								$q = db_query($sql);
								// Add md_id to keymap array
								$response_data_array[$item_key]['md_id'] = db_insert_id();
							}
						}
					}
				}
				
				## Check to see how many temporal fields' datetime reference fields have values that will occur in the future
				// Set initial count
				$future_dates = 0;
				// Fetch data only for temporal fields
				$data_temporal_fields = Records::getData($project_id, 'array', $record_identifier_rc, $temporal_fields);
				// Loop through and count if value exists in the future
				foreach ($data_temporal_fields[$record_identifier_rc] as $these_fields) {
					foreach ($these_fields as $this_field=>$this_value) {
						// Is value a date AND occurs today or after today's date
						if ($this_value != '' && $this_value >= TODAY) $future_dates++;
					}
				}				
				
				// Now add "last fetch" timestamp for the record as well as future_date_count
				$sql = "update redcap_ddp_records set updated_at = '".NOW."', fetch_status = null, 
						future_date_count = $future_dates where mr_id = $mr_id";
				$q = db_query($sql);
			}
			
			// print "<b>POST params sent to Data Web Service:</b>";
			// print_array($params);
			// print "<b>POST['fields'] after json_decode():</b>";
			// print_array($field_info);
			// print "<b>RESPONSE data after json_decode():</b>";
			// print_array($response_data_array);		
		}
		
		## IF WE'RE NOT RETURNING ANY CACHED VALUES (I.E. CRON - JUST CACHING VALUES), THEN STOP HERE
		if (!$returnCachedValues) return;
		
		## GET THE CACHED DATA IN TABLE (even if we just cached it - just in case other values were already cached so we don't miss them)
		$response_data_array = array();
		$sql = "select d.md_id, m.external_source_field_name, d.source_timestamp, d.source_value, m.event_id 
				from redcap_ddp_mapping m, redcap_ddp_records_data d, redcap_ddp_records r 
				where m.map_id = d.map_id and d.mr_id = r.mr_id and m.project_id = " . $this->project_id . " 
				and m.project_id = r.project_id and r.record = '".prep($record_identifier_rc)."'
				and m.map_id in (".prep_implode($map_ids).")";
		$q = db_query($sql);
		//print $sql;
		while ($row = db_fetch_assoc($q)) {
			$response_data_array[] = array('field'=>$row['external_source_field_name'], 'timestamp'=>$row['source_timestamp'], 
										   'value'=>decrypt($row['source_value'], self::DDP_ENCRYPTION_KEY), 
										   'md_id'=>$row['md_id'], 'event_id'=>$row['event_id']);
		}
			
		/* 		
		ob_start();
		//print_array($field_event_info);
		print_array($response_data_array);
		print json_encode(array('item_count'=>-2, 'html'=>ob_get_clean()));
		exit;
		*/
		 
		// Return array of response data AND $field_info array (or false if not JSON encoded)
		return (is_array($response_data_array) ? array($response_data_array, $field_event_info) : false);
	}
	

	/**
	 * RENDER THE DATA ADJUDICATION TABLE AFTER RECEIVING DATA FORM WEB SERVICE
	 */
	public function renderAdjudicationTable($record, $event_id, $day_offset, $day_offset_plusminus, $data_array_src, 
											$data_array_rc, $form_data, $output_html, $record_exists, $show_excluded=false)
	{
		global $Proj, $isAjax, $lang, $longitudinal;
		
		// If no data or error with web service, then display error message
		if ($data_array_src === false || empty($data_array_src)) {
			return 	array(0, RCView::div(array('class'=>"darkgreen", 'style'=>'padding:10px;max-width:100%;'),
								RCView::img(array('src'=>'accept.png','class'=>'imgfix')) .
								$lang['ws_140']
							)
					);
		}
		
		// Get mappings external=>REDCap
		$mappings = $this->getMappedFields();
		
		## GET EXISTING REDCAP DATA
		// Loop through all mapped fields to get fields/event_ids needed for REDCap data pull
		$rc_mapped_fields = $rc_mapped_events = $map_ids = $map_id_list = array();
		foreach ($mappings as $src_field=>$event_attr) {
			foreach ($event_attr as $this_event_id=>$field_attr) {
				// Add event_id
				$rc_mapped_events[] = $this_event_id;
				// Loop through fields
				foreach ($field_attr as $rc_field=>$attr) {
					// Add field
					$rc_mapped_fields[] = $rc_field;		
					// Segregate map_id's into separate array with event_id-field as keys
					$map_ids[$src_field][$this_event_id][$rc_field] = $attr['map_id'];
					// Put all map_ids in an array
					$map_id_list[] = $attr['map_id'];
				}
			}
		}
		$rc_mapped_events = array_unique($rc_mapped_events);
		$rc_mapped_fields = array_unique($rc_mapped_fields);
		
		// Get array of md_ids with values already excluded for the given record
		// $excluded_values_by_md_id = $this->getExcludedValues($record, $map_id_list);
		$excluded_values_by_md_id = array(); ## DO NOT USE THIS FEATURE [YET]
		
		// Get array of md_ids as keys for items/values not yet adjudicated for the given record
		$non_adjudicated_values_by_md_id = $this->getNonAdjudicatedValues($record, $map_id_list);
		
		// Loop through mapped RC fields and create array of JUST the multiple choice fields with their enums as a sub-array 
		// (to use for displaying the option choice in adjudication table).
		$rc_mapped_fields_choices = array();
		foreach ($rc_mapped_fields as $this_field) {
			if ($Proj->isMultipleChoice($this_field)) {
				$rc_mapped_fields_choices[$this_field] = parseEnum($Proj->metadata[$this_field]['element_enum']);
			}
		}
		
		## GET EXISTING DATA AND (IF ON A FORM) DATA CURRENTLY ON FORM
		// Pull saved REDCap data for this record for the mapped fields
		$rc_existing_data = Records::getData('array', $record, $rc_mapped_fields, $rc_mapped_events);		
		// If form values were sent (which might be non-saved values), add them on top of $rc_existing_data
		if (!empty($form_data) && is_numeric($event_id)) {
			// Loop through vars and remove all non-real fields
			foreach ($form_data as $key=>$val) {
				// ADD to $rc_existing_data if we made it this far
				$rc_existing_data[$record][$event_id][$key] = $val;
			}
		}					
				
		// LOCKING: Collect form_name/event_ids that are locked for this record (1st level key is event_id, 2nd level key is form_name)
		$lockedFormsEvents = array();
		$sql = "select event_id, form_name from redcap_locking_data where project_id = " . $this->project_id . "
				and record = '" . prep($record) . "'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$lockedFormsEvents[$row['event_id']][$row['form_name']] = true;
		}
		
		// print_array($mappings);
		// print_array($data_array_rc);
				
		// Convert the source data into other array with src_field as key (for better searching)
		$source_data = $md_ids_event_ids = array();
		foreach ($data_array_src as $attr) {
			// Add SRC value and timestamp (if a temporal field)
			if (isset($attr['timestamp'])) {
				// Clean the timestamp in case ends with ".0" or anything else
				$attr['timestamp'] = substr($attr['timestamp'], 0, 19);
				// Add to array
				$source_data[$attr['field']][] = array('src_value'=>$attr['value'], 'src_timestamp'=>$attr['timestamp'], 'md_id'=>$attr['md_id']);
			} else {
				// Add to array
				$source_data[$attr['field']][] = array('src_value'=>$attr['value'], 'md_id'=>$attr['md_id']);
			}
			// Map event_id to md_id for filtering later when using multiple events of data, in which some of the same data may be cached in separate events (i.e. duplicates)
			$md_ids_event_ids[$attr['md_id']] = $attr['event_id'];
		}
		
		## Merge all RC field info and data with all Source field info and data
		// First loop through REDCap data and add to $rc_source_data
		$rc_source_data = array();
		foreach ($data_array_rc as $attr) {
			// Add RC data value, if exists
			$rc_source_data[$attr['event_id']][$attr['rc_field']]['rc_value'] = $rc_existing_data[$record][$attr['event_id']][$attr['rc_field']];
			// Add RC timestamp (if a temporal field)
			if (isset($attr['timestamp'])) {
				$rc_source_data[$attr['event_id']][$attr['rc_field']]['rc_timestamp'] = $attr['timestamp'];
				$rc_source_data[$attr['event_id']][$attr['rc_field']]['rc_preselect'] = $attr['preselect'];
			}
			// Add src_field placeholder
			$rc_source_data[$attr['event_id']][$attr['rc_field']]['src_fields'][$attr['src_field']] = array();
		}
		
		// Now loop through the $rc_source_data and pile the source data on top (and also checking if timestamp is in range for that event)
		foreach ($rc_source_data as $this_event_id=>$evt_attr) {
			foreach ($evt_attr as $rc_field=>$fld_attr) {
				foreach ($fld_attr['src_fields'] as $src_field=>$attr) {
					// If src_field exists in $source_data, then add it to $rc_source_data
					if (isset($source_data[$src_field])) {
						// If temporal, then add as array
						if (isset($source_data[$src_field][0]['src_timestamp'])) {
							// Loop through each timestamp and determine if is in datetime range given the day offset
							foreach ($source_data[$src_field] as $this_src_val_time) {
								// If the md_id of this value does not belong to this_event_id, then skip
								if ($md_ids_event_ids[$this_src_val_time['md_id']] != $this_event_id) continue;
								// Is date in range?
								if (self::dateInRange($this_src_val_time['src_timestamp'], $fld_attr['rc_timestamp'], $day_offset, $day_offset_plusminus)) {
									// Add temporal value
									$rc_source_data[$this_event_id][$rc_field]['src_fields'][$src_field][] = $this_src_val_time;
								}
							}
						} 
						// If not temporal, add just as single value
						else {
							$rc_source_data[$this_event_id][$rc_field]['src_fields'][$src_field] = $source_data[$src_field][0];
						}
					}
					// If src_field attribute array is empty, then remove it (didn't have data that was in the date range)
					if (is_array($rc_source_data[$this_event_id][$rc_field]['src_fields'][$src_field]) 
						&& empty($rc_source_data[$this_event_id][$rc_field]['src_fields'][$src_field])) 
					{
						unset($rc_source_data[$this_event_id][$rc_field]['src_fields'][$src_field]);
					}
				}
			}
		}
		unset($md_ids_event_ids);
		
		// print_array($form_data);
		// print_array($data_array_rc);
		// print_r($rc_source_data);
		
		## FIELD VALIDATION
		// Obtain an array of all Validation Types (from db table)
		$valTypes = getValTypes();
		// Put all fields with field validation into array with field as key and regex_php as value to validate fields later
		$valFields = array();
		foreach ($rc_mapped_fields as $this_field)
		{	
			// Get field's validation type
			$thisValType = $Proj->metadata[$this_field]['element_validation_type'];
			// Convert legacy validtion types
			if ($thisValType == "") {
				continue;
			} else {
				$thisValType = convertLegacyValidationType(convertDateValidtionToYMD($thisValType));
			}
			// Add to array
			$valFields[$this_field] = $valTypes[$thisValType]['regex_php'];
		}
		
		// Set HTML label for INVALID SOURCE VALUE
		$invalidSrcValueLabel = RCView::div(array('style'=>'margin-left:2em;text-indent:-2em;color:red;white-space:normal;line-height:8px;'), 
									RCView::img(array('src'=>'exclamation.png', 'style'=>'vertical-align:middle;')) .
										RCView::span(array('style'=>'vertical-align:middle;'),
											$lang['ws_08']
										)
								);
		// Set HTML label for if the REDCap VALUE = SOURCE VALUE when only ONE source value exists
		$sameSingleSrcValueLabel = RCView::div(array('style'=>'margin-left:2em;text-indent:-2em;color:green;white-space:normal;line-height:8px;'), 
									RCView::img(array('src'=>'tick.png', 'style'=>'vertical-align:middle;')) .
									RCView::span(array('style'=>'vertical-align:middle;'),
										$lang['ws_14']
									)
								);
		// Set HTML label for if the REDCap field is on a LOCKED form/event
		$lockedSrcValueLabel = 	RCView::div(array('style'=>'margin-left:2em;text-indent:-2em;color:#A86700;white-space:normal;line-height:8px;'), 
									RCView::img(array('src'=>'lock.png', 'style'=>'vertical-align:middle;')) .
										RCView::span(array('style'=>'vertical-align:middle;'),
											$lang['esignature_29']
										)
								);
		// Set HTML label for if the REDCap field value is out of range
		$outOfRangeSrcValueLabel = 	RCView::div(array('style'=>'margin-left:2em;text-indent:-2em;color:#A86700;white-space:normal;line-height:8px;'), 
									RCView::img(array('src'=>'exclamation_orange.png', 'style'=>'vertical-align:middle;')) .
										RCView::span(array('style'=>'vertical-align:middle;'),
											$lang['dataqueries_58']
										)
								);
				
		// Get external system ID field
		$external_id_field = $this->getMappedIdFieldExternal();
		$external_id_rc_event = array_pop(array_keys($mappings[$external_id_field]));
		$external_id_rc_field = array_pop(array_keys($mappings[$external_id_field][$external_id_rc_event]));
		$external_id_value = $rc_existing_data[$record][$external_id_rc_event][$external_id_rc_field];
		
		// Create array of REDCap fields/events where the RC value matches a SRC value that was returned
		// Array key = "event_id-RC field name"
		$rcFieldsSameValue = $rcAllFieldsEvents = array();
		
		// Keep count of total exclusions
		$total_exclusions = 0;
		
		// Keep count of items where all values are excluded
		$total_items_all_excluded = 0;
		
		## SORT ALL THE DATA ABOUT TO BE DISPLAYED IN THE TABLE
		// If we have more than one event of data, then sort the events in the correct order
		if (count($rc_source_data) > 1) {
			// Loop through all existing PROJECT events in their proper order
			$rc_source_data_reordered = array();
			foreach (array_keys($Proj->eventInfo) as $this_event_id) {
				// Does this event_id exist for this record?
				if (isset($rc_source_data[$this_event_id])) {
					// Add this event's data to reordered data array
					$rc_source_data_reordered[$this_event_id] = $rc_source_data[$this_event_id];
				}
			}
			// Set back as new
			$rc_source_data = $rc_source_data_reordered;
			unset($rc_source_data_reordered);
		}
		// Now sort all fields according to field order within each event
		foreach ($rc_source_data as &$rc_array) {
			// Gather all field_order values for fields in this event into an array
			$field_orders = array();
			foreach (array_keys($rc_array) as $this_rc_field) {
				$field_orders[] = $Proj->metadata[$this_rc_field]['field_order'];
			}
			// Now sort $rc_array according to field_order value
			array_multisort($field_orders, SORT_REGULAR, $rc_array);
		}
		
		// ob_start();
		// print_array($rc_source_data);
		// print json_encode(array('item_count'=>-2, 'html'=>ob_get_clean()));
		// exit;
		
		// Set default for flag if any temporal fields will be displayed in the popup
		$rtws_temporal_fields_displayed = 0;
		
		## LOOP THROUGH ALL RC FIELDS TO CREATE EACH AS A TABLE ROW
		foreach ($rc_source_data as $this_event_id=>$evt_attr)
		{
			// Get event name
			$this_event_name = $Proj->eventInfo[$this_event_id]['name_ext'];
			
			// SECTION HEADER: If longitudinal, display the event name as a table header
			if ($longitudinal && $output_html) {
				$rows .=	RCView::tr(array('class'=>"adjud_evt_hdr " . ($event_id == $this_event_id ? "" : "rtws-otherform"), 'evtid'=>$this_event_id),
								RCView::td(array('style'=>'border-top:2px solid #999;padding:5px;color:#fff;background-color:#555;font-weight:bold;', 'colspan'=>7),
									$this_event_name
								)
							);
			}
			
			// Loop through RC fields in this event
			foreach ($evt_attr as $rc_field=>$fld_attr)
			{
				// Get the RC field's validation type
				$thisValType = $Proj->metadata[$rc_field]['element_validation_type'];
				
				// Get the RC field's form_name
				$thisFormName = $Proj->metadata[$rc_field]['form_name'];
				
				// Determine if this field exists on a locked form/event
				$isLocked = (isset($lockedFormsEvents[$this_event_id][$thisFormName]));
				
				// Set field name/label string
				$rc_field_label = 	$rc_field .
									RCView::span(array('style'=>'color:#666;font-size:7pt;'),
										" &nbsp;\"" . $Proj->metadata[$rc_field]['element_label'] . "\""
									);
									
				// If we're on a data entry form, then set a class for the table row for fields NOT on this form/event
				$onThisFormClass = (isset($form_data[$rc_field]) && $event_id == $this_event_id) ? "" : "rtws-otherform";
									
				// Format the RC data value if a multiple choice field to display the option label
				if (!isset($rc_mapped_fields_choices[$rc_field]) || $fld_attr['rc_value'] == '') {
					// Display raw value
					$rc_value_formatted = $fld_attr['rc_value'];
				} else {
					// Display label and raw value
					$rc_value_formatted = $rc_mapped_fields_choices[$rc_field][$fld_attr['rc_value']] . " " 
										. RCView::span(array('style'=>'color:#777;'), "(" . $fld_attr['rc_value'] . ")");
				}
				
				## NON-TEMPORAL (single row)
				if (!isset($fld_attr['rc_timestamp'])) 
				{
					// Get source field name and value
					$src_field = array_shift(array_keys($fld_attr['src_fields']));
					$src_value = $fld_attr['src_fields'][$src_field]['src_value'];
					$thisMdId = $fld_attr['src_fields'][$src_field]['md_id'];
					
					// Determine if the source value has been excluded
					$src_value_excluded = false;
					$src_value_excluded_action = '1';
					$src_value_excluded_text = RCView::img(array('src'=>'cross.png', 'class'=>'imgfix2 opacity50', 'title'=>$lang['dataqueries_87']));
					$src_value_excluded_class = '';
					if (isset($excluded_values_by_md_id[$thisMdId])) {
						$src_value_excluded = true;
						$src_value_excluded_action = '0';
						$src_value_excluded_text = RCView::img(array('src'=>'plus2.png', 'class'=>'imgfix2 opacity50', 'title'=>$lang['dataqueries_88']));
						$src_value_excluded_class = 'darkRedClr';
						$total_exclusions++;
						// Count the entire item as excluded if we're not showing exclusions
						if (!$show_excluded) $total_items_all_excluded++;
					}
					
					// If RC value and SRC value are the same, then add to array
					$rcAllFieldsEvents["adjud_tr-$this_event_id-$rc_field"] = true;
					$preSelectRadio = "";
					$radioLinkVisibility = "hidden";
					$radioLinkBgColor = "";
					if ($src_value == $fld_attr['rc_value']) {
						$rcFieldsSameValue["adjud_tr-$this_event_id-$rc_field"] = true;
					} elseif (!$isLocked && !$src_value_excluded) {
						$preSelectRadio = "checked";
						$radioLinkVisibility = "visible";
						$radioLinkBgColor = "radiogreen";
					}
					
					// Output the row
					if ($output_html && ($show_excluded || !$src_value_excluded)) 
					{					
						// Set bg color for RC value cell if value exists in RC
						$rcValBgColor = '';
						if ($fld_attr['rc_value'] != '') {
							$rcValBgColor = ($src_value == $fld_attr['rc_value']) ? 'background-color:#C7ECC0;' : 'background-color:#ddd;';
						}
						
						// Set html for Exclude link
						$excludeLink = RCView::a(array('href'=>'javascript:;', 'onclick'=>"excludeValue($thisMdId,$src_value_excluded_action,$(this));", 'class'=>"reset $src_value_excluded_class", 'style'=>"font-size:8pt;text-decoration:underline;"), $src_value_excluded_text);
												
						// If field is on a locked form/event, then don't display radio button
						if ($isLocked) {
							$radioButton = $lockedSrcValueLabel;
						// If RC value is same as SRC value, then don't display radio button
						} elseif ($src_value == $fld_attr['rc_value']) {
							$radioButton = $sameSingleSrcValueLabel;
							$excludeLink = ''; // Don't show exclude link for existing values
							$radioLinkBgColor = "";
						} else {						
							// Set radio button and hidden reset link for cell
							// If a date[time] field that's not in YMD format, then convert to YMD
							if (substr($thisValType, 0, 4) == 'date' && (substr($thisValType, -4) == '_mdy'|| substr($thisValType, -4) == '_dmy')) {
								$src_value_radio = DateTimeRC::datetimeConvert($src_value, 'ymd', substr($thisValType, -3), 'ymd');
							} else {
								// Not a DMY/MDY date[time] field
								$src_value_radio = $src_value;
							}
							$radioButton = 	RCView::div(array('style'=>'float:left;padding-left:8px;'), 
												RCView::radio(array('class'=>'rtws_adjud_radio', 'style'=>'visibility:'.($src_value_excluded ? 'hidden' : 'visible'), $preSelectRadio=>$preSelectRadio, 'name'=>"rtws_adjud-$thisMdId-$this_event_id-$rc_field", 'value'=>$src_value_radio))
											) .
											RCView::div(array('style'=>'float:right;padding:2px 4px 0 0;'), 
												RCView::a(array('href'=>'javascript:;', 'onclick'=>"radioResetValAdjud('rtws_adjud-$thisMdId-$this_event_id-$rc_field','rtws_adjud_form')", 'class'=>'reset opacity50', 'style'=>"visibility:$radioLinkVisibility;font-size:8pt;"), $lang['form_renderer_20'])
											);
						}
						
						// Format the source data value if a multiple choice field to display the option label
						if (!isset($rc_mapped_fields_choices[$rc_field])) {
							// Display raw value (non-MC field)
							$src_value_formatted = $src_value;
							// FIELD VALIDATION: If field has validation and value fails validation, then give error
							if (isset($valFields[$rc_field]) && !preg_match($valFields[$rc_field], $src_value)) {
								$radioButton = $invalidSrcValueLabel;
								$radioLinkBgColor = "";
							} 
							// If data type is valid and has range validation, then check if within range
							elseif (isset($valFields[$rc_field]) && ($Proj->metadata[$rc_field]['element_validation_min'] != ''
								|| $Proj->metadata[$rc_field]['element_validation_max'] != '')) 
							{
								if ($src_value < $Proj->metadata[$rc_field]['element_validation_min']
									|| $src_value > $Proj->metadata[$rc_field]['element_validation_max']) 
								{										
									$src_value_formatted .= $outOfRangeSrcValueLabel;
								}
							}
						} elseif (isset($rc_mapped_fields_choices[$rc_field][$src_value])) {
							// MC field: Display label and raw value
							$src_value_formatted = $rc_mapped_fields_choices[$rc_field][$src_value] . " " 
												 . RCView::span(array('style'=>'color:#777;'), "($src_value)");
						} else {
							// Source value is NOT valid, so do not allow import and show error
							$src_value_formatted = $src_value;
							$radioButton = $invalidSrcValueLabel;
							$radioLinkBgColor = "";
						}
							
						// Display single row
						$rows .= RCView::tr(array('class'=>"evtfld-$this_event_id adjud_tr-$this_event_id-$rc_field $onThisFormClass", 'md_id'=>$thisMdId),
									(!$longitudinal ? '' :
										RCView::td(array('class'=>'odd', 'style'=>'line-height:11px;border-top:2px solid #999;'),
											$this_event_name
										)
									) .
									RCView::td(array('class'=>'odd', 'style'=>'border-top:2px solid #999;word-wrap:break-word;'),
										$rc_field_label
									) .
									RCView::td(array('class'=>'odd', 'style'=>'border-top:2px solid #999;'),
										" -"
									) .
									RCView::td(array('class'=>'odd', 'style'=>'border-top:2px solid #999;'),
										" -"
									) .
									RCView::td(array('class'=>'odd','style'=>'border-top:2px solid #999;'.$rcValBgColor),
										$rc_value_formatted
									) .
									RCView::td(array('class'=>'odd', 'style'=>'color:#C00000;border-top:2px solid #999;'),
										$src_value_formatted
									) .
									RCView::td(array('class'=>"odd $radioLinkBgColor",'style'=>'white-space:nowrap;border-top:2px solid #999;'),
										$radioButton
									)
									/* 
									. RCView::td(array('class'=>'odd', 'style'=>'text-align:center;border-top:2px solid #999;'),
										$excludeLink
									)
									*/
								);
					}
				}
				## TEMPORAL (possibly multiple rows)
				else 
				{
					// Set flag noting that temporal fields will be displayed in the popup
					$rtws_temporal_fields_displayed = 1;
					// Set which sub-row we're on, starting with 0
					$rc_field_loop = 0;
					// Count how many rows we'll need
					$rowspan = 0;
					foreach ($fld_attr['src_fields'] as $src_field=>$src_attr) {
						$rowspan += count($src_attr);
					}
					// Set flag for if this item contains at least one non-adjudicated value
					$hasNonAdjudicatedValues = 0;
					// Set array of src_fields concatenated with timestamp and value (so we can loop more easily through them all
					$this_row_src_fields = $this_row_sort_fields = $theseMdIds = array();
					foreach ($fld_attr['src_fields'] as $subrow_src_field=>$subrow_src_attr) {
						foreach ($subrow_src_attr as $subrow_src_attr2) {
							// Add each value's md_id to the array so that we have all md_id's for this item
							$theseMdIds[] = $subrow_src_attr2['md_id'];
							// Check if this md_id is a non-adjudicated value. If so, set flag to TRUE.
							if (isset($non_adjudicated_values_by_md_id[$subrow_src_attr2['md_id']])) {
								$hasNonAdjudicatedValues++;
							}
							// If timestamp is only a date, then append with 00:00
							$sort_timestamp = $subrow_src_attr2['src_timestamp'];
							if (strlen($subrow_src_attr2['src_timestamp']) == 10) {
								$sort_timestamp .= ' 00:00';
							}
							// Add to array
							$this_row_src_fields[] = $subrow_src_attr2['src_timestamp']."|".$subrow_src_field."|".$subrow_src_attr2['md_id']."|".$subrow_src_attr2['src_value'];
							$this_row_sort_fields[] = $sort_timestamp."-".$subrow_src_attr2['src_value']."-".$subrow_src_field."-".$subrow_src_attr2['md_id'];
						}
					}
					// Sort values by timestamp
					array_multisort($this_row_sort_fields, SORT_REGULAR, $this_row_src_fields);
					
					
					//print "\nfield: $rc_field, span: $rowspan, \$hasNonAdjudicatedValues: $hasNonAdjudicatedValues";
					//print_r($fld_attr['src_fields']);
					
					## PRE-SELECTION
					if ($output_html) 
					{
						// Set preselection option value if field has more than 1 row
						$preselect = ($rowspan > 1) ? $fld_attr['rc_preselect'] : '';
						$preselect_value = ""; // Default
						## "NEAR" PRE-SELECT
						// If preselect option is MIN or MAX, then loop through all values first to determine min or max
						if ($preselect == 'NEAR') {
							// Get RC timestamp
							$rc_timestamp_string = strtotime($fld_attr['rc_timestamp']);
							// Place all numbers into an array
							$src_ts_values = array();
							for ($i = 0; $i < $rowspan; $i++) {
								// Get source field name, timestamp, and value
								list ($srcTS, $nothing2, $thisMdId2, $this_src_value) = explode("|", $this_row_src_fields[$i], 4);
								// If the value is excluded for this item, then do not preselect it
								if (isset($excluded_values_by_md_id[$thisMdId2])) {
									continue;
								}
								// Add values to array and calculate proximity in time of each value
								$src_ts_values[abs($rc_timestamp_string-strtotime($srcTS))] = $this_src_value;
							}
							// Get closest value based on time proximity
							$smallestKey = min(array_keys($src_ts_values));
							$preselect_value = $src_ts_values[$smallestKey];
							// Remove array
							unset($src_ts_values);
						}
						## MIN/MAX PRE-SELECT
						// If preselect option is MIN or MAX, then loop through all values first to determine min or max
						elseif ($preselect == 'MIN' || $preselect == 'MAX') {
							// Place all numbers into an array
							$src_value_numbers = array();
							for ($i = 0; $i < $rowspan; $i++) {
								// Get source field name, timestamp, and value
								list ($nothing1, $nothing2, $thisMdId2, $this_src_value) = explode("|", $this_row_src_fields[$i], 4);
								// If the value is excluded for this item, then do not preselect it
								if (isset($excluded_values_by_md_id[$thisMdId2])) {
									continue;
								}
								// If value is numerical, then add to number array
								if (is_numeric($this_src_value)) {
									$src_value_numbers[] = $this_src_value;
								}
							}
							// Get the preselected value (min or max) for this item
							$preselect_value = ($preselect == 'MIN') ? min($src_value_numbers) : max($src_value_numbers);	
							// Remove array
							unset($src_value_numbers);					
						} 
						## SAME DAY PRE-SELECT
						// If not value is pre-selected BUT only one value falls on the same day as the REDCap date, then pre-select it
						elseif ($preselect == '') {
							$sameDateCount = 0;
							//print "\n\n$rc_field:";
							for ($i = 0; $i < $rowspan; $i++) {								
								// Get source field name, timestamp, and value
								list ($this_src_timestamp, $nothing1, $nothing2, $this_src_value) = explode("|", $this_row_src_fields[$i], 4);
								// If the src and RC dates are the same, then pre-select it
								if (substr($this_src_timestamp, 0, 10) == substr($fld_attr['rc_timestamp'], 0, 10)) {
									// Get this date's value and increment counter
									$sameDateValue = $this_src_value;
									$sameDateCount++;
								}
							}
							// If only one day matched the RC date, then pre-select that one
							if ($sameDateCount == 1) {
								$preselect_value = $sameDateValue;
							}
						}
					}
					
					// Keep track of number of exclusions for this item
					$excluded_mdids = array_intersect($theseMdIds, array_keys($excluded_values_by_md_id));
					$total_exclusions_this_item = count($excluded_mdids);
					// PRE-SELECTION: If item should have LAST value pre-selected, then set that value now so we know which it is when we get to it
					if ($output_html && $preselect == 'LAST') {
						if ($total_exclusions_this_item == 0) {
							// Since there are no exclusions, just get the very last value
							list ($nothing1, $nothing2, $nothing3, $preselect_value) = explode("|", $this_row_src_fields[$rowspan-1], 4);
						} else {
							// Since exclusions exist, first remove excluded values before we can determine the last one
							$non_excluded_values_this_item = array();
							for ($i = 0; $i < $rowspan; $i++) {
								$excluded_values_this_item_temp = explode("|", $this_row_src_fields[$i], 4);
								$thisMdId3 = $excluded_values_this_item_temp[2];
								if (!in_array($thisMdId3, $excluded_mdids)) {
									$non_excluded_values_this_item[$thisMdId3] = $excluded_values_this_item_temp[3];
								}
							}
							$preselect_value = array_pop($non_excluded_values_this_item);
						}					
					}
						
					// Add formatting to src timestamp
					if (strpos($fld_attr['rc_timestamp'], " ") !== false) {
						// REDCap Date/Time
						list ($rc_timestamp1, $rc_timestamp2) = explode(" ", $fld_attr['rc_timestamp'], 2);
						$rc_timestamp_formatted = $rc_timestamp1 . RCView::span(array('class'=>'adjud_ts'), $rc_timestamp2);
					} else {
						// REDCap Date, so add "(00:00)" to show that it is assumed to use midnight as reference point
						$rc_timestamp_formatted = $fld_attr['rc_timestamp'] . RCView::span(array('class'=>'adjud_ts'), '(00:00)');
					}
					
					// Set flag to note which value is the first non-excluded value for this item.
					// Default as false, then true for first non-excluded value, then Null for any values after being true.
					$is_first_nonexcluded_value_this_item = false;
					
					// Loop through all subrows/values
					for ($i = 0; $i < $rowspan; $i++)
					{
						// Get source field name, timestamp, and value
						list ($src_timestamp, $src_field, $thisMdId, $src_value) = explode("|", $this_row_src_fields[$i], 4);
					
						// If RC value and SRC value are the same, then add to array
						$rcAllFieldsEvents["adjud_tr-$this_event_id-$rc_field"] = true;
						if ($src_value == $fld_attr['rc_value'] 
							// If this item has at least one value that's not been adjudicated (get imported after other values were adjudicated),
							// then show this item so that user has a chance to see the new value (in case they need to import it and
							// overwrite the existing value).
							&& !($hasNonAdjudicatedValues > 0 && $hasNonAdjudicatedValues < $rowspan)) 
						{
							$rcFieldsSameValue["adjud_tr-$this_event_id-$rc_field"] = true;
						}
					
						// If we have already noted the first non-excluded value for this item, then set this to null.
						if ($is_first_nonexcluded_value_this_item === true) {
							$is_first_nonexcluded_value_this_item = null;
						}
						// Determine if the source value has been excluded
						$src_value_excluded = false;
						$src_value_excluded_action = '1';
						$src_value_excluded_text = RCView::img(array('src'=>'cross.png', 'class'=>'imgfix2 opacity50', 'title'=>$lang['dataqueries_87']));
						$src_value_excluded_class = '';
						if (isset($excluded_values_by_md_id[$thisMdId])) {
							$src_value_excluded = true;
							$src_value_excluded_action = '0';
							$src_value_excluded_text = RCView::img(array('src'=>'plus2.png', 'class'=>'imgfix2 opacity50', 'title'=>$lang['dataqueries_88']));
							$src_value_excluded_class = 'darkRedClr';
							$total_exclusions++;
							// Count the entire item as excluded if we're not showing exclusions (only do this for very last value of this item)
							if (!$show_excluded && $i == ($rowspan-1) && $total_exclusions_this_item >= $rowspan) $total_items_all_excluded++;
						}
						// If this is the first non-excluded value for this item, set flag to true.
						elseif ($is_first_nonexcluded_value_this_item === false) {
							$is_first_nonexcluded_value_this_item = true;
						}
						
						if ($output_html && ($show_excluded || !$src_value_excluded)) 
						{
							// Set attributes to pre-select field if only has one value returned
							if (!$isLocked && (($rowspan == 1 && $src_value != $fld_attr['rc_value']) 
								// If ealiest value should be preselected
								|| ($preselect == 'FIRST' && $is_first_nonexcluded_value_this_item === true)
								// If latest value should be preselected
								|| ($preselect == 'LAST' && $src_value == $preselect_value))
							) {
								$preSelectRadio = "checked";
								$radioLinkVisibility = "visible";
								$radioLinkBgColor = "radiogreen";
								// Reset $preselect_value so that it doesn't preselect 2 values if 2 values are identical
								if ($preselect == 'LAST' && $src_value == $preselect_value) {
									$preselect_value = '';
								}
							// If max or min or nearest value should be preselected AND this is that value, then preselect it
							} elseif (($preselect == 'MIN' || $preselect == 'MAX' || $preselect == 'NEAR' || $preselect == '') && $src_value == $preselect_value) {
								$preSelectRadio = "checked";
								$radioLinkVisibility = "visible";
								$radioLinkBgColor = "radiogreen";
								// Reset $preselect_value so that it doesn't preselect 2 values if 2 values are identical
								$preselect_value = '';
							} else {
								$preSelectRadio = "";
								$radioLinkVisibility = ($src_value_excluded ? "hidden" : "visible");
								$radioLinkBgColor = "";
							}
							
							// If existing value matches a src value already, then do NOT preselect an option
							if (isset($rcFieldsSameValue["adjud_tr-$this_event_id-$rc_field"])) {
								$preselect_value = '';
								$preSelectRadio = "";
								$radioLinkVisibility = "hidden";
								$radioLinkBgColor = "";
							}
							
							// print "\n$rc_field, $preselect, $preselect_value, $src_value == $preselect_value";
							
							// If this item contains any non-adjudicated values, then make sure nothing gets preselected
							if ($hasNonAdjudicatedValues > 0 && $hasNonAdjudicatedValues < $rowspan) $preselect_value = '';
							
							// Add formatting to src timestamp
							if (strpos($src_timestamp, " ") !== false) {
								list ($src_timestamp1, $src_timestamp2) = explode(" ", $src_timestamp, 2);
								$src_timestamp_formatted = $src_timestamp1 . 
															RCView::span(array('class'=>'adjud_ts'), substr($src_timestamp2, 0, 5));
							} else {
								$src_timestamp_formatted = $src_timestamp;
							}
						
							// Set html for Exclude link
							$excludeLink = RCView::a(array('href'=>'javascript:;', 'onclick'=>"excludeValue($thisMdId,$src_value_excluded_action,$(this));", 'class'=>"reset $src_value_excluded_class", 'style'=>"font-size:8pt;text-decoration:underline;"), $src_value_excluded_text);
														
							// If field is on a locked form/event, then don't display radio button
							if ($isLocked) {
								$radioButton = $lockedSrcValueLabel;
							// If RC value is same as SRC value, then don't display radio button
							} elseif ($src_value == $fld_attr['rc_value']) {
								$radioButton = $sameSingleSrcValueLabel;
								// Don't show exclude link for existing values
								$excludeLink = '';
								$radioLinkBgColor = "";
								// If this item contains any non-adjudicated values, then add a hidden input with the value so that
								// any non-adjudicated values will get set as adjudicated upon save (otherwise the item will just keep showing up).
								if ($hasNonAdjudicatedValues > 0) {
									$radioButton .= RCView::hidden(array('name'=>"rtws_adjud-$thisMdId-$this_event_id-$rc_field", 'value'=>$src_value)); 
								}
							} else {
								// Set radio button and hidden reset link for cell
								// If a date[time] field that's not in YMD format, then convert to YMD
								if (substr($thisValType, 0, 4) == 'date' && (substr($thisValType, -4) == '_mdy'|| substr($thisValType, -4) == '_dmy')) {
									$src_value_radio = DateTimeRC::datetimeConvert($src_value, 'ymd', substr($thisValType, -3));
								} else {
									// Not a DMY/MDY date[time] field
									$src_value_radio = $src_value;
								}
								$radioButton = 	RCView::div(array('style'=>'float:left;padding-left:8px;'),
													RCView::radio(array('class'=>'rtws_adjud_radio', 'style'=>'visibility:'.($src_value_excluded ? 'hidden' : 'visible'), $preSelectRadio=>$preSelectRadio, 'name'=>"rtws_adjud-$thisMdId-$this_event_id-$rc_field", 'value'=>$src_value_radio))
												) .
												RCView::div(array('style'=>'float:right;padding:2px 4px 0 0;'), 
													RCView::a(array('href'=>'javascript:;', 'onclick'=>"radioResetValAdjud('rtws_adjud-$thisMdId-$this_event_id-$rc_field','rtws_adjud_form')", 'class'=>'reset opacity50', 'style'=>"visibility:$radioLinkVisibility;font-size:8pt;"), $lang['form_renderer_20'])
												);
							}
							
							// Format the source data value if a multiple choice field to display the option label
							if (!isset($rc_mapped_fields_choices[$rc_field])) {						
								// Display raw value (non-MC field)
								$src_value_formatted = $src_value;
								// FIELD VALIDATION: If field has validation and value fails validation, then give error
								if (isset($valFields[$rc_field]) && !preg_match($valFields[$rc_field], $src_value)) 
								{
									$radioButton = $invalidSrcValueLabel;
									$radioLinkBgColor = "";
								} 
								// If data type is valid and has range validation, then check if within range
								elseif (isset($valFields[$rc_field]) && ($Proj->metadata[$rc_field]['element_validation_min'] != ''
									|| $Proj->metadata[$rc_field]['element_validation_max'] != '')) 
								{
									if ($src_value < $Proj->metadata[$rc_field]['element_validation_min']
										|| $src_value > $Proj->metadata[$rc_field]['element_validation_max']) 
									{										
										$src_value_formatted .= $outOfRangeSrcValueLabel;
									}
								}
							} elseif (isset($rc_mapped_fields_choices[$rc_field][$src_value])) {
								// Display label and raw value
								$src_value_formatted = $rc_mapped_fields_choices[$rc_field][$src_value] . " " 
													 . RCView::span(array('style'=>'color:#777;'), "($src_value)");
							} else {
								// Source value is NOT valid, so do not allow import and show error
								$src_value_formatted = $src_value;
								$radioButton = $invalidSrcValueLabel;
								$radioLinkBgColor = "";
							}
							
							// Make cell for source date green if occurs on same day as REDCap date
							$srcTsBgColor = (substr($fld_attr['rc_timestamp'], 0, 10) == substr($src_timestamp, 0, 10)) ? 'background-color:#C7ECC0;' : '';
							$rcValBgColor = '';
							if ($fld_attr['rc_value'] != '') {
								$rcValBgColor = ($src_value == $fld_attr['rc_value']) ? 'background-color:#C7ECC0;' : 'background-color:#ddd;';
							}
							
							## Display first row (or display the first non-excluded value/row for this item if hiding excluded values)
							if ($i == 0 || (!$show_excluded && $is_first_nonexcluded_value_this_item === true)) {
								$rows .= RCView::tr(array('class'=>"evtfld-$this_event_id adjud_tr-$this_event_id-$rc_field $onThisFormClass", 'md_id'=>$thisMdId),
											(!$longitudinal ? '' :
												RCView::td(array('class'=>'odd', 'style'=>'line-height:11px;border-top:2px solid #999;', 'rowspan'=>($rowspan-($show_excluded ? 0 : $total_exclusions_this_item)), 'valign'=>'top'),
													$this_event_name
												)
											) .
											RCView::td(array('class'=>'odd', 'style'=>'word-wrap:break-word;border-top:2px solid #999;', 'rowspan'=>($rowspan-($show_excluded ? 0 : $total_exclusions_this_item)), 'valign'=>'top'),
												$rc_field_label
											) .
											RCView::td(array('class'=>'odd', 'style'=>'border-top:2px solid #999;', 'rowspan'=>($rowspan-($show_excluded ? 0 : $total_exclusions_this_item)), 'valign'=>'top'),
												$rc_timestamp_formatted
											) .
											RCView::td(array('class'=>'odd', 'style'=>'border-top:2px solid #999;'.$srcTsBgColor.($rowspan == 1 ? '' : 'border-bottom:1px solid #eee;')),
												$src_timestamp_formatted
											) .
											RCView::td(array('class'=>'odd', 'style'=>'border-top:2px solid #999;'.$rcValBgColor.($rowspan == 1 ? '' : 'border-bottom:1px solid #eee;')),
												$rc_value_formatted
											) .
											RCView::td(array('class'=>'odd', 'style'=>'color:#C00000;border-top:2px solid #999;'.($rowspan == 1 ? '' : 'border-bottom:1px solid #eee;')),
												$src_value_formatted
											) .
											RCView::td(array('class'=>"odd $radioLinkBgColor", 'style'=>'border-top:2px solid #999;white-space:nowrap;'.($rowspan == 1 ? '' : 'border-bottom:1px solid #eee;')),
												$radioButton
											) 
											/* 
											. RCView::td(array('class'=>'odd', 'style'=>'text-align:center;border-top:2px solid #999;'.($rowspan == 1 ? '' : 'border-bottom:1px solid #eee;')),
												$excludeLink
											)
											*/
										);
							
							}
							## Display non-first rows
							else {
								$rows .= RCView::tr(array('class'=>"evtfld-$this_event_id adjud_tr-$this_event_id-$rc_field $onThisFormClass", 'md_id'=>$thisMdId),
											RCView::td(array('class'=>'odd', 'style'=>$srcTsBgColor),
												$src_timestamp_formatted
											) .
											RCView::td(array('class'=>'odd', 'style'=>$rcValBgColor),
												$rc_value_formatted
											) .
											RCView::td(array('class'=>'odd', 'style'=>'color:#C00000;'),
												$src_value_formatted
											) .
											RCView::td(array('class'=>"odd $radioLinkBgColor", 'style'=>'white-space:nowrap;'),
												$radioButton
											)
											/* 
											. RCView::td(array('class'=>'odd', 'style'=>'text-align:center;'),
												$excludeLink
											)
											*/
										);
							}
						}
					}
					// Increment $rc_field_loop
					$rc_field_loop++;
				}
			}
		}
		
		
		// Count the number of items that need to be adjudicated
		$itemsToAdjudicate = (count($rcAllFieldsEvents) - count($rcFieldsSameValue) - $total_items_all_excluded);
		
		
		// Set table html
		$html = '';
		if ($output_html) 
		{
			// If ALL rows in table are HIDDEN, then give a note
			// if ($itemsToAdjudicate == 0) {
			
			// }
			
			// Get the last time data was fetched
			$lastFetchTimeText = $this->getLastFetchTime($record, true);
			
			// Add row above table header to display number of new and hidden items
			$itemNumBar = RCView::div(array('style'=>'border:1px solid #ccc;border-bottom:0;border-top:0;padding:10px;background-color:#eee;max-width:100%;'),
							// New items
							RCView::div(array('style'=>'padding-top:3px;float:left;font-weight:bold;font-size:14px;'),
								RCView::span(array('style'=>'vertical-align:middle;'), "New items:") . 
								RCView::span(array('style'=>'vertical-align:middle;margin-left:7px;color:#C00000;font-size:18px;'), $itemsToAdjudicate) .
								RCView::span(array('style'=>'vertical-align:middle;margin-left:50px;color:#777;font-size:13px;font-weight:normal;'), 
									$lang['ws_141'] . RCView::SP . $lastFetchTimeText
								)
							) .
							// Button to limit to filter to fields on this form (ONLY IF on a form)
							(!(!empty($form_data) && is_numeric($event_id)) ? '' :
								RCView::div(array('style'=>'float:left;margin-left:50px;color:#555;font-size:12px;font-weight:normal;'),						
									RCView::div(array('style'=>''),
										RCView::radio(array('name'=>'rtws-otherformfields-radio', 'value'=>'allforms', 'checked'=>'checked', 'onclick'=>"hideOtherFormFields(0)")) .
										$lang['ws_142'] . " " .
										($longitudinal ? $lang['ws_155'] : $lang['ws_156'])
									) .
									RCView::div(array('style'=>''),
										RCView::radio(array('name'=>'rtws-otherformfields-radio', 'value'=>'thisform', 'onclick'=>"hideOtherFormFields(1)")) .
										$lang['ws_143']
									)
								)
							) .
							RCView::div(array('style'=>'float:right;text-align:right;'),
								// Hidden Items buttons
								(empty($rcFieldsSameValue) ? '' :
									RCView::button(array('id'=>'btn_existing_items_show', 'log_view'=>'1', 'class'=>'jqbuttonmed', 'style'=>'font-size:11px;color:#000066;margin-left:10px;', 'onclick'=>"showHiddenAdjudRows(true); return false;"), 
										$lang['global_84']." ".count($rcFieldsSameValue)." ".$lang['ws_145']
									) .									
									RCView::button(array('id'=>'btn_existing_items_hide', 'class'=>'jqbuttonmed', 'style'=>'display:none;font-size:11px;color:#000066;margin-left:10px;', 'onclick'=>"showHiddenAdjudRows(false); return false;"), 
										$lang['ws_144']." ".count($rcFieldsSameValue)." ".$lang['ws_146']
									)
								) .
								// Exclusions button
								(($total_exclusions == 0 || $show_excluded) ? '' :
									(empty($rcFieldsSameValue) ? '' : RCView::br()) .
									RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:11px;color:#C00000;margin-left:10px;', 'onclick'=>"
										if (confirmRefetchSourceData()) {
											triggerRTWSmappedField( $('#rtws_adjud_popup_recordname').text(), true, 1, true);
										}
										return false;
									"), 
										$lang['global_84']." $total_exclusions ".$lang['ws_147']
									)
								)
							) .
							RCView::div(array('class'=>'clear'), '')
						);
					
			// Table header
			$rows = RCView::tr(array('class'=>($itemsToAdjudicate != 0 ? '' : 'hidden')),
						(!$longitudinal ? '' :
							RCView::th(array('class'=>'header', 'style'=>'padding:10px;width:100px;'),
								$lang['ws_148']
							)
						) .
						RCView::th(array('class'=>'header', 'style'=>'padding:10px;'),
							$lang['ws_149']
						) .
						RCView::th(array('class'=>'header', 'style'=>'padding:10px 5px;width:108px;'),
							$lang['ws_150']
						) .
						RCView::th(array('class'=>'header', 'style'=>'padding:10px 5px;width:108px;'),
							RCView::div(array('style'=>'color:#C00000;'), $this->getSourceSystemName()) .
							RCView::div(array(), $lang['ws_151'])
						) .
						RCView::th(array('class'=>'header', 'style'=>'padding:10px 5px;width:100px;'),
							RCView::div(array(), "REDCap") .
							RCView::div(array(), $lang['ws_152'])
						) .
						RCView::th(array('class'=>'header', 'style'=>'padding:10px 5px;width:100px;'),
							RCView::div(array('style'=>'color:#C00000;'), $this->getSourceSystemName()) .
							RCView::div(array(), $lang['ws_153'])
						) .
						RCView::th(array('class'=>'header', 'style'=>'font-size:13px;color:#000066;text-align:center;padding:10px 3px;width:75px;'),
							$lang['ws_154'] .
							RCView::div(array('style'=>'text-align:center;'),
								RCView::img(array('src'=>'go-down.png', 'style'=>'vertical-align:middle;'))
							)
						)
						/* 
						. RCView::th(array('class'=>'header', 'style'=>'text-align:center;font-size:11px;font-weight:normal;padding:10px 3px;width:60px;'),
							RCView::div(array('class'=>'nowrap'),
								"Exclude" . 
								RCView::a(array('href'=>'javascript:;', 'style'=>'margin-left:3px;', 'onclick'=>"rtwsExcludeExplain();"),
									RCView::img(array('src'=>'help.png', 'style'=>'vertical-align:middle;'))
								)
							)
						)
						*/
					) .
					// Table rows
					$rows;
			
			// Form/table html
			$html .=	// Div to display number of items
						$itemNumBar .
						RCView::form(array('method'=>'post', 'name'=>'rtws_adjud_form', 'id'=>'rtws_adjud_form', 'style'=>'margin:0 0 20px;', 'action'=>APP_PATH_WEBROOT."DynamicDataPull/index.php?pid=".$this->project_id, 'enctype'=>'multipart/form-data'), 
							// Table
							RCView::table(array('id'=>'adjud_table', 'class'=>'form_border', 'style'=>'table-layout:fixed;width:100%;'), $rows) .
							// Hidden field for REDCap mapped ID field
							RCView::hidden(array('id'=>'hidden_source_id_value', 'name'=>"rtws_adjud--$external_id_rc_event-$external_id_rc_field", "value"=>$external_id_value)) .
							// Hidden field to denote if this record exists already (to deal with record auto-numbering)
							RCView::hidden(array('name'=>"rtws_adjud-record_exists", "value"=>$record_exists))
						);
							
			// HIDDEN ELEMENTS INSIDE DIALOG
			// Render an invisible div containing the event_id-rc_fieldname of all rows to hide
			$html .= RCView::div(array('id'=>'adjud_tr_classes', 'class'=>'hidden'), implode(",", array_keys($rcFieldsSameValue)));
			// Place flag if any temporal fields are being displayed (so we can hide certain labels if they are not)
			$html .= RCView::hidden(array('id'=>"rtws_temporal_fields_displayed", "value"=>$rtws_temporal_fields_displayed));
		}
		
		// Set the new item count for this record in the table
		$sql = "update redcap_ddp_records set item_count = $itemsToAdjudicate 
				where record = '" . prep($record) . "' and project_id = " . $this->project_id;
		$q = db_query($sql);
		
		// Return $itemsToAdjudicate and all html as array
		return array($itemsToAdjudicate, $html);
	}
	

	/**
	 * GET NEW ITEM COUNT FOR A RECORD
	 * Returns integer for count (if not exists, returns null)
	 */
	private function getNewItemCount($record)
	{
		$item_count = null;
		$sql = "select item_count from redcap_ddp_records
				where record = '" . prep($record) . "' and project_id = " . $this->project_id;
		$q = db_query($sql);
		if ($q && db_num_rows($q)) {
			$item_count = db_result($q, 0);
		}
		// Return count is exists, else return null
		return (is_numeric($item_count) ? $item_count : null);
	}
	

	/**
	 * LOG ALL THE SOURCE DATA POINTS (MD_ID'S) VIEWED BY THE USER
	 * Returns nothing.
	 */
	public function logDataView($external_id_value=null, $md_ids_viewed=array())
	{
		// Keep count of data points logged
		$num_logged = 0;
		if (!empty($md_ids_viewed)) 
		{
			// Get the timestamp and external source field names for the md_id's
			$md_ids_data = array();
			$sql = "select d.md_id, m.external_source_field_name, d.source_timestamp 
					from redcap_ddp_records_data d, redcap_ddp_mapping m 
					where m.map_id = d.map_id and d.md_id in (".prep_implode(array_unique($md_ids_viewed)).") 
					and m.project_id = ".$this->project_id."";
			$q = db_query($sql);
			if (db_num_rows($q) < 1) return $num_logged;
			while ($row = db_fetch_assoc($q)) {
				$md_ids_data[$row['md_id']] = array('field'=>$row['external_source_field_name'], 'timestamp'=>$row['source_timestamp']);
			}
			// Get ui_id of user
			$userInfo = User::getUserInfo(USERID);
			// Now log all these md_ids in mapping_logging table
			$sql = "insert into redcap_ddp_log_view (time_viewed, user_id, project_id, source_id)
					values ('".NOW."', ".checkNull($userInfo['ui_id']).", ".$this->project_id.", '".prep($external_id_value)."')";
			if (db_query($sql)) {
				// Get ml_id from insert
				$ml_id = db_insert_id();
				// Now add each data point to mapping_logging_data table
				foreach ($md_ids_data as $md_id=>$attr) {
					$sql = "insert into redcap_ddp_log_view_data (ml_id, source_field, source_timestamp, md_id)
							values ($ml_id, '".prep($attr['field'])."', ".checkNull($attr['timestamp']).", $md_id)";
					if (db_query($sql)) $num_logged++;
				}
				// If somehow no data points were logged, then remove this instance from logging table
				if ($num_logged == 0) {
					$sql = "delete from redcap_ddp_log_view where ml_id = $ml_id";
					db_query($sql);
				}
			}
		}
		// Return count of data points that were logged as having been viewed
		return $num_logged;
	}
	
		
	/**
	 * Determine if a date[time] falls within a window of time by using a base date[time] +- offset
	 */
	private static function dateInRange($dateToCheck, $dateBase, $dayOffset, $day_offset_plusminus)
	{
		// Convert day_offset to seconds for comparison
		$dayOffsetSeconds = $dayOffset*86400;
		// Check if in range, which is dependent upon offset_plusminus value
		if ($day_offset_plusminus == '+-') {
			$diff = abs(strtotime($dateToCheck) - strtotime($dateBase));
			return ($diff < $dayOffsetSeconds);
		} elseif ($day_offset_plusminus == '+') {
			$diff = strtotime($dateToCheck) - strtotime($dateBase);
			return ($diff < $dayOffsetSeconds && $diff >= 0);
		} elseif ($day_offset_plusminus == '-') {
			$diff = strtotime($dateBase) - strtotime($dateToCheck);
			return ($diff < $dayOffsetSeconds && $diff >= 0);
		} else {
			return false;
		}
	}
	
	
	/**
	 * PARSE AND SAVE DATA SUBMITTED AFTER BEING ADJUDICATED
	 */
	public function saveAdjudicatedData($record, $event_id, $form_data)
	{
		global $table_pk, $table_pk_label, $Proj, $auto_inc_set, $lang;
		
		// Get required file
		require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';
		
		// If using record auto-numbering and record does not exist yet, then update $record with next record name (in case current one was already saved by other user)
		if (isset($form_data['rtws_adjud-record_exists']) && !$form_data['rtws_adjud-record_exists'] && $auto_inc_set) {
			$record = getAutoId();
		}
		unset($form_data['rtws_adjud-record_exists']);
		
		// Parse submitted form data into array of new/changed values. Save with record, event_id, field as 1st, 2nd, and 3rd-level array keys.
		$data_to_save = $md_ids = array();
		foreach ($form_data as $key=>$val) {
			// Explode the key
			list ($nothing, $md_id, $rc_event, $rc_field) = explode("-", $key, 4);
			// Add to rc data array
			$data_to_save[$record][$rc_event][$rc_field] = $val;
			// Add md_id to array so we can set this value as "adjudicated" in the mapping_data table
			if ($md_id != '') $md_ids[] = $md_id;
		}
		
		// Set string of javascript to execute if on a data entry form to update DOM
		$adjudJsUpdate = '';		
		
		// Get the REDCap field name and event_id of the external identifier		
		list ($rc_field_external, $rc_event_external) = $this->getMappedIdRedcapFieldEvent();
		
		// Keep count of number of items saved
		$itemsSaved = 0;
		
		// Loop through each event of data and save it using saveRecord() function
		foreach ($data_to_save as $this_record=>$event_data) {
			foreach ($event_data as $this_event_id=>$field_data) {
				// Simulate new Post submission (as if submitted via data entry form)
				$_POST = array_merge(array($table_pk=>$this_record), $field_data);
				// Need event_id in query string for saving properly
				$_GET['event_id'] = $this_event_id; 
				// Delete randomization field values and log it
				saveRecord($this_record);
				// Add javascript to string for eval'ing (only on data entry form)
				if ($event_id != '') {
					if ($this_event_id == $event_id) {
						## CURRENT EVENT
						// Loop through all fields in event
						foreach ($field_data as $this_field=>$this_value) {
							// Increment number of items saved
							if ($this_field != $table_pk && $this_field != $rc_field_external) {
								$itemsSaved++;
							}
							// Get field type
							$thisFieldType = $Proj->metadata[$this_field]['element_type'];
							// RADIO (including YesNo and TrueFalse
							if ($thisFieldType == 'radio' || $thisFieldType == 'yesno' || $thisFieldType == 'truefalse') {
								$adjudJsUpdate .= "$('form#form input[name=\"$this_field\"]').val('".cleanHtml($this_value)."');";
								// Make sure to select the ___radio input as well
								$adjudJsUpdate .= "$('form#form input[name=\"{$this_field}___radio\"][value=\"".cleanHtml2($this_value)."\"]').prop('checked', true);";
							}
							// NOTES BOX
							elseif ($thisFieldType == 'textarea') {
								$adjudJsUpdate .= "$('form#form textarea[name=\"$this_field\"]').val('".cleanHtml($this_value)."');";
							}
							// DROPDOWN
							elseif ($thisFieldType == 'select') {
								$adjudJsUpdate .= "$('form#form select[name=\"$this_field\"]').val('".cleanHtml($this_value)."');";
							}						
							// ALL OTHER FIELD TYPES
							else {
								$adjudJsUpdate .= "$('form#form input[name=\"$this_field\"]').val('".cleanHtml($this_value)."');";
							}
						}
					} else {
						## DIFFERENT EVENT DATA (might be hidden in other form on page)
						// Get unique event name for this event
						$this_unique_event_name = $Proj->getUniqueEventNames($this_event_id);
						// Only execute this chunk if this form exists
						$adjudJsUpdate .= " if ($('form[name=\"form___{$this_unique_event_name}\"]').length) { ";
						// Loop through all fields in event
						foreach ($field_data as $this_field=>$this_value) {
							$adjudJsUpdate .= "$('form[name=\"form___{$this_unique_event_name}\"] input[name=\"$this_field\"]').val('".cleanHtml($this_value)."');";
						}
						// End the IF statement
						$adjudJsUpdate .= " } ";
					}
				}
			}
		}
		
		// Set all adjudicated items as "adjudicated" in mapping_data table (include all values for a given item/field that was adjudicated)
		$sql = "";
		if (!empty($md_ids)) {
			$sql = "update redcap_ddp_records_data a, redcap_ddp_mapping b, 
					redcap_ddp_mapping c, redcap_ddp_records_data d, redcap_ddp_records e 
					set d.adjudicated = 1 where a.map_id = b.map_id and b.field_name = c.field_name 
					and d.map_id = c.map_id and d.mr_id = e.mr_id and e.mr_id = a.mr_id 
					and a.md_id in (" . prep_implode($md_ids) . ")";
			$q = db_query($sql);
		}
		
		// If on a data entry form, generate JS needed to update fields on the currently open page.
		// Put all the JS inside a hidden div to be eval'd.
		$adjudJsUpdate =   "$(function(){ 
								$adjudJsUpdate 
								if (page == 'DataEntry/index.php' || page == 'Mobile/data_entry.php') {
									$('#form input[name=\"$table_pk\"], #form input[name=\"__old_id__\"]').val('".cleanHtml($record)."');
									$('#form input[name=\"hidden_edit_flag\"]').val('1');
									var numItemsBefore = $('#RTWS_sourceDataCheck_msgBox .badge:first').text()*1;
									var numItemsAfter = (numItemsBefore - $itemsSaved);
									$('#RTWS_sourceDataCheck_msgBox .badge:first').html(numItemsAfter);
									calculate(); doBranching();
									autoCheckNewItemsFromSource('".cleanHtml($record)."', false);
								} else if (page == 'DataEntry/record_status_dashboard.php') {
									$('#rtws_new_items-".cleanHtml($record)."').removeClass('data').removeClass('statusdashred').addClass('darkgreen').html(recordProgressIcon);
									triggerRTWSmappedField('".cleanHtml($record)."',false,false);
								}
						    });";
		// Render div containing javascript
		print RCView::div(array('id'=>'adjud_js_set_form_values', 'class'=>'hidden'), $adjudJsUpdate);
		
		// Return confirmation message in pop-up
		?>
		<div class="darkgreen" style="margin:10px 0;">
			<table cellspacing=10 width=100%>
				<tr>
					<td style="padding:0 30px 0 40px;">
						<img src="<?php echo APP_PATH_IMAGES ?>check_big.png">
					</td>
					<td style="font-size:14px;font-family:verdana;line-height:22px;padding-right:30px;">
						<?php 
						print "<b>{$lang['ws_157']}</b><br>{$lang['ws_158']} $table_pk_label \"<b>$record</b>\"!" 
						?>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
	
	
	/**
	 * CALL "METADATA" WEB SERVICE TO OBTAIN LIST OF EXTERNAL SOURCE FIELDS
	 * Return metadata as array with unique field name as array keys and long name as values.
	 * If not JSON encoded, then return FALSE.
	 */
	private function getExternalSourceFields()
	{
		// Get global var
		global $realtime_webservice_url_metadata, $lang;
		// Call the URL as POST request
		$params = array('user'=>USERID, 'project_id'=>$this->project_id, 'redcap_url'=>APP_PATH_WEBROOT_FULL);
		$metadata_json = http_post($realtime_webservice_url_metadata, $params, 30);
		// Decode json into array
		$metadata_array = json_decode($metadata_json, true);
		// Display an error if the web service can't be reached or if the response is not JSON encoded
		if (!$metadata_json || !is_array($metadata_array)) {
			$msg = $lang['ws_159']."<br>";
			if ($metadata_json !== false && !is_array($metadata_array)) {
				$msg .= "<br>".$lang['ws_160'];
			} elseif ($metadata_json === false) {
				$msg .= "<br>".$lang['ws_161'];
			}
			$msg = RCView::div(array('class'=>'red', 'style'=>'margin-top:20px;'), $msg);
			if (SUPER_USER) {
				$msg .= RCView::div(array('style'=>'margin-top:50px;font-size:14px;font-weight:bold;'),
							$lang['global_01'] . $lang['colon']
						) .
						RCView::div(array('style'=>'max-width:800px;border:1px solid #ccc;padding:5px;'), $metadata_json);
			}
			exit($msg);
		}
		// Loop through array of source fields to ensure that one is the identifier
		$num_id_fields = 0;
		foreach ($metadata_array as $key=>&$attr) {
			if (isset($attr['identifier']) && $attr['identifier'] == '1') {
				$num_id_fields++;
			}
		}
		if ($num_id_fields != 1) {
			$msg = $lang['ws_159']."<br>";
			if ($num_id_fields == 0) {
				// No id field
				$msg .= "<br>".$lang['ws_162'];
			} elseif ($num_id_fields > 1) {
				// More than one id field
				$msg .= "<br>".$lang['ws_163'];
			}
			$msg .= " ".$lang['ws_164']." (e.g., [{\"field\":\"mrn\",\"identifier\":\"1\",...}])".$lang['period']." ".$lang['ws_165'];
			exit( RCView::div(array('class'=>'red'), $msg));
		}
		
		// Keep an array with the cat-subcat-fieldname concatenated together for sorting purposes later
		$field_sorter = array();
		
		// Make sure we have all the correct elements for each field
		$metadata_array2 = array();
		$id_field_key = null;
		foreach ($metadata_array as $key=>&$attr) 
		{
			$attr2 = array(	'field'=>$attr['field'],  
							'temporal'=>($attr['temporal'] == '1' ? 1 : 0),
							'label'=>$attr['label'], 
							'description'=>$attr['description'], 
							'category'=>$attr['category'], 
							'subcategory'=>$attr['subcategory']
						  );
			if (isset($attr['identifier']) && $attr['identifier'] == '1') {
				$attr2['identifier'] = 1;
				// Always set the source id field's cat and subcat to blank so that it's viewed separate from the other fields
				$attr2['category'] = $attr2['subcategory'] = '';
				// Set key value for $id_field_key
				$id_field_key = $key;
			} else {
				// Add cat-subcat-fieldname concatenated together (don't do this for ID field)
				$field_sorter[] = $attr['category'].'---'.$attr['subcategory'].'---'.$attr['field'];
			}
			// Add to new array
			$metadata_array2[$key] = $attr2;
		}
		$metadata_array = $metadata_array2;
		unset($metadata_array2);
		
		## Put ID field first and then order all cats, subcats, and fields alphabetically
		// Remove ID field for now since we'll add it back at the end
		$id_field_array = $metadata_array[$id_field_key];
		unset($metadata_array[$id_field_key]);
		
		// Sort all fields by cat, subcat, field name
		array_multisort($field_sorter, SORT_REGULAR, $metadata_array);
		
		// Finally, add ID field back to array at beginning
		$metadata_array = array_merge(array($id_field_array), $metadata_array);
		
		// Return array of fields (or false if not JSON encoded)
		return (is_array($metadata_array) ? $metadata_array : false);
	}
	

	/**
	 * GET LIST OF FIELDS ALREADY MAPPED TO EXTERNAL SOURCE FIELDS
	 * Return array of fields with external source field as 1st level key, REDCap event_id as 2nd level key, 
	 * REDCap field name as 3rd level key,and sub-array of attributes (temporal_field, is_record_identifier).
	 */
	private function getMappedFields()
	{	
		global $Proj;
		// Make sure Project Attribute class has instantiated the $Proj object
		if (!isset($Proj) || empty($Proj)) {
			$Proj = new Project($this->project_id);
		}
		
		// If class variable is null, then create mapped field array
		if ($this->field_mappings === null) {
			// Put fields in array
			$this->field_mappings = array();
			// Query table
			$sql = "select * from redcap_ddp_mapping where project_id = ".$this->project_id." 
					order by is_record_identifier desc, external_source_field_name, event_id, field_name, temporal_field";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) 
			{
				// If event_id is orphaned, then skip it
				if (!isset($Proj->eventInfo[$row['event_id']])) continue;
				// If field is orphaned, then skip it
				if (!isset($Proj->metadata[$row['field_name']])) continue;					
				// Initialize sub-array, if not initialized
				if (!isset($this->field_mappings[$row['external_source_field_name']])) {
					$this->field_mappings[$row['external_source_field_name']] = array();
				}
				// Add to array
				$this->field_mappings[$row['external_source_field_name']][$row['event_id']][$row['field_name']] = array(
					'map_id' => $row['map_id'], 
					'is_record_identifier' => $row['is_record_identifier'], 
					'temporal_field' => $row['temporal_field'], 
					'preselect' => $row['preselect']
				);
			}
		}
		// Return the array of field mappings
		return $this->field_mappings;
	}

	
	/**
	 * RETURN ARRAY OF MAPPED REDCAP FIELDS IF INSTRUMENT HAS ANY REDCAP FIELDS MAPPED TO SOURCE FIELDS FOR THIS FORM/EVENT
	 * Note: This also includes all temporal date/time fields used for mapped fields.
	 */
	public function formEventMappedFields($form_name, $event_id)
	{
		global $Proj;
		// Put fields in array
		$fields = array();
		// Query table
		$sql = "select field_name, temporal_field from redcap_ddp_mapping where project_id = ".$this->project_id." 
				and event_id = $event_id and field_name in (".prep_implode(array_keys($Proj->forms[$form_name]['fields'])).")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) 
		{
			// Add field to array, if on this form
			if (isset($Proj->forms[$form_name]['fields'][$row['field_name']])) {
				$fields[] = $row['field_name'];
			}
			// Add temporal field to array, if not null and on this form
			if ($row['temporal_field'] != '' && isset($Proj->forms[$form_name]['fields'][$row['temporal_field']])) {
				$fields[] = $row['temporal_field'];
			}
		}
		// Make all unique
		$fields = array_unique($fields);
		// Return array
		return $fields;
	}

	
	/**
	 * OUTPUT JAVASCRIPT FOR TRIGGERING ADJUDICATION POPUP ON DATA ENTRY FORM
	 */
	public function renderJsAdjudicationPopup($record, $event_id=null, $form_name=null)
	{
		global $lang, $Proj, $table_pk_label, $realtime_webservice_offset_days, $realtime_webservice_offset_plusminus, $hidden_edit;
		// Do not display RTWS functionality in mobile view of data entry form
		if (PAGE == 'Mobile/data_entry.php') return;
		// Make sure RTWS is enabled and user can adjudicate
		if (!($this->isEnabledInSystem() && $this->isEnabledInProject() && $this->userHasAdjudicationRights())) return;
		// Get the REDCap field name mapped to the external source identifier field
		list ($rc_identifier_field, $rc_identifier_event) = $this->getMappedIdRedcapFieldEvent();
		// Get array of mapped REDCap fields for this form/event
		$formEvent_mappedFields = $this->formEventMappedFields($form_name, $event_id);
		// If this page is the data entry form AND does NOT have fields mapped to source fields for the event given, then return
		// if ($form_name != null && empty($formEvent_mappedFields)) return;
		// Div for dialog
		print 	RCView::div(array('id'=>'rtws_adjud_popup', 'class'=>'simpleDialog', 'title'=>$lang['ws_27']." ".$this->getSourceSystemName()),
					// Instructions
					RCView::div(array('style'=>'margin:0 0 15px;'),
						$lang['ws_166']
					) .
					// Blue context msg bar
					RCView::div(array('class'=>'blue', 'style'=>'font-size:15px;max-width:100%;'),
						RCView::div(array('style'=>'float:left;padding:5px 0 0 5px;'),
							$lang['ws_02'] . 
							// Record record name w/ label
							" " . RCView::b($table_pk_label) . " " .
							" \"" . RCView::span(array('id'=>'rtws_adjud_popup_recordname', 'style'=>'font-weight:bold;'), $record) . "\" " .
							// Source ID field label and value
							// RCView::span(array('id'=>'rtws_adjud_popup_idval', 'style'=>'font-weight:bold;color:#800000;'),
								// " (" . $Proj->metadata[$rc_identifier_field]['element_label'] . 
								// " \"" . RCView::span(array('id'=>'rtws_adjud_popup_idval'), '') . "\") "
							// ) . 
							// Day offset setting
							RCView::span(array('class'=>'rtws_pullingdates_label'),
								$lang['ws_190'] . RCView::SP .
								RCView::span(array('id'=>'rtws_adjud_popup_day_offset_plusminus_span', 'style'=>'font-weight:bold;margin-right:2px;'), 
									($realtime_webservice_offset_plusminus == '+-' ? "&plusmn;" : $realtime_webservice_offset_plusminus)
								) . 
								RCView::span(array('id'=>'rtws_adjud_popup_day_offset_span', 'style'=>'font-weight:bold;'), 
									$realtime_webservice_offset_days
								) .
								RCView::b(RCView::SP . $lang['scheduling_25'])
							)
						) .
						RCView::div(array('style'=>'float:right;padding-right:5px;'),						
							// Button to fetch data again
							RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:13px;font-family:arial;', 
								'onclick'=>"if (confirmRefetchSourceData()) triggerRTWSmappedField( $('#rtws_adjud_popup_recordname').text(), true, null, null, true);"), 
								RCView::img(array('src'=>'databases_arrow.png', 'style'=>'vertical-align:middle;')) .
								RCView::span(array('style'=>'vertical-align:middle;'), "Refresh data from" . " " . $this->getSourceSystemName())
							) .
							RCView::span(array('class'=>'rtws_pullingdates_label'),
								$lang['ws_190'] . RCView::SP .
								// Dropdown of day offset plus/minus
								"<select id='rtws_adjud_popup_day_offset_plusminus'>
									<option value='+-' ".($realtime_webservice_offset_plusminus == '+-' ? 'selected' : '').">&plusmn;</option>
									<option value='+' ".($realtime_webservice_offset_plusminus == '+' ? 'selected' : '').">+</option>
									<option value='-' ".($realtime_webservice_offset_plusminus == '-' ? 'selected' : '').">-</option>
								</select>" .
								// Text box for day offset value
								RCView::text(array('id'=>'rtws_adjud_popup_day_offset', 'value'=>$realtime_webservice_offset_days, 
									'onblur'=>"redcap_validate(this,'".self::DAY_OFFSET_MIN."','".self::DAY_OFFSET_MAX."','hard','float')", 'class'=>'x-form-text x-form-field', 
									'style'=>'width:25px;height:16px;')) .
								$lang['ws_189']
							)
						) .
						RCView::div(array('class'=>'rtws_pullingdates_label', 'style'=>'clear:both;text-align:right;margin:0 5px 0 0;font-size:11px;'),
							$lang['ws_192']." ".self::DAY_OFFSET_MIN." ".$lang['ws_191']." ".self::DAY_OFFSET_MAX." ".$lang['scheduling_25']
						)
					) .
					// Div for displaying progress
					RCView::div(array('id'=>'rtws_adjud_popup_progress', 'style'=>'padding:30px;font-size:14px;text-align:center;'),
						RCView::img(array('src'=>'progress_circle.gif', 'class'=>'imgfix')) .
						$lang['ws_168']
					) .
					// Div where payload will go
					RCView::div(array('id'=>'rtws_adjud_popup_content', 'style'=>'margin-bottom:10px;'), '')
				);
		// Get array of all date/datetime fields mapped with temporal fields
		$temporalDateFieldsEvents = $this->getTemporalDateFieldsEvents();
		// Obtain an array of the preview fields and set flag if preview fields have been defined
		$preview_fields = $this->getPreviewFields();
		$hasPreviewFields = (!empty($preview_fields));
		// Find all mapped fields displayed on this form/event and set onchange/onclick trigger for autoCheckNewItemsFromSource()
		$jsTrigger = '';		
		foreach ($formEvent_mappedFields as $this_field) {
			// Get field type
			$thisFieldType = $Proj->metadata[$this_field]['element_type'];
			// Add db icon for field
			$jsTrigger .=  "addDDPicon('$this_field');\n";
			// RADIO
			if ($thisFieldType == 'radio') {
				$jsTrigger .= "$('form#form input[name=\"{$this_field}___radio\"]').click(function(){ autoCheckNewItemsFromSource(rtws_record_name, false); });\n";
			}
			// TEXTAREA
			elseif ($thisFieldType == 'textarea') {
				$jsTrigger .= "$('form#form textarea[name=\"{$this_field}\"]').change(function(){ autoCheckNewItemsFromSource(rtws_record_name, false); });\n";
			}
			// ALL OTHER FIELD TYPES
			else {
				// If THIS IS THE IDENTIFIER FIELD, then prompt user to confirm the preview fields before saving the value
				if ($event_id != null && $this_field == $rc_identifier_field) {
					$previewFieldProgressDiv = 	RCView::div(array('style'=>'font-weight:bold;color:#333;margin:10px 0 100px;font-size:13px;'),
													RCView::img(array('class'=>'imgfix', 'src'=>'progress_circle.gif')) .
													"Fetching preview data for \"<span id='rtws_idfield_new_record_preview_field_progress_id_value'></span>\"..."
												);
					$jsTrigger .=  "$('form#form input[name=\"$rc_identifier_field\"]').change(function(){
										$(this).val( trim($(this).val()) );
										if ($(this).val().length == 0) return;
										// Set dialog progress div content
										$('#rtws_idfield_new_record_preview_fields').html('".cleanHtml($previewFieldProgressDiv)."');
										$('#rtws_idfield_new_record_preview_field_progress_id_value').html( $(this).val() );
										// Open dialog
										$('#rtws_idfield_new_record_warning').dialog({ bgiframe: true, modal: true, width: 500, zIndex: 3999,
											close: function(){ $('form#form input[name=\"$rc_identifier_field\"]').focus(); } });
										".(!$hasPreviewFields 
											? 	"$('#rtws_preview_save_btn').button('enable');"
											: 	"// Ajax request for preview field data
												$('#rtws_preview_save_btn').button('disable');
												var ddp_preview_ajax = $.post(app_path_webroot+'DynamicDataPull/preview.php?pid='+pid,{ source_id_value: $(this).val() }, function(data){
													$('#rtws_idfield_new_record_preview_fields').html(data);
													$('#rtws_preview_save_btn').button('enable');
												});
												// If the validation error popup is displayed because invalid data type on ID field, then close the Preview popup
												setTimeout(function(){
													if ($('#redcapValidationErrorPopup').is(':visible')) {
														$('#rtws_idfield_new_record_warning').dialog('close');
														ddp_preview_ajax.abort();
													}
												},100);"
										)."
									});\n";
				} else {
					// NORMAL TEXT FIELDS
					// If this field is a temporal date field OR is the identifier field, then set it to ALWAYS force a data fetch 
					// (since it's value requires more data to be queried from the source system)
					$forceDataFetch = ($event_id != null && ($this_field == $rc_identifier_field 
										|| isset($temporalDateFieldsEvents[$event_id][$this_field]))) ? 'true' : 'false';
					$jsTrigger .= "$('form#form input[name=\"$this_field\"]').change(function(){ autoCheckNewItemsFromSource(rtws_record_name, $forceDataFetch); });\n";
				}
			}
		}
		## HIDDEN DIALOG TO SAVE FORM IF RECORD HAS NOT BEEN SAVED YET
		// If record has not been saved yet, then give user message to first save the record
		if ($event_id != null) 
		{
			print 	RCView::div(array('id'=>'rtws_idfield_new_record_warning', 'class'=>'simpleDialog', 'style'=>'background-color:#EFF6E8;',
						'title'=>($hasPreviewFields ? $lang['ws_169']." ".$this->getSourceSystemName() : $lang['ws_172'])),
						// Preview instructions
						RCView::div(array('style'=>($hasPreviewFields ? '' : 'display:none;')),
							RCView::b($lang['ws_170']) . " " . $lang['ws_171']
						) .
						// Instructions when there are NO preview fields
						RCView::div(array('style'=>(!$hasPreviewFields ? '' : 'display:none;').'margin-bottom:25px;'),
							$lang['ws_193']
						) .
						// Div for loading preview field data
						RCView::div(array('id'=>'rtws_idfield_new_record_preview_fields', 'style'=>($hasPreviewFields ? '' : 'display:none;').'margin:20px 0 15px;'),
							''
						) .
						// Save/cancel buttons
						RCView::div(array('style'=>'margin-top:15px;text-align:right;'),
							RCView::button(array('id'=>'rtws_preview_save_btn', 'class'=>"jqbutton", 'style'=>'padding: 0.4em 0.8em !important;', 'onclick'=>"
								appendHiddenInputToForm('scroll-top', $(window).scrollTop());
								appendHiddenInputToForm('open-ddp', '1');
								appendHiddenInputToForm('save-and-continue','1');
								$(this).hide();
								$('#rtws_preview_cancel_btn').hide();
								$('#rtws_preview_save_progress_text').show();
								dataEntrySubmit(this);"), 
								RCView::img(array('style'=>'vertical-align:middle;', 'src'=>'databases_arrow.png')) .
								RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;color:#222;'),
									$lang['ws_172']
								)
							) .
							RCView::span(array('id'=>'rtws_preview_save_progress_text', 'style'=>'display:none;font-size:13px;font-weight:bold;margin-right:70px;line-height:18px;'),
								RCView::img(array('style'=>'vertical-align:middle;', 'src'=>'progress_circle.gif')) .
								RCView::span(array('style'=>'vertical-align:middle;'),
									$lang['ws_173']
								)
							) .
							RCView::button(array('id'=>'rtws_preview_cancel_btn', 'class'=>"jqbutton", 'style'=>'padding: 0.4em 0.8em !important;margin-left:3px;', 'onclick'=>"
								$('#rtws_idfield_new_record_warning').dialog('close');"), 
								$lang['global_53']
							)
						)
					);
		}
		// Get count of new items to display at top of page when it loads
		if ($hidden_edit && $event_id != null) 
		{
			## GET NUMBER OF ITEMS TO ADJUDICATE and the html to display inside the dialog
			$itemsToAdjudicate = $this->getNewItemCount($record);
			// If itemsToAdjudicate is null (due to never having been fetch OR if cron just updated it), then determine new count
			if ($itemsToAdjudicate === null) {
				// Get current data saved for this form
				$form_data = Records::getData('array', $record, array_keys($Proj->forms[$form_name]['fields']), $event_id);
				$form_data = $form_data[$record][$event_id];
				// Get number of items to adjudicate and the html to display inside the dialog
				list ($itemsToAdjudicate, $newItemsTableHtml) 
					= $this->fetchAndOutputData($record, $event_id, $form_data, $realtime_webservice_offset_days, $realtime_webservice_offset_plusminus, 
												false, true, false, false);
			}
			// Determine which message to display depending on if we have new items to adjudicate
			if ($itemsToAdjudicate == 0) {
				// No new items
				$newItemsText = RCView::img(array('src'=>'information_frame.png', 'class'=>'imgfix')) . 
								RCView::span(array('style'=>'color:#000066;'),
									$lang['ws_174']
								) .
								RCView::SP . "(" . 
								RCView::a(array('href'=>'javascript:;', 'style'=>'margin:0 1px;font-size:11px;', 'onclick'=>"openAdjudicationDialog('".cleanHtml($record)."');return false;"),
									$lang['global_84']
								) . ")";
			} else {
				// There are 1 or more new items
				$newItemsText = RCView::div(array('id'=>'RTWS_sourceDataCheck_msgBox', 'class'=>'red', 'style'=>'color:#C00000;text-align:center;font-weight:bold;'),
									RCView::span(array('class'=>'badge', 'style'=>'font-size:12px;'), $itemsToAdjudicate) .
									RCView::span(array('class'=>'imgfix1'),
										$lang['ws_175']
									) .
									RCView::button(array('class'=>'jqbuttonmed', 'style'=>'margin-left:10px;', 'onclick'=>"openAdjudicationDialog('".cleanHtml($record)."');return false;"),
										$lang['global_84']
									)
								);
			}
		}
		// Javascript
		?>
		<script type='text/javascript'>
		// Set record name
		var rtws_record_name = getParameterByName('id');
		// Language variables
		var langFetchData = '<?php print cleanHtml($lang['ws_03']) ?>';
		// Display dialog to explain "exclude"
		function rtwsExcludeExplain() {
			simpleDialog('<?php print cleanHtml($lang['ws_25']) ?>', '<?php print cleanHtml($lang['ws_24']) ?>');
		}
		$(function() {
			// Perform auto-check of new items from source system and display count at top of data entry form
			if (page == 'DataEntry/index.php' || page == 'Mobile/data_entry.php') {
				// Auto run ajax request in background (unless data entry form is locked)
				if (!$('#lock_record_msg').length) {
					if (record_exists) {
						// Set context msg "new items" count at top of page
						setRTWSContextMsgPlaceholder('<?php echo cleanHtml($newItemsText) ?>');
						$('#RTWS_sourceDataCheck_msgBox button').button();
					}
					// Set triggers on mapped fields to run on onchange
					<?php print $jsTrigger ?>
				}
			}
		});
		</script>
		<?php
		// Call javascript file
		callJSfile('DynamicDataPullAdjudicate.js');
	}

	
	/**
	 * RETURNS ARRAY OF ALL DATE/DATETIME FIELDS MAPPED WITH TEMPORAL FIELDS
	 * Array will contain event_id as first-level key and date field name as 2nd-level key
	 */
	private function getTemporalDateFieldsEvents()
	{
		$temporalDateFieldsEvents = array();
		$sql = "select distinct event_id, temporal_field from redcap_ddp_mapping 
				where project_id = " . $this->project_id . " and temporal_field is not null";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$temporalDateFieldsEvents[$row['event_id']][$row['temporal_field']] = true;
		}
		return $temporalDateFieldsEvents;
	}

	
	/**
	 * DETERMINE IF RTWS IS ENABLED AT THE SYSTEM-LEVEL
	 */
	public function isEnabledInSystem()
	{
		// Get global vars
		global $realtime_webservice_global_enabled;
		// If both vars have a value, then it IS enabled
		return $realtime_webservice_global_enabled;
	}

	
	/**
	 * DETERMINE IF RTWS IS ENABLED IN THE CURRENT PROJECT
	 */
	public function isEnabledInProject()
	{
		// Get global vars
		global $realtime_webservice_enabled;
		// Return boolean
		return (isset($realtime_webservice_enabled) && $realtime_webservice_enabled);
	}

	
	/**
	 * DETERMINE IF USER HAS RTWS MAPPING PRIVILEGES
	 */
	public function userHasMappingRights()
	{
		// Get global vars
		global $user_rights;
		// Return boolean
		return ($user_rights['realtime_webservice_mapping'] == '1');
	}

	
	/**
	 * DETERMINE IF USER HAS RTWS ADJUDICATION PRIVILEGES
	 */
	public function userHasAdjudicationRights($checkUserAccessWebService=false)
	{
		// Get global vars
		global $user_rights;
		// Check project-level user rights first
		if ($user_rights['realtime_webservice_adjudicate'] != '1') return false;
		// Return boolean (check user access web service, if applicable)
		if ($checkUserAccessWebService) {
			return $this->userHasAdjudicationRightsWebService();
		} else {
			return true;
		}
	}
	
	
	/**
	 * CALL "USER ACCESS" WEB SERVICE TO GET "1" (user can adjudicate data from source system) or "0" (user cannot adjudicate data)
	 * Return TRUE if web service returns "1", else return FALSE.
	 * The web service will be called only once per user per project per session and will store a value in the session for checking afterward.
	 * The session variable will be project-specific so that it will be call whenever first accessing the project, which will allow
	 * admins to possibly build project-specific checks (e.g. IRB number) when first accessing a DDP-enabled project.
	 */
	public function userHasAdjudicationRightsWebService()
	{
		// Get global var
		global $realtime_webservice_url_user_access;
		
		// If user access web service URL is not defined, then always return TRUE
		if (trim($realtime_webservice_url_user_access) == '') return true;
		
		// Set session variable name
		$session_var_name = 'ddp_user_access_'.$this->project_id;
		
		// If user has session variable set to 0 or 1, then we've already checked the user web service during this session
		if (isset($_SESSION[$session_var_name]))
		{
			// Return true if has session value of "1"
			return ($_SESSION[$session_var_name] == '1');
		} 
		else
		{
			// CALL WEB SERVICE		
			// Set parameters for request
			$params = array('user'=>USERID, 'project_id'=>$this->project_id, 'redcap_url'=>APP_PATH_WEBROOT_FULL);
			// Call the URL as POST request
			$response = http_post($realtime_webservice_url_user_access, $params, 30);
			// Set session value and return true if web service returned "1"
			if ($response !== false && trim($response."") === '1') {
				$_SESSION[$session_var_name] = '1';
				return true;
			} else {
				// Return false on failure and set session value to "0"
				$_SESSION[$session_var_name] = '0';
				return false;
			}
		}
	}

	
	/**
	 * DETERMINE IF MAPPING HAS BEEN SET UP FOR PROJECT
	 * Check redcap_ddp_mapping table to see if any fields have been mapped.
	 */
	public function isMappingSetUp()
	{	
		// Return boolean
		return ($this->getMappedIdFieldExternal() !== false);
	}

	
	/**
	 * GET THE EXTERNAL FIELD NAME FOR RECORD IDENTIFIER FIELD MAPPED TO THE REDCAP FIELD IN THE PROJECT
	 */
	public function getMappedIdFieldExternal()
	{
		// Query table
		$sql = "select external_source_field_name from redcap_ddp_mapping 
				where project_id = ".$this->project_id." and is_record_identifier = 1 limit 1";
		$q = db_query($sql);		
		// Return boolean
		return (db_num_rows($q) > 0 ? db_result($q, 0) : false);
	}

	
	/**
	 * GET THE REDCAP FIELD NAME AND EVENT_ID FOR RECORD IDENTIFIER FIELD MAPPED TO THE EXTERNAL FIELD IN THE PROJECT
	 */
	public function getMappedIdRedcapFieldEvent()
	{
		// Query table
		$sql = "select field_name, event_id from redcap_ddp_mapping 
				where project_id = ".$this->project_id." and is_record_identifier = 1 limit 1";
		$q = db_query($sql);		
		// Return boolean
		if (db_num_rows($q) > 0) {
			return array(db_result($q, 0, 'field_name'), db_result($q, 0, 'event_id'));
		} else {
			return false;
		}
	}
	

	/**
	 * EXCLUDE A SOURCE VALUE for a given record during the Adjudication process
	 */
	public function excludeValue($md_id, $exclude) 
	{
		global $lang;
		// Make sure we have all the values we need, else return error
		if (($exclude != '0' && $exclude != '1') || !is_numeric($md_id)) return '0';	
		// Update table
		$sql = "update redcap_ddp_records_data set exclude = $exclude where md_id = $md_id";
		$q = db_query($sql);
		// Log the event
		if ($q) {
			$log_description = ($exclude) ? "Exclude source value (DDP)" : "Remove exclusion for source value (DDP)";
			log_event($sql, "redcap_ddp_records_data", "MANAGE", $md_id, "md_id = $md_id", $log_description);
		}
		// Return label text if true, else '0'
		return ($q ? ($exclude 
						? RCView::img(array('src'=>'plus2.png', 'class'=>'imgfix2 opacity50', 'title'=>$lang['dataqueries_88'])) 
						: RCView::img(array('src'=>'cross.png', 'class'=>'imgfix2 opacity50', 'title'=>$lang['dataqueries_87'])) 
					  ) 
				   : '0');
	}
	

	/**
	 * GET EXCLUDED VALUES
	 * Return array of md_ids with values already excluded for the given record
	 */
	private function getExcludedValues($record, $map_ids=array()) 
	{
		// Put all values in array with md_id as array key
		$excluded_values_by_md_id = array();
		if (!empty($map_ids)) {
			$sql = "select d.md_id, d.source_value from redcap_ddp_records r, redcap_ddp_records_data d
					where d.mr_id = r.mr_id and d.map_id in (" . prep_implode(array_unique($map_ids)) . ") 
					and r.record = '" . prep($record) . "' and r.project_id = " . $this->project_id . "
					and exclude = 1";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$excluded_values_by_md_id[$row['md_id']] = decrypt($row['source_value'], self::DDP_ENCRYPTION_KEY);
			}
		}
		return $excluded_values_by_md_id;
	}
	

	/**
	 * GET MD_ID'S OF VALUES THAT HAVE *NOT* BEEN ADJUDICATED YET
	 * Return array of md_ids as keys for the given record
	 */
	private function getNonAdjudicatedValues($record, $map_ids=array()) 
	{
		// Put all values in array with md_id as array key
		$non_adjudicated_values_by_md_id = array();
		if (!empty($map_ids)) {
			$sql = "select d.md_id from redcap_ddp_records r, redcap_ddp_records_data d
					where d.mr_id = r.mr_id and d.map_id in (" . prep_implode(array_unique($map_ids)) . ") 
					and r.record = '" . prep($record) . "' and r.project_id = " . $this->project_id . "
					and adjudicated = 0";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$non_adjudicated_values_by_md_id[$row['md_id']] = true;
			}
		}
		return $non_adjudicated_values_by_md_id;
	}
	

	/**
	 * GET TIME OF LAST DATA FETCH FOR A RECORD
	 * Return timestamp or NULL, if record data has not been cached yet.
	 */
	private function getLastFetchTime($record, $returnInAgoFormat=false) 
	{
		global $lang;
		$sql = "select updated_at from redcap_ddp_records 
				where project_id = " . $this->project_id . " and record = '" . prep($record) . "' limit 1";
		$q = db_query($sql);
		if (db_num_rows($q)) {
			$ts = db_result($q, 0);
			// If we're returning the time in "X hours ago" format, then convert it, else return as is
			if ($returnInAgoFormat) {
				// If timestamp is NOW, then return "just now" text
				if ($ts == NOW) return $lang['ws_176'];
				// First convert to minutes
				$ts = (strtotime(NOW) - strtotime($ts))/60;
				// Return if less than 60 minutes
				if ($ts < 60) return ($ts < 1 ? $lang['ws_177'] : (floor($ts) . " " . (floor($ts) == 1 ? $lang['ws_178'] : $lang['ws_179'])));
				// Convert to hours
				$ts = $ts/60;
				// Return if less than 24 hours
				if ($ts < 24) return floor($ts) . " " . (floor($ts) == 1 ? $lang['ws_180'] : $lang['ws_181']);
				// Convert to days and return
				$ts = $ts/24;
				return floor($ts) . " " . (floor($ts) == 1 ? $lang['ws_182'] : $lang['ws_183']);
			}
			// Return value
			return $ts;
		} else {
			return null;
		}
	}
	

	/**
	 * GET MR_ID FOR A RECORD
	 * Return mr_id primary key for a record in this project. If not exists yet in table, then insert it.
	 */
	private function getMrId($record) 
	{
		$sql = "select mr_id from redcap_ddp_records 
				where project_id = " . $this->project_id . " and record = '" . prep($record) . "' limit 1";
		$q = db_query($sql);
		if (db_num_rows($q)) {
			return db_result($q, 0);
		} else {
			$sql = "insert into redcap_ddp_records (project_id, record)
					values (" . $this->project_id . ", '" . prep($record) . "')";
			return (db_query($sql) ? db_insert_id() : false);
		}
	}
	

	/**
	 * GET ANY "PREVIEW" SOURCE FIELDS THAT HAVE BEEN MAPPED
	 * Return array of source fields that have been designated as "preview" fields
	 */
	private function getPreviewFields() 
	{
		$sql = "select field1, field2, field3, field4, field5 
				from redcap_ddp_preview_fields where project_id = " . $this->project_id;
		$q = db_query($sql);
		if (db_num_rows($q)) {
			// Remove all blank instances
			$preview_fields = db_fetch_assoc($q);
			foreach ($preview_fields as $key=>$field) {
				if ($field == '') unset($preview_fields[$key]);
			}
			return array_values($preview_fields);
		} else {
			return array();
		}
	}
	

	/**
	 * SEED MR_ID'S FOR ALL RECORDS IN ALL PROJECTS UTILIZING RTWS SERVICE (excluding archived/inactive projects)
	 * Returns count of number of records seeded, else FALSE if query failed. 
	 * It adds new row for each record into redcap_ddp_records table.
	 */
	public static function seedMrIdsAllProjects() 
	{
		global $realtime_webservice_stop_fetch_inactivity_days;
		// Validate value of $realtime_webservice_stop_fetch_inactivity_days
		if (!is_numeric($realtime_webservice_stop_fetch_inactivity_days) || $realtime_webservice_stop_fetch_inactivity_days < 1) {
			$realtime_webservice_stop_fetch_inactivity_days = 7;
		}
		// Get timestamp of time limit of inactivity for a project
		$x_days_ago = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-$realtime_webservice_stop_fetch_inactivity_days,date("Y")));
		// Seed all mr_id's for all projects using DDP
		$sql = "select distinct d.project_id, d.record from redcap_projects p, redcap_metadata m, redcap_data d 
				left join redcap_ddp_records r on d.project_id = r.project_id and d.record = r.record 
				where p.status <= 1 and p.realtime_webservice_enabled = 1 and p.date_deleted is null
				and ((p.last_logged_event is not null and p.last_logged_event > '$x_days_ago') 
					or (select count(1) from redcap_ddp_records r2 where r2.project_id = p.project_id and r2.future_date_count > 0) > 0)
				and m.project_id = p.project_id and d.project_id = p.project_id 
				and m.field_name = d.field_name and m.field_order = 1 and r.record is null";
		$q = db_query($sql);		
		while ($row = db_fetch_assoc($q)) {
			// Add to ddp_records table
			$sql = "insert into redcap_ddp_records (project_id, record) 
					values ({$row['project_id']}, '" . prep($row['record']) . "')";
			db_query($sql);
		}
		// Return number of records seeded
		return ($q ? db_num_rows($q) : false);
	}
	

	/**
	 * SET "QUEUED" FETCH STATUS FOR ALL RECORDS IN ALL PROJECTS UTILIZING RTWS SERVICE (excluding archived/inactive projects)
	 * Returns count of number of records that get queued, else FALSE if query failed.
	 */
	public static function setQueuedFetchStatusAllProjects() 
	{
		global $realtime_webservice_data_fetch_interval, $realtime_webservice_stop_fetch_inactivity_days;
		
		// Make sure we have a value for $realtime_webservice_data_fetch_interval
		if (!(is_numeric($realtime_webservice_data_fetch_interval) && $realtime_webservice_data_fetch_interval >= 1)) {
			$realtime_webservice_data_fetch_interval = 24;
		}
		
		// Set fetch interval as specific timestamp in the past
		$fetchIntervalTime = date("Y-m-d H:i:s", (strtotime(NOW)-(3600*$realtime_webservice_data_fetch_interval)));
		// Validate value of $realtime_webservice_stop_fetch_inactivity_days
		if (!is_numeric($realtime_webservice_stop_fetch_inactivity_days) || $realtime_webservice_stop_fetch_inactivity_days < 1) {
			$realtime_webservice_stop_fetch_inactivity_days = 7;
		}
		// Get timestamp of time limit of inactivity for a project
		$x_days_ago = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-$realtime_webservice_stop_fetch_inactivity_days,date("Y")));
				
		// Get list of records that are ready to be queued (ignore any records where the value is blank for the source ID field).
		// Make sure that we still check records with datetime reference fields with values in the future, even if the project or record has
		// not been modified in the past X days of inactivity.
		$project_mrid_list = array();
		$sql = "select r.project_id, r.record, r.mr_id from 
				redcap_projects p, redcap_ddp_mapping m, redcap_data d, redcap_ddp_records r 
				where p.status <= 1 and p.realtime_webservice_enabled = 1 and p.date_deleted is null
				and ((p.last_logged_event is not null and p.last_logged_event > '$x_days_ago') 
					or (select count(1) from redcap_ddp_records r2 where r2.project_id = p.project_id and r2.future_date_count > 0) > 0)
				and p.project_id = m.project_id and m.is_record_identifier = 1 and d.project_id = m.project_id 
				and d.event_id = m.event_id and d.field_name = m.field_name and r.project_id = m.project_id 
				and r.record = d.record and d.value != '' and r.fetch_status is null 
				and (r.updated_at is null or r.updated_at <= '$fetchIntervalTime')";
		$q = db_query($sql);		
		while ($row = db_fetch_assoc($q)) {
			// Add to array
			$project_mrid_list[$row['project_id']][$row['record']] = $row['mr_id'];
		}
		
		// Keep count of number of records queued
		$numRecordsQueued = 0;
		// Loop through records and return only those modified in past X days (based upon $realtime_webservice_stop_fetch_inactivity_days)
		foreach ($project_mrid_list as $this_project_id=>$records_mrids) {
			// If there are more records than max records per batch, then do in several batches
			$records_log_query = array_chunk($records_mrids, self::RECORD_LIMIT_PER_LOG_QUERY, true);
			// Query log_event table for each batch
			foreach ($records_log_query as $records_mrids_this_batch) {
				// Return a list of records in this batch that have dates of service that exist in the future
				$records_mrids_this_batch_future_dates = self::checkRecordsFutureDates($this_project_id, $records_mrids_this_batch);
				// Return a list of records in this batch that have been modified in past X days 
				// (exclude those already found that have future dates - reduces query time)
				$records_mrids_this_batch_NO_future_dates = array_diff_assoc($records_mrids_this_batch, $records_mrids_this_batch_future_dates);
				$records_mrids_this_batch_modified = self::checkRecordsModifiedRecently($this_project_id, $records_mrids_this_batch_NO_future_dates, $x_days_ago);
				// Merge the mr_id arrays to build the ones that we need to queue in this batch
				$mr_ids_to_queue_this_batch = array_unique(array_merge($records_mrids_this_batch_modified, $records_mrids_this_batch_future_dates));
				// Set the fetch status of the records returned to QUEUED
				if (!empty($mr_ids_to_queue_this_batch)) 
				{
					$sql = "update redcap_ddp_records set fetch_status = 'QUEUED' 
							where mr_id in (" . prep_implode($mr_ids_to_queue_this_batch) . ")";
					if (db_query($sql)) {
						// Increment queued record count
						$numRecordsQueued += db_affected_rows();						
					}
				}
			}
		}
		
		// Return number of recrods queued
		return $numRecordsQueued;
	}
	
	
	/**
	 * RETURN A LIST OF RECORDS IN THIS BATCH THAT HAVE DATES OF SERVICE THAT EXIST IN THE FUTURE
	 */
	private static function checkRecordsFutureDates($this_project_id, $records_mrids=array()) 
	{
		// Query the ddp_records table using the record names in $records_mrids
		$records_mrids_this_batch_future_dates = array();
		$sql = "SELECT record from redcap_ddp_records where project_id = $this_project_id 
				and record in (" . prep_implode(array_keys($records_mrids)) . ") 
				and (future_date_count > 0 or updated_at is null)";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Add to array with record as key and mr_id as value
			$records_mrids_this_batch_future_dates[$row['record']] = $records_mrids[$row['record']];
		}
		// Return array of ONLY the records modified in past X days
		return $records_mrids_this_batch_future_dates;
	}
	
	
	/**
	 * QUERY LOG TABLE TO DETERMINE IF A LIST OF RECORDS HAVE BEEN MODIFIED IN PAST X DAYS
	 * Returns an array of with record name as array key and mr_id as its corresponding value for
	 * records that HAVE been modified in the past X days (based upon $realtime_webservice_stop_fetch_inactivity_days).
	 */
	private static function checkRecordsModifiedRecently($this_project_id, $records_mrids=array(), $x_days_ago) 
	{
		// Query the log_event table using the record names in $records_mrids
		$records_mrids_this_batch_modified = array();
		$sql = "select distinct(pk) as record from redcap_log_event 
				force index (event_project) where project_id = $this_project_id 
				and event in ('UPDATE', 'INSERT', 'DELETE', 'DOC_UPLOAD', 'DOC_DELETE', 'LOCK_RECORD', 'ESIGNATURE') 
				and object_type = 'redcap_data' and ts > '$x_days_ago' 
				and pk in (" . prep_implode(array_keys($records_mrids)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Add to array with record as key and mr_id as value
			$records_mrids_this_batch_modified[$row['record']] = $records_mrids[$row['record']];
		}
		// Return array of ONLY the records modified in past X days
		return $records_mrids_this_batch_modified;
	}
	

	/**
	 * FETCH QUEUED RECORDS FROM SOURCE (up to a certain limit per batch)
	 * For an individual project with RTWS service enabled, query the external source system for every record
	 * whose "updated_at" timestamp is greater than the data fetch interval (e.g. 24 hours).
	 */
	public static function fetchQueuedRecordsFromSource() 
	{
		global $redcap_version, $Proj;
		// Get list of projects for which to fetch source data
		$project_mrid_list = self::getProjectsWithQueuedRecords();
		// Set all queued records in this batch with "fetching" fetch status
		$num_records_ready_to_fetch = self::setFetchingStatusForQueuedRecords($project_mrid_list);
		// If nothing to fetch, then stop here
		if ($num_records_ready_to_fetch == 0) return;
		// Collect count of total records fetched from the source system
		$num_records_fetched = 0;
		// Loop through each project with data to fetch, processing each one as a single HTTP Get request
		foreach ($project_mrid_list as $this_project_id=>$these_mrids) 
		{
			// Instantiate Project object for this project and make sure it gets set as global (so that we don't have to recreate the object for EACH RECORD fetched)
			$Proj = new Project($this_project_id);
			// Instantiate DynamicDataPull object for this project
			$DDP = new DynamicDataPull($this_project_id);
			// Get all records=>source ID field values by mr_id
			$sourceIdValues = $DDP->getSourceIdValuesByMrId($these_mrids);
			// Loop through all mr_id's to get its record name and source id field value
			foreach ($sourceIdValues as $mr_id=>$attr) {
				// Ensure that record's fetch_status is still FETCHING (just in case user fetch via front-end while the cron was waiting)
				$sql = "select 1 from redcap_ddp_records where mr_id = $mr_id and fetch_status is not null";
				$q = db_query($sql);
				if (!db_num_rows($q)) continue;
				// Fetch and cache any data from the source system for this record
				$DDP->fetchData($attr['record'], null, $attr['source_id'], $Proj->project['realtime_webservice_offset_days'], $Proj->project['realtime_webservice_offset_plusminus'],
								array(), true, '1', false, $this_project_id);
				// Set fetch_status to NULL. Also set new item count to null since we're not running renderAdjudicationTable(), 
				// which saves the proper "new item count" that includes existing data values and excluded values, etc.
				// Setting new item count to null will force it to be set when the user loads a data entry form or views the adjudication popup.
				$sql = "update redcap_ddp_records set fetch_status = null, item_count = null where mr_id = $mr_id";
				db_query($sql);
			}
			// For any mr_id's that are somehow left over and not fetched from the source system, set fetch_status as null
			$other_mrids = array_diff($these_mrids, array_keys($sourceIdValues));
			if (!empty($other_mrids)) {
				$sql = "update redcap_ddp_records set fetch_status = null where mr_id in (" . prep_implode($other_mrids) . ")";
				db_query($sql);
			}
			// Increment the number of records whose data was fetched in the source system
			$num_records_fetched += count($sourceIdValues);
		}		
		// Free up memory (since this is a global variable)
		unset($Proj);
		// Return the total number of records fetched from the source system
		return $num_records_fetched;
	}
	

	/**
	 * GET LIST OF PROJECTS FOR WHICH TO FETCH SOURCE DATA
	 * Returns array of project_id's for the projects with queued records.
	 */
	private static function getProjectsWithQueuedRecords() 
	{
		// Get projects with gueued records
		$project_mrid_list = array();
		$sql = "select project_id, mr_id from redcap_ddp_records 
				where fetch_status = 'QUEUED' order by updated_at limit " . self::FETCH_LIMIT_PER_BATCH;
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Add project_id to array
			$project_mrid_list[$row['project_id']][] = $row['mr_id'];
		}
		return $project_mrid_list;
	}
	

	/**
	 * SET "FETCHING" FETCH STATUS FOR ALL QUEUED RECORDS IN THIS BATCH
	 * 
	 */
	private static function setFetchingStatusForQueuedRecords($project_mrid_list=array()) 
	{
		// If there are no projects with queued records, then return 0
		if (empty($project_mrid_list)) return 0;
		// Convert $project_mrid_list into array of only mr_id's
		$mr_ids = array();
		foreach ($project_mrid_list as $this_project_id=>$these_mrids) {
			foreach ($these_mrids as $this_mr_id) {
				$mr_ids[] = $this_mr_id;
			}
		}
		// Update the fetch status of records that are ready to be queued
		$sql = "update redcap_ddp_records set fetch_status = 'FETCHING'
				where fetch_status = 'QUEUED' and mr_id in (" . prep_implode($mr_ids) . ")";
		return (db_query($sql)) ? db_affected_rows() : false;
	}
	

	/**
	 * GET RECORD NAMES AND THE VALUE OF THEIR CORRESPONDING SOURCE SYSTEM ID FIELD USING MR_ID
	 * Input an array of mr_id's and it returns an array of source system id field values with REDCap record names with mr_id as array key.
	 */
	public function getSourceIdValuesByMrId($mr_ids=array()) 
	{
		// Query to get source ID field values and corresponding record name
		$sourceIdValues = array();
		$sql = "select r.mr_id, d.record, d.value from 
				redcap_ddp_mapping m, redcap_data d, redcap_ddp_records r 
				where m.project_id = " . $this->project_id . " and m.is_record_identifier = 1 and d.project_id = m.project_id 
				and d.event_id = m.event_id and d.field_name = m.field_name and r.project_id = m.project_id 
				and r.record = d.record and r.mr_id in (" . prep_implode($mr_ids) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Remove any blank values
			if ($row['value'] == '') continue;
			// Add value to array with record name as key
			$sourceIdValues[$row['mr_id']] = array('record'=>$row['record'], 'source_id'=>$row['value']);
		}
		return $sourceIdValues;
	}
	

	/**
	 * OUTPUT THE EXPLANATION TEXT FOR DDP FOR DISPLAYING IN AN INFORMATION POPUP
	 */
	public static function getDdpExplanationText()
	{
		global $lang, $realtime_webservice_custom_text, $realtime_webservice_user_rights_super_users_only;
		// First, set the video link's html
		$realtime_webservice_custom_text_dialog = 
			RCView::div(array('style'=>'text-align:right;'), 
				RCView::img(array('src'=>'video_small.png', 'class'=>'imgfix')) .
				RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;', 'onclick'=>"popupvid('ddp01.swf')"), $lang['ws_41'])
			);
		// Set text (if custom text is provided, then display it instead of stock text)
		if (trim($realtime_webservice_custom_text) != '') {
			$realtime_webservice_custom_text_dialog .= 	RCView::div(array('id'=>'ddp_info_custom_text'), 
															nl2br(filter_tags($realtime_webservice_custom_text)));
		} else {
			$realtime_webservice_custom_text_dialog .= 	RCView::div(array('id'=>'ddp_info_custom_text'), 
															$lang['ws_50']);
		}
		// Add stock text/instructions about DDP
		$realtime_webservice_custom_text_dialog .= 
			RCView::div(array('style'=>'font-weight:bold;font-size:110%;'), $lang['ws_38']) .
			$lang['ws_37'] . " " . $lang['ws_62'] . RCView::br() . RCView::br() .
			RCView::div(array('style'=>'font-weight:bold;font-size:110%;'), $lang['ws_39']) .
			$lang['ws_40'] . RCView::br() . RCView::br() .
			RCView::div(array('style'=>'font-weight:bold;font-size:110%;'), $lang['ws_42']) .
			($realtime_webservice_user_rights_super_users_only ? $lang['ws_56'] : $lang['ws_57'] . " " . RCView::b($lang['ws_58'])) . " " .			
			$lang['ws_43'] . RCView::br() . RCView::br() .
			RCView::div(array('style'=>'font-weight:bold;font-size:110%;'), $lang['ws_44']) .
			$lang['ws_45'] . RCView::br() . RCView::br() .
			RCView::div(array('style'=>'font-weight:bold;font-size:110%;'), $lang['ws_46']) .
			$lang['ws_47'] . RCView::br() . RCView::br() .
			RCView::div(array('style'=>'font-weight:bold;font-size:110%;'), $lang['ws_48']) .
			$lang['ws_49'] . " " . $lang['ws_59'] . " " . RCView::b($lang['ws_60']) . " " . $lang['ws_61'];
		// Return HTML
		return $realtime_webservice_custom_text_dialog;
	}
	

	/**
	 * OBTAIN DATA FOR THE PREVIEW FIELDS AND DISPLAY IT
	 */
	public function displayPreviewData($record_identifier_external)
	{
		global $lang, $realtime_webservice_url_data;
		
		if ($record_identifier_external == '') return 'ERROR!';
		
		// Obtain an array of the preview fields
		$preview_fields = $this->getPreviewFields();
		
		// Loop through fields to put in necessary array format for sending to data web service
		$field_info = array();
		foreach ($preview_fields as $this_preview_field) {
			$field_info[] = array('field'=>$this_preview_field);
		}
		
		## CALL DATA WEB SERVICE
		// Set params to send in POST request (all JSON-encoded in single parameter 'body')
		// $params = array('user'=>USERID, 'project_id'=>$this->project_id, 'redcap_url'=>APP_PATH_WEBROOT_FULL, 
						// 'body' => json_encode(array('id'=>$record_identifier_external, 'fields'=>$field_info))
						// );
		$params = array('user'=>(defined('USERID') ? USERID : ''), 'project_id'=>$this->project_id, 'redcap_url'=>APP_PATH_WEBROOT_FULL, 
						'id'=>$record_identifier_external, 'fields'=>$field_info);
		// Call the URL as POST request
		// $response_json = http_post($realtime_webservice_url_data, $params, 30);	
		$response_json = http_post($realtime_webservice_url_data, $params, 30, 'application/json');
		// Decode json into array
		$response_data_array = json_decode($response_json, true);
		
		// Display an error if the web service can't be reached or if the response is not JSON encoded
		if (!$response_json || !is_array($response_data_array)) {
			$error_msg = $lang['ws_137']."<br><br>";
			if ($response_json !== false && !is_array($response_data_array)) {
				$error_msg .=  $lang['ws_138']."<div style='color:#C00000;margin-top:10px;'>$response_json</div>";
			} elseif ($response_json === false) {
				$error_msg .= $lang['ws_139']." $realtime_webservice_url_data.";
			}
			exit($error_msg);
		}
		
		// Convert response array of data from web service into other other
		$preview_fields_data = array();
		foreach ($preview_fields as $this_preview_field) {
			// Seed with blank value first
			$preview_fields_data[$this_preview_field] = RCView::span(array('style'=>'font-weight:normal;'), 
															"<i>{$lang['ws_184']}</i>"
														);
			// Find this preview field in the response array returned from web service
			foreach ($response_data_array as $attr) {
				if ($attr['field'] == $this_preview_field) {
					$preview_fields_data[$this_preview_field] = $attr['value'];
				}
			}
		}
		
		## Build HTML table for displaying the preview field data
		// Row for source ID value
		$rows = RCView::tr(array(),
					RCView::td(array('valign'=>'top', 'style'=>'text-align:right;font-size:14px;'),
						$this->getMappedIdFieldExternal() . $lang['colon']
					) .
					RCView::td(array('valign'=>'top', 'style'=>'font-weight:bold;font-size:14px;color:#C00000;'),
						$record_identifier_external .
						// If no data returned, then display warning icon
						(!empty($response_data_array) ? '' :
							RCView::img(array('src'=>'exclamation_red.png', 'style'=>'margin-left:7px;'))
						)
					)
				);
		// Display row for each preview field
		foreach ($preview_fields_data as $this_preview_field=>$this_preview_field_value) {
			$rows .= 	RCView::tr(array(),
							RCView::td(array('valign'=>'top', 'style'=>'text-align:right;font-size:14px;'),
								$this_preview_field . $lang['colon']
							) .
							RCView::td(array('valign'=>'top', 'style'=>'font-weight:bold;font-size:14px;color:#C00000;'),
								$this_preview_field_value
							)
						);
		}
		// Render table
		$html = RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin-bottom:5px;'),
					$lang['ws_185'] . " \"$record_identifier_external\"{$lang['questionmark']}"
				) . 
				RCView::table(array('cellspacing'=>'5', 'style'=>'table-layout:fixed;margin-left:20px;'), $rows);
		
		// EXTENDED LOGGING: Log all values that were displayed in the popup to user
		if (!empty($response_data_array)) {
			// Get ui_id of user
			$userInfo = User::getUserInfo(USERID);
			// Now log all these md_ids in mapping_logging table
			$sql = "insert into redcap_ddp_log_view (time_viewed, user_id, project_id, source_id)
					values ('".NOW."', ".checkNull($userInfo['ui_id']).", ".$this->project_id.", '".prep($record_identifier_external)."')";
			if (db_query($sql)) {
				// Get ml_id from insert
				$ml_id = db_insert_id();
				// Now add each data point to mapping_logging_data table
				foreach ($response_data_array as $attr) {
					$sql = "insert into redcap_ddp_log_view_data (ml_id, source_field, source_timestamp)
							values ($ml_id, '".prep($attr['field'])."', ".checkNull($attr['timestamp']).")";
					db_query($sql);
				}
			}
		}
		
		// Return html
		return $html;
	}
	

	/**
	 * PURGE THE DATA CACHE OF SOURCE SYSTEM DATA (ONLY IF PROJECT IS ARCHIVED/INACTIVE)
	 * 
	 */
	public function purgeDataCache() 
	{
		global $status;
		// If project is not archive/inactive status, then return false
		if ($status <= 1) return false;
		// Remove all records in mapping_records table
		$sql = "delete from redcap_ddp_records where project_id = " . $this->project_id;
		return (db_query($sql));
	}
	

	/**
	 * RETURN CUSTOM NAME OF EXTERNAL SOURCE SYSTEM, ELSE RETURN STOCK TEXT IF NOT DEFINED
	 * 
	 */
	public function getSourceSystemName()
	{
		global $realtime_webservice_source_system_custom_name, $lang;
		// If custom name not defined
		if (trim($realtime_webservice_source_system_custom_name) == '') {
			return $lang['ws_52'];
		} else {
			return $realtime_webservice_source_system_custom_name;
		}
	}
	
}