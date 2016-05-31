<?php

class BK_ID {
//Make a truely unique brisskit id
//It is possible that someone else will be running this at the same time, and may
//come up with the same random number, so need to LOCK the table for READS by anyone.
//This will have the effect of serializing all calls to this and will slow the
//process down a lot!

//24/5/12
//Olly Butters

#connect to the database using the options in the civicrm mysql config file
static function connect() {

	$config = parse_ini_file ( BK_Constants::CIVICRM_MYSQL_CONFIG );
	if (!$config) {
		throw new Exception("Unable to read config file (".BK_Constants::CIVICRM_MYSQL_CONFIG.".");
	}
	$host = $config['host'];
	$user = $config['user'];
	$pass = $config['pass'];
	
	//Connect to admin DB.
	$con = mysql_connect($host,$user,$pass);
	if(!$con)
	{
		throw new Exception(mysql_error());
	}
	return $con;
}

#get the brisskit institutional prefix from the brisskit config file
static function get_inst_prefix() {
	#get brisskit config

/*
	$config = parse_ini_file ( BK_Constants::BRISSKIT_CONFIG );
*/

  // 4th Dec 2015
  //
  // For now we'll hardcode this, until we can work out a reasonable place to record the inst_prefix, rather than in a config file under /etc
  //
  
  $config['inst_prefix'] = 'GEN';
	
  	
	if (!$config) {
		throw new Exception("Unable to read config file (".BK_Constants::BRISSKIT_CONFIG.".");
	}

	#get prefix from config
	if (isset($config['inst_prefix'])) {
		$inst_prefix = $config['inst_prefix'];
	}
	else {
		throw new Exception("No institutional 'inst_prefix' value set in config file (".BK_Constants::BRISSKIT_CONFIG.").");
	}
	return $inst_prefix;
}


#create brisskit ID
static function make_brisskit_id()
{
	# 5.1. Required info (db credentials and institutional prefix) is pulled from config files and a brisskit admin db connection is made 
	$inst_prefix = self::get_inst_prefix();
	
	//$verbose=FALSE;
	$verbose=TRUE;

	//Check only letters and numbers
	if(!ctype_alnum($inst_prefix))
	{
    	throw new Exception("Prefix contains non-alphanumeric characters: $inst_prefix");
	}


	//Pick the range of the brisskit number - must be an int!
	//100000000-999999999 seems reasonable (thats 9 digits).
	$minimum_number=100000000;
	$maximum_number=999999999;
	$range = $maximum_number - $minimum_number;


	//LOCK the table so other people cant even READ it.
	mysql_query("LOCK TABLES existing_brisskit_id WRITE");
	if(mysql_error())
	{
		throw new Exception("Error LOCKing tables: ".mysql_error());
	}

	//return "starting make brisskit id";
	//sleep(10);

	//In a while loop as we may pick an existing number.
	$counter=0;
	$ok=FALSE;
	while(!$ok)
	{
		# 5.2. A BRISSkit ID is randomly generated and compared to those previously stored 
		//Make a random number
		$proposed_bid=rand($minimum_number,$maximum_number);

		//Add the prefix
		$proposed_bid = $inst_prefix."-".$proposed_bid;

		if($verbose)
		{
                        BK_Utils::audit("make_brisskit_id Proposed ID = ".$proposed_bid);

		}

		
		//See if it already exists
		$query="SELECT * FROM existing_brisskit_id WHERE bid=\"".$proposed_bid."\"";
		$result=mysql_query($query);
		
		if(mysql_error())
		{
			throw new Exception(mysql_error());

		}

		$num_rows=mysql_num_rows($result);
		
		//If it doesnt exist lets use it.
		if($num_rows==0)
		{
			$ok=TRUE;
		}
		
		//Make sure there are some potential numbers left!
		if($counter>$range)
		{
			throw new Exception("No unique numbers left in range".$minimum_number." - ".$maximum_number);
		}
		$counter++;
	}


	//Add to DB if ok
	$query = "INSERT INTO existing_brisskit_id VALUES (\"".$proposed_bid."\")";
	if($verbose)
	{
                BK_Utils::audit("make_brisskit_id query $query");
	}
	mysql_query($query);
	if(mysql_error())
	{
		throw new Exception(mysql_error());
	}

	//Check that it inserted ok.
	if(mysql_affected_rows()!=1)
	{
		throw new Exception(mysql_affected_rows()." rows in the INSERT.");
	}

	//UNLOCK the table.
	mysql_query("UNLOCK TABLES");

	//If this has failed then bail out.
	if(mysql_error())
	{
		throw new Exception(mysql_error());
	}

	//Everything shoudl have worked then :)
	return $proposed_bid;
}
}
?>
