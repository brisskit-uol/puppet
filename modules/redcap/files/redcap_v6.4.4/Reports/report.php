<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// This file not used anymore as of 6.0.0. Redirect to new reports page.
redirect(APP_PATH_WEBROOT . "DataExport/index.php?pid=$project_id");