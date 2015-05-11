<?php

/**
 * submitByMail plugin version 1.0d1
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

require_once(dirname(__FILE__)."/submitByMailPlugin/sbmGlobals.php");
submitByMailGlobals::$have_decoder = stream_resolve_include_path("Mail/mimeDecode.php");
if (submitByMailGlobals::$have_decoder) require_once 'Mail/mimeDecode.php';
submitByMailGlobals::$have_imap = function_exists('imap_open');

/**
 * Registers the plugin with phplist
 * 
 * @category  phplist
 * @package   conditionalPlaceholderPlugin
 */

class submitByMailPlugin extends phplistPlugin
{
    // Parent properties overridden here
    public $name = 'Submit by Mail Plugin';
    public $version = '1.0d1';
    public $enabled = false;
    public $authors = 'Arnold Lesikar';
    public $description = 'Allows messages to be submitted to mailing lists by email';
    public $coderoot; 	// coderoot relative to the phplist admin directory
    public $DBstruct =array (	//For creation of the required tables by Phplist
    		'escrow' => array(
    			"token" => array("varchar(35) not null primary key", "Token sent to confirm escrowed submission"),
    			"file_name" => array("varchar(255) not null","File name for escrowed submission"),
    			"sender" => array("varchar(255) not null", "From whom?"),
    			"subject" => array("varchar(255) not null default '(no subject)'","subject"),
    			"listid" => array("integer not null","List ID"),
    			"expires" => array ("integer not null", "Unix time when submission expires without confirmation")
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
			),
		);  				// Structure of database tables for this plugin
	
	public $tables = array ();	// Table names are prefixed by Phplist
	public $publicPages = array('test2_page');	// Pages that do not require an admin login
	public $commandlinePages = array ('pipeInMsg',);
	public $settings = array(
    	"escrowHoldTime" => array (
      		'value' => 1,
     		'description' => 'Days escrowed messages are held before being discarded',
      		'type' => "integer",
      		'allowempty' => 0,
      		"max" => 7,
      		"min" => 1,
      		'category'=> 'campaign',),
      
    	// Note that the content type of the message must be multipart or text
    	// The settings below apply to attachments.
    	// Note also that we do not allow multipart attachments.
    	"allowedTextSubtypes" => array(
			'value' => 'plain, html',
    		'description' => 'MIME text/subtypes allowed for attachments',
    		'type' => 'text',
    		'allowempty' => 0,
      		'category' => 'campaign',),
      		
		'allowedImageSubtypes' => array(
			'value' => 'gif, jpeg, pjpeg, tiff, png',
    		'description' => 'image/subtypes allowed for attachments',
    		'type' => 'text',
    		'allowempty' => 0,
      		'category' => 'campaign',),
      		
      	"allowedMimeTypes" => array (
    		'value' => 'application/pdf',
    		'description' => 'Additional MIME content-types allowed for attachments',
    		'type' => 'text',
    		'allowempty' => 1,
      		'category' => 'campaign',),
      		
		"manualMsgCollection" => array (
    		'value' => 1,
    		'description' => 'Use browser to collect messages submitted by POP (Yes or No)',
    		'type' => "boolean",
      		'allowempty' => 0,
      		'category'=> 'campaign',), 
      	
      	"popTimeout" => array (
      		'value' => 0,
    		'description' => 'POP3 timeout in seconds; set 0 to use default value',
    		'type' => "integer",
      		'allowempty' => 0,
      		"max" => 120,
      		"min" => 0,
      		'category'=> 'campaign',),  				
	);
	public $pageTitles = array ("configure_a_list" => "Configure a List for Submission by Email",
								"collectMsgs" => "Collect Messages Submitted by Email", 
											"my_test_page" => "Page for Testing Prospective Plugin Methods");
	public $topMenuLinks = array('configure_a_list' => array ('category' => 'Campaigns'),
								  'collectMsgs' => array ('category' => 'Campaigns'),
									'my_test_page' => array ('category' => 'Campaigns') );	
	
	// Properties particular to this plugin  	
  	public $escrowdir; 	// Directory for messages escrowed for confirmation
  	
