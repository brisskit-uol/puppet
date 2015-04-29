<?php

/**
 * RENDER PROJECT LIST
 * Display all REDCap projects in table format
 */
class RenderProjectList
{

    /*
    * PUBLIC PROPERTIES
    */
     public $pageTitle;
	 
	 
    /*
    * PUBLIC FUNCTIONS
    */

    // @return void
    // @access public
    function renderprojects($section = "") {
	
		global  $display_nonauth_projects, $auth_meth_global, $dts_enabled_global, $google_translate_enabled, 
				$isIE, $lang, $rc_connection, $realtime_webservice_global_enabled;
			
		require APP_PATH_DOCROOT . "Classes/DTS.php";
		
		// Place all project info into array
		$proj = array();
		// Are we viewing the list from the Control Center?
		$isControlCenter = (strpos(PAGE_FULL, "/ControlCenter/") !== false);
		
		//First get projects list from User Info and User Rights tables
		if ($isControlCenter && isset($_GET['userid']) && $_GET['userid'] != "") {
			// Show just one user's (not current user, since we are super user in Control Center)
			$sql = "select p.project_id, p.project_name, p.app_title, p.status, p.draft_mode, p.surveys_enabled, p.date_deleted, p.repeatforms 
					from redcap_user_rights u, redcap_projects p 
					where u.project_id = p.project_id and u.username = '".prep($_GET['userid'])."' order by p.project_id";
		} elseif ($isControlCenter && isset($_GET['view_all'])) {
			// Show all projects
			$sql = "select p.project_id, p.project_name, p.app_title, p.status, p.draft_mode, p.surveys_enabled, p.date_deleted, p.repeatforms
					from redcap_projects p order by p.project_id";
		} elseif ($isControlCenter && (!isset($_GET['userid']) || $_GET['userid'] == "")) {
			// Show no projects (default)
			$sql = "select 1 from redcap_projects limit 0";
		} else {
			// Show current user's (ignore "deleted" projects)
			$sql = "select p.project_id, p.project_name, p.app_title, p.status, p.draft_mode, p.surveys_enabled, p.date_deleted, p.repeatforms
					from redcap_user_rights u, redcap_projects p 
					where u.project_id = p.project_id and u.username = '" . prep(USERID) . "' 
					and p.date_deleted is null order by p.project_id";
		}
		$q = db_query($sql);
		while ($row = db_fetch_array($q)) 
		{		
			$proj[$row['project_name']]['project_id'] = $row['project_id'];
			$proj[$row['project_name']]['longitudinal'] = $row['repeatforms'];
			$proj[$row['project_name']]['status'] = $row['status'];
			$proj[$row['project_name']]['date_deleted'] = $row['date_deleted'];
			$proj[$row['project_name']]['draft_mode'] = $row['draft_mode'];
			$proj[$row['project_name']]['surveys_enabled'] = $row['surveys_enabled'];
			$proj[$row['project_name']]['app_title'] = strip_tags(str_replace(array("<br>","<br/>","<br />"), array(" "," "," "), html_entity_decode($row['app_title'], ENT_QUOTES)));				
			if (isset($_GET['no_counts'])) {
				$proj[$row['project_name']]['count'] = "";			
				$proj[$row['project_name']]['field_num'] = "";
			} else {
				$proj[$row['project_name']]['count'] = 0;			
				$proj[$row['project_name']]['field_num'] = 0;
			}
		}
		
		## DTS: If enabled globally, build list of projects to check to see if adjudication is needed
		if ($dts_enabled_global)
		{
			// Set default
			$dts_rights = array();
			// Get projects with DTS enabled
			if (!$isControlCenter) {
				// Where normal user has DTS rights
				$sql = "select p.project_id from redcap_user_rights u, redcap_projects p where u.username = '" . prep(USERID) . "' and 
						p.project_id = u.project_id and p.dts_enabled = 1 and 
						p.project_name in (" . prep_implode(array_keys($proj)) . ")";
				// Don't query using DTS user rights on project if a super user because they might not have those rights in
				// the user_rights table, although once they access the project, they are automatically given those rights
				// because super users get maximum rights for everything once they're inside a project.
				if (!SUPER_USER) {
					$sql .= " and u.dts = 1";
				}
			} else {
				// Super user in Control Center
				$sql = "select project_id from redcap_projects where dts_enabled = 1 
						and project_name in (" . prep_implode(array_keys($proj)) . ")";
			}
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				$dts_rights[$row['project_id']] = true;
			}
		}
		
