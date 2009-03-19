<?php
/***************************************************************************
 *   copyright				: (C) 2008 WeBid
 *   site					: http://www.webidsupport.com/
 ***************************************************************************/

/***************************************************************************
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version. Although none of the code may be
 *   sold. If you have been sold this script, get a refund.
 ***************************************************************************/

require('../includes/config.inc.php');
include "loggedin.inc.php";

unset($ERR);

if(isset($_POST['action']) && $_POST['action'] == 'updatelanguage' && isset($_POST['deflang'])) {
	$query = "UPDATE " . $DBPrefix . "settings SET defaultlanguage = '".$_POST['deflang']."'";
	$result = mysql_query($query);
	$system->check_mysql($result, $query, __LINE__, __FILE__);
	$system->SETTINGS['defaultlanguage'] = $_POST['defaultlanguage'];
}

// Retrieve default language
$query = "SELECT defaultlanguage FROM " . $DBPrefix . "settings";
$result = mysql_query($query);
$system->check_mysql($result, $query, __LINE__, __FILE__);
$DEFAULTLANGUAGE = mysql_result($result,0,"defaultlanguage");

$html = '';
if(is_array($LANGUAGES)) {
	reset($LANGUAGES);
	while(list($k,$v) = each($LANGUAGES)) {
		$html .= '<INPUT TYPE="radio" name="deflang" value="' . $k . '" ' . (($DEFAULTLANGUAGE == $k) ? " CHECKED" : '') . '>
	<IMG SRC="../includes/flags/' . $k . '.gif" HSPACE=2>
	' . $v . (($DEFAULTLANGUAGE == $k) ? '&nbsp;' . $MGS_2__0005 : '') . '<BR>';
	}
}

loadblock($MGS_2__0004, $MGS_2__0003, $html);

$template->assign_vars(array(
        'ERROR' => (isset($ERR)) ? $ERR : '',
        'SITEURL' => $system->SETTINGS['siteurl'],
		'TYPE' => 'pre',
		'TYPENAME' => $MSG['25_0008'],
		'PAGENAME' => $MSG['2__0002']
        ));

$template->set_filenames(array(
        'body' => 'adminpages.html'
        ));
$template->display('body');
?>