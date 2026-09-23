<?php

require_once('../../../lib-common.php');

paypal_access_check('paypal.admin');

$display = paypal_admin_menu();

$template = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates');
$template->set_file('help', 'admin_help.thtml');

$template->set_var(array(
    'admin_url' => $_CONF['site_admin_url'] . '/plugins/paypal',

    'help_intro' => $LANG_PAYPAL_1['help_intro'],
    'help_quick_actions' => $LANG_PAYPAL_1['help_quick_actions'],
    'help_catalog_setup' => $LANG_PAYPAL_1['help_catalog_setup'],
    'help_monitoring' => $LANG_PAYPAL_1['help_monitoring'],
    'help_blocks' => $LANG_PAYPAL_1['help_blocks'],
    'help_blocks_intro' => $LANG_PAYPAL_1['help_blocks_intro'],
    'help_workflow' => $LANG_PAYPAL_1['help_workflow'],
    'help_troubleshooting' => $LANG_PAYPAL_1['help_troubleshooting'],
    'help_troubleshooting_text' => $LANG_PAYPAL_1['help_troubleshooting_text'],

    'products' => $LANG_PAYPAL_ADMIN['products'],
    'create_product' => $LANG_PAYPAL_1['create_product'],
    'subscriptions' => $LANG_PAYPAL_1['subscriptions'],
    'create_membership' => $LANG_PAYPAL_1['new_membership'],
    'recurring_payments' => $LANG_PAYPAL_1['recurring_payments'],
    'create_recurring' => $LANG_PAYPAL_1['create_recurring_payment'],
    'manage_categories' => $LANG_PAYPAL_ADMIN['manage_categories'],
    'manage_attributes' => $LANG_PAYPAL_ADMIN['manage_attributes'],
    'manage_attribute_types' => $LANG_PAYPAL_ADMIN['manage_attributetypes'],
    'manage_shipping' => $LANG_PAYPAL_ADMIN['manage_shipping'],
    'shipper_services' => $LANG_PAYPAL_ADMIN['shipper_services'],
    'shipping_locations' => $LANG_PAYPAL_ADMIN['shipping_locations'],
    'purchase_history' => $LANG_PAYPAL_1['purchases'],
    'downloads' => $LANG_PAYPAL_1['downloads'],
    'ipn_logs' => $LANG_PAYPAL_1['IPN_logs'],

    'help_products' => $LANG_PAYPAL_1['help_products'],
    'help_create_product' => $LANG_PAYPAL_1['help_create_product'],
    'help_subscriptions' => $LANG_PAYPAL_1['help_subscriptions'],
    'help_create_membership' => $LANG_PAYPAL_1['help_create_membership'],
    'help_recurring' => $LANG_PAYPAL_1['help_recurring'],
    'help_create_recurring' => $LANG_PAYPAL_1['help_create_recurring'],
    'help_categories' => $LANG_PAYPAL_1['help_categories'],
    'help_attributes' => $LANG_PAYPAL_1['help_attributes'],
    'help_attribute_types' => $LANG_PAYPAL_1['help_attribute_types'],
    'help_shipping' => $LANG_PAYPAL_1['help_shipping'],
    'help_shipper_services' => $LANG_PAYPAL_1['help_shipper_services'],
    'help_shipping_locations' => $LANG_PAYPAL_1['help_shipping_locations'],
    'help_purchase_history' => $LANG_PAYPAL_1['help_purchase_history'],
    'help_downloads' => $LANG_PAYPAL_1['help_downloads'],
    'help_ipn' => $LANG_PAYPAL_1['help_ipn'],
    'help_cart_block' => $LANG_PAYPAL_1['help_cart_block'],
    'help_random_block' => $LANG_PAYPAL_1['help_random_block'],
    'help_step_config' => $LANG_PAYPAL_1['help_step_config'],
    'help_step_catalog' => $LANG_PAYPAL_1['help_step_catalog'],
    'help_step_shipping' => $LANG_PAYPAL_1['help_step_shipping'],
    'help_step_test' => $LANG_PAYPAL_1['help_step_test'],
    'help_step_monitor' => $LANG_PAYPAL_1['help_step_monitor'],
));

$display .= COM_startBlock($LANG_PAYPAL_1['help']);
$display .= $template->parse('', 'help');
$display .= COM_endBlock();

COM_output(PAYPAL_createHTMLDocument($display, $LANG_PAYPAL_1['help']));
