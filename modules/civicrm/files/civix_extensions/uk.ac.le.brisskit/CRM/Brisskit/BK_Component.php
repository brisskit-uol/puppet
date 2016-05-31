<?php

/* 
 *
 * Methods to support 2 versions of CiviCase specific to Brisskit - Study and Recruitment
 *
 */

require_once("BK_Constants.php");

class BK_Component {

  /*
   *
   * globally-accessible value (via the getter) of the component we're dealing with. 
   * Usually this will be determined by the URL but where it is necessary to process both studies and recruitments on the same screen, 
   * this may be set by the application before querying/updating the database.
   *
   * As we want to be able to determine the component as soon as we start accessing the DB via the DAO layer, we do not
   * want to rely on DAO being set up, so any DB accesses we need are done directly.
   *
   */
  private static $current_component_name; 
  private static $call_count; 


  static function is_valid_name ($component_name) {
    if ($component_name == BK_Constants::CIVISTUDY) {
      return TRUE;
    }
    else if ($component_name == BK_Constants::CIVIRECRUITMENT) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }


  static function set_component_name ($component_name) {
    if (!self::is_valid_name($component)) {
       throw new Exception("$component is not a valid component in " . __FILE__ . ' ' . __METHOD__ . "\n");
    }
    else {
      #global $bk_component;
      #$bk_component = $component_name;
      self::set_current_component_name ($component_name);
    }
  }


  /*
   *
   * Returns the component name if previously set
   * If not, set it then from the context (url and query string) and return it
   * If the context gives no hints we'll default to CIVISTUDY
   *
   */
  static function get_component_name () {
    /* 
      If not already set, try to extract it from the URL
      If not able to do this, set it to the default
    */
    self::$call_count++;
    // BK_Utils::audit ("Call count " . self::$call_count);
  

    if (!self::is_valid_name(self::get_current_component_name())) {
      $component_name = self::get_component_name_by_context();
      BK_Utils::audit ("Comp" . $component_name);
      self::set_current_component_name ($component_name);
    }

    
    if (!self::is_valid_name(self::get_current_component_name())) {
      $component_name = BK_Constants::CIVISTUDY;  // The default
      BK_Utils::audit ("Comp2" . $component_name);
      self::set_current_component_name ($component_name);
      return $component_name; 
    }
    else {
      return self::get_current_component_name();
    }
  }


  /*
   *  Simple getter
   */ 
  static function get_current_component_name () {
    return self::$current_component_name;
  }


  /*
   *  Simple setter
   */ 
  static function set_current_component_name ($component_name) {
    self::$current_component_name = $component_name;
  }


  /*
   *  Checks whether component is CIVISTUDY. Will derive from context if necessary
   */
  static function is_study() {
    return (self::get_component_name() == BK_Constants::CIVISTUDY);
  }


  /*
   *  Checks whether component is CIVIRECRUITMENT. Will derive from context if necessary
   */
  static function is_recruitment() {
    return (self::get_component_name() == BK_Constants::CIVIRECRUITMENT);
  }



  /*
   *
   *
   *
   * Functions to return the current subsystem of CiviResearch - CiviStudy or CiviRecruitment
   *
   *
   *
   */


  /* Next 3 functions will use standard civi data if we can (not sure) or we'll need to record our own mapping if not */

  // Return the component (study/recruitment) for the activity
  // TODO
  static function get_component_name_by_activity($activity_id) {
    return BK_Constants::CIVISTUDY;
  }


  /* 
   *
   * Return the component name (study/recruitment) for the case 
   *
   */
  static function get_component_name_by_case_id($case_id)  {
    $component_id = self::get_component_id_by_case_id($case_id);
    $component_name = self::get_component_name_by_id($component_id);
    return $component_name;
  }


  /* 
   *
   * Return the component id (study/recruitment) for the case 
   *
   */
  static function get_component_id_by_case_id ($case_id) {
    BK_Utils::audit($case_id);
    $component_id = BK_Utils::single_value_query('SELECT component_id FROM brisskit_case_mappings where case_id = ?', $case_id);
    if ($component_id) {
      return $component_id;
    }
    else {
      return 0;
    }
  }


