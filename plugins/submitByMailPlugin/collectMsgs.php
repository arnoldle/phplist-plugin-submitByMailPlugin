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
 
if (!defined('PHPLISTINIT')) die(); // avoid pages being loaded directly

$sbm = $GLOBALS['plugins']['submitByMailPlugin'];

$popAccts = $sbm->getPopData();

if ($GLOBALS['commandline']) {
	$count['lost'] = $count['error'] = $count['escrow'] = $count['queue'] = $count['draft'] = 0;
	foreach ($popAccts as $anAcct) {
		// Open the default mailbox, i.e., the inbox
		if ($hndl = imap_open($sbm->completeServerName($anAcct['pop3server']), 
			$anAcct['submissionadr'], $anAcct['password'] )){
			$nm = imap_num_msg($hndl);
			for ($i = 0; $i < $nm; $i++) {
				if (($hdr = imap_fetchheader($hndl, $i)) && ($bdy = imap_body ($hndl, $i))) {
					$msg = $hdr . $bdy;
					$sbm->receiveMsg($msg, $anAcct['submissionadr'], $count);
				} else {
					logEvent("Lost connection to $anAcct[submissionadr]");
					$count['lost']++;
					break;
				}
			}
		} else {
			logEvent("Connection to $anAcct[submissionadr] timed out");
			$count['lost']++;
		}
	}
	$total = 0;
	foreach ($count as $key => $val) if ($key != 'lost') $total += $val;
	print ("$total messages processed\n");
	foreach ($count as $key => $val) if ($key != 'lost') print("$key: $val\n");
	print("Unsuccessful or interrupted connections: " . $count['lost'] . "\n"); 
} else {
	$content = <<<EOD
<table style="width:60%; margin-top:20px; margin-left:auto; margin-right:auto; font-size:16px;"><tr><td>Messages escrowed:</td><td id="escrow" class="cntval">&nbsp;</td></tr>
<tr><td>Messages saved as draft:</td><td id="draft" class="cntval">&nbsp;</td></tr>
<tr><td>Messages queued:</td><td id="queue" class="cntval">&nbsp;</td></tr>
<tr><td>Unacceptable messages:</td><td id="error" class="cntval">&nbsp;</td></tr>
<tr><td>&nbsp;</td><td><hr style="border-top: 1px solid #8c8b8b;" /></td>
<tr><td><strong>Total Messages processed:</strong></td><td id="total" class="cntval"><strong>&nbsp;</strong></td></tr>
<tr id="lrow" style="color:red; display:none"><td>Lost Connections:</td><td id="lost">&nbsp;</td></tr>
</table>
EOD;
	$panel = new UIPanel("Collect Email Messages", $content); // Selector .panel .header h2
	print($panel->display());
	//<a id="cbtn" class="button" title="Collect Submitted Messages" onclick="getmsgs()">Collect Messages</a>
	print('<div id="mybtn" style="margin-left:22%; margin-top:15px;">'
	. '<button title="Collect Submitted Messages" onclick="getmsgs()">Collect Messages</button></div>' . "\n");
	// We need a hidden button here linking to the page listing campaigns
	$btm0=<<<ESD
<script type="text/javascript">
function getmsgs() {
	var i = 0;
	var email;
	
ESD;
	print $btm0;
	$i = count($popAccts);
	print ('var adrs = [');
	foreach ($popAccts as $acct) {
		print('"' . $acct . '"');
		$i--;
		if ($i) print(', ');
	}
	print ("];\n");
$btm1 = <<<ESD2
	var totl = 0;
	var cumcnt = {error:0, escrow:0, queue:0, draft:0, lost:0};
	var mykeys = [];
	for(var k in cumcnt) mykeys.push(k);
	$('.cntval').text('0');
	$("#mybtn").hide();
	adrs.forEach(function(email) {
		$('div.panel>div.header>h2').text('Collecting messages from ' + email);
		$.post( "plugins/submitByMailPlugin/emailajax.php", {job:'getmsg', email:email}, function(data) {
				mykeys.forEach( function (itm) {
					cumcmt[itm] += data[itm];
					totl += cumcnt[itm];
					$ ('#' + itm).text(cumcnt[itm]);					
					});
				totl -= cumcnt.lost;
				$("#total>strong").text(totl);
				if (cumcnt.lost) $('#lost').text(cumcnt.lost).show();
		});			
	});
	$('div.panel>div.header>h2').text('All messages collected');
	$('#mybtn').html ('
ESD2;
	print ($btm1 . $sbm->outsideLinkButton("eventlog&start=0", 'View Event Log')
		. "').show();\n" . '}' . "\n</script>");

}  

/* We need to do some thing about the asynchronous ajax call above.
Turning it into a synchronous call is deprecated in upcoming versions of javascript.
Maybe put a spinner in the header of the panel and use some kind of timing loop to
wait for completion of the ajax?
*/
?>