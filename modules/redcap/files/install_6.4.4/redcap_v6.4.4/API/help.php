<?php 
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require dirname(dirname(__FILE__)) . "/Config/init_global.php";

// Initialize page display object
$objHtmlPage = new HtmlPage();
$objHtmlPage->addExternalJS(APP_PATH_JS . "base.js");
$objHtmlPage->PrintHeaderExt();

# check if the API is enabled first
if (!$api_enabled) {
	print RCView::disabledAPINote();
	exit;
}

?>

<script type="text/javascript" src="<?php echo dirname(dirname(__FILE__)) ?>/resources/js/base.js"></script>
<script type="text/javascript">
jQuery(document).ready(function() {
	//toggle the component with class msg_body
	jQuery(".heading").click(function()
	{
		var img = (jQuery(this).next(".content").css("display") == "none") ? "minus.png" : "plus.png";
		jQuery(this).css("background","#eee url(<?php echo APP_PATH_IMAGES ?>"+img+") no-repeat left center");
		jQuery(this).next(".content").slideToggle(500);
	});
	// If "view" parameter exists in query string, open that section and scroll to it
	var view = getParameterByName('view');
	if ($('#'+view).length) {
		$('#'+view).next(".content").show();
		setTimeout(function(){
			$(window).scrollTop( $('#'+view).position().top-5 );
			setTimeout(function(){
				$('#'+view).next(".content").effect('highlight',{},2500);
			},300);
		},300);
	}
});
</script>

<style>
h2 {
	font-size: 17px;
	margin: 0; 
	padding: 5px 5px 5px 25px;
	font-weight: normal;
}
div.content h3 {
	-moz-border-radius:15px 15px 15px 15px;
	background:none repeat scroll 0 0 #E7F3F8;
	clear:both;
	color:#447AA4;
	display:block;
	font-size:15px;
	margin-top:12px;
	padding:5px 10px;
	text-shadow:0 1px #FFFFFF;
}
div.section h3 {
	margin-left: 15px;
}
div.content p, div.content pre {
	padding-left: 20px;
}
div.content ul {
	list-style-type: disc;
	margin:10px 0 10px 20px;
	line-height:20px;
}
div.content li ul {
	margin: 0;
}
div.subitem {
	margin-left: 15px;
}
div.section div {
	padding-left: 20px;
}
.heading {
	background: #eee url('<?php echo APP_PATH_IMAGES ?>plus.png') no-repeat left center;
	position: relative;
	cursor: pointer;
	width: 814px;
}
.cat {
	background: #ccc; 
	position: relative;
	font-size: 17px;
	margin: 0; 
	padding: 5px 5px 5px 10px;
	border:1px solid #555;
	font-weight: bold;
	width: 814px;
}
.content {
	background: #fafafa; 
	border:1px solid #ccc; 
	padding:0 10px 10px;
	display:none;
	width: 808px;
}
</style>

<div style="padding:0px 10px; font-family:Arial; font-size:13px; width: 800px;">
	<h1 style="margin:20px 0 0px;font-size:24px;color:#800000">REDCap API Documentation</h1>
	
	<p style="padding-bottom:10px;">
		<a style="text-decoration:underline;" href="<?php echo APP_PATH_WEBROOT_PARENT ?>">Return to REDCap</a> &nbsp;|&nbsp;
		<a style="text-decoration:underline;" href="<?php echo str_replace("?", "", $_SERVER['PHP_SELF']) ?>?logout=1">Logout</a>
	</p>

	<p style="padding-bottom:10px;">
		This page may be used for obtaining information for constructing or modifying REDCap API requests. Click any of the
		categories in the table below to expand its section.<br><br>
		<b>What is an API?</b><br>
		The acronym "API" stands for "Application Programming Interface". An API is just a defined way for a program to accomplish a task, 
		usually retrieving or modifying data. In REDCap's case, we provide an API method for both exporting and importing data in and out
		of REDCap (more functionality will come in the future). 
		Once we expand the REDCap API's abilities to a more comprehensive feature set in the future, programmers may then use the REDCap API to 
		make applications, websites, widgets, and other projects that interact with REDCap. 
		Programs talk to the REDCap API over HTTP, the same protocol that your browser uses to visit and interact with web pages.
	</p>
	
	<div class="cat">
		Basic API Info:
	</div>
	
	<div class="heading">
		<h2>Obtaining Tokens for API Requests</h2>
	</div>
	<div class="content">
		<p>
		In order to use the REDCap API for a given REDCap project, you must first be given a token that is specific to your username for that
		particular project. Rather than using username/passwords, the REDCap API uses tokens as a means of secure authentication, in which a token
		must be included in every API request. 
		Please note that each user will have a different token for each REDCap project to which they have access.
		Thus, multiple tokens will be required for making API requests to multiple projects.
		<br><br>
		To obtain an API token for a project, navigate to that project, then click the API link in the Applications sidebar.
		On that page you will be able to request an API token for the project from your REDCap administrator, and that page
		will also display your API token if one has already been assigned. If you do not see a link for the API page on your
		project's left-hand menu, then someone must first give you API privileges within the project (via the project's 
		User Rights page).
		</p>
	</div>
	
	<div class="heading">
		<h2>Error Codes & Responses</h2>
	</div>
	<div id="responseCodesBox" class="content">
		<h3>HTTP Status Codes:</h3>
		<p>The REDCap API attempts to return appropriate <a href="http://en.wikipedia.org/wiki/List_of_HTTP_status_codes">HTTP status codes</a> for every request.</p>
		<ul>
			<li><strong>200 OK:</strong> Success!</li>
			<li><strong>400 Bad Request:</strong> The request was invalid. An accompanying message will explain why.</li>
			<li><strong>401 Unauthorized:</strong> API token was missing or incorrect.</li>
			<li><strong>403 Forbidden:</strong> You do not have permissions to use the API.</li>
			<li><strong>404 Not Found:</strong> The URI you requested is invalid or the resource does not exist.</li>
			<li><strong>406 Not Acceptable:</strong> The data being imported was formatted incorrectly.</li>
			<li><strong>500 Internal Server Error:</strong> The server encountered an error processing your request.</li>
			<li><strong>501 Not Implemented:</strong> The requested method is not implemented.</li>
		</ul>

		<h3>Error Messages:</h3>
		<p>When the API returns error messages, it does so in your requested format. You can specify the format you
		want using the <b>returnFormat</b> parameter. For example, an error from an XML method might look like this:</p>
