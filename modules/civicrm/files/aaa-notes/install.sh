#!/bin/bash
# Drupal-7 CiviCRM install script.
# Tested on a 'vanilla' Ubuntu 11.10 server install.
#
# NOTE: This is the original install script aimed at version 4.1.1
# 

#
# BRISSkit directories.
brisskitvar="/var/local/brisskit"
brisskitetc="/etc/brisskit"

#
# Drupal major version number.
# Don't change this without extensive testing.
drupalversion="drupal-7"

#
# Drupal top level directories.
drupalroot="${brisskitvar}/drupal"
drupalconf="${drupalroot}/conf"
apacheroot="${drupalroot}/site"

#
# The Apache virtual host name.
# This is only used if this is not the default site.
# If the intended virtual host name does not match the local machine name then you can set this manually.
#drupalhost="$(hostname -f)"
#Get the bru name eg bru1 as the hostname rather than bru1-admin.
drupalhost="$(hostname | cut -d'-' -f1)"

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
drupalname="civicrm" 

#
# The Drupal site directory name within dupal/sites.
# Set this to 'default' to make this the default Drupal site.
drupalsite="default" 

#
# CiviCRM settings.
civicrmroot="${brisskitvar}/civicrm"
civicrmdata="civicrm"
civicrmversion="4.1.1"
civicrmtarfile="civicrm-${civicrmversion}-drupal.tar.gz"
civicrminstall="civicrm-${civicrmversion}-drupal"

#
# Generate the Drupal core install path.
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
# Configure SSH server.

    #
    # Install and SSH server.
    if [ -z "$(which sshd)" ]
    then

        apt-get -y install openssh-server

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
postfix postfix/mynetworks string 127.0.0.0/8 [::ffff:127.0.0.0]/104 [::1]/128
postfix postfix/mailname	        string  civicrm.brisskit.org.uk
postfix postfix/recipient_delim	    string
postfix postfix/main_mailer_type    select  Internet Site
postfix postfix/destinations	    string  localhost
postfix postfix/mailbox_limit	    string  51200000
postfix postfix/relayhost	        string
postfix postfix/procmail	        boolean false
postfix postfix/protocols	        select  all
postfix postfix/chattr	            boolean false
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
# Install password generator.

    if [ -z "$(which pwgen)" ]
    then

        apt-get -y install pwgen

    fi

#
# Database admin functions.
# These will be replaced by the BRISSkit functions installed by Puppet.

    #
    # Create a random password.
    randompass()
        {
        #local size=${1:-'20'}
        #tr -dc '[:alnum:]' < /dev/urandom | fold -w "${size}" | head -n 1
        pwgen 22 1
        }

    #
    # Get a database config file.
    # WARNING - this uses a hard coded path from brisskit_db_param.
    getdbconf() {
        local base=$1
        echo "${brisskitetc}/mysql/${base}.cfg"
        }

    #
    # Initialise a database config file.
    initdbconf() {
        local base=$1
        local conf=$(getdbconf ${base})
        local type=${2:-'mysql'}
        local host=${3:-'localhost'}
        local name=${4:-${base}}
        local user=${5:-${base}}
        local pass=${6:-$(randompass)}
        #
        # If the database config isn't installed.
        if [ ! -f "${conf}" ]
        then
            mkdir -p "$(dirname ${conf})"
            echo "# Database config params set by install script" > "${conf}"
            echo "host=${host}" >> "${conf}"
            echo "type=${type}" >> "${conf}"
            echo "name=${name}" >> "${conf}"
            echo "user=${user}" >> "${conf}"
            echo "pass=${pass}" >> "${conf}"
        fi
        }

    #
    # Get the path to database config param.
    getdbparam() {
        local base=$1
        local name=$2
        #
        # If BRISSkit functions not installed.
        if [ -z "$(which brisskit_db_param)" ]
        then
            local conf="$(getdbconf ${base})"
            if [ -f "${conf}" ]
            then
                sed -n 's/^'"${name}"'=\(.\{1,\}\)$/\1/p' "${conf}"
            else
                echo ""
            fi
        #
        # If BRISSkit functions are installed.
        else
            brisskit_db_param "${base}" "${name}"
        fi
        }

    #
    # Get a database type.
    getdbtype() {
        local base=$1
        echo "$(getdbparam "${base}" 'type')"
        }

    #
    # Get a database host.
    getdbhost() {
        local base=$1
        echo "$(getdbparam "${base}" 'host')"
        }

    #
    # Get a database name.
    getdbname() {
        local base=$1
        echo "$(getdbparam "${base}" 'name')"
        }

    #
    # Get a database user name.
    getdbuser() {
        local base=$1
        echo "$(getdbparam "${base}" 'user')"
        }

    #
    # Get a database password.
    getdbpass() {
        local base=$1
        echo "$(getdbparam "${base}" 'pass')"
        }

    #
    # Database login.
    databaselogin()
        {
        local base=$1
        local name="$(getdbname ${base})"
        local user="$(getdbuser ${base})"
        local pass="$(getdbpass ${base})"
        
        mysql --user="${user}" --password="${pass}" "${name}"
        }

    #
    # Database info.
    databaseinfo()
        {
        local base=$1
        echo ""
        echo "Database [$(getdbname ${base})]"
        echo "Username [$(getdbuser ${base})]"
        echo "Password [$(getdbpass ${base})]"
        }

