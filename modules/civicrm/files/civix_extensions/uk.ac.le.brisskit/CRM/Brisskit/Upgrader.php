<?php


/**
 * Collection of upgrade steps.
 */
require_once ("CRM/Brisskit/BK_Constants.php");
require_once ("CRM/Brisskit/BK_Utils.php");
class CRM_Brisskit_Upgrader extends CRM_Brisskit_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed.
   *
  */
  public function install() {
    $this->executeSqlFile('sql/brisskit_install.sql');
    BK_Utils::set_status("Database updated successfully");

    require_once ("CRM/Brisskit/BK_Setup.php");
    try {
      BK_Utils::create_civi_option_value("activity_type", array("name" => BK_Constants::DATACOL_CONSENT, "label" =>BK_Constants::DATACOL_CONSENT, "is_active"=>1));
      BK_Utils::create_civi_option_value("activity_type", array("name" => BK_Constants::TISSUE_CONSENT, "label" =>BK_Constants::TISSUE_CONSENT, "is_active"=>1));
      BK_Utils::create_civi_option_value("activity_type", array("name" => BK_Constants::USEINFO_CONSENT, "label" => BK_Constants::USEINFO_CONSENT, "is_active"=>1));
      BK_Utils::set_status("Options added successfully");
    }
    catch(Exception $ex) {
      BK_Utils::set_status("Error creating option valuies in " . __FILE__ . ' ' . __METHOD__ . "\n" . $ex->getMessage(), 'error');
    }

    try {
      BK_Setup::init_required_fields();
      BK_Utils::set_status("Required fields added successfully");
    }
    catch(Exception $ex) {
      BK_Utils::set_status("Error initializing requured fields in " . __FILE__ . ' ' . __METHOD__ . "\n" . $ex->getMessage(), 'error');
    }

    _install();
  }

  public function enable() {
    _enable();
  }

  public function disable() {
    _disable();
  }

  public function uninstall() {
    _uninstall();
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   *
  public function uninstall() {
   $this->executeSqlFile('sql/brisskit_uninstall.sql');
  }

  /**
   * Example: Run a simple query when a module is enabled.
   *
  public function enable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a simple query when a module is disabled.
   *
  public function disable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   *
  public function upgrade_4200() {
    $this->ctx->log->info('Applying update 4200');
    CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
    CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
    return TRUE;
  } // */


  /**
   * Example: Run an external SQL script.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4201() {
    $this->ctx->log->info('Applying update 4201');
    // this path is relative to the extension base dir
    $this->executeSqlFile('sql/upgrade_4201.sql');
    return TRUE;
  } // */


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4202() {
    $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

    $this->addTask(ts('Process first step'), 'processPart1', $arg1, $arg2);
    $this->addTask(ts('Process second step'), 'processPart2', $arg3, $arg4);
    $this->addTask(ts('Process second step'), 'processPart3', $arg5);
    return TRUE;
  }
  public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  public function processPart3($arg5) { sleep(10); return TRUE; }
  // */


  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4203() {
    $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

    $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
    $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
    for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
      $endId = $startId + self::BATCH_SIZE - 1;
      $title = ts('Upgrade Batch (%1 => %2)', array(
        1 => $startId,
        2 => $endId,
      ));
      $sql = '
        UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
        WHERE id BETWEEN %1 and %2
      ';
      $params = array(
        1 => array($startId, 'Integer'),
        2 => array($endId, 'Integer'),
      );
      $this->addTask($title, 'executeSql', $sql, $params);
    }
    return TRUE;
  } // */

}


function _install() {
  $message= "Install time!";
  BK_Utils::audit ($message);

  // 1. Add each group
  _create_groups ();

  // 2.  Create the roles and link them to the groups.
  _create_acl_roles_groups ();

  //3.  Create the ACLs
  _acl_update ('create');

  //4. Create custom field groups and custom fields within those groups
  _process_custom_groups_fields (BK_Constants::ACTION_CREATE);
}
function _create_groups () {
  // Load the groups from our XML file.
  $bk_acl_groups = BK_Utils::get_brisskit_xml("Groups.xml");
  $group_type =array();
  $group_type[1]=1;

  foreach ($bk_acl_groups as $key =>$group_parms) {
    // Check if group exits to avoid errors
    if (! _group_exists($group_parms['title'])) {
      $result = civicrm_api3('Group', 'create', array(
        'sequential' => 1,
        'title' => $group_parms['title'],
        'name' => $group_parms['name'],
        'is_active' => 1,
        'visibility' => "User and User Admin Only",
        'description' => $group_parms['description'],
        'group_type' => $group_type,
      ));
    }
  }
}
/**
 * Helper function to link roles to groups.
 *
 * Get the OptionGroup for roles, loop thru roles in XML file creating link between groups and roles in acl_entity_roles table.
 */
