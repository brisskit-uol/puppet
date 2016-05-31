#!/bin/bash



# Drupal-7 CiviCRM install script.
# 

#
# This script installs Drupal, CiviCRM and BRISSKit on Ubuntu (Debian may work but is untested)
#
# 1) Install Ubuntu packages (apt-get install etc) 
# 2) Download appropriate versions of drupal and CiviCRM
# 3) Extract files
# 4) Ensure mysql database and users are created
# 5) Run standard install scripts
# 6) Update CiviCRM with BRISSKit code
# 7) Update DB with with BRISSKit-specific data
# 8) When finished, display instructions for completing a) The drupal installation and b) the CiviCRM installation
#
# 
# After installation, CiviCRM and BRISSKit modules should be enabled in the drupal admin backend, and the CiviCase component enabled in the CiviCRM admin (Administer->System Settings->Enable CiviCRM components)
#
# 
# Drupal and civicrm can share the same database connection parameters but it should also work if they are separate.
#
#
# The script can run in 3 situations:
#
# 1) Fresh install
#
# 2) Install on top of a previous version
# This will retain the existing data but in tests did not fix errors in a previous installation
#
# 3) A reinstall, by specifying the -r option
# This drops existing drupal and CiviCRM databases and users
# Probably more reliable than 2)
#
#
# Prerequisites:
# ==============
#
# mysql must be installed (apt-get install mysql-server) or available remotely
#
# 
# TODO 
# ==== 
#
# while testing this was needed - echo extension=mysql.so >> /etc/php5/apache2/php.ini followed by "service apache2 restart". Is there a more robust was of doing this?
#
# The script now exits when there's a problem running drush. But there may be other fatal errors that are not detected?
#
# Some irrelevant messages are displayed, e.g. the results of dpkg -l
#  
# The script won't (in most cases) upgrade Ubuntu packages that are not already installed - should we have an option for this?
#
# Work needed on determination of hostname/domain - at the moment hostnames with hyphens are truncated.
#

# Need to be logged in as root (or sudo)
if [[ $EUID -ne 0 ]]
then
    echo "The script must run as root"
    exit 1
fi

#
# Start in directory of this script
cd `dirname "$0"`
scriptdir=`pwd`


#
# Pull in local configuration
source install-config.sh


civicrmversion=$default_civicrm_version	

reinstall_option=false
install_localization_files=false

# The following command line options are allowed, (h)elp, (r)einstall, (i)nteractive, (l)ocalize
#
# localize is not currently implemented

while getopts "ihlrv:" option; do
    case $option in
    	i) interactive=true;;
    	r) reinstall_option=true;;
		  v) civicrmversion="$OPTARG";;
		  l) install_localization_files=true ;;
		\? | h) echo "usage: $0 [-i] [-h] [-r] [-v civicrm_version_no] 
						i - interactive
						h - this text 
						r - reinstall (removes existing database and settings)
						l - include localization files for CiviCRM (l10n)
						v - install specified version of civiCRM. Default is $civicrmversion"; exit;;
    esac
done

echo  "Script will install CiviCRM vession $civicrmversion";

if [[ "$interactive" != true ]]
  then 
      echo -e "\e[36m"
      echo "##################################################"
      echo "#                                                #"
      echo "#                 IMPORTANT                      #"
      echo "#                 =========                      #"
      echo "#                                                #"
      echo "# The script is running in non-interactive mode  #"
      echo "# and may assume some key settings are correct.  #"
      echo "# If running this for the first time, and        #"
      echo "# particularly on new hardware configureations   #"
      echo "# it is strongly recommended to run this install #"
      echo "# script with the -i option.                     #" 
      echo "#                                                #"
      echo "##################################################"
      echo -e "\e[39m"
fi

reinstall=false

#
# "reinstall" will delete data so get confirmation we really want to do it
if  [[ "$reinstall_option" = true ]]
then 
    read -p "Reinstallation will any remove existing settings and drupal and civicrm databases. Would you like to continue? y/N " confirm_reinstall
    if [[ -n "$confirm_reinstall" && ( $confirm_reinstall == "Y" || $confirm_reinstall == "y" ) ]]
        then 
            reinstall=true
        else
            echo "User requested abort ... exiting"
            exit
    fi
