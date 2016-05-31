<?php

if (!defined('BK_EXTENSIONS_DIR')) {
  define ("BK_EXTENSIONS_DIR",
    dirname( __FILE__ )
    . DIRECTORY_SEPARATOR . 'files'
    . DIRECTORY_SEPARATOR . 'civicrm'
    . DIRECTORY_SEPARATOR . 'custom_ext');
}

if (!defined('BK_HOOKS_DIR')) {
  define ("BK_HOOKS_DIR",
    BK_EXTENSIONS_DIR
    . DIRECTORY_SEPARATOR . 'uk.ac.le.brisskit'
    . DIRECTORY_SEPARATOR . 'our_hooks');
}

/* Default extension directory so that our extension is found without setting extensions directory */
global $civicrm_setting;
$civicrm_setting['Directory Preferences']['extensionsDir'] = '/var/local/brisskit/drupal/site/civicrm/sites/default/files/civicrm/custom_ext/';

/*
  $include_path = BK_EXTENSIONS_DIR . PATH_SEPARATOR . BK_HOOKS_DIR . PATH_SEPARATOR . get_include_path( );
  set_include_path( $include_path );
*/
