<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Display list of usernames who would have access
$content = DataExport::displayReportAccessUsernames($_POST);
// Output JSON
print json_encode(array('content'=>$content, 'title'=>$lang['report_builder_108']));