function _create_acl_roles_groups () {
  // Get the group id for acl_roles, we'll need it when we create roles as option_values.
  $result = civicrm_api3('OptionGroup', 'get', array(
    'sequential' => 1,
    'return' => "id",
    'name' => "acl_role",
  ));

  if (($result['count']==0) 
  && ($result['is_error']==0) ) {
  BK_Utils::audit ('Cannot find acl role:'.print_r($result, TRUE));
    //Exceptions stop un/install in its tracks, WSOD, just audit instead.
    #throw new CRM_Extension_Exception(ts("Unable to determine id for Option Group acl_role. Check Option Group acl_role is defined.") ) ;
  }

  $gid=$result['values'][0]['id'];

  // Load the roles from our XML file.
  $bk_acl_roles = BK_Utils::get_brisskit_xml("Roles.xml");
  
  // Add each role and link it to a group
  foreach ($bk_acl_roles as $key =>$role_parms) {
    // Firstly, check the role doesn't already exist
    $result = civicrm_api3('OptionValue', 'get', array(
    'sequential' => 1,
    'name' => $role_parms['name'],
    ));

    if ($result['is_error']==0)  {
      if ($result['count']==0) {

      // Role doesn't exist, create the Role (these are option values)
        $result = civicrm_api3('OptionValue', 'create', array(
          'sequential' => 1,
          'option_group_id' => $gid,      // From our Option Group 'acl_role'
          'label' => $role_parms['label'],
          'name' => $role_parms['name'],
          'description' => $role_parms['description'],
        ));
        // Was the create successful?
        if (($result['count']==1) 
        && ($result['is_error']==0) ) {
          // Role was created, we need its value to link it to our group.
          $value=$result['values'][0]['value'];
          BK_Utils::audit ("create role val $value result:".print_r($result, TRUE));
          // Now link the role to the group
          if (isset($role_parms['groups'])) {
            _update_acl_roles($value, $role_parms['groups'], 'create');
          }
        }
        
      }
      elseif ($result['count']==1) {
        // Role already exists, we need its value to link it to our group.
        $value=$result['values'][0]['value'];
        BK_Utils::audit ("Role already exists, create role val $value result:".print_r($result, TRUE));
        // Now link the role to the group
        if (isset($role_parms['groups'])) {
          _update_acl_roles($value, $role_parms['groups'], 'create');
        }
      }
    }
  }

}

/**
 * Helper function to create/delete acl_entity_role entries linking a role to a group.
 *
 * @param $value (string), $groups array(string)
 *
 */
function _update_acl_roles($value, $groups, $action) {

  // Should never happen, but we're cautious.
  if (empty($value)) {
    return;
  }
  BK_Utils::audit ("$action acl with value $value groups:".print_r($groups, TRUE));
  // Loop thru groups linking them to the role.

  /*
  If the Roles XML has multiple group elements we get this:
  [1] => Array
    (
      [name] => ViewAllPatientsRol3
      [label] => ViewAllPatientsRol3
      [description] => Brisskit ACL role
      [groups] => Array
        (
          [group] => Array
            (
              [0] => Receptionist Group
              [1] => Administrator Group
              [2] => Study Member Group
            )

        )

    )

  However, if there is only a single group entry we get:
  [0] => Array
    (
      [name] => ViewMyPatientsRol3
      [label] => ViewMyPatientsRol3
      [description] => Brisskit ACL role
      [groups] => Array
        (
          [group] => Receptionist Group
        )

    )
  so we need to massage the data for the foreach.
  */
  if (!is_array($groups['group'])) {
    $single_group_name=$groups['group'];
    $groups['group']=array('0'=>$single_group_name);
    BK_Utils::audit ("Massaged groups group to:".print_r($groups['group'], TRUE));
  }

  foreach ($groups['group'] as $key=>$group_name) {
      BK_Utils::audit ("Loop groups with key $key gn::".print_r($group_name, TRUE));
      // Firstly, get the group id from the name.
      $entity_id=_get_group_id_by_name ($group_name);
      if ($entity_id>0) {
        if  ($action==BK_Constants::ACTION_CREATE) {
          // We've got a valid group id, go create an AclRole
          _acl_role_create ($entity_id, $value);
        }
        elseif  ($action==BK_Constants::ACTION_DELETE) {
          // We've got a valid group id, go rm the AclRole
          _acl_role_delete ($entity_id, $value);
        }
      }
  }
}

