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

require('includes/config.inc.php');
// // If user is not logged in redirect to login page
if (!isset($_SESSION['WEBID_LOGGED_IN'])) {
    header("location: user_login.php");
    exit;
}

$query = "SELECT a.auction, a.seller, a.winner, a.bid, b.id, b.current_bid, b.title, a.qty, b.ends FROM " . $DBPrefix . "winners a
		LEFT JOIN " . $DBPrefix . "auctions b ON (a.auction = b.id)
		WHERE (b.closed = 1 OR b.bn_only = 'y') AND b.suspended = 0
		AND ((a.seller = " . $_SESSION['WEBID_LOGGED_IN'] . " AND a.feedback_win = 0)
		OR (a.winner = " . $_SESSION['WEBID_LOGGED_IN'] . " AND a.feedback_sel = 0))";
$res = mysql_query($query);
$system->check_mysql($res, $query, __LINE__, __FILE__);

$k = 0;
while ($row = mysql_fetch_array($res)) {
    $them = ($row['winner'] == $_SESSION['WEBID_LOGGED_IN']) ? $row['seller'] : $row['winner'];
    // Get details
    $query = "SELECT u.nick, u.email
			FROM " . $DBPrefix . "users u
			WHERE u.id = " . $them;
    $re_ = mysql_query($query);
    $system->check_mysql($re_, $query, __LINE__, __FILE__);

    $template->assign_block_vars('fbs', array(
            'ID' => $row['id'],
            'ROWCOLOUR' => ($k % 2) ? 'bgcolor="#FFFEEE"' : '',
            'TITLE' => $row['title'],
            'WINORSELLNICK' => mysql_result($re_, 0, "nick"),
            'WINORSELL' => ($row['winner'] == $_SESSION['WEBID_LOGGED_IN']) ? $MSG['25_0002'] : $MSG['25_0001'],
            'WINORSELLEMAIL' => mysql_result($re_, 0, "email"),
            'BID' => $row['bid'],
            'BIDFORM' => $system->print_money($row['bid']),
            'QTY' => ($row['qty'] == 0) ? 1 : $row['qty'],
            'WINNER' => $row['winner'],
            'SELLER' => $row['seller'],
			'CLOSINGDATE' => FormatDate($row['ends']),
            'WS' => ($row['winner'] == $_SESSION['WEBID_LOGGED_IN']) ? 's' : 'w'
            ));
    $k++;
}

$template->assign_vars(array(
        'NUM_AUCTIONS' => $k
        ));

$TPL_rater_nick = $_SESSION['WEBID_LOGGED_IN_USERNAME'];
include "header.php";
$TMP_usmenutitle = $MSG['25_0003'];
include "includes/user_cp.php";
$template->set_filenames(array(
        'body' => 'sellbuyfeedback.html'
        ));
$template->display('body');
include "footer.php";
?>