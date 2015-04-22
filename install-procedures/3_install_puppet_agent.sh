#!/bin/bash
#===============================================================================================#
# Installs Puppet agent																			#
#																								#
# v0.1 - 26/03/2015 - RP - 	Initial release														#
# v0.2 - 27/03/2015 - RP -	Made script more generic.											#
#							Install specific Puppet agent version.								#
#							Use Puppet commands where possible for broader OS compatibility		#
#							Allow setting of PuppetMaster (defaults to ga-puppet if left blank)	#
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
wget -P $TMPDIR https://apt.puppetlabs.com/puppetlabs-release-trusty.deb
sudo dpkg -i $TMPDIR/puppetlabs-release-trusty.deb

# Refresh repositories
echo "***LOG:*** Refreshing repositories"
sudo apt-get update

# Install Puppet
echo "***LOG:*** Installing Puppet agent"
sudo apt-get -y install puppet=3.7.5-1puppetlabs1

# Change setting to allow Puppet agent service to run
echo "***LOG:*** Changing setting to allow Puppet agent service to run"
sudo sed -i 's/START=no/START=yes/' /etc/default/puppet

# Set name of PuppetMaster or use ga-puppet if not supplied
echo "***LOG:*** Adding settings to puppet.conf"
sudo puppet config set server ${PUPPETMASTER:-ga-puppet} --section agent

# Start Puppet Agent service
echo "***LOG:*** Starting Puppet agent service"
sudo puppet resource service puppet ensure=running enable=true

# Script complete
echo "===SCRIPT COMPLETED==="