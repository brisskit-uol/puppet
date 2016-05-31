<?php

/* 
 * This file contains functions that are called directly from (patched) core. Ideally these would be implemented as proper civicrm hooks, so there's probably a better way of doing this.
 * TODO - investigate whether there are CiviCRM hooks we can use instead
 *  
*/

require_once ('brisskit_ts.php'); // replaces ts()
require_once (__DIR__ . '/../CRM/Brisskit/BK_Component.php'); // replaces ts()

class BK_Hooks {
  // Note: this gets called when we int the DB so we can't read any tables via DAO etc

  static function set_mysql_view_component_name ($dao) {
    $bk_component_name = BK_Component::get_current_component_name();

    $sql="set @component_name='$bk_component_name'";

    $dao->query($sql);
    if (PEAR::getStaticProperty('DB_DataObject', 'lastError')) {
      throw new Exception("Could not set mysql @component_name in " . __FILE__ . ' ' . __METHOD__ . "\n");
      return FALSE;
    }
  }
}
