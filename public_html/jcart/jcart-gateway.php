<?php

/* Reminder: always indent with 4 spaces (no tabs). */
// +---------------------------------------------------------------------------+
// | Paypal Plugin 1.1                                                         |
// +---------------------------------------------------------------------------+
// | jcart-gateway.php                                                         |
// |                                                                           |
// +---------------------------------------------------------------------------+
// | Copyright (C) 2010 by the following authors:                              |
// |                                                                           |
// | Authors: ::Ben - cordiste AT free DOT fr                                  |
// +---------------------------------------------------------------------------+
// | Based on JCART v1.1                                                       |
// |                                                                           |
// | Copyright (C) 2010 by the following authors:                              |
// | JCART v1.1  http://conceptlogic.com/jcart/                                |
// |                                                                           |   
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
 * require core geeklog code
 */
require_once '../../lib-common.php';

$cart = PAYPAL_getCart();

$updateCart = !empty($_POST['jcart_update_cart']);
$emptyCart = !empty($_POST['jcart_empty']);
$checkoutPage = $_PAY_CONF['site_url'] . '/checkout.php';
$payBy = isset($_POST['pay_by']) ? $_POST['pay_by'] : '';
$shipping = isset($_POST['shipping']) && is_numeric($_POST['shipping']) ? $_POST['shipping'] : '0.00';

// WHEN JAVASCRIPT IS DISABLED THE UPDATE AND EMPTY BUTTONS ARE DISPLAYED
// RE-DISPLAY THE CART IF THE VISITOR CLICKS EITHER BUTTON
if ($updateCart || $emptyCart)
	{

	// UPDATE THE CART
	if ($updateCart)
		{
		$cart_updated = $cart->update_cart();
		if ($cart_updated !== true)
			{
			$_SESSION['quantity_error'] = true;
			}
		}

	// EMPTY THE CART
	if ($emptyCart)
		{
		$cart->empty_cart();
		}

    PAYPAL_saveCart($cart);

	// REDIRECT BACK TO THE CHECKOUT PAGE
	header('Location: ' . $checkoutPage);
	exit;
	}

