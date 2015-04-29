<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

include 'header.php';

// Class to render user's project list
require APP_PATH_CLASSES . "RenderProjectList.php";
?>

<h3 style="margin-top: 0;"><?php echo RCView::img(array('src'=>'folders_stack.png', 'class'=>'imgfix1')) . $lang['control_center_110'] ?></h3>

<p style='margin-top:0;'><?php echo $lang['control_center_20'] ?></p>


<script type="text/javascript">
$(function(){	
	// Auto-suggest for adding new users
	$('#user_search').ui_autocomplete({
		source: app_path_webroot+"UserRights/search_user.php?searchEmail=1",
		minLength: 2,
		delay: 150,
		select: function( event, ui ) {
			$(this).val(ui.item.value);
			$('#user_search_btn').click();
			return false;
		}
	})
	.data('ui_autocomplete')._renderItem = function( ul, item ) {
		return $("<li></li>")
			.data("item", item)
			.append("<a>"+item.label+"</a>")
			.appendTo(ul);
	};
});
</script>
<?php
// If user is selected, then display a link to view their user information
$userInfoLink = "";
if (isset($_GET['userid']) && $_GET['userid'] != "")
{
	$userInfoArray = User::getUserInfo($_GET['userid']);
	if ($userInfoArray !== false) {
		// Link to user info page
		$userInfoLink =  "<button style=';color:#800000;font-size:11px;' onclick=\"window.location.href='".APP_PATH_WEBROOT."ControlCenter/view_users.php?username={$_GET['userid']}';\">{$lang['system_config_241']} \"{$_GET['userid']}\"</button>";
	} else {
		// Not a valid username
		$userInfoLink = RCView::span(array('class'=>'yellow'),
							RCView::img(array('src'=>'exclamation_orange.png', 'class'=>'imgfix')) .
							$lang['control_center_441']
						);
	}
}
// Create "add new user" text box
$usernameTextboxJsFocus = "if ($(this).val() == '".cleanHtml($lang['control_center_4428'])."') { 
							$(this).val(''); $(this).css('color','#000'); 
						  }";
$usernameTextboxJsBlur = "$(this).val( trim($(this).val()) );
						  if ($(this).val() == '') { 
							$(this).val('".cleanHtml($lang['control_center_4428'])."'); $(this).css('color','#999'); 
						  }";
$usernameTextboxValue  = (isset($_GET['userid']) ? $_GET['userid'] : $lang['control_center_4428']);
$usernameTextboxStyle  = (isset($_GET['userid']) ? '' : 'color:#999;');
$usernameTextbox = RCView::text(array('id'=>'user_search', 'class'=>'x-form-text x-form-field', 'maxlength'=>'255', 
					'style'=>'width:350px;'.$usernameTextboxStyle, 'value'=>$usernameTextboxValue,
					'onkeydown'=>"if(event.keyCode==13) $('#user_search_btn').click();",
					'onfocus'=>$usernameTextboxJsFocus,'onblur'=>$usernameTextboxJsBlur));
print 	RCView::div(array('style'=>'margin:10px 0 20px;'),
			RCView::b($lang['control_center_437']) . "<br>" . $usernameTextbox .
			RCView::button(array('id'=>'user_search_btn', 'style'=>'margin-left:4px;', 'onclick'=>"
					var us_ob = $('#user_search');
					us_ob.trigger('focus');
					us_ob.val( trim(us_ob.val()) );				
					var userParts = us_ob.val().split(' ');
					us_ob.val( trim(userParts[0]) );
					if (us_ob.val().length > 0) {
						if (!chk_username(us_ob)) {
							return alertbad(us_ob,'".cleanHtml($lang['control_center_45'])."');
						}
						$('#user_search').prop('disabled',true);
						$(this).prop('disabled',true);
						window.location.href = app_path_webroot+page+'?userid='+us_ob.val();
					}"), $lang['global_84']) .
			// View All button
			RCView::span(array('style'=>'margin:0 5px 0 2px;color:#888;font-size:11px;'), "&mdash; ".$lang['global_46']. " &mdash;") . 
			RCView::button(array('onclick'=>"window.location.href = app_path_webroot+page+'?view_all=1';"), $lang['control_center_4380']) .
			RCView::div(array('style'=>'text-align:right;padding:3px 0;margin-right:100px;'), $userInfoLink)
		);

// Display listing of all existing projects
$projects = new RenderProjectList ();
$projects->renderprojects("control_center");

// Hidden "undelete project" div
print RCView::simpleDialog("", $lang['control_center_378'], 'undelete_project_dialog');

include 'footer.php';
