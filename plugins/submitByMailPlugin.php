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

// Define a class allowing each decoded message to be treated as an object
// The constructor is called with a string representing the entire message to be
// decoded. Decoding sets public variables containing the relevant parts of the message
// with flags or properties defining what we have in the message.
// An instance of this class is created only after we know that we have a message from
// a valid list owner or superuser sent to an existing list.
class decodedMessage extends mime_parser_class {	// Manuel Lemos' decoder class
	public $inlineImages = array();
	public $attachments = array();
	public $message = array();
	
	private function clean($str) {
		return strtolower(trim($str));
	}
	
	// Remove <!DOCTYPE...>, <html...>, <head...>...</head>, <body...>, </body>, </html> tags
	// from submitted message to be stored
	function cleanHtml($str) {
		$patterns = array(
						'#.*<body.*>#Uis',
						'#</body>#i',
						'#</html>#i'
					);
		foreach ($patterns as $pat)			
			$str = preg_replace($pat, '', $str);
		return $str;
	}
	
	function __construct($str) {
		$sbm = $GLOBALS["plugins"]['submitByMailPlugin']; 	// The submitByMailPlugin instance
		$this->mbox = 0;	// Set to 0 for parsing a single message file
    	$this->decode_bodies = 1;	// Set to 1 for decoding the message bodies
		$this->ignore_syntax_errors = 1;	
    	$this->track_lines = 0;	// Set to 0 to avoid keeping track of the lines of the message data
    	$this->use_part_file_names = 0;	// Set to 1 to make message parts be saved with original file names
    								// when the SaveBody parameter is used
		$this->custom_mime_types = array(	//MIME types not yet recognized by the Analyze class function.
    	    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>array(
        	    'Type' => 'ms-word',
            	'Description' => 'Word processing document in Microsoft Office OpenXML format'
        	)
    	);

    	$parameters=array(
        	'Data'=> $str,	// Input data from string rather than a file
        	'SkipBody'=>0	// Do not retrieve or save message body parts
    	);
    
    	if ($this->Decode($parameters, $decoded)) {
    		if (!$this->Analyze($decoded[0], $uncodedMsg))
    			throw new Exception($this->error);
    	} else
			throw new Exception($this->error);
			
		$this->message['subject'] = $uncodedMsg['Subject'];
		$fromFld = $uncodedMsg['From'][0];
		if (isset($fromFld['name']) && (trim($fromFld['name'])))
			$this->message['from']['name'] = $fromFld['name'];
		$this->message['from']['address'] = $fromFld['address'];
			
		foreach ($uncodedMsg['To'] as $val)
			$this->message['to'][] = $val['address'];
		
		if (!preg_match('/(html|text)/i', $uncodedMsg['Type'], $match))
			throw new Exception("Submitted message not text or html");
		$this->message['is_html'] = (strtolower($match[1]) == 'html');
		
		if ($this->message['is_html'])
			$this->message['content'] = $this->cleanHtml($uncodedMsg['Data']);
		else {	
			$this->message['content'] = $uncodedMsg['Data'];
			$this->message['encoding'] = (isset($uncodedMsg['Encoding'])?$uncodedMsg['Encoding']:'UTF-8');
		}
			
		if ($uncodedMsg['Related']) {	// Inline files
			foreach ($uncodedMsg['Related'] as $val) {															
				if ($val['Type'] == 'image') { // Only keep inline image files, no others allowed
					$temp['filename'] = $val['FileName'];
					$temp['cid'] = $val['ContentID'];
					$temp['content'] = $val['Data'];
					$this->message['images'][] = $temp;
				}
			}
		}
		
		if ($uncodedMsg['Attachments']) {
			foreach ($uncodedMsg['Attachments'] as $val) {
				$is_html = false;
				if ($val['FileName'])
					$temp['filename'] = $val['FileName'];
				else if (preg_match('/(html|text)/i', $val['Type'], $match)) { // No file name: we'll
																	 	// combine with the message
																	 	// as an inline attachment
					$temp['Type'] = $val['Type'];
					$is_html = (strtolower($match[1]) == 'html');
				} else
					throw new Exception('Unknown inline type. Cannot handle.');	
				if ($is_html)	
					$temp['content'] = $this->cleanHtml($val['Data']);								
				else {
					$temp['content'] = $val['Data'];
					$temp['encoding'] = (isset($val['Encoding'])?$val['Encoding']:'UTF-8');
				}
				$this->message['attachments'][] = $temp;	
			}
		}				
  	}
}

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
				"queue" => array ("tinyint default 0", "Flags that messages are queued immediately rather than being saved as drafts"),
				"template" => array("integer default 0", "Template to use with messages submitted to this address"),
				"footer" => array("text","Footer for a message submitted to this address")
			)
		);  				// Structure of database tables for this plugin
	
	public $tables = array ('escrow', 'list');	// Table names are prefixed by Phplist	
	  	
  	public $escrowdir; 	// Directory for messages escrowed for confirmation
  	public $escrowtbl, $listtbl;
  	
  	const ONE_DAY = 86400; 	// 24 hours in seconds
  	
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
    $params = array (
    				$id,
    				1,
    				trim($_POST['submitEmail']), 
    				trim($_POST['uname']), 
    				trim($_POST['pw']), 
    				($_POST['cmethod'] == 'Pipe')? 1:0,
    				($_POST['confirm'] == 'Yes')? 1: 0, 
    				($_POST['mdisposal'] == 'queue')? 1: 0,
    				$_POST['template'],
    				trim($_POST['footer'])
    				) ;
    $query = sprintf("select * from %s where id=%d", $this->listtbl, $id);
    if ($row = Sql_Fetch_Row_Query($query)){	// Already have this id in our list table?
    	if (!strlen($params[2])) {	// No email submission address means delete old data
    		$params = array();
    		$query = sprintf("delete from %s where id = %d", $this->listtbl, $id);
    	} else {
    		array_shift($params);
    		$query = sprintf("update %s set mail_submit_ok=?, email=?, username=?, password=?, pipe_submission=?, confirm=?, queue=?, template=?, footer=? where id=%d", $this->listtbl, $id);
    	}
	} else {
		if (!strlen($params[2]))	// No data for submission by email
    		return true;
    	$query = sprintf ("insert into %s values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $this->listtbl); 
    }
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

		$str = <<<EOD
$hr
<fieldset>
	<legend style="text-align:center; font-size:18px; color:DarkBlue;margin-bottom:15px;">Submit to $list[name] by Mail</legend>
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
	<input type="radio" name="confirm" value="No" $cfmno />No</label></p>$template_form $footer_form
</fieldset>
$hr
EOD;
		return $str;
  } 
}


?>