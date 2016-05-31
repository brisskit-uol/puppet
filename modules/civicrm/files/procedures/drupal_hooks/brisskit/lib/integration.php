<?php
include "drupal_setup.php";
include "HTTP/Request.php";
define("I2B2_WEBSERVICE","http://i2b2:8080/i2b2WS/rest/service/pdo");
define("CATISSUE_WEBSERVICE","http://catissue:8080/civi.catissue.ws/rest/service/pdo");
define("ONYX_WEBSERVICE","http://onyx:8080/onyxWS/rest/service/pdo");
define("DATA_SOURCE","BRICCS");
define("PDO_STRING","http://www.i2b2.org/xsd/hive/pdo/1.1/pdo");

function post_contact_to_server($contact=null, $activity_id, $service) {
	if (!$contact) {
		throw new Exception("No contact provided");
	}

  $req_params = array();

  switch ($service) {
    case 'catissue':
	    $req = new HTTP_Request(CATISSUE_WEBSERVICE);
	    $req->addPostData("incomingXML", contact_to_xml($contact,"CP_Prostate_Cancer",false));
      break;
    case 'i2b2':
      $req_params = array('readTimeout'=>array(5,0));
      $req = new HTTP_Request(I2B2_WEBSERVICE,$req_params);
	    $req->addPostData("incomingXML", contact_to_xml($contact));
      break;
    case 'onyx':
      $req_params = array('readTimeout'=>array(5,0));
      $req = new HTTP_Request(ONYX_WEBSERVICE,$req_params);
	    $req->addPostData("incomingXML", contact_to_xml($contact, null , true, true));
      break;
    default: 
      echo __FILE__ . ' ' . __FUNCTION__ . " Service is not valid! $service, $contact, $activity_id";
  }

	$req->addHeader("content-type", "application/xml");
	$req->setMethod(HTTP_REQUEST_METHOD_POST);
	$req->addPostData("activity_id",$activity_id);

	$sent = $req->sendRequest();
	$code = $req->getResponseCode();
	$status = $req->getResponseReason();
	
	if ($code<200 || $code>299) {
		throw new Exception($status);
	}

  if ($service == 'i2b2') {
    post_contact_to_server($contact,$activity_id, 'onyx');
  }
}

function _add_event_set($contact, &$xml,$event_id) {
	$xml->startElement("pdo:event_set");
	$xml->startElement("event");
	add_attributes($xml, 
		array(
			"download_date"=>date(DATE_ATOM),
			"import_date"=>date(DATE_ATOM),
			"sourcesystem_cd"=>DATA_SOURCE,
			"update_date"=>date(DATE_ATOM),
			"upload_id"=>"1"
		)
	);

	add_child($xml,"event_id",$event_id, 
		array(
			"source" =>DATA_SOURCE
		)
	);
	add_child($xml,"patient_id",$contact['brisskit_id'], 
		array(
			"source" =>DATA_SOURCE
		)
	);
	add_child($xml,"param","F", 
		array(
			"column" =>"ACTIVE_STATUS_CD",
			"name" => "active status"
		)
	);
	add_child($xml,"param","@", 
		array(
			"column" =>"INOUT_CD",
			"name" => ""
		)
	);
	add_child($xml,"param","@", 
		array(
			"column" =>"LOCATION_CD",
			"name" => ""
		)
	);
	add_child($xml,"param","@", 
		array(
			"column" =>"LOCATION_PATH",
			"name" => ""
		)
	);
	add_child($xml,"start_date",date(DATE_ATOM), 
		array(
		)
	);
	add_child($xml,"end_date","@", 
		array(
		)
	);
	$xml->endElement();
	$xml->endElement();
}

function _add_pid_set($contact, &$xml) {
	$xml->startElement("pdo:pid_set");
	$xml->startElement("pid");
	
	add_child($xml,"patient_id",$contact['brisskit_id'], 
		array(
			"download_date"=>date(DATE_ATOM),
			"import_date"=>date(DATE_ATOM),
			"sourcesystem_cd"=>DATA_SOURCE,
			"source" => DATA_SOURCE,
			"status" => "Active",
			"update_date"=>date(DATE_ATOM),
			"upload_id"=>"1"
		)
	);
	$xml->endElement();
	$xml->endElement();
}
function _add_eid_set($contact, &$xml,$event_id) {
	$xml->startElement("pdo:eid_set");
	$xml->startElement("eid");
	
	add_child($xml,"event_id",$event_id, 
		array(
			"download_date"=>date(DATE_ATOM),
			"import_date"=>date(DATE_ATOM),
			"source" => DATA_SOURCE,
			"sourcesystem_cd"=>DATA_SOURCE,
			"status" => "Active",
			"update_date"=>date(DATE_ATOM),
			"upload_id"=>"1"
		)
	);
	$xml->endElement();
	$xml->endElement();
}

