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

include 'includes/config.inc.php';
include $include_path . 'auction_types.inc.php';
include $include_path . "countries.inc.php";
include $include_path . 'dates.inc.php';
include $include_path . "membertypes.inc.php";
include $main_path . "language/" . $language . "/categories.inc.php";
// Get parameters from the URL
foreach($membertypes as $idm => $memtypearr) {
    $memtypesarr[$memtypearr['feedbacks']] = $memtypearr;
}
ksort($memtypesarr, SORT_NUMERIC);

$id = (isset($_SESSION['CURRENT_ITEM'])) ? intval($_SESSION['CURRENT_ITEM']) : 0;
$id = (isset($_GET['id'])) ? intval($_GET['id']) : 0;
$id = (isset($_POST['id'])) ? intval($_POST['id']) : $id;
if (!is_numeric($id)) $id = 0;
$bidderarray = array();
$bidderarraynum = 1;

$_SESSION['CURRENT_ITEM'] = $id;
$_SESSION['REDIRECT_AFTER_LOGIN'] = $system->SETTINGS['siteurl'] . 'item.php?id=' . $id;
// get auction all needed data
$query = "SELECT a.*, ac.counter, u.nick, u.reg_date, u.country, u.zip FROM " . $DBPrefix . "auctions a
		LEFT JOIN " . $DBPrefix . "users u ON (u.id = a.user)
		LEFT JOIN " . $DBPrefix . "auccounter ac ON (ac.auction_id = a.id)
		WHERE a.id = " . $id . " LIMIT 1";
$result = mysql_query($query);
$system->check_mysql($result, $query, __LINE__, __FILE__);
if (mysql_num_rows($result) == 0) {
    header('location: index.php');
    exit;
}
$auction_data = mysql_fetch_assoc($result);
$category = $auction_data['category'];
$title = $auction_data['title'];
$description = $auction_data['description'];
$pict_url_plain = $auction_data['pict_url'];
$auction_type = $auction_data['auction_type'];
$ends = $auction_data['ends'];
$start = $auction_data['starts'];
$user_id = $auction_data['user'];
$minimum_bid = $auction_data['minimum_bid'];
$high_bid = $auction_data['current_bid'];
if($system->SETTINGS['datesformat'] == 'USA') {
	$seller_reg = gmdate('m/d/Y', $auction_data['reg_date'] + $system->tdiff);
} else {
	$seller_reg = gmdate('d/m/Y', $auction_data['reg_date'] + $system->tdiff);
}
// sort out counter
if (empty($auction_data['counter'])) {
    mysql_query("INSERT INTO " . $DBPrefix . "auccounter VALUES (" . $id . ", 1)");
    $auction_data['counter'] = 1;
} else {
    if (!in_array($id, $_SESSION['WEBID_VIEWED_AUCTIONS'])) {
        mysql_query("UPDATE " . $DBPrefix . "auccounter set counter = counter + 1 WHERE auction_id=" . $id);
        $_SESSION['WEBID_VIEWED_AUCTIONS'][] = $id;
    }
}

$difference = $ends - time();