  /* 
   *
   * Return the component id (study/recruitment) for the case type
   *
   */
  static function get_component_id_by_case_type_id($case_type_id) {
    BK_Utils::audit("SELECT component_id FROM brisskit_case_type_mappings WHERE case_type_id = ? : " . $case_type_id);

    $component_id = BK_Utils::single_value_query("SELECT component_id FROM brisskit_case_type_mappings WHERE case_type_id = ?", $case_type_id);
    if ($component_id) {
      return $component_id;
    }
    else {
      return 0;
    }
  }


  static function map_case_type_to_component($case_type_id, $component) {
    if (!in_array($component, array( BK_Constants::CIVISTUDY, BK_Constants::CIVIRECRUITMENT))) {
       throw new Exception("$component is not a valid component in " . __FILE__ . ' ' . __METHOD__ . "\n");
    }
  }


  /* 
   *
   * We will use url or __FILE__ depending on whether called via http or cli
   *
   * There are different ways we can determine the context. 
   * 1) The strings 'study' or 'recruitment' in the URI when we've been able to add these ourselves
   * 2) From the case we're dealing with where the case id is present in the request uri
   * 3) From the case we're dealing with where the case id is present in the entryURL parameter (this is used by CiviCRM a bit like referrer, but is not always present).
   *
   */
  static function get_component_name_by_context() {
    BK_Utils::audit(print_r($_REQUEST, TRUE));

    BK_Utils::audit('#########' . $_SERVER['REQUEST_URI']);
    if (preg_match('/.*civicrm\/recruitment.*/', $_SERVER['REQUEST_URI'], $matches)) {
      return BK_Constants::CIVIRECRUITMENT;
    }
    else if (preg_match('/.*civicrm\/study.*/', $_SERVER['REQUEST_URI'], $matches)) {
      return BK_Constants::CIVISTUDY;
    }

    $case_id = self::extract_case_id_from_uri($_SERVER['REQUEST_URI']);
    if (!$case_id) {
      if (isset($_REQUEST['entryURL'])) {
        $case_id = self::extract_case_id_from_uri($_REQUEST['entryURL']);
      }
      if (!$case_id) {
        $case_id = 0;
      }
    }
    BK_Utils::audit("caseid: $case_id");

    $component_id = self::get_component_id_by_case_id($case_id);
    BK_Utils::audit("component_id: $component_id");

    $component_name = self::get_component_name_by_id($component_id);
    return $component_name;
 }