  	private $errMsgs = array("nodecode" => 'Msg discarded: cannot decode',
  							"unauth" => "List '%s': Msg discarded; unauthorized sender",
  							"nosub" => "List '%s': Msg discarded; empty subject line",
  							"badmain" => "List '%s': Msg discarded; bad type for main message",
  							"badtyp" => "List '%s': Msg discarded; mime type not allowed",
  							"noattach" => "List '%s': Msg discarded; attachments not permitted",
  							"toodeep" => "List '%s': Msg discarded; mime nesting too deep",
  							"badinlin" => "List '%s': Msg discarded; inline type not allowed"
  							);
  							
  	public $numberPerList = 20;		// Number of lists tabulated per page in listing
	
	private $allowedMimes = array(); // Allowed MIME subtypes keyed on types
	private $allowedMain = array(); // MIME subtypes allowed as main message, keyed
									// on types
	// Parameters for the message we are dealing with currently
	// If only PHP had genuine scope rules, so many private class properties would not 
	// be necessary!!
	public $lid;		// ID for list receiving current message
	public $sender;		// Sender of the current message
	public $subj;		// Subject line of the current message
	private $mid;		// Message ID for current message being saved or queued
	private $holdTime;	// Days to hold escrowed message
	private $textmsg;	// Text version of current message
	private $htmlmsg;	// HTML version of current message
	
	const ONE_DAY = 86400; 	// 24 hours in seconds
  	
  	function __construct()
    {
    	// Ensure that we don't crash Phplist and that we can't activate the plugin if 
    	// we don't have the PEAR mime decoder and the imap extension
    	if ((!submitByMailGlobals::$have_decoder) || (!submitByMailGlobals::$have_imap)) {
    	
    		// Don't have prerequisites; make sure plug-in remains unitialized and disabled
    		$query = sprintf("delete from %s where item = '%s'", $GLOBALS['tables']['config'], md5('plugin-submitByMailPlugin-initialised'));
    		sql_query($query);
    		$this->commandlinePages = $this->pageTitles = $this->topMenuLinks = $this->settings
    			= $this->dbStruct = array();	// Avoid showing this plugin exists!
    			
    		// Since Phplist constructs the plugin class even for disabled plugins, make
    		// sure that we call the parent constructor as we must always do when constructing
    		// the class
    		parent::__construct();
    		return;
    	}
	   	$this->coderoot = dirname(__FILE__) . '/submitByMailPlugin/';
		
		$this->escrowdir = $this->coderoot . "escrow/";
		if (!is_dir($this->escrowdir))
			mkdir ($this->escrowdir);
			
		parent::__construct();
		
		$this->holdTime = (int) getConfig("escrowHoldTime");
		
		// Build array of allowed MIME types and subtypes
		// For safety, wait until parent is constructed before building these arrays
		$str = getConfig('allowedTextSubtypes');
    	$str = strtolower(str_replace(' ','', $str));
    	$this->allowedMimes['text'] = explode(',', $str);
    	
    	$str = getConfig('allowedImageSubtypes');
    	$str = strtolower(str_replace(' ','', $str));
    	$this->allowedMimes['image'] = explode(',', $str);
    	
    	$str = getConfig('allowedMimeTypes');
    	$str = strtolower(str_replace(' ','', $str));
    	$addTypes = explode (',', $str);
    	foreach ($addTypes as $val) {
    		$partial = explode('/', $val);
    		$this->allowedMimes[trim($partial[0])][] = trim($partial[1]);    	
    	}
    	
    	$this->pop_timeout = (int) getConfig("popTimeout");
    	if ($this->pop_timeout) {
    		imap_timeout (IMAP_OPENTIMEOUT, $this->pop_timeout);
    		imap_timeout (IMAP_READTIMEOUT, $this->pop_timeout);
    		imap_timeout (IMAP_WRITETIMEOUT, $this->pop_timeout);
    	}
    	
    	// Don't let the admin add to the multipart types
    	$this->allowedMimes['multipart'] = array('mixed', 'alternative', 'related');
    	$this->allowedMain = array('text' => array('plain', 'html'), "multipart" => $this->allowedMimes['multipart']);
    	
    	// Make sure that we don't show the message collection page if we don't allow
    	// manual collection of messages, remove that page from the menus.
    	if (!getConfig("manualMsgCollection")) {
    		$this->delArrayItem(1, $topMenuLinks);
    		$this->delArrayItem(1, $pageTitles);
    	}
    	
    	// Delete escrowed messages that have expired
    	// Do this here, because we don't want the user to have to set up a cron script
    	// for this.
    	$this->deleteExpired();
    }
   	    
