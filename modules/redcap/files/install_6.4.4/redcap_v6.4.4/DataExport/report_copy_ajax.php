<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Validate id
if (!isset($_POST['report_id'])) exit('0');
$report_id = $_POST['report_id'];
$report = DataExport::getReports($report_id);
if (empty($report)) exit('0');

// Copy the report and return the new report_id
$new_report_id = DataExport::copyReport($report_id);
if ($new_report_id === false) exit('0');

// Return HTML of updated report list and report_id
print json_encode(array('new_report_id'=>$new_report_id, 'html'=>DataExport::renderReportList()));