<?php 
    /* BK_EXTENSIONS_DIR and BK_HOOKS_DIR are defined in civicrm.settings.php */

    require_once ( BK_EXTENSIONS_DIR 
      . DIRECTORY_SEPARATOR . 'uk.ac.le.brisskit'
      . DIRECTORY_SEPARATOR . 'CRM' 
      . DIRECTORY_SEPARATOR . 'Brisskit'
      . DIRECTORY_SEPARATOR . 'BK_Utils.php');

    require_once ( BK_HOOKS_DIR . DIRECTORY_SEPARATOR . 'BK_Hooks.php');
    BK_Hooks::set_mysql_view_component_name($this);
