<?php
include ('/var/local/brisskit/drupal/site/civicrm/sites/default/civicrm.settings.php');
require_once ('/var/local/brisskit/drupal/site/civicrm/sites/all/modules/civicrm/packages/DB.php');

function get_component_id_by_case_id ($case_id) {
  print "case_id = $case_id\n";
  
  $db = DB::connect(CIVICRM_DSN);
  if (PEAR::isError($db)) {
    die($db->getMessage());
  }
  $sth = $db->prepare('SELECT component_id FROM brisskit_case_mappings where case_id = ?');
  $res =& $db->execute($sth, $case_id);

  if (PEAR::isError($res)) {
      die($res->getMessage());
  }

  if ($row =& $res->fetchRow()) {
    return $row[0];
  }
  else {
    return 0;
  }
  $db->close();
}

function get_component_name_by_id ($component_id) {
  print "component_id = $component_id\n";
  
  $db = DB::connect(CIVICRM_DSN);
  if (PEAR::isError($db)) {
    die($db->getMessage());
  }
  $sth = $db->prepare('SELECT component_name FROM brisskit_components where component_id = ?');
  $res =& $db->execute($sth, $component_id);

  if (PEAR::isError($res)) {
      print_r($res);
      die($res->getMessage());
  }

  if ($row =& $res->fetchRow()) {
    return $row[0];
  }
  else {
    return '';
  }
  $db->close();
}

function get_component_id_by_name ($name) {
  print "case_id = $case_id\n";
  
  $db = DB::connect(CIVICRM_DSN);
  if (PEAR::isError($db)) {
    die($db->getMessage());
  }
  $sth = $db->prepare('SELECT component_id FROM brisskit_components where component_name = ?');
  $res =& $db->execute($sth, $name);

  if (PEAR::isError($res)) {
      die($res->getMessage());
  }

  if ($row =& $res->fetchRow()) {
    return $row[0];
  }
  else {
    return 0;
  }
  $db->close();
}

$comp_id = get_component_id_by_case_id (33);
print "$comp_id \n";

$comp_name = get_component_name_by_id ($comp_id);
print "$comp_name \n";

$comp_id = get_component_id_by_name ('recruitment');
print "$comp_id \n";
exit;
?>
