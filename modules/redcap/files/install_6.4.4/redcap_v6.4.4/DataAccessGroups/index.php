<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

	
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// TABS
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";

print  "<div style='text-align:right;max-width:700px;'>
			<img src='" . APP_PATH_IMAGES . "video_small.png' class='imgfix'> 
			<a onclick=\"window.open('".CONSORTIUM_WEBSITE."videoplayer.php?video=data_access_groups02.flv&referer=".SERVER_NAME."&title=Data Access Groups','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');\" href=\"javascript:;\" style=\"font-size:12px;text-decoration:underline;font-weight:normal;\">".$lang['data_access_groups_07']."</a>
		</div>";

print  "<p>{$lang['data_access_groups_01']}</p>";


//Data Access Groups (only show to users that are NOT in a group)
if ($user_rights['group_id'] == "") {
		
	print  "<p>{$lang['data_access_groups_02']} {$lang['data_access_groups_ajax_40']}</p>
			<div id='group_table'>";
	include APP_PATH_DOCROOT . "DataAccessGroups/data_access_groups_ajax.php";
	print  "</div>";
			
} else {

	//User does not have permission to be here because user is in a data access group.
	print  "<div class='red' style='margin-top:30px;'>
				<b>{$lang['data_access_groups_03']}</b><br>{$lang['data_access_groups_04']}
			</div>";
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	exit;
	
}
?>

<script type="text/javascript">
// JavaScript Document to allow inline editing
//
// Function  : createXMLHttpRequest
// Purpose   : create XMLHttpRequest()
// Parameters: none
// Output    : true or false
// --------------------------------------------------------------------------

function createXMLHttpRequest() {
};
createXMLHttpRequest.prototype.initial = function() {
	try {
		// Mozilla / Safari
		this._xh = new XMLHttpRequest();
	} catch (e) {
		// Explorer
		var _ieModule = new Array(
		'MSXML2.XMLHTTP.5.0',
		'MSXML2.XMLHTTP.4.0',
		'MSXML2.XMLHTTP.3.0',
		'MSXML2.XMLHTTP',
		'Microsoft.XMLHTTP'
		);
		var success = false;
		for (var i=0;i < _ieModule.length && !success; i++) {
			try {
				this._xh = new ActiveXObject(_ieModule[i]);
				success = true;
			} catch (e) {
				// Catch an exception
			}
		}
		if ( !success ) {
			// If not successfull
			return false;
		}
		return true;
	}
}

createXMLHttpRequest.prototype.taken= function() {
	stateActual = this._xh.readyState;
	return (stateActual && (stateActual < 4));
}

createXMLHttpRequest.prototype.processing = function() {
	if (this._xh.readyState == 4 && this._xh.status == 200) {
		this.process = true;
	}
}

createXMLHttpRequest.prototype.toSend = function(urlget,data) {
	if (!this._xh) {
		this.initial();
	}
	if (!this.taken()) {
		this._xh.open("GET",urlget,false);
		this._xh.send(data);
		if (this._xh.readyState == 4 && this._xh.status == 200) {
			return this._xh.responseText;
		}
		
	}
	return false;
}

var formVars = "";
var changing = false;

//
// Function  : fieldEnter
// Purpose   : get the inline data using XMLHttpRequest () 
// Parameters: none
// Output    : true or false
// --------------------------------------------------------------------------
function fieldEnter(field,evt,idfld) {
	evt = (evt) ? evt : window.event;
	if (evt.keyCode == 13 && field.value!="") {
		elem = document.getElementById( idfld );
		remote = new createXMLHttpRequest;
		nt = remote.toSend(app_path_webroot+"DataAccessGroups/data_access_groups_ajax.php?pid="+pid+"&action=rename&group_id=" +escape(elem.id)+ "&item="+escape(field.value),"");
		//remove glow
		noLight(elem);
		elem.innerHTML = nt;
		changing = false;
		return false;
	} else {
		return true;
	}


}

