<?php

/* Reminder: always indent with 4 spaces (no tabs). */
// +---------------------------------------------------------------------------+
// | Paypal Plugin 1.6                                                         |
// +---------------------------------------------------------------------------+
// | functions.inc                                                             |
// |                                                                           |
// | This file does two things: 1) it implements the necessary Geeklog Plugin  |
// | API methods and 2) implements all the common code needed by this plugin.  |
// +---------------------------------------------------------------------------+
// | Copyright (C) 2009-2016 by the following authors:                         |
// |                                                                           |
// | Authors: ::Ben - cordiste AT free DOT fr                                  |
// +---------------------------------------------------------------------------+
// | Created with the Geeklog Plugin Toolkit.                                  |
// +---------------------------------------------------------------------------+
// | Based on the original paypal Plugin                                       |
// | Copyright (C) 2005 - 2006 by the following authors:                       |
// |                                                                           |
// | Vincent Furia <vinny01 AT users DOT sourceforge DOT net>                  |   
// +---------------------------------------------------------------------------+
// |                                                                           |
// | This program is free software; you can redistribute it and/or             |
// | modify it under the terms of the GNU General Public License               |
// | as published by the Free Software Foundation; either version 2            |
// | of the License, or (at your option) any later version.                    |
// |                                                                           |
// | This program is distributed in the hope that it will be useful,           |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
// | GNU General Public License for more details.                              |
// |                                                                           |
// | You should have received a copy of the GNU General Public License         |
// | along with this program; if not, write to the Free Software Foundation,   |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.           |
// |                                                                           |
// +---------------------------------------------------------------------------+

/**
 * paypal plugin functions.inc file
 *
 * @author Vincent Furia <vinny01 AT users DOT sourceforge DOT net>
 * @copyright Vincent Furia 2005 - 2006
 * @package paypal
 */

global $_CONF, $_TABLES, $_USER, $_DB_table_prefix, $_PLUGINS, $_PAY_CONF;
global $LANG_PAYPAL_1, $LANG_PAYPAL_ADMIN, $LANG_PAYPAL_CART, $LANG_PAYPAL_TYPE;
global $LANG_PAYPAL_EMAIL, $LANG_PAYPAL_LOGIN, $LANG_PAYPAL_MESSAGE, $LANG_PAYPAL_PAYMENT;
global $LANG_configsections, $LANG_confignames, $LANG_configsubgroups, $LANG_tab, $LANG_fs, $LANG_configselects;
global $LANG_PAYPAL_TOOLTIPS, $PLG_paypal_MESSAGE3002;

if (isset($_SERVER['PHP_SELF']) && strpos(strtolower($_SERVER['PHP_SELF']), 'functions.inc') !== false) {
    die ('This file can not be used on its own.');
}


/**
 * Language file include
 */
$plugin_path = $_CONF['path'] . 'plugins/paypal/';
$langfile = $plugin_path . 'language/' . $_CONF['language'] . '.php';

if (file_exists($langfile)) {
    require_once $langfile;
} else {
    require_once $plugin_path . 'language/english.php';
}

/**
 * Throw in uid = 1 for anonymous users
 */
if ( empty($_USER['uid']) ) {
    $_USER['uid'] = 1;
    $_USER['username'] = $LANG_PAYPAL_1['anonymous'];
    $_USER['fullname'] = $LANG_PAYPAL_1['Anonymous'];
}

/**
 * Load our config
 */
require_once $_CONF['path_system'].'classes/config.class.php';
require_once ($_CONF['path'] . 'plugins/paypal/lib/configuration.php');
$c = config::get_instance();
$_PAY_CONF = $c->get_config('paypal');

// PayPal 1.7.0 renamed the historical misspelled configuration key.
// Keep this migration here as well as in upgrade.php so pre-release 1.7.0
// installations whose stored plugin version is already 1.7.0 are repaired.
if (!array_key_exists('enable_pay_by_check', $_PAY_CONF)
    && array_key_exists('enable_pay_by_ckeck', $_PAY_CONF)
) {
    $c->add(
        'enable_pay_by_check',
        (int) $_PAY_CONF['enable_pay_by_ckeck'],
        'select',
        0,
        0,
        3,
        70,
        true,
        'paypal'
    );
    $c->del('enable_pay_by_ckeck', 'paypal');
    $_PAY_CONF = $c->get_config('paypal');
}

$dynamicBlockDefaults = array(
    'cart_block_enabled' => array(1, 'select', 3, 60),
    'cart_block_isleft' => array(0, 'select', 3, 61),
    'cart_block_order' => array(50, 'text', 0, 62),
    'random_block_enabled' => array(1, 'select', 3, 65),
    'random_block_isleft' => array(0, 'select', 3, 66),
    'random_block_order' => array(60, 'text', 0, 67),
);
$dynamicBlockConfigChanged = false;
foreach ($dynamicBlockDefaults as $name => $definition) {
    if (!array_key_exists($name, $_PAY_CONF)) {
        $c->add(
            $name,
            $definition[0],
            $definition[1],
            0,
            60,
            $definition[2],
            $definition[3],
            true,
            'paypal',
            60
        );
        $dynamicBlockConfigChanged = true;
    }
}
if ($dynamicBlockConfigChanged) {
    $_PAY_CONF = $c->get_config('paypal');
}

$paypalTabsReady = isset($_TABLES['conf_values'])
    ? (int) DB_getItem(
        $_TABLES['conf_values'],
        'COUNT(*)',
        "group_name = 'paypal' AND name = 'tab_general'"
    )
    : 0;
if ($paypalTabsReady === 0) {
    PAYPAL_applyConfigTabs();
}

/*
* Include specific paypal config file
*/
require_once ($_CONF['path'] . 'plugins/paypal/paypal.php');

require_once ($_CONF['path'] . 'plugins/paypal/interoperability.php');
require_once ($_CONF['path'] . 'plugins/paypal/services.inc.php');
require_once ($_CONF['path'] . 'plugins/paypal/storage.php');
require_once ($_CONF['path'] . 'plugins/paypal/uninstall.php');

/*
* Include classes
*/
 require_once ($_CONF['path'] . 'plugins/paypal/classes/jcart.class.php');

 /**
*Include plugin files
*/
require_once $_CONF['path'] . 'plugins/paypal/lib/features.php';

// +---------------------------------------------------------------------------+
// | Geeklog Plugin API Implementation                                         |
// +---------------------------------------------------------------------------+

/**
 * Return contextual help for Geeklog's configuration manager.
 *
 * @param string $id Configuration option name
 * @return string
 */
function plugin_getconfigtooltip_paypal($id)
{
    global $LANG_PAYPAL_TOOLTIPS;

    return isset($LANG_PAYPAL_TOOLTIPS[$id])
        ? $LANG_PAYPAL_TOOLTIPS[$id]
        : '';
}

/**
 * Build a cache-busted PayPal stylesheet link.
 *
 * @param string $file Stylesheet filename
 * @return string
 */
function PAYPAL_stylesheetLink($file)
{
    global $_CONF, $_PAY_CONF;

    $cssPath = rtrim($_CONF['path_html'], '/\\')
        . '/' . trim($_PAY_CONF['paypal_folder'], '/\\')
        . '/css/' . basename($file);

    $version = '1.7.0';
    if (is_file($cssPath)) {
        clearstatcache(true, $cssPath);
        $modified = @filemtime($cssPath);
        if ($modified !== false && $modified > 0) {
            $version .= '-' . (int) $modified;
        }
    }

    return '<link rel="stylesheet" type="text/css" href="'
        . htmlspecialchars(
            rtrim($_CONF['site_url'], '/')
            . '/' . trim($_PAY_CONF['paypal_folder'], '/')
            . '/css/' . basename($file),
            ENT_QUOTES,
            'UTF-8'
        )
        . '?v=' . rawurlencode($version) . '">' . "\n";
}

function plugin_getheadercode_paypal()
{
    global $_CONF, $_PAY_CONF, $_SCRIPTS, $jcart;

    $retval = '';
    $currentUrl = COM_getCurrentURL();
    $requestPath = '';
    if (!empty($_SERVER['REQUEST_URI'])) {
        $parsedPath = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestPath = is_string($parsedPath) ? $parsedPath : '';
    }
    if ($requestPath === '' && !empty($_SERVER['SCRIPT_NAME'])) {
        $requestPath = (string) $_SERVER['SCRIPT_NAME'];
    }
    $requestPath = str_replace('\\', '/', $requestPath);

    $isPayPalAdmin = strpos($requestPath, '/admin/plugins/paypal/') !== false;
    $configGroup = isset($_POST['conf_group'])
        ? (string) $_POST['conf_group']
        : (isset($_GET['conf_group']) ? (string) $_GET['conf_group'] : '');
    $isPayPalConfig = strpos($requestPath, '/admin/configuration.php') !== false
        && $configGroup === 'paypal';
    $isPublicPayPal = !$isPayPalAdmin
        && !$isPayPalConfig
        && strpos($requestPath, '/' . trim($_PAY_CONF['paypal_folder'], '/') . '/') !== false;

    $_SCRIPTS->setJavaScriptLibrary('jquery');

    $cartBlockEnabled = false;
    if (isset($GLOBALS['_TABLES']['blocks'])) {
        $cartBlockEnabled = (int) DB_getItem(
            $GLOBALS['_TABLES']['blocks'],
            'bid',
            "type = 'phpblock' AND phpblockfn = 'phpblock_paypal_cart' AND is_enabled = 1"
        ) > 0;
    }

    $dynamicCartEnabled = !empty($_PAY_CONF['cart_block_enabled']);

    if ((stripos($currentUrl, '/' . $_PAY_CONF['paypal_folder'] . '/') !== false
            && stripos($currentUrl, 'informations.php') === false)
        || $cartBlockEnabled
        || $dynamicCartEnabled
    ) {
            $cartConfig = array(
                'relayUrl' => $jcart['url'] . 'jcart-relay.php',
                'relayBlockUrl' => $jcart['url'] . 'jcart-relay-block.php',
                'itemId' => $jcart['item_id'],
                'itemRef' => $jcart['item_ref'],
                'itemName' => $jcart['item_name'],
                'itemPrice' => $jcart['item_price'],
                'itemQty' => $jcart['item_qty'],
                'itemAdd' => $jcart['item_add'],
                'itemWeight' => $jcart['item_weight'],
                'updateButton' => $jcart['text']['update_button'],
            );
            $_SCRIPTS->setJavaScript(
                'window.PayPalCartConfig = ' . json_encode($cartConfig) . ';',
                true
            );
            $_SCRIPTS->setJavaScriptFile(
                'paypal_cart',
                '/' . $_PAY_CONF['paypal_folder'] . '/js/jcart-1.7.0.js'
            );
    }

    if ($isPublicPayPal || $isPayPalAdmin || $isPayPalConfig
        || $cartBlockEnabled || $dynamicCartEnabled
    ) {
        $retval .= PAYPAL_stylesheetLink('paypal-1.7.0.css');
    }

    if ($isPayPalAdmin) {
        $retval .= '<link rel="stylesheet" type="text/css" href="'
            . htmlspecialchars(
                rtrim($_CONF['site_url'], '/') . '/jquery/datepicker/datepicker.css',
                ENT_QUOTES,
                'UTF-8'
            )
            . '">' . "\n";
    }

    return $retval;
}

/**
 * Return the PayPal storefront entry for Geeklog's main menu.
 *
 * @return array
 */
function plugin_getmenuitems_paypal()
{
    global $_PAY_CONF, $LANG_PAYPAL_1;

    if (!empty($_PAY_CONF['hide_paypal_menu'])) {
        return array();
    }

    $label = isset($_PAY_CONF['menulabel']) ? trim((string) $_PAY_CONF['menulabel']) : '';
    if ($label === '') {
        $label = $LANG_PAYPAL_1['store'];
    }

    return array($label => $_PAY_CONF['site_url'] . '/index.php');
}

/**
 * Return PayPal dynamic blocks using the Geeklog block API.
 *
 * Existing legacy PHP blocks are detected by phpblockfn and take precedence,
 * which avoids duplicates on upgraded sites.
 *
 * @param string $side  left or right
 * @param string $topic current topic
 * @return array
 */
function plugin_getBlocks_paypal($side, $topic = '')
{
    global $_PAY_CONF, $_TABLES, $LANG_PAYPAL_1;

    $blocks = array();
    $side = ($side === 'left') ? 'left' : 'right';

    $legacyCart = (int) DB_getItem(
        $_TABLES['blocks'],
        'bid',
        "type = 'phpblock' AND phpblockfn = 'phpblock_paypal_cart' AND is_enabled = 1"
    );
    $requestPath = '';
    if (!empty($_SERVER['REQUEST_URI'])) {
        $parsedPath = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestPath = is_string($parsedPath) ? $parsedPath : '';
    }
    $isCheckoutPage = substr($requestPath, -13) === '/checkout.php';

    $cartSide = !empty($_PAY_CONF['cart_block_isleft']) ? 'left' : 'right';
    if (!$isCheckoutPage
        && !$legacyCart
        && !empty($_PAY_CONF['cart_block_enabled'])
        && $side === $cartSide
    ) {
        $cart = PAYPAL_getCart();
        $cartIsEmpty = $cart->is_empty();

        $blocks[] = array(
            'name' => 'paypal_cart',
            'type' => 'dynamic',
            'onleft' => $cartSide === 'left' ? 1 : 0,
            'title' => isset($LANG_PAYPAL_1['cart']) ? $LANG_PAYPAL_1['cart'] : 'Cart',
            'blockorder' => isset($_PAY_CONF['cart_block_order']) ? (int) $_PAY_CONF['cart_block_order'] : 50,
            'content' => phpblock_paypal_cart(true),
            'allow_autotags' => false,
            'help' => '',
            'css_id' => 'paypal-cart-dynamic-block',
            'css_classes' => $cartIsEmpty
                ? 'paypal-cart-dynamic-block paypal-cart-dynamic-block--empty'
                : 'paypal-cart-dynamic-block'
        );
    }

    $legacyRandom = (int) DB_getItem(
        $_TABLES['blocks'],
        'bid',
        "type = 'phpblock' AND phpblockfn = 'phpblock_paypal_randomBlock' AND is_enabled = 1"
    );
    $randomSide = !empty($_PAY_CONF['random_block_isleft']) ? 'left' : 'right';
    if (!$legacyRandom && !empty($_PAY_CONF['random_block_enabled']) && $side === $randomSide) {
        $content = phpblock_paypal_randomBlock();
        if ($content !== '') {
            $blocks[] = array(
                'name' => 'paypal_random_product',
                'type' => 'dynamic',
                'onleft' => $randomSide === 'left' ? 1 : 0,
                'title' => isset($LANG_PAYPAL_1['random_product'])
                    ? $LANG_PAYPAL_1['random_product']
                    : 'Random product',
                'blockorder' => isset($_PAY_CONF['random_block_order']) ? (int) $_PAY_CONF['random_block_order'] : 60,
                'content' => $content,
                'allow_autotags' => false,
                'help' => ''
            );
        }
    }

    return $blocks;
}

/**
 * Describe PayPal dynamic blocks to Geeklog configuration consumers.
 *
 * @param string $side
 * @param string $topic
 * @return array
 */
function plugin_getBlocksConfig_paypal($side, $topic = '')
{
    global $_PAY_CONF, $LANG_PAYPAL_1;

    return array(
        array(
            'plugin' => 'PayPal',
            'name' => 'paypal_cart',
            'title' => isset($LANG_PAYPAL_1['cart']) ? $LANG_PAYPAL_1['cart'] : 'Cart',
            'type' => 'dynamic',
            'onleft' => empty($_PAY_CONF['cart_block_isleft']) ? 0 : 1,
            'blockorder' => isset($_PAY_CONF['cart_block_order']) ? (int) $_PAY_CONF['cart_block_order'] : 50,
            'allow_autotags' => false,
            'help' => '',
            'css_id' => 'paypal-cart-dynamic-block',
            'css_classes' => 'paypal-cart-dynamic-block',
            'enable' => empty($_PAY_CONF['cart_block_enabled']) ? 0 : 1,
            'topic_option' => 'all',
            'topic' => array(),
            'inherit' => array()
        ),
        array(
            'plugin' => 'PayPal',
            'name' => 'paypal_random_product',
            'title' => isset($LANG_PAYPAL_1['random_product'])
                ? $LANG_PAYPAL_1['random_product']
                : 'Random product',
            'type' => 'dynamic',
            'onleft' => empty($_PAY_CONF['random_block_isleft']) ? 0 : 1,
            'blockorder' => isset($_PAY_CONF['random_block_order']) ? (int) $_PAY_CONF['random_block_order'] : 60,
            'allow_autotags' => false,
            'help' => '',
            'enable' => empty($_PAY_CONF['random_block_enabled']) ? 0 : 1,
            'topic_option' => 'all',
            'topic' => array(),
            'inherit' => array()
        )
    );
}

/**
* Return plugin entry for the Admins Only block
*
* @return array Array containing (plugin name, plugin admin url, # of plugin items or '')
*/
function plugin_getadminoption_paypal()
{
    global $_CONF, $LANG_PAYPAL_1;

    if (SEC_hasRights('paypal.admin')) {
        return array($LANG_PAYPAL_1['plugin_name'],
            $_CONF['site_admin_url'] . '/plugins/paypal/purchase_history.php', 0);
    }
}

/**
* Returns the URL of the plugin's icon
*
* @return   string      URL of the icon
*
*/
function plugin_geticon_paypal()
{
    global $_PAY_CONF;

    return $_PAY_CONF['site_url'] . '/images/paypal.gif';
}

/**
 * Returns the current plugin code version
 *
 * @return string    plugin version
 */
function plugin_chkVersion_paypal()
{
    global $_CONF;

    require_once $_CONF['path'] . 'plugins/paypal/autoinstall.php';

    $inst_parms = plugin_autoinstall_paypal('paypal');

    return $inst_parms['info']['pi_version'];
}