// THE VISITOR HAS CLICKED THE PAYPAL CHECKOUT BUTTON
else 
	{

	///////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////
	/*

	A malicious visitor may try to change item prices before checking out,
	either via javascript or by posting from an external script.

	Here you can add PHP code that validates the submitted prices against
	your database or validates against hard-coded prices.

	The cart data has already been sanitized and is available thru the
	$cart->get_contents() function. For example:

	foreach ($cart->get_contents() as $item)
		{
		$item_id	= $item['id'];
		$item_name	= $item['name'];
		$item_price	= $item['price'];
		$item_qty	= $item['qty'];
		}

	*/
	///////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////

    $valid_prices = true;
    $validatedItems = array();

    foreach ($cart->get_contents() as $item) {
        $parsed = PAYPAL_parseItemIdentifier($item['id']);
        $productId = (int) $parsed['product_id'];
        $quantity = isset($item['qty']) ? (int) $item['qty'] : 0;

        if ($productId <= 0 || $quantity <= 0) {
            $valid_prices = false;
            break;
        }

        $res = DB_query(
            "SELECT * FROM {$_TABLES['paypal_products']} "
            . "WHERE id = {$productId} LIMIT 1"
        );
        $product = DB_fetchArray($res);

        if (!is_array($product)
            || empty($product['id'])
            || (int) $product['active'] !== 1
            || SEC_hasAccess2($product) < 2) {
            $valid_prices = false;
            break;
        }

        $unitPrice = (float) PAYPAL_productPrice($product);
        $attributeNames = array();

        if (!empty($parsed['attributes'])) {
            $attributeIds = array_map('intval', $parsed['attributes']);
            $idList = implode(',', $attributeIds);

            $attributeResult = DB_query(
                "SELECT at.at_id, at.at_name, at.at_price "
                . "FROM {$_TABLES['paypal_product_attribute']} pa "
                . "INNER JOIN {$_TABLES['paypal_attributes']} at "
                . "ON at.at_id = pa.pa_aid "
                . "WHERE pa.pa_pid = {$productId} "
                . "AND at.at_enabled = 1 "
                . "AND at.at_id IN ({$idList})"
            );

            $validAttributeCount = 0;
            while ($attribute = DB_fetchArray($attributeResult)) {
                $unitPrice += (float) $attribute['at_price'];
                $attributeNames[] = $attribute['at_name'];
                ++$validAttributeCount;
            }

            if ($validAttributeCount !== count($attributeIds)) {
                $valid_prices = false;
                break;
            }
        }

        $validatedItems[] = array(
            'id' => $item['id'],
            'name' => $product['name']
                . (!empty($attributeNames) ? ' - ' . implode(', ', $attributeNames) : ''),
            'price' => number_format($unitPrice, 2, '.', ''),
            'qty' => $quantity,
        );
    }

	///////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////

	// IF THE SUBMITTED PRICES ARE NOT VALID
	if ($valid_prices !== true)
		{
		// KILL THE SCRIPT
		die($jcart['text']['checkout_error']);
		}

	// PRICE VALIDATION IS COMPLETE
	// SEND CART CONTENTS TO PAYPAL USING THEIR UPLOAD METHOD, FOR DETAILS SEE http://tinyurl.com/djoyoa
	else if ($valid_prices === true)
		{
			if ($payBy == 'check') {
			   echo COM_refresh($_PAY_CONF['site_url'] . '/informations.php?shipping=' . $shipping . '&pay_by=check');
			   exit();
			} else {
                $merchantIdentity = isset($_PAY_CONF['receiverEmailAddr'])
                    ? trim((string) $_PAY_CONF['receiverEmailAddr'])
                    : '';

                if ($merchantIdentity === '') {
                    COM_errorLog('PayPal checkout blocked: merchant identity is not configured.');

                    $message = isset($LANG_PAYPAL_CART['merchant_not_configured'])
                        ? $LANG_PAYPAL_CART['merchant_not_configured']
                        : 'The PayPal merchant account is not configured.';

                    if (SEC_hasRights('paypal.admin')) {
                        $message .= ' <form method="post" action="'
                            . htmlspecialchars($_CONF['site_admin_url'] . '/configuration.php', ENT_QUOTES, 'UTF-8')
                            . '" style="display:inline">'
                            . '<input type="hidden" name="conf_group" value="paypal">'
                            . '<button type="submit">'
                            . htmlspecialchars(
                                isset($LANG_PAYPAL_1['configuration']) ? $LANG_PAYPAL_1['configuration'] : 'Configuration',
                                ENT_QUOTES,
                                'UTF-8'
                            )
                            . '</button></form>';
                    }

                    COM_output(PAYPAL_createHTMLDocument(
                        COM_showMessageText($message, $LANG_PAYPAL_1['error'])
                    ));
                    exit;
                }
				// PAYPAL COUNT STARTS AT ONE INSTEAD OF ZERO
				$paypal_count = 1;
				$items_query_string = '';
                foreach ($validatedItems as $item) {
                    $items_query_string .= '&item_number_' . $paypal_count . '=' . rawurlencode($item['id']);
                    $items_query_string .= '&item_name_' . $paypal_count . '=' . rawurlencode($item['name']);
                    $items_query_string .= '&amount_' . $paypal_count . '=' . rawurlencode($item['price']);
                    $items_query_string .= '&quantity_' . $paypal_count . '=' . (int) $item['qty'];
                    ++$paypal_count;
                }
				
				$items_query_string .= '&currency_code=' . $_PAY_CONF['currency'];
				$items_query_string .= '&cancel_return=' . urlencode($_PAY_CONF['site_url'] . '/index.php?mode=cancel');
				$items_query_string .= '&return=' . urlencode($_PAY_CONF['site_url'] . '/index.php?mode=endTransaction');
				$items_query_string .= '&notify_url=' . urlencode($_PAY_CONF['site_url'] . '/ipn.php');
				$items_query_string .= '&rm=2';
				$items_query_string .= '&no_note=1';

				$items_query_string .= '&handling_cart=' . $shipping;
				//$items_query_string .= '&shipping_cart=' . $shipping;
				$items_query_string .= '&custom=' . $_USER['uid'];
				$items_query_string .= '&charset=' . $_CONF['default_charset'];
				if ($_PAY_CONF['image_url']) {
					$items_query_string .= '&image_url=' . urlencode($_PAY_CONF['image_url']);
				}
				if ($_PAY_CONF['cpp_header_image']) {
					$items_query_string .= '&cpp_header_image=' . urlencode($_PAY_CONF['cpp_header_image']);
				}
				if ($_PAY_CONF['cpp_headerback_color']) {
					$items_query_string .= '&cpp_headerback_color=' . $_PAY_CONF['cpp_headerback_color'];
				}
				if ($_PAY_CONF['cpp_headerborder_color']) {
					$items_query_string .= '&cpp_headerborder_color=' . $_PAY_CONF['cpp_headerborder_color'];
				}
				if ($_PAY_CONF['cpp_payflow_color']) {
					$items_query_string .= '&cpp_payflow_color=' . $_PAY_CONF['cpp_payflow_color'];
				}
				if ($_PAY_CONF['cs']) {
				 $items_query_string .= '&cs=' . $_PAY_CONF['cs'];
				}
							 
				// REDIRECT TO PAYPAL WITH MERCHANT ID AND CART CONTENTS
				header(
                    'Location: https://' . $_PAY_CONF['paypalURL']
                    . '/cgi-bin/webscr?cmd=_cart&upload=1&business='
                    . rawurlencode($merchantIdentity)
                    . $items_query_string
                );
                exit;
			}
		}
	}

?>
