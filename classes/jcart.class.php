<?php

/* Reminder: always indent with 4 spaces (no tabs). */
// +---------------------------------------------------------------------------+
// | Paypal Plugin 1.1                                                         |
// +---------------------------------------------------------------------------+
// | jcart.class.php                                                           |
// |                                                                           |
// +---------------------------------------------------------------------------+
// | Copyright (C) 2010 by the following authors:                              |
// |                                                                           |
// | Authors: ::Ben - cordiste AT free DOT fr                                  |
// +---------------------------------------------------------------------------+
// | Based on JCART v1.1 & Webforce Cart v.1.5                                 |
// |                                                                           |
// | Copyright (C) 2010 by the following authors:                              |
// | JCART v1.1  http://conceptlogic.com/jcart/                                |
// |                                                                           |
// | Copyright (C) 2004 - 2005 by the following authors:                       |
// | Webforce Ltd, NZ http://www.webforce.co.nz/cart/                          |   
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

define("PAYBYCHECK", false);
	
// JCART
class jcart {
	var $total = 0;
	var $itemcount = 0;
	var $totalweight = 0;
	var $items = array();
	var $itemprices = array();
	var $itemqtys = array();
	var $itemname = array();
	var $itemweights = array();

	// GET CART CONTENTS
	function get_contents()
		{
		$items = array();
		foreach($this->items as $tmp_item)
			{
			$item = array();

			$item['id'] = $tmp_item;
			$item['qty'] = $this->itemqtys[$tmp_item];
			$item['price'] = $this->itemprices[$tmp_item];
			$item['name'] = $this->itemname[$tmp_item];
			$item['weight'] = $this->itemweights[$tmp_item];
			$item['subtotal'] = $item['qty'] * $item['price'];
			$items[] = $item;
			}
		return $items;
		}


	// ADD AN ITEM
	function add_item($item_id, $item_qty = 1, $item_price = 0, $item_name = '', $item_weight = 0)
		{
		// VALIDATION
		$valid_item_qty = $valid_item_price = false;

		// IF THE ITEM QTY IS AN INTEGER, OR ZERO
		if (preg_match("/^[0-9-]+$/i", $item_qty))
			{
			$valid_item_qty = true;
			}
		// IF THE ITEM PRICE IS A FLOATING POINT NUMBER
		if (is_numeric($item_price))
			{
			$valid_item_price = true;
			}

		// ADD THE ITEM
		if ($valid_item_qty !== false && $valid_item_price !== false)
			{
			// IF THE ITEM IS ALREADY IN THE CART, INCREASE THE QTY
			if (isset($this->itemqtys[$item_id]) && $this->itemqtys[$item_id] > 0)
				{
				$this->itemqtys[$item_id] = $item_qty + $this->itemqtys[$item_id];
				$this->_update_total();
				$this->_update_totalweight();
				}
			// THIS IS A NEW ITEM
			else
				{
				$this->items[] = $item_id;
				$this->itemqtys[$item_id] = $item_qty;
				$this->itemprices[$item_id] = $item_price;
				$this->itemname[$item_id] = $item_name;
				$this->itemweights[$item_id] = $item_weight;
				}
			$this->_update_total();
			$this->_update_totalweight();
			return true;
			}

		else if	($valid_item_qty !== true)
			{
			$error_type = 'qty';
			return $error_type;
			}
		else if	($valid_item_price !== true)
			{
			$error_type = 'price';
			return $error_type;
			}
		}


	// UPDATE AN ITEM
	function update_item($item_id, $item_qty)
		{
		// IF THE ITEM QTY IS AN INTEGER, OR ZERO
		// UPDATE THE ITEM
		if (preg_match("/^[0-9-]+$/i", $item_qty))
			{
			if($item_qty < 1)
				{
				$this->del_item($item_id);
				}
			else
				{
				$this->itemqtys[$item_id] = $item_qty;
				}
			$this->_update_total();
			$this->_update_totalweight();
			return true;
			}
		}


