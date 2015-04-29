<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Set title for left-hand menu panel for Reports
$reportsListTitle = $lang['app_06'];
if ($user_rights['reports']) {
	$reportsListTitle = "<table cellspacing='0' width='100%'>
						<tr>
							<td>{$lang['app_06']}</td>
							<td id='menuLnkEditReports' class='opacity50' style='text-align:right;padding-right:10px;'>"
								. RCView::img(array('src'=>'pencil_small2.png','class'=>'imgfix1 '.($isIE ? 'opacity50' : '')))
								. RCView::a(array('href'=>APP_PATH_WEBROOT."DataExport/index.php?pid=$project_id",'style'=>'font-family:arial;font-size:11px;text-decoration:underline;color:#000066;font-weight:normal;'), $lang['bottom_71']) . "
							</td>
						</tr>
					   </table>";
}

// Output html for left-hand menu panel for Reports
$reportsList = DataExport::outputReportPanel();
if ($reportsList != "") {
	print renderPanel($reportsListTitle, $reportsList, 'report_panel');
}