   function initialise() {
   		// Base url for links to some of our pages
   		if (!$GLOBALS["commandline"])
   			saveConfig('burl', $GLOBALS['scheme'] . "://" . $GLOBALS['website'] . $GLOBALS['pageroot'] . '/admin/');
    	parent::initialise();
    } 
    
    function delArrayItem($ndx, &$ary){
    	unset ($ary[$ndx]);
    	$ary = array_values($ary);
    }
    
    // Delete expired messages in escrow
    function deleteExpired() {
    	$query = sprintf("select token, file_name from %s where expires < %d", $this->tables['escrow'], time());
    	$result = Sql_Query ($query);
    	while ($row = Sql_Fetch_Row($result)) {
    		unlink ($this->escrowdir . $row[1]);
    		$query = sprintf ("delete from %s where token = '%s'", $this->tables['escrow'], $row[0]);
    		Sql_Query($query);
    	}
    }

  	// Provide complete server name in a form suitable for a SSL/TLS POP call using iMap function
  	function completeServerName($server) {
  		return '{' . $server . submitByMailGlobals::SERVER_TAIL . '}';
  	}
  	
  	function adminmenu() {
    	return $this->pageTitles;
	}
	
	function generateRandomString($length = 35) {
    	return substr(sha1(mt_rand()), 0, $length);
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
    
    // Get an array of the mailing lists with submission address and list id
    function getTheLists($name='') {
    	global $tables;
    	$A = $tables['list']; 	// Phplist table of lists, including name and id
		$B = $this->tables['list'];	// My table holds mail submission data for lists
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
    	$out = 0;
    	if (preg_match('/<(.*)>/', $email, $match))
			$email = $match[1];$query = sprintf("select id from %s where submissionadr='%s'", $this->tables['list'], trim($email));
    	if ($res = Sql_Query($query)) {
    		$row = Sql_Fetch_Row($res);
    		return $row[0];
    	}
    	return false;
    }
    
    function getCredentials ($email) {
    	$query = sprintf("select pop3server, password from %s where submissionadr='%s'",
    		$this->tables['list'], $email);
    	return Sql_Fetch_Assoc_Query($query);
    }
    
    // Returns array of connection parameters for the lists receiving messages via POP
    function getPopData() {
    	$out = array();
    	$query = sprintf("select pop3server, submissionadr, password from %s where pipe_submission=0",
    		$this->tables['list']);
    	$result = Sql_Query($query);
    	while ($row = Sql_Fetch_Assoc($result))
    		$out[] = $row;
    	return $out;
    }
        
    function getListParameters ($id) {
    	$query = sprintf ("select pop3server, pipe_submission, confirm, queue from %s where id=%d", $this->tables['list'], $id);
    	return Sql_Fetch_Assoc_Query($query);
    }
    
    // What to do with messages for a particular list
    function getDisposition ($id) {
    	$query = sprintf ("select confirm, queue from %s where id=%d", $this->tables['list'], $id);
    	$row = Sql_Fetch_Array_Query($query);
    	return $row[0]? "escrow" : ($row[1]? "queue" : "save"); 
    }
    
    function doQueueMsg($lid) {
    	$query = sprintf ("select queue from %s where id=%d", $this->tables['list'], $id);
    	$row = Sql_Fetch_Array_Query($query);
    	return $row[0];
    }
    
    function getListFtrTmplt($id) {
    	$query = sprintf ("select template, footer from %s where id=%d", $this->tables['list'], $id);
    	$row = Sql_Fetch_Array_Query($query);
    	return $row;
    }
    
    function getListAdminAdr($listId) {
    	$A = $GLOBALS['tables']['admin'];
    	$B = $GLOBALS['tables']['list'];
    	$query = sprintf ("select email from %s left join %s on %s.id=%s.owner where %s.id=%d", $A, $B, $A, $B, $B, $listId );    	
    	$row = Sql_Fetch_Row_Query($query);	
		return $row[0];
    }
    
    // Get the email addresses of all the admins
    function getAdminAdrs() {
    	$query = sprintf("select email from %s", $GLOBALS['tables']['admin']);
    	$result = Sql_Query($query);
    	while ($row = Sql_Fetch_Row($result))
    		$out[] = $row[0];
    	return $out;
    }
    
    function getSuperAdrs() {
    	$query = sprintf ("select email from %s where superuser=1", $GLOBALS['tables']['admin']);
    	$res = Sql_query($query);
    	while ($row = Sql_Fetch_Row($res))
    		$out[] = trim($row[0]);
    	return $out;
    }
   
    function std($str) {
    	return strtolower(trim($str));
    }
    
    // Get filename associated with a part if there is one
    function getFn($apart) {
    	if (isset($apart->d_parameters['filename']))
    		return $apart->d_parameters['filename'];
    	if (isset($apart->ctype_parameters['name']))
    		return $apart->ctype_parameters['name'];
    	return false;
    }
    
    function badMime($apart, $lvl) {
    	$decoder = new Mail_mimeDecode($apart);
		$params['include_bodies'] = false;
		$params['decode_bodies']  = false;
		$params['decode_headers'] = true;
		$out = $decoder->decode($params);
		$hdrs = $out->headers;
		
    	$mimes = $this->allowedMimes;
    	$mains = $this->allowedMain;
    	$c1 = $this->std($apart->ctype_primary); 
    	$c2 = $this->std($apart->ctype_secondary);

    	if ($lvl > 2)
    		return "toodeep"; 	// Mime parts too deeply nexted
    	
    	// Is the part an allowed mime type, subtype:
    	if ((!array_key_exists( $c1,$mimes)) || (!in_array($c2, $mimes[$c1])))
    		return "badtyp";		// Message has a forbidden mime type
    	
    	// If multipart, check the parts	
    	if ($c1 == 'multipart') {
    		foreach ($apart->parts as $mypart) {
   			if ($result = $this->badMime($mypart, $lvl+1))	// Return if find bad part
    				return $result;
    		}
    		return false;    		
    	} else { // if not multipart, is it OK as attachment or inline?
    	
    		// Do we have a file name? Treat the part as an attachment
    		$havefn = $this->getFn($apart);
    		if ($havefn && !ALLOW_ATTACHMENTS)
    			return "noattach";		// Have an attachment when none or allowed.
    
    		// If no file name, but have something other than text or multipart
    		// Treat it as inline and an error
    		// Multipart type is excluded by this point; we are only looking for text types
    		if (!$havefn && ((!array_key_exists( $c1, $mains)) || (!in_array($c2, $mains[$c1]))))
    			return "badinlin"; 		// Forbidden inline attachment
    		return false;
    		
    	}
    }  
    
 	// Check if the message is acceptable; $mbox is the address at which the email arrived.
    // We need this so that in case of a submission to multiple lists we can tell
    // which list we are sending this instance of the message to.
    //
    // If there is a problem with the message, this function notifies the list administrator 
    // (and the sender, if the sender is a superuser), logs the problem, and then returns false.
    // Otherwise the function simply returns true.
    //
    // As a side effect this function sets $this->lid and $this->sender for use in
    // further processing the message
    function badMessage ($msg, $mbox) {
    	$decoder = new Mail_mimeDecode($msg);
		$params['include_bodies'] = false;
		$params['decode_bodies']  = false;
		$params['decode_headers'] = true;
		$out = $decoder->decode($params);
		$hdrs = $out->headers;
		$this->subj = trim($hdrs['subject']); 		
		$this->lid = $this->getListID($mbox);
			
		$authSenders[] = $this->getListAdminAdr($this->lid); // Admin for this list
		
		// Authorized senders are the list administrator and superusers
		$this->sender = trim($hdrs['from']);
		if (!$this->sender) return "nodecode";
		if (preg_match('/<(.*)>/', $this->sender, $match))
			$from = trim($match[1]);
		$authorized = ($from == $authSenders[0])? 1 : 0; // Ordinary list admin cannot send to lists he doesn't aadminister
		
		if (!$authorized) { // Check on the superusers
			$supers = $this->getSuperAdrs();
			$authorized = in_array($from, $supers);
			if (!$authorized)
				return "unauth";
		}
		
		// We do not allow an empty subject line in messages submitted by email
		if ($this->subj == '')
			return "nosub";
		
		// Check that we have an acceptable MIME structure
		$mains = $this->allowedMain;
		$c1 = $this->std($out->ctype_primary); 
    	$c2 = $this->std($out->ctype_secondary);
		if ((!array_key_exists( $c1, $mains)) || (!in_array($c2, $mains[$c1]))) 
    		return "badmain";		// The main message is not proper text or multipart
    	if ($c1 == 'text') 	// Must be plain or html here
    		return false;
    	else { 	// Multipart
    		foreach ($out->parts as $mypart) {
    			if ($result = $this->badMime($mypart, 1))	// Return if find bad part
    				return $result;
    		}
    		return false;	// All parts OK 
    	}  	
	}
	
	// Hold message for confirmation by the sender
	// Save msg in the 'escrow' subdirectory and save location and message information
	// in the Phplist database. 
	function escrowMsg($msg) {
		$tfn = tempnam ($this->escrowdir , "msg" );
		file_put_contents ($tfn, $msg);
		$fname = basename($tfn);
		$tokn = $this->generateRandomString();
		$xpir = time() + self::ONE_DAY * $this->holdTime;
		$query = sprintf ("insert into %s values ('%s', '%s', '%s','%s', %d, %d)", $this->tables['escrow'], $tokn, $fname, 
			sql_escape($this->sender), sql_escape($this->subj), $this->lid, $xpir);
		Sql_Query($query);
		return $tokn;
	}

	// Some email user agents separate sections of html messages showing email attachments
	// inline. Apple Mail is an example of this. The result can be multiple html and body tags in
	// tags in a message. It may not be necessary, but we remove those to avoid trouble.
	// User agents may produce all sorts of mixtures of plain text and html, for example
	// a long text message with an html part at the end, following an inline attachment.
	// For Phplist we separate the text and html messages, and there is nothing that we 
	// can do if the text and html of the message are mixed up improperly. 
	function cleanHtml($html) {
		if (stripos($html, '<head') !== false) 	// If we have a complete webpage, leave it be
			return $html;
		$html = str_ireplace("</html>", "", $html); 
		$html = str_ireplace("</body>", "", $html);
		$fndcnt = -1;
		preg_replace_callback("/<html[^>]*>/i", 
			function ($matches) use (&$fndcnt) {
				$fndcnt++;
				if ($fndcnt)
					return '';
			 	else			// Leave first '<html>' tag alone.
					return $matches[0];
					
			},
			$html);
		$haveHtmlTag = ($fndcnt > -1);
		$fndcnt = -1;
		preg_replace_callback("/<body[^>]*>/i", 
			function ($matches) use (&$fndcnt) {
				$fndcnt++;
				if ($fndcnt)
					return '';
			 	else			// Leave first '<body>' tag alone
					return $matches[0];
					
			},
			$html);
		$haveBodyTag = ($fndcnt > -1);
		
		// Make sure that the '<body>' and '<html>' tags are closed if they are there
		return $html . ($haveBodyTag? '</body>' : '') . ($haveHtmlTag? '</html>' : '');
	}
	
	// Save the $messagedata array in the database. This code if taken almost verbatim
	// from the Phplist file sendcore.php. We save the message data only after setting
	// the message status.
	function saveMessageData($messagedata) {
		$query = sprintf('update %s  set '
     		. '  subject = ?'
     		. ', fromfield = ?'
     		. ', tofield = ?'
     		. ', replyto = ?'
     		. ', embargo = ?'
     		. ', repeatinterval = ?'
     		. ', repeatuntil = ?'
     		. ', message = ?'
     		. ', textmessage = ?'
     		. ', footer = ?'
     		. ', status = ?'
     		. ', htmlformatted = ?'
     		. ', sendformat  =  ?'
     		. ', template  =  ?'
     		. ' where id = ?', $tables["message"]);
     	$htmlformatted = ($messagedata["sendformat"] == 'HTML'); 
  		$result = Sql_Query_Params($query, array(
       		$messagedata['subject']
     		, $messagedata['fromfield']
     		, $messagedata['tofield']
     		, $messagedata['replyto']
     		, sprintf('%04d-%02d-%02d %02d:%02d',
        		$messagedata['embargo']['year'],$messagedata['embargo']['month'],$messagedata['embargo']['day'],
        		$messagedata['embargo']['hour'],$messagedata['embargo']['minute'])
     		, $messagedata['repeatinterval']
     			, sprintf('%04d-%02d-%02d %02d:%02d',
        		$messagedata["repeatuntil"]['year'],$messagedata["repeatuntil"]['month'],$messagedata["repeatuntil"]['day'],
        		$messagedata["repeatuntil"]['hour'],$messagedata["repeatuntil"]['minute'])
     		, $messagedata["message"]
     		, $messagedata["textmessage"]
     		, $messagedata["footer"]
     		, $messagedata['status']
     		, $htmlformatted ? '1' : '0'
     		, $messagedata["sendformat"]
     		, $messagedata["template"]
     		, $this->mid));
     	return $this->mid; 	// Return private message ID so we can use it in other files
	}
	
	// Do the actual decoding of bodies of message
	// Before this function is called, we have already determined that all of the 
	// message parts are acceptable
	function decodePart ($apart, &$msgdata) {
		$decoder = new Mail_mimeDecode($msg);
		$params['include_bodies'] = true;
		$params['decode_bodies']  = true;
		$params['decode_headers'] = true;
		$out = $decoder->decode($params);
		$hdrs = $out->headers;
		$c1 = $this->std($apart->ctype_primary); 
    	$c2 = $this->std($apart->ctype_secondary);
		// If multipart, check the parts	
   		if ($c1 == 'multipart') {
    		foreach ($apart->parts as $mypart) {
   				$this->decodePart ($mypart, $msgdata);
    		}  		
    	} else { // if not multipart, is it OK as attachment or inline?
    	
			// Do we have a file name? Treat the part as an attachment 
    		if (($attachname = $this->getFn($apart)) && strlen($out->body)) {
    			// Handle atttachment
    			list($name,$ext) = explode(".",basename($attachname));
        		# create a temporary file to make sure to use a unique file name to store with
        		$newfile = tempnam($GLOBALS["attachment_repository"],$name);
        		unlink ($newfile); 	// Want the name, not the file that tempnam creates
        		$newfile .= ".".$ext;
        		file_put_contents($newfile, $out->body);
         		Sql_query(sprintf('insert into %s (filename,remotefile,mimetype,description,size) values("%s","%s","%s","%s",%d)',
          			$tables["attachment"],
          			basename($newfile), 
          			$attachname, 
          			$c1 . '/' . $c2, 
          			'From submitted email', 
          			filesize($newfile))
          		);
          		$attachmentid = Sql_Insert_Id();
      		 	Sql_query(sprintf('insert into %s (messageid,attachmentid) values(%d,%d)',
          			$tables["message_attachment"],$this->mid,$attachmentid));
          	}  else {	// if not multipart and not attachment must be text/plain or text/html
    				if ($c2 == 'plain')
    					$this->textmsg .= $out->body;
    				else
    					$this->htmlmsg .= $out->body; 
    		} 
    	} 	
	} 
	
		// Put default message values into the Phplist database and get an ID for the 
		// message. Then load the message data array with values for the message
		function loadMessageData ($msg) {
		
		$defaulttemplate = getConfig('defaultmessagetemplate');
  		$defaultfooter = getConfig('messagefooter');
  		
  		// Note that the 'replyto' item appears not to be in use
  		// This item in $messagedata must exist, but it will remain empty
  		// So we do nothing further with it
  		$query
  		= " insert into %s"
  		. "    (subject, status, entered, sendformat, embargo"
  		. "    , repeatuntil, owner, template, tofield, replyto,footer)"
  		. " values"
  		. "    ('(no subject)', 'draft', current_timestamp, 'HTML'"
  		. "    , current_timestamp, current_timestamp, ?, ?, '', '', ? )";
  		$query = sprintf($query, $GLOBALS['tables']['message']);
  		Sql_Query_Params($query, array(listOwner($this->lid), $defaulttemplate,$defaultfooter));
  		// Set the current message ID
  		$this->mid = Sql_Insert_Id();
		// Tie the message to the proper list
      	$query = "replace into %s (messageid,listid,entered) values(?,?,current_timestamp)";
      	$query = sprintf($query,$GLOBALS['tables']['listmessage']);
      	Sql_Query_Params($query,array($this->mid,$this->lid));
      	      	
      	// Now create the messageData array with the default values
      	// We are going to load it with the template and footer set for the current list
      	// and the MIME decoded message
      	$messagedata = loadMessageData($this->mid);
      	$messagedata['subject'] = $this->subj;
      	$messagedata['fromfield'] = $this->sender;
      	$tempftr = $this->getListFtrTmplt($this->lid);
      	$messagedata['template'] = $tempftr[0];
      	$messagedata['footer'] = $tempftr[1];
      	
      	// Now decode the MIME. Load attachments into database. Get text and html msg
      	$this->htmlmsg = $this->textmsg = '';
      	$this->decodePart($msg);
      	if ($this->htmlmsg) {
      		$messagedata["message"] = $this->cleanHtml($this->htmlmsg);
      		$messagedata["sendformat"] = 'HTML';
      		if ($this->textmsg)
      			$messagedata["textmessage"];
      	} else {
      		$messagedata["message"] = $this->textmsg;
      		$messagedata["sendformat"] = 'text';
      	}
        return $messagedata;
	}  
	
	// Update the status for the current message
	function updateStatus($status) {
		$query = sprintf("update %s set status=%s where id=%d", $GLOBALS['tables']['message'], $this->mid);
		sql_query($query);
	}
	
	function saveDraft($msg) {
		$msgData = $this->loadMessageData ($msg); 	// Default messagedata['status'] is 'draft'
		// Allow plugins manipulate data or save it somewhere else
  		foreach ($GLOBALS['plugins'] as $pluginname => $plugin)
  			$plugin->sendMessageTabSave($this->id,$msgData);
		$this->saveMessageData($msgData);
	}
	
	function queueMsg($msg) {
		// Need to test this concept!!!!
		$msgData = $this->loadMessageData ($msg);
		// Make sure the message has been properly saved, including giving the 
		// plugins a chance to participate.
		foreach ($GLOBALS['plugins'] as $pluginname => $plugin)
  			$plugin->sendMessageTabSave($this->id,$msgData);
		$this->saveMessageData($msgData);
		
		// Now can we queue this message. Ask if it's OK with the plugins
		$queueErr = '';
		foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
  			$pluginerror = '';
  			$pluginerror = $plugin->allowMessageToBeQueued($messagedata);
  			if ($pluginerror) 
  				$queueErr .= $pluginerror . "\n"; 
  		}
  		if (!$queueErr) {
 			$this->updateStatus('submitted');
			return false;
		} else
			return $queueErr;
	}
	