<pre>
&lt;?xml version="1.0" encoding="UTF-8" ?&gt;
&lt;hash&gt;
   &lt;error&gt;detailed error message&lt;/error&gt;
&lt;/hash&gt;
</pre>
	</div>
	
	
	<div id="security" class="heading">
		<h2>API Security: Best Practices</h2>
	</div>
	<div id="responseCodesBox" class="content">
		<p>
		Although API requests to REDCap are done using SSL (HTTPS), which means that the traffic to and from the REDCap server is encrypted,
		there is still more that can be done to ensure the highest level of security when using the API. This is especially important if
		you are moving sensitive data into or out of REDCap. One thing that is *highly* recommended is for your API script/program 
		(i.e. the thing making the request to the REDCap API) to
		<b>validate the SSL certificate of the REDCap web server</b> when it makes the API request. 
		</p>
		<p>
		<b style="color:#800000;">Background on SSL certificates:</b><br>
		Web servers have SSL certificates so that their identity can be validated and thus trusted, after which secure, encrypted communication can take place with the server. 
		The reason it is important to validate the server's SSL certificate is because it is possible (although extremely rare) to be the victim of a 
		<a style="text-decoration:underline;" target="_blank" href="http://en.wikipedia.org/wiki/Man-in-the-middle_attack">Man in the Middle Attack</a> 
		even when your web traffic is secure over SSL/HTTPS. A Man in the Middle (MiM) attack can be performed by a hacker who
		impersonates the REDCap web server using a fake/invalid SSL certificate. In this way, it is possible for your API script to think
		that the hacker is really the REDCap server and thus unwittingly send your request not to REDCap but to the hacker, in which he/she can actually 
		see the contents of your request, including your API token, and then use your token to impersontate you to make
		API requests to REDCap in the future as if they were you.
		</p>
		<p>
		<b style="color:#800000;">How to prevent Man in the Middle attacks:</b><br>
		Preventing MiM attacks is pretty simple. Essentially all you need to do is to force your API script to validate the SSL certificate
		of the REDCap server. REDCap's SSL certificate will always be valid, but the hacker's fake certificate can never be determined to be valid 
		if you attempt to validate it. In many programs or programming languages that can make API requests, validating an SSL certificate 
		is often as easy as setting a flag. For example, <a href='http://curl.haxx.se/libcurl/' target='_blank' style='text-decoration:underline;'>cURL</a> 
		is popularly used by many API scripts in programming languages such as PHP, R, SAS, and many more in order to make the web request to REDCap.
		<b>So if your API script is utilizing cURL, all you need to do is modify your script so that it sets the cURL option named CURLOPT_SSL_VERIFYPEER 
		to have a value of TRUE.</b> Once done, your API script will attempt to make the API request to REDCap *only* if it can validate REDCap's SSL certificate.
		<b>Thus by adding the SSL certificate check, you have completely prevented the possibility of MiM attacks and are using the most secure form of
		communication with the REDCap API.</b> If you are not using cURL, there are plenty of other examples on the web for how to 
		validate an SSL certificate in different programming
		languages. Such examples can be found simply by Googling the name of your programming language + "verify ssl certificate" 
		(e.g., "<a href="https://www.google.com/search?q=Java+verify+ssl+certificate" target='_blank' style='text-decoration:underline;'>Java verify ssl certificate</a>"),
		which should provide you with many helpful results.
		</p>
		<p>
		<b style="color:#800000;">REMINDER:</b> Please remember that while REDCap itself has many security layers to help protect you and to ensure the highest level of security and data integrity, 
		it is *your* responsiblity to ensure that you are using the most secure methods and best practices when using the REDCap API.
		</p>
	</div>
	
	
	<div class="heading">
		<h2>API Examples</h2>
	</div>
	<div id="examplesBox" class="content">
		<p>
			The REDCap API can be called from a variety of clients using any popular client-side or web development language
			that you are able to implement (e.g .NET, Python, PHP, Java). Below you may download a ZIP file containing
			several examples of how to call the API using various software languages. The files contained therein
			may be modified however you wish.<br><br>
			<b>NOTE: The files included in the ZIP file below are *not* officially sanctioned REDCap files</b> but are merely 
			examples of how one might make API requests using specific software languages. Please be aware that the 
			files in the ZIP could potentially change from one REDCap version to the next.<br><br>
			<button class="jqbuttonmed" onclick="window.location.href='<?php echo APP_PATH_WEBROOT ?>API/redcap_api_examples.zip';">Download API examples (.zip)</button>
		</p>
	</div>
	
	<div class="heading">
		<h2>Unique Event Names</h2>
	</div>
	<div id="eventNamesBox" class="content">
		<p>
			Event names are frequently used in API calls to longitudinal projects. To obtain a list of the event
			names available to a given project, navigate to the project, then click the API link in the Applications sidebar.
		</p>
	</div>
	
	<br/>
	
	
	
	
	
	
	
	
	
	
	
	<div class="cat">
		Supported Actions:
	</div>
	
	<div class="heading">
		<h2>Export Records</h2>
	</div>
	<div id="recordExportBox" class="content">
		<h3>Description</h3>
		<p>This function allows you to export a set of records for a project</p>
		<p><b>Note about export rights:</b> Please be aware that Data Export user rights will be applied to this API request. 
		For example, if you have 
		"No Access" data export rights in the project, then the API data export will fail and return an error. And if you have
		"De-Identified" or "Remove all tagged Identifier fields" data export rights, then some data fields *might* be removed and
		filtered out of the data set returned from the API. To make sure that no data is unnecessarily filtered out of your API request,
		you should have "Full Data Set" export rights in the project.</p>
		<p style="color:#555;">Note regarding Parent/Child projects: While this *does* work for Parent/Child projects, please note that it will export the 
		Parent's records or the Child's records separately rather than together. So if accessing the Parent via API, it will only
		return the Parent's records, and if accessing the Child via API, it will only return the Child's records.</p>

		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>record</div>
				</li>
				<li><strong>format</strong><br/><div>csv, json, xml [default]</div></li>
				<li><strong>type:</strong>
					<ul>
						<li style="margin-left:30px;">flat - output as one record per row [default]</li>
						<li style="margin-left:30px;">eav - output as one data point per row
							<ul>
								<li style="margin-left:30px;">Non-longitudinal: Will have the fields - record*, field_name, value</li>
								<li style="margin-left:30px;">Longitudinal: Will have the fields - record*, field_name, value, redcap_event_name</li>
							</ul>
						</li>
					</ul>
					<p>* "record" refers to the record ID for the project</p>
				</li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>records</strong><br/> <div>an array of record names specifying specific records you wish to pull (by default, all records are pulled)</div></li>
				<li><strong>fields</strong><br/> <div>an array of field names specifying specific fields you wish to pull (by default, all fields are pulled)</div></li>
				<li><strong>forms</strong><br/> <div>an array of form names you wish to pull records for.  If the form name has a space in 
				it, replace the space with an underscore (by default, all records are pulled)</div></li>
				<li><strong>events</strong><br/> <div>an array of unique event names that you wish to pull records for - only for longitudinal projects</div></li>
				<li><strong>rawOrLabel</strong><br/> <div>raw [default], label - export the raw coded values or labels for the options of multiple choice fields</div></li>
				<li><strong>rawOrLabelHeaders</strong><br/> <div>raw [default], label - (for "csv" format "flat" type only) for the CSV headers, export the variable/field names (raw) or the field labels (label)</div></li>
				<li><strong>exportCheckboxLabel</strong><br/> <div>true, false [default] - specifies the format of checkbox field values specifically
					when exporting the data as labels (i.e., when rawOrLabel=label) in flat format (i.e., when type=flat).
					When exporting labels, by default (without providing the exportCheckboxLabel flag or if exportCheckboxLabel=false), 
					all checkboxes will either have a value "Checked" if they are checked or "Unchecked" if not checked.
					But if exportCheckboxLabel is set to true, it will instead export the checkbox value as the checkbox option's label (e.g., "Choice 1") if checked
					or it will be blank/empty (no value) if not checked. If rawOrLabel=false or if type=eav, then the exportCheckboxLabel flag is ignored.
					(The exportCheckboxLabel parameter is ignored for type=eav because "eav" type always exports checkboxes differently anyway, in which checkboxes
					are exported with their true variable name (whereas the "flat" type exports them as variable___code format), and
					another difference is that "eav" type *always* exports checkbox values as the choice label for labels export, 
					or as 0 or 1 (if unchecked or checked, respectively) for raw export.)
				<li><strong>returnFormat</strong><br/> <div>csv, json, xml - specifies the format of error messages.
				If you do not pass in this flag, it will select the default format for you passed based on the
				"format" flag you passed in or if no format flag was passed in, it will default to "xml".</div></li>
				<li><strong>exportSurveyFields</strong><br/> <div>true, false [default] - specifies whether or not to export the survey identifier
				field (e.g., "redcap_survey_identifier") or survey timestamp fields (e.g., instrument+"_timestamp") when surveys 
				are utilized in the project. If you do not pass in this flag, it will default to "false". If set to "true",
				it will return the redcap_survey_identifier field and also the survey timestamp field for a particular survey 
				when at least one field from that survey is being exported. NOTE: If the survey identifier
				field or survey timestamp fields are imported via API data import, they will simply be ignored since they are not
				real fields in the project but rather are pseudo-fields.</div></li>
				<li><strong>exportDataAccessGroups</strong><br/> <div>true, false [default] - specifies whether or not to export the "redcap_data_access_group"
				field when data access groups are utilized in the project. If you do not pass in this flag, it will default to "false".
				NOTE: This flag is only viable if the user whose token is being used to make the API request is *not* in a 
				data access group. If the user is in a group, then this flag will revert to its default value.</div></li>
			</ul>
		</div>
		
		<h3>Returns:</h3>
		<p>Data from the project in the format and type specified ordered by the record (primary key of project) and then by event id</p>
		
<pre>
EAV XML:
&lt;?xml version="1.0" encoding="UTF-8" ?&gt;
&lt;records&gt;
   &lt;item&gt;
      &lt;record&gt;&lt;/record&gt;
      &lt;field_name&gt;&lt;/field_name&gt;
      &lt;value&gt;&lt;/value&gt;
      &lt;redcap_event_name&gt;&lt;/redcap_event_name&gt;
   &lt;/item&gt;
&lt;/records&gt;

Flat XML:
&lt;?xml version="1.0" encoding="UTF-8" ?&gt;
&lt;records&gt;
   &lt;item&gt;
      each data point as an element
      ...
   &lt;/item&gt;
&lt;/records&gt;
</pre>
	</div>
	
	<div class="heading">
		<h2>Export Reports</h2>
	</div>
	<div id="recordExportBox" class="content">
		<h3>Description</h3>
		<p>This function allows you to export the data set of a report created on a project's "Data Exports, Reports, and Stats" page.</p>
		<p><b>Note about export rights:</b> Please be aware that Data Export user rights will be applied to this API request. For example, if you have 
		"No Access" data export rights in the project, then the API report export will fail and return an error. And if you have
		"De-Identified" or "Remove all tagged Identifier fields" data export rights, then some data fields *might* be removed and
		filtered out of the data set returned from the API. To make sure that no data is unnecessarily filtered out of your API request,
		you should have "Full Data Set" export rights in the project.</p>
		<p>Also, please note the the "Export Reports" method does *not* make use of the "type" (flat/eav) parameter, which can be used in
		the "Export Records" method. All data for the "Export Reports" method is thus exported
		in flat format. If the "type" parameter is supplied in the API request, it will be ignored.</p>

		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>report</div></li>
				<li><strong>report_id</strong><br/><div>the report ID number provided next to the report name on the report list page</div></li>
				<li><strong>format</strong><br/><div>csv, json, xml [default]</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>returnFormat</strong><br/> <div>csv, json, xml - specifies the format of error messages.
				If you do not pass in this flag, it will select the default format for you passed based on the
				"format" flag you passed in or if no format flag was passed in, it will default to "xml".</div></li>
				<li><strong>rawOrLabel</strong><br/> <div>raw [default], label - export the raw coded values or labels for the options of multiple choice fields</div></li>
				<li><strong>rawOrLabelHeaders</strong><br/> <div>raw [default], label - (for "csv" format "flat" type only) for the CSV headers, export the variable/field names (raw) or the field labels (label)</div></li>
				<li><strong>exportCheckboxLabel</strong><br/> <div>true, false [default] - specifies the format of checkbox field values specifically
					when exporting the data as labels (i.e., when rawOrLabel=label).
					When exporting labels, by default (without providing the exportCheckboxLabel flag or if exportCheckboxLabel=false), 
					all checkboxes will either have a value "Checked" if they are checked or "Unchecked" if not checked.
					But if exportCheckboxLabel is set to true, it will instead export the checkbox value as the checkbox option's label (e.g., "Choice 1") if checked
					or it will be blank/empty (no value) if not checked. If rawOrLabel=false, then the exportCheckboxLabel flag is ignored.
			</ul>
		</div>
		
		<h3>Returns:</h3>
		<p>Data from the project in the format and type specified ordered by the record (primary key of project) and then by event id</p>
	</div>
	
	<div class="heading">
		<h2>Import Records</h2>
	</div>
	<div id="recordImportBox" class="content">
		<h3>Description</h3>
		<p>This function allows you to import a set of records for a project</p>
		<p style="color:#555;">NOTE: While this *does* work for Parent/Child projects, please note that it will import the records
		only to the specific project you are accessing via the API (i.e. the Parent or the Child project) and not to both.
		Additionally, if importing new records into a Child project, those records must also already exist in the Parent project, or
		else the API will return an error.</p>

		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>record</div>
				</li>
				<li><strong>format</strong><br/><div>csv, json, xml [default]</div></li>
				<li><strong>type</strong>
					<ul>
						<li style="margin-left:30px;">flat - input as one record per row [default]</li>
						<li style="margin-left:30px;">eav - input as one data point per row
							<ul>
								<li style="margin-left:30px;">Non-longitudinal: Must have the fields - record*, field_name, value</li>
								<li style="margin-left:30px;">Longitudinal: Must have the fields - record*, field_name, value, redcap_event_name**</li>
							</ul>
						</li>
					</ul>
					<div>
					<br/>* "record" refers to the record ID for the project<br/>
					** Event name is the unique name for an event, not the event label
					</div>
				</li>
                <li><strong>overwriteBehavior</strong>
                    <ul>
                        <li style="margin-left:30px;">normal - blank/empty values will be ignored [default]</li>
                        <li style="margin-left:30px;">overwrite - blank/empty values are valid and will overwrite data</li>
                    </ul>
				<li><strong>data</strong><br/> 
				<div>the formatted data to be imported</div>
				<div style="font-size:11px;padding:5px;margin:5px 0 5px 20px;border:1px solid #ddd;">
					NOTE: When importing data in EAV type format, please be aware that checkbox fields must have their field_name
					listed as variable+"___"+optionCode and its value as either "0" or "1" (unchecked or checked, respectively).
					For example, for a checkbox field with variable name "icecream", it would be imported as EAV with the field_name as "icecream___4" having 
					a value of "1" in order to set the option coded with "4" (which might be "Chocolate") as "checked".
				</div>

<pre>
EAV XML:
&lt;?xml version="1.0" encoding="UTF-8" ?&gt;
&lt;records&gt;
   &lt;item&gt;
      &lt;record&gt;&lt;/record&gt;
      &lt;field_name&gt;&lt;/field_name&gt;
      &lt;value&gt;&lt;/value&gt;
      &lt;redcap_event_name&gt;&lt;/redcap_event_name&gt;
   &lt;/item&gt;
&lt;/records&gt;

Flat XML:
&lt;?xml version="1.0" encoding="UTF-8" ?&gt;
&lt;records&gt;
   &lt;item&gt;
      each data point as an element
      ...
   &lt;/item&gt;
&lt;/records&gt;
</pre></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>dateFormat</strong><br/> <div>MDY, DMY, YMD [default] - the format of values being imported for dates or datetime fields 
				(understood with M representing "month", D as "day", and Y as "year") - 
				NOTE: The default format is Y-M-D (with <b>dashes</b>), while MDY and DMY values should always be formatted as M/D/Y or D/M/Y (with <b>slashes</b>), respectively.</div></li>
				<li><strong>returnContent</strong><br/> <div>ids - a list of all record IDs that were imported, count [default] - the number of records imported, nothing - no text, just the HTTP status code</div></li>
				<li><strong>returnFormat</strong><br/> <div>csv, json, xml - specifies the format of returned content or error messages.
				If you do not pass in this flag, it will select the default format for you based on the
				"format" flag you passed in or if no format flag was passed in, it will default to "xml".</div></li>
			</ul>
		</div>
		
		<h3>Returns:</h3>
		<p>the content specified by <b>returnContent</b></p>
	</div>
	
	<div class="heading">
		<h2>Export Metadata (i.e. Data Dictionary)</h2>
	</div>
	<div id="metadataExportBox" class="content">
		<h3>Description</h3>
		<p>This function allows you to export the metadata for a project</p>
		<p style="color:#555;">NOTE: While this *does* work for Parent/Child projects, please note that it will export the 
		Parent's metadata or the Child's metadata separately rather than together. So if accessing the Parent via API, it will only
		return the Parent's metadata, and if accessing the Child via API, it will only return the Child's metadata.</p>

		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>metadata</div>
				</li>
				<li><strong>format</strong><br/><div>csv, json, xml [default]</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>fields</strong><br/> <div>an array of field names specifying specific fields you wish to pull (by default, all metadata is pulled)</div></li>
				<li><strong>forms</strong><br/> <div>
					an array of form names specifying specific data collection instruments for which you wish 
					to pull metadata (by default, all metadata is pulled). NOTE: These "forms" are not the form label values that are seen on the webpages, 
					but instead they are the unique form names seen in Column B of the data dictionary.
				</div></li>
				<li><strong>returnFormat</strong><br/> <div>csv, json, xml - specifies the format of error messages.
				If you do not pass in this flag, it will select the default format for you passed based on the
				"format" flag you passed in or if no format flag was passed in, it will default to "xml".</div></li>
			</ul>
		</div>
		
		<h3>Returns:</h3>
		<p>Metadata from the project (i.e. Data Dictionary values) in the format specified ordered by the field order</p>
	</div>
	
	<div class="heading">
		<h2>Export List of Export Field Names (i.e. variables used during exports and imports)</h2>
	</div>
	<div class="content">
		<h3>Description</h3>
		<p>This function returns a list of the export/import-specific version of field names for all fields (or for one field, if desired) 
		in a project. This is mostly used for checkbox fields because during data exports and data imports, checkbox fields 
		have a different variable name used than the exact one defined for them in the Online Designer and Data Dictionary, 
		in which *each checkbox option* gets represented as its own export field name in the following format: 
		field_name + triple underscore + converted coded value for the choice. For non-checkbox fields, 
		the export field name will be exactly the same as the original field name. 
		Note: The following field types will be automatically removed from the list returned by this method 
		since they cannot be utilized during the data import process: "calc", "file", and "descriptive". 
		</p><p>
		The list that is returned will contain the three following attributes for each field/choice: 
		"original_field_name", "choice_value", and "export_field_name".
		The choice_value attribute represents the raw coded value for a checkbox choice.
		For non-checkbox fields, the choice_value attribute will always be blank/empty.
		The export_field_name attribute represents the export/import-specific version of that field name.
		</p>

		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>exportFieldNames</div>
				</li>
				<li><strong>format</strong><br/><div>csv, json, xml [default]</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>field</strong><br/> <div>A field's variable name. By default, all fields are returned, 
				but if field is provided, then it will only the export field name(s) for that field.
				If the field name provided is invalid, it will return an error.</div></li>
				<li><strong>returnFormat</strong><br/> <div>csv, json, xml - specifies the format of error messages.
				If you do not pass in this flag, it will select the default format for you passed based on the
				"format" flag you passed in or if no format flag was passed in, it will default to "xml".
				The list that is returned will contain the original field name (variable) of the field 
				and also the export field name(s) of that field.</div></li>
			</ul>
		</div>
		
		<h3>Returns:</h3>
		<p>Returns a list of the export/import-specific version of field names for all fields (or for one field, if desired) in a project 
		in the format specified and ordered by their field order .
		The list that is returned will contain the three following attributes for each field/choice: 
		"original_field_name", "choice_value", and "export_field_name".
		The choice_value attribute represents the raw coded value for a checkbox choice.
		For non-checkbox fields, the choice_value attribute will always be blank/empty.
		The export_field_name attribute represents the export/import-specific version of that field name.</p>
	</div>
	
	<div class="heading">
		<h2>Export a File</h2>
	</div>
	<div id="fileExportBox" class="content">
		<h3>Description</h3>
		<p>This method allows you to download a document that has been attached to an individual record for a File Upload field.
		Please note that this method may also be used for Signature fields (i.e. File Upload fields with "signature" validation type).</p>
		<p><b>Note about export rights:</b> Please be aware that Data Export user rights will be applied to this API request. 
		For example, if you have 
		"No Access" data export rights in the project, then the API file export will fail and return an error. And if you have
		"De-Identified" or "Remove all tagged Identifier fields" data export rights, then the API file export will 
		fail and return an error *only if* the File Upload field has been tagged as an Identifier field. 
		To make sure that your API request does not return an error, you should have "Full Data Set" export rights in the project.</p>
		
		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>file</div></li>
				<li><strong>action</strong><br/><div>export</div></li>
				<li><strong>record</strong><br/><div>the record ID</div></li>
				<li><strong>field</strong><br/><div>the name of the field that contains the file</div></li>
				<li><strong>event</strong><br/><div>the unique event name - only for longitudinal projects</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>returnFormat</strong><br/> <div>csv, json, xml - specifies the format of error messages.
				If you do not pass in this flag, it will select the default format for you passed based on the
				"format" flag you passed in or if no format flag was passed in, it will default to "xml".</div></li>
			</ul>
		</div>
		
		<h3>Returns:</h3>
		<p>the contents of the file</p>
		<p>
			<strong>How to obtain the filename of the file:</strong><br/>
			The MIME type of the file, along with the name of the file and its extension, can be found in the header of
			the returned response. Thus in order to determine these attributes of the file being exported, you will need to 
			parse the response header. Example: <br/>
			content-type = application/vnd.openxmlformats-officedocument.wordprocessingml.document; name="FILE_NAME.docx"
		</p>
	</div>
	
	<div class="heading">
		<h2>Import a File</h2>
	</div>
	<div id="fileImportBox" class="content">
		<h3>Description</h3>
		<p>This method allows you to upload a document that will be attached to an individual record for a File Upload field.
		Please note that this method may NOT be used for Signature fields (i.e. File Upload fields with "signature" validation type)
		because a signature can only be captured and stored using the web interface.</p>

		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>file</div></li>
				<li><strong>action</strong><br/><div>import</div></li>
				<li><strong>record</strong><br/><div>the record ID</div></li>
				<li><strong>field</strong><br/><div>the name of the field that contains the file</div></li>
				<li><strong>event</strong><br/><div>the unique event name - only for longitudinal projects</div></li>
				<li><strong>file</strong><br/><div>the contents of the file</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>returnFormat</strong><br/> <div>csv, json, xml - specifies the format of error messages.
				If you do not pass in this flag, it will select the default format for you passed based on the
				"format" flag you passed in or if no format flag was passed in, it will default to "xml".</div></li>
			</ul>
		</div>
	</div>
	
	<div class="heading">
		<h2>Delete a File</h2>
	</div>
	<div id="fileDeleteBox" class="content">
		<h3>Description</h3>
		<p>This method allows you to remove a document that has been attached to an individual record for a File Upload field.
		Please note that this method may also be used for Signature fields (i.e. File Upload fields with "signature" validation type).</p>

		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>file</div></li>
				<li><strong>action</strong><br/><div>delete</div></li>
				<li><strong>record</strong><br/><div>the record ID</div></li>
				<li><strong>field</strong><br/><div>the name of the field that contains the file</div></li>
				<li><strong>event</strong><br/><div>the unique event name - only for longitudinal projects</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>returnFormat</strong><br/> <div>csv, json, xml - specifies the format of error messages.
				If you do not pass in this flag, it will select the default format for you passed based on the
				"format" flag you passed in or if no format flag was passed in, it will default to "xml".</div></li>
			</ul>
		</div>
	</div>

	<div class="heading">
		<h2>Export Instruments (i.e., Data Entry Forms)</h2>
	</div>
	<div id="armExportBox" class="content">
		<h3>Description</h3>
		<p>This function allows you to export a list of the data collection instruments for a project. This includes their unique instrument name
		as seen in the second column of the Data Dictionary, as well as each instrument's corresponding instrument label, which is seen
		on a project's left-hand menu when entering data. The instruments will be ordered according to their order in the project.</p>
		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>instrument</div>
				</li>
				<li><strong>format</strong><br/><div>csv, json, xml [default]</div></li>
			</ul>
		</div>
		<h3>Returns:</h3>
		<p>Instruments for the project in the format specified and will be ordered according to their order in the project.</p>
	</div>
	
	<!-- PDF -->
	<div class="heading">
		<h2>Export PDF file of Data Collection Instruments (either as blank or with data)</h2>
	</div>
	<div id="armExportBox" class="content">
		<h3>Description</h3>
		<p>This function allows you to export a PDF file for any of the following:
		1) a single data collection instrument (blank), 2) all instruments (blank),
		3) a single instrument (with data from a single record), 4) all instruments (with data from a single record), or
		5) all instruments (with data from ALL records). This is the exact same PDF file that is downloadable from a project's
		data entry form in the web interface, and additionally, the user's privileges with regard to data exports
		will be applied here just like they are when downloading the PDF in the web interface (e.g., if they have de-identified 
		data export rights, then it will remove data from certain fields in the PDF). If the user has "No Access" data export
		rights, they will not be able to use this method, and an error will be returned.</p>
		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>pdf</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>record</strong><br/><div>the record ID. The value is blank by default.
					If record is blank, it will return the PDF as blank (i.e. with no data).
					If record is provided, it will return a single instrument or all instruments containing data from that record only.</div></li>
				<li><strong>event</strong><br/><div>the unique event name - only for longitudinal projects. For a longitudinal project,
				if record is not blank and event is blank, it will return data for all events from that record.
				If record is not blank and event is not blank, it will return data only for the specified event from that record.</div></li>
				<li><strong>instrument</strong><br/><div>the unique instrument name as seen in the second column of the Data Dictionary. 
				The value is blank by default, which returns all instruments. If record is not blank and instrument is blank, 
				it will return all instruments for that record.</div></li>
				<li><strong>allRecords</strong><br/><div>[The value of this parameter does not matter and is ignored.] 
					If this parameter is passed with any value,
					it will export all instruments (and all events, if longitudinal) with data from all records.
					Note: If this parameter is passed, the parameters record, event, and instrument will be ignored.</div></li>
				<li><strong>returnFormat</strong><br/><div>csv, json, xml [default] - The returnFormat is only used with regard
				to the format of any error messages that might be returned.</div></li>
			</ul>
		</div>
		<h3>Returns:</h3>
		<p>A PDF file containing one or all data collection instruments from the project, in which the instruments will be blank (no data),
		contain data from a single record, or contain data from all records in the project, depending on the parameters passed in the 
		API request.</p>
	</div>
	
	<!-- surveyLink -->
	<div class="heading">
		<h2>Export a Survey Link for a Participant</h2>
	</div>
	<div id="" class="content">
		<h3>Description</h3>
		<p>This function returns a unique survey link (i.e., a URL) in plain text format for a specified record and
		data collection instrument (and event, if longitudinal) in a project. If the user does not have "Manage Survey Participants"
		privileges, they will not be able to use this method, and an error will be returned. If the specified
		data collection instrument has not been enabled as a survey in the project, an error will be returned.</p>
		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>surveyLink</div></li>
				<li><strong>record</strong><br/><div>the record ID. The name of the record in the project.</div></li>
				<li><strong>instrument</strong><br/><div>the unique instrument name as seen in the second column of the Data Dictionary.
				This instrument must be enabled as a survey in the project.</div></li>
				<li><strong>event</strong><br/><div>the unique event name (for longitudinal projects only).</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>returnFormat</strong><br/><div>csv, json, xml [default] - The returnFormat is only used with regard
				to the format of any error messages that might be returned.</div></li>
			</ul>
		</div>
		<h3>Returns:</h3>
		<p>Returns a unique survey link (i.e., a URL) in plain text format for the specified record and instrument (and event, if longitudinal).</p>
	</div>
	
	<!-- surveyQueueLink -->
	<div class="heading">
		<h2>Export a Survey Queue Link for a Participant</h2>
	</div>
	<div id="" class="content">
		<h3>Description</h3>
		<p>This function returns a unique Survey Queue link (i.e., a URL) in plain text format for the specified record in a project
		that is utilizing the Survey Queue feature.
		If the user does not have "Manage Survey Participants"
		privileges, they will not be able to use this method, and an error will be returned. If the Survey Queue feature
		has not been enabled in the project, an error will be returned.</p>
		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>surveyQueueLink</div></li>
				<li><strong>record</strong><br/><div>the record ID. The name of the record in the project.</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>returnFormat</strong><br/><div>csv, json, xml [default] - The returnFormat is only used with regard
				to the format of any error messages that might be returned.</div></li>
			</ul>
		</div>
		<h3>Returns:</h3>
		<p>Returns a unique Survey Queue link (i.e., a URL) in plain text format for the specified record in the project.</p>
	</div>
	
	<!-- surveyReturnCode -->
	<div class="heading">
		<h2>Export a Survey Return Code for a Participant</h2>
	</div>
	<div id="" class="content">
		<h3>Description</h3>
		<p>This function returns a unique Return Code in plain text format for a specified record and
		data collection instrument (and event, if longitudinal) in a project. If the user does not have "Manage Survey Participants"
		privileges, they will not be able to use this method, and an error will be returned. If the specified
		data collection instrument has not been enabled as a survey in the project or
		does not have the "Save & Return Later" feature enabled, an error will be returned.</p>
		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>surveyReturnCode</div></li>
				<li><strong>record</strong><br/><div>the record ID. The name of the record in the project.</div></li>
				<li><strong>instrument</strong><br/><div>the unique instrument name as seen in the second column of the Data Dictionary.
				This instrument must be enabled as a survey in the project.</div></li>
				<li><strong>event</strong><br/><div>the unique event name (for longitudinal projects only).</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>returnFormat</strong><br/><div>csv, json, xml [default] - The returnFormat is only used with regard
				to the format of any error messages that might be returned.</div></li>
			</ul>
		</div>
		<h3>Returns:</h3>
		<p>Returns a unique Return Code in plain text format for the specified record and instrument (and event, if longitudinal).</p>
	</div>
	
	<!-- participantList -->
	<div class="heading">
		<h2>Export a Survey Participant List</h2>
	</div>
	<div id="" class="content">
		<h3>Description</h3>
		<p>This function returns the list of all participants for a specific survey instrument (and for a specific event, 
		if a longitudinal project). If the user does not have "Manage Survey Participants"
		privileges, they will not be able to use this method, and an error will be returned. If the specified
		data collection instrument has not been enabled as a survey in the project, an error will be returned.</p>
		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>participantList</div></li>
				<li><strong>instrument</strong><br/><div>the unique instrument name as seen in the second column of the Data Dictionary.
				This instrument must be enabled as a survey in the project.</div></li>
				<li><strong>event</strong><br/><div>the unique event name (for longitudinal projects only).</div></li>
				<li><strong>format</strong><br/><div>csv, json, xml [default]</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>returnFormat</strong><br/> <div>csv, json, xml - specifies the format of error messages.
				If you do not pass in this flag, it will select the default format for you passed based on the
				"format" flag you passed in or if no format flag was passed in, it will default to "xml".</div></li>
			</ul>
		</div>
		<h3>Returns:</h3>
		<p>Returns the list of all participants for the specified survey instrument [and event] in the desired format. 
		The following fields are returned: email, email_occurrence, identifier, invitation_sent_status, invitation_send_time, 
		response_status, survey_access_code, survey_link. The attribute "email_occurrence" represents the current count 
		that the email address has appeared in the list (because emails can be used more than once), thus email + email_occurrence 
		represent a unique value pair. "invitation_sent_status" is "0" if an invitation has not yet been sent to the participant, 
		and is "1" if it has. "invitation_send_time" is the date/time in which the next invitation will be sent, 
		and is blank if there is no invitation that is scheduled to be sent. "response_status" represents whether 
		the participant has responded to the survey, in which its value is 0, 1, or 2 for "No response", "Partial", or "Completed", respectively. 
		Note: If an incorrect event_id or instrument name is used or if the instrument has not been enabled as a survey, then an error will be returned.
		</p>
	</div>

	<div class="heading">
		<h2>Export Events</h2>
	</div>
	<div id="eventExportBox" class="content">
		<h3>Description</h3>
		<p>This function allows you to export the events for a project</p>
		<p>NOTE: this only works for longitudinal projects</p>

		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>

		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>

		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>event</div>
				</li>
				<li><strong>format</strong><br/><div>csv, json, xml [default]</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>arms</strong><br/> <div>an array of arm numbers that you wish to pull events for (by default, all events are pulled)</div></li>
				<li><strong>returnFormat</strong><br/> <div>csv, json, xml - specifies the format of error messages.
				If you do not pass in this flag, it will select the default format for you passed based on the
				"format" flag you passed in or if no format flag was passed in, it will default to "xml".</div></li>
			</ul>
		</div>

		<h3>Returns:</h3>
		<p>Events for the project in the format specified</p>
	</div>

	<div class="heading">
		<h2>Export Arms</h2>
	</div>
	<div id="armExportBox" class="content">
		<h3>Description</h3>
		<p>This function allows you to export the Arms for a project</p>
		<p>NOTE: this only works for longitudinal projects</p>

		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>

		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>

		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>arm</div>
				</li>
				<li><strong>format</strong><br/><div>csv, json, xml [default]</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>arms</strong><br/> <div>an array of arm numbers that you wish to pull events for (by default, all events are pulled)</div></li>
				<li><strong>returnFormat</strong><br/> <div>csv, json, xml - specifies the format of error messages.
				If you do not pass in this flag, it will select the default format for you passed based on the
				"format" flag you passed in or if no format flag was passed in, it will default to "xml".</div></li>
			</ul>
		</div>

		<h3>Returns:</h3>
		<p>Arms for the project in the format specified</p>
	</div>

	<div class="heading">
		<h2>Export Instrument-Event Mappings</h2>
	</div>
	<div id="formEventExportBox" class="content">
		<h3>Description</h3>
		<p>This function allows you to export the instrument-event mappings for a project (i.e., how the data collection instruments
		are designated for certain events in a longitudinal project).</p>
		<p>NOTE: this only works for longitudinal projects</p>

		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>

		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>

		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>formEventMapping</div>
				</li>
				<li><strong>format</strong><br/><div>csv, json, xml [default]</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>arms</strong><br/> <div>an array of arm numbers that you wish to pull events for (by default, all events are pulled)</div></li>
				<li><strong>returnFormat</strong><br/> <div>csv, json, xml - specifies the format of error messages.
				If you do not pass in this flag, it will select the default format for you passed based on the
				"format" flag you passed in or if no format flag was passed in, it will default to "xml".</div></li>
			</ul>
		</div>

		<h3>Returns:</h3>
		<p>Instrument-Event mappings for the project in the format specified</p>
	</div>

	<div class="heading">
		<h2>Export Users</h2>
	</div>
	<div id="userExportBox" class="content">
		<h3>Description</h3>
		<p>This function allows you to export the users for a project</p>

		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>

		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>

		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>user</div>
				</li>
				<li><strong>format</strong><br/><div>csv, json, xml [default]</div></li>
			</ul>
			<h3>Optional</h3>
			<ul>
				<li><strong>returnFormat</strong><br/> <div>csv, json, xml - specifies the format of error messages.
				If you do not pass in this flag, it will select the default format for you passed based on the
				"format" flag you passed in or if no format flag was passed in, it will default to "xml".</div></li>
			</ul>
		</div>

		<h3>Returns:</h3>
		<p>User information for the project in the format specified</p>
		<p>
			Data Export: 0=no access, 2=De-Identified, 1=Full Data Set<br/>
			Form Rights: 0=no access, 2=read only, 1=view records/responses and edit records (survey responses are read-only),
				3 = edit survey responses
		</p>
		<p>
			(NOTE: At this time, only a limited amount of rights-related info will be exported (expiration, data access group ID, data export rights, and form-level rights.
			However, more info about a user's rights will eventually be added to the Export Users API functionality in future versions of REDCap.)
		</p>
	</div>
	
	<div class="heading">
		<h2>Export REDCap Version</h2>
	</div>
	<div id="fileExportBox" class="content">
		<h3>Description</h3>
		<p>This method returns the current REDCap version number as plain text (e.g., 4.13.18, 5.12.2, 6.0.0).</p>
		
		<h3>URL</h3>
		<p><strong><?php echo APP_PATH_WEBROOT_FULL ?>api/</strong></p>
		
		<h3>Supported Request Methods</h3>
		<p><strong>POST</strong></p>
		
		<h3>Parameters (case sensitive)</h3>
		<div class="section">
			<h3>Required</h3>
			<ul>
				<li>
					<strong>token</strong><br/>
					<div>the API token specific to your REDCap project and username (each token is unique to each user for each project)
					- See the section above for obtaining a token for a given project</div>
				</li>
				<li><strong>content</strong><br/><div>version</div></li>
			</ul>
		</div>
		
		<h3>Returns:</h3>
		<p>the current REDCap version number (three numbers delimited with two periods) as plain text - e.g., 4.13.18, 5.12.2, 6.0.0</p>
	</div>
</div>

<div class="space" style="margin:120px 0;"></div>

<?php

$objHtmlPage->PrintFooterExt();
