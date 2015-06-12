<?php
/**
 * 
 * @category  phplist
 * @package   submitByMail Plugin
 * @author    Arnold V. Lesikar
 * @copyright 2014 Arnold V. Lesikar
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 *
 * This file is a part of the submitByMailPlugin for Phplist
 *
 * The submitByMailPlugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 * For more information about how to use this plugin, see
 * http://resources.phplist.com/plugins/submitByMail .
 * 
 */

if (!defined('PHPLISTINIT')) die(); ## avoid pages being loaded directly

$sbm = $GLOBALS['plugins']['submitByMailPlugin'];

if (isset($_GET['mtk'])) {
	$res = '<title>Confirm Message</title>';
	$res .= $GLOBALS['pagedata']["header"];
	$res .= "<h3>Confirm Message</h3>";
	$token = $_GET['mtk'];
	$query = sprintf("select file_name, sender, subject, listid, listsadressed from %s where token='%s'", $sbm->tables['escrow'], $token);
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
		
		$res .= '<div class="sbmcfm">';
		if ((count($sbm->alids) == 1) && ($doqueue = $sbm->doQueueMsg ($sbm->lid))) {
			if ($qerr = $sbm->queueMsg($msg)) {
				$msgid = $sbm->saveDraft($msg);
				$res = '<p class ="sbmcfm">Your message with the subject \'' . $sbm->subj . 
								"' was not queued because of the following error(s):<br \> $qerr"
								. '</p><p class ="sbmcfm">The message has been saved as a draft.</p>';
				logEvent("A message with the subject '" . $sbm->subj ."' received but not queued because of a problem.");
			} else {
				$res .='<p class ="sbmcfm">Your message with the subject \'' . $sbm->subj . "' was received and has been queued for distribution.</p>";
				logEvent("A message with the subject '" . $sbm->subj ."' was received and queued.");
			}
		} else {	
			$msgid = $sbm->saveDraft($msg);
			$res .='<p class ="sbmcfm">Your message with the subject \'' . $sbm->subj . "' was received and has been saved as a draft.</p>";
			logEvent("A message with the subject '" . $sbm->subj ."' was received and and saved as a draft.");
		}	
		$res .='</div>';		
		unlink($fn);
		$query = sprintf ("delete from %s where token = '%s'", $sbm->tables['escrow'], $token);
    	Sql_Query($query);
	} else
		$res .='<div  style="color:red !important; margin-top:30px;"><p style="font-size:14px; line-height:1.6;">Message not found.<br />You either have a typo in the URL or the hold time for the message has expired.</p></div>';
	$res .= "<p>".$GLOBALS["PoweredBy"].'</p>';
	$res .= $GLOBALS['pagedata']["footer"]; 
	print($res);
	$style = '<style>p.sbmcfm {font-size:14px !important; line-height:1.6;}</style>';
	print ($style);
} else
	FileNotFound();

?>