else 
    reinstall=false
fi

if [[ "$reinstall" = true ]]
then 
	echo "Will reinstall"
else 
	echo "Will not reinstall"
fi


source "set-install-paths.sh"


if [ -z $DOMAIN ]
then 
	# Use IP address for dev systems etc.
	drupalhost="$(hostname -I | cut -d' ' -f1)" 
else
	# TODO - this is specific to bru, usually we'd want hyphenated domain names to be left as-is
	drupalhost="$(hostname | cut -d'-' -f1)"
fi


if [ "${drupalstub}" != "" ]
then
    sitehref="http://${drupalhost}${DOMAIN}/${drupalstub}"
else
    sitehref="http://${drupalhost}${DOMAIN}"
fi


if [[ $interactive == true ]]
then
  echo "Script has determined the following values for domain, hostname and web address."
  echo "Domain: $DOMAIN"
  echo "Hostname: $drupalhost"
  echo "Civi web address: $sitehref"
  echo "Ensure these are correct and are publicly accessible"
  read -p "Would you like to continue? y/N " confirm_continue
fi

if [[ $interactive == true ]]
then
  if [[ -n "$confirm_continue" && ( $confirm_continue == "Y" || $confirm_continue == "y" ) ]]
    then
      echo "Continuing ..."
    else
      echo
      echo "Add the following line to install-config.sh:"
      echo "DOMAIN=\"www.example.com\" # where www.example.com is the full public domain name or IP address"
      echo
      exit
  fi
fi

#
# Install basic tools.

#Do an repo update first
apt-get update 

    if [ -z "$(which unzip)" ]
    then

        apt-get -y install unzip

    fi

    if [ -z "$(which wget)" ]
    then

        apt-get -y install wget

    fi

#
# Configure firewall.
# https://wiki.ubuntu.com/UncomplicatedFirewall

    #
    # Install and configure firewall.
    if [ -z "$(which ufw)" ]
    then

        apt-get -y install ufw

        ufw default deny
        ufw allow ssh/tcp
        ufw allow http/tcp
        ufw logging on
        ufw --force enable

    fi

#
# Install Postfix mail server

    if [ -z "$(which postfix)" ]
    then

        #
        # Disable the debconf front-end.
        DEBIAN_FRONTEND=noninteractive

        #
        # Set configuration params before the install.
cat | debconf-set-selections << EOF
postfix postfix/root_address string root
postfix postfix/mynetworks             string 127.0.0.0/8 [::ffff:127.0.0.0]/104 [::1]/128
postfix postfix/mailname               string  ${POSTFIX_MAILNAME}
postfix postfix/recipient_delim        string
postfix postfix/main_mailer_type       select  Internet Site
postfix postfix/destinations           string  localhost
postfix postfix/mailbox_limit          string  51200000
postfix postfix/relayhost              string
postfix postfix/procmail               boolean false
postfix postfix/protocols              select  all
postfix postfix/chattr                 boolean false
EOF

        #
        # Install the service.
        apt-get -y install postfix

        # Reconfigure manually if required.
        # dpkg-reconfigure postfix
        # vi /etc/postfix/main.cf

#
# Send a test email.

#   sendmail -t << EOF
#   To:test@brisskit.org.uk
#   Subject:Test email
#   Test email .... please ignore
#   .
#   EOF

    fi

#


#
# Install password generator.

    if [ -z "$(which pwgen)" ]
    then
        apt-get -y install pwgen
    fi
    
#=================================================================================
# MySQL stuff
# Since the original script was written mysql has become local (ie: co-located)
# rather than on a remote machine.
#=================================================================================
apt-get install -y mysql-client

#
# Check prerequisite: we can connect as root mysql user
#
if mysql --user=${MYSQL_ROOT_UN} \
         --password=${MYSQL_ROOT_PW} \
         --execute=quit
then
        echo "Able to connect to mysql root user - continuing"
