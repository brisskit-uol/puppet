<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

print json_encode(array('content'=>DataQuality::renderDRWinstructions(), 'title'=>$lang['dataqueries_275']));
