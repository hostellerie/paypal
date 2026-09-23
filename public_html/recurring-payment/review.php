<?php

require_once '../../lib-common.php';

if (!in_array('paypal', $_PLUGINS)) {
    echo COM_refresh($_CONF['site_url'] . '/index.php');
    exit;
}

paypal_access_check('paypal.user');

$vars = array(
    'token' => 'text',
);
paypal_filterVars($vars, $_REQUEST);

$display = paypal_user_menu();

require_once $_CONF['path'] . 'plugins/paypal/lib/paypal_nvp.php';

$token = isset($_REQUEST['token']) ? (string) $_REQUEST['token'] : '';
$sessionToken = PAYPAL_NVP_session('TOKEN');

if ($token === ''
    || $sessionToken === ''
    || !hash_equals((string) $sessionToken, (string) $token)) {
    $display .= COM_showMessageText($LANG_PAYPAL_1['access_denied'], $LANG_PAYPAL_1['error']);
    COM_output(PAYPAL_createHTMLDocument($display));
    exit;
}

$response = GetShippingDetails($token);
$ack = strtoupper(PAYPAL_NVP_responseValue($response, 'ACK'));

if ($ack !== 'SUCCESS' && $ack !== 'SUCCESSWITHWARNING') {
    $error = PAYPAL_NVP_responseValue(
        $response,
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

$firstName = PAYPAL_NVP_responseValue($response, 'FIRSTNAME');
$lastName = PAYPAL_NVP_responseValue($response, 'LASTNAME');
$shipToName = PAYPAL_NVP_responseValue($response, 'SHIPTONAME');
$shipToStreet = PAYPAL_NVP_responseValue($response, 'SHIPTOSTREET');
$shipToStreet2 = PAYPAL_NVP_responseValue($response, 'SHIPTOSTREET2');
$shipToCity = PAYPAL_NVP_responseValue($response, 'SHIPTOCITY');
$shipToState = PAYPAL_NVP_responseValue($response, 'SHIPTOSTATE');
$shipToCountry = PAYPAL_NVP_responseValue($response, 'SHIPTOCOUNTRYNAME');
$shipToZip = PAYPAL_NVP_responseValue($response, 'SHIPTOZIP');

if ($_USER['uid'] > 1) {
    PAYPAL_updateUserDetails(
        (int) $_USER['uid'],
        array(
            'user_name' => $shipToName,
            'user_street1' => $shipToStreet,
            'user_street2' => $shipToStreet2,
            'user_postal' => $shipToZip,
            'user_city' => $shipToCity,
            'user_country' => $shipToCountry,
            'user_contact' => trim($firstName . ' ' . $lastName),
        ),
        true
    );
}

$currency = htmlspecialchars(
    (string) PAYPAL_NVP_session('currencyCodeType'),
    ENT_QUOTES,
    'UTF-8'
);
$initialAmount = (float) PAYPAL_NVP_session('Payment_Amount', 0);
$billingAmount = htmlspecialchars(
    (string) PAYPAL_NVP_session('BILLINGAMT'),
    ENT_QUOTES,
    'UTF-8'
);
$frequency = (int) PAYPAL_NVP_session('BILLINGFREQUENCY', 0);
$period = htmlspecialchars(
    (string) PAYPAL_NVP_session('BILLINGPERIOD'),
    ENT_QUOTES,
    'UTF-8'
);
$description = htmlspecialchars(
    (string) PAYPAL_NVP_session('BILLINGDESCRIPTION'),
    ENT_QUOTES,
    'UTF-8'
);
$customerName = htmlspecialchars(
    trim($lastName . ' ' . $firstName),
    ENT_QUOTES,
    'UTF-8'
);

$onetime = $LANG_PAYPAL_1['will_pay'];
if ($initialAmount > 0) {
    $onetime = $LANG_PAYPAL_1['will_pay_once']
        . ' <strong>' . $currency . ' '
        . number_format($initialAmount, 2, '.', '') . '</strong> '
        . $LANG_PAYPAL_1['and'];
}

$csrfToken = SEC_createToken();

$display .= '<section class="paypal-recurring-review">'
    . '<h2>' . $LANG_PAYPAL_1['confirm_informations'] . '</h2>'
    . '<p>' . $LANG_PAYPAL_1['info_name'] . ' <strong>' . $customerName . '</strong></p>'
    . '<p>' . $onetime . ' <strong>' . $currency . ' ' . $billingAmount . '</strong> '
    . $LANG_PAYPAL_1['every'] . ' <strong>' . $frequency . ' ' . $period . '</strong> '
    . $LANG_PAYPAL_1['for'] . ' <strong>' . $description . '</strong></p>'
    . '<form action="' . $_PAY_CONF['site_url'] . '/recurring-payment/order_confirm.php" method="post">'
    . '<input type="hidden" name="' . CSRF_TOKEN . '" value="'
    . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">'
    . '<button type="submit">' . $LANG_PAYPAL_1['review'] . '</button>'
    . '</form>'
    . '</section>';

COM_output(PAYPAL_createHTMLDocument($display));
