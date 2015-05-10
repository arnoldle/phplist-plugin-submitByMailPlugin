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
 
 if (!defined('PHPLISTINIT')) die(); ## avoid pages being loaded directly
 $sbm = $GLOBALS['plugins']['submitByMailPlugin'];
 if (!($mbox = $cline['e'])) {
 	logEvent("Message discarded: no mailbox specified with pipe");
 	die();
 }
 if (!($this->lid = $sbm->getListId($mbox))) {
 	logEvent("Message discarded: no list associated with mailbox with pipe");
 	die();
 }
 $query = sprintf ("select pipe_submission from %s where id=%d", $this->tables['list'], $this->lid);
 $row = Sql_Fetch_Array_Query($query);
 if (!$row[0]) {
 	logEvent("Message discarded: list " . $this->lid . " not assigned to pipe submission.");
 	die();
  }
 ob_end_clean();
 $msg = file_get_contents('php://stdin');
 // Process the message
 $sbm->receiveMsg($msg, $mbox);
?>
 
 