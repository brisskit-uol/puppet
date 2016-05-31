#
# If the CiviCRM version is not specified when calling this script, the following version will be installed.
#
# As of June 2015, 4.4.15 is the most recent LTS version - see https://civicrm.org/versions for guidelines
# However, this version uses the "old" way of configuring Case Types, using xml files
#
# Note that 4.6.3 is incompatible as it is not possible to have 2 Civi cases with the same relationship between client and professional e.g. it is not possible to have
# the same administrator for 2 different studies involving the same patient
# This is thought to be a bug that will be fixed in future versions.
#
# So we use the 4.5.8 version.

default_civicrm_version="4.5.8"

required_min_apache_version=2.4.3

#
# Drupal major version number.
# Don't change this without extensive testing.
drupalversion="drupal-7"



#======================================================
# Edit/replace the following with suitable DB values.
#
# The mysql accounts will be created if they don't 
# already exist
#======================================================
DBTYPE="mysql"
MYSQL_CIVICRM_DB="civicrm"
MYSQL_CIVICRM_UN="civicrm"
MYSQL_CIVICRM_PW="br1ssk1t123"
MYSQL_DRUPAL_DB="drupal"
MYSQL_DRUPAL_UN="drupal"
MYSQL_DRUPAL_PW="br1ssk1t123"
#======================================================


#======================================================
# The following details must be valid before running
# the install scripts i.e. a root mysql user must exist
#======================================================
MYSQL_HOST="localhost"
MYSQL_ROOT_UN="root"
MYSQL_ROOT_PW="graphic_dust"



# DOMAIN=".brisskit.le.ac.uk"
# DOMAIN=".brisskit.le.axa.uk"
DOMAIN=""


POSTFIX_MAILNAME="civicrm.brisskit.org.uk"
