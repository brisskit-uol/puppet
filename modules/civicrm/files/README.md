#=======================================================================#
# Procedures and Artifacts for installing the Civicrm web application   #
#=======================================================================#

There are a number of possible approaches to using this project.

(1) A maven zipped artifact can be produced and used (see below)
(2) The git version of the project can be used
    Both of the above use the civicrm_install.sh script in the bin directory.
(3) Some of the distinctive puppet artifacts may be archived in the
    puppet-artifacts directory.

But this is not be the whole story...
  (a) It is assumed that Mysql has been installed locally
  (b) There are other pre-requisites installed using appt-get
      (For details, read the civicrm_install.sh script in the bin directory)
  (c) There is a wget into sourceforge to acquire the specific version of civicrm.
      This needs addressing. The patching needs to be taken into account.
      Plus the frequency of updating.

#======================================================================#
# Using Maven:                                                         #
# Inspect the POM and the production-bin.xml in the assembly directory #
#======================================================================#
To build a local zip artifact, the default install invocation is sufficient...
mvn clean install

To deploy to the remote BRISSKit repos, we need to develop a script,
mvn-remote-deploy.sh, which can manage automatic tunnelling.

Special note. The above strategy is not working as at 4/5/2015.
The tunnelling is not working as expected.