else
        echo "Could not connect to database as root"
        exit 1
fi

# If user requested to delete DB do it
if [ "$reinstall" = true ]
    then
    # drop civi user...     
    mysql --user=${MYSQL_ROOT_UN} \
          --password=${MYSQL_ROOT_PW} \
          --execute="DROP USER ${MYSQL_CIVICRM_UN}@${MYSQL_HOST}"

    # Drop drupal user...     
    mysql --user=${MYSQL_ROOT_UN} \
          --password=${MYSQL_ROOT_PW} \
          --execute="DROP USER ${MYSQL_DRUPAL_UN}@${MYSQL_HOST}"

    # Delete the civi database...
    mysql --user=${MYSQL_ROOT_UN} \
          --password=${MYSQL_ROOT_PW} \
          --execute="DROP DATABASE IF EXISTS ${MYSQL_CIVICRM_DB}"

    # Delete the drupal database...
    mysql --user=${MYSQL_ROOT_UN} \
          --password=${MYSQL_ROOT_PW} \
          --execute="DROP DATABASE IF EXISTS ${MYSQL_DRUPAL_DB}"
fi
 

# Create the civi database...
mysql --user=${MYSQL_ROOT_UN} \
      --password=${MYSQL_ROOT_PW} \
      --execute="CREATE DATABASE IF NOT EXISTS ${MYSQL_CIVICRM_DB}"
 
# Create an overall civi user...     
mysql --user=${MYSQL_ROOT_UN} \
      --password=${MYSQL_ROOT_PW} \
      --execute="CREATE USER ${MYSQL_CIVICRM_UN}@${MYSQL_HOST} identified by '${MYSQL_CIVICRM_PW}'"

# Grant everything on the civi database to the overall civi user...
mysql --user=${MYSQL_ROOT_UN} \
      --password=${MYSQL_ROOT_PW} \
      --execute="GRANT ALL ON ${MYSQL_CIVICRM_DB}.* TO ${MYSQL_CIVICRM_UN}@${MYSQL_HOST}"

# Create the drupal database...
mysql --user=${MYSQL_ROOT_UN} \
      --password=${MYSQL_ROOT_PW} \
      --execute="CREATE DATABASE IF NOT EXISTS ${MYSQL_DRUPAL_DB}"
 
# Create an overall drupal user...     
mysql --user=${MYSQL_ROOT_UN} \
      --password=${MYSQL_ROOT_PW} \
      --execute="CREATE USER ${MYSQL_DRUPAL_UN}@${MYSQL_HOST} identified by '${MYSQL_DRUPAL_PW}'"

# Grant everything on the drupal database to the overall drupal user...
mysql --user=${MYSQL_ROOT_UN} \
      --password=${MYSQL_ROOT_PW} \
      --execute="GRANT ALL ON ${MYSQL_DRUPAL_DB}.* TO ${MYSQL_DRUPAL_UN}@${MYSQL_HOST}"

#
# Database admin functions.
# These will be replaced by the BRISSkit functions installed by Puppet.

    #
    # Create a random password.
    randompass()
        {
         pwgen 22 1
        }

#
# Install Apache.

    #
    # Install Apache webserver
    if [ -z "$(which apache2)" ]
    then

        apt-get -y install apache2

    fi

    #
    # Enable mod-rewrite
    pushd /etc/apache2/mods-enabled

        if [ ! -e rewrite.load ]
        then
            ln -sf ../mods-available/rewrite.load
        fi

    popd

#


#
# Check prerequisite: Apache 2.4.3
#

current_apache_version=$(apachectl -v | grep 'Server version' | cut -d"/" -f2 | cut -d" " -f1)

if  $(dpkg --compare-versions $current_apache_version ge $required_min_apache_version) 
then 
	echo ""
else
	echo "Your version of apache $current_apache_version is less than the required $required_min_apache_version"
    read -p "You may need to change vhost settings later. Would you like to continue? y/N " confirm_apache_version_old
    if [[ -n "$confirm_apache_version_old" && ( $confirm_apache_version_old == "Y" ) ]]
    then 
		echo ""
    else
    	echo "User requested abort ... exiting"
    	exit 1
    fi