/**
* Update the plugin
*
* @return   int     Number of message to display or true for success
*
*/
function plugin_upgrade_paypal()
{
    global $_CONF;

    require_once $_CONF['path'].'plugins/paypal/upgrade.php';

    return paypal_upgrade();
}

/**
* Callback function when another plugin was installed/uninstalled/upgarded/
* enabled/disabled
*
* @param    string  $type   plugin name
* @param    string  $status 'installed', 'uninstalled', 'upgraded', 'enabled',
*                           or 'disabled'
* @return   void
* @see      PLG_pluginStateChange
*/
function plugin_pluginstatechange_paypal($type, $status)
{
    // Kept for Geeklog Plugin API compatibility.
}

/**
 * Render a complete Geeklog document.
 *
 * COM_createHTMLDocument() is available in the supported Geeklog range and is
 * the required rendering path in Geeklog 2.2.x where COM_siteHeader() and
 * COM_siteFooter() no longer exist.
 *
 * @param string $content
 * @param string $pagetitle
 * @return string
 */
function PAYPAL_createHTMLDocument($content, $pagetitle = '')
{
    global $_PAY_CONF;

    $options = array();

    if ($pagetitle !== '') {
        $options['pagetitle'] = $pagetitle;
    }

    $displayBlocks = isset($_PAY_CONF['display_blocks'])
        ? (int) $_PAY_CONF['display_blocks']
        : 3;

    $options['custom'] = array('PAYPAL_renderConfiguredBlocks', $displayBlocks);
    $options['rightblock'] = ($displayBlocks === 2 || $displayBlocks === 3);

    return COM_createHTMLDocument($content, $options);
}

/**
 * Render only the block columns enabled by the PayPal display setting.
 *
 * Values: 0 none, 1 left, 2 right, 3 left and right.
 *
 * @param int    $displayBlocks
 * @param string $side
 * @return string
 */
function PAYPAL_renderConfiguredBlocks($displayBlocks, $side)
{
    $displayBlocks = (int) $displayBlocks;

    if (($side === 'left' && ($displayBlocks === 1 || $displayBlocks === 3))
        || ($side === 'right' && ($displayBlocks === 2 || $displayBlocks === 3))
    ) {
        return COM_showBlocks($side);
    }

    return '';
}

/**
 * Checks to see if the user has sufficient rights to view the page
 *
 * Check if the current user has rights (at least one of) specified by $rights.  If not log the
 * error, display a generic access denied message and exit.
 *
 * @param $rights string comma seperated list of allowable rights
 */
function paypal_access_check ($rights = 'paypal.viewer') 
{
    global $_USER, $_SERVER, $LANG_PAYPAL_1, $_PAY_CONF;
	
	if ($_USER['uid'] < 2) $_USER['uid'] = 1;

    // Confirm that the user has sufficient rights to access this page
    if ($_USER['uid'] < 2 && $_PAY_CONF['paypal_login_required'] == 1) {
        $display = '';
        if (SEC_hasRights('paypal.user,paypal.admin', 'OR')) {
            $display .= paypal_user_menu();
        } else {
            $display .= paypal_viewer_menu();
        }
        $display .= SEC_loginRequiredForm();
        COM_output(PAYPAL_createHTMLDocument($display));
        exit;
    }
	
	// Confirm that the user has sufficient rights to access this page
	$features = explode(",",(SEC_getUserPermissions()));
    if ( !in_array($rights, $features) ) {
        $display = '';
        if (SEC_hasRights('paypal.user,paypal.admin', 'OR')) {
            $display .= paypal_user_menu();
        } else {
            $display .= paypal_viewer_menu();
        }
        $display .= COM_startBlock($LANG_PAYPAL_1['access_denied']);
        $display .= $LANG_PAYPAL_1['access_denied_message'];
        $display .= COM_endBlock();
        COM_accessLog("Unauthorized user {$_USER['username']} from IP {$_SERVER['REMOTE_ADDR']} "
                    . 'attempted to access the paypal plugin at ' . $_SERVER['REQUEST_URI']);
        COM_output(PAYPAL_createHTMLDocument($display, $LANG_PAYPAL_1['access_denied']));
        exit;
    }
}

/**
 * Returns viewer menu display
 *
 * Generates the viewer menu from the template and returns the result as a string of HTML
 *
 * @return string HTML of viewer menu
 */
function paypal_viewer_menu() {
    global $_CONF, $_PAY_CONF, $LANG_PAYPAL_1, $_TABLES;

    $display = '';

    // generate the menu from the template
    $menu = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates/menus');
    $menu->set_file(array('menu' => 'viewer_menu.thtml'));
    $menu->set_var('site_url', $_PAY_CONF['site_url']);
    $menu->set_var('view_cart', $LANG_PAYPAL_1['view_cart']);
    
	// Display types
    $types = 0;
    $nb_products = DB_count($_TABLES['paypal_products'],'type','product');
    $nb_subscriptions = DB_count($_TABLES['paypal_products'],'type','subscription');
	$type = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates/menus');
	$type->set_file(array('table' => 'types.thtml'));
	if ($nb_products > 0) {
		$types++;
		$type->set_var('products', '<a href="' . $_PAY_CONF['site_url'] . '/index.php?type=product">' . $LANG_PAYPAL_1['product_list_label'] . '</a>');
	} else {
		$type->set_var('products', '');
	}
	if ($nb_subscriptions > 0) {
		if ($types > 0) {
			 $separator = '';
		}
		$type->set_var('memberships', $separator . '<a href="' . $_PAY_CONF['site_url'] . '/index.php?type=subscription">' . $LANG_PAYPAL_1['memberships'] . '</a>');
	} else {
		$type->set_var('memberships', '');
		if ($types == 0) $type->set_var('products', '<a href="' . $_PAY_CONF['site_url'] . '/index.php">' . $LANG_PAYPAL_1['product_list_label'] . '</a>');
	}
	$type_layout = $type->parse('', 'table');		
    $menu->set_var('product_list_label', $type_layout);
	
	if ($_PAY_CONF['view_membership'] == '1') {
    	$menu->set_var('memberships', '<a href="' . $_PAY_CONF['site_url'] . '/memberships_history.php">' . $LANG_PAYPAL_1['members_list'] . '</a>');
	} else {
	    $menu->set_var('memberships', '');
	}
	
    $display .= $menu->parse('output', 'menu');

    // return results
    return $display;
}

/**
 * Returns user menu display
 *
 * Generates the user menu from the template and returns the result as a string of HTML
 *
 * @return string HTML of user menu
 */
function paypal_user_menu() {
    global $_CONF, $_PAY_CONF, $LANG_PAYPAL_1, $_TABLES;

    $display = '';

    // generate the menu from the template
    $menu = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates/menus');
    $menu->set_file(array('menu' => 'user_menu.thtml'));
    $menu->set_var('site_url', $_PAY_CONF['site_url']);
	
	// Display types
    $types = 0;
    $separator = '';
    //Products
	$sql = "SELECT type 
			    FROM {$_TABLES['paypal_products']}
				WHERE type ='product' AND hidden ='0' ANd active ='1'" .
                COM_getPermSQL('AND', 0, 2);
	
	$res = DB_query($sql);
	$nb_products = DB_numRows($res);
	
    //subscriptions
	$sql = "SELECT type 
			    FROM {$_TABLES['paypal_products']}
				WHERE type ='subscription' AND hidden ='0' ANd active ='1'" .
                COM_getPermSQL('AND', 0, 2);
	
	$res = DB_query($sql);
	$nb_subscriptions = DB_numRows($res);

	$type = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates/menus');
	$type->set_file(array('table' => 'types.thtml'));
	if ($nb_products > 0) {
		$types++;
		$type->set_var('products', '<a href="' . $_PAY_CONF['site_url'] . '/index.php?type=product">' . $LANG_PAYPAL_1['product_list_label'] . '</a>');
	} else {
		$type->set_var('products', '');
	}
	if ($nb_subscriptions > 0) {
		if ($types > 0) {
			 $separator = '';
		}
		$type->set_var('memberships', $separator . '<a href="' . $_PAY_CONF['site_url'] . '/index.php?type=subscription">' . $LANG_PAYPAL_1['memberships'] . '</a>');
	} else {
		$type->set_var('memberships', '');
		if ($types == 0) $type->set_var('products', '<a href="' . $_PAY_CONF['site_url'] . '/index.php">' . $LANG_PAYPAL_1['product_list_label'] . '</a>');
	}
	$type_layout = $type->parse('', 'table');		
    $menu->set_var('product_list_label', $type_layout);
	
    $menu->set_var('my_purchases', $LANG_PAYPAL_1['my_purchases']);
    $menu->set_var('my_details', $LANG_PAYPAL_1['my_details']);
	$menu->set_var('view_cart', $LANG_PAYPAL_1['view_cart']);
    $menu->set_var('paypal_url', 'https://' . $_PAY_CONF['paypalURL']);
    $menu->set_var('business', $_PAY_CONF['receiverEmailAddr']);
	if ($_PAY_CONF['view_membership'] == '1') {
    	$menu->set_var('memberships', '<a href="' . $_PAY_CONF['site_url'] . '/memberships_history.php">' . $LANG_PAYPAL_1['members_list'] . '</a>');
	} else {
	    $menu->set_var('memberships', '');
	}
	if (SEC_hasRights('paypal.admin')) {
        $menu->set_var('admin', '<a href="' . $_CONF['site_url'] . '/admin/plugins/paypal/index.php">' . $LANG_PAYPAL_1['admin'] . '</a>');
    } else {
	    $menu->set_var('admin', '');
	}
    $display .= $menu->parse('output', 'menu');

    // return results
    return $display;
}

/**
 * Returns admin menu display
 *
 * Generates the admin menu from the template and returns the result as a string of HTML
 *
 * @return string HTML of admin menu
 * @todo add support for pending (physical) transaction, not fully implemented
 */
function paypal_admin_menu() {
    global $_CONF, $_PAY_CONF, $_TABLES, $LANG_PAYPAL_1, $LANG_PAYPAL_ADMIN;

    $display = '';

    // generate the menu from the template
    $menu = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates/menus');
    $menu->set_file(array('menu' => 'admin_menu.thtml'));
    $menu->set_var('site_url', $_CONF['site_url']);
    $menu->set_var('admin_url', $_CONF['site_admin_url']);
	$menu->set_var('paypal_url', $_PAY_CONF['site_url']);
    $menu->set_var('homepage_label', $LANG_PAYPAL_1['homepage_label']);
    $menu->set_var('store', $LANG_PAYPAL_1['store']);
    $menu->set_var('product_list_label', $LANG_PAYPAL_ADMIN['products']);
    $menu->set_var('new_product', $LANG_PAYPAL_1['new_product']);
    $menu->set_var('purchase_history', $LANG_PAYPAL_1['purchases']);
	$menu->set_var('subscriptions', $LANG_PAYPAL_1['subscriptions']);
	$menu->set_var('recurring_payments', $LANG_PAYPAL_1['recurring_payments']);
	$menu->set_var('downloads_history', $LANG_PAYPAL_1['downloads']);
    $menu->set_var('view_IPN_log', $LANG_PAYPAL_1['IPN_logs']);
    $menu->set_var('help', $LANG_PAYPAL_1['help']);
    $menu->set_var('configuration', $LANG_PAYPAL_1['configuration']);
    /**
     * @todo add support for pending transaction, not fully implemented
     */
    //$cnt = DB_count($_TABLES['paypal.purchases'], 'status', 'pending');
    //if ( $cnt <= 0 ) {
    //    $menu->set_var('pending', 'style="display: none" ');
    //} else {
    //    $menu->set_var('pending', '');
    //}

    $display .= $menu->parse('output', 'menu');

    // return results
    return $display;
}

/**
 * Filters submitted variables
 *
 * Uses geeklog functions to filter submitted variables for illegal content.  Any editing is
 * done in place and no value is returned
 *
 * @param array $vars associative array of variable name => type (type in (number,alpha,text,html))
 * @param array &$arr reference to associative array of variable name => value
 */
function paypal_filterVars($vars, &$arr)
{
    foreach ($vars as $var => $type) {
        $target =& $arr;
        $key = $var;

        // Support HTML form array notation such as perm_owner[0].
        if (preg_match('/^([^\\[]+)\\[([^\\]]+)\\]$/', $var, $matches)) {
            $base = $matches[1];
            $nestedKey = $matches[2];

            if (!isset($arr[$base]) || !is_array($arr[$base])) {
                $arr[$base] = array();
            }

            $target =& $arr[$base];
            $key = $nestedKey;
        }

        if (!isset($target[$key])) {
            $target[$key] = '';
        }

        switch ($type) {
            case 'number':
                $target[$key] = COM_applyFilter($target[$key], true);
                break;

            case 'html':
                $target[$key] = COM_checkHTML($target[$key], 'paypal.admin');
                break;

            case 'text':
                $target[$key] = htmlspecialchars(
                    strip_tags(COM_stripslashes($target[$key])),
                    ENT_QUOTES,
                    'UTF-8'
                );
                break;

            case 'alpha':
            default:
                $target[$key] = COM_applyFilter($target[$key], false);
                break;
        }

        unset($target);
    }
}

/**
 * Send out an email with (possibly) an attachment
 *
 * This function is needed because geeklog's built in mail function does not allow adding
 * attachments to outgoing email, this function does exactly that
 *
 * @param  to           string   recipients name and email address
 * @param  subject      string   subject of the email
 * @param  message_text string   the text of the email
 * @param  attachments  array    array of file names (path included), to attach
 * @param  from         string   (optional) sender of the the email
 * @param  html         bool     true if to be sent as an HTML email
 * @return boolean               true if successful,  otherwise false
 */
function paypal_mailAttachment($to, $subject, $message_text, $attachments = array(),
                               $from = '', $html = true) {
    global $_CONF, $LANG_CHARSET;

    static $mailobj;

    include_once( 'Mail.php' );
    include_once( 'Mail/RFC822.php' );

    $method = $_CONF['mail_settings']['backend'];

    if( !isset( $mailobj )) {
        if(( $method == 'sendmail' ) || ( $method == 'smtp' )) {
            $mailobj =& Mail::factory( $method, $_CONF['mail_settings'] );
        } else {
            $method = 'mail';
            $mailobj =& Mail::factory( $method );
        }
    }

    if( empty( $LANG_CHARSET )) {
        $charset = $_CONF['default_charset'];
        if( empty( $charset )) {
            $charset = 'iso-8859-1';
        }
    } else {
        $charset = $LANG_CHARSET;
    }

    if( empty( $from )) {
        $from = COM_formatEmailAddress( $_CONF['site_name'], $_CONF['site_mail']);
    }

    $headers = array();

    $headers['From'] = $from;
    if( $method != 'mail' ) {
        $headers['To'] = $to;
    }
    $headers['Date'] = date( 'r' ); // RFC822 formatted date
    if( $method == 'smtp' ) {
        list( $usec, $sec ) = explode( ' ', microtime());
        $m = substr( $usec, 2, 5 );
        $headers['Message-Id'] = '<' .  date( 'YmdHis' ) . '.' . $m
                               . '@' . $_CONF['mail_settings']['host'] . '>';
    }
    $headers['Subject'] = $subject;
    $headers['X-Mailer'] = 'geeklog ' . VERSION;

    if (count($attachments) <= 0) {
        if( $html ) {
            $headers['Content-Type'] = 'text/html; charset=' . $charset;
            $headers['Content-Transfer-Encoding'] = '8bit';
        } else {
            $headers['Content-Type'] = 'text/plain; charset=' . $charset;
        }
        $message = $message_text;
    } else {
        // Create MIME Boundary, set MIME relevant headers
        $mime_boundary = '==Mulipart_Boundary_x' . md5(time());
        $headers['MIME-Version'] = '1.0';
        $headers['Content-Type'] = "multipart/mixed;\n  boundary=\"$mime_boundary\"";

        // Create the message part of the message
        $message = "This is a multi-part message in MIME format.\n\n"
                 . "--$mime_boundary\n";
        if( $html ) {
            $message .= "Content-Type:text/html; charset=i\"$charset\"\n";
            $message .= "Content-Transfer-Encoding: 8bit\n\n";
        } else {
            $message .= "Content-Type:text/plain; charset=i\"$charset\"\n";
            $message .= "Content-Transfer-Encoding: 7bit\n\n";
        }
        $message .= $message_text . "\n\n";

        // Add attachments
        foreach ($attachments as $file) {
            // get file
            $fileh = fopen($file,'rb');
            $data  = fread($fileh,filesize($file));
            fclose($fileh);
            $filename = basename($file);

            // encode data
            $data = chunk_split(base64_encode($data));

            // Add attachment to message
            $message .= "--$mime_boundary\n" . "Content-Type: application/octet-stream;\n"
                      . "Content-Disposition: attachment;\n  filename=\"$filename\"\n"
                      . "Content-Transfer-Encoding: base64\n\n" . $data . "\n\n";
        }

        $message .= "--$mime_boundary--\n";
    }

    $retval = $mailobj->send( $to, $headers, $message );
    if( $retval !== true ) {
        COM_errorLog( $retval->toString(), 1 );
    }

    return( $retval === true ? true : false );
}

/**
 * Manually generate different types of buttons
 *
 * @param string $type Type of button to generate (donation, subscribe, buy_now, add_to_cart)
 * @param array  $vars Associative array of input variables, contents depend on type of button,
 *                     include: (name, id, price, business, user_id, count, type)
 * @return string      HTML of requested button
 */