//
// Function  : fieldBlur
// Purpose   : get the inline data using XMLHttpRequest () 
// Parameters: none
// Output    : true or false
// --------------------------------------------------------------------------
function fieldBlur(field,idfld) {
	elem = document.getElementById( idfld );
	remote = new createXMLHttpRequest;
	if (field.value!="") {		
		nt = remote.toSend(app_path_webroot+"DataAccessGroups/data_access_groups_ajax.php?pid="+pid+"&action=rename&group_id=" +escape(elem.id)+ "&item="+escape(field.value),"");
	} else {
		
		//nt = remote.toSend(urlBase + "?fieldname=" +escape(elem.id)+ "&content="+escape("click here to add")+"&"+formVars,"");
		//elem.style.color = "#A0A0A0";
		//elem.style.fontSize = "10px";
	}
	elem.innerHTML = nt;
	changing = false;
	// Now do a follow-up ajax request to reload entire DAG table (in case any unique group names changeed)
	$.get(app_path_webroot+"DataAccessGroups/data_access_groups_ajax.php?pid="+pid,{ }, function(data){
		$('#group_table').html(data);
		editbox_init();
		initWidgets();
	});
	return false;
}

//
// Function  : change
// Purpose   : Change span to input field
// Parameters: id of span
// Output    : true or false
// --------------------------------------------------------------------------

