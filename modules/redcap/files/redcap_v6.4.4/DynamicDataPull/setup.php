<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Header
include APP_PATH_DOCROOT  . 'ProjectGeneral/header.php';
renderPageTitle("<img src='".APP_PATH_IMAGES."databases_arrow.png'> " . $lang['ws_51'] . " " . $DDP->getSourceSystemName());


// CSS & Javascript
?>
<style type="text/css">
#ext_field_tree { border:1px solid #ccc; padding:15px; width:650px; background-color:#f3f3f3; }
#ext_field_tree .rc_ext_cat { margin: 0 0 3px 50px; display:none; text-indent:-23px; }
#ext_field_tree .rc_ext_subcat { margin: 0 0 3px 30px; display:none; text-indent:-23px; }
#ext_field_tree .rc_ext_cat_name, #ext_field_tree .rc_ext_subcat_name { margin: 2px 0 0; cursor:pointer;cursor:hand; }
#ext_field_tree .rc_ext_cat_name { font-weight:bold; }
#src_fld_map_table .data { background-image:none }
</style>
<script type="text/javascript">
$(function(){
	// Hide "saved" message after displaying for a bit
	if ($('.msgrt').length) {
		setTimeout(function(){
			$('.msgrt').hide('slow');
		}, 3000);
	}
	// Trigger to remove "blue" class on row
	$('.mapfld').change(function(){
		// Add/remove blue class
		if ($(this).val() == '') {	
			$(this).parents('tr:first').children('td').addClass('blue');		
		} else {
			$(this).parents('tr:first').children('td').removeClass('blue');	
		}
	});
});
</script>
<?php

// Render page
print $DDP->renderSetupPage();

// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';