fi


# Install PHP.

    #
    # Install PHP
    #if [ -z "$(which php)" ]
    if [ ! -e "/etc/apache2/mods-available/php5.conf" ]
    then

        apt-get -y install php5
    fi

#
# php modules
    if  ! dpkg -l php5-mysql 
    then
        apt-get -y install php5-mysql

        if ! egrep ^extension=mysql.so /etc/php5/apache2/php.ini 
        then
            echo extension=mysql.so >> /etc/php5/apache2/php.ini
            service apache2 restart
        fi
    fi
    
    if  ! dpkg -l php5-gd 
    then
        apt-get -y install php5-gd
    fi

    if ! dpkg -l php5-gmp 
    then
        apt-get -y install php5-gmp
    fi

    if ! dpkg -l php5-curl
    then
        apt-get -y install php5-curl
    fi


# Install PECL uploadprogress library.

    peclini="/etc/php5/apache2/conf.d/uploadprogress.ini"
    pecllib="$(find /usr/lib/php5/ -name uploadprogress.so)"

    #
    # If either the lib or ini files are missing.
    if [ ! -e "${peclini}" -o -z "${pecllib}" ]
    then
    
        #
        # If the library is missing.
        if [ -z "${pecllib}" ]
        then

            apt-get -y install php5-dev
            apt-get -y install make
            apt-get -y install php-pear

            #
            # Install the library.
						#
						# Use -Z option to work around bug in some 32-bit versions of Ubuntu
						# https://bugs.launchpad.net/ubuntu/+source/php5/+bug/1315888
						# https://bugs.launchpad.net/ubuntu/+source/php5/+bug/1310552
						#
            pecl install -Z uploadprogress

        fi

        #
        # If the ini file is missing
        if [ ! -e "${peclini}" ]
        then
cat > "${peclini}" << EOF
extension=uploadprogress.so
EOF
        fi

        service apache2 restart

    fi

    #
    # Install Drupal shell (drush).
    if [ -z "$(which drush)" ]
    then

        #
        # Install PHP pear libraries.
        if [ -z "$(which pear)" ]
        then

            apt-get -y install php-pear

        fi

        #
        # Locate the drush metadata.
        pear channel-discover pear.drush.org

        #
        # Install drush.
		#
		# Use -Z option to work around bug in some 32-bit versions of Ubuntu
		# https://bugs.launchpad.net/ubuntu/+source/php5/+bug/1315888
		# https://bugs.launchpad.net/ubuntu/+source/php5/+bug/1310552
		#
        pear install -Z drush/drush

    fi

#
# if drush returns a non-zero error code we want to exit from the script
    drush status || exit 1

#
# Install Drupal.

    if [ ! -d "${drupalroot}" ]
    then
        mkdir -p "${drupalroot}"
    fi

#/var/local/drupal           <- drupalroot
#/var/local/drupal/conf      <- drupalconf
#/var/local/drupal/site      <- apacheroot
#/var/local/drupal/site/stub <- drupalcore

    installpath=$(dirname  ${drupalcore})
    installname=$(basename ${drupalcore})

    echo "Installing Drupal.. "
    echo "core [${drupalcore}]"
    echo "path [${installpath}]"
    echo "name [${installname}]"

    if [[ ! -d "${drupalcore}" || "$reinstall" = true ]]
    then

        if [ ! -d "${installpath}" ]
        then
            mkdir "${installpath}"
        fi

        pushd "${installpath}"

            drush dl "${drupalversion}"  --drupal-project-rename="${installname}" || exit 1

        popd
    fi

#
# Install common drupal modules.

    pushd "${drupalcore}"

        drush dl 'content_taxonomy' || exit 1
        drush dl 'ctools' || exit 1
        drush dl 'date' || exit 1
        drush dl 'email' || exit 1
        drush dl 'favicon' || exit 1
        drush dl 'field_group' || exit 1
        drush dl 'token' || exit 1
        drush dl 'views' || exit 1
        drush dl 'og' || exit 1

    popd

