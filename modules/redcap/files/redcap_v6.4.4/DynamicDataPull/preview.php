<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Obtain data for the preview fields and display it
print $DDP->displayPreviewData($_POST['source_id_value']);
