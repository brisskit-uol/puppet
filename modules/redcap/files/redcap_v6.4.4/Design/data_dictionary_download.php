<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/
	
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';


# MAKE DATA DICTIONARY EXCEL FILE 

//If coming from project revision history page and referencing rev_id, then use metadata archive table
if (isset($_GET['rev_id']) && is_numeric($_GET['rev_id']))
{
	$metadata_where = "and pr_id = " . $_GET['rev_id'];
	$metadata_table = "redcap_metadata_archive";
}
//If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
else
{
	$metadata_where = "";
	$metadata_table = isset($_GET['draft']) ? "redcap_metadata_temp" : "redcap_metadata";
}

// Open connection to create file in memory and write to it
$fp = fopen('php://memory', "x+");

// Add headers
$ddheaders = array( "Variable / Field Name", "Form Name", "Section Header", "Field Type", "Field Label", 
					"Choices, Calculations, OR Slider Labels", "Field Note", "Text Validation Type OR Show Slider Number", "Text Validation Min", 
					"Text Validation Max", "Identifier?", "Branching Logic (Show field only if...)", "Required Field?", 
					"Custom Alignment", "Question Number (surveys only)", "Matrix Group Name", "Matrix Ranking?");
fputcsv($fp, $ddheaders);

//Pull the metadata from table to export into CSV file
$select = "SELECT field_name, form_name, element_preceding_header, element_type, element_label, element_enum, element_note,
		   element_validation_type, element_validation_min, element_validation_max, field_phi, branching_logic, field_req, 
		   custom_alignment, question_num, grid_name, grid_rank
		   FROM $metadata_table WHERE project_id = $project_id AND field_name != concat(form_name,'_complete') $metadata_where
		   ORDER BY field_order";
$export = db_query($select);
$num_rows = db_num_rows($export);
while ($row = db_fetch_assoc($export)) 
{
	// Loop through all columns for last-minute formatting
	$line = array();
	foreach ($row as $this_field=>$value) 
	{ 
		// Unescape the values
		$value = html_entity_decode($value, ENT_QUOTES);
		//Remove \n in Select Choices and replace with |
		if ($this_field == "element_enum") {
			$value = str_replace("\\n", "|", trim($value));
		//Change Subject Identifier, Required Field and Matrix Ranking values of '1' to 'y'
		} elseif ($this_field == "field_phi" || $this_field == "field_req" || $this_field == "grid_rank") {
			$value = trim($value) == "1" ? "y" : "";
		//Change to user-friendly/non-legacy values for Validation
		} elseif ($this_field == "element_validation_type") {					
			if (in_array($value, array("date","datetime","datetime_seconds"))) {
				$value .= "_ymd";
			} elseif (in_array($value, array("int","float"))) {
				$value = str_replace(array("int","float"), array("integer","number"), $value);
			}
		//Change to user-friendly values for Validation
		} elseif ($this_field == "element_type") {
			$value = str_replace(array("select","textarea"), array("dropdown","notes"), $value);
		} elseif ($this_field == "element_preceding_header") {
			// If Section Header is only whitespace (to server as a placeholder), then wrap single space in quotes to preserve it.
			if (substr($value, 0, 1) == " " && trim($value) == "") $value = ' ';
		}
		if ($value != "") {
			// Fix any formatting
			$value = ($this_field == "branching_logic" || $this_field == "element_enum") ? html_entity_decode($value, ENT_QUOTES) : label_decode($value);
			// $value = ($this_field == "branching_logic") ? html_entity_decode($value, ENT_QUOTES) : label_decode($value);
			// $value = html_entity_decode($value, ENT_QUOTES);
			$value = str_replace(array("&#39;","&#039;"), array("'","'"), $value);
			// For Japanese encoding
			if ($project_encoding == 'japanese_sjis' && mb_detect_encoding($value) == "UTF-8") {
				$value = mb_convert_encoding($value, "SJIS", "UTF-8");
			} 
		}
		// Add value to line
		$line[] = $value;
	}
	// Write this line to CSV file
	fputcsv($fp, $line);
}
// Create filename
$filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 30)."_DataDictionary_".date("Y-m-d");
// If a revision number given, then append to filename
if (isset($_GET['revnum']) && is_numeric($_GET['revnum'])) {
	$filename .= "_rev" . $_GET['revnum'];
}
// Output to file
header('Pragma: anytextexeptno-cache', true);
header("Content-type: application/csv");
header("Content-Disposition: attachment; filename=$filename.csv");
// Open file for reading and output to user
fseek($fp, 0);
$file_contents = stream_get_contents($fp);
// Output the file contents
print addBOMtoUTF8($file_contents);
// Logging
log_event("",$metadata_table,"MANAGE",$project_id,"project_id = $project_id","Download data dictionary");
		