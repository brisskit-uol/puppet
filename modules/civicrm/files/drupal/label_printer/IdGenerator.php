<?php

/**
 * This class provides utility functions for creating unique, but random looking IDs.
 * 
 * Example usage:
 *
 * <code>
 *
 * // Generate an ID
 *
 *   $idGenerator = new IdGenerator('Prefix');
 *   $ID = $idGenerator->next();
 *
 * </code>
 * 
 * Things to note are:
 *
 *   + The prefix parameter cannot be NULL or empty.
 *   + The getNext saves the generated ID.
*/

class IdGenerator
{

//    const PRIME = 99991;
    const PRIME = 999983; // PRIME MOD 4 must equal 3

    /**
     * Constructor.
     *
     * @param string $prefix for the codes being generated.
     * @throws Exception if the $prefix is empty or not set.
     */
     public function __construct($prefix) {
        if (!isset($prefix) || trim($prefix)==='') {
            throw new InvalidArgumentException("IdGenerator cannot be instantiated without a prefix.");
        }

        $this->prefix = $prefix;
    }

    /**
     * Function that returns the next number in the sequence for the prefix given in the constructor
     *
     * @return next ID in the sequence
     */
    public function next() {
        $lockName = __METHOD__.$this->prefix;

        if (lock_acquire($lockName,3)) {
            $newOrdinal = $this->_get_previous() + 1;
            $uniqueId = $this->_create_unique_id($newOrdinal);
            $formattedId = $this->prefix.substr("00000000".$uniqueId, -7);
            $checkDigit = $this->_get_checkdigit($formattedId);
            $fullcode = $formattedId.$checkDigit;
            $this->_save($newOrdinal, $uniqueId, $checkDigit, $fullcode);

            lock_release($lockName);
        } else {
            form_set_error('',t('Unable to secure a lock on the database.'));
        }

        return $fullcode;
    }

    /**
     * Function that checks whether the check digit for a code is valid
     *
     * @param string $fullcode.
     * @return boolean of whether the check digit is valid
     */
    public function validateCheckDigit($fullcode) {
        $actualCheckDigit = substr($fullcode, -1);
        $codePart = substr($fullcode, 0, strlen($fullcode) - 1);

        $expectedCheckDigit = $this->_get_checkdigit($codePart);

        return ($actualCheckDigit == $expectedCheckDigit);
    }

    /**
     * Function that checks whether a code is valid
     *
     * @param string $fullcode.
     * @return boolean of whether the full code is valid
     */
    public function validate($fullcode, $isMandatory = false) {
        if (strlen($fullcode) == 0 && !$isMandatory) {
            return true;
        }

        $regEx = '/^(' . $this->prefix . '\d{7}[A-Z])?$/';

        if (!preg_match($regEx, $fullcode)) {
            return false;
        }

        return $this->validateCheckDigit($fullcode);
      }

    /**
     * Function that tests that the numbers returned are unique
     *
     */
    public function test() {
        // Use SplFixedArray because otherwise we run out of memory
        $results = new SplFixedArray(Self::PRIME);
        $largestNumber = 0;

        for ($x = 0; $x < Self::PRIME; $x++) {
            $uniqueId = $this->_create_unique_id($x);

            if ($uniqueId > Self::PRIME) {
              drupal_set_message("ID too big: ".$uniqueId." for ".$x);
              return;
            }

            if (isset($results[$uniqueId])){
                drupal_set_message("Failed");
                return;
            } else {
              $results[$uniqueId] = 1;
            }

            if ($uniqueId > $largestNumber) {
              $largestNumber = $uniqueId;
            }

            if ($x < 20) {
              drupal_set_message("ID: ".$uniqueId);
            }
        }

        drupal_set_message("Worked");
        drupal_set_message("Largest ID was: ".$largestNumber);
    }

    private function _permuteQPR($x){
        // See http://preshing.com/20121224/how-to-generate-a-sequence-of-unique-random-integers/

        if ($x >= Self::PRIME)
            throw Exception("Random number seed to high.");

        $residue = ($x * $x) % Self::PRIME;

        //drupal_set_message("residue: ".$residue);
        return ($x <= Self::PRIME / 2) ? $residue : Self::PRIME - $residue;
    }

    private function _create_unique_id($x){
        $first = ($this->_permuteQPR($x) + $this->_get_numeric_representation_of_prefix()) % Self::PRIME;
        $second = $this->_permuteQPR($first);

        return $second;
    }

    private function _get_numeric_representation_of_prefix(){
        $result = 0;
        for ($i = 0; $i < strlen($this->prefix); $i++) {
            $result += ord(substr($this->prefix, $i,1));
        }

        return $result;
    }

    private function _get_checkdigit($id){

        $checkDigits = array(
            "A",
            "B",
            "C",
            "D",
            "E",
            "F",
            "G",
            "H",
            "J",
            "K",
            "L",
            "M",
            "N",
            "P",
            "Q",
            "R",
            "S",
            "T",
            "V",
            "W",
            "X",
            "Y",
            "Z",
            );

        $result = 0;

        for ($i = 0; $i < strlen($id); $i++) {
            $result += ord(substr($id, $i,1)) * $i;
        }

        return $checkDigits[$result % 23];
    }

    private function _get_previous() {
        $result = 0;

        $query = db_select('unique_ids', 'u')
                    ->condition('prefix', $this->prefix,'=');
        $query->addExpression('MAX(ordinal)', 'max_ordinal');
        $queryResult = $query->execute()
                    ->fetchAssoc();

        if ($queryResult['max_ordinal']) {
            $result = $queryResult['max_ordinal'];
        }

        return $result;
    }

    private function _save($ordinal, $unique_id, $check_digit, $fullcode) {
        db_insert('unique_ids')
            ->fields(array(
              'ordinal' => $ordinal,
              'prefix' => $this->prefix,
              'unique_id' => $unique_id,
              'check_digit' => $check_digit,
              'fullcode' => $fullcode,
            ))
            ->execute();
    }
}