		/* 
		## DDP: If enabled globally, build list of projects to check to see if adjudication is needed
		// Set default
		$ddp_new_items = array();
		if ($realtime_webservice_global_enabled)
		{
			// Get projects with DDP enabled
			if (!$isControlCenter) {
				// Where normal user has DDP rights
				$sql = "select distinct p.project_id from redcap_user_rights u, redcap_projects p where u.username = '" . prep(USERID) . "' and 
						p.project_id = u.project_id and p.realtime_webservice_enabled = 1 and 
						p.project_name in (" . prep_implode(array_keys($proj)) . ")";
				// Don't query using DDP user rights on project if a super user because they might not have those rights in
				// the user_rights table, although once they access the project, they are automatically given those rights
				// because super users get maximum rights for everything once they're inside a project.
				if (!SUPER_USER) {
					$sql .= " and u.realtime_webservice_adjudicate = 1";
				}
			} else {
				// Super user in Control Center
				$sql = "select project_id from redcap_projects where realtime_webservice_enabled = 1 
						and project_name in (" . prep_implode(array_keys($proj)) . ")";
			}
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				$ddp_new_items[$row['project_id']] = 0;
			}
			// If any displayed projects have DDP enabled and user has "adjudication" rights, then go see if any new items to adjudicate
			if (!empty($ddp_new_items))
			{
				$sql = "select r.project_id from redcap_ddp_records r, redcap_ddp_records_data d 
						where r.mr_id = d.mr_id and d.adjudicated = 0 and d.exclude = 0 
						and r.project_id in (" . prep_implode(array_keys($ddp_new_items)) . ") group by r.project_id";
				$q = db_query($sql);
				while ($row = db_fetch_assoc($q))
				{
					$ddp_new_items[$row['project_id']] = 1;
				}
			}
		}
		*/
		
		// Project Templates: Build array of all templates so we can put a star by their title for super users only
		$templates = (SUPER_USER) ? ProjectTemplates::getTemplateList() : array();
		
		//Loop through projects and render each table row
		$row_data = array();
		$all_proj_ids = array();
		foreach ($proj as $app_name => $attr) 
		{
			
			//If project is archived, do not show it (unless in Control Center)
			if (!$isControlCenter && $attr['status'] == '3' && !isset($_GET['show_archived'])) continue;
			
			// Store project_id in array to use in AJAX call on pageload
			$all_proj_ids[] = $attr['project_id'];
			
			//Determine if we need to show if a production project's drafted changes are in review
			$in_review = '';
			if ($attr['draft_mode'] == '2') {
				$in_review = "<br><span class='aGridsub' onclick=\"window.location.href='" . APP_PATH_WEBROOT . "Design/project_modifications.php?pid={$attr['project_id']}';return false;\">({$lang['control_center_104']})</span>"; 
			}
			
			//Determine if we need to show Super User functionality (edit db, delete db)
			$settings_link = '';
			if ($isControlCenter) {
				$settings_link = '<div class="aGridsub">
									<a style="color:#000;font-family:Tahoma;font-size:10px;" href="'.APP_PATH_WEBROOT.'ControlCenter/edit_project.php?project='.$attr['project_id'].'">'.$lang['control_center_106'].'</a> |
									<a style="font-family:Tahoma;font-size:10px;" href="javascript:;" onclick="revHist('.$attr['project_id'].')">'.$lang['app_18'].'</a> | 
									'.($attr['date_deleted'] == "" 
										? '<a style="color:#800000;font-family:Tahoma;font-size:10px;" href="javascript:;" onclick="delete_project('.$attr['project_id'].',this)">'.$lang['control_center_105'].'</a>'
										: '<a style="color:green;font-family:Tahoma;font-size:10px;" href="javascript:;" onclick="undelete_project('.$attr['project_id'].',this)">'.$lang['control_center_375'].'</a> <br>
										   <img src="'.APP_PATH_IMAGES.'bullet_delete.png"> <span style="color:red;">'.$lang['control_center_380'].' '.DateTimeRC::format_ts_from_ymd(date('Y-m-d H:i:s', strtotime($attr['date_deleted'])+3600*24*PROJECT_DELETE_DAY_LAG)).'</span>
										   <br><span style="color:#666;margin:0 3px 0 12px;">'.$lang['global_46'].'</span> <a style="text-decoration:underline;color:red;font-family:Tahoma;font-size:10px;" href="javascript:;" onclick="delete_project('.$attr['project_id'].',this,1)">'.$lang['control_center_381'].'</a>'
									).'
								</div>'; 
			}
			
