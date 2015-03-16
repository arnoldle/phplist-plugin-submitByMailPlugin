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

require_once(dirname(__FILE__)."/submitByMailPlugin/ajax.php");

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
    			"subject" => array("varchar(255) not null default '(no subject)'","subject"),
    			"listid" => array("integer not null","List ID"),
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
	public $settings = array(
    "escrowHoldTime" => array (
      'value' => 1,
      'description' => 'How many days escrowed messages are held before being discarded',
      'type' => "text",
      'allowempty' => 0,
      "max" => 7,
      "min" => 1,
      'category'=> 'general',
    ),
  );
	public $pagesTitles = array ("configure_a_list" => "Configure a List for Submission by Email",
											"my_test_page" => "Page for Testing Prospective Plugin Methods");
	public $topMenuLinks = array('configure_a_list' => array ('category' => 'Campaigns'),
									'my_test_page' => array ('category' => 'Campaigns') );	
	  	
  	public $escrowdir; 	// Directory for messages escrowed for confirmation
  	public $escrowtbl, $listtbl;
  	public $target; 	// The ID of the list targetted by the current message
	public $owner;		// The ID of the owner of the current message
	public $check = array ('authSender', 'checkTo', 'owner', 'mailSubmit', 'pipeOK', 'attachOK', 'inlineOK');
	public $pipesubmission = 0;
	
	public $numberPerList = 20;		// Number of lists tabulated per page in listing
	
	
  	const ONE_DAY = 86400; 	// 24 hours in seconds
  	const SERVER_TAIL = ':995/pop3/ssl/novalidate-cert';
  	
  	// Provide complete server name in a form suitable for a SSL/TLS POP call using iMap function
  	function completeServerName($server) {
  		return '{' . $server . sbmAjax::SERVER_TAIL . '}';
  	}
  	
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
}
?>