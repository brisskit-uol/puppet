<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Purge the DDP's data cache. Return 1 if successful, else 0.
print ($DDP->purgeDataCache() ? '1' : '0');