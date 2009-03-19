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

include "includes/config.inc.php";
include $include_path . "auctionstoshow.inc.php";

// If user is not logged in redirect to login page
if (!isset($_SESSION['WEBID_LOGGED_IN'])) {
    header("Location: user_login.php");
    exit;
}

// DELETE OPEN AUCTIONS
$NOW = time();
$NOWB = gmdate('Ymd');

// Update
if (isset($_POST['action']) && $_POST['action'] == "update") {
    // Delete auction
    if (is_array($_POST['delete'])) {
        while (list($k, $v) = each($_POST['delete'])) {
            $v = intval($v);
            // Pictures Gallery
            if (file_exists($upload_path . $v)) {
                if ($dir = @opendir($upload_path . $v)) {
                    while ($file = readdir($dir)) {
                        if ($file != "." && $file != "..") {
                            unlink($upload_path . $v . '/' . $file);
                        }
                    }
                    closedir($dir);
                    @rmdir($upload_path . $v);
                }
            }
            $query = "SELECT photo_uploaded, pict_url FROM " . $DBPrefix . "auctions WHERE id = " . $v;
            $res_ = mysql_query($query);
            $system->check_mysql($res_, $query, __LINE__, __FILE__);

            if (mysql_num_rows($res_) > 0) {
                $pict_url = mysql_result($res_, 0, 'pict_url');
                $photo_uploaded = mysql_result($res_, 0, 'photo_uploaded');
                // Uploaded picture
                if ($photo_uploaded) {
                    @unlink($upload_path . $pict_url);
                }
            }
            $query = "UPDATE " . $DBPrefix . "counters SET closedauctions = (closedauctions - 1)";
            $system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
			$query = "DELETE FROM " . $DBPrefix . "auctioninvitedlists WHERE auction_id = " . $v;
            $system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
            $query = "DELETE FROM " . $DBPrefix . "auctionblacklists WHERE auction_id = " . $v;
			$system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
            $query = "DELETE FROM " . $DBPrefix . "auccounter WHERE auction_id = " . $v;
			$system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
            $query = "DELETE FROM " . $DBPrefix . "auctions WHERE id=" . $v;
            $system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
            // Bids
			$query = "SELECT * FROM " . $DBPrefix . "bids WHERE auction = " . $v;
            $decremsql = mysql_query($query);
			$system->check_mysql($decremsql, $query, __LINE__, __FILE__);
            $decrem = mysql_num_rows($decremsql);
            $query = "DELETE FROM " . $DBPrefix . "bids WHERE auction = " . $v;
            $system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
            // Proxy Bids
            $query = "DELETE FROM " . $DBPrefix . "proxybid WHERE itemid = " . $v;
            $system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
        }
    }
    if (is_array($_POST['sell'])) {
        while (list($k, $v) = each($sell)) {
			$query = "UPDATE " . $DBPrefix . "auctions SET sold = 's' WHERE id = " . intval($k);
            $system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
        }
        include('cron.php');
    }
    // Re-list auctions
    if (is_array($_POST['relist'])) {
        unset($RELISTED_TITLE);
        while (list($k, $v) = each($_POST['relist'])) {
			$k = intval($k);
			$query = "SELECT * FROM " . $DBPrefix . "auctions WHERE id = " . $k;
			$res = mysql_query($query);
			$system->check_mysql($res, $query, __LINE__, __FILE__);
            $AUCTION = mysql_fetch_array($res);

            // auction ends
            $WILLEND = time() + ($_POST['duration'][$k] * 24 * 60 * 60);

            $query = "UPDATE " . $DBPrefix . "auctions
                  SET starts = '" . $NOW . "',
                  ends = '" . $WILLEND . "',
                  duration = '" . $_POST['duration'][$k] . "',
                  closed = 0,
                  num_bids = 0,
                  relisted = relisted + 1,
                  current_bid = 0,
                  sold = 'n'
                  WHERE id = " . $k;
            $system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);

            // Insert into relisted table
            $query = "INSERT INTO " . $DBPrefix . "closedrelisted VALUES (" . $k . ", '" . $NOWB . "', '" . $k . "')";
            $system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);

            $query = "DELETE FROM " . $DBPrefix . "bids WHERE auction = " . $k;
            $system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
            // Proxy Bids
            $query = "DELETE FROM " . $DBPrefix . "proxybid WHERE itemid = " . $k;
            $system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
            // Winners: only in case of reserve not reached
            $query = "DELETE FROM " . $DBPrefix . "winners WHERE auction = " . $k;
            $system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
            unset($_SESSION['EDITED_AUCTIONS']);
            // Update COUNTERS table
            $query = "UPDATE " . $DBPrefix . "counters SET auctions = (auctions + 1)";
            $system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
            // and increase category counters
			$query = "SELECT * FROM " . $DBPrefix . "categories WHERE cat_id = " . $AUCTION['category'];
			$res = mysql_query($query);
			 $system->check_mysql($res, $query, __LINE__, __FILE__);
            $row = mysql_fetch_array($res);
            $counter = $row['counter'] + 1;
            $subcoun = $row['sub_counter'] + 1;
            $parent_id = $row['parent_id'];
			$query = "UPDATE " . $DBPrefix . "categories SET counter = " . $counter . ", sub_counter = " . $subcoun . " WHERE cat_id = " . $AUCTION['category'];
            $system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
            // update recursive categories
            while ($parent_id != 0) {
                // update this parent's subcounter
				$query = "SELECT * FROM " . $DBPrefix . "categories WHERE cat_id = " . $parent_id;
				$res = mysql_query($query);
				$system->check_mysql($res, $query, __LINE__, __FILE__);
                $rw = mysql_fetch_array($res);
                $subcoun = $rw['sub_counter'] + 1;
				$query = "UPDATE " . $DBPrefix . "categories SET sub_counter = " . $subcoun . " WHERE cat_id = " . $parent_id;
                $system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
                // get next parent
                $parent_id = intval($rw['parent_id']);
            }

            $RELISTED_TITLE[$AUCTION['id']] = $AUCTION['title'];
            unset($_SESSION['CLOSED_EDITED']);
        }
    }
}

