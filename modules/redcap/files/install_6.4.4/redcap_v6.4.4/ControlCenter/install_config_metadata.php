<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

// Build array for rendering form
$elements   = array();
	
$elements[] = array("rr_type"=>"header", "css_element_class"=>"header", "value"=>$lang['system_config_01']);
$elements[] = array("rr_type"=>"select", "name"=>"superusers_only_create_project", 
					"label"=>"{$lang['system_config_12']}<br><span class='configsub'>{$lang['system_config_13']}</span>",
					"enum"=>"1, {$lang['system_config_14']} \\n 0, {$lang['system_config_15']}");
$elements[] = array("rr_type"=>"select", "name"=>"superusers_only_move_to_prod", 
					"label"=>"{$lang['system_config_16']}<br><span class='configsub'>{$lang['system_config_17']}</span>",
					"enum"=>"1, {$lang['system_config_18']} \\n 0, {$lang['system_config_146']}");
$elements[] = array("rr_type"=>"hidden", "name"=>"auto_report_stats");
$elements[] = array("rr_type"=>"text", "name"=>"redcap_base_url", "value"=>'', "style"=>"width:100%;",
					"label"=>"{$lang['pub_105']}<br><span class='configsub'>({$lang['pub_110']})</span>");
$elements[] = array("rr_type"=>"select", "name"=>"enable_url_shortener", 
					"label"=>"{$lang['system_config_132']}",
					"enum"=>"0, {$lang['global_23']} \\n 1, {$lang['system_config_27']}", 
					"note"=>"<span style='color:#666;'>{$lang['system_config_238']}</span>"); 
					
// DATE AND NUMBER FORMAT SETTINGS
$install_datetime_options = $install_decimal_options = $install_thousands_sep_options = array();
foreach (DateTimeRC::getDatetimeDisplayFormatOptions() as $val=>$option) {
	$install_datetime_options[] = "$val, $option";
}
foreach (User::getNumberDecimalFormatOptions() as $val=>$option) {
	$val = str_replace(',', '&#44;', $val); // Escape due to parsing issues
	$install_decimal_options[] = "$val, $option";
}
foreach (User::getNumberThousandsSeparatorOptions() as $val=>$option) {
	if ($val == ' ') $val = '';
	$val = str_replace(',', '&#44;', $val); // Escape due to parsing issues
	$val = str_replace("'", '&#39;', $val); // Escape due to parsing issues
	$install_thousands_sep_options[] = "$val, $option";
}
$elements[] = array("rr_type"=>"header", "css_element_class"=>"header", "value"=>$lang['system_config_290']);
$elements[] = array("rr_type"=>"select", "name"=>"default_datetime_format", "label"=>$lang['user_82'],
					"enum"=>implode(" \\n ", $install_datetime_options),
					"note"=>"<div style='color:#800000;font-size:10px;'>(e.g., 12/31/2004 22:57 or 31/12/2004 10:57pm)</div>"); 
$elements[] = array("rr_type"=>"select", "name"=>"default_number_format_decimal", "label"=>$lang['user_83'],
					"enum"=>implode(" \\n ", $install_decimal_options),
					"note"=>"<div style='color:#800000;font-size:10px;'>(e.g., 3.14 or 3,14)</div>"); 
$elements[] = array("rr_type"=>"select", "name"=>"default_number_format_thousands_sep", "label"=>$lang['user_84'],
					"enum"=>implode(" \\n ", $install_thousands_sep_options),
					"note"=>"<div style='color:#800000;font-size:10px;'>(e.g., 1,000,000 or 1.000.000 or 1 000 000)</div>"); 
// HOME PAGE VALUES
$elements[] = array("rr_type"=>"header", "css_element_class"=>"header", "value"=>$lang['system_config_72']);
$elements[] = array("rr_type"=>"text", "name"=>"homepage_contact", 
					"label"=>$lang['system_config_77']);
$elements[] = array("rr_type"=>"text", "name"=>"homepage_contact_email", 
					"label"=>$lang['system_config_78'],
					"onblur"=>"redcap_validate(this,'0','','hard','email')");

$elements[] = array("rr_type"=>"header", "css_element_class"=>"header", "value"=>$lang['system_config_88']);
$elements[] = array("rr_type"=>"text", "name"=>"project_contact_name", 
					"label"=>"{$lang['system_config_91']}<br><span class='configsub'>({$lang['system_config_92']})</span>");
$elements[] = array("rr_type"=>"text", "name"=>"project_contact_email", 
					"label"=>"{$lang['system_config_93']} {$lang['system_config_91']}",
					"onblur"=>"redcap_validate(this,'','','hard','email')");						
$elements[] = array("rr_type"=>"text", "name"=>"project_contact_prod_changes_name", 
					"label"=>"{$lang['system_config_94']}<br><span class='configsub'>({$lang['system_config_95']})</span>");
$elements[] = array("rr_type"=>"text", "name"=>"project_contact_prod_changes_email", 
					"label"=>"{$lang['system_config_93']} {$lang['system_config_96']}",
					"onblur"=>"redcap_validate(this,'','','hard','email')");
$elements[] = array("rr_type"=>"text", "name"=>"institution", "style"=>"width:75%;",
					"label"=>$lang['system_config_97']);	
$elements[] = array("rr_type"=>"text", "name"=>"site_org_type", "style"=>"width:75%;",
					"label"=>$lang['system_config_98']);
					
$elements[] = array("rr_type"=>"submit", "name"=>"", 
					"label"=>"", "value"=>"Save Changes");