function _add_patient_set($contact, &$xml,$study, $app) {
	$xml->startElement("pdo:patient_set");
	$xml->startElement("patient");
	if (!isset($contact['brisskit_id'])) {
		throw new Exception("BRISSkit participant ID is a required field");
	}
	add_attributes($xml, 
		array(
			"download_date"=>date(DATE_ATOM),
			"import_date"=>date(DATE_ATOM),
			"sourcesystem_cd"=>DATA_SOURCE,
			"update_date"=>date(DATE_ATOM),
			"upload_id"=>"1"
		)
	);

	add_child($xml,"patient_id",$contact['brisskit_id'], 
		array(
			"source" => DATA_SOURCE
		)
	);
	
	if ($app) {
		$names = split(' ',$contact['display_name']);
		add_child($xml,"param","2012-09-17T14:00:00.000+01:00",
		array(
			"column" => "appointment_date",
			"name" => "appointment date"
		));
		add_child($xml,"param",$names[1],
		array(
			"column" => "last_name",
			"name" => "last name"
		));
		add_child($xml,"param",$names[0],
		array(
			"column" => "first_name",
			"name" => "first name"
		));
		}
		
	add_child($xml,"param","N",
		array(
			"column" => "vital_status_cd",
			"name" => "date interpretation code"
		)
	); 
	$birthdate = null;
	$age = null;
	if (isset($contact['birth_date'])) {
		$birthdate = $contact['birth_date']."T00:00:00+01:00";
		$age = age($contact['birth_date']);
	}
	else {
		$birthdate = "0000-00-00T00:00:00+01:00";
		$age = 0;
	}
	if (isset($contact['gender_id'])) {
		$contact['sex'] = strtoupper(get_option_group_name("gender",$contact['gender_id']));
	}
	else {
		$contact['sex']="UNSPECIFIED";
	}
	
	add_child($xml,"param", $birthdate,
		array(
			"column" => "birth_date",
			"name" => "birthdate"
		)
	); 
	add_child($xml,"param",$age,
		array(
			"column" => "age_in_years_num",
			"name" => "age"
		)
	); 
	add_child($xml,"param","Unknown",
		array(
			"column" => "race_cd",
			"name" => "ethnicity"
		)
	);
	add_child($xml,"param",$contact['sex'],
		array(
			"column" => "sex_cd",
			"name" => "sex"
		)
	);
	
	if ($study) {
		add_child($xml,"study_name",$study,
		array(
			"source" => DATA_SOURCE,
		)
	);
	
	
	}
	$xml->endElement();
	$xml->endElement();
}

# 4. Builds a PDO
function contact_to_xml($contact, $study=null, $has_events=true, $app=false) {
	
	$xml = new XMLWriter();
	
	$xml->openMemory();
	$xml->startDocument('1.0', 'UTF-8');
	$xml->setIndent(true);
	$xml->startElement("pdo:patient_data");
	
	add_attributes($xml, 
		array(
			"xmlns:pdo"=>PDO_STRING
		)
	);
	
	if ($has_events) {
		$event_id = $contact['brisskit_id']."_".date(DATE_ATOM);
		_add_event_set($contact, $xml,$event_id);
		_add_pid_set($contact, $xml);
		_add_eid_set($contact, $xml,$event_id);
	}
	_add_patient_set($contact, $xml,$study, $app);
	$xml->endElement();
	$content = $xml->outputMemory();
	return $content;
}

function add_attributes(&$node, $attributes) {
	foreach($attributes as $key => $value) {
		$node->writeAttribute($key,$value);
	}
}

function add_child(&$node, $name, $value, $attributes) {
	$node->startElement($name);
	add_attributes($node,$attributes);
	$node->text($value);
	$node->endElement();
}

function add_children(&$node, $children) {
	foreach($children as $child) {
		add_child($node, $child);
	}
}
