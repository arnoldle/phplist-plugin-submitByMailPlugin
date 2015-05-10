<?php
if (!defined('PHPLISTINIT')) die(); ## avoid pages being loaded directly

$sbm = $GLOBALS['plugins']['submitByMailPlugin'];

// Make sure the button links to the edit page instead of trying to link inside the plugin
function myButton($name, $desc) {
	$str = PageLinkButton($name, $desc);
	$str = str_replace("pi=submitByMailPlugin&", '', $str);
	return $str;
}

$error = false;
if (isset($_GET['tk'])) {
	$token = $_GET['tk'];
	$query = sprintf("select file_name, sender, subject, listid from %s where token='%s'", $sbm->tables['escrow'], $token);
	// Don't need to check for expiration of the message, since an expired message will
	// already have been removed as the plugin was constructed in order to load this page
	if ($result=Sql_Query($query)) {
		$msgdata = Sql_Fetch_Assoc($result);
		$sbm->subj = $msgdata['subject'];
		$sbm->sender = $msgdata['sender'];
		$sbm->lid = $msgdata['listid'];
		$fn = $sbm->escrowdir . $msgdata['file_name'];
		$msg = file_get_contents($sbm->escrowdir . $msgdata['file_name']);
		if ($doqueue = $sbm->doQueueMsg ($this->lid)) {
			if ($qerr = $sbm->queueMsg($msg)) $mid = $sbm->saveDraft($msg);
		} else			
			$mid = $this->saveDraft($msg);
		unlink($fn);
		$query = sprintf ("delete from %s where token = '%s'", $this->tables['escrow'], $token);
    	Sql_Query($query);
	} else
		$error = 2;
} else
	$error = 1;
if (!$error) {
	if ($doqueue && $qerr) {
		print ('<p style="font-size:14px;margin-top:100px;">Cannot queue message with subject: \''
			. $sbm->subj . "'. The message has been saved as a draft.");
			print ('<p>'. myButton("send&id=$mid", 'Edit Message') .'</p>');
	} else if ($doqueue && !$qerr)
		print ('<p style="font-size:14px;margin-top:100px;">The message with subject: \''
			. $sbm->subj . "' has been queued for distribution to the list '" . listName($sbm->lid) . "'.</p>");
	else {
		print ('<p style="font-size:14px;margin-top:100px;">The message with subject: \''
			. $sbm->subj . "' has been saved as a draft for later editing.</p>");
		print ('<p>' . myButton("send&id=$mid", 'Edit Message') . '</p>');
    }	
} else {
	if ($error == 1)
		print ('<p style="font-size:14px;margin-top:100px;">Correct page not found</p>');
	else
		print ('<p style="font-size:14px;margin-top:100px;">Message not found. You either have a typo in the URL or the hold time for the message has expired.</p>');
}

?>
