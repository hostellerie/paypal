<?php

if (!isset($GLOBALS['_CONF'])) {
    die('This file can not be used on its own.');
}

/**
 * PayPal configuration tabs for Geeklog 2.1.1+.
 *
 * The schema only controls presentation (tab/fieldset/order). Existing values
 * remain untouched.
 *
 * @return array
 */
function PAYPAL_configTabSchema()
{
    return array(
        'general' => array(
            'id' => 0,
            'values' => array(
                'paypal_folder', 'menulabel', 'paypal_login_required',
                'hide_paypal_menu', 'receiverEmailAddr', 'currency',
                'anonymous_buy', 'purchase_email_user',
                'purchase_email_user_attach', 'purchase_email_anon',
                'purchase_email_anon_attach', 'default_permissions'
            ),
        ),
        'payments' => array(
            'id' => 10,
            'values' => array(
                'paypalURL', 'enable_buy_now', 'enable_pay_by_paypal',
                'enable_pay_by_check', 'API_UserName', 'API_Password',
                'API_Signature'
            ),
        ),
        'catalog' => array(
            'id' => 20,
            'values' => array(
                'paypal_main_header', 'paypal_main_footer', 'products_col',
                'order', 'view_membership', 'display_complete_memberships',
                'view_review', 'display_2nd_buttons', 'display_item_id',
                'maxPerPage', 'categoryHeading', 'categoryColumns',
                'displayCatImage', 'catImageWidth', 'displayCatDescription'
            ),
        ),
        'images' => array(
            'id' => 30,
            'values' => array(
                'max_images_per_products', 'max_image_width',
                'max_image_height', 'max_image_size', 'max_thumbnail_size',
                'attribute_thumbnail_size', 'thumb_width', 'thumb_height'
            ),
        ),
        'checkout' => array(
            'id' => 40,
            'values' => array(
                'image_url', 'cpp_header_image', 'cpp_headerback_color',
                'cpp_headerborder_color', 'cpp_payflow_color', 'cs'
            ),
        ),
        'shop' => array(
            'id' => 50,
            'values' => array(
                'shop_name', 'shop_street1', 'shop_street2', 'shop_postal',
                'shop_city', 'shop_country', 'shop_siret', 'shop_phone1',
                'shop_phone2', 'shop_fax', 'seo_shop_title'
            ),
        ),
        'blocks' => array(
            'id' => 60,
            'values' => array(
                'display_blocks',
                'cart_block_enabled', 'cart_block_isleft', 'cart_block_order',
                'random_block_enabled', 'random_block_isleft',
                'random_block_order'
            ),
        ),
    );
}

/**
 * Create missing tab/fieldset records and remap configuration values.
 *
 * This is idempotent and safe for both fresh installs and upgrades.
 *
 * @return bool
 */
function PAYPAL_applyConfigTabs()
{
    global $_TABLES;

    if (empty($_TABLES['conf_values'])) {
        return false;
    }

    $schema = PAYPAL_configTabSchema();
    $group = 'paypal';

    foreach ($schema as $name => $definition) {
        $id = (int) $definition['id'];
        $tabName = 'tab_' . $name;
        $fieldsetName = 'fs_' . $name;

        PAYPAL_ensureConfigStructure($tabName, 'tab', $id);
        PAYPAL_ensureConfigStructure($fieldsetName, 'fieldset', $id);

        $order = 10;
        foreach ($definition['values'] as $configName) {
            $safeName = DB_escapeString($configName);
            DB_query(
                "UPDATE {$_TABLES['conf_values']} SET "
                . "subgroup = 0, fieldset = {$id}, tab = {$id}, sort_order = {$order} "
                . "WHERE group_name = '{$group}' AND name = '{$safeName}'"
            );
            $order += 10;
        }
    }

    // Remove obsolete presentation-only structures from the old layout.
    $obsolete = array(
        'fs_main', 'fs_permissions', 'sg_display', 'fs_display',
        'fs_checkoutpage', 'sg_myshop', 'fs_shopdetails'
    );
    foreach ($obsolete as $name) {
        $safeName = DB_escapeString($name);
        DB_query(
            "DELETE FROM {$_TABLES['conf_values']} "
            . "WHERE group_name = '{$group}' AND name = '{$safeName}'"
        );
    }

    return !DB_error();
}

/**
 * Ensure one structural configuration row exists.
 *
 * @param string $name
 * @param string $type
 * @param int    $id
 * @return void
 */
function PAYPAL_ensureConfigStructure($name, $type, $id)
{
    global $_TABLES;

    $safeName = DB_escapeString($name);
    $safeType = DB_escapeString($type);
    $exists = (int) DB_getItem(
        $_TABLES['conf_values'],
        'COUNT(*)',
        "name = '{$safeName}' AND group_name = 'paypal'"
    );

    if ($exists > 0) {
        DB_query(
            "UPDATE {$_TABLES['conf_values']} SET "
            . "type = '{$safeType}', subgroup = 0, fieldset = {$id}, "
            . "tab = {$id}, sort_order = 0 "
            . "WHERE group_name = 'paypal' AND name = '{$safeName}'"
        );
        return;
    }

    $config = config::get_instance();
    $config->add(
        $name,
        null,
        $type,
        0,
        $id,
        null,
        0,
        true,
        'paypal',
        $id
    );
}