			// DTS Adjudication notification (only on myProjects page)
			$dtsLink = "";
			// Determine if DTS is enabled globally and also for this user on this project
			if ($dts_enabled_global && isset($dts_rights[$attr['project_id']]))
			{
				// Instantiate new DTS object
				$dts = new DTS();
				// Get count of items that needed adjudication
				$recommendationCount = $dts->getPendingCountByProjectId($attr['project_id']);
				// Render a link if items exist
				if ($recommendationCount > 0) {
					$dtsLink = '<div class="aGridsub" style="padding:0 5px;text-align:right;">
									<a title="'.$lang['home_28'].'" href="'.APP_PATH_WEBROOT . 'DTS/index.php?pid='.$attr['project_id'].'" style="text-decoration:underline;color:green; font-family:Tahoma; font-size:10px;"><img src="'.APP_PATH_IMAGES.'tick_small_circle.png"> '.$lang['home_28'].'</a>
								</div>';
				} else {
					$dtsLink = '<div class="aGridsub" style="color:#aaa;padding:0 5px;text-align:right;">'.$lang['home_29'].'</div>';
				}
			}
			
			// DDP Adjudication notification (only on myProjects page)
			$ddpLink = "";
			/* 
			if ($realtime_webservice_global_enabled && isset($ddp_new_items[$attr['project_id']]))
			{
				// If there are some items to adjudicate, then display link to page
				if ($ddp_new_items[$attr['project_id']] > 0) {
					$ddpLink = '<div class="aGridsub" style="padding:0 5px;text-align:right;">
									<a title="'.$lang['home_48'].'" href="'.APP_PATH_WEBROOT . 'DataEntry/record_status_dashboard.php?pid='.$attr['project_id'].'" style="text-decoration:underline;color:green; font-family:Tahoma; font-size:10px;"><img src="'.APP_PATH_IMAGES.'tick_small_circle.png"> '.$lang['home_48'].'</a>
								</div>';
				} else {
					$ddpLink = '<div class="aGridsub" style="color:#aaa;padding:0 5px;text-align:right;">'.$lang['home_29'].'</div>';
				}
			}
			*/
			
			// If project is a template, then display a star next to title (for super users only)
			$templateIcon = (isset($templates[$attr['project_id']])) 
				? ($templates[$attr['project_id']]['enabled'] ? RCView::img(array('src'=>'star_small2.png','style'=>'margin-left:5px;')) : RCView::img(array('src'=>'star_small_empty2.png','style'=>'margin-left:5px;')))
				: '';
			
