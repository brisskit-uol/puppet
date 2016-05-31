<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * FIXME
 */
class Brisskit_Test extends CiviUnitTestCase {
  function setUp() {
    // If your test manipulates any SQL tables, then you should truncate
    // them to ensure a consisting starting point for all tests
    $this->quickCleanup(array('civicrm_custom_group', 'civicrm_custom_field'), 'civicrm_custom_value');
    parent::setUp();
  }

  function tearDown() {
    parent::tearDown();
  }

  /**
   * Test that 1^2 == 1
   */
  function testSquareOfOne() {
    $this->assertEquals(1, 1*1);
  }

  /**
   * Test that 8^2 == 64
   */
  function testSquareOfEight() {
    $this->assertEquals(64, 8*8);
  }

  /**
   * Test that the brisskit installation of custome fields worked as expectedQ
   * We use the following data as an example
   *
   * Group: (array("title" => "Workflow", "extends" => "Activity","is_active"=>1,"style"=>"Inline","collapse_display"=>1));
   * Field: ($cg, array("name" => "workflow_triggered", "label" => "Workflow triggered", "data_type" => "Boolean", "html_type" => "Radio","is_active"=>1, "is_view"=>1));
   * Value: ("activity_type", array("name" => BK_Constants::ACTIVITY_CHECK_STATUS, "label" => BK_Constants::ACTIVITY_CHECK_STATUS, "is_active"=>1));
   */

  function testInstall () {
    
    $this->assertDBQuery(0, 'SELECT count(*) FROM civicrm_custom_group');
    $this->assertDBQuery(0, 'SELECT count(*) FROM civicrm_custom_field');
    $this->assertDBQuery(0, 'SELECT count(*) FROM civicrm_custom_value');

    BK_Setup::init_required_fields();

    $this->assertDBQuery(1, "SELECT count(*) FROM civicrm_custom_group where name ='Workflow'");
    $this->assertDBQuery(1, "SELECT count(*) FROM civicrm_custom_field where name ='workflow_triggered'");
    $this->assertDBQuery(1, "SELECT count(*) FROM civicrm_custom_group where name ='" . BK_Constants::ACTIVITY_CHECK_STATUS . "'");
  
    // If we upgrade we'll run the setup again but the DB should be unchanged 
    BK_Setup::init_required_fields();

    $this->assertDBQuery(1, "SELECT count(*) FROM civicrm_custom_group where name ='Workflow'");
    $this->assertDBQuery(1, "SELECT count(*) FROM civicrm_custom_field where name ='workflow_triggered'");
    $this->assertDBQuery(1, "SELECT count(*) FROM civicrm_custom_group where name ='" . BK_Constants::ACTIVITY_CHECK_STATUS . "'");
  }
}
