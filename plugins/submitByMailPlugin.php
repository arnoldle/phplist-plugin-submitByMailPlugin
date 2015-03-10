<?php

/**
 * submitByMail plugin version 1.0a1
 * 
 *
 * @category  phplist
 * @package   submitByMail Plugin
 * @author    Arnold V. Lesikar
 * @copyright 2014 Arnold V. Lesikar
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 * 
 * This program is free software: you can redistribute it and/or modify
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

/**
 * Registers the plugin with phplist
 * 
 * @category  phplist
 * @package   conditionalPlaceholderPlugin
 */

class submitByMailPlugin extends phplistPlugin
{
    /*
     *  Inherited variables
     */
    public $name = 'Submit by Mail Plugin';
    public $version = '1.0a1';
    public $enabled = false;
    public $authors = 'Arnold Lesikar';
    public $description = 'Allows messages to be submitted to mailing lists by email';
    public $DBstruct =array (	//For creation of the required tables by Phplist
    		'escrow' => array(
    			"token" => array("varchar(35) not null primary key", "Token sent to confirm escrowed submission"),
    			"file_name" => array("varchar(255) not null","File name for escrowed submission"),
    			"expires" => array ("timestamp not null", "Time when submission expires without confirmation")
    			
			),
			'list' => array(
				"id" => array("integer not null primary key", "ID of the list associated with the email address"),
				"pop3server" => array ("varchar(255) not null", "Server collecting list submissions"),
				"submissionadr" => array ("varchar(255) not null", "Email address for list submission"),
				"password" => array ("varchar(255)","Password associated with the user name"),
				"pipe_submission" => array ("tinyint default 0", "Flags messages are submitted by a pipe from the POP3 server"),
				"confirm" => array ("tinyint default 1", "Flags email submissions are escrowed for confirmation by submitter"),
				"queue" => array ("tinyint default 0", "Flags that messages are queued immediately rather than being saved as drafts"),
				"template" => array("integer default 0", "Template to use with messages submitted to this address"),
				"footer" => array("text","Footer for a message submitted to this address")
			)
		);  				// Structure of database tables for this plugin
	
	public $tables = array ();	// Table names are prefixed by Phplist
	public $commandlinePages = array ('receiveMsg',);
	public $pagesTitles = array ("configure_a_list" => "Configure a List for Submission by Email");
	public $topMenuLinks = array('configure_a_list' => array ('category' => 'Campaigns'));	
	  	
  	public $escrowdir; 	// Directory for messages escrowed for confirmation
  	public $escrowtbl, $listtbl;
  	public $target; 	// The ID of the list targetted by the current message
	public $owner;		// The ID of the owner of the current message
	public $check = array ('authSender', 'checkTo', 'owner', 'mailSubmit', 'pipeOK', 'attachOK', 'inlineOK');
	public $pipesubmission = 0;
	
	public $numberPerList = 20;		// Number of lists tabulated per page in listing
	
	
  	const ONE_DAY = 86400; 	// 24 hours in seconds
  	
  	function adminmenu() {
    	return array (
      		"configure_a_list" => "Configure a List for Submission by Email",
      	    );
	}
	
	function cleanFormString($str) {
		return sql_escape(strip_tags(trim($str)));
	}
	
	function myFormStart($action, $additional) {
		$html = formStart($additional);
		preg_match('/action\s*=\s*".*"/Ui', $html, $match);
		$html = str_replace($match[0], 'action="' . $action .'"', $html);
		return $html;
	}
  	
  	function __construct()
    {
    	$this->coderoot = dirname(__FILE__) . '/submitByMailPlugin/';
		
		$this->escrowdir = $this->coderoot . "escrow/";
		if (!is_dir($this->escrowdir))
			mkdir ($this->escrowdir);
            	
		parent::__construct();
    }
    
    function initialise() {
    	saveConfig('dcrt', $_SERVER['DOCUMENT_ROOT']);	// We need the document root,
    													// which is not availabler
    													// from the command line
		parent::initialise();
    }

	function notifySender($to, $subject, $message) {
    	sendMail ($to, $subject, $message);
    	logEvent ($message);
    }
    
    function getTheLists($name='') {
    	global $tables;
    	$A = $tables['list']; 	// My table holds submission stuff for lists
		$B = $this->tables['list'];	// Phplist table of lists, including name and id
		$out = array();
		if (strlen($name)) {
			$where = sprintf("WHERE $A.name='%s' ", $name); 
		}
    	$query = "SELECT $A.name,$B.submissionadr,$A.id FROM $A LEFT JOIN $B ON $A.id=$B.id {$where}ORDER BY $A.name";
    	if ($res = Sql_Query($query)) {
    		$ix = 0;
    		while ($row = Sql_Fetch_Row($res)) {
    			$out[$ix] = $row;
    			$ix += 1;
    		}	
    	}
    	return $out; 
    }
          
    // Get the numberical id of a list from its email submission address
    function getListID ($email) {
    	$query = sprintf("select id from %s where submissionadr='%s'", $this->tables['list'], trim($submissionadr));
    	if ($res = Sql_Query($query)) {
    		$row = Sql_Fetch_Row($res);
    		return $row[0];
    	}
    	return false;
    }
    
    function getListParameters ($id) {
    	$query = sprintf ("select mail_submit_ok, pop3server, pipe_submission, confirm, queue from %s where id=%d", $this->tables['list'], $id);
    	return Sql_Fetch_Assoc_Query($query);
    }
    
