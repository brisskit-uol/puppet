<?php

//require_once "core.php";

function init_drupal() {
  include_once 'drupal_setup.php';
  civicrm_initialize();
          
  BK_Utils::audit("Drupal initialised successfully");
}
