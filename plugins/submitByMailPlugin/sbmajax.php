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
 // and to collect messages submitted via email
 // Note: the RFC's don't seem to require any particular headers here. See
 // http://stackoverflow.com/questions/4726515/what-http-response-headers-are-required

require_once dirname(__FILE__) ."/sbmGlobals.php";

// No access except via ajax called by one of the plugin pages.
if (!isset($_POST['job']) || !isset($_SERVER["HTTP_REFERER"]) || !strpos($_SERVER["HTTP_REFERER"], 'pi=submitByMailPlugin')) die();
ob_clean();
switch ($_POST['job']) {
	case 'validate': 
	case 'verify': 
		{   
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
		}
	case 'getmsgs':
		{
			/* 
			This file does not load Phplist. So the only way we can actually download
			and process messages is to call a page of the SBM plugin with a system command.
			An issue with this call is that index.php tests $_SERVER['SERVER_SOFTWARE'] to 
			detect command line operation.
			
			Shell environmental variables seem to be passed through the $_SERVER array. 
			This means that the 'SERVER_SOFTWARE' is passed with the value appropriate to
			the web page that does the exec, usually 'Apache.' So we can take care of the 
			issue by unsetting SERVER_SOFTWARE as an environmental variable before calling
			the PHP binary, so that Phplist will recognize that we are exec-ing the command
			line. Go to
			http://stackoverflow.com/questions/10731183/set-server-variable-when-calling-php-from-command-line,
			See the first answer and the comment by Alex Weinstein. 
			
			This issue cost me hours of frustration!
			*/
			$email = trim($_POST['param']);
			$syscmd = 'unset SERVER_SOFTWARE; ' . trim($_POST['cmd']) . " -e$email";
			exec ($syscmd, $output);	// $output is an array containing the result counts for the messages processed.
			print($output[0]);
			exit();
		}
	case 'ckdir':
		{
			$dir = trim($_POST['directory']);
			$status = 'OK';
			if (!file_exists($dir) || !is_dir($dir))
				$status = 'nodir';
			else if (!is_writable($dir))
				$status= 'nowrite';
			print($status);
			exit();
		}
		
	}