	// UPDATE THE ENTIRE CART
	// VISITOR MAY CHANGE MULTIPLE FIELDS BEFORE CLICKING UPDATE
	// ONLY USED WHEN JAVASCRIPT IS DISABLED
	// WHEN JAVASCRIPT IS ENABLED, THE CART IS UPDATED ONKEYUP
	function update_cart()
		{
		// POST VALUE IS AN ARRAY OF ALL ITEM IDs IN THE CART
		if (isset($_POST['jcart_item_ids']) && is_array($_POST['jcart_item_ids']))
			{
			// TREAT VALUES AS A STRING FOR VALIDATION
			$item_ids = implode($_POST['jcart_item_ids']);
			}

		// POST VALUE IS AN ARRAY OF ALL ITEM QUANTITIES IN THE CART
		if (isset($_POST['jcart_item_qty']) && is_array($_POST['jcart_item_qty']))
			{
			// TREAT VALUES AS A STRING FOR VALIDATION
			$item_qtys = implode($_POST['jcart_item_qty']);
			}

		// IF NO ITEM IDs, THE CART IS EMPTY
		if (!empty($_POST['jcart_item_id']))
			{
			// IF THE ITEM QTY IS AN INTEGER, OR ZERO, OR EMPTY
			// UPDATE THE ITEM
			if (preg_match("/^[0-9-]+$/i", $item_qtys) || $item_qtys == '')
				{
				// THE INDEX OF THE ITEM AND ITS QUANTITY IN THEIR RESPECTIVE ARRAYS
				$count = 0;

				// FOR EACH ITEM IN THE CART
				foreach ((array) $_POST['jcart_item_id'] as $item_id)
					{
					// GET THE ITEM QTY AND DOUBLE-CHECK THAT THE VALUE IS AN INTEGER
					$update_item_qty = intval(isset($_POST['jcart_item_qty'][$count]) ? $_POST['jcart_item_qty'][$count] : 0);

					if($update_item_qty < 1)
						{
						$this->del_item($item_id);
						}
					else
						{
						// UPDATE THE ITEM
						$this->update_item($item_id, $update_item_qty);
						}

					// INCREMENT INDEX FOR THE NEXT ITEM
					$count++;
					}
				return true;
				}
			}
		// IF NO ITEMS IN THE CART, RETURN TRUE TO PREVENT UNNECSSARY ERROR MESSAGE
		else if (empty($_POST['jcart_item_id']))
			{
			return true;
			}
		}


	// REMOVE AN ITEM
	/*
	GET VAR COMES FROM A LINK, WITH THE ITEM ID TO BE REMOVED IN ITS QUERY STRING
	AFTER AN ITEM IS REMOVED ITS ID STAYS SET IN THE QUERY STRING, PREVENTING THE SAME ITEM FROM BEING ADDED BACK TO THE CART
	SO WE CHECK TO MAKE SURE ONLY THE GET VAR IS SET, AND NOT THE POST VARS

	USING POST VARS TO REMOVE ITEMS DOESN'T WORK BECAUSE WE HAVE TO PASS THE ID OF THE ITEM TO BE REMOVED AS THE VALUE OF THE BUTTON
	IF USING AN INPUT WITH TYPE SUBMIT, ALL BROWSERS DISPLAY THE ITEM ID, INSTEAD OF ALLOWING FOR USER FRIENDLY TEXT SUCH AS 'remove'
	IF USING AN INPUT WITH TYPE IMAGE, INTERNET EXPLORER DOES NOT SUBMIT THE VALUE, ONLY X AND Y COORDINATES WHERE BUTTON WAS CLICKED
	CAN'T USE A HIDDEN INPUT EITHER SINCE THE CART FORM HAS TO ENCOMPASS ALL ITEMS TO RECALCULATE TOTAL WHEN A QUANTITY IS CHANGED, WHICH MEANS THERE ARE MULTIPLE REMOVE BUTTONS AND NO WAY TO ASSOCIATE THEM WITH THE CORRECT HIDDEN INPUT
	*/
	function del_item($item_id)
		{
		$ti = array();
		$this->itemqtys[$item_id] = 0;
		foreach($this->items as $item)
			{
			if($item != $item_id)
				{
				$ti[] = $item;
				}
			}
		$this->items = $ti;
		$this->_update_total();
		$this->_update_totalweight();
		}


    function is_empty()
        {
        return $this->itemcount <= 0;
        }

	// EMPTY THE CART
	function empty_cart()
		{
		$this->total = 0;
		$this->itemcount = 0;
		$this->totalweight = 0;
		$this->items = array();
		$this->itemprices = array();
		$this->itemqtys = array();
		$this->itemname = array();
		$this->itemweights = array();
		}