/**
 * Helper function to create acl_entity_role entries linking a role to a group.
 *
 * @param $entity_id (string), $value (string)
 *
 */
function _acl_role_create ($entity_id, $value) {

  // Don't create if it already exists
  $result = civicrm_api3('AclRole', 'get', array(
    'sequential' => 1,
    'entity_table' => "civicrm_group",
    'entity_id' => $entity_id,
    'acl_role_id' => $value,
  ));

  if (($result['is_error']==0) && ($result['count']==1)) {
    return;
  }
  $result = civicrm_api3('AclRole', 'create', array(
    'sequential' => 1,
    'entity_table' => "civicrm_group",
    'entity_id' => $entity_id,
    'acl_role_id' => $value,
    'is_active' => 1,
    'id' => 0,
  ));
  BK_Utils::audit ("*** create AclRole val $value ent id $entity_id result:".print_r($result, TRUE));
}

/**
 * Helper function to create/delete ACLs driven by XML input file.
 *
 * @param $entity_id (string), $value (string)
 *
 */
function _acl_update ($action) {
  // Load the ACLs from our XML file.
  $bk_acl_acls = BK_Utils::get_brisskit_xml("Acls.xml");

  // Get the value on this role, it serves as the entity_id on the ACL.
  foreach ($bk_acl_acls as $key =>$acl_parms) {
    $result = civicrm_api3('OptionValue', 'get', array(
    'sequential' => 1,
    'name' => $acl_parms['role'],
    ));

    // Did we find the role?
    if (($result['count']==1) 
    && ($result['is_error']==0) ) {
      // Role was found, we need its value to link it to our group.
      $value=$result['values'][0]['value'];
      // Now get the group id, it serves as the entity id
      $object_id=_get_group_id_by_name ($acl_parms['group']);
      if ($object_id>0) {
        if ($action==BK_Constants::ACTION_CREATE) {
          _acl_create ($acl_parms['name'], $value, $object_id, $acl_parms['operation']);
        }
        elseif ($action==BK_Constants::ACTION_DELETE) {
          _acl_delete ($acl_parms['name']);
        }
      }
      else {
        #throw new CRM_Extension_Exception(ts("Cannot find group $object_id Check Groups.xml groups match those in Acls.xml.") ) ;
        //Exceptions stop un/install in its tracks, WSOD, just audit instead.
        BK_Utils::audit ("Cannot find group $object_id Check Groups.xml groups match those in Acls.xml.".print_r($result, TRUE));
      }
    }
    // Role was not found, our previous steps failed or roles in acls.xml don't match those in roles.xml. If xml is edited we may have old values lying around!
    elseif ($action==BK_Constants::ACTION_CREATE) {
      //Exceptions stop un/install in its tracks, WSOD, just audit instead.
      #throw new CRM_Extension_Exception(ts("Cannot find role ".$acl_parms['role'].". Check Roles.xml roles match those in Acls.xml.") ) ;
      BK_Utils::audit ("Cannot find role ".$acl_parms['role'].". Check Roles.xml roles match those in Acls.xml.");
    }
    elseif ($action==BK_Constants::ACTION_DELETE) {
    // We can still delete ACL even if role not found
      _acl_delete ($acl_parms['name']);
    }
  }
}

/**
 * Helper function to create an acl.
 *
 * @param $name (string), $value (string), $object_id (string), operation (string)
 *
 */
function _acl_create ($name, $value, $object_id, $operation) {
  $result = civicrm_api3('Acl', 'create', array(
  'sequential' => 1,
  'id' => 0,
  'name' => $name,
  'entity_id' => $value,  //value on role.
  'operation' => $operation,
  'object_id' => $object_id, //Group id
  'entity_table' => "civicrm_acl_role",
  'is_active' => 1,
  'object_table' => "civicrm_saved_search",
  ));
  BK_Utils::audit ("ACL create obj id $object_id value $value name $name".print_r($result, TRUE));
}