  /*
   *
   * From the url it is often possible to obtain the case id we are working with.
   *
   * This could break between civi versions if they change the url formats.
   * Ideally civi would have its own canonical form for GET and POST params - might
   * be worth looking into - the paths look suspiciously like REST endpoints (sort of) so 
   * there might be more structure than we've assumed. 
   *
   */
  static function extract_case_id_from_uri ($uri) {
    $uri = str_replace('&amp;', '&', $uri);

    BK_Utils::audit("Entering function " . __FUNCTION__);
    ### Just return if we're not passed a uri to work with
    if (empty($uri)) {
        return 0;
    }

    BK_Utils::audit("extr case id $uri");

    ### Create an array from the uri
    parse_str($uri, $query_parms);

    ### Handle caseid and caseID
    $query_parms = array_change_key_case($query_parms, CASE_LOWER);
    BK_Utils::audit(print_r($query_parms,TRUE));

    ### May not always exist, e.g. click Home->Civicrm



    if (array_key_exists('/civicrm/index_php?q', $query_parms)) {
      $query_path = $query_parms['/civicrm/index_php?q'];
    }
    else {
      $needle = '/civicrm/index_php?q';    

      $query_path = '';
      foreach ($query_parms as $key => $value) {
        // Check for strings ending with $needle
        $start_pos = strlen($key) - strlen($needle);
        // if (strpos($key, $needle, $start_pos) !== FALSE) {
        if (strpos($key, $needle) !== FALSE) {
          $query_path = $value;
        }
      }

      if ($query_path == '') {
        return 0;
      }
    }
    

    ### A mapping of paths from the query string to the correct parm holding the case id.
    ### TODO hold elsewhere?
    $map_query_path_case_parm = array (
        'civicrm/contact/view/case'         => 'id',
        'civicrm/ajax/activity'             => 'caseid',
        'civicrm/ajax/globalrelationships'  => 'caseid',
        'civicrm/ajax/caseroles'            => 'caseid',
        'civicrm/case/activity'             => 'caseid',
    );

    BK_Utils::audit("#######$$$ $uri $query_path");

// 2015-11-18 01:58:41 #########/civicrm/index.php?q=civicrm/case/activity&action=add&reset=1&cid=33&caseid=12&atype=2&snippet=json
// 2015-11-18 01:58:43 #########/civicrm/index.php?q=civicrm/case/activity&snippet=4&type=Activity&subType=2&qfKey=277f73a765f891a7ecd6e99d093e6cc8_3338&cgcount=1

    ### Finally, we can return the case id using the correct parm
    if (array_key_exists($query_path, $map_query_path_case_parm)) {
        $query_parms_key = $map_query_path_case_parm[$query_path];

        ### Avoid 'undefined index' errors by checking key exists

        if (array_key_exists($query_parms_key, $query_parms)) {
            $case_id = $query_parms[$query_parms_key];
        }
        else {
            $case_id = 0;
        }
    }
    else {
        $case_id = 0;
    }

    BK_Utils::audit("Caseid: $case_id");
    return $case_id;
  } 

  /*
   * Maps a case to the brisskit component
   */

/*

mysql> select * from brisskit_case_mappings;
+---------+--------------+
| case_id | component_id |
+---------+--------------+
|       2 |            1 |
+---------+--------------+

*/

  static function delete_case_mapping($case_id) {
    $sql = "DELETE FROM brisskit_case_mappings where case_id = %1";
    $params = array(1 => array($case_id, 'Integer'));
    CRM_Core_DAO::executeQuery( $sql, $params );
	}

  static function create_case_mapping ($case_id, $component_id) {
    require_once "CRM/Core/DAO.php";
    $sql = "INSERT INTO brisskit_case_mappings (case_id, component_id) values (%1, %2)";
    $params = array(1 => array($case_id, 'Integer'),
                    2 => array($component_id, 'Integer'));

    BK_Utils::set_status($sql);
    BK_Utils::set_status($case_id);
    BK_Utils::set_status($component_id);
    CRM_Core_DAO::executeQuery( $sql, $params );
  }

  static function create_case_type_mapping ($case_type_id, $component_id) {
    require_once "CRM/Core/DAO.php";
    $sql = "INSERT INTO brisskit_case_type_mappings (case_type_id, component_id) values (%1, %2)";
    $params = array(1 => array($case_type_id, 'Integer'),
                    2 => array($component_id, 'Integer'));
    CRM_Core_DAO::executeQuery( $sql, $params );
  }

  static function get_component_id_by_name ($component_name) {
    $params =  array(1 => array($component_name, 'String'));
    return CRM_Core_DAO::singleValueQuery("SELECT component_id FROM brisskit_components WHERE component_name = %1", $params);
  }
/*
  static function get_component_name_by_id ($component_id) {
    $params =  array(1 => array($component_id, 'Integer'));
    return CRM_Core_DAO::singleValueQuery("SELECT component_name FROM brisskit_components WHERE component_id = %1", $params);
  }
*/

  static function get_component_name_by_id ($component_id) {
    $db = DB::connect(CIVICRM_DSN);
    if (PEAR::isError($db)) {
      die($db->getMessage());
    }
    $sth = $db->prepare('SELECT component_name FROM brisskit_components where component_id = ?');
    $res =& $db->execute($sth, $component_id);

    if (PEAR::isError($res)) {
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
}