function paypal_genButton($type, $vars) {
    global $_CONF, $_USER, $_PAY_CONF;

    // must be an expected type
    if ( $type != 'donation' && $type != 'subscribe' &&
         $type != 'buy_now' && $type != 'add_to_cart' ) {
        return false;
    }

    $button = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates');
    $button->set_file(array('button'   => "{$type}_button.thtml"));

    $button->set_var('site_url', $_CONF['site_url']);
    $button->set_var('paypal_url', 'https://' . $_PAY_CONF['paypalURL']);

    if (empty($vars['user_id'])) {
        $button->set_var('user_id', $_USER['uid']);
    } else {
        $button->set_var('user_id', $vars['user_id']);
    }
    if (empty($vars['business'])) {
        $button->set_var('business', $_PAY_CONF['receiverEmailAddr']);
    } else {
        $button->set_var('business', $vars['business']);
    }

    $button->set_var('id', $vars['id']);
	if ($_PAY_CONF['display_item_id'] == 1 && $vars['item_id'] != '' ) {
        $button->set_var('name', $vars['name'] . ' | ' . $vars['item_id']);
	} else {
	     $button->set_var('name', $vars['name']);
	}
    $button->set_var('price', number_format($vars['price'], $_CONF['decimal_count'], $_CONF['decimal_separator'], $_CONF['thousand_separator']));
	$button->set_var('price2', $vars['price']);
	if ($vars['shipping_type'] == 0) {
		$button->set_var('item_weight', '0.00');
	} else {
		$button->set_var('item_weight', $vars['weight']);
	}
    $button->set_var('count', $vars['count']);
    $button->set_var('type', $vars['type']);
	$button->set_var('return', $vars['return']);
	$button->set_var('notify_url', $_PAY_CONF['site_url'] . '/ipn.php');
	$button->set_var('image_url', $_PAY_CONF['image_url']);
    $button->set_var('cpp_header_image', $_PAY_CONF['cpp_header_image']);
    $button->set_var('cpp_headerback_color', $_PAY_CONF['cpp_headerback_color']);
    $button->set_var('cpp_headerborder_color', $_PAY_CONF['cpp_headerborder_color']);
    $button->set_var('cpp_payflow_color', $_PAY_CONF['cpp_payflow_color']);
    $button->set_var('cs', $_PAY_CONF['cs']);

    return $button->parse('output', 'button');
}

/**
 * Return the current shopping cart, creating it when needed.
 *
 * @return jcart
 */
function PAYPAL_getCart()
{
    static $cart = null;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if ($cart instanceof jcart) {
        return $cart;
    }

    $cart = new jcart();

    if (isset($_SESSION['paypal_cart']) && is_array($_SESSION['paypal_cart'])) {
        $state = $_SESSION['paypal_cart'];

        $cart->total = isset($state['total']) ? (float) $state['total'] : 0;
        $cart->itemcount = isset($state['itemcount']) ? (int) $state['itemcount'] : 0;
        $cart->totalweight = isset($state['totalweight']) ? (float) $state['totalweight'] : 0;
        $cart->items = isset($state['items']) && is_array($state['items'])
            ? $state['items'] : array();
        $cart->itemprices = isset($state['itemprices']) && is_array($state['itemprices'])
            ? $state['itemprices'] : array();
        $cart->itemqtys = isset($state['itemqtys']) && is_array($state['itemqtys'])
            ? $state['itemqtys'] : array();
        $cart->itemname = isset($state['itemname']) && is_array($state['itemname'])
            ? $state['itemname'] : array();
        $cart->itemweights = isset($state['itemweights']) && is_array($state['itemweights'])
            ? $state['itemweights'] : array();

        // Recalculate derived values instead of trusting stale session totals.
        $cart->_update_total();
        $cart->_update_totalweight();
    } elseif (isset($_SESSION['jcart']) && ($_SESSION['jcart'] instanceof jcart)) {
        // One-time migration for sessions created by older PayPal code.
        $cart = $_SESSION['jcart'];
    }

    // Never keep a serialized jcart object in the session. Geeklog may start
    // the next request before the jcart class has been loaded.
    unset($_SESSION['jcart']);

    return $cart;
}

/**
 * Persist the cart using session-safe scalar and array values only.
 *
 * @param jcart $cart
 * @return void
 */
