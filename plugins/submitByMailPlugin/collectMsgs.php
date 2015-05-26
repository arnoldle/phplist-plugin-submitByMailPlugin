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
$flag = $sbm->deleteMsgOnReceipt? CL_EXPUNGE: 0;
$count = array();

if ($GLOBALS['commandline']) { 
	ob_end_clean();
	$count['lost'] = $count['error'] = $count['escrow'] = $count['queue'] = $count['draft'] = 0;
	if (isset($cline['e'])) { // Response to system call sent by ajax server
		$myarray = array('submissionadr' => $cline[e]);
		$myarray = array_merge($myarray,$sbm->getCredentials($cline[e]));
		$sbm->downloadFromAcct ($myarray, $count);
		print(json_encode($count));
		die();
	} else { // Command line, but not ajax
		$popAccts = $sbm->getPopData();
		$count['lost'] = $count['error'] = $count['escrow'] = $count['queue'] = $count['draft'] = 0;
		logEvent("Beginning POP collection of submitted messages.");
		foreach ($popAccts as $anAcct) {
			$sbm->downloadFromAcct ($anAcct, $count);
		}
	$total = 0;
	foreach ($count as $key => $val) if ($key != 'lost') $total += $val;
	print ("$total messages processed\n");
	logEvent("POP: $total messages processed.");
	foreach ($count as $key => $val) if ($key != 'lost') print("$key: $val\n");
	print("Unsuccessful or interrupted connections: " . $count['lost'] . "\n"); 
	logEvent("POP: Unsuccessful or interrupted connections: " .  $count['lost']);
	die();
	}
} else {
	
	if (!isSuperUser()) {
		print ("<p>You do not have sufficient privileges to view this page.</p>");
		die();
	}

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
ESD;
	print ($btm0);
	$popAccts = $sbm->getPopData();
	print ("var config = '" . realpath($GLOBALS["configfile"]) . "';\n");
	$i = count($popAccts);
	print ('var adrs = [');
	foreach ($popAccts as $acct) {
		if ($sbm->isSecure) print('"' . $acct['submissionadr'] . '"');
		else print('"' . $acct['id'] . '"');
		$i--;
		if ($i) print(', ');
	}
	print ("];\n");
/**
	In jQuery v1.7 synchronous Ajax calls are deprecated, and their disappearance is promised
	for the later versions. So in going through the array of email addresses/list_ids, we 
	have no choice but to use recursion
**/
$btm1 = <<<ESD2
	adrs = ['testfwd@suncitydems.org'];
	var totl = 0;
	var cumcnt = {error:0, escrow:0, queue:0, draft:0, lost:0};
	var mykeys = [];
	for(var k in cumcnt) {
		mykeys.push(k);
	}
	$('.cntval').text('0');
	$("#mybtn").hide();
	/* $.ajaxSetup({
  		url: "plugins/submitByMailPlugin/emailajax.php",
  		dataType: "json"
	}); */
	var i = 0;
	function mypost() {
		$('div.panel>div.header>h2').text('Collecting messages from ' + adrs[i]);
		$.post("plugins/submitByMailPlugin/emailajax.php",{job:'getmsg', cfg:config, param:adrs[i]}).done(function(data) {
				alert(data);
				/*mykeys.forEach( function (itm) {
					cumcmt[itm] += data[itm];
					totl += cumcnt[itm];
					$ ('#' + itm).text(cumcnt[itm]);					
					});
				totl -= cumcnt.lost;
				$("#total>strong").text(totl);
				if (cumcnt.lost) {
					$('#lost').text(cumcnt.lost).show();
				}
				i+=1;
				if (i < adrs.length) {
					//mypost();
				} else {
					$('div.panel>div.header>h2').text('All messages collected');
					$('#mybtn').html ('
ESD2;
	print ($btm1 . $sbm->outsideLinkButton("eventlog&start=0", 'View Event Log')
		. "').show();\n\t\t\t\t}*/\n\t\t});\n\t}\n}\n</script>");
}
?>