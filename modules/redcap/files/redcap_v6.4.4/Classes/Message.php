<?php

class Message
{

    /*
    * PUBLIC PROPERTIES
    */


    // @var to string
    // @access public
    private $to;
		
	// @var toName string
    // @access public
    private $toName;
    
    // @var from string
    // @access public
    private $from;
	
	// @var fromName string
    // @access public
    private $fromName;
    
    // @var from string
    // @access public
    private $cc;
    
    // @var from string
    // @access public
    private $bcc;
    
    // @var subject string
    // @access public
    private $subject;

    // @var body string
    // @access public
    private $body;

    // @var attachments array
    // @access public
    private $attachments = array();
    
    /*
    * PUBLIC FUNCITONS
    */
    
    function getTo()            { return $this->to; }
	function getCc()           { return $this->cc; }
	function getBcc()           { return $this->bcc; }
    function getFrom() 
	{ 
		if (!strpos($this->from,'@')) $this->from = $this->from;
		return $this->from; 
	}
    function getSubject()       { return $this->subject; }
    function getBody()          { return $this->body; }

    function setTo($val)        { $this->to = $val; }
	function setToName($val) { $this->toName = $val; }
    function setCc($val)       { $this->cc = $val; } 	
    function setBcc($val)       { $this->bcc = $val; } 	
    function setFrom($val)      { $this->from = $val; }
	function setFromName($val) { $this->fromName = $val; }
    function setSubject($val)   { $this->subject = $val; }
	
	/**
	 * Attaches a file
	 * @param string $file_full_path The full file path of a file (including its file name)
	 */
    function setAttachment($file_full_path) { 
		if (!empty($file_full_path)) $this->attachments[] = $file_full_path; 
	}
    function getAttachments() { return $this->attachments; }
		
	/**
	 * Sets the content of this HTML email.
	 * @param string $val the HTML that makes up the email.
	 * @param boolean $onlyBody true if the $html parameter only contains the message body. If so,
	 * then html/body tags will be automatically added, and the message will be prepended with the
	 * standard REDCap notice.
	 */
    function setBody($val, $onlyBody=false) {
		if ($onlyBody) {
			global $lang;
			$this->body =
				"<html>\n" .
				"<body style=\"font-family:Arial;font-size:10pt;\">\n" .
				$lang['global_21'] . "<br /><br />\n" .
				$val .
				"</body>\n" .
				"</html>\n";
		}
		else $this->body = $val;	
	}	
			
    // @return void
    // @access public
    function send() 
	{
		global $from_email;
		// Check if we need to set universal FROM email address
		if (!isEmail($from_email)) $from_email = '';
		// Set subject using base-64 encode
		$subject = '=?UTF-8?B?'.substr(base64_encode($this->getSubject()), 0, 240).'?=';
		$attachments = $this->getAttachments();
		if (empty($attachments)) {
			## Email WITHOUT an attachment
			$headers  = "MIME-Version: 1.0" . PHP_EOL;
			$headers .= "From: " . ($from_email == '' ? $this->getFrom() : $from_email) . PHP_EOL;
			if ($this->getCc() != "") {
				$headers .= "Cc:"  . $this->getCc() . PHP_EOL;
			}
			if ($this->getBcc() != "") {
				$headers .= "Bcc:"  . $this->getBcc() . PHP_EOL;
			}
			$headers .= "Reply-To: " . $this->getFrom() . PHP_EOL;
			$headers .= "Return-Path: " . $this->getFrom() . PHP_EOL;
			$headers .= "Content-type: text/html; charset=utf-8" . PHP_EOL;
			$headers .= "Content-Transfer-Encoding: base64" . PHP_EOL;
			$content = rtrim(chunk_split(base64_encode($this->getBody())));	
		} else {
			## Email WITH an attachment
			// Set separator hash
			$separator = md5(time());
			// main header
			$headers  = "MIME-Version: 1.0" . PHP_EOL;
			$headers .= "From: " . ($from_email == '' ? $this->getFrom() : $from_email) . PHP_EOL;
			if ($this->getCc() != "") {
				$headers .= "Cc:"  . $this->getCc() . PHP_EOL;
			}
			if ($this->getBcc() != "") {
				$headers .= "Bcc:"  . $this->getBcc() . PHP_EOL;
			}
			$headers .= "Reply-To: " . $this->getFrom() . PHP_EOL;
			$headers .= "Return-Path: " . $this->getFrom() . PHP_EOL;
			$headers .= "Content-Type: multipart/mixed; boundary=\"".$separator."\"";
			// message
			$content .= "--".$separator.PHP_EOL;
			$content .= "Content-type: text/html; charset=utf-8" . PHP_EOL;
			$content .= "Content-Transfer-Encoding: base64" . PHP_EOL . PHP_EOL;
			$content .= rtrim(chunk_split(base64_encode($this->getBody()))).PHP_EOL;
			// attachments
			foreach ($attachments as $this_attachment) {
				$content .= PHP_EOL."--".$separator.PHP_EOL;
				$content .= "Content-Type: application/octet-stream; name=\"".basename($this_attachment)."\"".PHP_EOL; 
				$content .= "Content-Transfer-Encoding: base64".PHP_EOL;
				$content .= "Content-Disposition: attachment" . PHP_EOL . PHP_EOL;
				$content .= chunk_split(base64_encode(file_get_contents($this_attachment))) . PHP_EOL;
				$content .= "--".$separator;
			}
			$content .= "--";			
		}
		
		// Return boolean if sent or not
        return mail($this->getTo(), $subject, $content, $headers, "-f " . ($from_email == '' ? $this->getFrom() : $from_email));
    }
  
	/**
	 * Returns HTML suitable for displaying to the user if an email fails to send.
	 */
	function getSendError()
	{
		global $lang;
		return  "<div style='font-family:Arial;font-size:12px;background-color:#F5F5F5;border:1px solid #C0C0C0;padding:10px;'>
			<div style='font-weight:bold;border-bottom:1px solid #aaaaaa;color:#800000;'>
			<img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'>
			{$lang['control_center_243']}
			</div><br>
			{$lang['global_37']} <span style='color:#666;'>{$this->fromName} &#60;{$this->from}&#62;</span><br>
			{$lang['global_38']} <span style='color:#666;'>{$this->toName} &#60;{$this->to}&#62;</span><br>
			{$lang['control_center_28']} <span style='color:#666;'>{$this->subject}</span><br><br>
			{$this->body}<br>
			</div><br>";
	}
}
