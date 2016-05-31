<?php 
/*
 *
 * This function provides support for component-specific word replacements
 * Brisskit has components based on CiviCase - study and recruitment, and this function is a replacement for the standard ts() function.
 * However, after we've done our replacements that sting is passed to the standard I18n function so general replacemnents are still done.
 *
 */

function brisskit_ts($string, $params = array()) {

  // global $tsLocale;

  /*

  e.g. 

  word replacements table:
  $find_word = study|Case
  so $lhs = study
  and $rhs = Case
  replacement is "Study" (This is not used here - ts does the work)

  String we are passed:
  "this is a Case"

  Converted to:
  "this is a study|Case"

  ts() Does the rest, converting to:
  "this is a Study"

  */

// Note: we can't do a call via civicrm_api3 because that calls this and this calls that and ....
// If there is an error anyway

  
    // match_type is exactMatch or wildcardMatch
    // We'll do the exact matches first
    $query = "SELECT * FROM civicrm_word_replacement WHERE is_active = 1 ORDER BY match_type";

    $dao = CRM_Core_DAO::executeQuery($query);

    $result = array();
    $result ['values'] = array();

    while ($dao->fetch()) {
      $result ['values'][] = array( 'find_word'=>$dao->find_word,
                                    'replace_word'=>$dao->replace_word,
                                    'match_type'=>$dao->match_type,
                                    'id'=>$dao->id);
    }
/*
    if ($result->is_error) {
     throw new Exception("Error retrieving word replacements " . __FILE__ . ' ' . __METHOD__ . "\n");
    }
*/

#print $string;

  foreach ($result['values'] as $word_replacement_record) {
    // find_word is of the form brisskit_component_name|string_to_match e.g. study|Studies
    $find_word = $word_replacement_record['find_word'];
    $match_type = $word_replacement_record['match_type'];
    $id = $word_replacement_record['id'];
   
    // The exact match checking rule should ideally be the same as in the main word replacement code. But for our purposes
    // we can deal with the limited replacements with a simple word boundary check

    // So we don't process some strings more than once, we create an intermediate string with placeholders that won't be matched by subsequent replacements

    require_once (BK_EXTENSIONS_DIR . "/uk.ac.le.brisskit/CRM/Brisskit/BK_Component.php");

    $current_component = BK_Component::get_component_name();  // This works 5/8/15
    if (strpos($find_word, '|') !== FALSE) {
      list($lhs, $rhs) = explode('|', $find_word);
      $lhs = trim($lhs);
      $rhs = trim($rhs);
      if ($lhs == $current_component) {
        if ($match_type == 'exactMatch') {
          $regex = "/\b" . $rhs . "\b/";  // Match on word boundary
          $string = preg_replace($regex, "{$id}", $string);
        }
        else {
          $string = str_replace($rhs, "{$id}", $string);
        }
      }
    }
  }

  // Now replace the placeholders
  foreach ($result['values'] as $word_replacement_record) {
    $replace_word = $word_replacement_record['replace_word'];
    $id = $word_replacement_record['id'];
    $string = str_replace("{$id}", $replace_word, $string);
  }

  // Do any standard replacements
  $i18n = CRM_Core_I18n::singleton();
  return $i18n->crm_translate($string, $params);
}
