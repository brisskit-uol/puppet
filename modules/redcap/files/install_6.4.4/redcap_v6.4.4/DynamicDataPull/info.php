<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

// Disable authentication so we can use this page as an information page to anyone
define("NOAUTH", true);
// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

// Dynamic Data Pull explanation - hidden dialog text
if ($isAjax) {
	print json_encode(array('content'=>DynamicDataPull::getDdpExplanationText(), 
							'title'=>RCView::img(array('src'=>'databases_arrow.png','style'=>'vertical-align:middle;')) .
									 RCView::span(array('style'=>'vertical-align:middle;'), $lang['ws_51'] . " " . DynamicDataPull::getSourceSystemName())));
}

// If non and AJAX request, display as regular page with header/footer
else
{
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->PrintHeaderExt();
	print 	RCView::div('', 
				RCView::div(array('style'=>'font-size:18px;font-weight:bold;float:left;padding:30px 0 0;'), 
					RCView::img(array('src'=>'databases_arrow.png','class'=>'imgfix2')) .
					$lang['ws_28']
				) .
				RCView::div(array('style'=>'text-align:right;float:right;'), 
					RCView::img(array('src'=>'redcaplogo_small.gif'))
				) .
				RCView::div(array('class'=>'clear'), '')
			) .
			RCView::div(array('style'=>'margin:15px 0 50px;'), DynamicDataPull::getDdpExplanationText());
	$objHtmlPage->PrintFooterExt();
}