#
# Install MySQL server.

    if [ -z "$(which mysqld)" ]
    then

        #
        # Create the database config.
        initdbconf mysql mysql localhost mysql root

        #
        # Disable the debconf front-end.
        # http://ajohnstone.com/achives/installing-java-mysql-unattendednon-interactive-installation/
        DEBIAN_FRONTEND=noninteractive

        #
        # Configure admin password before the install.
        # Requred to prevent debconf prompting for the values. 
        # http://ajohnstone.com/achives/installing-java-mysql-unattendednon-interactive-installation/
cat | debconf-set-selections << EOF
mysql-server-5.1 mysql-server/root_password       password $(getdbpass 'mysql')
mysql-server-5.1 mysql-server/root_password_again password $(getdbpass 'mysql')
mysql-server-5.1 mysql-server/start_on_boot       boolean  true
EOF

        #
        # Install MySQL server
        apt-get -y install mysql-server

        #
        # Test the admin password.
        mysql --user="$(getdbuser 'mysql')" --password="$(getdbpass 'mysql')" --execute \
            "SELECT version()"

    fi

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
# Install PHP.

    #
    # Install PHP
    #if [ -z "$(which php)" ]
    if [ ! -e "/etc/apache2/mods-available/php5.conf" ]
    then

        apt-get -y install php5 php5-mysql php5-gd
        apt-get -y install php5-gmp
    fi

#
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

            #
            # Install make.
            if [ -z "$(which make)" ]
            then

                apt-get -y install make

            fi

            #
            # Install PHP pear libraries.
            if [ -z "$(which pear)" ]
            then

                apt-get -y install php-pear

            fi

            #
            # Install PHP dev libraries.
            if [ -z "$(which pecl)" ]
            then

                apt-get -y install php5-dev

            fi

            #
            # Install the library.
            pecl install uploadprogress

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
        pear install drush/drush

    fi

    drush status

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

    if [ ! -d "${drupalcore}" ]
    then

        if [ ! -d "${installpath}" ]
        then
            mkdir "${installpath}"
        fi

        pushd "${installpath}"

            drush dl "${drupalversion}"  --drupal-project-rename="${installname}"

        popd

    fi

# ----------------------------- -----------------------------
# Snapshot :
# ubuntu-11.10-theta-20120419025656.bak
# iota-civicrm-20120419153746.bak
# ----------------------------- -----------------------------

#
# Install common drupal modules.

    pushd "${drupalcore}"

        drush dl 'content_taxonomy'
        drush dl 'ctools'
        drush dl 'date'
        drush dl 'email'
        drush dl 'favicon'
        drush dl 'field_group'
        drush dl 'token'
        drush dl 'views'
        drush dl 'og'

    popd

#
# Create our Drupal database.

    initdbconf "${drupalname}"

    mysqladminuser="$(getdbuser 'mysql')"
    mysqladminpass="$(getdbpass 'mysql')"

    databasename="$(getdbname ${drupalname})"
    databaseuser="$(getdbuser ${drupalname})"
    databasepass="$(getdbpass ${drupalname})"

    #
    # Create our database
    mysqladmin --user="${mysqladminuser}" --password="${mysqladminpass}" create "${databasename}"

    #
    # Create our database user
    mysql --user="${mysqladminuser}" --password="${mysqladminpass}" --execute \
        "CREATE USER '${databaseuser}'@'localhost' IDENTIFIED BY '${databasepass}'"

    #
    # Grant access to our database
    mysql --user="${mysqladminuser}" --password="${mysqladminpass}" --execute \
        "GRANT ALL ON ${databasename}.* TO '${databaseuser}'@'localhost'"

    #
    # Test the user account.
    mysql --user="$(getdbuser ${drupalname})" --password="$(getdbpass ${drupalname})" --execute \
        "SELECT version()"

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
            # Create our site settings.
            if [ ! -e 'settings.php' ] 
            then

                host="$(getdbhost ${drupalname})"
                type="$(getdbtype ${drupalname})"
                name="$(getdbname ${drupalname})"
                user="$(getdbuser ${drupalname})"
                pass="$(getdbpass ${drupalname})"
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

        if [ ! -e "${drupalname}.conf" ]
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

    popd

    service apache2 reload