function change(actual) {
	if(!changing){
		width = widthEl(actual.id) + 20;
		height =heightEl(actual.id) + 2;
		if(width < 100) width = 150;
		//if(height < 40)
			actual.innerHTML = "<input id=\""+ actual.id +"_field\" class=\"x-form-text x-form-field\" style=\"width:95%;\" maxlength=\"100\" type=\"text\" value=\"" + actual.innerHTML.replace(/\"/g,'&quot;') + "\" onkeypress=\"return fieldEnter(this,event,'" + actual.id + "')\" onfocus=\"highLight(this);\" onblur=\"noLight(this); return fieldBlur(this,'" + actual.id + "');\" />"
							 + "<div style='font-weight:normal;color:#777;font-size:11px;'><?php print cleanHtml2($lang['data_access_groups_ajax_38']) ?></div>";
		//else
			//actual.innerHTML = "<textarea name=\"textarea\" id=\""+ actual.id +"_field\" style=\"width: "+width+"px; height: "+height+"px;\" onfocus=\"highLight(this);\" onblur=\"noLight(this); return fieldBlur(this,'" + actual.id + "');\">" + actual.innerHTML + "</textarea>";
	
		changing = true;
	}	
	
	actual.firstChild.focus();
}

//
// Function  : editbox
// Purpose   : find all span tags with class editText and id as fieldname parsed to update script. add onclick function
// Parameters: none
// Output    : none
// --------------------------------------------------------------------------
function editbox_init(){
	// Set onclick
	$('.editText').click(function(){
		change(this);
	});
	// Add floating pencil next to group name when mouseover
	$('.editText').hover(function(){
		$(this).css('background','#fafafa url("'+app_path_images+'pencil_small3.png") no-repeat right');
	},
	function(){
		$(this).css('background','');
	});

}

//
// Function  : addEvent
// Purpose   : crossbrowser load function
// Parameters: current window,  type of event capture, type of element, 
// Output    : none
// --------------------------------------------------------------------------
function addEvent(elm, evType, fn, useCapture)
{
  if (elm.addEventListener){
    elm.addEventListener(evType, fn, useCapture);
    return true;
  } else if (elm.attachEvent){
    var r = elm.attachEvent("on"+evType, fn);
    return r;
  } else {
    alert("Please upgrade your browser to use full functionality on this page");
  }
}

//
// Function  : widthEl
// Purpose   : get width of text element
// Parameters: span id
// Output    : width
// --------------------------------------------------------------------------
function widthEl(span){

	if (document.layers){
	  w=document.layers[span].clip.width;
	} else if (document.all && !document.getElementById){
	  w=document.all[span].offsetWidth;
	} else if(document.getElementById){
	  w=document.getElementById(span).offsetWidth;
	}
return w;
}

//
// Function  : heightEl
// Purpose   : get height of text element
// Parameters: span id
// Output    : height
// --------------------------------------------------------------------------
function heightEl(span){

	if (document.layers){
	  h=document.layers[span].clip.height;
	} else if (document.all && !document.getElementById){
	  h=document.all[span].offsetHeight;
	} else if(document.getElementById){
	  h=document.getElementById(span).offsetHeight;
	}
return h;
}

//
// Function  : highlight
// Purpose   : set span to be highlighted
// Parameters: span id
// Output    : none
// --------------------------------------------------------------------------
function highLight(span){
	//span.parentNode.style.border = "2px solid #D1FDCD";
	//span.parentNode.style.padding = "0";
	span.style.border = "1px solid #54CE43";          
}

//
// Function  : nolight
// Purpose   : set span to be non highlighted
// Parameters: span id
// Output    : none
// --------------------------------------------------------------------------
function noLight(span){
	//span.parentNode.style.border = "0px";
	//span.parentNode.style.padding = "0px";
	span.style.border = "0px";  
}

//
// Function  : highlight
// Purpose   : sets post/get vars for updated
// Parameters: span id
// Output    : none
// --------------------------------------------------------------------------
function setVarsForm(vars){
	formVars  = vars;
}

// Execute label editing on page load
$(function(){
	addEvent(window, "load", editbox_init);
	editbox_init();
});

function hidedagMsg() {
	setTimeout(function(){
		$('.dagMsg').slideToggle('normal');
	},200);
	setTimeout(function(){
		$('.dagMsg').slideToggle(1200);
	},4000);
}

function del_msg(this_group_id,this_group_name) {
	var delDagAjax = function(){
		$.get(app_path_webroot+'DataAccessGroups/data_access_groups_ajax.php?pid='+pid+'&action=delete&item='+this_group_id,{ },function(data){
			$('#group_table').html(data);
			editbox_init();
			initWidgets();
			hidedagMsg();
		});
	};
	simpleDialog('<?php echo cleanHtml($lang['rights_184']) ?> \"<b>'+this_group_name+'</b>\"<?php echo cleanHtml($lang['questionmark']) ?>','<?php echo cleanHtml($lang['rights_185']) ?>',null,null,null,'<?php echo cleanHtml($lang['global_53']) ?>',delDagAjax,'<?php echo cleanHtml($lang['global_19']) ?>');
}
function add_group() {
	if ($('#new_group').val() != '<?php echo cleanHtml($lang['rights_179']) ?>') {
		$('#new_group').prop('disabled',true);
		$('#new_group_button').prop('disabled',true);
		$('#progress_img').css('visibility','visible');		
		$.get(app_path_webroot+'DataAccessGroups/data_access_groups_ajax.php?pid='+pid+'&action=add&item='+$('#new_group').val(),{ },function(data){
			$('#group_table').html(data);
			editbox_init();
			initWidgets();
			hidedagMsg();
		});
	}
}
function add_user_to_group() {
	if ($('#group_users').val() == '') {
		simpleDialog('<?php echo cleanHtml($lang['data_access_groups_ajax_17']) ?>',null,null,null,"$('#group_users').focus();");
	} else {
		$('#groups').prop('disabled',true);
		$('#group_users').prop('disabled',true);
		$('#progress_img_user').css('visibility','visible');	
		$.get(app_path_webroot+'DataAccessGroups/data_access_groups_ajax.php?pid='+pid+'&action=add_user&user='+$('#group_users').val()+'&group_id='+$('#groups').val(),{ },function(data){
			$('#group_table').html(data);
			editbox_init();
			initWidgets();
			hidedagMsg();
		});
	}
}
function select_group(user) {
	$.get(app_path_webroot+'DataAccessGroups/data_access_groups_ajax.php?pid='+pid+'&action=select_group&user='+user,{ },function(data){
		$('#groups').val(data);
	});
}

</script>


<?php	

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