	// This function is called for each message as it is received
	// to determine whether the message should be escrowed or processed immediately
	// $count is an optional array with the proper items to count the outcomes.
	function receiveMsg($msg, $mbox, &$count=null) {
		if ($result = $this->badMessage($msg, $mbox)) {
			logEvent(sprintf($this->errMsgs[$result], listName($this->lid)));
			if (($result != "unauth") && ($result != "nodecode")) {
				// Edit the log entry for the email to the sender
				if ($result == 'nosub') $this->subj = '(no subject)';
				$ofs = strpos($this->errMsgs[$result], 'Msg discarded;') + strlen('Msg discarded;');
				sendMail($this->sender, "Message Received and Discarded",
					"A message with the subject '" . $this->subj . "'was received but discarded for the following reason:" . 
						substr($this->errMsgs[$result], $ofs));
			}
			if (is_array($count)) $count['error']++;
		} else { 
			switch ($this->getDisposition($this->lid)) {
				case 'escrow':
					$tokn = $this->escrowMsg($msg);
					$cfmlink = getConfig('burl' . "?pi=submitByMailPlugin&p=confirmMsg.php&tk=$tokn");
					sendMail($this->sender, 'Message Received and Escrowed', 
						"<p>A message with the subject '" . $this->subj . "'was received and escrowed.</p>\n" .
						"<p>To confirm this message, please click the following link:" .
						'<a href="' . $cfmlink . '">' . '"$cfmlink</a>.' . "</p>\n<p> You may need to login before reaching this page.</p>"
						); 
					if (is_array($count)) $count['escrow']++;
					break;
				case 'queue': 
					if ($err = $this->queueMsg($msg)) {
						sendMail($this->sender, 'Message Received but NOT Queued', 
							"A message with the subject '" . $this->subj . "'was received. It was not queued because of the following error(s): " .$err);
						if (is_array($count)) $count['draft']++;
					} else {
						sendMail($this->sender, 'Message Received and Queued', 
						"A message with the subject '" . $this->subj . "' was received and is queued for distribution.");
						if (is_array($count)) $count['queue']++;
					}
					break;
				case 'save':	
					$this->saveDraft($msg);
					sendMail($this->sender, 'Message Received and Queued', 
						"A message with the subject '" . $this->subj . "' was received and has been saved as a draft.");
					if (is_array($count)) $count['draft']++;
					break;
			}
		}		
	}  
}

?>