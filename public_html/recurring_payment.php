<?php

require_once '../lib-common.php';

if (!in_array('paypal', $_PLUGINS)) {
    echo COM_refresh($_CONF['site_url'] . '/index.php');
    exit;
}

paypal_access_check('paypal.user');

$vars = array(
    'msg' => 'text',
    'pid' => 'number',
);
paypal_filterVars($vars, $_REQUEST);

$display = paypal_user_menu();
$productId = isset($_REQUEST['pid']) ? (int) $_REQUEST['pid'] : 0;

if ($productId <= 0) {
    $display .= COM_showMessageText($LANG_PAYPAL_1['wrong_type'], $LANG_PAYPAL_1['error']);
    COM_output(PAYPAL_createHTMLDocument($display));
    exit;
}

$res = DB_query(
    "SELECT * FROM {$_TABLES['paypal_products']} "
    . "WHERE id = {$productId} LIMIT 1"
);
$product = DB_fetchArray($res);

if (!is_array($product)
    || empty($product['id'])
    || $product['type'] !== 'recurrent'
    || (int) $product['active'] !== 1
    || SEC_hasAccess2($product) < 2) {
    $display .= COM_showMessageText($LANG_PAYPAL_1['wrong_type'], $LANG_PAYPAL_1['error']);
    COM_output(PAYPAL_createHTMLDocument($display));
    exit;
}

require_once $_CONF['path'] . 'plugins/paypal/lib/paypal_nvp.php';

$response = PAYPAL_beginRecurringCheckout($product, $_USER['uid']);
$ack = strtoupper(PAYPAL_NVP_responseValue($response, 'ACK'));

if ($ack === 'SUCCESS' || $ack === 'SUCCESSWITHWARNING') {
    RedirectToPayPal(PAYPAL_NVP_responseValue($response, 'TOKEN'));
}

$error = PAYPAL_NVP_responseValue($response, 'L_LONGMESSAGE0', $LANG_PAYPAL_1['save_fail']);
$display .= COM_showMessageText(
    htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'),
    $LANG_PAYPAL_1['error']
);

COM_output(PAYPAL_createHTMLDocument($display));
