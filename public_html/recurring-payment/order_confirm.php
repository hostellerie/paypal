<?php

require_once '../../lib-common.php';

if (!in_array('paypal', $_PLUGINS)) {
    echo COM_refresh($_CONF['site_url'] . '/index.php');
    exit;
}

paypal_access_check('paypal.user');

$display = paypal_user_menu();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !SEC_checkToken()) {
    $display .= COM_showMessageText($LANG_PAYPAL_1['access_denied'], $LANG_PAYPAL_1['error']);
    COM_output(PAYPAL_createHTMLDocument($display));
    exit;
}

require_once $_CONF['path'] . 'plugins/paypal/lib/paypal_nvp.php';

$itemId = (int) PAYPAL_NVP_session('item_id', 0);
$payerId = PAYPAL_NVP_session('payer_id');
$token = PAYPAL_NVP_session('TOKEN');

if ($itemId <= 0 || $payerId === '' || $token === '') {
    $display .= COM_showMessageText($LANG_PAYPAL_1['save_fail'], $LANG_PAYPAL_1['error']);
    COM_output(PAYPAL_createHTMLDocument($display));
    exit;
}

$res = DB_query(
    "SELECT * FROM {$_TABLES['paypal_products']} "
    . "WHERE id = {$itemId} LIMIT 1"
);
$product = DB_fetchArray($res);

if (!is_array($product)
    || empty($product['id'])
    || $product['type'] !== 'recurrent'
    || (int) $product['active'] !== 1
    || SEC_hasAccess2($product) < 2
    || !PAYPAL_prepareRecurringSession($product, $_USER['uid'])) {
    $display .= COM_showMessageText($LANG_PAYPAL_1['wrong_type'], $LANG_PAYPAL_1['error']);
    COM_output(PAYPAL_createHTMLDocument($display));
    exit;
}

$initialAmount = (float) PAYPAL_NVP_session('Payment_Amount', 0);
$groupId = (int) PAYPAL_NVP_session('group_id', 0);
$currencyCode = PAYPAL_NVP_session('currencyCodeType');
$billingAmount = PAYPAL_NVP_session('BILLINGAMT');
$billingFrequency = (int) PAYPAL_NVP_session('BILLINGFREQUENCY', 0);
$billingPeriod = PAYPAL_NVP_session('BILLINGPERIOD');

$initialTransactionId = '';

if ($initialAmount > 0) {
    $paymentResponse = ConfirmPayment($initialAmount);
    $paymentAck = strtoupper(PAYPAL_NVP_responseValue($paymentResponse, 'ACK'));

    if ($paymentAck !== 'SUCCESS' && $paymentAck !== 'SUCCESSWITHWARNING') {
        $error = PAYPAL_NVP_responseValue(
            $paymentResponse,
            'L_LONGMESSAGE0',
            $LANG_PAYPAL_1['save_fail']
        );
        $display .= COM_showMessageText(
            htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'),
            $LANG_PAYPAL_1['error']
        );
        COM_output(PAYPAL_createHTMLDocument($display));
        exit;
    }

    $initialTransactionId = PAYPAL_NVP_responseValue(
        $paymentResponse,
        'PAYMENTINFO_0_TRANSACTIONID'
    );

    if ($initialTransactionId !== ''
        && DB_count($_TABLES['paypal_purchases'], 'txn_id', $initialTransactionId) == 0) {
        $items = array(1 => $itemId);
        $quantities = array(1 => 1);
        $prices = array(1 => number_format($initialAmount, 2, '.', ''));
        $names = array(1 => $product['name']);

        PAYPAL_handlePurchase(
            $items,
            $quantities,
            array(),
            $names,
            $prices,
            0,
            'complete',
            (int) $_USER['uid'],
            $initialTransactionId,
            date('Y-m-d H:i:s'),
            PAYPAL_NVP_responseValue($paymentResponse, 'PAYMENTINFO_0_TRANSACTIONTYPE', 'express_checkout'),
            PAYPAL_NVP_responseValue($paymentResponse, 'PAYMENTINFO_0_PAYMENTTYPE', 'instant')
        );
    }
}

$profileResponse = CreateRecurringPaymentsProfile();
$profileAck = strtoupper(PAYPAL_NVP_responseValue($profileResponse, 'ACK'));

if ($profileAck !== 'SUCCESS' && $profileAck !== 'SUCCESSWITHWARNING') {
    $error = PAYPAL_NVP_responseValue(
        $profileResponse,
        'L_LONGMESSAGE0',
        $LANG_PAYPAL_1['save_fail']
    );
    $display .= COM_showMessageText(
        htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'),
        $LANG_PAYPAL_1['error']
    );
    COM_output(PAYPAL_createHTMLDocument($display));
    exit;
}

$profileId = PAYPAL_NVP_responseValue($profileResponse, 'PROFILEID');
$profileStatus = PAYPAL_NVP_responseValue($profileResponse, 'PROFILESTATUS', 'ActiveProfile');

if ($profileId === '') {
    $display .= COM_showMessageText($LANG_PAYPAL_1['save_fail'], $LANG_PAYPAL_1['error']);
    COM_output(PAYPAL_createHTMLDocument($display));
    exit;
}

$safeProfileId = DB_escapeString($profileId);
if (DB_count($_TABLES['paypal_recurrent'], 'profileid', $profileId) == 0) {
    $safeStatus = DB_escapeString($profileStatus);
    DB_query(
        "INSERT INTO {$_TABLES['paypal_recurrent']} "
        . "SET profileid='{$safeProfileId}', recdate=NOW(), "
        . "status='{$safeStatus}', user_id=" . (int) $_USER['uid'] . ", "
        . "product_id={$itemId}, group_id={$groupId}"
    );
}

if ($groupId > 0) {
    PAYPAL_addToGroup($groupId, $_USER['uid']);
}

$display .= '<p>' . $LANG_PAYPAL_1['recurrent_has_been_set'] . ' '
    . $LANG_PAYPAL_1['will_pay'] . ' <strong>'
    . htmlspecialchars((string) $currencyCode, ENT_QUOTES, 'UTF-8') . ' '
    . htmlspecialchars((string) $billingAmount, ENT_QUOTES, 'UTF-8')
    . '</strong> ' . $LANG_PAYPAL_1['every'] . ' <strong>'
    . $billingFrequency . ' '
    . htmlspecialchars((string) $billingPeriod, ENT_QUOTES, 'UTF-8')
    . '</strong></p>';

// Prevent an accidental refresh from repeating the final step.
unset(
    $_SESSION['TOKEN'],
    $_SESSION['payer_id'],
    $_SESSION['Payment_Amount'],
    $_SESSION['item_id'],
    $_SESSION['group_id']
);

COM_output(PAYPAL_createHTMLDocument($display));
