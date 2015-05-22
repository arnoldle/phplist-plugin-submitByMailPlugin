<?php
if (!defined('PHPLISTINIT')) die(); ## avoid pages being loaded directly

$sbm = $GLOBALS['plugins']['submitByMailPlugin'];

if (isset($_GET['mtk'])) {
	print("<h2>Confirm Message</h2>");
	$token = $_GET['mtk'];
	$query = sprintf("select file_name, sender, subject, listid, listsaddressed from %s where token='%s'", $sbm->tables['escrow'], $token);
	// Don't need to check for expiration of the message, since an expired message will
	// already have been removed as the plugin was constructed in order to load this page
	$result=Sql_Query($query);
	if (Sql_Num_Rows($result)) {
		$msgdata = Sql_Fetch_Assoc($result);
		$sbm->subj = $msgdata['subject'];
		$sbm->sender = $msgdata['sender'];
		$sbm->lid = $msgdata['listid'];
		$sbm->alids =unserialize($msgdata['listsaddressed']);
		$fn = $sbm->escrowdir . $msgdata['file_name'];
		$msg = file_get_contents($sbm->escrowdir . $msgdata['file_name']);
		
		print('<div style="font-size:14px;width:700px; margin-top:30px;">');
		if ((count($sbm->alids) == 1) && ($doqueue = $sbm->doQueueMsg ($sbm->lid))) {
			if ($qerr = $sbm->queueMsg($msg)) {
				$msgid = $sbm->saveDraft($msg);
				print('<p>Your message with the subject \'' . $sbm->subj . 
								"' was not queued because of the following error(s):<br \> $qerr"
								. "</p><p>The message has been saved as a draft.</p>");
				print ('<p>'. $sbm->outsideLinkButton("send&id=$msgid", 'Edit Message') .'</p>');
				logEvent("A message with the subject '" . $sbm->subj ."' received but not queued because of a problem.");
			} else {
				print('<p>Your message with the subject \'' . $sbm->subj . "' was received and has been queued for distribution.</p>");
				logEvent("A message with the subject '" . $sbm->subj ."' was received and queued.");
			}
		} else {	
			$msgid = $sbm->saveDraft($msg);
			print ('<p>Your message with the subject \'' . $sbm->subj . "' was received and has been saved as a draft.</p>");
			print ('<p>'. $sbm->outsideLinkButton("send&id=$msgid", 'Edit Message') .'</p>');
			logEvent("A message with the subject '" . $sbm->subj ."' was received and and saved as a draft.");
		}	
		print('</div>');		
		unlink($fn);
		$query = sprintf ("delete from %s where token = '%s'", $sbm->tables['escrow'], $token);
    	Sql_Query($query);
	} else
		print ('<div style="font-size:14px !important;margin-top:30px;color:red !important"><p>Message not found.</p><p>You either have a typo in the URL or the hold time for the message has expired.</p></div>');
} else
	print ('<p>Page not found</p>');
	

?>