#
# Create our Drupal site config.

    pushd "${drupalcore}/sites"

        #
        # Create our site directory.
        if [ ! -d "${drupalsite}" ] 
        then

            mkdir "${drupalsite}"

        fi

        pushd "${drupalsite}"

            #
            # If we are NOT the default site.
            if [ 'default' != "${drupalsite}" ]
            then
                #
                # Create the multi-site aliases
                if [ ! -e "sites.php" ] 
                then

                    if [ ! -e "../sites.php" ] 
                    then
cat >> "../sites.php" << EOF
<?php
/**
 * Multi-site directory aliasing:
 *
 */

EOF
                fi

cat >> "../sites.php" << EOF
require '${drupalsite}/sites.php' ;
EOF

cat > "sites.php" << EOF
<?php
/**
 * Multi-site directory aliasing:
 *
 */

\$sites['${drupalhost}'] = '${drupalsite}';

EOF
                fi
            fi

            #
            # If user has requested a reinstallation, remove existing settings
            if [ "$reinstall" = true ] 
            then
                rm 'settings.php'
            fi

            #
            # Create our site settings.
            if [ ! -e 'settings.php' ] 
            then
            
                host="${MYSQL_HOST}"
                type="${DBTYPE}"
                name="${MYSQL_DRUPAL_DB}"
                user="${MYSQL_DRUPAL_UN}"
                pass="${MYSQL_DRUPAL_PW}"
                salt="$(randompass)"

cat > settings.php << EOF
<?php

/*
 * Database config.
 *
 */
\$databases['default']['default'] = array(
    'driver'    => '${type}',
    'database'  => '${name}',
    'username'  => '${user}',
    'password'  => '${pass}',
    'host'      => '${host}',
    'prefix'    => '',
    'collation' => 'utf8_general_ci',
    );

/**
 * Salt for one-time login links, cancel links and form tokens, etc.
 *
 */
\$drupal_hash_salt = '${salt}';

EOF

            fi

            #
            # Allow Apache to write to the files.
            if [ ! -d "files" ] 
            then

                mkdir "files"
                chgrp 'www-data' "files"
                chmod 'g+rws'    "files"

            fi

            #
            # Allow Apache to modify our settings (required for install).
            chgrp 'www-data' "settings.php"
            chmod 'g+rw'     "settings.php"
            
        popd

    popd

#
# Create our Apache vhost config.

    if [ ! -d "${drupalconf}" ]
    then
        mkdir -p "${drupalconf}"
    fi

    pushd "${drupalconf}"

        if [ ! -e "${drupalname}.conf" ] || [ "$reinstall" = true ]
        then

#
# Create our Apache config.
cat > "${drupalname}.conf" << EOF
<VirtualHost *:80>

    # With no server name set, this will match any hostname.
    # ServerName  ${drupalhost}

    ServerAdmin  admin@localhost
    DocumentRoot ${apacheroot}
    ErrorLog     \${APACHE_LOG_DIR}/${drupalname}.error.log
    CustomLog    \${APACHE_LOG_DIR}/${drupalname}.access.log common

    php_value include_path "."

    <Directory ${drupalcore}>

        #
        # Allow symbolic links.
        Options FollowSymLinks

		# Apache >= 2.4.3 (Will break previous versions - 500 errors)
		Require all granted

        #
        # Set the rewrite rules here.
        RewriteEngine on
        RewriteBase /
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)\$ index.php?q=\$1 [L,QSA]

    </Directory>

</VirtualHost>
EOF

#
# TODO
# For default site, VirtualHost config with no server name will match any hostname.
# For multi site install, add ServerName and ServerAlias.
#

        fi
    popd
    
    #
    # Install our Apache config.
    pushd /etc/apache2/sites-enabled

        ln -sf ${drupalconf}/${drupalname}.conf ${drupalname}.conf

        #
        # Remove the default Apache site.
        if [ -L "000-default" ]
        then
            rm "000-default"
        fi
		# Recent Apache versions
        if [ -L "000-default.conf" ]
        then
            rm "000-default.conf"
        fi


    popd

    service apache2 reload

