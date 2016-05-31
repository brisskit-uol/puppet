<?php

define('DRUPAL_ROOT', '/var/local/brisskit/drupal/site/civicrm');
require_once(DRUPAL_ROOT . '/includes/bootstrap.inc');
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);


$path = "/var/local/brisskit/drupal/site/civicrm/sites/all/modules/civicrm";



#civicrm defined constant to config dir -> deals with symlinks
define("CIVICRM_CONFDIR","/var/local/brisskit/drupal/site/civicrm/sites/default");

set_include_path("$path/drupal" . PATH_SEPARATOR . $path . PATH_SEPARATOR . $path."/packages".PATH_SEPARATOR."/var/local/brisskit/drupal/site/civicrm/sites/default");

require_once 'CRM/Core/ClassLoader.php';
CRM_Core_ClassLoader::singleton()->register();

//crm_initialize();

$error = include_once 'CRM/Core/Config.php';

require_once "civicrm.module";
require_once 'civicrm.config.php';
#include_once 'civicrm.settings.php';

require_once 'CRM/Utils/Array.php';

/*
function conf_path() {
	global $def_path;
	return $def_path;
}
*/
/*
function t() {
	
}
*/
/*
function arg() {
	return null;
}
*/
/*
function drupal_set_message($message,$type='message') {
	print "$type:$message\n";
}
*/
/*
function drupal_add_css() {
	
}
*/
/*
function drupal_get_path($type,$name) {
	
}
*/
