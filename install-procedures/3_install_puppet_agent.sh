#!/bin/bash
#===============================================================================================#
# Installs Puppet agent																			#
#===============================================================================================#

# Set a location to be used for temporary files and logs
TMPDIR=/tmp/puppetagent-install

# Set the name of the Puppet Master to connect to
PUPPETMASTER=

# *** DO NOT EDIT BELOW THIS LINE *** #

# Create a temp folder for use in the installation process
mkdir $TMPDIR

# Log installation to file
exec >> $TMPDIR/install-$(date +"%F-%H%M%S").log 2>&1

# Download and install package to add Puppet repository
echo "***LOG:*** Downloading Puppet repository package"
wget -P $TMPDIR http://apt.puppetlabs.com/puppetlabs-release-pc1-trusty.deb
sudo dpkg -i $TMPDIR/puppetlabs-release-pc1-trusty.deb

# Refresh repositories
echo "***LOG:*** Refreshing repositories"
sudo apt-get update

# Install Puppet
echo "***LOG:*** Installing Puppet agent"
sudo apt-get -y install puppet-agent=1.0.0-1trusty

# Change setting to allow Puppet agent service to run
echo "***LOG:*** Changing setting to allow Puppet agent service to run"
sudo sed -i 's/START=no/START=yes/' /etc/default/puppet

# Set name of PuppetMaster or use ga-puppet if not supplied
echo "***LOG:*** Adding settings to puppet.conf"
sudo /opt/puppetlabs/bin/puppet config set server ${PUPPETMASTER:-ga-puppet} --section agent

# Start Puppet Agent service
echo "***LOG:*** Starting Puppet agent service"
sudo /opt/puppetlabs/bin/puppet resource service puppet ensure=running enable=true

# Script complete
echo "===SCRIPT COMPLETED==="