/**
 * Helper function to delete an acl.
 *
 * @param $name (string)
 *
 */
function _acl_delete ($name) {
  
  $result = civicrm_api3('Acl', 'get', array(
    'sequential' => 1,
    'return' => "id",
    'name' => $name,
  ));
  if (($result['count']==1) 
  && ($result['is_error']==0) ) {
    $id=$result['values'][0]['id'];
    $result = civicrm_api3('Acl', 'delete', array(
    'id' => $id,
    ));
    BK_Utils::audit ("ACL delete id $id ".print_r($result, TRUE));
  }
}

/**
 * Helper function to delete a custom field.
 *
 * @param $label (string)
 *
 * @param $custom_group_id (string)
 *
 */
function _custom_field_delete ($label, $custom_group_id) {
    $result = civicrm_api3('CustomField', 'get', array(
      'sequential' => 1,
      'label' => $label,
      'custom_group_id' => $custom_group_id,
    ));

  // Did that work?
   if (($result['count']==1) 
  && ($result['is_error']==0) ) {
    $id=$result['values'][0]['id'];
    $result = civicrm_api3('CustomField', 'delete', array(
    'id' => $id,
    ));
  }
}

/**
 * Helper function to delete a custom field group.
 *
 * @param $label (string)
 *
 * @param $custom_group_id (string)
 *
 */
function _custom_group_delete ($custom_group_id) {

  if ((!empty($custom_group_id)) 
  && ($custom_group_id>0) ) {
    $result = civicrm_api3('CustomGroup', 'delete', array(
    'id' => $custom_group_id,
    ));
  }
}
/**
 * Helper function to delete an acl role.
 *
 * @param $entity_id (string), $value (string)
 *
 */
function _acl_role_delete ($entity_id, $value) {
  // rm the AclRole
  $result = civicrm_api3('AclRole', 'delete', array(
    'sequential' => 1,
    'entity_id' => $entity_id,
    'acl_role_id' => $value,
  ));
  BK_Utils::audit ("++++ remove AclRole val $value ent id $entity_id result:".print_r($result, TRUE));
  $result = civicrm_api3('System', 'flush', array(
  'sequential' => 1,
  ));
}

/**
 * Helper function to delete the roles and link between roles and groups (aclRoles).
 *
 * @param $name (string), $value (string), $object_id (string), operation (string)
 *
 */
function _remove_acl_roles_groups () {
  // Load the roles from our XML file.
  $bk_acl_roles = BK_Utils::get_brisskit_xml("Roles.xml");
  
  // Add each role and link it to a group
  foreach ($bk_acl_roles as $key =>$role_parms) {
    BK_Utils::audit ("+ get role name".$role_parms['name']);
    // Firstly, get the role id from its name
    $result = civicrm_api3('OptionValue', 'get', array(
    'sequential' => 1,
    'return' => "id, value",
    'name' => $role_parms['name'],
    ));
    if (($result['count']==1) 
    && ($result['is_error']==0) ) {
      $id=$result['values'][0]['id'];
      $value=$result['values'][0]['value'];
      // Unlink the role & the group

      if (isset($role_parms['groups'])) {
        _update_acl_roles($value, $role_parms['groups'], 'delete');
      }
      BK_Utils::audit ("++ unlink & rm opt val id $id value $value");
      /*
      This delete won't work if BAO del logic finds an ACL Role linked to this role.
      We've just deleted our ACL roles, there may be caching or there may be other roles that prevent this delete from working; $result is empty
      & no execption is thrown.
      At install time our create logic checks for their existence and doesn't create new ones if they do.
      */
      try {
        $result = civicrm_api3('OptionValue', 'delete', array(
          'id' => $id,
        ));
      }
      catch (CiviCRM_API3_Exception $e) {
        $error = $e->getMessage();
        BK_Utils::audit ("++>>api err:".print_r($error, TRUE));
      }
      BK_Utils::audit ("++>del opt val id $id result:".print_r($result, TRUE));
    }
  }
}

/**
 * Called at un/install time to create/delete the custom groups and custom fields we need
 *
 * Calls helper functions to add/delete custom field groups and custom fields.
 *
 */