// get watch item data
if (isset($_SESSION['WEBID_LOGGED_IN'])) {
    // Check if this item is not already added
    $query = "SELECT item_watch from " . $DBPrefix . "users WHERE id = " . $_SESSION['WEBID_LOGGED_IN'];
    $result = mysql_query($query);
    $system->check_mysql($result, $query, __LINE__, __FILE__);

    $watcheditems = trim(mysql_result ($result, 0, 'item_watch'));
    $auc_ids = split(' ', $watcheditems);
    if (in_array($id, $auc_ids)) {
        $watch_var = 'delete';
        $watch_string = $MSG['5202_0'];
    } else {
        $watch_var = 'add';
        $watch_string = $MSG['5202'];
    }
}
// get ending time
if ($difference > 0) {
    $ending_time = '';
    $d = 0;
    $days_difference = floor($difference / 86400);
    if ($days_difference > 0) {
        $daymsg = ($days_difference == 1) ? $MSG['126b'] : $MSG['126'];
        $ending_time .= $days_difference . ' ' . $daymsg . ' ';
        $d++;
    }
    $difference = $difference % 86400;
    $hours_difference = floor($difference / 3600);
    if ($hours_difference > 0) {
        $ending_time .= $hours_difference . $MSG['25_0037'] . ' ';
        $d++;
    }
    $difference = $difference % 3600;
    $minutes_difference = floor($difference / 60);
    $seconds_difference = $difference % 60;
    if ($minutes_difference > 0 && $d < 2) {
        $ending_time .= $minutes_difference . $MSG['25_0032'] . ' ';
        $d++;
    }
    if ($seconds_difference > 0 && $d < 2) {
        $ending_time .= $seconds_difference . $MSG['25_0033'];
    }
    $has_ended = false;
} else {
    $ending_time = '<span class="errfont">' . $MSG['911'] . '</span>';
    $has_ended = true;
}
// categories list
$c_name = array();
$c_id = array();
$cat_value = '';

$query = "SELECT cat_id, parent_id, cat_name FROM " . $DBPrefix . "categories WHERE cat_id = " . $auction_data['category'];
$result = mysql_query($query);
$system->check_mysql($result, $query, __LINE__, __FILE__);

$result = mysql_fetch_array ($result);
$parent_id = $result['parent_id'];
$cat_id = $categories;

$j = $auction_data['category'];
$i = 0;
do {
    $query = "SELECT cat_id, parent_id, cat_name FROM " . $DBPrefix . "categories WHERE cat_id = '$j'";
    $result = mysql_query($query);
    $system->check_mysql($result, $query, __LINE__, __FILE__);

    $catarr = mysql_fetch_array ($result);
    $parent_id = $catarr['parent_id'];
    $c_name[$i] = $category_names[$catarr['cat_id']];
    $c_id[$i] = $catarr['cat_id'];
    $i++;
    $j = $parent_id;
} while ($parent_id != 0);

for ($j = $i - 1; $j >= 0; $j--) {
    if ($j == 0) {
        $cat_value .= "<a href='" . $system->SETTINGS['siteurl'] . "browse.php?id=" . $c_id[$j] . "'>" . $c_name[$j] . "</a>";
    } else {
        $cat_value .= "<a href='" . $system->SETTINGS['siteurl'] . "browse.php?id=" . $c_id[$j] . "'>" . $c_name[$j] . "</a> / ";
    }
}
// get highest bid/bidder details
$query = "SELECT b.bid AS maxbid, b.bidder, u.nick, u.rate_sum FROM " . $DBPrefix . "bids b
		LEFT JOIN " . $DBPrefix . "users u ON (u.id = b.bidder)
		WHERE b.auction = " . $id . "
		ORDER BY b.bid DESC, b.id DESC LIMIT 1";
$result = mysql_query($query);
$system->check_mysql($result, $query, __LINE__, __FILE__);
$hbidder_data = mysql_fetch_assoc($result);
$num_bids = mysql_num_rows($result);

$userbid = false;
if (isset($_SESSION['WEBID_LOGGED_IN']) && $num_bids > 0) {
    // check if youve bid on this before
    $query = "SELECT bid FROM " . $DBPrefix . "bids WHERE auction = " . $id . " AND bidder = " . $_SESSION['WEBID_LOGGED_IN'] . " LIMIT 1";
    $result = mysql_query($query);
    $system->check_mysql($result, $query, __LINE__, __FILE__);
    if (mysql_num_rows($result) > 0) {
        if ($_SESSION['WEBID_LOGGED_IN'] == $hbidder_data['bidder']) {
            $yourbidmsg = $MSG['25_0088'];
            $yourbidclass = 'yourbidwin';
            if ($difference <= 0) {
                $yourbidmsg = $MSG['25_0089'];
            }
        } else {
            $yourbidmsg = $MSG['25_0087'];
            $yourbidclass = 'yourbidloss';
        }
        $userbid = true;
    }
}