    function getListOwner($id) {
    	$query = sprintf ("select owner from %s where id=%d", $GLOBALS['tables']['list'], $id);
    	$row = Sql_Fetch_Row_Query($query);
    	return $row[0];
    }

    
/*  function processEditList($id) {
    # purpose: process edit list page (usually save fields)
    # return false if failed
    # 200710 Bas
    if ($_POST['submitOK'] == 'No') { 	// No submission params if submission disallowed
    	$query = sprintf("delete * from %s where id=%d", $this->tables['list'], $id);
    	return true;
    }
    
    // Make sure that a different list does not have same submission address
    $sadr = trim($_POST['submitadr']);
    $query = sprintf("select id from %s where submitAdr=%s", $this->tables['list'], $sadr);
    $dbres = Sql_Query($query);
    $numrows = Sql_Num_Rows($dbres);
    if ($numrows > 0) {	// This submission address is in the database
    	// Find the first list with a different id from the list we'e editing
    	$row = Sql_Fetch_Row($dbres);
    	$cur = $row[0];
    	while($cur == $id) {
    		$row = Sql_Fetch_Row($dbres);
    		if ($row)
    			$cur = $row[0];
		}
		if ($cur <> $id) {	// Got a different list with this submission address
    		Warn ("The address you have entered belongs to the list &quot;" . listname($id) . "&quot;. Message submission by email is not enabled for the list &quot;" . $_POST['listname'] . '&quot;.');
    		return false;
		}
			    	
    }
    
    // If POP submission, verify that it works
    if ($_POST['cmethod'] == 'Pipe')
    	$server = $pass = ''; 	// Don't need POP3 params with pipe
    else {
    	$server = trim($_POST['pop3Server']);
    	$pass = trim($_POST['pw']);
    }
    
    // Everything OK. Store the data	
    $params = array (
    				$id,
    				1,
    				$server, 
    				$sadr, 
    				$pass, 
    				($_POST['cmethod'] == 'Pipe')? 1:0, 
    				($_POST['confirm'] == 'Yes')? 1: 0, 
    				($_POST['mdisposal'] == 'queue')? 1: 0,
    				$_POST['template'],
    				trim($_POST['footer'])
    				) ;
    Sql_Query_Params($query, $params);
    return true;
  }

    function displayEditList($list) {
    # purpose: return tablerows with list attributes for this list
    # Currently used in list.php
    # 200710 Bas
     
    	// Set up defaults for form
    	$eml = $user = $pass = $msyes = $pipe = $cfmno = $queue = '';
    	$save = $pop = $cfmyes = $msno = $ckd = 'checked';
    	$tmplt = 0;
    	$footer = getConfig('messagefooter');
    	
    	if (isset($list['id'])) {
    		$query = sprintf("select * from %s where id=%d", $this->tables['list'], $list['id']);
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
		}
		
		$req = Sql_Query("select id,title from {$GLOBALS['tables']['template']} order by listorder");
  		$templates_available = Sql_Num_Rows($req);
  		if ($templates_available) {
  			$template_form = '<p><div class="field"><label for="template">Template to use for messages submitted through this address:</label>
  			<select name="template"><option value="0">-- Use None</option>';
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
        	
        $footer_form = '<p><div class="field"><label for="footer">Footer to be used for messages submitted through this address:</label>
   <textarea name="footer" cols="65" rows="5">'. htmlspecialchars($footer).'</textarea></div></p>';
   		$hr = '<hr style="height:1px; border:none; color:#000; background-color:#000; width:80%; text-align:right; margin: 0 auto 10px 0;"/>';

		$ln = trim($list['name'])? $list['name']: 'This List';
		$str = <<<EOD
$hr
<fieldset>
	<legend style="text-align:center; font-size:18px; color:DarkBlue;margin-bottom:15px;">Submit to $ln by Mail</legend>
<p>	<label>Submission by mail allowed: <input type="radio" name="submitOK" value="Yes" $msyes />Yes&nbsp;&nbsp;&nbsp;&nbsp;
	<input type="radio" name="submitOK" value="No" $msno />No</label>
</p>
<p>
<label>Collection method:&nbsp;&nbsp;<input type="radio" name="cmethod" value="POP" $pop />POP3 with SSL/TLS
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" name="cmethod" value="Pipe" $pipe />Pipe</label>
</p><p>
<label style="display:inline !important;">Submission Address: <input type="text" name="submitadr" style="width:250px !important; 
display:inline !important;" value="$user" maxength="255" /></label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<label style="display:inline !important;">Password: <input type="text" name="pw" 
style="width:125px !important; display:inline !important;" value="$pass" maxength="255" /></label>
<label>Mail Submission POP3 Server:<input type="text" name="pop3Server" value="$eml" maxlength="255" /></label>

<label>What to do with submitted message:&nbsp;&nbsp;<input type="radio" name="mdisposal" 
value="save" $save />Save&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" name="mdisposal" value="queue" $queue />Queue</label>
<label>Confirm submission:&nbsp;&nbsp;<input type="radio" name="confirm" value="Yes" $cfmyes />Yes&nbsp;&nbsp;&nbsp;&nbsp;
	<input type="radio" name="confirm" value="No" $cfmno />No</label></p>$template_form $footer_form
</fieldset>
$hr
EOD;
		return $str;
  } */
}
?>