function PAYPAL_saveCart($cart)
{
    if (!($cart instanceof jcart)) {
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['paypal_cart'] = array(
        'total' => (float) $cart->total,
        'itemcount' => (int) $cart->itemcount,
        'totalweight' => (float) $cart->totalweight,
        'items' => is_array($cart->items) ? $cart->items : array(),
        'itemprices' => is_array($cart->itemprices) ? $cart->itemprices : array(),
        'itemqtys' => is_array($cart->itemqtys) ? $cart->itemqtys : array(),
        'itemname' => is_array($cart->itemname) ? $cart->itemname : array(),
        'itemweights' => is_array($cart->itemweights) ? $cart->itemweights : array(),
    );
}

/**
 * Render the shopping cart.
 *
 * @param int $block 1 when rendering the cart block
 * @return string
 */
function PAYPAL_displayCart($block = 0)
{
    global $jcart;

    $cart = PAYPAL_getCart();
    $html = $cart->display_cart($jcart, $block);
    PAYPAL_saveCart($cart);

    return $html;
}

/**
*   Create a shop block.
*   Shows products from the shop
*
*   @return                 HTML for the block
*/
function phpblock_paypal_displayProducts($A = array(), $args = array())
{
    $category = isset($args[0]) && is_numeric($args[0]) ? (int) $args[0] : '';
    $columns = isset($args[1]) && is_numeric($args[1]) ? (int) $args[1] : '';

    return PAYPAL_displayProducts('', 2, $category, $columns);
}

/**
*   Create a cart block.
*   Shows products in the cart.
*
*   @return                 HTML for the block
*/
function phpblock_paypal_cart($keepEmptyContainer = false)
{
    $requestPath = '';
    if (!empty($_SERVER['REQUEST_URI'])) {
        $parsedPath = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestPath = is_string($parsedPath) ? $parsedPath : '';
    }

    if (substr($requestPath, -13) === '/checkout.php') {
        return '';
    }

    $cart = PAYPAL_getCart();

    // Geeklog 2.1.1 PHP blocks are suppressed completely when their callback
    // returns an empty string. Keep that legacy behaviour so an empty cart
    // does not leave an empty titled block in the layout.
    if ($cart->is_empty() && !$keepEmptyContainer) {
        return '';
    }

    return '<div id="paypal-cart-block" class="paypal-cart-block">'
        . PAYPAL_displayCart(1)
        . '</div>';
}

/**
 * Provide PayPal autotags.
 *
 * [paypal:count] renders the current cart item count. The count is the sum of
 * item quantities, matching the cart block and checkout item count.
 *
 * @param string $op
 * @param string $content
 * @param array  $autotag
 * @return mixed
 */
function plugin_autotags_paypal($op, $content = '', $autotag = array())
{
    global $_TABLES, $LANG_PAYPAL_1, $_PAY_CONF, $_SCRIPTS;

    if ($op == 'tagname') {
        return array('paypal', 'paypal_product');
    }

    if ($op == 'permission') {
        return array('paypal', 'paypal_product');
    }

    if ($op == 'nopermission') {
        return array();
    }

    if ($op == 'description') {
        return array(
            'paypal' => $LANG_PAYPAL_1['autotag_desc_paypal'],
            'paypal_product' => $LANG_PAYPAL_1['autotag_desc_paypal_product']
        );
    }

    if ($op == 'parse') {
        if (!is_array($autotag)
            || !isset($autotag['tag'])
            || !isset($autotag['tagstr'])
        ) {
            return $content;
        }

        $parameter = isset($autotag['parm1'])
            ? trim((string) $autotag['parm1'])
            : '';

        // Issue #1: [paypal:count] returns the sum of quantities currently
        // stored in the visitor's cart.
        if ($autotag['tag'] == 'paypal'
            && strtolower($parameter) == 'count'
        ) {
            $cart = PAYPAL_getCart();
            $count = isset($cart->itemcount) ? (int) $cart->itemcount : 0;

            return str_replace($autotag['tagstr'], (string) $count, $content);
        }

        $pid = COM_applyFilter($parameter);
        if (!is_numeric($pid) || (int) $pid <= 0) {
            return $content;
        }
        $pid = (int) $pid;

        if ($autotag['tag'] == 'paypal') {
            $url = $_PAY_CONF['site_url'] . '/product_detail.php?product=' . $pid;
            if (empty($autotag['parm2'])) {
                $linktext = DB_getItem(
                    $_TABLES['paypal_products'],
                    'name',
                    "id = '{$pid}'"
                );
            } else {
                $linktext = $autotag['parm2'];
            }

            $link = COM_createLink($linktext, $url);

            return str_replace($autotag['tagstr'], $link, $content);
        }

        if ($autotag['tag'] == 'paypal_product') {
            $_SCRIPTS->setJavaScriptFile(
                'paypal_cart',
                '/' . $_PAY_CONF['paypal_folder'] . '/js/jcart-javascript.php'
            );
            $html = PAYPAL_displayProducts($pid, 1);

            return str_replace($autotag['tagstr'], $html, $content);
        }

        return $content;
    }

    if ($op == 'closetag') {
        return array();
    }

    return $content;
}

/**
* Geeklog informs us that we're about to be enabled or disabled
*
* @param    boolean     $enable     true = we're being enabled, false = disabled
* @return   void
*
*/
function plugin_enablestatechange_paypal ($enable)
{
    global $_TABLES;

    $is_enabled = $enable ? 1 : 0;

    // toggle paypal block
    DB_query ("UPDATE {$_TABLES['blocks']} SET is_enabled = $is_enabled WHERE (type = 'phpblock') AND (phpblockfn = 'phpblock_paypal_cart')");
    DB_query ("UPDATE {$_TABLES['blocks']} SET is_enabled = $is_enabled WHERE (type = 'phpblock') AND (phpblockfn = 'phpblock_paypal_randomBlock')");
}


/**
* Display products list
*
* @param    numeric     $autotag     0 = shop, 1 = autotag,  2 = block
* @param    numeric     $category
* @param    numeric     $column      override column setting
* @return   html for product list
*
*/
function PAYPAL_displayProducts ($pid = '', $autotag=0, $category = '', $column = '')
{
    global $_CONF, $_PAY_CONF, $LANG_PAYPAL_1, $LANG_PAYPAL_CART, $_TABLES, $_USER;

    $retval = '';
    $p_retval = '';
    $field = '';
    $category_link = '';

    // Get products from database
    $sql = "SELECT p.id,p.type,p.item_id,p.name,p.short_description,p.price,p.product_type, p.logged, p.hidden,
            i.pi_filename,  p.owner_id, p.group_id, p.perm_owner, p.perm_group, p.perm_members, p.perm_anon,
			p.customisable, p.discount_p, p.discount_a, p.price_ref, p.weight, p.shipping_type,p.billingamt,p.duration_type
            FROM {$_TABLES['paypal_products']} AS p
            LEFT JOIN {$_TABLES['paypal_images']} AS i
            ON i.pi_pid = p.id AND i.pi_img_num IN (SELECT MIN(pi_img_num) FROM {$_TABLES['paypal_images']} WHERE pi_img_num >= 1 AND pi_pid = p.id)  
            ";
            
    $sql_index = $sql  . ' WHERE p.active=1 ';
    
	//Clean input
    $page = (isset($_REQUEST['page']) && is_numeric($_REQUEST['page']))
        ? (int) $_REQUEST['page']
        : 1;
    $requestedType = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
    $type = array_key_exists($requestedType, $_PAY_CONF['types']) ? $requestedType : '';
	
    // If applicable, limit by category
    if ( $category > 0 ) {
        $sql_index .= " AND p.cat_id = '{$category}'";
    }

    // If applicable, limit by type
    if (!empty($type) ) {
	    
        $sql_index .= " AND p.type = '{$type}' ";
    }

    // If applicable, limit to one article (autotag)
    if (!empty($pid) && is_numeric($pid) ) {
        $sql_index .= " AND p.id = '{$pid}'";
    }

    // If applicable, order by
    if (!empty($_PAY_CONF['order'])) {
	    // 03.27.13 ORDER BY type = error in your SQL syntax 
        $sql_index .= " ORDER BY {$_PAY_CONF['order']}";
    }
	
	if($category != '') {
		$field = 'cat_id';
	} else {
		$category = 0;
	}
	
	$sql_total = $sql_index;

    // If applicable, handle pagination of query
    if (isset($_PAY_CONF['maxPerPage']) && $_PAY_CONF['maxPerPage'] > 0) {
        // Count products from database
        $count = DB_count($_TABLES['paypal_products'], $field, $category);
		
        // Make sure page count is reasonable, if not, fix it
        if ($page <= 0) {
            $page = 1;
        }
        if ((($page-1) * $_PAY_CONF['maxPerPage']) > $count) {
            $page = ceil($count / $_PAY_CONF['maxPerPage']);
        }
        // Add limit for pagination (if applicable)
        if ($count > $_PAY_CONF['maxPerPage']) {
            $sql_index .= ' LIMIT ' . ($page-1) * $_PAY_CONF['maxPerPage'] . ', ' . $_PAY_CONF['maxPerPage'];
        }
    }

    // Display categories
	
    if ($pid == '' && $autotag != 2) {
        if (isset( $_PAY_CONF['categoryColumns']) && $_PAY_CONF['categoryColumns'] > 0) {
            $categoryId = (int) $category;
            $sql = "SELECT c.cat_id, c.cat_name, c.description, c.image
                FROM {$_TABLES['paypal_categories']} AS c
                WHERE c.enabled = '1'
                  AND c.parent_id = {$categoryId}"
                . COM_getPermSQL('AND', 0, 2, 'c')
                . " ORDER BY c.cat_name";
			
            $cat_res = DB_query($sql);
            $nrows = DB_numRows($cat_res);
			
			$breadcrumbs = PAYPAL_Breadcrumbs($category);
			
			if ($breadcrumbs != '') {
				    $retval .= '&nbsp;' . $breadcrumbs;
				} else {
				    $retval .= '&nbsp;' . $LANG_PAYPAL_1['home'];
				}

            if ($nrows > 0) {
				$cat = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates');
				$cat->set_file(array('table'    => 'category_table.thtml'));
				$cat->set_var('width', floor (100 / $_PAY_CONF['categoryColumns']));
				

				$cat->set_file(array('row'      => 'category_row.thtml',
								 'category' => 'category.thtml'));
				for ($i = 1; $i <= $nrows; $i++) {
						$C = DB_fetchArray ($cat_res);
						//image
						if ($_PAY_CONF['displayCatImage'] == 1) {
						   $cat_image = $_PAY_CONF['path_cat_images'] . $C['image'];
						   (is_numeric($_PAY_CONF['catImageWidth'])) ? $imgwidth = $_PAY_CONF['catImageWidth'] : $imgwidth = 100;
							if (file_exists($cat_image) && $C['image'] != '') {
								$cat->set_var('cat_image','<p><a href="' . $_PAY_CONF['site_url'] . '/index.php?n=' 
								. urlencode($C['cat_name']) . '&amp;category='. $C['cat_id'] 
                                . '"><img src="' . $_PAY_CONF['images_cat_url'] . rawurlencode($C['image'])
                                . '" class="cat_image" style="max-width:' . (int) $imgwidth . 'px;height:auto" title="'
                                . htmlspecialchars($C['cat_name'], ENT_QUOTES, 'UTF-8') . '" alt="'
                                . htmlspecialchars($C['cat_name'], ENT_QUOTES, 'UTF-8') . '"></a></p>');
							} else {
								$cat->set_var('cat_image', '');
							}
						} else {
							$cat->set_var('cat_image', '');
						}
						//Description
						if ($_PAY_CONF['displayCatDescription'] == 1 ) {
						    $cat->set_var('cat_description', '<p class="cat_description">' . $C['description'] . '</p>');
						} else {
    						$cat->set_var('cat_description', '');
						}
						if ($_PAY_CONF['categoryHeading'] == '' ) {
						    $cat->set_var('category_heading', '<br' . XHTML .  '>&nbsp;');
						} else {
    						$cat->set_var('category_heading', '<h2>' . $_PAY_CONF['categoryHeading'] . '</h2>');
						}
						$cat->set_var('category_name', $C['cat_name']);
						$cat->set_var('category_link', $_PAY_CONF['site_url'] . '/index.php?n=' 
								. urlencode($C['cat_name']) . '&amp;category='. $C['cat_id'] 
								);

						$cat->parse('catrow', 'category', true);
						if ($i % $_PAY_CONF['categoryColumns'] == 0) {
							$cat->parse('categories', 'row', true);
							$cat->set_var('catrow', '');
						}
				}
				if ($nrows % $_PAY_CONF['categoryColumns'] != 0) {
						$cat->parse('categories', 'row', true);
				}
				
				$retval .= $cat->parse('', 'table');
            }
        }
    }

    // Create product template
    $product = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates');
    $product->set_file(array('start'   => 'product_list_start.thtml',
                             'end'     => 'product_list_end.thtml',
                             'product' => 'product_list_item.thtml',
                             'buy'     => 'buy_now_button.thtml',
							 'custom' => 'customise_button.thtml',
                             'cart'    => 'add_to_cart_button.thtml'));
    $product->set_var('edit', '');
	$product->set_var('free_shipping', '');
    $product->set_var('site_url', $_PAY_CONF['site_url']);
    $product->set_var('paypal_url', 'https://' . $_PAY_CONF['paypalURL']);
    $product->set_var('buy_now_url', $_PAY_CONF['site_url'] . '/buy_now.php');
    $product->set_var('user_id', $_USER['uid']);
    $product->set_var('business', $_PAY_CONF['receiverEmailAddr']);
    $product->set_var('price_label', $LANG_PAYPAL_1['price_label']);
    $product->set_var('currency', $_PAY_CONF['currency']);
    $product->set_var('notify_url', $_PAY_CONF['site_url'] . '/ipn.php');
    $product->set_var('return', $_PAY_CONF['site_url'] . '/index.php?mode=endTransaction');
    $product->set_var('cbt',$LANG_PAYPAL_1['cbt'] . ' ' . $_CONF['site_name']);
    $product->set_var('cancel_return', $_PAY_CONF['site_url'] . '/index.php?mode=cancel');
    $product->set_var('cs', $_PAY_CONF['cs']);
    $product->set_var('cs', $_PAY_CONF['cs']);
    $product->set_var('charset', $_CONF['default_charset']);

    if ($_PAY_CONF['image_url']) {
        $product->set_var('image_url', $_PAY_CONF['image_url']);
    } else {
        $product->set_var('image_url', '');
    }
    if ($_PAY_CONF['cpp_header_image']) {
        $product->set_var('cpp_header_image', $_PAY_CONF['cpp_header_image']);
    } else {
        $product->set_var('cpp_header_image', '');
    }
    if ($_PAY_CONF['cpp_headerback_color']) {
        $product->set_var('cpp_headerback_color', $_PAY_CONF['cpp_headerback_color']);
    } else {
        $product->set_var('cpp_headerback_color', '');
    }
    if ($_PAY_CONF['cpp_headerborder_color']) {
        $product->set_var('cpp_headerborder_color', $_PAY_CONF['cpp_headerborder_color']);
    } else {
        $product->set_var('cpp_headerborder_color', '');
    }
    if ($_PAY_CONF['cpp_payflow_color']) {
        $product->set_var('cpp_payflow_color', $_PAY_CONF['cpp_payflow_color']);
    } else {
        $product->set_var('cpp_payflow_color', '');
    }
    if ($_PAY_CONF['cs']) {
     $product->set_var('cs', $_PAY_CONF['cs']);
    } else {
        $product->set_var('cs', '');
    }

    if (!empty($_REQUEST['n'])) {
        $product->set_var('title', '<h2>' . $_REQUEST['n'] . '</h2>');
    } else if  ($autotag == 2) {
	    $product->set_var('title', '');
	} else {
        $product->set_var('title', '<h2>' . $LANG_PAYPAL_1['featured_products'] . '</h2>');
    }
    if (empty($pid)) {
        $retval .= $product->parse('', 'start');
    } else {
        $retval .= '<div class="paypal-product-grid paypal-product-grid--single">';
    }

    // query
    $prod_res = DB_query($sql_index);
	$prod_total_res = DB_query($sql_total);
	$nb_prod_total = DB_numRows($prod_total_res);
	
	$nb_prod = 0;
	
    // Display each product
    $col_number = 1;

	//Overide column setting for block if user want to
    if ($column >0 && $column <5 ) $_PAY_CONF['products_col'] = $column;
	
    if ($_PAY_CONF['products_col'] == 1) {$product->set_var('width', '100');}
    if ($_PAY_CONF['products_col'] == 2) {$product->set_var('width', '50');}
    if ($_PAY_CONF['products_col'] == 3) {$product->set_var('width', '33');}
    if ($_PAY_CONF['products_col'] == 4) {$product->set_var('width', '25');}
	
	
	while ($A = DB_fetchArray($prod_res)) {
        if (SEC_hasAccess2($A)) {
		    $nb_prod++;
			
			$product->set_var('recurrent', '');
		    $product->set_var('display_price', '');
					
			$product->set_var('id', $A['id']);
			if ($_PAY_CONF['display_item_id'] == 1 && $A['customisable'] == 0 && $A['item_id'] != '') {
				$product->set_var(array (
					'name_button' => $A['name'] . ' | ' . $A['item_id'],
					'name' => $A['name'])
					);
			} else {
				 $product->set_var(array (
					'name' => $A['name'],
					'name_button' => $A['name'])
					);
			}
			$product->set_var('link_name', '<a class="product-details" href="' . $_PAY_CONF['site_url'] . '/product_detail.php?product=' . $A['id'] . '&amp;n='. urlencode($A['name']) . '">' . $A['name'] . '</a>');
			if (($A['hidden'] == 1) && SEC_hasRights('paypal.admin')) {
			  $product->set_var('hidden','<strong><font color="red">' . $LANG_PAYPAL_1['hidden'] . '</font></strong><br/>');
			} else {
			  $product->set_var('hidden','');
			}
			$product->set_var('short_description', PLG_replacetags($A['short_description']));
			if ($A['item_id'] != '' && $_PAY_CONF['display_item_id'] == 1) {
				$product->set_var('item_id', '<p class="product-item-id">' . $A['item_id'] . '</p>');
			} else {
				$product->set_var('item_id', '');
			}
			$product_price = PAYPAL_productPrice($A);
			$product->set_var('price', number_format($product_price, $_CONF['decimal_count'], $_CONF['decimal_separator'], $_CONF['thousand_separator']));
			$product->set_var(array('price_ref' => '',
									'discount'  => ''));
			if ($A['price_ref'] != '' && ($A['price_ref'] > $product_price)) $product->set_var('price_ref', '<span class="price_deleted">' . number_format($A['price_ref'], $_CONF['decimal_count'], $_CONF['decimal_separator'], $_CONF['thousand_separator']) . '</span>');
			if ($A['discount_a'] != '' && $A['discount_a'] != 0 && ($A['price'] > $product_price)) {
				 $product->set_var('price_ref', '<span class="price_deleted">' . number_format($A['price'], $_CONF['decimal_count'], $_CONF['decimal_separator'], $_CONF['thousand_separator']) . '</span>');
			} else if ($A['discount_p'] != '' && ($A['price'] > $product_price)) {
				 $product->set_var('price_ref', '<span class="price_deleted">' . number_format($A['price'], $_CONF['decimal_count'], $_CONF['decimal_separator'], $_CONF['thousand_separator']) . '</span>');
		    }
			
			$product->set_var('price2', $product_price);
			$product->set_var('details', '<a class="product-details" href="' . $_PAY_CONF['site_url'] . '/product_detail.php?product='. $A['id'] . '">' . $LANG_PAYPAL_1['details'] . '</a>');

			//First product image	
			if ($A['pi_filename'] == '') {
				$product->set_var('small_pic', '');
			} else {
				if ($autotag==0) {
				    $width = $_PAY_CONF['thumb_width'];
					$height = $_PAY_CONF['thumb_height'];
				} else if($autotag==1){
				    $width = $_PAY_CONF['max_thumbnail_size'];
					$height = $_PAY_CONF['max_thumbnail_size'];
				} else if($autotag==2){
				    $width = '100%';
					$height = '100%';
				}
                $product->set_var(
                    'small_pic',
                    '<a class="product-details" href="' . $_PAY_CONF['site_url']
                    . '/product_detail.php?n=' . urlencode($A['name']) . '&amp;product=' . (int) $A['id']
                    . '"><img class="paypal-image-link" src="' . $_PAY_CONF['images_url']
                    . rawurlencode($A['pi_filename']) . '" style="max-width:' . htmlspecialchars((string) $width, ENT_QUOTES, 'UTF-8')
                    . ';max-height:' . htmlspecialchars((string) $height, ENT_QUOTES, 'UTF-8')
                    . ';width:auto;height:auto" alt="' . htmlspecialchars($A['name'], ENT_QUOTES, 'UTF-8') . '"></a>'
                );
				
			}

			// FIXME: If a user purchased once with no expiration, this query will not operate correctly
			$time = DB_getItem($_TABLES['paypal_purchases'], 'MAX(UNIX_TIMESTAMP(expiration))',
							   "user_id = {$_USER['uid']} AND product_id = {$A['id']}");
			// Setup purchase links. If anonymous, ask for login. If free or purchased display
			// download link
			// For anymous users, login
			$product->set_var('customisable', '');
			$product->set_var('login', '');
			
			//Weight
            if ($A['shipping_type'] == 0) {
			    $product->set_var('item_weight', '0.00');
				if ($A['product_type'] == 0 && $A['type'] == 'product') {
				    $product->set_var('free_shipping', '<span class="paypal-badge paypal-badge--shipping">' . $LANG_PAYPAL_CART['free_shipping'] . '</span>');
				} else {
				    $product->set_var('free_shipping', '');
			    }
			} else {
    			$product->set_var('item_weight', $A['weight']);
				$product->set_var('free_shipping', '');
			}
			
			$product->set_var('item_ref', $A['item_id']);

			if (( $A['price'] > 0 && ($_USER['uid'] < 2 && $_PAY_CONF['anonymous_buy'] == 0)) || ($A['logged'] == 1 && $_USER['uid'] < 2)) {
				$loginlink = $_PAY_CONF['site_url'] . '/login.php';
				$createlink = $_CONF['site_url'] . '/users.php?mode=new';
				$product->set_var('buy_now', '');
				$product->set_var('add2cart', '');
				$product->set_var('details', '');
				$product->set_var('login', "<em>" . $LANG_PAYPAL_1['you_must'] . ' ' . "<a href=\"$loginlink\">" . $LANG_PAYPAL_1['login'] . "</a> " . $LANG_PAYPAL_1['or'] . " <a href=\"$createlink\">" . $LANG_PAYPAL_1['create_account'] . "</a> " . $LANG_PAYPAL_1['to_purchase'] . "</em>");
			// Free items or items purchases and not expired, active, download
			} else if ( $A['product_type'] == 1 && ( $A['price'] == 0 || $time > time() ) ) {
				$product->set_var('buy_now', '');
				$product->set_var('add2cart', '<a href="' . $_PAY_CONF['site_url'] . '/download.php'
										   . '?id=' . $A['id'] . '">' . $LANG_PAYPAL_1['Download'] . '</a>');
			// Buttons customisation
			} else if ($A['customisable'] == 1) {
			    $product->set_var('buy_now', '');
				$product->set_var('add2cart', '');
				$product->set_var('product_page', $_PAY_CONF['site_url'] . '/product_detail.php?n=' . urlencode($A['name']) . ' &amp;product=' . $A['id']);
				$product->set_var('customise', $LANG_PAYPAL_1['customise']);
				$product->set_var('customisable', $product->parse('', 'custom'));
			// buttons for recurrent
			} else if ($A['type'] == 'recurrent') {
				$product->set_var('buy_now_button', $LANG_PAYPAL_1['buy_now_button']);
				$product->set_var('buy_now', $product->parse('', 'buy'));
				$product->set_var('add_to_cart', '');
				$product->set_var('add2cart', '');
				$recurrent_periode = strtolower($A['duration_type']);
				$recurrent_price = number_format($A['billingamt'], $_CONF['decimal_count'], $_CONF['decimal_separator'], $_CONF['thousand_separator']) . ' ' . $_PAY_CONF['currency'] . ' / ' . $LANG_PAYPAL_1["recurrent_$recurrent_periode"];
				if ($A['price'] > 0) {
					$product->set_var('recurrent', '<br' . XHTML .'>+' . $recurrent_price);
					$product->set_var('display_price', '');
				} else {
					$product->set_var('display_price', 'display:none;');
					$product->set_var('recurrent', $recurrent_price);
				}
			// buttons for everyone else
			} else {
				$product->set_var('buy_now_button', $LANG_PAYPAL_1['buy_now_button']);
				if ( $_PAY_CONF['enable_buy_now'] == 1 ) {
					$product->set_var('buy_now', $product->parse('', 'buy'));
				} else {
					$product->set_var('buy_now', '');
				}
				$product->set_var('add_to_cart', $LANG_PAYPAL_1['add_to_cart']);
				$product->set_var('add2cart', $product->parse('', 'cart'));
			}
			
			//rating
			if (function_exists('RATING_display_rating') && $pid == '' && $_PAY_CONF['view_review'] == 1) {
				$product->set_var('rating',  RATING_display_rating('paypal_' . $A['id'], 'paypal', $A['id'], 1, 5, 64) );
			}else {
				$product->set_var('rating', '');
			}

			//Edit link
			if (SEC_hasRights('paypal.admin')) {
				$product->set_var('edit', "<form action=\"{$_CONF['site_url']}/admin/plugins/paypal/product_edit.php?op=edit&amp;id={$A['id']}\" method=\"POST\"><div style=\"float:right; margin-left:5px;\"> <input type=\"image\" src=\"{$_PAY_CONF['site_url']}/images/edit.png\" name=\"id\" value=\"{$A['id']}\" align=\"middle\" /></div><input type=\"hidden\" name=\"op\" value=\"edit\" /></form>");
			}

            if (($A['hidden'] == 0) || SEC_hasRights('paypal.admin')) {
                $p_retval .= $product->parse('', 'product');
            }
		}
	}
	$retval .= $p_retval;
	
	//Products total
    if ($nb_prod == 0 && $autotag == 2) return;
	if ($autotag != 2 ) {
        if ($nb_prod_total == 0) {
            $product->set_var('products_total', $LANG_PAYPAL_1['no_product_to_display']);
        } else if($nb_prod == 1){
            $product->set_var('products_total', '<p><small>&nbsp;'. $nb_prod . ' ' . $LANG_PAYPAL_1['one_product_to_display'] . ' / ' . $nb_prod_total . ' ' . '</small></p>');
        } else {
            $product->set_var('products_total', '<p><small>&nbsp;'. $nb_prod . ' ' . $LANG_PAYPAL_1['products_to_display'] . ' / ' . $nb_prod_total . ' ' . '</small></p>');
        }
    }

    // Display pagination
    if ((isset($_PAY_CONF['maxPerPage'])) && ($_PAY_CONF['maxPerPage'] > 0) && ($nb_prod_total > $_PAY_CONF['maxPerPage'])) {
		if ($category != '') {
		    $category_link = '?category=' . $category;
		}
        $product->set_var('pagination', COM_printPageNavigation($_PAY_CONF['site_url'] . '/index.php' . $category_link, $page, ceil($count / $_PAY_CONF['maxPerPage'])));
    } else {
        $product->set_var('pagination', '');
    }

    if (empty($pid)) {
        $retval .= $product->parse('', 'end');
    } else {
        $retval .= '</div>';
    }

    return $retval;
}


function hitProduct ($product)
{
    global $_TABLES;
    // increment hit counter for product
    DB_query("UPDATE {$_TABLES['paypal_products']} SET hits = hits + 1 WHERE id = '$product'");

}

/**
* Geeklog is asking us to provide any items that show up in the type
* drop-down on search.php.  Let's users search for ads.
*
* @return   array   (plugin name/entry title) pair for the dropdown
*
*/

function plugin_searchtypes_paypal()
{
    global $LANG_PAYPAL_1;

    $tmp['paypal'] = $LANG_PAYPAL_1['products_search'];

    return $tmp;
}


/**
* This searches for entries matching the user query and returns an array for the
* header and table rows back to search.php where it will be formated and printed
*
* @param    string  $query      Keywords user is looking for
* @param    date    $datestart  Start date to get results for
* @param    date    $dateend    End date to get results for
* @param    string  $topic      The topic they were searching in
* @param    string  $type       Type of items they are searching, or 'all' (deprecated)
* @param    int     $author     Get all results by this author
* @param    string  $keyType    search key type: 'all', 'phrase', 'any'
* @param    int     $page       page number of current search (deprecated)
* @param    int     $perpage    number of results per page (deprecated)
* @return   object              search result object
*
*/

function plugin_dopluginsearch_paypal($query, $datestart, $dateend, $topic, $type, $author, $keyType, $page, $perpage)
{
    global $_TABLES, $_PAY_CONF, $LANG_PAYPAL_1;

    // Make sure the query is SQL safe
    $query = DB_escapeString(trim($query));

    $sql = "SELECT id, name as title, price, pi_filename, short_description  as description, UNIX_TIMESTAMP() AS date, owner_id AS uid, hits, ";
    $sql .= "CONCAT('/{$_PAY_CONF['paypal_folder']}/product_detail.php?product=', id) AS url ";
    $sql .= "FROM {$_TABLES['paypal_products']} AS p ";
	$sql .= "LEFT JOIN {$_TABLES['paypal_images']} AS i
            ON i.pi_pid = p.id AND i.pi_img_num IN (SELECT MIN(pi_img_num) FROM {$_TABLES['paypal_images']} WHERE pi_img_num = 1 AND pi_pid = p.id) ";
	$sql .= "WHERE p.active = '1' 
            AND p.hidden = '0' ";
			
	$sql .= COM_getPermSQL('AND', 0, 2, 'p') . ' ';
	
	if (!empty($datestart) && !empty($dateend)) {
        $delim = substr($datestart, 4, 1);
        if (!empty($delim)) {
            $DS = explode($delim, $datestart);
            $DE = explode($delim, $dateend);
            $startdate = mktime(0,0,0,$DS[1],$DS[2],$DS[0]);
            $enddate = mktime(23,59,59,$DE[1],$DE[2],$DE[0]);
            $sql .= "AND (UNIX_TIMESTAMP(p.created) BETWEEN '$startdate' AND '$enddate') ";
        }
    }
    if (!empty ($author)) {
        $sql .= "AND (p.owner_id = '$author') ";
    }

    $search = new SearchCriteria('paypal', $LANG_paypal_1['products']);
    $search->setRank(3);
    $columns = array('p.name', 'p.short_description');
    list($sql,$ftsql) = $search->buildSearchSQL($keyType, $query, $columns, $sql);

	$formattedResults = array();

	$result = DB_query($sql);
	if ($result) {
		if (DB_numRows($result) == 0) {
			return '';
		}
		while ($row = DB_fetchArray($result)) {
		    //First product image	
            if ($row['pi_filename'] == '') {
                $image = '';
            } else {
                $image = ' <a class="paypal-image-link" href="' . $_PAY_CONF['images_url'] . $row['pi_filename'] . '"><img class="paypal-image-link" src="'. $_PAY_CONF['site_url'] . '/timthumb.php?src=' . $_PAY_CONF['images_url'] . $row['pi_filename'] . '&amp;h=55&amp;zc=1&amp;q=100" alt="' . $row['title'] . '" align="top" style="margin-top:3px;"/></a>';
            }
			$formattedResults[] = array(LF_SOURCE_NAME=>'paypal',
                                LF_SOURCE_TITLE=>$LANG_paypal_1['products'],
                                'title'=>$row['title'] . ' | ' . $row['price'] . ' ' . $_PAY_CONF['currency'],
                                'description'=>$row['description'],
                                'date'=>$row['date'],
                                'url'=>$row['url'],
                                'hits'=>$row['hits'],
                                'uid'=> COM_createLink(htmlspecialchars(COM_getDisplayName( $row['uid'] )),
                                    $_CONF['site_url'] . '/users.php?mode=profile&amp;uid=' . $row['uid']) . $image 
                                 );
		}
	}
	
	$search->setResults($formattedResults);								

    return $search;
}

//Not implemented yet
/*
function phpblock_paypal_searchBlock()
{
	global $_CONF, $_PAY_CONF, $_TABLES, $LANG_PAYPAL_1;
	
	$T = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates/blocks');
	$T->set_file('search_block', 'search_block.thtml');
	$options = '';
	$options .= '<option value="" selected=selected >' . $LANG_PAYPAL_1['category_label'] . '</option>';
	$res = DB_query("SELECT category FROM {$_TABLES['paypal_products']} ORDER BY category");
	while ( $A = DB_fetchArray($res) ) {
	    if ($A['category'] != '') {
		    $options .= '<option value=' . $A['category'] . '>' . $A['category'] . '</option>';
		}
	}
	$T->set_var('site_url', $_PAY_CONF['site_url']);
	$T->set_var('store', $LANG_PAYPAL_1['store']);
	$T->set_var('options', $options);
	$T->set_var('search_button', $LANG_PAYPAL_1['search_button']);
	$T->parse('output', 'search_block');
    $retval = $T->finish ($T->get_var('output'));
	
	return $retval;
}
*/

/**
*   Create a random product block.
*   Shows a single product selected from all actived products.
*
*   @return                 HTML for the block
*/
function phpblock_paypal_randomBlock()
{
    global $_CONF, $_TABLES, $_PAY_CONF;

    $sql = "SELECT p.id "
        . "FROM {$_TABLES['paypal_products']} AS p "
        . "WHERE p.active = 1 AND p.hidden = 0 AND p.show_in_blocks = 1 "
        . COM_getPermSQL('AND', 0, 2, 'p');

    $result = DB_query($sql);
    $selectedId = 0;
    $seen = 0;

    // Reservoir sampling avoids ORDER BY RAND() while giving each accessible
    // product the same chance of being selected.
    while ($row = DB_fetchArray($result)) {
        ++$seen;
        if (mt_rand(1, $seen) === 1) {
            $selectedId = (int) $row['id'];
        }
    }

    if ($selectedId <= 0) {
        return '';
    }

    $sql = "SELECT p.*, i.pi_filename "
        . "FROM {$_TABLES['paypal_products']} AS p "
        . "LEFT JOIN {$_TABLES['paypal_images']} AS i "
        . "ON i.pi_pid = p.id "
        . "AND i.pi_img_num = ("
        . "SELECT MIN(pi_img_num) FROM {$_TABLES['paypal_images']} "
        . "WHERE pi_img_num >= 1 AND pi_pid = p.id"
        . ") "
        . "WHERE p.id = {$selectedId} LIMIT 1";

    $A = DB_fetchArray(DB_query($sql));
    if (!is_array($A) || empty($A['name'])) {
        return '';
    }

    $template = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates/blocks');
    $template->set_file('random_block', 'random_block.thtml');

    $productUrl = $_PAY_CONF['site_url']
        . '/product_detail.php?n=' . rawurlencode($A['name'])
        . '&amp;product=' . (int) $A['id'];

    $smallPic = '';
    if (!empty($A['pi_filename'])) {
        $imageUrl = rtrim($_PAY_CONF['images_url'], '/')
            . '/' . rawurlencode(basename($A['pi_filename']));
        $maxSize = isset($_PAY_CONF['max_thumbnail_size'])
            ? max(1, (int) $_PAY_CONF['max_thumbnail_size'])
            : 160;

        $smallPic = '<a class="product-details" href="' . $productUrl . '">'
            . '<img class="paypal-image-link" loading="lazy" decoding="async" src="'
            . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" '
            . 'style="max-width:' . $maxSize . 'px;height:auto;" '
            . 'alt="' . htmlspecialchars($A['name'], ENT_QUOTES, 'UTF-8') . '"' . XHTML . '>'
            . '</a>';
    }

    $price = number_format(
        PAYPAL_productPrice($A),
        $_CONF['decimal_count'],
        $_CONF['decimal_separator'],
        $_CONF['thousand_separator']
    ) . ' ' . $_PAY_CONF['currency'];

    if ($A['price_ref'] !== '' && (float) $A['price_ref'] > (float) $A['price']) {
        $price = '<span class="price_deleted">'
            . number_format(
                $A['price_ref'],
                $_CONF['decimal_count'],
                $_CONF['decimal_separator'],
                $_CONF['thousand_separator']
            )
            . '</span> ' . $price;
    }

    $template->set_var(array(
        'site_url' => $_PAY_CONF['site_url'],
        'product_url' => $productUrl,
        'item_id' => (int) $A['id'],
        'item_name' => htmlspecialchars($A['name'], ENT_QUOTES, 'UTF-8'),
        'small_pic' => $smallPic,
        'price' => $price,
    ));

    return $template->parse('', 'random_block');
}

function PAYPAL_listPurchases($list='one')
{
    global $_CONF, $_TABLES, $_PAY_CONF, $LANG_PAYPAL_1, $LANG_PAYPAL_ADMIN, $_USER;

    require_once $_CONF['path_system'] . 'lib-admin.php';

    $retval = '';
	
	$defsort_arr = array('field' => 'purchase_date', 'direction' => 'desc');
	
	if ($list == 'all') {
	    if (DB_count($_TABLES['paypal_purchases']) == 0){
	        $retval .= '<p>' . $LANG_PAYPAL_1['purchases_history_empty'] . '</p>';
			return $retval;
	    }
	} else if ($list == 'one') {
	    if (DB_count($_TABLES['paypal_purchases'], 'user_id', $_USER['uid']) == 0){
	        $retval .= '<p>' . $LANG_PAYPAL_1['no_record'] . '</p>';
			return $retval;
	    }
	}

    if ($list == 'all') {
	    $header_arr = array(      // display 'text' and use table field 'field'
		    array('text' => $LANG_PAYPAL_1['purchase_date'], 'field' => 'purchase_date', 'sort' => true),
		    array('text' => $LANG_PAYPAL_ADMIN['products'], 'field' => 'product_id', 'sort' => true),
            array('text' => $LANG_PAYPAL_1['user_id'], 'field' => 'user_id', 'sort' => true),
			array('text' => $LANG_PAYPAL_1['status'], 'field' => 'status', 'sort' => true),
            array('text' => $LANG_PAYPAL_1['txn_id'], 'field' => 'txn_id', 'sort' => true),
            array('text' => $LANG_PAYPAL_1['expiration'], 'field' => 'expiration', 'sort' => true),
			array('text' => $LANG_PAYPAL_1['ID'], 'field' => 'id', 'sort' => true)
        );

        $text_arr = array(
            'has_extras' => true,
            'form_url' => $_CONF['site_admin_url'] . '/plugins/paypal/purchase_history.php'
        );
	} else if ($list == 'one') {
	    if ($_PAY_CONF['display_item_id'] == 1 ) {
			$header_arr = array(      // display 'text' and use table field 'field'
				array('text' => $LANG_PAYPAL_ADMIN['products'], 'field' => 'product_id', 'sort' => true),
				array('text' => $LANG_PAYPAL_1['item_id_label'], 'field' => 'item_id', 'sort' => true),
				array('text' => $LANG_PAYPAL_1['purchase_date'], 'field' => 'purchase_date', 'sort' => true),
				array('text' => $LANG_PAYPAL_1['status'], 'field' => 'status', 'sort' => true),
				array('text' => $LANG_PAYPAL_1['ID'], 'field' => 'id', 'sort' => true),
			);
		} else {
		    $header_arr = array(      // display 'text' and use table field 'field'
				array('text' => $LANG_PAYPAL_ADMIN['products'], 'field' => 'product_id', 'sort' => true),
				array('text' => $LANG_PAYPAL_1['purchase_date'], 'field' => 'purchase_date', 'sort' => true),
				array('text' => $LANG_PAYPAL_1['status'], 'field' => 'status', 'sort' => true),
				array('text' => $LANG_PAYPAL_1['ID'], 'field' => 'id', 'sort' => true),
			);
		}
        $defsort_arr = array('field' => 'id', 'direction' => 'desc');
        $text_arr = array(
            'has_extras' => true
        );
	} else if ($list == 'downloadable') {
	    if ($_PAY_CONF['display_item_id'] == 1 ) {
			$header_arr = array(      // display 'text' and use table field 'field'
				array('text' => $LANG_PAYPAL_ADMIN['products'], 'field' => 'product_id', 'sort' => true),
				array('text' => $LANG_PAYPAL_1['item_id_label'], 'field' => 'item_id', 'sort' => true),
				array('text' => $LANG_PAYPAL_1['purchase_date'], 'field' => 'purchase_date', 'sort' => true),
				array('text' => $LANG_PAYPAL_1['expiration'], 'field' => 'expiration', 'sort' => true),
				array('text' => $LANG_PAYPAL_1['Download'], 'field' => 'product_type', 'sort' => false)
			);
		} else {
		    $header_arr = array(      // display 'text' and use table field 'field'
				array('text' => $LANG_PAYPAL_ADMIN['products'], 'field' => 'product_id', 'sort' => true),
				array('text' => $LANG_PAYPAL_1['purchase_date'], 'field' => 'purchase_date', 'sort' => true),
				array('text' => $LANG_PAYPAL_1['expiration'], 'field' => 'expiration', 'sort' => true),
				array('text' => $LANG_PAYPAL_1['Download'], 'field' => 'product_type', 'sort' => false)
			);
		}
		if ($_PAY_CONF['display_item_id'] == 1 ) $header_arr[] = array('text' => $LANG_PAYPAL_1['item_id_label'], 'field' => 'item_id', 'sort' => true);
        $defsort_arr = array('field' => 'purchase_date', 'direction' => 'desc');
        $text_arr = array(
        );
	}
	
	$sql = "SELECT
	            p.*, u.username, prod.name, prod.product_type
            FROM 
				{$_TABLES['paypal_purchases']} AS p

			LEFT JOIN
				{$_TABLES['users']} AS u 
			ON
				p.user_id = u.uid
			LEFT JOIN
				{$_TABLES['paypal_products']} AS prod
			ON
				p.product_id = prod.id
			WHERE 1=1
			";
	
    if ($list == 'one' || $list == 'downloadable') $sql .= " AND u.uid = {$_USER['uid']}";
    if ($list == 'downloadable') $sql .= " AND TO_DAYS(NOW()) <= TO_DAYS(p.expiration)";


    $query_arr = array(
        'sql'            => $sql
    );

    $retval .= ADMIN_list('paypal', 'PAYPAL_getListField_paypal_purchases',
                          $header_arr, $text_arr, $query_arr, $defsort_arr);

    return $retval;
}

/**
*   Get an individual field for the paypal screen.
*
*   @param  string  $fieldname  Name of field (from the array, not the db)
*   @param  mixed   $fieldvalue Value of the field
*   @param  array   $A          Array of all fields from the database
*   @param  array   $icon_arr   System icon array
*   @param  object  $EntryList  This entry list object
*   @return string              HTML for field display in the table
*/
function PAYPAL_getListField_paypal_purchases($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PAY_CONF, $LANG_PAYPAL_1;

    switch($fieldname) {

        case "id":
			$retval = '<a href="' . $_PAY_CONF['site_url'] . '/transaction.php?type=purchase&amp;id=' . $A['id'] . '" title="'. $LANG_PAYPAL_1['see_transaction'] . '">' . $A['id'] .'</a>';
            break;
		case "product_id":
            $retval =  $A['name'];
            break;
		case "user_id":
            $retval = '<a href="' . $_CONF['site_url'] . '/users.php?mode=profile&uid=' . $A['user_id'] . '">' . $A['username'] .'</a>';
            break;

		case "txn_id":
            $retval = '<a href="' . $_CONF['site_url'] . '/admin/plugins/paypal/ipnlog.php?view=ipnlog&op=single&txn_id=' . $A['txn_id'] . '">' . $A['txn_id'] . '</a>';
            break;
		case "purchase_date":
			$date = COM_getUserDateTimeFormat($A['purchase_date']);
			$retval = $date[0];
            break;
		case "status":
            if ($A['status'] == 'pending') {
			    $retval = '<a href="' . $_PAY_CONF['site_url'] . '/transaction.php?type=purchase&amp;id=' .
				$A['id'] . '" title="'. $LANG_PAYPAL_1['see_transaction'] . '">' . $LANG_PAYPAL_1[$A['status']] .'</a>';
			} else {
    			$retval =  $LANG_PAYPAL_1[strtolower($A['status'])];
			}
            break;
		case "expiration":
		    if (empty($A['expiration'])) return $retval = $LANG_PAYPAL_1['N.A'];
			$date = COM_getUserDateTimeFormat($A['expiration']);
            ($A['expiration'] != '0000-00-00 00:00:00') ? $retval = $date[0] : $retval = $LANG_PAYPAL_1['N.A'];
            break;
        case "product_type" :
            if ($A['product_type'] == '1') {
     			$retval = '<a href="' . $_PAY_CONF['site_url'] . '/download.php' . '?id=' . $A['product_id'] . '">' . $LANG_PAYPAL_1['Download'] . '</a>';
			} else { 
			    $retval = '';
			}
            break;

        default:
            $retval = stripslashes($fieldvalue);
            break;
    }
    return $retval;
}

function phpblock_PAYPAL_displaySubscriptions ()
{
    $retval = PAYPAL_listSubscriptions('public');
	return $retval;
}

function PAYPAL_listSubscriptions($list, $active = 0, $group ='')
{
    global $_CONF, $_PAY_CONF, $_TABLES, $LANG_ADMIN, $LANG_PAYPAL_1, $_USER;

    require_once $_CONF['path_system'] . 'lib-admin.php';

    $retval = '';
    $header_arr = array();
    $text_arr = array(
        'has_extras' => false,
        'form_url' => '',
    );

    if (DB_count($_TABLES['paypal_subscriptions']) == 0) {
        return '<p>' . $LANG_PAYPAL_1['subscriptions_empty'] . '</p>';
    }

    $defsort_arr = array('field' => 'purchase_date', 'direction' => 'desc');

    if ($list == 'all') {
        $header_arr = array(      // display 'text' and use table field 'field'
			array('text' => $LANG_PAYPAL_1['accession_date'], 'field' => 'purchase_date', 'sort' => true),
			array('text' => $LANG_PAYPAL_1['member'], 'field' => 'user_id', 'sort' => true),
            array('text' => $LANG_PAYPAL_1['subscription'], 'field' => 'product_id', 'sort' => true),
			array('text' => $LANG_PAYPAL_1['amount'], 'field' => 'price', 'sort' => true),
			array('text' => $LANG_ADMIN['edit'], 'field' => 'edit', 'sort' => false),
        );
	} else if ($list =='one') {
	    $header_arr = array(      // display 'text' and use table field 'field'
            array('text' => $LANG_PAYPAL_1['ID'], 'field' => 'id', 'sort' => true),
			array('text' => $LANG_PAYPAL_1['accession_date'], 'field' => 'purchase_date', 'sort' => true),
            array('text' => $LANG_PAYPAL_1['subscription'], 'field' => 'product_id', 'sort' => true),
			array('text' => $LANG_PAYPAL_1['amount'], 'field' => 'price', 'sort' => true),
            array('text' => $LANG_PAYPAL_1['expiration'], 'field' => 'expiration', 'sort' => true)
		);
	} else if ($list == 'public') {
	    $header_arr = array(      // display 'text' and use table field 'field'
			array('text' => '<small>' . $LANG_PAYPAL_1['member'] . '</small>', 'field' => 'user_id', 'sort' => true),
            array('text' => '<small>' . $LANG_PAYPAL_1['subscription'] . '</small>', 'field' => 'product_id', 'sort' => true),
			array('text' => '<small>' . $LANG_PAYPAL_1['accession_date'] . '</small>', 'field' => 'purchase_date', 'sort' => true)
		);
	} else if ($list == 'logo') {
	    $header_arr = array(      // display 'text' and use table field 'field'
			array('text' => '<small>' . $LANG_PAYPAL_1['member'] . '</small>', 'field' => 'user_id', 'sort' => false)
		);
	}

    if ($list == 'all') {
	    $text_arr = array(
            'has_extras' => true,
            'form_url' => $_CONF['site_admin_url'] . '/plugins/paypal/subscriptions.php'
        );
	}
	
	$sql = "SELECT
	            s.*,
				u.username,
				u.fullname,
				u.photo,
				u.email,
				p.name,
				g.grp_name,
				us.user_name,
				i.pi_filename
            FROM 
			    {$_TABLES['paypal_subscriptions']} AS s 
			LEFT JOIN
                {$_TABLES['users']} AS u 
            ON
                s.user_id = u.uid 
			LEFT JOIN
                {$_TABLES['paypal_products']} AS p 
            ON 
			    p.id = s.product_id 
			LEFT JOIN
                {$_TABLES['groups']} AS g 
            ON 
			    s.add_to_group = g.grp_id 
			LEFT JOIN
                {$_TABLES['paypal_users']} AS us 
            ON 
			    s.user_id = us.user_id
            LEFT JOIN {$_TABLES['paypal_images']} AS i
            ON i.pi_pid = p.id AND i.pi_img_num IN (SELECT MIN(pi_img_num) FROM {$_TABLES['paypal_images']} WHERE pi_img_num >= 1 AND pi_pid = p.id)				
			";
	if ($list == 'one') $sql .= " WHERE u.uid ={$_USER['uid']}";
	
	if ( ($_PAY_CONF['display_complete_memberships'] == 0 || $active == 1 ) && ($list == 'public' || $list == 'logo') ) {
	    $sql .= " WHERE TO_DAYS(NOW()) <= TO_DAYS(s.expiration)";
	}

    $query_arr = array(
        'table'          => 'paypal_subscriptions',
        'sql'            => $sql,
        'query_fields'   => array('s.id', 's.product_id', 's.user_id', 's.purchase_date', 's.expiration', 's.add_to_group', 'u.username', 'p.name', 'g.grp_name', 'us.user_name'),
        'default_filter' => COM_getPermSQL ('AND', 0, 3)
    );

    $logo_list = '';
	
	$retval .= ADMIN_list('paypal', 'PAYPAL_getListField_paypal_subscriptions',
                          $header_arr, $text_arr, $query_arr, $defsort_arr);
	
	//Logo
    ($list == 'logo') ? $retval =  $_SESSION['logo_list'] . '<div style="clear:both;"></div>' : $_SESSION['logo_list']  = ''; 	

    return $retval;
}

/**
*   Get an individual field for the paypal screen.
*
*   @param  string  $fieldname  Name of field (from the array, not the db)
*   @param  mixed   $fieldvalue Value of the field
*   @param  array   $A          Array of all fields from the database
*   @param  array   $icon_arr   System icon array
*   @param  object  $EntryList  This entry list object
*   @return string              HTML for field display in the table
*/
function PAYPAL_getListField_paypal_subscriptions($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $LANG_ADMIN, $LANG_STATIC, $_TABLES, $_PAY_CONF, $LANG_PAYPAL_1;

    require_once $_CONF['path_system'] . 'lib-user.php';
	
	switch($fieldname) {
	
        case "edit":
		    $edit_url = $_CONF['site_admin_url'] . '/plugins/paypal/subscriptions.php?mode=edit&amp;id=' . $A['id'];
            $retval = COM_createLink($icon_arr['edit'], $edit_url);
            break;
			
        case "id":
			$retval = '<a href="' . $_PAY_CONF['site_url'] . '/transaction.php?type=subscription&amp;id=' . $A['id'] . '">' . $LANG_PAYPAL_1['see_subscription'] . ' ' . $A['id'] .'</a>';
            break;
			
		case "product_id":
		    if ($A['pi_filename'] != '') {
                $retval = '<img align="absmiddle" src="'. $_PAY_CONF['site_url'] . '/timthumb.php?src=' . $_PAY_CONF['images_url'] . $A['pi_filename'] . '&amp;h=20&amp;&amp;zc=1&amp;q=100" alt="' . $A['name'] . '" /> <a href="' . $_PAY_CONF['site_url'] . '/product_detail.php?product='. $A['product_id'] . '">' . COM_truncate( $A['name'], 60, '...' ) .'</a>';
			} else {
			    $retval = '<a href="' . $_PAY_CONF['site_url'] . '/product_detail.php?product='. $A['product_id'] . '">' . COM_truncate( $A['name'], 60, '...' ) .'</a>';
			}
            break;
			
		case "user_id":
			if ( SEC_hasRights('paypal.admin') ) {
    			//$retval .= ' <a href="' . $_PAY_CONF['site_url'] . '/details.php?mode=edit&uid=' . $A['user_id'] . '"><small>(' . $LANG_PAYPAL_1['edit_user_details'] . ')</small></a>';
				$edit_url = $_PAY_CONF['site_url'] . '/details.php?mode=edit&uid=' . $A['user_id'];
				$retval = COM_createLink($icon_arr['edit'], $edit_url) . ' ';
			} 
			if ($A['user_name'] != '') {
			    $retval .= '<a href="' . $_CONF['site_url'] . '/users.php?mode=profile&uid=' . $A['user_id'] . '">' . $A['user_name'] . '</a>';
			} else if ($A['fullname'] != '') {
			    $retval .= '<a href="' . $_CONF['site_url'] . '/users.php?mode=profile&uid=' . $A['user_id'] . '">' . $A['fullname'] . '</a>';
			} else {
			    $retval .= '<a href="' . $_CONF['site_url'] . '/users.php?mode=profile&uid=' . $A['user_id'] . '">' . $A['username'] . '</a>';
			}
			
			//Logo list
			$photo = PAYPAL_getUserPhoto ($A['user_id'], $A['photo'], $A['email'], 75);
			if (!empty($photo)) $_SESSION['logo_list'] .= '<div class= "paypal_userPhoto"> <span class="helper">' . $photo . '</div>';
			
            break;
			
		case "purchase_date":
            $date = COM_getUserDateTimeFormat($A['purchase_date']);
			$retval = $date[0];
			//$retval = $date[0];
            break;
			
		case "expiration":
			$date = COM_getUserDateTimeFormat($A['expiration']);
			$retval = $date[0];
            break;
			
		case "add_to_group":
			$retval = $A['grp_name'] . ' (' . $A['add_to_group'] . ')';
            break;
			
        case "price":
            $retval = number_format($A['price'], $_CONF['decimal_count'], $_CONF['decimal_separator'], $_CONF['thousand_separator']);
            break;
			
        default:
            $retval = stripslashes($fieldvalue);
            break;
    }
    return '<small>' . $retval . '</small>';
}

//testing
//plugin_runScheduledTask_paypal ();

function plugin_runScheduledTask_paypal ()
{
	global $_TABLES, $_PAY_CONF, $_CONF;
	
    // Update subscriptions
	$case[0] = "TO_DAYS(s.expiration) - TO_DAYS(NOW()) <= {$_PAY_CONF['days_before_expiration']} AND s.notification < 1";
	$case[1] = 'TO_DAYS(NOW()) = TO_DAYS(s.expiration) AND s.notification < 2';
	$case[2] = 'TO_DAYS(NOW()) > TO_DAYS(s.expiration) AND s.notification < 3';
	
    for ($i=0; $i<3;$i++) {	
        $sql = "SELECT s.*, p.name, u.username, u.email
                    FROM {$_TABLES['paypal_subscriptions']} AS s
		    		LEFT JOIN {$_TABLES['paypal_products']} p
		    		    ON p.id = s.product_id
                    LEFT JOIN {$_TABLES['users']} u
                        ON u.uid = s.user_id
                    WHERE 
		    		    $case[$i] 			 
                    ";

        $res = DB_query($sql);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                // Send and update subscription expiration notification
				$level = $A['notification']+1;
	            if ($level > 3) $level = 3;
				PAYPAL_notifyExpiration($A);
		    	//Remove user from group if needed
	    		if ($A['notification'] == 2) PAYPAL_removeFromGroup ($A['add_to_group'], $A['user_id']);
				DB_query("UPDATE {$_TABLES['paypal_subscriptions']} 
                SET notification=$level 
                WHERE id='{$A['id']}'");
            }
        }
    }
	
	//check paypal profile recurring payments
    require_once $_CONF['path'] . 'plugins/paypal/lib/paypal_nvp.php';

    // List and refresh recurring payment profiles.
    $res = DB_query("SELECT * FROM {$_TABLES['paypal_recurrent']} WHERE status ='Active' OR status ='ActiveProfile'");
    while ($A = DB_fetchArray($res)) {
        $profileid = isset($A['profileid']) ? $A['profileid'] : '';
        $profileDetails = GetRecurringPaymentsProfileDetails($profileid);
        $remoteStatus = isset($profileDetails['STATUS']) ? $profileDetails['STATUS'] : '';

        if ($remoteStatus !== '' && $remoteStatus != $A['status']) {
            $safeStatus = DB_escapeString($remoteStatus);
            DB_query("UPDATE {$_TABLES['paypal_recurrent']} SET status ='{$safeStatus}' WHERE profileid='" . DB_escapeString($profileid) . "'");

            COM_errorLog(
                "PAYPAL - RECURRING PAYMENT {$profileid} Status change from '{$A['status']}' "
                . "to '{$remoteStatus}' for user ID: {$A['user_id']} group: {$A['group_id']}"
            );

            if ($remoteStatus !== 'Active' && $remoteStatus !== 'ActiveProfile') {
                PAYPAL_removeFromGroup($A['group_id'], $A['user_id'], 'PAYPAL - RECURRING PAYMENT');
            }
        } else {
            COM_errorLog(
                "PAYPAL - RECURRING PAYMENT {$profileid} Status '{$A['status']}' checked "
                . "for user ID: {$A['user_id']}"
            );
        }
    }
}

