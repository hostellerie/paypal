<?php
// +--------------------------------------------------------------------------+
// | PayPal Plugin 1.6 - geeklog CMS                                              |
// +--------------------------------------------------------------------------+
// | buy_now.php                                                              |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2010-2014 by the following authors:                        |
// |                                                                          |
// | Authors: ::Ben - cordiste AT free DOT fr                                 |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | This program is free software; you can redistribute it and/or            |
// | modify it under the terms of the GNU General Public License              |
// | as published by the Free Software Foundation; either version 2           |
// | of the License, or (at your option) any later version.                   |
// |                                                                          |
// | This program is distributed in the hope that it will be useful,          |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
// | GNU General Public License for more details.                             |
// |                                                                          |
// | You should have received a copy of the GNU General Public License        |
// | along with this program; if not, write to the Free Software Foundation,  |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.          |
// |                                                                          |
// +--------------------------------------------------------------------------+

/**
 * require core geeklog code
 */
require_once '../lib-common.php';

// Incoming variable filter
$vars = array('item_number' => 'number',
              'amount' => 'text',
			  'shipping' => 'number'
			 );
paypal_filterVars($vars, $_POST);

/* Ensure sufficient privs to read this page */
paypal_access_check('paypal.user');


$valid_process = true;
$display = '';
$req = '';
$item_id = isset($_POST['item_number']) ? $_POST['item_number'] : '';
$item_price = 0.0;
$paypalHost = (isset($_PAY_CONF['paypalURL']) && stripos($_PAY_CONF['paypalURL'], 'sandbox') !== false)
    ? 'www.sandbox.paypal.com'
    : 'www.paypal.com';
$paypalURL = 'https://' . $paypalHost . '/cgi-bin/webscr?cmd=_xclick';


/* MAIN */

$display .= paypal_user_menu();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION["user_id"] = $_USER['uid'];
$_SESSION["item_id"] = $item_id;

$A = array();
if ($item_id > 0) {
    $A = DB_fetchArray(
        DB_query(
            "SELECT * FROM {$_TABLES['paypal_products']} "
            . "WHERE id = " . (int) $item_id . " LIMIT 1"
        )
    );
}
if (!is_array($A) || empty($A['id'])) {
    $display .= $jcart['text']['checkout_error'];
    COM_output(PAYPAL_createHTMLDocument($display));
    exit;
}
$item_price = (float) PAYPAL_productPrice($A);

if ($A['type'] == 'recurrent') {
    require_once $_CONF['path'] . 'plugins/paypal/lib/paypal_nvp.php';

    $resArray = PAYPAL_beginRecurringCheckout($A, $_USER['uid']);
    $ack = strtoupper(PAYPAL_NVP_responseValue($resArray, 'ACK'));

    if ($ack === 'SUCCESS' || $ack === 'SUCCESSWITHWARNING') {
        RedirectToPayPal(PAYPAL_NVP_responseValue($resArray, 'TOKEN'));
    }

    $errorCode = PAYPAL_NVP_responseValue($resArray, 'L_ERRORCODE0');
    $errorShort = PAYPAL_NVP_responseValue($resArray, 'L_SHORTMESSAGE0');
    $errorLong = PAYPAL_NVP_responseValue($resArray, 'L_LONGMESSAGE0');
    $errorSeverity = PAYPAL_NVP_responseValue($resArray, 'L_SEVERITYCODE0');

    if (!empty($_SESSION['curl_error_no'])) {
        $errorCode = $_SESSION['curl_error_no'];
    }
    if (!empty($_SESSION['curl_error_msg'])) {
        $errorLong = $_SESSION['curl_error_msg'];
    }

    $display .= '<p>SetExpressCheckout API call failed.</p>'
        . '<p>' . htmlspecialchars((string) $errorLong, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>' . htmlspecialchars((string) $errorShort, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>' . htmlspecialchars((string) $errorCode, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>' . htmlspecialchars((string) $errorSeverity, ENT_QUOTES, 'UTF-8') . '</p>';

} else {

    if (SEC_hasAccess2($A) < 2 || (int) $A['active'] !== 1) {
        $valid_process = false;
    }

	$PAYPAL_POST['business'] = $_PAY_CONF['receiverEmailAddr'];
	$PAYPAL_POST['item_name'] = $A['name'];
	$PAYPAL_POST['custom'] = $_USER['uid'];
	$PAYPAL_POST['item_number'] = $A['id'];
    $PAYPAL_POST['amount'] = number_format($item_price, 2, '.', '');
	$PAYPAL_POST['no_note'] = '1';
	$PAYPAL_POST['currency_code'] = $_PAY_CONF['currency'];
	$PAYPAL_POST['return'] = $_PAY_CONF['site_url'] . '/index.php?mode=endTransaction';
	$PAYPAL_POST['notify_url'] = $_PAY_CONF['site_url'] . '/ipn.php';
	//TODO how to choose shipping cost? Do not use Buy now button...
	$PAYPAL_POST['handling_cart'] = isset($_POST['shipping']) ? $_POST['shipping'] : 0;
	$PAYPAL_POST['rm'] = '2';
	$PAYPAL_POST['cbt'] = $LANG_PAYPAL_1['cbt'] . ' ' . $_CONF['site_name'];
	$PAYPAL_POST['cancel_return'] = $_PAY_CONF['site_url'] . '/index.php?mode=cancel';
	$PAYPAL_POST['image_url'] = $_PAY_CONF['image_url'];
	$PAYPAL_POST['cpp_header_image'] = $_PAY_CONF['cpp_header_image'];
	$PAYPAL_POST['cpp_headerback_color'] = $_PAY_CONF['cpp_headerback_color'];
	$PAYPAL_POST['cpp_headerborder_color'] = $_PAY_CONF['cpp_headerborder_color'];
	$PAYPAL_POST['cpp_payflow_color'] = $_PAY_CONF['cpp_payflow_color'];
	$PAYPAL_POST['cs'] = $_PAY_CONF['cs'];
	$PAYPAL_POST['charset'] = $_CONF['default_charset'];


	foreach ($PAYPAL_POST as $key => $value) {
        $value = rawurlencode((string) $value);
		$req .= "&$key=$value";
	}

	if ($valid_process) {
		header('Location:'. $paypalURL .$req);
		exit;
	} else {
		$display .= $jcart['text']['checkout_error'];
	}
}

COM_output(PAYPAL_createHTMLDocument($display));

?>