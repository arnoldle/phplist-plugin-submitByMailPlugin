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
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 * For more information about how to use this plugin, see
 * http://resources.phplist.com/plugins/submitByMail .
 * 
 */

if (!defined('PHPLISTINIT')) die(); // avoid pages being loaded directly

$sbm = $GLOBALS['plugins']['submitByMailPlugin'];

if (!$sbm->isSecureConnection()) {

	Warn($sbm->insecure);
	return;
}

$count = array();

if ($GLOBALS['commandline']) { 
	ob_end_clean();
	if (getConfig('manualMsgCollection')) { 
		logEvent("SBM settings forbid attempt at command line message collection");
		print("SBM settings forbid attempt at command line message collection\n");
		die();
	}
	$popAccts = $sbm->getPopData();
	$count['lost'] = $count['error'] = $count['escrow'] = $count['queue'] = $count['draft'] = 0;
	logEvent("Beginning POP collection of submitted messages.");
	foreach ($popAccts as $anAcct) {
		print($anAcct['submissionadr']  . ": CUMULATIVE COUNTS\n");
		$sbm->downloadFromAcct ($anAcct, $count);
		foreach($count as $key => $val) {
			print("$key: $val\n");
		}
		print ("\n"); 
	}
	$total = 0;
	foreach ($count as $key => $val) if ($key != 'lost') $total += $val;
	print ("$total messages processed\n");
	logEvent("POP: $total messages processed.");
	foreach ($count as $key => $val) if ($key != 'lost') print("$key: $val\n");
	print("Unsuccessful or interrupted connections: " . $count['lost'] . "\n"); 
	logEvent("POP: Unsuccessful or interrupted connections: " .  $count['lost']);
	die();
} else {

	if (!isSuperUser()) {
		print ("<p>You do not have sufficient privileges to view this page.</p>");
		logEvent("Attempt to collect messages by non-super user.");
		die();
	}
	
	if (!getConfig("manualMsgCollection")) {
		print("<p>You cannot collect messages with your browser when submitByMailPlugin settings do not allow such message collection.</p>");
		die();
	}
	print '<noscript>';
   	print(Warn('<span style="font-weight:bold; font-size:18px;">Without Javascript, messages cannot be collected manually.<br />You must use a command line script for message collection.</span>')); 
	print '</noscript>';
	print('<div id="nojs" style="display:none;">'); // Hide page content if Javascript off
	Info('<strong style="font-size:16px;">Please do not leave this page while collecting messages.<br />Otherwise you may interrupt message collection.</strong>', 1);

	$content = <<<EOD
<table style="width:60%; margin-top:20px; margin-left:auto; margin-right:auto; font-size:16px;"><tr><td>Messages escrowed:</td><td id="escrow" class="cntval">&nbsp;</td></tr>
<tr><td>Messages saved as draft:</td><td id="draft" class="cntval">&nbsp;</td></tr>
<tr><td>Messages queued:</td><td id="queue" class="cntval">&nbsp;</td></tr>
<tr><td>Unacceptable messages:</td><td id="error" class="cntval">&nbsp;</td></tr>
<tr><td>&nbsp;</td><td><hr style="border-top: 2px solid #8c8b8b;" /></td>
<tr><td><strong>Total Messages processed:</strong></td><td id="ttl" class="cntval" style="font-weight:bold;">&nbsp;</td></tr>
<tr id="lrow" style="color:red; display:none"><td>Lost Connections:</td><td id="lost">&nbsp;</td></tr>
</table>
EOD;
	$panel = new UIPanel("Collect Email Messages", $content); // Selector .panel .header h2
	print($panel->display());
	print('<div id="mybtn" style="margin-left:22%; margin-top:15px;">'
	. '<button title="Collect Submitted Messages" onclick="getmsgs()">Collect Messages</button></div>' . "\n");

/**
	In jQuery v1.7 synchronous Ajax calls are deprecated, and their disappearance is promised
	for the later versions. So in going through the array of email addresses/list_ids, we 
	have no choice but to use recursion
**/
	$myscript = <<<ESO
<script type="text/javascript">
$(function() {
	$("#nojs").show();
});
function getmsgs() {;
	var adrs = [{{{listadrs}}}];
	$('.cntval').text('0');
	var totl = 0;
	var cumcnt = {error:0, escrow:0, queue:0, draft:0, lost:0};
	$("#mybtn").hide();
	var i = 0;
	function mypost() {
		$('div.panel>div.header>h2').html('Collecting messages from ' + adrs[i] + '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img style="width:34px; height:34px;" src="images/busy.gif">');
		$.post("?pi=submitByMailPlugin&page=sbmajax&ajaxed=1",{job:'getmsgs', cmd:'{{{phpcommand}}}', param:adrs[i]}).done(function(data) {
		        var cnt = JSON.parse(data);
				$.each (cnt, function (itm, val) {
					if (itm != 'lost') {
						totl += val;
					}
					cumcnt[itm] += val;
					$ ('#' + itm).text(cumcnt[itm]);										
					$('#ttl').text(totl);
					if (cumcnt.lost) {
						$('#lost').text(cumcnt.lost);
						$('#lrow').show();
					}
				});
				i+=1;
				if (i < adrs.length) {
					mypost();
				} else {
					$('div.panel>div.header>h2').text('All messages collected');
					$('#mybtn').html ('{{{newbutton}}}').show();
				}
		});
	}
	mypost();
}
</script>
ESO;
	// Fill in the placeholders in our javascript
	$myscript = str_replace('{{{phpcommand}}}', $sbm->makeCliCommand('collectMsgs'), $myscript);
	$myscript = str_replace('{{{newbutton}}}', $sbm->outsideLinkButton("eventlog&start=0", 'View Event Log'), $myscript);
	$popAccts = $sbm->getPopData();
	$i = count($popAccts);
	$acctstr = '';
	foreach ($popAccts as $acct) {
		$acctstr .= '"' . $acct['submissionadr'] . '"';
		$i--;
		if ($i) $acctstr .= ', ';
	}
	$myscript = str_replace('{{{listadrs}}}', $acctstr, $myscript);
	print($myscript);
	print('</div>');
}

?>