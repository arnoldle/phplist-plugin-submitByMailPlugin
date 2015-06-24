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
 // Ajax file called from edit_list.php to validate email address, verify POP credentials,
 // and to collect messages submitted via email

// No access except via ajax called by one of the plugin pages.
if (!isset($_POST['job']) || !isset($_SERVER["HTTP_REFERER"]) || !strpos($_SERVER["HTTP_REFERER"], 'pi=submitByMailPlugin')) die();
ob_end_clean();

switch ($_POST['job']) {
	case 'validate': 
	case 'verify':    
		$user=trim($_POST['user']);	// $user is the email address we're working with
		if (!filter_var($user, FILTER_VALIDATE_EMAIL)) {
			print('INVALID');
			exit();
		}
			
		if ($_POST['job']=='validate') {
			print ('OK');
			exit();
		}
 
  		$authhost= '{' . trim($_POST['server']) . submitByMailGlobals::SERVER_TAIL . '}';
		$pass = trim($_POST['pass']);

		if ($mbox=@imap_open( $authhost, $user, $pass )) { 	// No warning if imap_open fails!
        	imap_close($mbox);
        	print ("OK");
    	} else
        	print ("NO");
    	exit();
	
	case 'getmsgs':
		$sbm = $GLOBALS['plugins']['submitByMailPlugin'];
		$count = array();
		$count['lost'] = $count['error'] = $count['draft'] = $count['queue'] = $count['escrow'] = 0;
		$email = trim($_POST['param']);
		$myarray = array('submissionadr' => $email);
		$myarray = array_merge($myarray,$sbm->getCredentials($email));
		$sbm->downloadFromAcct ($myarray, $count);
		print(json_encode($count));
		exit();
	
	case 'ckdir':
		$dir = trim($_POST['directory']);
		$status = 'OK';
		if (!file_exists($dir) || !is_dir($dir))
			$status = 'nodir';
		else if (!is_writable($dir))
			$status= 'nowrite';
		print($status);
		exit();
	}