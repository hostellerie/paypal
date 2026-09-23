<?php

/* PayPal Plugin 1.7.0 - bounded read-only services. */

if (isset($_SERVER['PHP_SELF']) && strpos(strtolower($_SERVER['PHP_SELF']), 'services.inc.php') !== false) {
    die('This file can not be used on its own.');
}

function PAYPAL_serviceRejectWeb($args, &$svc_msg)
{
    if (is_array($args) && !empty($args['gl_svc'])) {
        $svc_msg['error_desc'] = 'PayPal interoperability services are available only to trusted internal plugin calls.';
        return true;
    }
    return false;
}

function service_dashboard_summary_paypal($args, &$output, &$svc_msg)
{
    global $_CONF, $_TABLES, $LANG_PAYPAL_1;

    $output = array();
    $svc_msg = array();

    if (PAYPAL_serviceRejectWeb($args, $svc_msg)) {
        return PLG_RET_AUTH_FAILED;
    }
    if (!SEC_hasRights('paypal.admin')) {
        $svc_msg['error_desc'] = 'PayPal administration permission is required.';
        return PLG_RET_AUTH_FAILED;
    }

    $products = (int) DB_count($_TABLES['paypal_products']);
    $active = (int) DB_count($_TABLES['paypal_products'], 'active', 1);
    $purchases = (int) DB_count($_TABLES['paypal_purchases']);
    $pending = (int) DB_count($_TABLES['paypal_purchases'], 'status', 'pending');
    $subscriptions = (int) DB_count($_TABLES['paypal_subscriptions']);

    $alerts = array();
    if ($pending > 0) {
        $alerts[] = array(
            'id' => 'pending-orders',
            'status' => 'warning',
            'message' => 'Pending orders require attention.',
            'count' => $pending
        );
    }

    $output = array(
        'schema' => 1,
        'status' => empty($alerts) ? 'ok' : 'warning',
        'metrics' => array(
            array('id' => 'products', 'label' => 'Products', 'value' => $products),
            array('id' => 'published', 'label' => 'Active products', 'value' => $active),
            array('id' => 'purchases', 'label' => 'Purchases', 'value' => $purchases),
            array('id' => 'pending', 'label' => 'Pending orders', 'value' => $pending),
            array('id' => 'subscriptions', 'label' => 'Subscriptions', 'value' => $subscriptions)
        ),
        'alerts' => $alerts,
        'links' => array(
            array(
                'label' => isset($LANG_PAYPAL_1['plugin_name']) ? $LANG_PAYPAL_1['plugin_name'] : 'PayPal',
                'url' => rtrim($_CONF['site_admin_url'], '/') . '/plugins/paypal/'
            )
        ),
        'updated' => time()
    );

    return PLG_RET_OK;
}
