<?php
/*
Use Civi API to check if case component is enabled.
If it isn't processing in this module stops because we cannot set perms 
for a component that isn't enabled.
*/
function civi_case_enabled () {

    //1. Init Civi.
    if ( ! civicrm_initialize( ) ) {
        bk_drupal_audit ("Failed to init Civi for checking Case enabled");
        return FALSE;
    }

	//2.  Use the API to find out all enabled components.
    $result = civicrm_api3('Setting', 'get', array(
      'sequential' => 1,
      'return' => "enable_components",
    ));

	//3. Did that work?
	if ($result['is_error']) {
		return FALSE;
	}

	//Check Case is enabled
	$components = $result['values'][0]['enable_components'];
	bk_drupal_audit (print_r($components, TRUE));
	if (in_array(BK_CIVI_CASE, $components)) {
		return TRUE;
	}
	else {
		return FALSE;
	}
}

function bk_drupal_audit ($message) {
  $file = '/tmp/bkmodule.log';
  $ts  = date('Y-m-d H:i:s');
  file_put_contents($file, $ts.' '.$message."\n", FILE_APPEND);
}
?>