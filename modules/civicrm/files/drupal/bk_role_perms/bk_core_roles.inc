<?php
  //Roles
  define("BK_ACCESS_CIVICRM", 'bk access civicrm');
  define("BK_CIVI_CASE_EDIT_ALL", 'bk civi case edit all');
  define("BK_CIVI_CASE_EDIT_MY", 'bk civi case edit my');
  define("BK_CIVI_CASE_VIEW_ALL", 'bk civi case view all');
  define("BK_CIVI_CASE_VIEW_MY", 'bk civi case view my');
  define("BK_CIVI_BK_ADMIN", 'bk brisskit administrator');

  //Components
  define("BK_CIVI_CASE", 'CiviCase');

function get_all_roles () {
	$bk_roles=array (
		BK_ACCESS_CIVICRM,
		BK_CIVI_BK_ADMIN,
		BK_CIVI_CASE_EDIT_ALL,
		BK_CIVI_CASE_EDIT_MY,
		BK_CIVI_CASE_VIEW_ALL,
		BK_CIVI_CASE_VIEW_MY
	);
	return $bk_roles;
}
?>