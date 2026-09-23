<?php

function PAYPAL_notifyExpiration($data)
{
    global $_TABLES, $_PAY_CONF, $_CONF, $LANG_PAYPAL_EMAIL;

    if (!is_array($data)) {
        return;
    }

    $level = isset($data['notification']) ? ((int) $data['notification'] + 1) : 1;
    if ($level > 3) {
        $level = 3;
    }

    switch ($level) {
        case 1:
            $subject = $LANG_PAYPAL_EMAIL['membership_expire_soon'];
            $body = $LANG_PAYPAL_EMAIL['membership_expire_soon_txt'];
            break;
        case 2:
            $subject = $LANG_PAYPAL_EMAIL['membership_expire_today'];
            $body = $LANG_PAYPAL_EMAIL['membership_expire_today_txt'];
            break;
        default:
            $subject = $LANG_PAYPAL_EMAIL['membership_expired'];
            $body = $LANG_PAYPAL_EMAIL['membership_expired_txt'];
            break;
    }

    $userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;
    $productId = isset($data['product_id']) ? (int) $data['product_id'] : 0;
    $expiration = isset($data['expiration']) ? $data['expiration'] : '';
    $email = isset($data['email']) ? $data['email'] : '';

    $author = COM_getDisplayName($userId);
    $date = COM_getUserDateTimeFormat($expiration);
    $product = DB_getItem($_TABLES['paypal_products'], 'name', "id={$productId}");

    $mailSubject = '[' . $_CONF['site_name'] . '] ' . $subject;
    $adminSubject = '[' . $_CONF['site_name'] . '] ' . $LANG_PAYPAL_EMAIL['membership_expiration'];

    $mailBody = $LANG_PAYPAL_EMAIL['hello'] . ' ' . $author . ",\n\n";
    $mailBody .= $body . "\n\n";
    $mailBody .= $product . "\n";
    $mailBody .= $LANG_PAYPAL_EMAIL['membership_expire_date'] . ' ' . $date[0] . "\n\n";
    $mailBody .= $LANG_PAYPAL_EMAIL['thanks'] . "\n";
    $mailBody .= $LANG_PAYPAL_EMAIL['sign'] . "\n";
    $mailBody .= "\n------------------------------\n " . $_CONF['site_url'] . " \n------------------------------\n";

    if ($email !== '') {
        COM_mail($email, $mailSubject, $mailBody, $_PAY_CONF['receiverEmailAddr']);
    }

    if ($level === 3) {
        $adminBody = $LANG_PAYPAL_EMAIL['member'] . $author . ' (uid:' . $userId . ') | '
            . $product . ' | ' . $LANG_PAYPAL_EMAIL['membership_expire_date'] . ' ' . $date[0] . "\n\n";
        COM_mail($_CONF['site_mail'], $adminSubject, $adminBody);
    }

    COM_errorLog(
        'PayPal subscription expiration notification: user=' . $userId
        . ' subscription=' . (isset($data['name']) ? $data['name'] : '')
        . ' level=' . $level
        . ' email=' . $email
    );
}

function PAYPAL_newSubscription()
{
    return PAYPAL_getSubscriptionForm();
}