$high_bid = ($num_bids == 0) ? $minimum_bid : $high_bid;

if ($auction_data['increment'] == 0) {
    // Get bid increment for current bid and calculate minimum bid
    $query = "SELECT increment FROM " . $DBPrefix . "increments WHERE
	((low <= $high_bid AND high >= $high_bid) OR
	(low < $high_bid AND high < $high_bid)) ORDER BY increment DESC";

    $result_incr = mysql_query($query);
    if (mysql_num_rows($result_incr) != 0) {
        $increment = mysql_result ($result_incr, 0, "increment");
    }
} else {
    $increment = $auction_data['increment'];
}

if ($atype == 2) {
    $increment = 0;
}

if ($customincrement > 0) {
    $increment = $customincrement;
}

if ($num_bids == 0 || $atype == 2) {
    $next_bidp = $minimum_bid;
} else {
    $next_bidp = $high_bid + $increment;
}

if ($num_bids > 0 && !isset($_GET['history'])) {
    $view_history = "(<a href='" . $system->SETTINGS['siteurl'] . "item.php?id=$id&history=view#history'>" . $MSG['105'] . "</a>)";
} elseif (isset($_GET['history'])) {
    $view_history = '(<a href="' . $system->SETTINGS['siteurl'] . 'item.php?id=' . $id . '">' . $MSG['507'] . '</a>)';
}
$min_bid = $system->print_money($minimum_bid);
$high_bid = $system->print_money($high_bid);
if ($difference > 0) {
    $next_bid = $system->print_money($next_bidp);
} else {
    $next_bid = '--';
}

if ($hbidder_data['rate_sum'] > 0) {
    $i = 0;
    foreach ($memtypesarr as $k => $l) {
        if ($k >= $hbidder_data['total_rate'] || $i++ == (count($memtypesarr) - 1)) {
            $buyer_rate_icon = $l['icon'];
            break;
        }
    }
}
// get seller feebacks
$query = "SELECT rated_user_id, rate FROM " . $DBPrefix . "feedbacks WHERE rated_user_id = " . $user_id;
$result = mysql_query($query);
$system->check_mysql($result, $query, __LINE__, __FILE__);
$num_feedbacks = mysql_num_rows ($result);
// count numbers
$fb_pos = $fb_neg = 0;
while ($fb_arr = mysql_fetch_assoc($result)) {
    if ($fb_arr['rate'] == 1) {
        $fb_pos++;
    } elseif ($fb_arr['rate'] == - 1) {
        $fb_neg++;
    }
}

$total_rate = $fb_pos - $fb_neg;

if ($total_rate > 0) {
    $i = 0;
    foreach ($memtypesarr as $k => $l) {
        if ($k >= $total_rate || $i++ == (count($memtypesarr) - 1)) {
            $seller_rate_icon = $l['icon'];
            break;
        }
    }
}

// Pictures Gellery
$K = 0;
if (file_exists($uploaded_path . $id)) {
    $dir = @opendir($uploaded_path . $id);
    if ($dir) {
        while ($file = @readdir($dir)) {
            if ($file != "." && $file != ".." && strpos($file, 'thumb-') === false) {
                $UPLOADED_PICTURES[$K] = $file;
                $K++;
            }
        }
        @closedir($dir);
    }
    $GALLERY_DIR = $id;

    if (is_array($UPLOADED_PICTURES)) {
        while (list($k, $v) = each($UPLOADED_PICTURES)) {
            $TMP = @getimagesize($uploaded_path . $id . '/' . $v);
            if ($TMP[2] >= 1 && $TMP[2] <= 3) {
                $template->assign_block_vars('gallery', array(
                        'V' => $v
                        ));
            }
        }
    }
}
// history
$query = "SELECT b.*, u.nick FROM " . $DBPrefix . "bids b
		LEFT JOIN " . $DBPrefix . "users u ON (u.id = b.bidder)
		WHERE b.auction = " . $id . " ORDER BY b.bid DESC, b.id DESC";