function _process_custom_groups_fields ($action) {
  $bk_custom_fields = BK_Utils::get_brisskit_xml("Custom.xml");
  
  // Add/rm each field/group depending on action
  foreach ($bk_custom_fields as $key =>$custom_parms) {

    // Firstly, check if the Custom Group exists
    $result = civicrm_api3('CustomGroup', 'get', array(
    'sequential' => 1,
    'name' => $custom_parms['title'],
    ));

    if ($result['is_error']==0)  {
      if (($result['count']==0) 
      && ($action==BK_Constants::ACTION_CREATE)) {
      // CustomGroup doesn't exist & we're creating, go create the CustomGroup 
        $cfg_id = _create_custom_field_group ($custom_parms['title'], $custom_parms['extends']);

        // Was the create successful?
        if ($cfg_id>0) {
          // CustomGroup was created, we need its value to link our custom fields to it.
          // Now link the role to the group
          if (isset($custom_parms['custom_fields'])) {
            _update_custom_fields($cfg_id, $custom_parms['custom_fields'], BK_Constants::ACTION_CREATE);
          }
        }
      }
      elseif ($result['count']==1) {
        // Custom group already exists, we need its id to link it to our custom fields, whatever the action
        $cfg_id=$result['values'][0]['id'];
        if (isset($custom_parms['custom_fields'])) {
          if ($action==BK_Constants::ACTION_CREATE) {
            _update_custom_fields($cfg_id, $custom_parms['custom_fields'], BK_Constants::ACTION_CREATE);
          }
          else {
            // Delete custom fields
            _update_custom_fields($cfg_id, $custom_parms['custom_fields'], BK_Constants::ACTION_DELETE );
             // Delete custom group
            _custom_group_delete ($cfg_id);
          }
        }
      }
    }
  }
}

/**
 * 
 * Calls helper functions to remove roles, aclRoles, groups and ACLs.
 *
 */
function _uninstall() {
  $message= "Uninstall time!";
  BK_Utils::audit ($message);
  // Delete our custom fields and custom field groups, driven by Custom.xml
  _process_custom_groups_fields (BK_Constants::ACTION_DELETE);
  
//Deleting must be done in reverse order to creation because we need to do lookups on roles and groups when deleting ACLs.
  BK_Utils::audit('1');
  _acl_update ('delete');

  BK_Utils::audit('1');
  // *Must* do roles before groups otherwise we can't get the group ids from the group names!
  _remove_acl_roles_groups ();

  BK_Utils::audit('1');
  // Finally we can do the groups.  Load the groups from our XML file.
  $bk_acl_groups = BK_Utils::get_brisskit_xml("Groups.xml");
  $group_type =array();
  $group_type[1]=1;

  BK_Utils::audit('1');
  // Remove each group
  foreach ($bk_acl_groups as $key =>$group_parms) {
  BK_Utils::audit('1');
    //1st get the group id
    $result = civicrm_api3('Group', 'get', array(
       'sequential' => 1,
       'return' => "id",
       'title' => $group_parms['title'],
     ));
    // Does the group exist?
    if ( _group_exists($group_parms['title'])) {
      // We have a group id, go delete it
      $id=$result['values'][0]['id'];
      $result = civicrm_api3('Group', 'delete', array(
        'sequential' => 1,
        'id' => $id,
      )); 
    }
  }
}

function _group_exists ($title) {
  //1st get the group id
  $result = civicrm_api3('Group', 'get', array(
     'sequential' => 1,
     'return' => "id",
     'title' => $title,
  ));
  // Did that work?
  if ($result['count']==1) {
    $id=$result['values'][0]['id'];
    if (!empty($id)) {
      return TRUE;
    }
  } 
  return FALSE;
}

function _custom_field_exists ($label, $custom_group_id) {
    $result = civicrm_api3('CustomField', 'get', array(
      'sequential' => 1,
      'label' => $label,
      'custom_group_id' => $custom_group_id,
    ));

  // Did that work?
  if ($result['count']==1) {
    $id=$result['values'][0]['id'];
    if (!empty($id)) {
      return TRUE;
    }
  } 
  return FALSE;
}

function _get_group_id_by_name ($name) {
  //1st get the group id
  $result = civicrm_api3('Group', 'get', array(
    'sequential' => 1,
    'return' => "id",
    'name' => $name,
  ));
  // Did that work?
  if ($result['count']==1) {
    $id=$result['values'][0]['id'];
    if (!empty($id)) {
      return $id;
    }
  } 
  return FALSE;
}

/**
 * Helper function to create custom field group, used as a container for our custom fields.
 *
 * @param $title (string), $extends (string)
 *
 */