			// Title as link
			if ($attr['status'] < 1) { // Send to setup page if in development still
				$title = '<div class="projtitle"><a title="'.htmlspecialchars(cleanHtml2($lang['control_center_432']), ENT_QUOTES).'" href="' . APP_PATH_WEBROOT . 'ProjectSetup/index.php?pid=' . $attr['project_id'] . '" class="aGrid">'.$attr['app_title'].$templateIcon.$in_review.$settings_link.$dtsLink.$ddpLink.'</a></div>';
			} else {
				$title = '<div class="projtitle"><a title="'.htmlspecialchars(cleanHtml2($lang['control_center_432']), ENT_QUOTES).'" href="' . APP_PATH_WEBROOT . 'index.php?pid=' . $attr['project_id'] . '" class="aGrid">'.$attr['app_title'].$templateIcon.$in_review.$settings_link.$dtsLink.$ddpLink.'</a></div>';
			}
			
			// Status
			if ($attr['date_deleted'] != "") {
				// If project is "deleted", display cross icon
				$iconstatus = '<img class="imgfix1" src="'.APP_PATH_IMAGES.'cross.png" title="'.cleanHtml2($lang['global_106']).'">';
			} else {
				// If typical project, display icon based upon status value
				switch ($attr['status']) {
					case 0: //Development
						$iconstatus = '<img class="imgfix1" src="'.APP_PATH_IMAGES.'page_white_edit.png" title="'.cleanHtml2($lang['global_29']).'">';
						break;
					case 1: //Production
						$iconstatus = '<img class="imgfix1" src="'.APP_PATH_IMAGES.'accept.png" title="'.cleanHtml2($lang['global_30']).'">';
						break;
					case 2: //Inactive
						$iconstatus = '<img class="imgfix1" src="'.APP_PATH_IMAGES.'delete.png" title="'.cleanHtml2($lang['global_31']).'">';
						break;
					case 3: //Archived
						$iconstatus = '<img class="imgfix1" src="'.APP_PATH_IMAGES.'bin_closed.png" title="'.cleanHtml2($lang['global_31']).'">';
						break;
				}
			}
			// Append $iconstatus with an invisible span containing the status (for ability to sort)
			$iconstatus .= RCView::span(array('class'=>'hidden'), $attr['status']);
			
			// Project type (classic or longitudinal)
			$icontype = ($attr['longitudinal']) ? RCView::img(array('class'=>'imgfix1', 'src'=>'blogs_stack.png', 'title'=>$lang['create_project_51']))
												: RCView::img(array('class'=>'imgfix1', 'src'=>'blog_blue.png', 'title'=>$lang['create_project_49']));
			// Append $iconstatus with an invisible span containing the value (for ability to sort)
			$icontype .= RCView::span(array('class'=>'hidden'), $attr['longitudinal']);
			
