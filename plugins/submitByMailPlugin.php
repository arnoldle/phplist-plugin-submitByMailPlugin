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
 * Additional permission under GNU GPL version 3 section 7
 *
 * If you modify this Program, or any covered work, by linking or combining it with
 * mime_parser.php (or a modified version of that library), containing parts covered by
 * the terms of the BSD 2-Clause License, the licensors of this Program grant you 
 * additional permission to convey the resulting work. Corresponding Source for a 
 * non-source form of such a combination shall include the source code for the parts of 
 * mime_parser.php and pop3.php used as well as that of the covered work.
 *
 * For more information about how to use this plugin, see
 * http://resources.phplist.com/plugins/submitByMail .
 * 
 */
 
// Manuel Lemos' files for POP3 and Mime decoding. We don't use PEAR because we cannot
// count on it being available at all sites running Phplist
require_once(dirname(__FILE__)."/submitByMailPlugin/mime/rfc822_addresses.php");
require_once(dirname(__FILE__)."/submitByMailPlugin/mime/mime_parser.php");
require_once(dirname(__FILE__)."/submitByMailPlugin/pop3/pop3.php");

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
				"mail_submit_ok" => array ("tinyint default 0", "Flags messages can be submitted by email"),
				"email" => array ("varchar(255) not null", "Email address for message submission to a list"),
				"username" => array ("varchar(255) not null", "User name for signing into POP3 server"),
				"password" => array ("varchar(255)","Password associated with the user name"),
				"pipe_submission" => array ("tinyint default 0", "Flags messages are submitted by a pipe from the POP3 server"),
				"confirm" => array ("tinyint default 1", "Flags email submissions are escrowed for confirmation by submitter"),
				"queue" => array ("tinyint default 0", "Flags that messages are queued immediately rather than being saved as drafts")
			)
		);  				// Structure of database tables for this plugin
	
	public $tables = array ('escrow', 'list');	// Table names are prefixed by Phplist	
	  	
  	public $escrowdir; 	// Directory for messages escrowed for confirmation
  	public $escrowtbl, $listtbl;
  	
  	const ONE_DAY = 86400; 	// 24 hours in seconds

    function adminmenu() {
    	return array ("ldaimages" => "Manage Inline Images");
  	}

	function __construct()
    {
    	$this->coderoot = dirname(__FILE__) . '/submitByMailPlugin/';
		
		$this->escrowdir = $this->coderoot . "escrow";
		if (!is_dir($this->escrowdir))
			mkdir ($this->escrowdir);
            	
		parent::__construct();
    }
    
    function activate() {
    	$this->escrowtbl = $GLOBALS['tables']['submitByMailPlugin_escrow'];  	
    	$this->listtbl = $GLOBALS['tables']['submitByMailPlugin_list']; 
    	return true;
    }
    
    
    // Save unprocessed message in the escrow directory
    // $theMail is the entire message including headers and attachments
    function escrowMail($theMail, $token) {
    	$fn = tempnam ($this->escrowdir, 'temp');
    	file_put_contents ($fn, $theMail);
    	$query - sprintf("insert into %s values ('%s', '%s', %d)", $token, $fn, time() + self::ONE_DAY);
    	Sql_Query($query);    	
    }
    
    // Create a token for confirmation of email submissions
    // Use the Phplist algorithm for creating a password token
    function createToken() {
    	while(1){
    		$tm = time(); $rn = rand(1, $tm);
    		$key = md5($tm ^ $rn);
  			$SQLquery = sprintf("select * from %s where token = '%s'", $this->escrowtbl, $key);
  			$row = Sql_Fetch_Row_Query($SQLquery);
	  		if($row[0]=='') break;
  		}
  		return $key;
    }
    
  function processEditList($id) {
    # purpose: process edit list page (usually save fields)
    # return false if failed
    # 200710 Bas
    if ($_POST['submitOK'] == 'No')		// Don't update if can't submit by email 
    	return true;
    $params = array (
    				$id,
    				1,
    				$_POST['submitEmail'], 
    				$_POST['uname'], 
    				$_POST['pw'], 
    				($_POST['cmethod'] == 'Pipe')? 1:0,
    				($_POST['confirm'] == 'Yes')? 1: 0, 
    				($_POST['mdisposal'] == 'queue')? 1: 0
    				) ;
    $query = sprintf("select * from %s where id=%d", $this->listtbl, $id);
    if ($row = Sql_Fetch_Row_Query($query)){	// Already have this id in our list table?
    	array_shift($params);
    	$query = sprintf("update %s set mail_submit_ok=?, email=?, username=?, password=?, pipe_submission=?, confirm=?, queue=? where id=%d", $this->listtbl, $id);
     } else 
    	$query = sprintf ("insert into %s values (?, ?, ?, ?, ?, ?, ?, ?)", $this->listtbl); 
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
    	if (isset($list['id'])) {
    		$query = sprintf("select * from %s where id=%d", $this->listtbl, $list['id']);
    		if ($row = Sql_Fetch_Assoc_Query($query)) {
    			$eml = $row['email'];
    			$user = $row['username'];
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
			}
		}
		$str = <<<EOD
<fieldset>
	<legend>Submit to $list[name] by Mail</legend>
<p>	<label>Submission by mail allowed: <input type="radio" name="submitOK" value="Yes" $msyes />Yes&nbsp;&nbsp;&nbsp;&nbsp;
	<input type="radio" name="submitOK" value="No" $msno />No</label>
</p>
<p>
<label>Mail submission address:<input type="text" name="submitEmail" value="$eml" maxlength="255" /></label>
<label style="display:inline !important;">Username: <input type="text" name="uname" style="width:300px !important; 
display:inline !important;" value="$user" maxength="255" /></label>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<label style="display:inline !important;">Password: <input type="text" name="pw" 
style="width:125px !important; display:inline !important;" value="$pass" maxength="255" /></label>

<label>Collection method:&nbsp;&nbsp;<input type="radio" name="cmethod" value="POP" $pop />POP
&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" name="cmethod" value="Pipe" $pipe />Pipe</label>
<label>What to do with submitted message:&nbsp;&nbsp;<input type="radio" name="mdisposal" 
value="save" $save />Save&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" name="mdisposal" value="queue" $queue />Queue</label>
<label>Confirm submission:&nbsp;&nbsp;<input type="radio" name="confirm" value="Yes" $cfmyes />Yes&nbsp;&nbsp;&nbsp;&nbsp;
	<input type="radio" name="confirm" value="No" $cfmno />No</label></p>
</fieldset>
EOD;
		return $str;
  }

}
?>