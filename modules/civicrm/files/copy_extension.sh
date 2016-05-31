pushd `dirname "$0"`


source ./procedures/bin/install-config.sh
source ./procedures/bin/set-install-paths.sh


extensions_dir="${drupalcore}/sites/${drupalsite}/files/civicrm/custom_ext"

if [ ! -d "${extensions_dir}" ]
then
  mkdir -p "${extensions_dir}"
fi

echo "Copying files from civix_extensions/uk.ac.le.brisskit to ${extensions_dir}" 
cp -uvr civix_extensions/uk.ac.le.brisskit "${extensions_dir}"
echo 


echo "Changing to extensions directory"
pushd civix_extensions/uk.ac.le.brisskit
echo 

echo "Diff ..."
diff -r . "${extensions_dir}/uk.ac.le.brisskit"
echo 

echo "Changing to patches directory"
pushd ../../patches/
echo 

# /bin/bash sed_script.sh
/bin/bash Case_patch.sh

#
# patch to include brisskit-specific settings (currently constants) from brisskit_civicrm.settings.php
#
# --forward (ignore if already patched)
# --reject-file=- (do not create reject file)
#
sudo patch --forward --reject-file=- /var/local/brisskit/drupal/site/civicrm/sites/default/civicrm.settings.php civicrm.settings.php.patch
cp brisskit_civicrm.settings.php /var/local/brisskit/drupal/site/civicrm/sites/default/

#
# patch to include brisskit-specific settings (set mysql variable) from brisskit_DAO.php
#
sudo patch --forward --reject-file=- /var/local/brisskit/drupal/site/civicrm/sites/all/modules/civicrm/CRM/Core/DAO.php DAO.php.patch
cp brisskit_DAO.php /var/local/brisskit/drupal/site/civicrm/sites/all/modules/civicrm/CRM/Core/

#
# patch to include brisskit-specific settings (load our replacement for ts() from brisskit_I18n.php
#
sudo patch --forward --reject-file=- /var/local/brisskit/drupal/site/civicrm/sites/all/modules/civicrm/CRM/Core/I18n.php I18n.php.patch
cp brisskit_I18n.php /var/local/brisskit/drupal/site/civicrm/sites/all/modules/civicrm/CRM/Core/

echo "Changing to patches files directory"
pushd files/
echo 

cp  Case.php  /var/local/brisskit/drupal/site/civicrm/sites/all/modules/civicrm/CRM/Case/BAO/


echo "Changing back to original directory"
popd
popd
popd
pwd

popd

