source set-install-paths.sh
pwd

echo "../drupal_hooks/brisskit ${brisskit_module_root}"

cp -r ../drupal_hooks/brisskit/* ${brisskit_module_root}

# We need write access to the templates dir

chmod 777 /var/local/brisskit/drupal/site/civicrm/sites/default/files/civicrm/templates_c/en_US/

touch /tmp/bk_audit.log
touch /tmp/bk_debug.log
chmod 777 /tmp/bk_audit.log
chmod 777 /tmp/bk_debug.log