function PAYPAL_addsubscription ($membership, $data) 
{
    global $_TABLES, $_PAY_CONF;
	
	$purchase_date = date("Y/m/d");
    $expiration = 'NOW() + INTERVAL ' . $membership['duration'] . " " . $membership['duration_type'];
	if (DEBUG) COM_errorLog('PAYPAL: Expiration ' . $expiration);
	
	$sql = "
	INSERT INTO 
	    {$_TABLES['paypal_subscriptions']}
	SET 
	    product_id = '{$membership['id']}', 
		user_id = '{$data['custom']}', 
        txn_id = '{$data['txn_id']}', 
        purchase_date = '{$purchase_date}', 
        expiration = {$expiration}, 
        price = '{$membership['price']}', 
		status = '{$data['status']}', 
		add_to_group = '{$membership['add_to_group']}'
	";
    
	DB_query($sql);
}

function PAYPAL_addToGroup ($groupid, $uid) 
{
	global $_CONF;
	
	require_once $_CONF['path_system'] . 'lib-user.php';
	if (USER_addGroup($groupid, $uid)) COM_errorLog('Subscription >> User: ' .  $uid . ' added to group: ' . $groupid);
}

function PAYPAL_removeFromGroup ($groupid, $uid, $source = 'PAYPAL - SUBSCRIPTION')
{
	global $_CONF;
	
	require_once $_CONF['path_system'] . 'lib-user.php';
	if (USER_delGroup($groupid, $uid)) COM_errorLog($source . ' >> User: ' .  $uid . ' remove from group: ' . $groupid);
}