	// INTERNAL FUNCTION TO RECALCULATE TOTAL
	function _update_total()
		{
		$this->itemcount = 0;
		$this->total = 0;
		if (sizeof($this->items) > 0)
			{
			foreach($this->items as $item)
				{
				$this->total = $this->total + ($this->itemprices[$item] * $this->itemqtys[$item]);

				// TOTAL ITEMS IN CART (ORIGINAL wfCart COUNTED TOTAL NUMBER OF LINE ITEMS)
				$this->itemcount += $this->itemqtys[$item];
				}
			}
		}

	// INTERNAL FUNCTION TO RECALCULATE TOTALWEIGHT
	function _update_totalweight()
		{
		$this->itemcount = 0;
		$this->totalweight = 0;
		if (sizeof($this->items) > 0)
			{
			foreach($this->items as $item)
				{
				$this->totalweight = $this->totalweight + ($this->itemweights[$item] * $this->itemqtys[$item]);

				// TOTAL ITEMS IN CART (ORIGINAL wfCart COUNTED TOTAL NUMBER OF LINE ITEMS)
				$this->itemcount += $this->itemqtys[$item];
				}
			}
		}

	// PROCESS AND DISPLAY CART
	function display_cart($jcart, $block=0)
		{
		global $_CONF, $_PAY_CONF, $LANG_PAYPAL_1, $LANG_PAYPAL_CART, $_USER, $_TABLES, $LANG_PAYPAL_ADMIN, $_SCRIPTS;
		
		// JCART ARRAY HOLDS USER CONFIG SETTINGS
		extract($jcart);

        $error_message = '';
        $src = '';
        $disable_paypal_checkout = '';
        $shippers_radio = '';
        $skip = 0;

		// ASSIGN USER CONFIG VALUES AS POST VAR LITERAL INDICES
		// INDICES ARE THE HTML NAME ATTRIBUTES FROM THE USERS ADD-TO-CART FORM
        $item_id = isset($_POST[$item_id]) ? $_POST[$item_id] : '';
        $item_qty = isset($_POST[$item_qty]) ? $_POST[$item_qty] : '';
        $item_price = isset($_POST[$item_price]) ? $_POST[$item_price] : '';
        //Todo if block==1 shorten name
        $item_name = isset($_POST[$item_name]) ? $_POST[$item_name] : '';
        $item_weight = isset($_POST[$item_weight]) ? $_POST[$item_weight] : '';
        $itemAddRequested = !empty($_POST[$item_add]);

		// ADD AN ITEM
		if ($itemAddRequested)
			{
			$item_added = $this->add_item($item_id, $item_qty, $item_price, $item_name, $item_weight);
			// IF NOT TRUE THE ADD ITEM FUNCTION RETURNS THE ERROR TYPE
			if ($item_added !== true)
				{
				$error_type = $item_added;
				switch($error_type)
					{
					case 'qty':
						$error_message = $text['quantity_error'];
						break;
					case 'price':
						$error_message = $text['price_error'];
						break;
					}
				}
			}

		// UPDATE A SINGLE ITEM
		// CHECKING POST VALUE AGAINST $text ARRAY FAILS?? HAVE TO CHECK AGAINST $jcart ARRAY
		if (isset($_POST['jcart_update_item']) && $_POST['jcart_update_item'] == $jcart['text']['update_button'])
			{
			$item_updated = $this->update_item(isset($_POST['item_id']) ? $_POST['item_id'] : '', isset($_POST['item_qty']) ? $_POST['item_qty'] : '');
			if ($item_updated !== true)
				{
				$error_message = $text['quantity_error'];
				}
			}

		// UPDATE ALL ITEMS IN THE CART
		if (!empty($_POST['jcart_update_cart']) || !empty($_POST['jcart_checkout']))
			{
			$cart_updated = $this->update_cart();
			if ($cart_updated !== true)
				{
				$error_message = $text['quantity_error'];
				}
			}

		// REMOVE AN ITEM
		if (!empty($_GET['jcart_remove']) && !$itemAddRequested && empty($_POST['jcart_update_cart']) && empty($_POST['jcart_check_out']))
			{
			$this->del_item($_GET['jcart_remove']);
			}

		// EMPTY THE CART
		if (!empty($_POST['jcart_empty']))
			{
			$this->empty_cart();
			}

		// DETERMINE WHICH TEXT TO USE FOR THE NUMBER OF ITEMS IN THE CART
		if ($this->itemcount > 1)
			{
			$text['items_in_cart'] = $text['multiple_items'];
			}
		if ($this->itemcount <= 1)
			{
			$text['items_in_cart'] = $text['single_item'];
			}

		// DETERMINE IF THIS IS THE CHECKOUT PAGE
		// WE FIRST CHECK THE REQUEST URI AGAINST THE USER CONFIG CHECKOUT (SET WHEN THE VISITOR FIRST CLICKS CHECKOUT)
		// WE ALSO CHECK FOR THE REQUEST VAR SENT FROM HIDDEN INPUT SENT BY AJAX REQUEST (SET WHEN VISITOR HAS JAVASCRIPT ENABLED AND UPDATES AN ITEM QTY)
		$is_checkout = strpos($_SERVER['REQUEST_URI'], $form_action);
		if ($is_checkout !== false || isset($_REQUEST['jcart_is_checkout']) && $_REQUEST['jcart_is_checkout'] == 'true')
			{
			$is_checkout = true;
			}
		else
			{
			$is_checkout = false;
			}
			
		$retval = '';

		// OVERWRITE THE CONFIG FORM ACTION TO POST TO jcart-gateway.php INSTEAD OF POSTING BACK TO CHECKOUT PAGE
		// THIS ALSO ALLOWS US TO VALIDATE PRICES BEFORE SENDING CART CONTENTS TO PAYPAL
		if ($is_checkout == true) {
			$form_action = $_PAY_CONF['site_url'] . '/jcart/jcart-gateway.php';
		} else {
			$form_action = $_PAY_CONF['site_url'] . '/checkout.php';
		}

		// DEFAULT INPUT TYPE
		// CAN BE OVERRIDDEN IF USER SETS PATHS FOR BUTTON IMAGES
		$input_type = 'submit';

		// IF THIS ERROR IS TRUE THE VISITOR UPDATED THE CART FROM THE CHECKOUT PAGE USING AN INVALID PRICE FORMAT
		// PASSED AS A SESSION VAR SINCE THE CHECKOUT PAGE USES A HEADER REDIRECT
		// IF PASSED VIA GET THE QUERY STRING STAYS SET EVEN AFTER SUBSEQUENT POST REQUESTS
		if (!empty($_SESSION['quantity_error'])) {
			$error_message = $text['quantity_error'];
			unset($_SESSION['quantity_error']);
		}

		// OUTPUT THE CART
		if ($is_checkout == true && $block == 1) {
		    return $LANG_PAYPAL_CART['checkout'] . '...';
		}

		// DISPLAY THE CART HEADER
		$cart = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates');
		if (isset($_REQUEST['pay_by']) && $_REQUEST['pay_by'] == 'check' && $block == 0) {
		    $cart->set_file(array('cart_start'   => 'cart_start_check.thtml',
                                  'cart_item'    => 'cart_item_check.thtml',
								  'cart_empty'   => 'cart_empty.thtml',
                                  'cart_end'     => 'cart_end_check.thtml'));
		} 
		else if ($block == 0) {
            $cart->set_file(array('cart_start'   => 'cart_start.thtml',
                                  'cart_item'    => 'cart_item.thtml',
								  'cart_empty'   => 'cart_empty.thtml',
                                  'cart_end'     => 'cart_end.thtml'));
		} else {
		    $cart->set_file(array('cart_start'   => 'cart_block_start.thtml',
                                  'cart_item'    => 'cart_block_item.thtml',
								  'cart_empty'   => 'cart_empty.thtml',
                                  'cart_end'     => 'cart_block_end.thtml'));
		}
		
		if ($is_checkout == true)
		{
            $steps = '<ol id="ULcheckoutProcedure" class="paypal-checkout-steps">'
                . '<li id="LIactiveStep" class="paypal-checkout-step is-active" aria-current="step">'
                . $LANG_PAYPAL_1['checkout_step_1'] . '</li>'
                . '<li class="paypal-checkout-step">' . $LANG_PAYPAL_1['checkout_step_2'] . '</li>'
                . '<li class="paypal-checkout-step">' . $LANG_PAYPAL_1['checkout_step_3'] . '</li>'
                . '</ol>';
			$cart->set_var('steps', $steps);
		} else if (isset($_REQUEST['pay_by']) && $_REQUEST['pay_by'] == 'check' || PAYBYCHECK == true) {
            $steps = '<ol id="ULcheckoutProcedure" class="paypal-checkout-steps">'
                . '<li class="paypal-checkout-step is-complete">' . $LANG_PAYPAL_1['checkout_step_1'] . '</li>'
                . '<li id="LIactiveStep" class="paypal-checkout-step is-active" aria-current="step">'
                . $LANG_PAYPAL_1['checkout_step_2'] . '</li>'
                . '<li class="paypal-checkout-step">' . $LANG_PAYPAL_1['checkout_step_3'] . '</li>'
                . '</ol>';
			$cart->set_var('steps', $steps);
		} else {
			$cart->set_var('steps', '');
		}
		
		if (isset($_REQUEST['pay_by']) && $_REQUEST['pay_by'] == 'check' && $block == 0) {
			// Get details to edit and display the form on informations.php page
			if (!COM_isAnonUser()) {
				$sql = "SELECT * FROM {$_TABLES['paypal_users']} WHERE user_id = {$_USER['uid']}";
				$res = DB_query($sql);
				$A = DB_fetchArray($res);
                if (!is_array($A)) {
                    $A = array();
                }
                if (!isset($A['user_id'])) {
                    $A['user_id'] = '';
                }
				if ($A['user_id'] == '' && SEC_hasRights('paypal.admin')) {
					$A['user_id'] = isset($_REQUEST['uid']) ? (int) $_REQUEST['uid'] : 0;
				}
				if ($A['user_id'] == '') {
					$A['user_id'] = $_USER['uid'];
				}
				$informations = '<h2>' . $LANG_PAYPAL_1['review_details'] . '</h2>'; 
				$informations .= '<p>' . $LANG_PAYPAL_1['confirm_order_check'] . '</p>';
				$informations .= '<div style="margin:25px;">' . PAYPAL_getDetailsForm($A, $_PAY_CONF['site_url'] . '/details.php?mode=save', $LANG_PAYPAL_1['confirm_order_button'], (isset($_GET['shipping']) ? $_GET['shipping'] : '')) . '</div>';
				$cart->set_var('informations', $informations);
			}	
		}
		// IF THERE'S AN ERROR MESSAGE WRAP IT IN SOME HTML
		if ($error_message) {
			$error_message = "<p class='jcart-error'>$error_message</p>";
			$cart->set_var('error_message', $error_message);
		} else {
			$cart->set_var('error_message', '');
		}
        $cart->set_var('xhtml', XHTML);
		$cart->set_var('form_action', $form_action);
		$cart->set_var('cart_title', $text['cart_title']);
		$cart->set_var('itemcount', $this->itemcount . "&nbsp;" . $text['items_in_cart']);
		$cart->set_var('description', $text['description']);
		$cart->set_var('unit_price', $text['unit_price']);
		$cart->set_var('quantity', $text['quantity']);
		$cart->set_var('item_price', $text['item_price']);

    	$retval .= $cart->parse('', 'cart_start');

		// IF ANY ITEMS IN THE CART
		if($this->itemcount > 0) {
$categories = array();
			// DISPLAY LINE ITEMS
			foreach($this->get_contents() as $item) {
				// ADD THE ITEM ID AS THE INPUT ID ATTRIBUTE
				// THIS ALLOWS US TO ACCESS THE ITEM ID VIA JAVASCRIPT ON QTY CHANGE, AND THEREFORE UPDATE THE CORRECT ITEM
				// NOTE THAT THE ITEM ID IS ALSO PASSED AS A SEPARATE FIELD FOR PROCESSING VIA PHP
				
				$cart->set_var('name', $item['name']);
				$cart->set_var('id', $item['id']);
				//GET ALL PRODUCTS CATEGORIES
				$cat = DB_getItem($_TABLES['paypal_products'], 'cat_id', 'id='. PAYPAL_realId($item['id']));
				if ($cat != 0) 	$categories[] .= $cat;
				$cart->set_var('price', number_format($item['price'], $_CONF['decimal_count'], $_CONF['decimal_separator'], $_CONF['thousand_separator']));
				$cart->set_var('currency_symbol', $text['currency_symbol']);
				$cart->set_var('qty', $item['qty']);
				$cart->set_var('subtotal', number_format($item['subtotal'], $_CONF['decimal_count'], $_CONF['decimal_separator'], $_CONF['thousand_separator']));
				$cart->set_var('remove_png', $_PAY_CONF['site_url']. '/images/remove.png');
				$cart->set_var('remove', $LANG_PAYPAL_CART['remove']);
				$retval .= $cart->parse('', 'cart_item');
			}
		}

		// THE CART IS EMPTY
		else
			{
            $emptyMessage = $block == 1
                ? $text['empty_message']
                : '<strong>' . $text['empty_message'] . '</strong>';
            $cart->set_var('empty', $emptyMessage);
			$retval .= $cart->parse('', 'cart_empty');
			}

		// DISPLAY THE CART FOOTER

		//Subtotal
		($block == 0) ? $cart->set_var('subtotal', $text['subtotal'] . ' <strong>' . number_format($this->total,$_CONF['decimal_count'], $_CONF['decimal_separator'], $_CONF['thousand_separator']) 
		. ' ' . $text['currency_symbol'] . '</strong>') : $cart->set_var('subtotal', '<strong>' . number_format($this->total,$_CONF['decimal_count'], $_CONF['decimal_separator'], $_CONF['thousand_separator']) 
		. ' ' . $text['currency_symbol'] . '</strong>');
		
		// IF THIS IS THE CHECKOUT HIDE THE CART CHECKOUT BUTTON
		if ($this->itemcount > 0
            && $is_checkout !== true
            && (!isset($_REQUEST['pay_by']) || $_REQUEST['pay_by'] != 'check')
        ) {
            $src = '';
			if ($button['checkout']) {
    			$input_type = 'image';
				$src = ' src="' . $button['checkout'] . '" alt="' . $text['checkout_button'] . '" title="" ';
			}
			$cart->set_var('checkout', '<input type="' . $input_type . '" ' . $src . 'id="jcart-checkout" name="jcart_checkout" class="jcart-button" value="' . $text['checkout_button'] . '" />');
		} else {
		    $cart->set_var('checkout', '');
		}
			
		$retval .= $cart->parse('', 'cart_end');
		
		//Update and empty button
		if ($block == 0) {
		    $retval .= "\t\t\t<div class='jcart-hide'>\n";
            $src = '';
		    if ($button['update']) { $input_type = 'image'; $src = ' src="' . $button['update'] . '" alt="' . $text['update_button'] . '" title="" ';	}
		    $retval .= "\t\t\t\t<input type='" . $input_type . "' " . $src ."name='jcart_update_cart' value='" . $text['update_button'] . "' class='jcart-button' />\n";
            $src = '';
		    if ($button['empty']) { $input_type = 'image'; $src = ' src="' . $button['empty'] . '" alt="' . $text['empty_button'] . '" title="" ';	}
		    $retval .= "\t\t\t\t<input type='" . $input_type . "' " . $src ."name='jcart_empty' value='" . $text['empty_button'] . "' class='jcart-button' />\n";
		    $retval .= "\t\t\t</div>\n";
		}
		$retval .= "\t\t\t\t\t</td>\n";
		$retval .= "\t\t\t\t</tr>\n";
		$retval .= "\t\t\t</table>\n\n";
		
		// IF THIS IS THE CHECKOUT DISPLAY THE PAYPAL CHECKOUT BUTTON AND SHIPPING RATE
		if ( ($is_checkout == true  && $block == 0 && ($this->itemcount > 0)) || isset($_REQUEST['pay_by']) && $_REQUEST['pay_by'] == 'check' && $block == 0) {
			// HIDDEN INPUT ALLOWS US TO DETERMINE IF WE'RE ON THE CHECKOUT PAGE
			// WE NORMALLY CHECK AGAINST REQUEST URI BUT AJAX UPDATE SETS VALUE TO jcart-relay.php
			$retval .= "\t\t\t<input type='hidden' id='jcart-is-checkout' name='jcart_is_checkout' value='true' />\n";
			
			$weight = $this->totalweight;
			$weight = str_replace(",",".",$weight);
		    $weight = preg_replace('/[^\d.]/', '', $weight);
			//WEIGHT
			$retval .= "\t\t\t<input type='hidden' id='weight' name='weight' value='{$weight}' />\n";
			
			//SHIPPING RATE
			$shipping = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates');
			$shipping->set_file(array('cart_shipping'   => 'cart_shipping.thtml'));
			$shipping->set_var('choose_shipping', $LANG_PAYPAL_CART['choose_shipping']);
			if ($weight > 0) {		
				//SHIPPER SERVICE
				$sql = "SELECT
						*
					FROM {$_TABLES['paypal_shipping_cost']} AS sc
					LEFT JOIN {$_TABLES['paypal_shipper_service']} AS ss
					ON sc.shipping_shipper_id = ss.shipper_service_id
					LEFT JOIN {$_TABLES['paypal_shipping_to']} AS st
					ON sc.shipping_destination_id = st.shipping_to_id
					WHERE '{$weight}' > sc.shipping_min AND '{$weight}' < sc.shipping_max
					ORDER by st.shipping_to_order, sc.shipping_amt ASC
					";
				$res = DB_query($sql);
				if (DB_numRows($res) > 0) {
				    $i = 0;
				    while ($A = DB_fetchArray($res)) {
                        $skip = 0;
					    if (isset($_GET['shipping']) && $_GET['shipping'] !== '' && $_GET['shipping'] == $A['shipping_amt']) {
						    $checked = ' checked';
							$skip = 0;
						} else if (isset($_GET['shipping']) && $_GET['shipping'] !== '') {
						    $checked = '';
							$skip = 1; 
						} else if ($i == 0) {
							$checked = ' checked';
						} else { 
							$checked = '';
						}
						if ( ( (count($categories) == 1 && in_array($A['shipper_service_exclude_cat'], $categories)) || $A['shipper_service_exclude_cat'] == 0 || count($categories) == 0 ) && $skip == 0 ) {
                            $shippingLabel = trim(
                                $A['shipping_to_name'] . ' | '
                                . $A['shipper_service_name'] . ' - '
                                . $A['shipper_service_service']
                            );
                            $shippers_radio .= '<label class="paypal-shipping-option">'
                                . '<span class="paypal-shipping-option__choice">'
                                . '<input type="radio" name="shipping" value="'
                                . htmlspecialchars($A['shipping_amt'], ENT_QUOTES, 'UTF-8')
                                . '"' . $checked . '> '
                                . htmlspecialchars($shippingLabel, ENT_QUOTES, 'UTF-8')
                                . '</span>'
                                . '<span class="paypal-shipping-option__price">+ '
                                . number_format(
                                    (float) $A['shipping_amt'],
                                    $_CONF['decimal_count'],
                                    $_CONF['decimal_separator'],
                                    $_CONF['thousand_separator']
                                )
                                . ' ' . htmlspecialchars($_PAY_CONF['currency'], ENT_QUOTES, 'UTF-8')
                                . '</span></label>' . LB;
							$i++;
						}
				    }
				} else {
                     $shippers_radio = '<label class="paypal-shipping-option">'
                        . '<span class="paypal-shipping-option__choice">'
                        . '<input type="radio" name="shipping" value="0.00" checked> '
                        . htmlspecialchars($LANG_PAYPAL_CART['free_shipping'], ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '<span class="paypal-shipping-option__price">+ 0.00 '
                        . htmlspecialchars($_PAY_CONF['currency'], ENT_QUOTES, 'UTF-8')
                        . '</span></label>';
				}

			} else {
                $shippers_radio = '<label class="paypal-shipping-option">'
                    . '<span class="paypal-shipping-option__choice">'
                    . '<input type="radio" name="shipping" value="0.00" checked> '
                    . htmlspecialchars($LANG_PAYPAL_CART['free_shipping'], ENT_QUOTES, 'UTF-8')
                    . '</span>'
                    . '<span class="paypal-shipping-option__price">+ 0.00 '
                    . htmlspecialchars($_PAY_CONF['currency'], ENT_QUOTES, 'UTF-8')
                    . '</span></label>';
			}
			
			$shipping->set_var('shipping_radio_buttons', $shippers_radio);
			$retval .= $shipping->parse('', 'cart_shipping');

			// SEND THE URL OF THE CHECKOUT PAGE TO jcart-gateway.php
			// WHEN JAVASCRIPT IS DISABLED WE USE A HEADER REDIRECT AFTER THE UPDATE OR EMPTY BUTTONS ARE CLICKED
			$protocol = 'http://'; if (!empty($_SERVER['HTTPS'])) { $protocol = 'https://'; }
			$retval .= "\t\t\t<input type='hidden' id='jcart-checkout-page' name='jcart_checkout_page' value='" . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "' />\n";

            // PAYPAL CHECKOUT BUTTON
            $src = '';
			if ($button['paypal_checkout'])	{ 
                $input_type = 'image';
                $src = ' src="' . $button['paypal_checkout'] . '" alt="' . $text['checkout_paypal_button'] . '" title="" '; 
            }
			if ((!isset($_REQUEST['pay_by']) || $_REQUEST['pay_by'] != 'check')) {
                $retval .= '<section class="paypal-checkout-section paypal-payment-methods">'
                    . '<h2 class="paypal-checkout-section__title">'
                    . $LANG_PAYPAL_CART['payment_methods_title'] . '</h2>';

				if ($_PAY_CONF['enable_pay_by_paypal']) {
                    $retval .= '<div class="paypal-payment-card paypal-payment-card--paypal">'
                        . '<div class="paypal-payment-card__body">'
                        . '<strong class="paypal-payment-card__title">'
                        . htmlspecialchars($LANG_PAYPAL_CART['payment_card_paypal'], ENT_QUOTES, 'UTF-8')
                        . '</strong>'
                        . '<span class="paypal-payment-card__help">'
                        . htmlspecialchars($LANG_PAYPAL_CART['payment_card_paypal_help'], ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</div>'
                        . '<div class="paypal-payment-card__action">'
                        . '<input type="' . $input_type . '" ' . $src
                        . 'id="jcart-paypal-checkout" name="jcart_paypal_checkout" value="'
                        . $text['checkout_paypal_button'] . '"'
                        . $disable_paypal_checkout . '>'
                        . '</div>'
                        . '</div>';
				}

				if ($is_checkout == true
                    && $block == 0
                    && ($this->itemcount > 0)
                    && $_PAY_CONF['enable_pay_by_check'] == 1
                ) {
					if (!COM_isAnonUser()) {
							$js = 'function payby ( selectedtype )';
							$js .= '{';
							$js .= '  document.jcart.pay_by.value = selectedtype ;';
							$js .= '  document.jcart.submit() ;';
							$js .= '}';
							$_SCRIPTS->setJavaScript($js, true);

							$retval .= '<input type="hidden" name="pay_by">';
                            $retval .= '<div class="paypal-payment-card paypal-payment-card--check">'
                                . '<div class="paypal-payment-card__body">'
                                . '<strong class="paypal-payment-card__title">'
                                . htmlspecialchars($LANG_PAYPAL_CART['payment_check'], ENT_QUOTES, 'UTF-8')
                                . '</strong>'
                                . '</div>'
                                . '<div class="paypal-payment-card__action">'
                                . '<a class="paypal-secondary-action" href="javascript:payby(\'check\')">'
                                . htmlspecialchars($LANG_PAYPAL_CART['payment_check'], ENT_QUOTES, 'UTF-8')
                                . '</a></div></div>';
					}
				}

                $retval .= '</section>';
			}
		}
		$retval .= "\t</form>\n";

		// IF UPDATING AN ITEM, FOCUS ON ITS QTY INPUT AFTER THE CART IS LOADED (DOESN'T SEEM TO WORK IN IE7)
		if (!empty($_POST['jcart_update_item']))
			{
			$retval .= "\t" . '<script type="text/javascript">jQuery(function(){jQuery("#jcart-item-id-' . (isset($_POST['item_id']) ? $_POST['item_id'] : '') . '").focus()});</script>' . "\n";
			}
		
        $retval .= "\t<div class=\"jcart_footer\">\n";
		
		//CONTINUE SHOPPING
        if ($is_checkout == true  && $block == 0) {
            $retval .= '<div class="paypal-checkout-footer">'
                . '<a class="paypal-secondary-action paypal-continue-shopping" href="'
                . $_PAY_CONF['site_url'] . '/index.php">'
                . '&#8592; ' . $LANG_PAYPAL_CART['continue_shopping']
                . '</a></div>';
        }
		
		$retval .= "\t</div></div>\n";
						
		return $retval;

		}
	}
?>