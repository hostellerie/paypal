<?php
// +--------------------------------------------------------------------------+
// | PayPal Plugin 1.6 - geeklog CMS                                          |
// +--------------------------------------------------------------------------+
// | confirmation.php                                                         |
// |                                                                          |
// | Check page for users of the paypal plugin                                |
// |                                                                          |
// | By default displays available products along with links to purchase      |
// | history and detailed product views                                       |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | Copyright (C) 2011-2014 by the following authors:                        |
// |                                                                          |
// | Authors: Ben     -    ben AT geeklog DOT fr                              |
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

$cart = PAYPAL_getCart();

// take user back to the homepage if the plugin is not active
if (!in_array('paypal', $_PLUGINS) || COM_isAnonUser() || ($cart->itemcount) < 1) {
    echo COM_refresh($_CONF['site_url'] . '/index.php');
    exit;
}

/* Ensure sufficient privs to read this page */
paypal_access_check('paypal.user');

$vars = array('msg' => 'text',
              'mode' => 'alpha',
              'shipping' => 'text'
              );
paypal_filterVars($vars, $_REQUEST);

/* valid price, access and active product only */
$items = array();
$namesfromcart = array();
$item_price = array();
$i = 1;
$quantities = array();
$valid_prices = true;
foreach ($cart->get_contents() as $item) {
    $realid = PAYPAL_realId($item['id']);
	$item_id	= $realid[0];
	$items[$i] = $item['id'];
	$namesfromcart[$i] = $item['name'];
	$quantities[$i] = $item['qty'];
	$item_price[$i]	= $item['price'];
	$A = DB_fetchArray(DB_query("SELECT * FROM {$_TABLES['paypal_products']} WHERE id = '{$item_id}' LIMIT 1"));
    if (!is_array($A)
        || $item_price[$i] <> PAYPAL_productPrice($A)
        || !SEC_hasAccess2($A)
        || !isset($A['active'])
        || $A['active'] != '1'
    ) {
        $valid_prices = false;
    }
	$i++;
}
if ($valid_prices !== true) {
	echo COM_refresh($_CONF['site_url'] . '/index.php');
	exit;
}


//Main

$display = '';
$data = array();

// EMPTY THE CART
$cart->empty_cart();
PAYPAL_saveCart($cart);

$display .= paypal_user_menu();

switch ($_REQUEST['mode']) {
		
	default :

        //Display cart
        $display .= '<div id="cart">
		             <div id="jcart">
                        <ol id="ULcheckoutProcedure" class="paypal-checkout-steps">
                            <li class="paypal-checkout-step is-complete">' . $LANG_PAYPAL_1['checkout_step_1'] . '</li>
                            <li class="paypal-checkout-step is-complete">' . $LANG_PAYPAL_1['checkout_step_2'] . '</li>
                            <li id="LIactiveStep" class="paypal-checkout-step is-active" aria-current="step">' . $LANG_PAYPAL_1['checkout_step_3'] . '</li>
                        </ol>
					</div></div>';

		$display .= PAYPAL_handlePurchase($items, $quantities, $data, $namesfromcart, $item_price);
}

COM_output(PAYPAL_createHTMLDocument($display));

?>