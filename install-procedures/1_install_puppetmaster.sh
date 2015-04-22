#!/bin/bash
#===========================================================================================#
# Installs Puppet Master																	#
#																							#
# v0.1 - 25/03/2015 - RP -	Initial release													#
# v0.2 - 27/03/2015 - RP -	Made script more generic.										#
#							Install specific Puppetmaster version.							#
#							Use Puppet commands where possible for broader OS compatibility	#
#===========================================================================================#

# Set a location to be used for temporary files and logs
TMPDIR=/tmp/puppetmaster-install

# *** DO NOT EDIT BELOW THIS LINE *** #

# Create a temp folder for use in the installation process
mkdir $TMPDIR

# Log installation to file
exec >> $TMPDIR/install-$(date +"%F-%H%M%S").log 2>&1

# Download and install package to add Puppet repository
echo "***LOG:*** Downloading Puppet repository package"
wget -P $TMPDIR https://apt.puppetlabs.com/puppetlabs-release-trusty.deb
sudo dpkg -i $TMPDIR/puppetlabs-release-trusty.deb

# Refresh repositories
echo "***LOG:*** Refreshing repositories"
sudo apt-get update

# Install Puppetmaster
echo "***LOG:*** Installing Puppetmaster"
sudo apt-get -y install puppetmaster-passenger=3.7.5-1puppetlabs1

# Stop Puppetmaster service
echo "***LOG:*** Stopping Puppetmaster service"
sudo puppet resource service apache2 ensure=stopped enable=true

# Add settings to puppet.conf until automatically provided through puppet itself
echo "***LOG:*** Adding settings to puppet.conf"
sudo puppet config set server $(hostname) --section agent
sudo puppet config set dns_alt_names $(hostname) --section master

# Remove all certificates
echo "***LOG:*** Removing certificates"
sudo puppet cert clean --all

# Regenerate certificates
# Kills process after 10 seconds or it will run forever
echo "***LOG:*** Regenerating certificates"
sudo timeout 10 puppet master --no-daemonize --verbose

# Start Puppetmaster service
echo "***LOG:*** Starting Puppetmaster service"
sudo puppet resource service apache2 ensure=running enable=true

# Script complete
echo "===SCRIPT COMPLETE==="