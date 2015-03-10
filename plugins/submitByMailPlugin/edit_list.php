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
$editid = $_GET['eid'];
$sbm = $GLOBALS['plugins']['submitByMailPlugin'];

// Set up defaults for form
$eml = $user = $pass = $msyes = $pipe = $cfmno = $queue = '';
$save = $pop = $cfmyes = $msno = $ckd = 'checked';
$tmplt = 0;
$footer = getConfig('messagefooter');

$query = sprintf("select * from %s where id=%d", $sbm->tables['list'], $editid);

if ($row = Sql_Fetch_Assoc_Query($query)) {
	$eml = $row['pop3server'];
	$user = $row['submissionadr'];
	$pass = $row['password'];

	if ($row['mail_submit_ok']) {
		$msyes = $ckd;
		$msno = '';
	} else {
		$msno = $ckd;
		$msyes = '';
	}
	if ($row['pipe_submission']) {
		$pipe = $ckd;
		$pop = '';
	} else {
		$pop = $ckd;
		$pipe = '';
	}
	if ($row['confirm']) {
		$cfmyes = $ckd;
		$cmno = '';
	} else {
		$cfmno = $ckd;
		$cfmyes = '';
	}
	if ($row['queue']) {
		$queue = $ckd;
		$save = '';
	} else {
		$save = $ckd;
		$queue = '';
	}
	$tmplt = $row['template'];
	$footer = $row['footer'];
}
		
$req = Sql_Query("select id,title from {$GLOBALS['tables']['template']} order by listorder");
$templates_available = Sql_Num_Rows($req);
if ($templates_available) {
	$template_form = '<p><div class="field"><label for="template">Template to use for messages submitted through this address:</label><select name="template"><option value="0">-- Use None</option>';
	$req = Sql_Query("select id,title, listorder from {$GLOBALS['tables']['template']} order by listorder");
	while ($row = Sql_Fetch_Assoc($req)) {   // need to fix lines below
		if ($row["title"]) {
			$template_form .= sprintf('<option value="%d" %s>%s</option>',$row["id"], 
			$row["id"]==$tmplt?'selected="selected"':'',$row["title"]);
		}
	}
	$template_form .= '</select></div></p>';
} else
	$template_form = '';

$footer_form = '<p><div class="field"><label for="footer">Footer to be used for messages submitted through this address:</label><textarea name="footer" cols="65" rows="5">'. htmlspecialchars($footer).'</textarea></div></p>';

$ln = listName($editid);

print ($sbm->myFormStart(PageURL2('configure_a_list'), 'name="sbmConfigEdit" class="submitByMailPlugin" id="sbmConfigEdit"'));

$mypanel = <<<EOD
<p>	<label>Submission by mail allowed: <input type="radio" name="submitOK" value="Yes" $msyes />Yes&nbsp;&nbsp;&nbsp;&nbsp;
	<input type="radio" name="submitOK" value="No" $msno />No</label>
</p>
<p>
<label>Collection method:&nbsp;&nbsp;<input type="radio" name="cmethod" value="POP" $pop/>POP3 with SSL/TLS
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" name="cmethod" value="Pipe" $pipe/>Pipe</label>
</p><p>
<label style="display:inline !important;">Submission Address: <input type="text" name="submitadr" style="width:250px !important; 
display:inline !important;" value="$user" maxength="255" /></label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span id="pop"><label style="display:inline !important;">Password: <input type="text" name="pw" 
style="width:125px !important; display:inline !important;" value="$pass" maxength="255" /></label>
<label>Mail Submission POP3 Server:<input type="text" name="pop3Server" value="$eml" maxlength="255" /></label></span>

<label>What to do with submitted message:&nbsp;&nbsp;<input type="radio" name="mdisposal" 
value="save" $save />Save&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" name="mdisposal" value="queue" $queue />Queue</label>
<label>Confirm submission:&nbsp;&nbsp;<input type="radio" name="confirm" value="Yes" $cfmyes />Yes&nbsp;&nbsp;&nbsp;&nbsp;
	<input type="radio" name="confirm" value="No" $cfmno />No</label></p>$template_form $footer_form
<input class="submit" type="submit" name="submitok" value="Save");" />
EOD;

$mypanel .= PageLinkClass('configure_a_list','Cancel','','button cancel','Do not save, and go back to the lists');

$panel = new UIPanel("Submit to List by Mail: <strong>$ln</strong>", $mypanel);
print($panel->display());
print '</form>';
?>
<script type="text/javascript">
$(document).ready(function () {
    toggleFields(); //call this first so we start out with the correct visibility depending on the selected form values
    //this will call our toggleFields function every time the selection value of our underAge field changes
    $( "input[type=radio]" ).change(function () {
        toggleFields();
    });

});
//this toggles the visibility of our parent permission fields depending on the current selected value of the underAge field
function toggleFields() {
	if ($("input[name=cmethod]:checked").val() == "POP") {
        $("#pop").show();
    } else {
        $("#pop").hide();
    }
}
</script>
