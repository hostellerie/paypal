<?php

if (strpos(strtolower(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : ''), 'uninstall.php') !== false) {
    die('This file can not be used on its own.');
}

/**
 * Return resources owned by the PayPal plugin for Geeklog auto-uninstall.
 *
 * Shared by autoinstall.php and functions.inc so failed installs and normal
 * uninstalls use the exact same cleanup contract.
 *
 * @return array
 */
if (!function_exists('plugin_autouninstall_paypal')) {
    function plugin_autouninstall_paypal()
    {
        return array(
            'tables' => array(
                'paypal_ipnlog',
                'paypal_downloads',
                'paypal_products',
                'paypal_purchases',
                'paypal_images',
                'paypal_categories',
                'paypal_subscriptions',
                'paypal_users',
                'paypal_attributes',
                'paypal_attribute_type',
                'paypal_product_attribute',
                'paypal_stock',
                'paypal_delivery',
                'paypal_stock_movements',
                'paypal_providers',
                'paypal_shipper_service',
                'paypal_shipping_to',
                'paypal_shipping_cost',
                'paypal_recurrent'
            ),
            'groups' => array(
                'Paypal Admin',
                'Paypal User',
                'Paypal Viewer'
            ),
            'features' => array(
                'paypal.admin',
                'paypal.user',
                'paypal.viewer'
            ),
            'php_blocks' => array(
                'phpblock_paypal_cart',
                'phpblock_paypal_randomBlock'
            ),
            'vars' => array()
        );
    }
}
