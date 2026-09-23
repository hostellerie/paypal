<?php
// +--------------------------------------------------------------------------+
// | PayPal Plugin 1.6 - geeklog CMS                                          |
// +--------------------------------------------------------------------------+
// | index.php                                                                |
// |                                                                          |
// | Index page for users of the paypal plugin                                |
// |                                                                          |
// | By default displays available products along with links to purchase      |
// | history and detailed product views                                       |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | Copyright (C) 2005-2006 by the following authors:                        |
// |                                                                          |
// | Authors: Vincent Furia     - vinny01 AT users DOT sourceforge DOT net    |
// |                                                                          |
// | Copyright (C) 2009-2014 by the following authors:                        |
// |                                                                          |
// | Authors: ::Ben - cordiste AT free DOT fr                                 |
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
 * Index page for users of the paypal plugin
 *
 * By default displays available products along with links to purchase history
 * and detailed product views
 *
 * @author Vincent Furia <vinny01 AT users DOT sourceforge DOT net>
 * @copyright Vincent Furia 2005 - 2006
 * @package paypal
 * @todo Add more complex logic to decide link display between:  purchase, login, download
 */

/**
 * require core geeklog code
 */
require_once '../lib-common.php';

// take user back to the homepage if the plugin is not active
if (!in_array('paypal', $_PLUGINS)) {
    echo COM_refresh($_CONF['site_url'] . '/index.php');
    exit;
}

/* Ensure sufficient privs to read this page */
paypal_access_check('paypal.viewer');

$vars = array('msg'      => 'text',
              'mode'     => 'alpha',
              'page'     => 'number',
              'category' => 'number',
			  'type'     => 'text',
			  'n'        => 'text',
              );
paypal_filterVars($vars, $_REQUEST);


//Main

$display = '';

$pageTitle = $_PAY_CONF['seo_shop_title'];
if ($_REQUEST['n'] !== '') {
    $pageTitle = $_REQUEST['n'] . ' | ' . $pageTitle;
}

if (SEC_hasRights('paypal.user,paypal.admin', 'OR')) {
    $display .= paypal_user_menu();
} else {
    $display .= paypal_viewer_menu();
}

switch ($_REQUEST['mode']) {
    case 'endTransaction':
        $cart = PAYPAL_getCart();
        $cart->empty_cart();
        PAYPAL_saveCart($cart);

        $txnId = isset($_POST['txn_id']) ? $_POST['txn_id'] : '';
        $firstName = isset($_POST['first_name']) ? $_POST['first_name'] : '';
        $lastName = isset($_POST['last_name']) ? $_POST['last_name'] : '';
        $payerEmail = isset($_POST['payer_email']) ? $_POST['payer_email'] : '';
        $currency = isset($_POST['mc_currency']) ? $_POST['mc_currency'] : '';
        $gross = isset($_POST['mc_gross']) ? $_POST['mc_gross'] : '';
        $itemCount = isset($_POST['num_cart_items']) ? (int) $_POST['num_cart_items'] : 0;

        $msg = $LANG_PAYPAL_1['thanks_details'];
        $msg .= '<p>' . $LANG_PAYPAL_1['transaction'] . ' ' . $txnId . '</p>';
        $msg .= '<p>' . $LANG_PAYPAL_1['name_label'] . ' ' . trim($firstName . ' ' . $lastName)
            . ' | ' . $LANG_PAYPAL_1['email'] . ' ' . $payerEmail . '</p>';

        if ($itemCount > 0) {
            $msg .= '<ul>';
            for ($i = 1; $i <= $itemCount; $i++) {
                $quantity = isset($_POST["quantity{$i}"]) ? $_POST["quantity{$i}"] : '';
                $itemName = isset($_POST["item_name{$i}"]) ? $_POST["item_name{$i}"] : '';
                $itemGross = isset($_POST["mc_gross_{$i}"]) ? $_POST["mc_gross_{$i}"] : '';
                $msg .= '<li>' . $quantity . 'x ' . $itemName . '... ' . $itemGross . ' ' . $currency . '</li>';
            }
            $msg .= '</ul>';
        }

        $msg .= '<p>' . $LANG_PAYPAL_1['total'] . ' ' . $gross . ' ' . $currency . '</p>';
        $display .= COM_showMessageText($msg, $LANG_PAYPAL_1['thanks']);
        break;
	
	case 'cancel':
		$msg = $LANG_PAYPAL_1['cancel_details']; 
        $display .= COM_showMessageText($msg, $LANG_PAYPAL_1['cancel']);
		$display .= PAYPAL_displayProducts('',0,$_REQUEST['category']);
        break;
		
	default :
        if ($_PAY_CONF['paypal_main_header'] !== '' && $_REQUEST['category'] === '') {
            $display .= '<div>' . PLG_replaceTags($_PAY_CONF['paypal_main_header']) . '</div>';
        }

        $display .= PAYPAL_displayProducts('', 0, $_REQUEST['category']);

        if ($_PAY_CONF['paypal_main_footer'] !== '') {
            $display .= '<div>' . PLG_replaceTags($_PAY_CONF['paypal_main_footer']) . '</div>';
        }
		
}

COM_output(PAYPAL_createHTMLDocument($display, $pageTitle));

?>