/**
* Displays a login form
*
* This is the version of the login form displayed in the content area of the
* page (not the side bar). It will present all options (remote authentication
* - including OpenID, new registration link, etc.) according to the current
* configuration settings.
*
* @param    array   $use_config     options to override some of the defaults
* @return   string                  HTML of the login form
*
*/
function PAYPAL_loginForm($use_config = array())
{
    global $_CONF, $LANG01, $LANG04;

    $retval = '';

    $default_config = array(
        // display options
        'hide_forgotpw_link' => false,

        // for hidden fields to be included in the form
        'hidden_fields'     => '',

        // options to locally override some specific $_CONF options
        'no_3rdparty_login' => false, // $_CONF['user_login_method']['3rdparty']
        'no_openid_login'   => false, // $_CONF['user_login_method']['openid']
        'no_newreg_link'    => false, // $_CONF['disable_new_user_registration']
        'no_plugin_vars'    => false, // call PLG_templateSetVars?

        // default texts
        'title'       => $LANG04[65], // Try Logging in Again
        'message'     => $LANG04[66], // You may have mistyped ...
        'button_text' => $LANG04[80]  // Login
    );

    $config = array_merge($default_config, $use_config);

    $loginform = COM_newTemplate($_CONF['path_layout'] . 'users');
    $loginform->set_file('login', 'loginform.thtml');
    $loginform->set_var('xhtml', XHTML);
    $loginform->set_var('site_url', $_CONF['site_url']);
    $loginform->set_var('site_admin_url', $_CONF['site_admin_url']);
    $loginform->set_var('layout_url', $_CONF['layout_url']);

    $loginform->set_var('start_block_loginagain',
                        COM_startBlock($config['title']));
    $loginform->set_var('lang_message', $config['message']);
    if ($config['no_newreg_link'] || $_CONF['disable_new_user_registration']) {
        $loginform->set_var('lang_newreglink', '');
    } else {
        $loginform->set_var('lang_newreglink', $LANG04[123]);
    }

    $loginform->set_var('lang_username', $LANG04[2]);
    $loginform->set_var('lang_password', $LANG01[57]);
    if ($config['hide_forgotpw_link']) {
        $loginform->set_var('lang_forgetpassword', '');
        $loginform->set_var('forgetpassword_link', '');
    } else {
        $loginform->set_var('lang_forgetpassword', $LANG04[25]);
        $forget = COM_createLink($LANG04[25], $_CONF['site_url']
                                              . '/users.php?mode=getpassword',
                                 array('rel' => 'nofollow'));
        $loginform->set_var('forgetpassword_link', $forget);
    }
    $loginform->set_var('lang_login', $config['button_text']);
    $loginform->set_var('end_block', COM_endBlock());

    // 3rd party remote authentification.
    $services = '';
    if (!$config['no_3rdparty_login'] &&
            $_CONF['user_login_method']['3rdparty'] &&
            ($_CONF['usersubmission'] == 0)) {
        $modules = SEC_collectRemoteAuthenticationModules();
        if (count($modules) > 0) {
            if (!$_CONF['user_login_method']['standard'] &&
                    (count($modules) == 1)) {
                $select = '<input type="hidden" name="service" value="'
                        . $modules[0] . '"' . XHTML . '>' . $modules[0];
            } else {
                // Build select
                $select = '<select name="service">';
                if ($_CONF['user_login_method']['standard']) {
                    $select .= '<option value="">' .  $_CONF['site_name']
                            . '</option>';
                }
                foreach ($modules as $service) {
                    $select .= '<option value="' . $service . '">' . $service
                            . '</option>';
                }
                $select .= '</select>';
            }

            $loginform->set_file('services', 'services.thtml');
            $loginform->set_var('lang_service', $LANG04[121]);
            $loginform->set_var('select_service', $select);
            $loginform->parse('output', 'services');
            $services .= $loginform->finish($loginform->get_var('output'));
        }
    }
    if (! empty($config['hidden_fields'])) {
        // allow caller to (ab)use {services} for hidden fields
        $services .= $config['hidden_fields'];
    }
    $loginform->set_var('services', $services);

    // OpenID remote authentification.
    if (!$config['no_openid_login'] && $_CONF['user_login_method']['openid'] &&
            ($_CONF['usersubmission'] == 0) &&
            !$_CONF['disable_new_user_registration']) {
        $loginform->set_file('openid_login', '../loginform_openid.thtml');
        $loginform->set_var('lang_openid_login', $LANG01[128]);
        $loginform->set_var('input_field_size', 40);

        // for backward compatibility - not used any more
        $app_url = isset($_SERVER['SCRIPT_URI'])
                 ? $_SERVER['SCRIPT_URI']
                 : 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
        $loginform->set_var('app_url', $app_url);

        $loginform->parse('output', 'openid_login');
        $loginform->set_var('openid_login',
            $loginform->finish($loginform->get_var('output')));
    } else {
        $loginform->set_var('openid_login', '');
    }

    if (! $config['no_plugin_vars']) {
        PLG_templateSetVars('loginform', $loginform);
    }
    $loginform->parse('output', 'login');

    $retval .= $loginform->finish($loginform->get_var('output'));

    return $retval;
}

/**
* Display a "to access this area you need to be logged in" message
*
* @return   string      HTML for the message
*
*/
function PAYPAL_loginRequiredForm()
{
    global $_CONF, $LANG_PAYPAL_LOGIN;

    $cfg = array(
        'title'   => $LANG_PAYPAL_LOGIN[1],
        'message' => $LANG_PAYPAL_LOGIN[2]
    );

    return PAYPAL_loginForm($cfg);
}

function PAYPAL_utf8ToUnicodeEntities ($source) {
    // array used to figure what number to decrement from character order value
    // according to number of characters used to map unicode to ascii by utf-8
    $decrement[4] = 240;
    $decrement[3] = 224;
    $decrement[2] = 192;
    $decrement[1] = 0;
   
    // the number of bits to shift each charNum by
    $shift[1][0] = 0;
    $shift[2][0] = 6;
    $shift[2][1] = 0;
    $shift[3][0] = 12;
    $shift[3][1] = 6;
    $shift[3][2] = 0;
    $shift[4][0] = 18;
    $shift[4][1] = 12;
    $shift[4][2] = 6;
    $shift[4][3] = 0;
   
    $pos = 0;
    $len = strlen ($source);
    $encodedString = '';
    while ($pos < $len) {
        $asciiPos = ord (substr ($source, $pos, 1));
        if (($asciiPos >= 240) && ($asciiPos <= 255)) {
            // 4 chars representing one unicode character
            $thisLetter = substr ($source, $pos, 4);
            $pos += 4;
        }
        else if (($asciiPos >= 224) && ($asciiPos <= 239)) {
            // 3 chars representing one unicode character
            $thisLetter = substr ($source, $pos, 3);
            $pos += 3;
        }
        else if (($asciiPos >= 192) && ($asciiPos <= 223)) {
            // 2 chars representing one unicode character
            $thisLetter = substr ($source, $pos, 2);
            $pos += 2;
        }
        else {
            // 1 char (lower ascii)
            $thisLetter = substr ($source, $pos, 1);
            $pos += 1;
        }

        // process the string representing the letter to a unicode entity
        $thisLen = strlen ($thisLetter);
        $thisPos = 0;
        $decimalCode = 0;
        while ($thisPos < $thisLen) {
            $thisCharOrd = ord (substr ($thisLetter, $thisPos, 1));
            if ($thisPos == 0) {
                $charNum = intval ($thisCharOrd - $decrement[$thisLen]);
                $decimalCode += ($charNum << $shift[$thisLen][$thisPos]);
            }
            else {
                $charNum = intval ($thisCharOrd - 128);
                $decimalCode += ($charNum << $shift[$thisLen][$thisPos]);
            }

            $thisPos++;
        }

        if ($thisLen == 1)
            $encodedLetter = "&#". str_pad($decimalCode, 3, "0", STR_PAD_LEFT) . ';';
        else
            $encodedLetter = "&#". str_pad($decimalCode, 5, "0", STR_PAD_LEFT) . ';';

        $encodedString .= $encodedLetter;
    }

    return $encodedString;
} 

