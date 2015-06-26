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
 
 // Generate a warning if the command line php.ini does not have a suitable include path
 // Also check for imap extension

if (!defined('PHPLISTINIT')) die(); // avoid pages being loaded directly

$sbm = $GLOBALS['plugins']['submitByMailPlugin'];

$dir = trim($_POST['directory']);

if ($_POST['scriptType']) {
	if ($version = $sbm->ckPHPversion()) {
		if ($_POST['scriptType'] == 'pipe') {
			$script = "#!/bin/sh\n" .  $sbm->makeCliCommand('pipeInMsg') . " -e$1>/dev/null 2>&1";
			$scptname = 'pipeMsg.sh';
		} elseif (($_POST['scriptType'] == 'collect')) {
			$script = "#!/bin/sh\n" . $sbm->makeCliCommand('collectMsgs');
			$scptname = 'collectMsgs.sh';
		} else {
			$script = "#!/bin/sh\n" . $sbm->makeCliCommand('processqueue');
			$script = str_replace(' -msubmitByMailPlugin', '', $script);
			$scptname = 'processQueue.sh';
		}
		if (substr($dir, -1) != '/') $dir .= '/';
		if ((file_put_contents($dir . $scptname, $script) === false)|| !chmod ($dir . $scptname, 0755)) {
			Warn('<span style="font-weight:bold; font-size:18px;">Error! Either could not write to specified directory or could not make script executable.</span>'); 
		} else {
		
			$info = "<div style='font-size:14px'><p>Script '$scptname' generated and stored in directory: '$dir'</p>";
			if ($version < submitByMailGlobals::RECPHP) {
				$info .= "<p><strong>This script uses PHP version $version. This is an earlier version than
					version 5.4+ recommended for use with phpList.</strong></p>";
				$info .= "<p>If a later command line version of PHP is available, you might consider entering the 
					path to that version into the submitByMailPlugin settings and then generating this script again.<p>";
				$info .= "</div>";
				Info($info);
			}
		}
	} else {
		$alignP = '<p style="text-align:left;">';
		$info ='<div style="font-size:14px"><p>Unable to access PHP command line binary. <strong>Script NOT generated!</strong></p>';
		$info .= "{$alignP}You must enter the correct path to the binary into the submitByMailPlugin settings or empty the setting";
		$info .= " to allow the plugin to find the binary itself.</p>{$alignP}If a command line binary is not available to the plugin,";
		$info .= ' message collection is NOT possible &mdash; neither through the browser nor on the command line.</p></div>';
		Warn($info);
	}	
}
$str = <<<EOI
<div style="font-size:14px">
<p>This page generates shell scripts that you can use to pipe messages into phpList and to collect messages
from mailboxes with POP.<p><p>The script to pipe in messages is called "pipeMsg.sh." Let's call the directory path to the directory where you store the script <em>/Path_to_the_script</em>. The you can pipe from a mailbox, say, <em>mybox@nowhere.com</em>, 
using the following command:
<p style="text-align:center"><em style="font-size:16px;">/Path_to_the_script/pipeMsg.sh mybox@nowhere.com</em></p><p>The script to collect messages is called "collectMsgs.sh.
As before, if the directory path to the script is <em>/Path_to_the_script</em>, you can use the following as 
a cron job:</p><p style="text-align:center"><em style="font-size:16px;">/Path_to_the_script/collectMsgs.sh > /dev/null 2>&1</em></p>
<p>In a similar fashion you can make a script "processQueue.sh" to use in a cron job to process the queue at scheduled intervals.<p>The scripts assume that the shell
is located in the usual Unix/Linux location <em>/bin/sh</em>.
<p>You should always include the full path to your script in these commands, because you cannot be sure of the environment of the process running these scripts.</div>
EOI;
print($str);
print('<div id="mydialog" title="Data Not Saved" style="text-align:center;"></div>'); // Space for modal dialogs using jQueryUI

$content = formStart('id="scptGenForm"');
$content .= <<<EOD
<h5>Select script to create:</h5>
<p><input type="radio" checked name="scriptType" value="pipe" /><strong>&nbsp;Script to pipe in a message to a list</strong></p>
<p><input type="radio" name="scriptType" value="collect" /><strong>&nbsp;Script to collect messages</input></p>
<p><input type="radio" name="scriptType" value="pqueue" /><strong>&nbsp;Script to process the queue</input></p>
<p>Directory in which to store the script:<br />
<input type=text name="directory" size=200 value ="$dir" />
<p><input class="submit" type="submit" name="submitBtn" value="Create Script" /></p>
</form>
EOD;

$panel = new UIPanel("Generate Scripts", $content); // Selector .panel .header h2
print($panel->display());
$myscript = <<<ESO
<script type="text/javascript">
$(document).ready(function () {
    $("#mydialog").dialog({
    		modal: true,
    		autoOpen: false,
    		width: 500
    	}); 
	$(".ui-dialog-titlebar-close").css("display","none");
	$(".ui-dialog-content").css("margin", "10px");
	$(".ui-dialog").css("border","3px solid DarkGray");
	$(".ui-dialog-content").css("font-size", "18px");
	});


function myalert(msg) {
	$("#mydialog").html(msg);
	$("#mydialog").dialog("option",{buttons:{"OK": function() {
        				$(this).dialog("close");}}});
    $("#mydialog").dialog("open");
}

function mysubmit() {
	var myform = document.getElementById("scptGenForm");
    myform.submit();
} 

$("#scptGenForm").submit(function(event) {
	var dir = $(":input[name='directory']").val();
	dir = dir.replace(/\s/g, '');
	if (!dir || 0 === dir.length) {
		myalert("You must enter a name for the directory where the script will be stored.");
		return false;
	}
	event.preventDefault(); 
	$.post( "?pi=submitByMailPlugin&page=sbmajax&ajaxed=1", {job:'ckdir', directory:dir}, function (data) {
		switch(data) {
				case 'OK':
					mysubmit();
				case 'nodir':
					{
					myalert('This directory does not exist. Please choose a different directory');
					break;
					}
				case 'nowrite':
					{
					myalert ('Cannot write in this directory. Please choose a different directory');
					break;
					} 
		}
	}, 'text');
});
</script>
<style>
.ui-dialog{top:30% !important}
</style>
ESO;
print ($myscript);
?>