// Retrieve closed auction data from the database
$query = "SELECT COUNT(id) AS COUNT FROM " . $DBPrefix . "auctions
		WHERE user = '" . $_SESSION['WEBID_LOGGED_IN'] . "'
		AND closed = 1 AND suspended != 8
		AND (num_bids = 0 OR (num_bids > 0 AND current_bid < reserve_price AND sold = 'n'))";
$res = mysql_query($query);
$system->check_mysql($res, $query, __LINE__, __FILE__);
$TOTALAUCTIONS = mysql_result($res, 0, 'COUNT');

if (!isset($_GET['PAGE']) || $_GET['PAGE'] == 1) {
    $OFFSET = 0;
    $PAGE = 1;
} else {
    $PAGE = $_GET['PAGE'];
    $OFFSET = ($PAGE - 1) * $LIMIT;
}
$PAGES = ceil($TOTALAUCTIONS / $LIMIT);
if (!$PAGES) $PAGES = 1;
$_SESSION['backtolist_page'] = $PAGE;
// Handle columns sorting variables
if (!isset($_SESSION['ca_ord']) && empty($_GET['ca_ord'])) {
    $_SESSION['ca_ord'] = 'title';
    $_SESSION['ca_type'] = 'asc';
} elseif (!empty($_GET['ca_ord'])) {
    $_SESSION['ca_ord'] = str_replace('..', '', addslashes(htmlspecialchars($_GET['ca_ord'])));
    $_SESSION['ca_type'] = str_replace('..', '', addslashes(htmlspecialchars($_GET['ca_type'])));
} elseif (isset($_SESSION['ca_ord']) && empty($_GET['ca_ord'])) {
    $_SESSION['ca_nexttype'] = $_SESSION['ca_type'];
}
if ($_SESSION['ca_nexttype'] == 'desc') {
    $_SESSION['ca_nexttype'] = 'asc';
} else {
    $_SESSION['ca_nexttype'] = 'desc';
}
if ($_SESSION['ca_type'] == 'desc') {
    $_SESSION['ca_type_img'] = '<img src="images/arrow_up.gif" align="center" hspace="2" border="0">';
} else {
    $_SESSION['ca_type_img'] = '<img src="images/arrow_down.gif" align="center" hspace="2" border="0">';
}

