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
ob_end_clean();
$sbm = $GLOBALS['plugins']['submitByMailPlugin'];
$mbox = $cline['e'];
if (!$mbox) {
 	echo "No mailbox specified.\n";
 	logEvent("Message discarded: no mailbox specified with pipe");
 	die();
}
if (!($mylid = $sbm->getListId($mbox))) {
 	echo "No list associated with this pipe.\n";
 	logEvent("Message discarded: no list associated with mailbox using this pipe");
 	die();
}
if (!$sbm->pipeOK($mylid)) {
 	echo "Pipe submission not permitted for this list.\n";
 	logEvent("Message discarded: list $mylid not assigned to pipe submission.");
 	die();
}
$msg = file_get_contents('php://stdin');
// Process the message
$sbm->receiveMsg($msg, $mbox);
echo "Message processed. Check Event Log for result.\n";
?>
 
 