$result_numbids = mysql_query ($query);
$system->check_mysql($result_numbids, $query, __LINE__, __FILE__);
$num_bids = mysql_num_rows($result_numbids);
$i = 0;
while ($bidrec = mysql_fetch_assoc($result_numbids)) {
	// -- Format bid date
	$bidrec['bidwhen'] = ArrangeDateNoCorrection($bidrec['bidwhen'] + $system->tdiff) . ":" . gmdate('s', $bidrec['bidwhen']);
	$BGCOLOR = (!($i % 2)) ? '' : 'class="alt-row"';
	if(!isset($bidderarray[$bidrec['nick']])) {
		if($system->SETTINGS['buyerprivacy'] == 'y' && $_SESSION['WEBID_LOGGED_IN'] != $auction_data['user'] && $_SESSION['WEBID_LOGGED_IN'] != $bidrec['bidder']) {
			$bidderarray[$bidrec['nick']] = $MSG['176'] . ' ' . $bidderarraynum;
			$bidderarraynum++;
		} else {
			$bidderarray[$bidrec['nick']] = $bidrec['nick'];
		}
	}
	$template->assign_block_vars('bidhistory', array(
			'BGCOLOUR' => $BGCOLOR,
			'ID' => $bidrec['bidder'],
			'NAME' => $bidderarray[$bidrec['nick']],
			'BID' => $system->print_money($bidrec['bid']),
			'WHEN' => $bidrec['bidwhen'],
			'QTY' => $bidrec['quantity']
			));
}

if (!$has_ended) {
    $bn_link = ' <a href="' . $system->SETTINGS['siteurl'] . 'buy_now.php?id=' . $id . '"><img border="0" align="absbottom" alt="' . $MSG['496'] . '" src="' . $system->SETTINGS['siteurl'] . 'images/buy_it_now.gif"></a>';
}