# ----------------------------- -----------------------------
# Snapshot :
# ubuntu-11.10-theta-20120418190050.bak
# iota-civicrm-20120419155930.bak
# ----------------------------- -----------------------------

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

                if [ ! -d "civicrm" ]
                then

                    if [ ! -d "../../zipfiles" ]
                    then
                        mkdir -p "../../zipfiles"
                    fi
                    
                    pushd "../../zipfiles"

                        if [ ! -e "${civicrmtarfile}" ]
                        then
                            wget -q "http://downloads.sourceforge.net/project/civicrm/civicrm-stable/4.1.1/${civicrmtarfile}"
                        fi

                    popd

                    tar -xvzf "../../zipfiles/${civicrmtarfile}"
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
        ln -s ${civicrmroot}/installs/${civicrminstall}/civicrm/ civicrm

    popd

#
# Create our CiviCRM database.

    initdbconf "${civicrmdata}"

    mysqladminuser="$(getdbuser 'mysql')"
    mysqladminpass="$(getdbpass 'mysql')"

    databasename="$(getdbname 'civicrm')"
    databaseuser="$(getdbuser 'civicrm')"
    databasepass="$(getdbpass 'civicrm')"

    #
    # Create our database
    mysqladmin --user="${mysqladminuser}" --password="${mysqladminpass}" create "${databasename}"

    #
    # Create our database user
    mysql --user="${mysqladminuser}" --password="${mysqladminpass}" --execute \
        "CREATE USER '${databaseuser}'@'localhost' IDENTIFIED BY '${databasepass}'"

    #
    # Grant access to our database
    mysql --user="${mysqladminuser}" --password="${mysqladminpass}" --execute \
        "GRANT ALL ON ${databasename}.* TO '${databaseuser}'@'localhost'"

    #
    # Test the user account.
    mysql --user="$(getdbuser ${civicrmdata})" --password="$(getdbpass ${civicrmdata})" --execute \
        "SELECT version()"

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
# Display database settings (for CiviCRM config).
# You will need these when initialising CiviCRM below.

if [ "${drupalstub}" != "" ]
then
    sitehref="http://${drupalhost}.brisskit.le.ac.uk/${drupalstub}"
else
    sitehref="http://${drupalhost}.brisskit.le.ac.uk"
fi

cat << EOF
    -------------------------
    -------------------------
    Drupal/CiviCRM deployment completed.

    Now you need to use a web browser to visit the site and complete the process.

    To complete the Drupal configuration goto :

        ${sitehref}/install.php

    To complete the CiviCRM configuration goto :

        ${sitehref}/sites/all/modules/civicrm/install/index.php

    The CiviCRM configuration page will need the following database settings

        CiviCRM Database Settings

            MySQL server   : localhost
            MySQL username : $(getdbuser "${civicrmdata}")
            MySQL password : $(getdbpass "${civicrmdata}")
            MySQL database : $(getdbname "${civicrmdata}")

        Drupal Database Settings

            MySQL server   : localhost
            MySQL username : $(getdbuser "${drupalname}")
            MySQL password : $(getdbpass "${drupalname}")
            MySQL database : $(getdbname "${drupalname}")

    -------------------------
    -------------------------

    Once you have completed the online configuration,
    remember to protect the drupal settings.

    chmod 'g-w' "${drupalcore}/sites/${drupalsite}"
    chmod 'g-w' "${drupalcore}/sites/${drupalsite}/settings.php"

EOF

# ----------------------------- -----------------------------
# Snapshot :
# ubuntu-11.10-theta-20120419135312.bak
# iota-civicrm-20120419164729.bak
# ----------------------------- -----------------------------


# TODO 
# File permissions
# https://drupal.org/node/244924

# Disable module install.
# https://drupal.org/documentation/install/modules-themes/modules-7