function PAYPAL_displayPurchaseHistory () 
{
    global $LANG_PAYPAL_1, $_SCRIPTS;
    
    $js = 'jQuery(function () {
        var tabContainers = jQuery(\'div.tabs > div\');
        
        jQuery(\'div.tabs ul.tabNavigation a\').click(function () {
            tabContainers.hide().filter(this.hash).show();
            
            jQuery(\'div.tabs ul.tabNavigation a\').removeClass(\'selected\');
            jQuery(this).addClass(\'selected\');
            
            return false;
        }).filter(\':first\').click();
    });';
	
	$_SCRIPTS->setJavaScriptLibrary('jquery');
	$_SCRIPTS->setJavaScript($js, true);

    $retval = '<div class="tabs">
      <!-- tabs -->
      <ul class="tabNavigation">
        <li><a href="#purchase">' . $LANG_PAYPAL_1['purchase_history'] . '</a></li>
        <li><a href="#membership">'. $LANG_PAYPAL_1['membership_history'] . '</a></li>
        <li><a href="#downloadable">'. $LANG_PAYPAL_1['downloadable_products'] . '</a></li>
      </ul>

      <!-- tab containers -->
      <div id="purchase">' . COM_startBlock() . PAYPAL_listPurchases('one') . COM_endBlock() . '</div>
      <div id="membership">' . COM_startBlock() .  PAYPAL_listSubscriptions('one') . COM_endBlock() . '</div>
      <div id="downloadable">' . COM_startBlock() .  PAYPAL_listPurchases('downloadable') . COM_endBlock() . '</div>
    </div>';

    return $retval;
}

/**
* Add a block on account user to display a paypal purchase history
*
*/
function plugin_profileblocksdisplay_paypal ($uid)
{
    global $_USER, $LANG_PAYPAL_1;
    
    $retval = '';
    
    if ($_USER['uid'] == $uid) $retval = '<h2>'. $LANG_PAYPAL_1['purchase_history'] . '</h2>'. PAYPAL_displayPurchaseHistory();
 
    return $retval;
}

function PAYPAL_getDetailsForm ($details, $url, $button, $shipping = 0)
{
    global $_CONF, $_PAY_CONF, $LANG_PAYPAL_1, $_TABLES;

    $details = array_merge(array(
        'user_id' => '',
        'user_name' => '',
        'user_street1' => '',
        'user_street2' => '',
        'user_postal' => '',
        'user_city' => '',
        'user_country' => '',
        'user_phone1' => '',
        'user_phone2' => '',
        'user_fax' => '',
        'user_contact' => '',
        'user_proid' => '',
    ), is_array($details) ? $details : array());

	//Display form
	$retval = '';
	if ($url == '') return $retval;
		
	$template = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates');
    $template->set_file(array('details' => 'details_form.thtml'));
    $template->set_var('validation_url', $url);
	$template->set_var('xhtml', XHTML);
    $template->set_var('uid', '<input type="hidden" name="user_id" value="' . $details['user_id'] .'" />');
	if (isset($_REQUEST['pay_by']) && $_REQUEST['pay_by'] == 'check') {
    	$template->set_var('operation', '<input type="hidden" name="pay_by" value="check" /><input type="hidden" name="shipping" value="' . $shipping . '" />');
	} else {
	    $template->set_var('operation', '');
	}

	//labels
	$template->set_var('my_details', $LANG_PAYPAL_1['my_details']);
	if (SEC_hasRights('paypal.admin')) {
	    $template->set_var('my_details', $LANG_PAYPAL_1['editing_user_details'] . ' #' . $details['user_id']);
	}
	$template->set_var('name_label', $LANG_PAYPAL_1['pro_name']);
	$template->set_var('street1_label', $LANG_PAYPAL_1['street1']);
	$template->set_var('street2_label', $LANG_PAYPAL_1['street2']);
	$template->set_var('postal_label', $LANG_PAYPAL_1['postal']);
	$template->set_var('city_label', $LANG_PAYPAL_1['city']);
	$template->set_var('country_label', $LANG_PAYPAL_1['country']);
	$template->set_var('phone1_label', $LANG_PAYPAL_1['phone1']);
	$template->set_var('phone2_label', $LANG_PAYPAL_1['phone2']);
	$template->set_var('fax_label', $LANG_PAYPAL_1['fax']);
	$template->set_var('contact_label', $LANG_PAYPAL_1['contact']);
	$template->set_var('proid_label', $LANG_PAYPAL_1['proid']);
	//values
	$template->set_var('name', $details['user_name']);
	$template->set_var('street1', $details['user_street1']);
	$template->set_var('street2', $details['user_street2']);
	$template->set_var('postal', $details['user_postal']);
	$template->set_var('city', $details['user_city']);
	$template->set_var('country', $details['user_country']);
	$template->set_var('phone1', $details['user_phone1']);
	$template->set_var('phone2', $details['user_phone2']);
	$template->set_var('fax', $details['user_fax']);
	$template->set_var('contact', $details['user_contact']);
	$template->set_var('proid', $details['user_proid']);

	//validation button
	$template->set_var('save_button', $button);
	
	$template->set_var('required_field', $LANG_PAYPAL_1['required_field']);
	
	$retval .= $template->parse('', 'details');
	
	return $retval;
}

/**
* Creates a <option> list for use in category form, shipping_cost form
*
* @return   string  Formated HTML of option values
*
*/
function PAYPAL_adOptionList( $table, $selection, $selected='', $sortcol='', $where='' )
{
    global $_DB_table_prefix, $_TABLES;

    $retval = '';

    $sql = "SELECT $selection  
	        FROM $table
			";

    if( $where != '' )
    {
        $sql .= " WHERE $where";
    }
	if ($sortcol != '') {
        $sql .= " ORDER BY {$sortcol} ASC";
	}
	$result = DB_query( $sql );
    $nrows = DB_numRows( $result );

    for( $i = 0; $i < $nrows; $i++ )
    {
        $A = DB_fetchArray( $result, true );
		($A['pid'] != '0') ? $spacer = '&nbsp; &nbsp;' : $spacer ='';
        $retval .= '<option value="' . $A[0] . '"';

        if( is_array( $selected ) AND count( $selected ) > 0 )
        {
            foreach( $selected as $selected_item )
            {
                if( ($A[0] == $selected_item) && ( $A['pid'] != '0' ) )
                {
                    $retval .= ' selected="selected"';
                }
            }
        }
        elseif( !is_array( $selected ) AND $A[0] == $selected AND $A['pid'] != '0' )
        {
            $retval .= ' selected="selected"';
        }

        $retval .= '>';

        if ($A[2] == '') {
    		$retval .= $spacer . $A[1];
		} else {
		    $retval .= $spacer . $A[1] .  ' - ' . $A[2];
		}
    }

    return $retval;
}

/**
*   Create the breadcrumb display, with links.
*
*   @param  integer $id ID of current category
*   @return string      Location string ready for display
*/
function PAYPAL_Breadcrumbs($id)
{
    global $_TABLES, $LANG_PAYPAL_1, $_PAY_CONF;

    $A = array();
    $location = '';

    $id = (int)$id;
    if ($id < 1) {
        return $location;
    } else {
        $parent = $id;
    }

    while (true) {
        $sql = "SELECT cat_name, cat_id, parent_id
            FROM {$_TABLES['paypal_categories']}
            WHERE cat_id='$parent' " .
            COM_getPermSQL('AND');
        $result = DB_query($sql);
        if (!$result) 
            break;

        $row = DB_fetchArray($result, false);
        $url = '<a href="' . $_PAY_CONF['site_url'] . '/index.php?n=' 
				. urlencode($row['cat_name']) . '&amp;category=' . 
                (int)$row['cat_id'] . '">' . $row['cat_name'] . '</a>';

        $A[] = $url;

        $parent = (int)$row['parent_id'];
        if ($parent == 0) {
            $url = '<a href="' . 
                    COM_buildURL($_PAY_CONF['site_url'] . '/index.php') . 
                    '">' . $LANG_PAYPAL_1['home'] . '</a>';
            $A[] = $url;
            break;
        }
    }

    $B = array_reverse($A);
    $location = implode(' > ', $B);
    return $location;

}

function PAYPAL_parseItemIdentifier($item)
{
    $item = (string) $item;
    $parts = explode('|', $item, 2);

    $productId = isset($parts[0]) && ctype_digit((string) $parts[0])
        ? (int) $parts[0]
        : 0;

    $attributes = array();
    if (isset($parts[1]) && $parts[1] !== '') {
        foreach (explode(',', $parts[1]) as $attributeId) {
            if (ctype_digit((string) $attributeId) && (int) $attributeId > 0) {
                $attributes[] = (int) $attributeId;
            }
        }
    }

    return array(
        'product_id' => $productId,
        'attributes' => array_values(array_unique($attributes)),
    );
}

function PAYPAL_realId($ids)
{
    if (is_array($ids)) {
        $realIds = array();
        foreach ($ids as $id) {
            $parsed = PAYPAL_parseItemIdentifier($id);
            if ($parsed['product_id'] > 0) {
                $realIds[] = $parsed['product_id'];
            }
        }
        return $realIds;
    }

    $parsed = PAYPAL_parseItemIdentifier($ids);
    return $parsed['product_id'];
}

function PAYPAL_productPrice ($product)
{
    global $_CONF;
	
	$price = $product['price'];
	
	if ($product['discount_a'] != '' && $product['discount_a'] != 0) {
    	$price = $product['price'] - $product['discount_a'];
	    return number_format($price, 2, '.', '');
	}
	if ($product['discount_p'] != '' && $product['discount_p'] != 0) $price = $product['price'] - ($product['price'] * ($product['discount_p']/100));
	
	return number_format($price, 2, '.', '');
}

function PAYPAL_getCartShippingContext($items)
{
    global $_TABLES;

    $weight = 0.0;
    $categories = array();

    if (!is_array($items)) {
        return array('weight' => 0.0, 'categories' => array());
    }

    foreach ($items as $item) {
        if (!is_array($item) || empty($item['id'])) {
            continue;
        }

        $parsed = PAYPAL_parseItemIdentifier($item['id']);
        $productId = (int) $parsed['product_id'];
        $quantity = isset($item['qty']) ? max(1, (int) $item['qty']) : 1;

        if ($productId <= 0) {
            continue;
        }

        $res = DB_query(
            "SELECT cat_id, shipping_type, weight "
            . "FROM {$_TABLES['paypal_products']} "
            . "WHERE id = {$productId} LIMIT 1"
        );
        $product = DB_fetchArray($res);

        if (!is_array($product)) {
            continue;
        }

        if (!empty($product['cat_id'])) {
            $categories[] = (int) $product['cat_id'];
        }

        if ((int) $product['shipping_type'] !== 0) {
            $weight += ((float) $product['weight']) * $quantity;
        }
    }

    return array(
        'weight' => round($weight, 3),
        'categories' => array_values(array_unique($categories)),
    );
}

function PAYPAL_getAllowedShippingRates($weight, $categories = array())
{
    global $_TABLES;

    $weight = max(0, (float) $weight);
    $categories = array_map('intval', is_array($categories) ? $categories : array());

    if ($weight <= 0) {
        return array();
    }

    $weightSql = number_format($weight, 3, '.', '');

    $sql = "SELECT sc.*, ss.shipper_service_name, ss.shipper_service_service, "
        . "ss.shipper_service_exclude_cat, st.shipping_to_name, st.shipping_to_order "
        . "FROM {$_TABLES['paypal_shipping_cost']} AS sc "
        . "LEFT JOIN {$_TABLES['paypal_shipper_service']} AS ss "
        . "ON sc.shipping_shipper_id = ss.shipper_service_id "
        . "LEFT JOIN {$_TABLES['paypal_shipping_to']} AS st "
        . "ON sc.shipping_destination_id = st.shipping_to_id "
        . "WHERE {$weightSql} >= sc.shipping_min "
        . "AND {$weightSql} <= sc.shipping_max "
        . "ORDER BY st.shipping_to_order, sc.shipping_amt ASC";

    $res = DB_query($sql);
    $rates = array();

    while ($rate = DB_fetchArray($res)) {
        $excludedCategory = isset($rate['shipper_service_exclude_cat'])
            ? (int) $rate['shipper_service_exclude_cat']
            : 0;

        if ($excludedCategory > 0 && in_array($excludedCategory, $categories, true)) {
            continue;
        }

        $rates[] = $rate;
    }

    return $rates;
}

function PAYPAL_isAllowedShippingAmount($amount, $weight, $categories = array())
{
    $amount = round((float) $amount, 2);
    $rates = PAYPAL_getAllowedShippingRates($weight, $categories);

    if (empty($rates)) {
        return abs($amount) < 0.005;
    }

    foreach ($rates as $rate) {
        if (abs($amount - round((float) $rate['shipping_amt'], 2)) < 0.005) {
            return true;
        }
    }

    return false;
}

function PAYPAL_getStockId($item_number)
{
    global $_TABLES;

	$stock_id = '';
    $attributes = '';
	
	if (is_array($item_number)) {
	    foreach ($item_number as $item) {
            $parsedItem = PAYPAL_parseItemIdentifier($item);
            $prod_id = (int) $parsedItem['product_id'];
			$item_id = DB_getItem($_TABLES['paypal_products'], 'item_id', "id={$prod_id}");
			$attributes = '';

			foreach ($parsedItem['attributes'] as $att) {
                $att = (int) $att;
                if ($att <= 0) {
                    continue;
                }

                $code = DB_getItem(
                    $_TABLES['paypal_attributes'],
                    'at_code',
                    "at_id={$att}"
                );
				$attributes .= $code;
			}
			$stock_id[] = $item_id . $attributes;
		}
	} else {
        $parsed = PAYPAL_parseItemIdentifier($item_number);
        $prod_id = $parsed['product_id'];
        $item_id = DB_getItem($_TABLES['paypal_products'], 'item_id', "id=" . (int) $prod_id);
        $atts = $parsed['attributes'];

		foreach ($atts as $att) {
		    if ($att == '') {
			    $code = '';
			} else {
    			$code = DB_getItem($_TABLES['paypal_attributes'], 'at_code', "at_id={$att}");
			}
			$attributes = $attributes . $code;
		}

		$stock_id = $item_id . $attributes;
	}
	return $stock_id;
}

function PAYPAL_stockMovement ($stock_id, $prod_id, $qty=0, $delivery=0, $date='')
{
	global $_TABLES, $LANG_PAYPAL_1;
	
	if ($stock_id == '' || $prod_id == '') {
	    if(DEBUG) COM_errorLog('PAYPAL: The product ID do not exists in our DB');
		return;
	}
	$pid = PAYPAL_realId($prod_id);
	$qty = intval($qty);
	($date == '') ? $date_sql = 'NOW()' : $date_sql = $date;
	
	$sql = "move_date = {$date_sql}, "
		 . "stock_id = '{$stock_id}', "
		 . "deli_id = '{$delivery}', "
		 . "move_cpid = '{$prod_id}', "
		 . "move_qty = '{$qty}'
		 ";
	$sql = "INSERT INTO {$_TABLES['paypal_stock_movements']} SET $sql ";
	DB_query($sql);
	
	if (DEBUG) COM_errorLog('PAYPAL: stock movement - ' . $sql);
	
	if (DB_error()) {
		$msg = $LANG_PAYPAL_1['save_fail'] . ' | SQL: ' . $sql;
		COM_errorLog($msg);
	}
	
	//update paypal_stock
	$sql = "st_qty = st_qty + $qty";
	$stid_exists = DB_getItem($_TABLES['paypal_stock'], 'st_id', "st_id='{$stock_id}'");
	if ( $stid_exists == '') {
        $sql .= ", st_pid = '{$pid}', "
		    . "st_cpid = '{$prod_id}', "
			. "st_id = '{$stock_id}'";
	    $sql = "INSERT INTO {$_TABLES['paypal_stock']} SET $sql ";
        DB_query($sql);
        if (DB_error()) {
			$msg = $LANG_PAYPAL_1['save_fail'] . ' | SQL: ' . $sql;
			COM_errorLog($msg);
		}
	} else {
	    $sql = "UPDATE {$_TABLES['paypal_stock']} SET $sql "
                 . "WHERE st_id = '{$stock_id}'";
		DB_query($sql);
        if (DB_error()) {
			$msg = $LANG_PAYPAL_1['save_fail'] . ' | SQL: ' . $sql;
			COM_errorLog($msg);
		}
	}
	if (DEBUG) COM_errorLog('PAYPAL: stock update - ' . $sql);
	
	return true;
}

function PAYPAL_message ($message, $title = '')
{
    global $LANG_PAYPAL_MESSAGE;
    
    $retval = '';
	if ($title == '') $title = $LANG_PAYPAL_MESSAGE['message'];
    if (!empty($message)) {
        $retval = COM_startBlock($title, '', 'blockheader-message.thtml');
        $retval .= stripslashes($message);
        $retval .= COM_endBlock('blockfooter-message.thtml');
    }
    return $retval;
}


/**
 * Add a record of the purchase to the DB
 *
 * @param array $products Product Id(s) of Product(s) purchased
 * @param array $quantity Quantity of products purchases
 * @param array $data private IPN variables
 * @param array $names
 * @param array $prices
 * @param $return 1=html for pending status 0=txn_id for complete status
 * @param $status pending, complete
 * @param $user_id
 * @param $ipn_id if not empty IPN exists in IPN table
 * @param $date to record old transaction in datetime format YYYY-MM-DD HH:MM:SS
 * @todo implemente physical item vs. download, reflected in 'status'
 */
function PAYPAL_handlePurchase( $products, $quantity, $data, $names, $prices, $return=1, $status='pending', $user_id=0, $ipn_id='', $date='', $txn_type ='check', $payment_type = 'check' ) {

	global $_TABLES, $_CONF, $_PAY_CONF, $LANG_PAYPAL_1, $LANG_PAYPAL_EMAIL, $_USER, $LANG_confignames;

	if (DEBUG) COM_errorLog('PAYPAL: Handle purchase start');
	// initialize runtime state
	$files = array();
    $namelist = array();
    $ipn = array('mc_gross' => 0.0);
    $shipping = isset($_REQUEST['shipping']) && is_numeric($_REQUEST['shipping'])
        ? (float) $_REQUEST['shipping']
        : 0.0;
	if ($user_id == 0) $user_id = $_USER['uid'];
	
	//get information for user table
	$sql = "SELECT * FROM {$_TABLES['paypal_users']} WHERE user_id = {$user_id}";

	$res = DB_query($sql);
	$informations = DB_fetchArray($res);
	
	//txn_id must unique
	$ipn['txn_id'] = COM_makesid();
	//but 
	if($ipn_id != '') $ipn['txn_id'] = $ipn_id;
	
	// for each item purchased, record purchase in purchase table
	for ($i = 1; $i < count($products) +1; $i++) {
		
		if (DEBUG) COM_errorLog('PAYPAL: Product ID ' . $products[$i]);
        // Resolve the product and optional attributes using the common parser.
        $parsedItem = PAYPAL_parseItemIdentifier($products[$i]);
        $productId = (int) $parsedItem['product_id'];

        if ($productId <= 0) {
            if (DEBUG) {
                COM_errorLog('PAYPAL: invalid product identifier ' . $products[$i]);
            }
            continue;
        }

        $sql = "SELECT * FROM {$_TABLES['paypal_products']} "
             . "WHERE id = {$productId}";
        $res = DB_query($sql);
        $A = DB_fetchArray($res);

        if (!is_array($A) || empty($A['id'])) {
            if (DEBUG) {
                COM_errorLog('PAYPAL: product not found ' . $productId);
            }
            continue;
        }
		
		if (DEBUG) COM_errorLog('PAYPAL: Type ' . $A['type']);
		
		if ($A['product_type'] == 1) {
			$files[] = $_PAY_CONF['download_path'] . $A['file'];
		}

		//Create our private IPN
		$ipn['mc_gross'] = ($quantity[$i] * $prices[$i]) + $ipn['mc_gross'];
		$ipn['mc_gross_'.$i] = number_format($quantity[$i] * $prices[$i], 2, '.', '');
		$ipn['quantity'.$i] = $quantity[$i];
		$ipn['item_number'.$i] = $products[$i];
		$ipn['item_name'.$i] = $names[$i];

		// Do record anonymous users in purchase table
		if ( $user_id > 0 ) {
			// Add the purchase to the paypal purchase table
			//TODO add product name
			
			($date == '') ? $date_sql = 'NOW()' : $date_sql = '\'' . $date . '\'';
			
            $safeTxnId = DB_escapeString($ipn['txn_id']);
            $safeStatus = DB_escapeString($status);
            $purchaseQuantity = isset($quantity[$i]) ? (int) $quantity[$i] : 1;
            if ($purchaseQuantity < 1) {
                $purchaseQuantity = 1;
            }

			$sql = "INSERT INTO {$_TABLES['paypal_purchases']} SET product_id = {$productId}, "
				 . "quantity = {$purchaseQuantity}, user_id = " . (int) $user_id . ", "
				 . "txn_id = '{$safeTxnId}', "
				 . 'purchase_date = ' . $date_sql . ", status = '{$safeStatus}'";

			DB_query($sql);
			if (DB_error()) {
                $error = $LANG_PAYPAL_1['save_fail'];
            } else {
                $error = $LANG_PAYPAL_1['save_success'];
            }
			if (DEBUG) {
			    COM_errorLog('PAYPAL: sql ' . $sql);
				COM_errorLog('PAYPAL: ' . $error);
				COM_errorLog('PAYPAL: purchase recorded');
			}
		}

		$namelist[$i] = $ipn['quantity'.$i] . ' x ' . $ipn['item_name'.$i] . ' | ' .  number_format(($ipn['mc_gross_'.$i]/$ipn['quantity'.$i]), $_CONF['decimal_count'], $_CONF['decimal_separator'], $_CONF['thousand_separator']) . ' ' . $_PAY_CONF['currency'];
		
		// stock movement
		$stock_id = PAYPAL_getStockId($ipn['item_number'.$i]);
		$qty = $ipn['quantity'.$i];
		PAYPAL_stockMovement ($stock_id, $ipn['item_number'.$i], -$qty, 0, $date_sql);
	}
	
	if ($ipn_id == '') {
		//continue to build our ipn data  
		$ipn['txn_type'] = $txn_type;
		$ipn['order_date'] = date('H:i:s M d, Y T'); //13:49:40 Jul 06, 2011 PDT
		$ipn['mc_gross'] = number_format($ipn['mc_gross'] + $shipping, 2, '.', '');
		$ipn['mc_currency'] = $_PAY_CONF['currency'];
		$ipn['custom'] = $user_id;
		$ipn['address_name'] = $informations['user_name'];
		$ipn['address_street'] = $informations['user_street1'] . ' ' . $informations['user_street2'];
		$ipn['address_zip'] = $informations['user_postal'];
		$ipn['address_city'] = $informations['user_city'];
		$ipn['address_country'] = $informations['user_country'];
		$ipn['payer_email'] = DB_getItem($_TABLES['users'],'email',"uid={$user_id}");
		($status != 'pending') ? $payment_status = $status : $payment_status = 'pending';
		$ipn['payment_status'] = $payment_status;
		$ipn['protection_eligibility'] = '';
		$ipn['address_status'] = '';
		$ipn['payer_id'] = '';
		$ipn['tax'] = '';
		$ipn['payment_date'] = '';
		$ipn['charset'] = '';
		$ipn['first_name'] = '';
		$ipn['mc_fee'] = '';
		$ipn['mc_handling'] = $shipping;
		$ipn['address_country_code'] = '';
		$ipn['notify_version'] = '';
		$ipn['payer_status'] = '';
		$ipn['business'] = '';
		$ipn['verify_sign'] = '';
		$ipn['payment_type'] = $payment_type;
		$ipn['last_name'] = '';
		$ipn['address_state'] = '';
		$ipn['receiver_email'] = '';
		$ipn['payment_fee'] = '';
		$ipn['receiver_id'] = '';
		$ipn['residence_country'] = '';
		$ipn['receipt_id'] = '';
		$ipn['handling_amount'] = '';
		$ipn['transaction_subject'] = '';
		$ipn['payment_gross'] = '';
		$ipn['shipping'] = '';
		$ipn['ipn_track_id'] = '';
		
		//Log our IPN
		PAYPAL_Log(str_replace("'", '&rsquo;', $ipn));
		
        // list of product names
        $products_list = '';
		for ($i2 = 1; $i2 < count($products) +1; $i2++) {
			$products_list .= '<p>' . $namelist[$i2] . '</p>';
		}
		if ($ipn['mc_handling'] > 0) {
			$products_list .= '<p>' . $LANG_PAYPAL_1['shipping'] . ' ' . number_format($ipn['mc_handling'], $_CONF['decimal_count'], $_CONF['decimal_separator'], $_CONF['thousand_separator']) . ' ' . $_PAY_CONF['currency'] . '</p>';
		}
		$products_list .= '<p>' . $LANG_PAYPAL_1['total_row_label'] . ' ' . number_format($ipn['mc_gross'], $_CONF['decimal_count'], $_CONF['decimal_separator'], $_CONF['thousand_separator']) . ' ' . $_PAY_CONF['currency'] . '</p>';
			
        // Build the purchaser/receiver message once, then honor the user/anonymous
        // email settings independently.
        $isAnonymousPurchase = ((int) $user_id === 1);
        $sendPurchaserEmail = $isAnonymousPurchase
            ? !empty($_PAY_CONF['purchase_email_anon'])
            : !empty($_PAY_CONF['purchase_email_user']);

			
			// setup templates
			$message = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates/email');
			
			$message->set_file(array('subject' => 'purchase_by_check_' . $status . '_subject.thtml',
									 'message' => 'purchase_by_check_' . $status . '_message.thtml' ));

			
			// site variables
			$message->set_var('site_name', $_CONF['site_name']);
			
			//Email subject
			$message->set_var('order_confirmation', $LANG_PAYPAL_EMAIL['order_confirmation']);
			$message->set_var('purchase_receipt', $LANG_PAYPAL_EMAIL['purchase_receipt']);
			
			//Date of payment
			$payment_date = COM_getUserDateTimeFormat($ipn['payment_date']);
			$message->set_var('payment_date', $payment_date[0]);
			
			$message->set_var('attached_files', '');
			$message->set_var('address_name', $ipn['address_name']);

			$message->set_var('products', $products_list);
			
			//Email messages
			$message->set_var('thank_you', $LANG_PAYPAL_EMAIL['thank_you_order']);
			$message->set_var('thanks', $LANG_PAYPAL_EMAIL['thanks']);

			// paypal details
			$shop_informations = '<p>' . $_PAY_CONF['shop_name'] . '</p><p>' .
									$_PAY_CONF['shop_street1'] . '<br' . XHTML . '>' .
									$_PAY_CONF['shop_street2'] . '<br' . XHTML . '>' .
									$_PAY_CONF['shop_postal'] . ' ' .
									$_PAY_CONF['shop_city'] . ' ' .
									$_PAY_CONF['shop_country'] . '</p><hr' . XHTML . '>';
									
			$shop_informations_bottom = '<p>';
			if ($_PAY_CONF['shop_phone1'] != '') $shop_informations_bottom .= '<p>' . $LANG_confignames['paypal']['shop_phone1'] . ' ' . $_PAY_CONF['shop_phone1'] . '<br' . XHTML . '>';
			if ($_PAY_CONF['shop_phone2'] != '') $shop_informations_bottom .= $LANG_confignames['paypal']['shop_phone2'] . ' ' . $_PAY_CONF['shop_phone2'] . '<br' . XHTML . '>';
			if ($_PAY_CONF['shop_fax'] != '') $shop_informations_bottom .= $LANG_confignames['paypal']['shop_fax'] . ' ' . $_PAY_CONF['shop_fax'] . '<br' . XHTML . '>';
			if ($_PAY_CONF['shop_siret'] != '') $shop_informations_bottom .= $LANG_confignames['paypal']['shop_siret'] . ' ' . $_PAY_CONF['shop_siret'] . '<br' . XHTML . '>';
			$shop_informations_bottom .= '</p>';
									
			$order_date = COM_getUserDateTimeFormat($ipn['order_date']);
			$message->set_var('order_date', $order_date[0]);
			$message->set_var('payer_email', $ipn['payer_email']);
			$message->set_var('address_name', $ipn['address_name']);
			$message->set_var('shop_informations', $shop_informations . $shop_informations_bottom);
			
			$subject = trim($message->parse('output', 'subject'));

			// if specified to mail attachment, do so, otherwise skip attachment

			$message->set_var('send_check', $LANG_PAYPAL_EMAIL['send_check']);
			$text = $message->parse('output', 'message');
            if ($sendPurchaserEmail && !empty($ipn['payer_email'])) {
                COM_mail(
                    $ipn['payer_email'],
                    $subject,
                    $text,
                    $_PAY_CONF['receiverEmailAddr'],
                    true
                );

                if (DEBUG) {
                    COM_errorLog(
                        'PAYPAL: Email was sent to ' . $ipn['payer_email']
                        . ' | Subject: ' . $subject
                    );
                }
            }

		//Send email to receiver
		COM_mail($_PAY_CONF['receiverEmailAddr'], $subject, $subject . ' >> ' . $text, '', true);

		if ($return == 1) {
			if ( $status == 'pending' ) $retval .= '<h2>'. $LANG_PAYPAL_1['order_received'] . '</h2>';
			$retval .= '<p>' . $LANG_PAYPAL_EMAIL['thank_you_order'] . '</p>';
			$retval .= '<blockquote><strong>' . $products_list . '</strong></blockquote>';
			if ( $status == 'pending' ) {
    			$retval .= '<p>&nbsp;</p><p>' . $LANG_PAYPAL_EMAIL['send_check'] . '</p>';
	    		$retval .= '<blockquote><strong>' . $shop_informations . $shop_informations_bottom . '</strong></blockquote>';
			}
            if ($sendPurchaserEmail) {
                $retval .= '<p>' . $LANG_PAYPAL_1['confirm_by_email'] . '</p>';
            }
			
			return $retval;
		} else {
			return $ipn['txn_id'];
		}
	} else {
	    //Update verified in IPN table
	}
}

/**
 * Log an order
 *
 * Logs the order like an incoming IPN (serialized)
 *
 * @param array $transaction of transaction
 */
function PAYPAL_Log($transaction) {
	
	global $_SERVER, $_TABLES;

	// Log to database
	$sql = "INSERT INTO {$_TABLES['paypal_ipnlog']} SET ip_addr = '{$_SERVER['REMOTE_ADDR']}', "
		 . "time = NOW(), verified = 1, txn_id = '{$transaction['txn_id']}', "
		 . "ipn_data = '" . serialize($transaction) . "'";
	
	DB_query($sql);
	
	if (DEBUG) COM_errorLog('PAYPAL: IPN recorded');
	
	return DB_insertId();
}

function PAYPAL_setAllKeys(&$product, $keys)
{
   foreach ($keys as $key) {
        if (!isset($product[$key])) $product[$key] = null;
   }
   
   return;
}

/**
* Get a user's photo, either uploaded or from an external service
*
* NOTE:     All parameters are optional and can be passed as 0 / empty string.
*
* @param    int     $uid    User ID
* @param    string  $photo  name of the user's uploaded image
* @param    string  $email  user's email address (for gravatar.com)
* @param    int     $width  preferred image width
* @return   string          <img> tag or empty string if no image available
*
*/
function PAYPAL_getUSERPhoto ($uid = 0, $photo = '', $email = '', $width = 0, $class='')
{
    global $_CONF, $_TABLES, $_USER;

    $userphoto = '';

    if ($_CONF['allow_user_photo'] == 1) {

        if (($width == 0) && !empty ($_CONF['force_photo_width'])) {
            $width = $_CONF['force_photo_width'];
        }

        // collect user's information with as few SQL requests as possible
        if ($uid == 0) {
            $uid = $_USER['uid'];
            if (empty ($email)) {
                $email = $_USER['email'];
            }
            if (!empty ($_USER['photo']) &&
                    (empty ($photo) || ($photo == '(none)'))) {
                $photo = $_USER['photo'];
            }
        }
        if ((empty ($photo) || ($photo == '(none)')) ||
                (empty ($email) && $_CONF['use_gravatar'])) {
            $result = DB_query ("SELECT email,photo FROM {$_TABLES['users']} WHERE uid = '$uid'");
            list($newemail, $newphoto) = DB_fetchArray ($result);
            if (empty ($photo) || ($photo == '(none)')) {
                $photo = $newphoto;
            }
            if (empty ($email)) {
                $email = $newemail;
            }
        }

        $img = '';
        if (empty ($photo) || ($photo == 'none')) {
            // no photo - try gravatar.com, if allowed
            if ($_CONF['use_gravatar']) {
                $img = 'http://www.gravatar.com/avatar/'
                     . md5( strtolower( trim( $email ) ) );
                if ($width > 0) {
                    $img .= '?s=' . $width;
                }
                if (!empty ($_CONF['gravatar_rating'])) {
                    $img .= '&r=' . $_CONF['gravatar_rating'];
                }
                if (!empty ($_CONF['default_photo'])) {
                    $img .= '&d='
                         . urlencode ($_CONF['default_photo']);
                }
            }
        } else {
            // check if images are inside or outside the document root
            if (strstr ($_CONF['path_images'], $_CONF['path_html'])) {
                $imgpath = substr ($_CONF['path_images'],
                                   strlen ($_CONF['path_html']));
                $img = $_CONF['site_url'] . '/' . $imgpath . 'userphotos/'
                     . $photo;
            } else {
                $img = $_CONF['site_url']
                     . '/getimage.php?mode=userphotos&amp;image=' . $photo;
            }
        }

        if (empty ($img) && !empty ($_CONF['default_photo'])) {
            $img = $_CONF['default_photo'];
        }
        if (!empty ($img)) {
            $userphoto = '<img src="' . $img . '"';
            if ($width > 0) {
                $userphoto .= ' width="' . $width . '"';
            }
            $userphoto .= ' alt="" class="userphoto '. $class . '"' . XHTML . '>';
        }
    }

    return $userphoto;
}

/*****
// PAYPAL_updateUserDetails 
// Update user details
// $fields user_name, user_street1, user_street2, user_postal, user_city,
//     user_country, user_phone1, user_phone2, user_fax, user_contact, user_proid
// $empty If true only empty fields are update. If false all fields are updated 
*/

function PAYPAL_updateUserDetails ($uid, $fields=array(), $empty=true) {
   
    global $_TABLES;
	
	// Check if user exits
    $uid_exist = DB_getItem($_TABLES['paypal_users'],'user_id', "user_id = '$uid'" );
   
    if ($uid_exist != $uid) {
	    // Create user first
		DB_query("INSERT INTO {$_TABLES['paypal_users']} SET user_id =$uid ");
	}
	// Update each field
	foreach ($fields as $field => $value) {
		if ($empty) {
			// Only if field is empty
			if ( DB_getItem($_TABLES['paypal_users'], $field, "user_id = '{$uid}'" ) == '' ) DB_query("UPDATE {$_TABLES['paypal_users']} SET $field='$value' WHERE user_id = {$uid}");
		} else {
			DB_query("UPDATE {$_TABLES['paypal_users']} SET $field=$value WHERE user_id = {$uid}");
		}
	}
}


?>