$template->assign_vars(array(
        'ID' => $auction_data['id'],
        'TITLE' => $auction_data['title'],
        'AUCTION_DESCRIPTION' => stripslashes($auction_data['description']),
        'PIC_URL' => $uploaded_path . $id . "/" . $pict_url_plain,
        'SHIPPING_COST' => ($auction_data['shipping_cost'] > 0) ? $system->print_money($auction_data['shipping_cost']) : $system->print_money($auction_data['shipping_cost']),
        'COUNTRY' => $auction_data['country'],
        'ZIP' => $auction_data['zip'],
        'QTY' => $auction_data['quantity'],
        'ENDS' => $ending_time,
        'STARTTIME' => ArrangeDateNoCorrection($start + $system->tdiff),
        'ENDTIME' => ArrangeDateNoCorrection($ends + $system->tdiff),
        'BUYNOW1' => $auction_data['buy_now'],
        'BUYNOW2' => ($auction_data['buy_now'] > 0) ? $system->print_money($auction_data['buy_now']) . $bn_link : $system->print_money($auction_data['buy_now']),
        'NUMBIDS' => $num_bids,
        'MINBID' => $min_bid,
        'MAXBID' => $high_bid,
        'NEXTBID' => $next_bid,
        'PNEXTBID' => $next_bidp,
        'INTERNATIONAL' => ($auction_data['international'] == 1) ? $MSG['033'] : $MSG['043'],
        'SHIPPING' => ($auction_data['shipping'] == '1') ? $MSG['031'] : $MSG['032'],
        'SHIPPINGTERMS' => nl2br($auction_data['shipping_terms']),
        'PAYMENTS' => str_replace("\n", ', ', $auction_data['payment']),
        'AUCTION_VIEWS' => $auction_data['counter'],
        'AUCTION_TYPE' => $auction_types[$auction_type],
        'ATYPE' => $auction_type,
        'THUMBWIDTH' => $system->SETTINGS['thumb_show'],
        'VIEW_HISTORY1' => (empty($view_history)) ? '' : $view_history . ' | ',
        'VIEW_HISTORY2' => $view_history,
        'CATSPATH' => $cat_value,
        'CAT_ID' => $auction_data['category'],
        'UPLOADEDPATH' => $uploaded_path,

        'SELLER_REG' => $seller_reg,
        'SELLER_ID' => $auction_data['user'],
        'SELLER_NICK' => $auction_data['nick'],
        'SELLER_TOTALFB' => $total_rate,
        'SELLER_FBICON' => (!empty($seller_rate_icon) && $seller_rate_icon != 'transparent.gif') ? '<img src="' . $system->SETTINGS['siteurl'] . 'images/icons/' . $seller_rate_icon . '" alt="' . $seller_rate_icon . '" class="fbstar">' : '',
        'SELLER_NUMFB' => $num_feedbacks,
        'SELLER_FBPOS' => ($num_feedbacks > 0) ? "(" . ceil($fb_pos * 100 / $total_rate) . "%)" : '100%',
        'SELLER_FBNEG' => ($fb_neg > 0) ? $MSG['5507'] . " (" . ceil($fb_neg * 100 / $total_rate) . "%)" : '0',

        'BUYER_ID' => $hbidder_data['bidder'],
        'BUYER_NAME' => $bidderarray[$hbidder_data['nick']],
        'BUYER_FB' => $hbidder_data['rate_sum'],
        'BUYER_FB_ICON' => (!empty($buyer_rate_icon) && $buyer_rate_icon != 'transparent.gif') ? '<img src="' . $system->SETTINGS['siteurl'] . 'images/icons/' . $buyer_rate_icon . '" alt="' . $buyer_rate_icon . '" class="fbstar">' : '',

        'WATCH_VAR' => $watch_var,
        'WATCH_STRING' => $watch_string,

        'YOURBIDMSG' => $yourbidmsg,
        'YOURBIDCLASS' => $yourbidclass,
		'BIDURL' => ($system->SETTINGS['usersauth'] == 'y' && $system->SETTINGS['https'] == 'y') ? str_replace('http://', 'https://', $system->SETTINGS['siteurl']) : $system->SETTINGS['siteurl'],

        'B_HASENDED' => $has_ended,
        'B_CANEDIT' => ($_SESSION['WEBID_LOGGED_IN'] == $auction_data['user'] && $num_bids == 0 && $difference > 0),
        'B_CANCONTACTSELLER' => ($system->SETTINGS['contactseller'] == 'always' || ($system->SETTINGS['contactseller'] == 'logged' && isset($_SESSION['WEBID_LOGGED_IN']))),
        'B_HASIMAGE' => (!empty($pict_url_plain)),
        'B_NOTBNONLY' => ($auction_data['bn_only'] == 'n'),
        'B_HASRESERVE' => ($auction_data['reserve_price'] > 0 && $auction_data['reserve_price'] > $auction_data['current_bid']),
        'B_BNENABLED' => ($system->SETTINGS['buy_now'] == 2),
        'B_HASGALELRY' => (count($UPLOADED_PICTURES) > 0),
        'B_SHOWHISTORY' => (isset($_GET['history']) && $num_bids > 0),
        'B_BUY_NOW' => ($auction_data['buy_now'] > 0 && ($auction_data['bn_only'] == 'y' || $auction_data['bn_only'] == 'n' && ($auction_data['num_bids'] == 0 || ($auction_data['reserve_price'] > 0 && $auction_data['current_bid'] < $auction_data['reserve_price'])))),
        'B_BUY_NOW_ONLY' => ($auction_data['bn_only'] == 'y'),
        'B_USERBID' => $userbid,
		'B_BIDDERPRIV' => ($system->SETTINGS['buyerprivacy'] == 'y' && $_SESSION['WEBID_LOGGED_IN'] != $auction_data['user'])
        ));

require("header.php");
$template->set_filenames(array(
        'body' => 'item.html'
        ));
$template->display('body');
include "footer.php";

?>