# Puppet #

### Provision VM in Azure ###
* Provision from gallery: _Ubuntu 14.04 LTS_
* Machine name: _ga-puppet_
* Machine size: _A1_ (to be performance tested and altered as necessary)
* Username: _azureuser_
* Password: from password database (until managed by Puppet itself)
* Cloud Service: _ga-brisskit_
* Virtual Network: _ga-brisskit_
* Virtual Network Subnet: _Subnet-1 (10.10.10.0/24)_
* Storage Account: _gabrisskit_
* Endpoints
	* SSH: _10022 => 22_

### Install Puppet Master ###
* Upload _1_install_puppetmaster.sh_ to server
* Set execute permissions on _1_install_puppetmaster.sh_ using `chmod u+x 1_install_puppetmaster.sh`
* Run _./1_install_puppetmaster.sh_

### Install PuppetDB ###
* Upload _2_install_puppetdb.sh_ to server
* Set execute permissions on _2_install_puppetdb.sh_ using `chmod u+x 2_install_puppetdb.sh`
* Run _./2_install_puppetdb.sh_

### Install Puppet agent ###
* Upload _3_install_puppet_agent.sh_ to server
* Set execute permissions on _3_install_puppet_agent.sh_ using `chmod u+x 3_install_puppet_agent.sh`
* Run _./3_install_puppet_agent.sh_