#
# Install CiviCRM.

    if [ ! -d "${civicrmroot}" ]
    then
        mkdir -p "${civicrmroot}"
    fi

    pushd "${civicrmroot}"

        if [ ! -d "installs" ]
        then
            mkdir -p "installs"
        fi

        pushd "installs"

            if [ ! -d "${civicrminstall}" ]
            then
                mkdir -p "${civicrminstall}"
            fi

            pushd "${civicrminstall}"

                if [[ ! -d "civicrm" || "$reinstall" = true ]]
                then

                    if [ ! -d "../../zipfiles" ]
                    then
                        mkdir -p "../../zipfiles"
                    fi
                    
                    pushd "../../zipfiles"

                        if [ ! -e "${civicrmtarfile}" ]
                        then
                            wget -q "http://downloads.sourceforge.net/project/civicrm/civicrm-stable/${civicrmversion}/${civicrmtarfile}"
                        fi

    					if [ ! -e "${civicrml10ntarfile}" ]
    					then
    						wget -q "http://downloads.sourceforge.net/project/civicrm/civicrm-stable/${civicrmversion}/${civicrml10ntarfile}"
    					fi

                    popd

                    tar -xvzf "../../zipfiles/${civicrmtarfile}" > /dev/null
                fi

            popd

        popd
    popd

#
# Add CiviCRM into Drupal.

    pushd "${drupalcore}/sites/all/modules"

        #
        # If 'civicrm' is already installed, remove it.
        if [ -e  "civicrm" ]
        then
            if [ -L "civicrm" ]
            then
                rm "civicrm"
            else
                rm -f "civicrm"
            fi
        fi
        #
        # Add a link to the new module.


echo "111111111111"
	cp -vrp ${civicrmroot}/installs/${civicrminstall}/civicrm/ civicrm
echo "222222222222222"
        # ln -s ${civicrmroot}/installs/${civicrminstall}/civicrm/ civicrm

    popd

#
# Un-protect our site directory (used by CiviCRM install.php).

    chgrp www-data "${drupalcore}/sites/${drupalsite}"
    chmod g+w      "${drupalcore}/sites/${drupalsite}"

#
# Check our site files directry is writeable.

    if [ ! -e "${drupalcore}/sites/${drupalsite}/files" ]
    then
        mkdir -p         "${drupalcore}/sites/${drupalsite}/files"
        chgrp 'www-data' "${drupalcore}/sites/${drupalsite}/files"
        chmod 'g+rwxs'   "${drupalcore}/sites/${drupalsite}/files"
    fi 


#
# Install localization code
	if $install_localization_files
	then
    	pushd "${drupalcore}/sites/all/modules"
		tar -xzf ${civicrml10ntarfile}
		popd
	fi


cd ${scriptdir}
cd ..

#####################################################################
# Install some cases

#Get the case files
#svn checkout ${case_location}

#Move to right place
cp -r civicases ${case_root}


#####################################################################
# Install the brisskit module

#Get source
#svn checkout ${brisskit_module_location}

#Move to right place
cp -r drupal_hooks/brisskit ${brisskit_module_root}

# Add a link to the new module.
ln -s ${brisskit_module_root} ${drupalcore}/sites/all/modules/brisskit

#
#
# Export configuration so php can use it via require_once() etc
# This may not be needed now we are using civix
#
#
# 
cat <<PHP_CONFIG > php_config.php
#
# This file was auto-generated by $0, $(date)
#
\$path = "${drupalcore}/sites/all/modules/civicrm";

#crm defined constant to config dir -> deals with symlinks
define("CIVICRM_CONFDIR","${drupalcore}/sites/default");

set_include_path("\$path/drupal" . PATH_SEPARATOR . \$path . PATH_SEPARATOR . \$path."/packages".PATH_SEPARATOR."${drupalcore}/sites/default");
PHP_CONFIG

#####################################################################
# Add the crontab to do the automatic data transfers
crontab cron/crontab




cd ${scriptdir}

