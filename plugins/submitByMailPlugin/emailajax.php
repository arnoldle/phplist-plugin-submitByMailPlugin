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
 
// Ajax file called from edit_list.php to validate email address, verify POP credentials,
// or submit a message to Phplist
require_once(dirname(__FILE__) ."/sbmGlobals.php");
switch ($_POST['job']) {
	case 'validate': 
	case 'verify': 
		{   
			$user=$_POST['user'];	// $user is the email address we're working with
			if (!filter_var($user, FILTER_VALIDATE_EMAIL)) {
				print('INVALID');
				exit;
			}
			
			if ($_POST['job']=='validate') {
				print ('OK');
				exit;
			}
 
  			$authhost= '{' . $_POST['server'] . submitByMailGlobals::SERVER_TAIL . '}';
			$pass = $_POST['pass'];

			if ($mbox=@imap_open( $authhost, $user, $pass )) { 	// No warning if imap_open fails!
        		imap_close($mbox);
        		print ("OK");
    		} else
        		print ("NO");
    		exit;
		}
	case 'getmsg': 
		{
			$count = array('error' => 0, 'escrow' => 0, 'queue' => 0, 'draft' => 0, 'lost' => 0  );
			$creds = $sbm->getCredentials($_POST['email']);
			if ($hndl = imap_open($sbm->completeServerName($creds['pop3server']), 
					$_POST['email'], $creds['password'] )){
				$nm = imap_num_msg($hndl);
				for ($i = 0; $i < $nm; $i++) {
					if (($hdr = imap_fetchheader($hndl, $i)) && ($bdy = imap_body ($hndl, $i))) {
						$msg = $hdr . $bdy;
						$sbm->receiveMsg($msg, $anAcct['submissionadr'], $count);
					} else {
						logEvent("Lost connection to $anAcct[submissionadr]");
						$count['lost']++;
						break;
					}
				}
			} else {
				logEvent("Connection to $anAcct[submissionadr] timed out");
				$count['lost']++;
			}
			print (json_encode($count));
		}
}