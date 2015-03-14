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

/* For paging the listing of the mail lists */
if (isset($_GET["start"])){
   $start = sprintf("%d", $_GET["start"]);
}
else $start = 0;

if (isset($_POST['search']) || isset($_POST['update'])) {	

   /* check the XSRF token */
   if (!verifyToken()) {
     print Error(s('Invalid security token, please reload the page and try again'));
     return;
   }
}

$ourpage = $_GET['page'];
$ourname = $_GET['pi'];
$sbm = $GLOBALS['plugins']['submitByMailPlugin'];
$currentUser = $_SESSION["logindetails"]["id"];
$needle = '';

// Handle update of submission configuration
/* if (isset($_POST['update'])) {
	$fn = $sbm->cleanFormString($_POST['filename']) . '.' . $sbm->cleanFormString($_POST['extension']);
	$desc = substr($sbm->cleanFormString($_POST['image_description']), 0, 255);
	$query = sprintf("update %s set file_name='%s', short_name='%s', description='%s' where imgid=%d", $imgtbl, $fn, $sbm->cleanFormString($_POST['shortname']),$desc, $_POST['imageid']);
	if (!Sql_query($query))
		Warn(sprintf("Update of information for image %d failed!", $_POST['imageid']));

} */

// Initialize seartch
if (isset($_POST['search']) || (isset($_POST['save']) && isset($_POST['needle']))){
	$needle = $_POST['needle'];
}

// Create the search form
$searchform = formStart() . sprintf ('<div><strong>%s:</strong><br /></div> <div><input name="needle" id="needle" size="50" maxlength="255"></div>','Enter the List Name');
$searchform .= '<input class="submit" type="submit" name="search" value="Search" />';
$searchpanel = new UIPanel("Search for a List", $searchform);
$sform = $searchpanel->display();
$listArray = $sbm->getTheLists($needle);
$total = count($listArray);

/* Prepare tabulate the mailing lists */
$mylist = new WebblerListing("ID");

/* Do the tabulation */
if (($total) && ($start >= $total))
	$start = $total - $sbm->numberPerList;

if (!$total)
	if (strlen($needle))
		$mylist->addElement("<strong>No list named $needle found</strong>", '');
	else
		$mylist->addElement('<strong>No lists exist</strong>', '');
else {
	$end = min ($total, $start + $sbm->numberPerList);
	for ($ix = $start; $ix < $end; $ix++) {
		$pid = $listArray[$ix][2];
		$editurl = PageURL2('edit_list','','eid=' . $pid);
		$mylist->addElement($pid, $editurl);
		$mylist->addColumn($pid, 'List Name', $listArray[$ix][0], $editurl);
		if ($listArray[$ix][1])
			$mylist->addColumn($pid, 'Submission Address', $listArray[$ix][1], '');
		else
			$mylist->addColumn($pid, 'Submission Address', 'None', '');
	}
	$paging=simplePaging("configure_a_list", $start, $total, $sbm->numberPerList,'Lists');
	$mylist->usePanel($paging);

}

$list = $mylist->display(0,'myclass');
$ltitle = '<div class="panel"><div class="header"><h2>ID</h2></div>';
if (isset($_REQUEST['needle']))
	$newtitle = '<div class="panel"><div class="header"><h2>Lists Found</h2></div>';
else
	$newtitle = '<div class="panel"><div class="header"><h2>Configuration for Submission by Email</h2></div>';
$list = str_replace($ltitle, $newtitle, $list);

$mypanel .= $list . '<br />';
$mypanel .= $sform . '<br />';
Info('Click on the Name or ID of a Mailing Liste to Configure the List for Email Submission or to Edit Its Submission Configuration.');
$panel = new UIPanel('Available Mailing Lists',$mypanel,'');
print $panel->display();
print('</form>');
