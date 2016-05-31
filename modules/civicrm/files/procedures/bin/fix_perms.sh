# The following needed to be done otherwise Civi would hang when a case is added
# The files were owned by root so presumably our install scripts were creating the files

sudo chown www-data  /var/local/brisskit/drupal/site/civicrm/sites/default/files/civicrm/ConfigAndLog/CiviCRM*.log
sudo chown -R  www-data /var/local/brisskit/drupal/site/civicrm/sites/default/files/civicrm/templates_c/en_US/