			$row_data[] = array($title, 
								"<span class='pid-cntr' id='pid-cntr-{$attr['project_id']}'><span class='pid-cnt'>{$lang['data_entry_64']}</span></span>", 
								"<span class='pid-cntf' id='pid-cntf-{$attr['project_id']}'><span class='pid-cnt'>{$lang['data_entry_64']}</span></span>", 
								"<span class='pid-cnti' id='pid-cnti-{$attr['project_id']}'><span class='pid-cnt'>{$lang['data_entry_64']}</span></span>", 
								$icontype, 
								$iconstatus);
		}
		
		// If user has access to zero projects
		$filter_projects_style = '';
		if (empty($row_data)) {
			$row_data[] = array(($isControlCenter ? $lang['home_37'] : $lang['home_38']),"","","","","");		
			// Hide the "filter projects" if no projects are showing
			$filter_projects_style = 'visibility:hidden;';
		}
		
		// Set table title name
		$tableHeader = $isControlCenter ? $lang['mobile_site_03'] : $lang['home_22'];
		
		// Set "My Projects" column header's project search input
		$searchProjTextboxJsFocus = "if ($(this).val() == '".cleanHtml($lang['control_center_440'])."') { 
									$(this).val(''); $(this).css('color','#000'); 
								  }";
		$searchProjTextboxJsBlur = "$(this).val( trim($(this).val()) );
								  if ($(this).val() == '') { 
									$(this).val('".cleanHtml($lang['control_center_440'])."'); $(this).css('color','#999'); 
								  }";
		$tableTitle = 	RCView::div(array('style'=>''),
							RCView::div(array('style'=>'font-size:13px;float:left;margin:2px 0 0 3px;'), $tableHeader) .
							RCView::div(array('style'=>'float:right;margin:0 10px 0 0;'), 
								RCView::text(array('id'=>'proj_search', 'class'=>'x-form-text x-form-field', 
									'style'=>'width:170px;color:#999;'.$filter_projects_style, 'value'=>$lang['control_center_440'],
									'onfocus'=>$searchProjTextboxJsFocus,'onblur'=>$searchProjTextboxJsBlur))
							) .
							RCView::div(array('class'=>'clear'), '')
						);
		
		// Render table
		$width = 850; // Whole table width
		$width2 = 40; // Records
		$width3 = 40; // Fields
		$width6 = 56; // Instruments
		$width5 = 30; // Type
		$width4 = 36; // Status
		if ($section == "control_center") $width = 660;
		$width1 = $width - $width2 - $width3 - $width4 - $width5 - $width6 - 73; // DB name
		$col_widths_headers[] = array($width1, $lang['home_30']);
		$col_widths_headers[] = array($width2, $lang['home_31'], "center", "int");
		$col_widths_headers[] = array($width3, $lang['home_32'], "center", "int");
		$col_widths_headers[] = array($width6, $lang['global_110'], "center", "int");
		$col_widths_headers[] = array($width5, $lang['home_39'], "center");
		$col_widths_headers[] = array($width4, $lang['home_33'], "center");
		renderGrid("proj_table", $tableTitle, $width, 'auto', $col_widths_headers, $row_data);
		
		?>
		<script type="text/javascript">
		// Set var for all pid's listed on the page
		var visiblePids = '<?php echo implode(",", $all_proj_ids) ?>';
		$(function(){
			// Enable "search project" input
			$('#proj_search').quicksearch('table#table-proj_table tbody tr');
			// Re-activate table search when table is re-sorted
			$('div#proj_table .hDivBox').click(function() {
				$('#proj_search').quicksearch('table#table-proj_table tbody tr');
			});	
			// Get counts for table
			getRecordOrFieldCountsMyProjects('fields', visiblePids);
			setTimeout("getRecordOrFieldCountsMyProjects('records', visiblePids)",100);
		});
		</script>
		<?php
		
		
		//Display any Public projects (using "none" auth) if flag is set in config table
		if ($display_nonauth_projects && $auth_meth_global != "none" && !$isControlCenter) {
			
			// Get all public dbs that the user does not already have access to (to prevent duplication in lists)
			$sql = "select project_id, project_name, app_title from redcap_projects where auth_meth = 'none' 
					and status in (1, 0) and project_id not in 
					(0,".pre_query("select project_id from redcap_user_rights where username = '" . prep(USERID) . "'").") 
					order by trim(app_title)";
			$q = db_query($sql, $rc_connection);
			
			// Only show this section if at least one public project exists
			if (db_num_rows($q) > 0) 
			{
				print  "<p style='margin-top:40px;'>{$lang['home_34']}";
				//Give extra note to super user
				if (SUPER_USER) {
					print  "<i>{$lang['home_35']}</i>";
				}
				print  "</p>";
				
				$pubList = array();
				while ($attr = db_fetch_assoc($q)) {				
					//Title
					$pubList[] = array('<a title="'.htmlspecialchars(cleanHtml2($lang['control_center_432']), ENT_QUOTES).'" href="' . APP_PATH_WEBROOT . 'index.php?pid=' . $attr['project_id'] . '" class="aGrid">'.$attr['app_title'].'</a>');
				}
				
				$col_widths_headers = array(  
										array(840, "<b>{$lang['home_36']}</b>")
									);
				renderGrid("proj_table_pub", "", 850, 'auto', $col_widths_headers, $pubList);
				
			}
				
		}
		
    }
    
}    
