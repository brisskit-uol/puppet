#!/bin/bash
#===========================================================================================#
# Installs PuppetDB v2																			#
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
sudo /opt/puppetlabs/bin/puppet module install puppetlabs-puppetdb

# Configure server to act as PuppetDB
# Listen on 0.0.0.0 (all)
# Install Puppetmaster
echo "***LOG:*** Applying manual Puppet manifest"
sudo /opt/puppetlabs/bin/puppet apply -e "class { 'puppetdb': listen_address => '0.0.0.0', puppetdb_version => '2.3.3-1puppetlabs1', } class { 'puppetdb::master::config': puppet_service_name => 'puppetserver', }"

# Script complete
echo "===SCRIPT COMPLETE==="