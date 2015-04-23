#!/bin/bash
#===========================================================================================#
# Installs Puppet Server																	#
#===========================================================================================#

# Set a location to be used for temporary files and logs
TMPDIR=/tmp/puppetserver-install

# *** DO NOT EDIT BELOW THIS LINE *** #

# Create a temp folder for use in the installation process
mkdir $TMPDIR

# Log installation to file
exec >> $TMPDIR/install-$(date +"%F-%H%M%S").log 2>&1

# Download and install package to add Puppet repository
echo "***LOG:*** Downloading Puppet repository package"
wget -P $TMPDIR https://apt.puppetlabs.com/puppetlabs-release-pc1-trusty.deb
sudo dpkg -i $TMPDIR/puppetlabs-release-pc1-trusty.deb

# Refresh repositories
echo "***LOG:*** Refreshing repositories"
sudo apt-get update

# Install Puppetserver
echo "***LOG:*** Installing Puppetserver"
sudo apt-get -y install puppetserver=2.0.0-1puppetlabs1

# Add settings to puppet.conf until automatically provided through puppet itself
echo "***LOG:*** Adding settings to puppet.conf"
sudo /opt/puppetlabs/bin/puppet config set server $(hostname) --section agent
sudo /opt/puppetlabs/bin/puppet config set dns_alt_names $(hostname) --section master

# Set Puppetserver JVM Heap Size to use 1GB RAM
# echo "***LOG:*** Setting Puppetserver JVM Heap Size"
# sudo sed -i 's/-Xms2g -Xmx2g/-Xms1g -Xmx1g/' /etc/default/puppetserver

# Start Puppetserver service
echo "***LOG:*** Starting Puppetserver service"
sudo /opt/puppetlabs/bin/puppet resource service puppetserver ensure=running enable=true

# Sleep for 1 minute to allow Puppetserver to start fully
echo "***LOG:*** Waiting while Puppetserver starts"
for i in {0..59}; do
	printf "%-80s\r" "Remaining: $((60-i)) seconds"
	sleep 1
	printf "%-80s\r" ""
done

# Change setting to allow Puppet agent service to run
echo "***LOG:*** Changing setting to allow Puppet agent service to run"
sudo sed -i 's/START=no/START=yes/' /etc/default/puppet

# Start Puppet agent service
echo "***LOG:*** Starting Puppet agent service"
sudo /opt/puppetlabs/bin/puppet resource service puppet ensure=running enable=true

# Script complete
echo "===SCRIPT COMPLETE==="