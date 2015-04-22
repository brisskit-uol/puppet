#!/bin/bash
#===========================================================================================#
# Installs PuppetDB																			#
#																							#
# v0.1 - 26/03/2015 - RP - 	Initial release													#
# v0.2 - 27/03/2015 - RP -	Made script more generic.										#
#							Install specific PuppetDB version.								#
#							Use Puppet commands where possible for broader OS compatibility	#
#===========================================================================================#

# Set a location to be used for temporary files and logs
TMPDIR=/tmp/puppetdb-install

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

# Install PuppetDB
echo "***LOG:*** Installing PuppetDB"
sudo puppet module install puppetlabs-puppetdb

# Configure server to act as PuppetDB
# Listen on 0.0.0.0 (all). Assumes puppetmaster-passenger is used and the puppetmaster service is apache2
# Install Puppetmaster
echo "***LOG:*** Applying manual Puppet manifest"
sudo puppet apply -e "class { 'puppetdb': listen_address => '0.0.0.0', puppetdb_version => '2.3.1-1puppetlabs1', } class { 'puppetdb::master::config': puppet_service_name => 'apache2', }"

# Script complete
echo "===SCRIPT COMPLETE==="