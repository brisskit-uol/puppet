#
# BRISSkit directories.
#
brisskitvar="/var/local/brisskit"
brisskitetc="/etc/brisskit"


#
# Drupal top level directories.
#
drupalroot="${brisskitvar}/drupal"
drupalconf="${drupalroot}/conf"
apacheroot="${drupalroot}/site"

#
# The site sub-directory.
# This adds a path to the site URL.
# e.g.
#     http://hostname/civicrm/....
#
# Set this to '' to install Druapl at the website root.
# e.g.
#     http://hostname/....
#
drupalstub="civicrm"

#
# The name of the Drupal site.
# This is used to set the name of the Drupal database and Apache config file.
#
drupalname="civicrm"

#
# The Drupal site directory name within drupal/sites.
# Set this to 'default' to make this the default Drupal site.
#
drupalsite="default"

#
# CiviCRM settings.
#
civicrmroot="${brisskitvar}/civicrm"
civicrmdata="civicrm"
civicrmtarfile="civicrm-${civicrmversion}-drupal.tar.gz"
civicrml10ntarfile="civicrm-${civicrmversion}-l10n.tar.gz"
civicrminstall="civicrm-${civicrmversion}-drupal"

#
# Brisskit module settings
#
brisskit_module_root="${brisskitvar}/brisskit_module_root"

#
# Civi case file repository
#
case_root="${brisskitvar}/civicases"

#
# Generate the Drupal core install path.
#
if [ "${drupalstub}" != '' ]
then
    drupalcore="${apacheroot}/${drupalstub}"
else
    drupalcore="${apacheroot}"
fi

#/var/local/brisskit/drupal              <- drupalroot
#/var/local/brisskit/drupal/conf         <- drupalconf
#/var/local/brisskit/drupal/site         <- apacheroot
#/var/local/brisskit/drupal/site/civicrm <- drupalcore








