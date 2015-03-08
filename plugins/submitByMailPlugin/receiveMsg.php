<?php
/*
 * receiveMsg.php
 *
 * @category  phplist
 * @package   submitByMail Plugin
 * @author    Arnold V. Lesikar
 * @copyright 2014 Arnold V. Lesikar
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 * 
 * receiveMsg.php is part of the submitByMail Plugin.
 * The submitByMail plugin is free software: you can redistribute it and/or modify
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
 */
 
if (!defined('PHPLISTINIT')) die(); ## avoid pages being loaded directly

$sbm = $GLOBALS["plugins"]['submitByMail'];
if ($GLOBALS["commandline"]) {
	$str = file_get_contents('php://stdin');
	$sbm->pipeSubmission = 1;
}
	
try {
	$uncoded = new decodedMessage ($str);

	$fromadr = $uncoded->message['from']['address'];
	$isSuperSender = false;

	foreach ($check as $theCheck) {
		switch ($theCheck) {
	
			case 'authSender': 	
			 				checkSender($uncoded);
							break;
						
			case 'checkTo':		
							checkTo($uncoded);
							break;
							
			case 'owner':		
							checkOwner($uncoded);
							break;
							
			case 'mailSubmit':	
							checkMailOK ($uncoded);
							break;
							
			case 'pipeOK':		
							checkPipe ($uncoded);
							break;
			
			case 'attachOK': 	
							checkAttach ($uncoded);
							break;
							
			case 'inlineOK':		
							checkInline ($uncoded);
							break;		
		}
		
} catch (Exception $e) {
	$fromadr = ($fromadr? $fromadr: getFromAdr ($str));	// No address from decoder -- try and get it ourselves
	if (!$fromadr) {	// No recognizable from - cannot notify sender
		logEvent($e);
		exit;
	}
	$subject = ($uncoded->message['subject']? $uncoded->message['subject']: getSubject($str));
	$msg = "The message you submitted with the subject '" . $subject . "'cannot be sent out because "
	$completion = array (
		'inlineType' => 'it tries to include an inline part other than text, html, or an image.',
	 	'msgType' => 'it is not an html or text message.',
		'noList' => 'no list was found to be currently associated with this address.',
		'twoLists' => 'the message is addressed to two or more lists.',
		'badAmin' => 'the sender of this message is not an administrator.',
		'badOwner' =>  'the sender of this message is not the owner of the list to which this message wass submitted.',
		'noMail' => 'mail submission is not enabled for the list associated with this address.',
		'badPipe' => 'mail submission by a command line pipe is not allowed for the list associated with this address.',
		'badAttach' => 'list messages are not allowed to contain attachments.',
		'badInline' => 'the total size of the inline images exceeds the limit of ' . getConfig("ImageAttachLimit") . 'kB.',
		);
	$msg .= ($completion[$e]? $completion[$e] : $e);	// Some error messages are defined by the mime parser
	sendMail($fromAdr, 'Submission of list message FAILED', $msg);
	exit;
}

if ($uncoded->listParams['confirm'])
	escrow the message and notify the sender
else
	save the message
	if (($uncoded->listParams['queue'])
		set status to submitted
	else 
		set status to draft

// Return the email address found in the From: line of a message
function getFromAdr ($str) {
	$pat = "/[a-z0-9._+\-\']+@[a-z0-9.\-]+\.[a-z]{2,}/i";
	preg_match('/^from:.*$/im', $str, $match);
	preg_match ($pat, $match[0], $match2);
	return $match2[0];
}

function getSubject($str) {
	preg_match('/^subject:(.*)$/im', $str, $match);
	return trim($match[1]);
}

function escrow($uncoded) {
}

?>