source copy_drupal_modules.sh
source copy_cron.sh
#source ../../copy_extension.sh
# source fix_perms.sh		# If debugging enabled

cat > ${scriptdir}/installation.txt << EOF

    -------------------------
    -------------------------
    Drupal/CiviCRM deployment completed - these instructions have been saved to ${scriptdir}/installation.txt.

    Now you need to use a web browser to visit the site and complete the process.

    To complete the Drupal configuration goto :

        ${sitehref}/install.php

    To complete the CiviCRM configuration goto :

        ${sitehref}/sites/all/modules/civicrm/install/index.php

    The CiviCRM configuration page will need the following database settings

        CiviCRM Database Settings

            MySQL server   : ${MYSQL_HOST}
            MySQL username : ${MYSQL_CIVICRM_UN}
            MySQL password : ${MYSQL_CIVICRM_PW}
            MySQL database : ${MYSQL_CIVICRM_DB}

        Drupal Database Settings

            MySQL server   : ${MYSQL_HOST}
            MySQL username : ${MYSQL_DRUPAL_UN}
            MySQL password : ${MYSQL_DRUPAL_PW}
            MySQL database : ${MYSQL_DRUPAL_DB}

    Next, enable CiviCase component via CiviCRM->Administer->System Settings->Enable Components

    Once you have completed the online configuration,
    remember to protect the drupal settings.

    sudo chmod 'g-w' "${drupalcore}/sites/${drupalsite}"
    sudo chmod 'g-w' "${drupalcore}/sites/${drupalsite}/settings.php"

    You also need to enable the brisskit module, these depend on CiviCase being enabled. 
    You need to be in the module directory to do this, so

    cd ${drupalcore}/sites/all/modules
    
    drush en bk_role_perms,bk_drupal_sample_data

    Set the Extension Resource URL in the CiviCRM admin backend (Administer -> System Settings -> Resource URLs) to:
    	${sitehref}/civicrm/sites/default/files/civicrm/custom_ext

    Copy the brisskit files:
      sudo /bin/bash copy_extension.sh

		Set the directory locations (Administer -> System Settings -> Directories) as:
			Custom Templates: /var/local/brisskit/civicases
			Custom PHP Path Directory: /var/local/brisskit/drupal/site/civicrm/sites/default/files/civicrm/custom_ext/uk.ac.le.brisskit/CRM/Brisskit/
			CiviCRM Extensions Directory: /var/local/brisskit/drupal/site/civicrm/sites/default/files/civicrm/custom_ext/


    Install the brisskit extension in the CiviCRM admin backend
			Administer -> System Settings -> Manage Extensions
			
    To prevent log files beeing downloadable (a security issue) add the following lines to /etc/apache2/apache2.conf then restart the webserver 
    with 'service apache2 restart':

<Directory /var/local/brisskit/drupal/site/civicrm/>
  Options Indexes FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>
  
    Drupal/CiviCRM deployment completed - these instructions have been saved to ${scriptdir}/installation.txt.

    -------------------------
    -------------------------

    

EOF

clear

echo -e "\e[36m"
cat ${scriptdir}/installation.txt
echo -e "\e[39m"

if $install_localization_files
then
	echo "To setup localization for your region go to Administer >> Localization >> Languages, Currency, Locations in CiviCRM"
	echo "(http://wiki.civicrm.org/confluence/display/CRMDOC/i18n+Administrator%27s+Guide%3A+Using+CiviCRM+in+your+own+language)"
fi

#
# Make a wider theme available for civicrm - install via Drupal backend
#
pushd "${drupalcore}/sites/all/themes"
sudo wget http://ftp.drupal.org/files/projects/civi_bartik-7.x-1.0.tar.gz
sudo tar -xvzf civi_bartik-7.x-1.0.tar.gz
popd


#Need to add the brisskit module download and enable a la:
#drush en brisskit,brisskit_datacol,brisskit_useinfo,brisskit_tissue


# TODO 
# File permissions
# https://drupal.org/node/244924

# Disable module install.
# https://drupal.org/documentation/install/modules-themes/modules-7