$query = "SELECT *  FROM " . $DBPrefix . "auctions
    WHERE user = '" . $_SESSION['WEBID_LOGGED_IN'] . "'
    AND closed = 1 AND suspended != 8
	AND (num_bids = 0 OR (num_bids > 0 AND reserve_price > 0 AND current_bid < reserve_price AND sold = 'n'))
    ORDER BY " . $_SESSION['ca_ord'] . " " . $_SESSION['ca_type'] . " LIMIT $OFFSET, $LIMIT";
$res = mysql_query($query);
$system->check_mysql($res, $query, __LINE__, __FILE__);

$i = 0;
while ($item = mysql_fetch_array($res)) {
    $canrelist = false;
    if (($item['current_bid'] > $item['reserve_price'])) {
        $cansell = false;
    } else {
        if ($item['reserve_price'] > 0 || $item['num_bids'] == 0) {
            $canrelist = true;
        }
        if ($item['reserve_price'] > 0 || $item['num_bids'] > 0) {
			$cansell = true;
        } else $cansell = false;
    }

    $template->assign_block_vars('items', array(
            'BGCOLOUR' => ($i % 2) ? '#FFCCFF' : '#EEEEEE',
            'ID' => $item['id'],
            'TITLE' => $item['title'],
            'STARTS' => FormatDate($item['starts']),
            'ENDS' => FormatDate($item['ends']),
            'BID' => ($item['current_bid'] == 0) ? '-' : $system->print_money($item['current_bid']),
            'BIDS' => $item['num_bids'],

            'B_CANRELIST' => $canrelist,
            'B_CANSSELL' => $cansell,
            'B_HASNOBIDS' => ($item['current_bid'] == 0)
            ));

    $i++;
}

// get pagenation
$PREV = intval($PAGE - 1);
$NEXT = intval($PAGE + 1);
if ($PAGES > 1) {
    $LOW = $PAGE - 5;
    if ($LOW <= 0) $LOW = 1;
    $COUNTER = $LOW;
    while ($COUNTER <= $PAGES && $COUNTER < ($PAGE + 6)) {
        $template->assign_block_vars('pages', array(
                'PAGE' => ($PAGE == $COUNTER) ? '<b>' . $COUNTER . '</b>' : '<a href="' . $system->SETTINGS['siteurl'] . 'yourauctions_c.php?PAGE=' . $COUNTER . '&id=' . $id . '"><u>' . $COUNTER . '</u></a>'
                ));
        $COUNTER++;
    }
}

$template->assign_vars(array(
        'BGCOLOUR' => ($i % 2) ? '#FFCCFF' : '#EEEEEE',
        'TBLHEADERCOLOUR' => $system->SETTINGS['tableheadercolor'],
        'ORDERCOL' => $_SESSION['ca_ord'],
        'ORDERNEXT' => $_SESSION['ca_nexttype'],
        'ORDERTYPEIMG' => $_SESSION['ca_type_img'],

        'PREV' => ($PAGES > 1 && $PAGE > 1) ? '<a href="' . $system->SETTINGS['siteurl'] . 'yourauctions_c.php?PAGE=' . $PREV . '&id=' . $id . '"><u>' . $MSG['5119'] . '</u></a>&nbsp;&nbsp;' : '',
        'NEXT' => ($PAGE < $PAGES) ? '<a href="' . $system->SETTINGS['siteurl'] . 'yourauctions_c.php?PAGE=' . $NEXT . '&id=' . $id . '"><u>' . $MSG['5120'] . '</u></a>' : '',
        'PAGE' => $PAGE,
        'PAGES' => $PAGES,

        'B_AREITEMS' => ($i > 0)
        ));

include "header.php";
$TMP_usmenutitle = $MSG['354'];
include "includes/user_cp.php";
$template->set_filenames(array(
        'body' => 'yourauctions_c.html'
        ));
$template->display('body');
include "footer.php";

?>