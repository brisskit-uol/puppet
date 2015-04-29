<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

// Disable authentication so this page can be used as general documentation
define("NOAUTH", true);
include_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
	

// If an AJAX request, return as JSON encoded title and content for a dialog pop-up
if ($isAjax)
{
	print json_encode(array('content'=>Piping::renderPipingInstructions(), 
		  'title'=>RCView::img(array('src'=>'pipe.png','style'=>'vertical-align:middle;')) .
				   RCView::span(array('style'=>'vertical-align:middle;'), $lang['design_456'])));
}

// If non and AJAX request, display as regular page with header/footer
else
{
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->PrintHeaderExt();
	print 	RCView::div('', 
				RCView::div(array('style'=>'font-size:18px;font-weight:bold;float:left;padding:30px 0 0;'), 
					RCView::img(array('src'=>'pipe.png','class'=>'imgfix2')) .
					$lang['design_456']
				) .
				RCView::div(array('style'=>'text-align:right;float:right;'), 
					RCView::img(array('src'=>'redcaplogo_small.gif'))
				) .
				RCView::div(array('class'=>'clear'), '')
			) .
			RCView::div(array('style'=>'margin:15px 0 50px;'), Piping::renderPipingInstructions());
	$objHtmlPage->PrintFooterExt();
}