function _create_custom_field_group ($title, $extends='Contact') {
  if (empty($title)) {
    return FALSE;
  }

  $result = civicrm_api3('CustomGroup', 'create', array(
    'sequential' => 1,
    'title' => $title,
    'extends' => $extends,
  ));

  // Did that work?
  if (($result['count']==1) 
  && ($result['is_error']==0) ) {
    return $result['id'];
  }
  else {
    return FALSE;
  }
}

/**
 * Helper function to create custom field.
 *
 * @param $custom_group_id (string), $label (string), $data_type, $html_type, $default_value, $is_view
 *
 */
function _custom_field_create ($custom_group_id, $label, $data_type, $html_type, $default_value, $is_view) {

  if (empty($custom_group_id)) {
    return FALSE;
  }

  // XML parser converts 0 to empty string, reset default value for Int to zero.  
  if (empty($default_value)) {
    switch ($data_type) {
      case 'Int':
        $default_value = 0;
        break;
      case 'String':
        $default_value = '';
        break;
      default:
        $default_value = '';
        break;
    }
  }

  if (! _custom_field_exists ($label, $custom_group_id) ) {
    $result = civicrm_api3('CustomField', 'create', array(
        'sequential' => 1,
        'custom_group_id' => $custom_group_id,
        'label' => $label,
        'data_type' => $data_type,
        'html_type' => $html_type,
        'default_value' => $default_value,
        'is_active' => 1,
        'is_view' => $is_view,
    ));

    // Did that work?
    if (($result['count']==1) 
    && ($result['is_error']==0) ) {
        return $result['id'];
    }
    else {
        return FALSE;
    }
  }
  else {
    BK_Utils::audit ("Skipping custom field create cfgid $custom_group_id, label $label field already exists");
  }
}

/**
 * Helper function to create/delete custom fields linking a custom group.
 *
 * @param $value (string), $groups array(string)
 *
 */
function _update_custom_fields($custom_group_id, $custom_fields, $action) {

  // Should never happen, but we're cautious.
  if (empty($custom_group_id)) {
    return;
  }

  // If there is only one custom field to create the xml parser returns a flatter structure than our foreach expects.
  // In this situation we massage the array to match what the foreach expects.
  if (! isset($custom_fields['custom_field'][0])) {
    $single_field_name=$custom_fields['custom_field'];
    $custom_fields['custom_field']=array('0'=>$single_field_name);
  }

  // Loop thru fields linking them to the custom field group id.
  foreach ($custom_fields['custom_field'] as $key=>$field_parm) {
      BK_Utils::audit ("Loop fields with key $key fn::".print_r($field_parm, TRUE));
      // Create or delete the field?
        if  ($action==BK_Constants::ACTION_CREATE) {
          // We've got a valid custom field group id, go create an AclRole
          _custom_field_create ($custom_group_id, $field_parm['label'], $field_parm['data_type'], $field_parm['html_type'], $field_parm['default_value'], $field_parm['is_view']);
        }
        elseif  ($action==BK_Constants::ACTION_DELETE) {
          // We've got a valid group id, go rm the AclRole
          _custom_field_delete ($field_parm['label'], $custom_group_id);
        }
  }
}

/**
 * Here we are going to setup two parts of our environment:
 * 1) The xml menu routing will be loaded by flushing the System.
 * 2) The directory where custom PHP can be found.
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function _enable() {
  $message= "Enable time!";
  BK_Utils::audit ($message);
  $bk_dir = dirname( __FILE__ ) . DIRECTORY_SEPARATOR;

  /* Force the xmlMenu hook to fire to add our routing.
     This is the same as hitting the /menu/rebuild&reset=1 URL.
  */
  $result = civicrm_api3('System', 'flush', array(
    'sequential' => 1,
  ));

  //Set our custom PHP dir
  $params = array('version' => 3,
    'customPHPPathDir' => $bk_dir,
  );
  try{
    $result = civicrm_api3('Setting', 'create', $params);
  }
  catch (CiviCRM_API3_Exception $e) {
    // Handle error here.
    $errorMessage = $e->getMessage();
    $errorCode = $e->getErrorCode();
    $errorData = $e->getExtraParams();
    return array(
      'error' => $errorMessage,
      'error_code' => $errorCode,
      'error_data' => $errorData,
    );
  }
}

/**
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function _disable() {
  $message= "Disable time!";
  BK_Utils::audit ($message);
}
