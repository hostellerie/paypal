<?php
// +--------------------------------------------------------------------------+
// | PayPal Plugin 1.6 - geeklog CMS                                          |
// +--------------------------------------------------------------------------+
// | BaseIPN.class.php                                                        |
// |                                                                          |
// | This file contains the BaseIPN class, it provides an interface to        |
// | deal with IPN transactions from paypal                                   |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | Copyright (C) 2009-2014 by the following authors:                        |
// |                                                                          |
// | Authors: Ben     -    ben AT geeklog DOT fr                              |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | Copyright (C) 2005-2006 by the following authors:                        |
// |                                                                          |
// | Authors: Vincent Furia     - vinny01 AT users DOT sourceforge DOT net    |
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

// this file can't be used on its own
if (!defined ('VERSION')) {
    die ('This file can not be used on its own.');
}

/**
 * This file contains the BaseIPN class, it provides an interface to deal with
 * IPN transactions from paypal
 *
 * @author Vincent Furia <vinny01 AT users DOT sourceforge DOT net>
 * @copyright Vincent Furia 2005 - 2006
 * @package paypal
 */
 
/* Paypal constants */
define('PAYPAL_FAILURE_UNKNOWN', 0);
define('PAYPAL_FAILURE_VERIFY', 1);
define('PAYPAL_FAILURE_COMPLETED', 2);
define('PAYPAL_FAILURE_UNIQUE', 3);
define('PAYPAL_FAILURE_EMAIL', 4);
define('PAYPAL_FAILURE_FUNDS', 5);

/**
 * This class provides an interface to deal with IPN transactions from paypal
 *
 * @package paypal
 */
class BaseIPN {

    /**
     * Verify the transaction by check Paypal's servers
     *
     * Validate transaction by posting data back to the paypal webserver.  The response from
     * paypal should include 'VERIFIED' on a line by itself.
     *
     * @param array $in Array containing POST variables of transaction
     * @return boolean true if result successfully validated, false otherwise
     */
    function Verify($in)
    {
        global $_PAY_CONF;

        if (DEBUG) {
            COM_errorLog('PAYPAL-IPN: verification start');
        }

        $rawPostData = file_get_contents('php://input');
        if (!is_string($rawPostData) || $rawPostData === '') {
            if (DEBUG) {
                COM_errorLog('PAYPAL-IPN: empty request body');
            }
            return false;
        }

        $sandbox = isset($_PAY_CONF['paypalURL'])
            && stripos((string) $_PAY_CONF['paypalURL'], 'sandbox') !== false;

        $paypalUrl = $sandbox
            ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://ipnpb.paypal.com/cgi-bin/webscr';

        $requestBody = 'cmd=_notify-validate&' . $rawPostData;

        $ch = curl_init($paypalUrl);
        if ($ch === false) {
            COM_errorLog('PAYPAL-IPN: cURL initialization failed');
            return false;
        }

        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Connection: Close',
            'User-Agent: Geeklog-PayPal/1.7.0 IPN-Verification',
            'Content-Type: application/x-www-form-urlencoded',
        ));

        $response = curl_exec($ch);

        if ($response === false) {
            if (DEBUG) {
                COM_errorLog(
                    'PAYPAL-IPN: verification request failed: ' . curl_error($ch)
                );
            }
            curl_close($ch);
            return false;
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            if (DEBUG) {
                COM_errorLog('PAYPAL-IPN: verification HTTP status ' . $httpCode);
            }
            return false;
        }

        $response = trim((string) $response);

        if (DEBUG) {
            COM_errorLog('PAYPAL-IPN: verification response ' . $response);
        }

        return $response === 'VERIFIED';
    }

    /**
     * Log an IPN
     *
     * Logs the incoming IPN (serialized) along with the time it arrived, the originating IP address
     * and whether or not it has been verified (caller specified).  Also inserts the txn_id
     * seperately for look-up purposes.
	 *
	 * If IPN is trucated, check your settings https://www.paypal.com/ie/cgi-bin/webscr?cmd=_profile-language-encoding
     *
     * @param array $in POST variables of IPN transaction
     * @param boolean $verified true if transaction has been verified, false otherwise
     */
    function Log($in, $verified = false) {
        
		global $_SERVER, $_TABLES, $_CONF;

        require_once $_CONF['path'] . 'plugins/paypal/classes/ForceUTF8.class.php';
		
		if(DEBUG) COM_errorLog("PAYPAL-IPN: Log start");
		
		// Change $verified into format for database
        if ($verified) {
            $verified = 1;
        } else {
            $verified = 0;
        }

        //Check if IPN already exists
        $txnId = isset($in['txn_id']) ? (string) $in['txn_id'] : '';
        $safeTxnId = DB_escapeString($txnId);
		$id = DB_getItem($_TABLES['paypal_ipnlog'], 'id', "txn_id='{$safeTxnId}'");
		
		if ( $id == '') {
		    // Alert admin of a possible charset issue
            if (!empty($in['charset'])
                && strtolower($in['charset']) != strtolower($_CONF['default_charset'])) {
				COM_errorLog('PAYPAL: IPN Charset possible issue. Please check your settings https://www.paypal.com/ie/cgi-bin/webscr?cmd=_profile-language-encoding. Paypal charset is set to ' 
				. $in['charset'] .  ' but your default charset is set to ' . $_CONF['default_charset']);
			}
		
            // Log to database using escaped values without mutating the IPN payload.
            $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? DB_escapeString($_SERVER['REMOTE_ADDR']) : '';
            $serialized = DB_escapeString(serialize($in));
			$sql = "INSERT INTO {$_TABLES['paypal_ipnlog']} SET ip_addr = '{$ipAddress}', "
				 . "time = NOW(), verified = " . (int) $verified . ", txn_id = '{$safeTxnId}', "
				 . "ipn_data = '{$serialized}'";
			
			DB_query($sql);
			
			if(DEBUG) COM_errorLog('PAYPAL-IPN: IPN recorded');
			return DB_insertId();
		} else {
		    if(DEBUG) COM_errorLog("PAYPAL-IPN: Log | IPN already exists in DB");
		    return $id;
		}
    }

    /**
     * Returns true if the the supplied email address is amoung allowed receiver addresses
     *
     * @param string $receiver_email Email to verify
     * @return boolean true if valid, false otherwise
     */
    function isValidEmail($receiver_email, $business) {
        
		global $_PAY_CONF;

        $expected = strtolower(trim((string) $_PAY_CONF['receiverEmailAddr']));
        $receiver = strtolower(trim((string) $receiver_email));
        $business = strtolower(trim((string) $business));

        if ($expected !== '' && ($receiver === $expected || $business === $expected)) {
		    if(DEBUG) COM_errorLog('PAYPAL-IPN: Email ok');
		    return true;
		} else {
		    if(DEBUG) COM_errorLog('PAYPAL-IPN: Email not ok: receiver= "' . $receiver_email . '" | business= "' . $business . '" Your email set in the config is "' . $_PAY_CONF['receiverEmailAddr'] . '"');
		    return false;
		}
    }

    /**
     * Checks to make sure that the transaction id is unique to prevent double counting.
     *
     * @param string $txn_id transaction id to verify
     * @return boolean true if unique, false otherwise
     */
    function isUniqueTxnId($txn_id) {
        global $_TABLES;

        // Count purchases with txn_id, if > 0
        $txn_id = (string) $txn_id;
        if ($txn_id === '') {
            return false;
        }
        $count = DB_count($_TABLES['paypal_purchases'], 'txn_id', $txn_id);
        if ($count > 0) {
		    if(DEBUG) COM_errorLog('PAYPAL-IPN: Txn is not unique');
            return false;
        } else {
		    if(DEBUG) COM_errorLog('PAYPAL-IPN: Txn is unique');
            return true;
        }
    }

    /**
     * Confirms that payment status is complete (not 'denied', 'failed', 'pending', etc.)
     *
     * @param string $payment_status payment status to verify
     * @return boolean true if complete, false otherwise
     */
    function isStatusCompleted($payment_status) {
        return ($payment_status == 'Completed');
    }

    /**
     * Checks if payment status is reversed or refunded (ie some sort of cancelation)
     *
     * @param string $payment_status payment status to check
     * @return boolean true if payment status is reversed or refunded, false otherwise
     */
    function isStatusReversed($payment_status) {
        return ($payment_status == 'Reversed' || $payment_status == 'Refunded');
    }

    /**
     * Checks to make sure provided funds are sufficient to cover the cost of the purchased item(s)
     *
     * @param array $ids ids of items to check
     * @param array $quantity number ordered (per item)
     * @param real $payment_gross total payment made in current transaction
     * @param string $currency Currency of funds in payment_gross
     * @return boolean true if funds are sufficient, false otherwise
     */
    function isSufficientFunds($ids, $quantity, $payment_gross, $currency, $shippingAmount = 0.0)
    {
        global $_PAY_CONF, $_TABLES;

        if (!is_array($ids) || empty($ids) || !is_array($quantity)) {
            return false;
        }

        if (!isset($_PAY_CONF['currency'])
            || strcasecmp((string) $_PAY_CONF['currency'], (string) $currency) !== 0) {
            if (DEBUG) COM_errorLog('PAYPAL-IPN: Currency mismatch');
            return false;
        }

        $expected = 0.0;
        $shippingItems = array();

        foreach ($ids as $index => $rawId) {
            $parsed = PAYPAL_parseItemIdentifier($rawId);
            $productId = $parsed['product_id'];
            $qty = isset($quantity[$index]) ? (int) $quantity[$index] : 0;

            if ($productId <= 0 || $qty <= 0) {
                return false;
            }

            $res = DB_query(
                "SELECT id, price, discount_a, discount_p, active "
                . "FROM {$_TABLES['paypal_products']} WHERE id = " . (int) $productId
            );
            $product = DB_fetchArray($res);

            if (!is_array($product) || empty($product['id']) || (int) $product['active'] !== 1) {
                return false;
            }

            $unitPrice = (float) PAYPAL_productPrice($product);

            if (!empty($parsed['attributes'])) {
                $attributeIds = array_map('intval', $parsed['attributes']);
                $idList = implode(',', $attributeIds);

                $attributeResult = DB_query(
                    "SELECT at.at_id, at.at_price "
                    . "FROM {$_TABLES['paypal_product_attribute']} pa "
                    . "INNER JOIN {$_TABLES['paypal_attributes']} at ON at.at_id = pa.pa_aid "
                    . "WHERE pa.pa_pid = " . (int) $productId
                    . " AND at.at_enabled = 1 AND at.at_id IN ({$idList})"
                );

                $validAttributes = 0;
                while ($attribute = DB_fetchArray($attributeResult)) {
                    $unitPrice += (float) $attribute['at_price'];
                    ++$validAttributes;
                }

                if ($validAttributes !== count($attributeIds)) {
                    if (DEBUG) COM_errorLog('PAYPAL-IPN: Invalid product attribute selection');
                    return false;
                }
            }

            $expected += $unitPrice * $qty;
            $shippingItems[] = array('id' => $rawId, 'qty' => $qty);
        }

        $shippingAmount = round((float) $shippingAmount, 2);
        $shippingContext = PAYPAL_getCartShippingContext($shippingItems);

        if (!PAYPAL_isAllowedShippingAmount(
            $shippingAmount,
            $shippingContext['weight'],
            $shippingContext['categories']
        )) {
            if (DEBUG) {
                COM_errorLog('PAYPAL-IPN: Invalid shipping amount ' . $shippingAmount);
            }
            return false;
        }

        $expected += $shippingAmount;

        // Allow only a one-cent rounding tolerance.
        $paid = round((float) $payment_gross, 2);
        $expected = round($expected, 2);

        if (($paid + 0.01) < $expected) {
            if (DEBUG) {
                COM_errorLog(
                    'PAYPAL-IPN: Insufficient funds. Expected ' . $expected
                    . ' ' . $_PAY_CONF['currency'] . ', received ' . $paid
                );
            }
            return false;
        }

        return true;
    }

    function handleReversal($in)
    {
        global $_TABLES;

        $sourceTxnId = '';
        if (!empty($in['parent_txn_id'])) {
            $sourceTxnId = (string) $in['parent_txn_id'];
        } elseif (!empty($in['txn_id'])) {
            $sourceTxnId = (string) $in['txn_id'];
        }

        if ($sourceTxnId === '') {
            return false;
        }

        $safeTxnId = DB_escapeString($sourceTxnId);
        $status = strtolower(isset($in['payment_status']) ? $in['payment_status'] : 'reversed');
        $safeStatus = DB_escapeString($status);

        DB_query(
            "UPDATE {$_TABLES['paypal_purchases']} "
            . "SET status = '{$safeStatus}' WHERE txn_id = '{$safeTxnId}'"
        );

        $res = DB_query(
            "SELECT id, user_id, add_to_group "
            . "FROM {$_TABLES['paypal_subscriptions']} "
            . "WHERE txn_id = '{$safeTxnId}'"
        );

        while ($subscription = DB_fetchArray($res)) {
            $userId = (int) $subscription['user_id'];
            $groupId = (int) $subscription['add_to_group'];

            if ($userId > 1 && $groupId > 1) {
                PAYPAL_removeFromGroup($groupId, $userId, 'PAYPAL - REFUND/REVERSAL');
            }
        }

        DB_query(
            "UPDATE {$_TABLES['paypal_subscriptions']} "
            . "SET status = '{$safeStatus}' WHERE txn_id = '{$safeTxnId}'"
        );

        if (DEBUG) {
            COM_errorLog(
                'PAYPAL-IPN: transaction ' . $sourceTxnId . ' marked ' . $status
            );
        }

        return true;
    }

    /**
     * Process an incoming IPN transaction
     *
     * Do the following:
     * <ol>
     * <li>verify IPN</li>
     * <li>Log IPN</li>
     * <li>Check that transaction is complete</li>
     * <li>Check that transaction is unique</li>
     * <li>Check for valid receiver email address</li>
     * <li>Process IPN</li>
     * </ol>
     *
     * @param array $in POST variables of transaction
     * @return boolean true if processing valid and completed, false otherwise
     */
    function Process($in)
    {
        global $_PAY_CONF;

        if (DEBUG) COM_errorLog('PAYPAL-IPN: IPN received');

        if (!is_array($in)) {
            return false;
        }

        $required = array('txn_id', 'payment_status', 'txn_type');
        foreach ($required as $field) {
            if (!isset($in[$field]) || trim((string) $in[$field]) === '') {
                if (DEBUG) COM_errorLog('PAYPAL-IPN: missing required field ' . $field);
                return false;
            }
        }

        if (empty($in['receiver_email']) && empty($in['business'])) {
            if (DEBUG) COM_errorLog('PAYPAL-IPN: missing receiver identity');
            return false;
        }

        $in += array(
            'receiver_email' => '',
            'business' => '',
            'mc_gross' => 0,
            'mc_currency' => '',
            'quantity' => 1,
            'item_number' => '',
            'item_name' => '',
            'custom' => 0,
            'mc_shipping' => 0,
            'mc_handling' => 0,
            'num_cart_items' => 0,
            'exchange_rate' => 0,
            'settle_currency' => '',
            'settle_amount' => '',
            'payment_gross' => isset($in['mc_gross']) ? $in['mc_gross'] : 0,
        );
		
        if (!$this->Verify($in)) {
            $logId = $this->Log($in, false);
            $this->handleFailure(PAYPAL_FAILURE_VERIFY, "PAYPAL-IPN: IPN($logId) Verification failed");
            return false;
        } else {
            $logId = $this->Log($in, true);
        }

        if ($this->isStatusReversed($in['payment_status'])) {
            return $this->handleReversal($in);
        }

        if (!$this->isStatusCompleted($in['payment_status'])) {
            return false;
        }

        if (!$this->isUniqueTxnId($in['txn_id'])) {
            $this->handleFailure(PAYPAL_FAILURE_UNIQUE, "($logId) Non-unique transaction id");
            return false;
        }

        if (!$this->isValidEmail($in['receiver_email'], $in['business'])) {
            $this->handleFailure(PAYPAL_FAILURE_EMAIL, "PAYPAL-IPN: IPN($logId) Invalid receiver email address");
            return false;
        }

        if (DEBUG) COM_errorLog('PAYPAL-IPN: Transaction type ' . $in['txn_type']);
		
		switch ($in['txn_type']) {
            // buy now, donate, smart logos
            case 'web_accept':  //usually buy now
            case 'send_money':  //usually donation/send money
                // Process Buy Now, ignore donations
                if (!empty($in['item_number'])) {
                    $ids = array($in['item_number']);
                    $quantity = array($in['quantity']);
					$name = array($in['item_name']);
                    if ($in['settle_amount'] !== ''
                        && (float) $in['exchange_rate'] > 0
                        && $in['settle_currency'] !== ''
                    ) {
                        $payment_gross = (float) $in['mc_gross'] * (float) $in['exchange_rate'];
                        $currency = $in['settle_currency'];
                    } else {
                        $payment_gross = $in['mc_gross'];
                        $currency      = $in['mc_currency'];
                    }
                    $shippingAmount = (float) $in['mc_shipping'] + (float) $in['mc_handling'];
                    if ($this->isSufficientFunds(
                        $ids,
                        $quantity,
                        $payment_gross,
                        $currency,
                        $shippingAmount
                    )) {
                        $this->handlePurchase($ids, $quantity, $in, $name);
                    } else {
                        $this->handleFailure(PAYPAL_FAILURE_FUNDS, "($logId) Insufficient funds for purchase");
                        return false;
                    }
                } else {
                    $this->handleDonation($in);
                }
                break;

            // shopping cart
            case 'cart':
                $ids = array();
                $quantity = array();
                $names = array();
                
                $cartItemCount = (int) $in['num_cart_items'];
                if ($cartItemCount > 0) {
                    for ($i = 1; $i <= $cartItemCount; $i++) {
                        $itemNumber = isset($in["item_number{$i}"]) ? $in["item_number{$i}"] : '';
                        $itemQuantity = isset($in["quantity{$i}"]) ? (int) $in["quantity{$i}"] : 0;
                        $itemName = isset($in["item_name{$i}"]) ? $in["item_name{$i}"] : '';

                        if ($itemNumber === '' || $itemQuantity <= 0) {
                            $this->handleFailure(
                                PAYPAL_FAILURE_UNKNOWN,
                                "($logId) Incomplete cart item {$i}"
                            );
                            return false;
                        }

                        if (DEBUG) {
                            COM_errorLog('PAYPAL-IPN: Cart case item: ' . $itemNumber);
                        }

                        $ids[] = $itemNumber;
                        $quantity[] = $itemQuantity;
                        $names[] = $itemName;
                    }
                } else {
                    $itemNumber = isset($in['item_number1']) ? $in['item_number1'] : '';
                    $itemQuantity = isset($in['quantity1']) ? (int) $in['quantity1'] : 0;
                    $itemName = isset($in['item_name1']) ? $in['item_name1'] : '';

                    if ($itemNumber === '' || $itemQuantity <= 0) {
                        $this->handleFailure(
                            PAYPAL_FAILURE_UNKNOWN,
                            "($logId) Cart IPN contains no valid items"
                        );
                        return false;
                    }

                    if (DEBUG) {
                        COM_errorLog('PAYPAL-IPN: Cart case item: ' . $itemNumber);
                    }

                    $ids[] = $itemNumber;
                    $quantity[] = $itemQuantity;
                    $names[] = $itemName;
                }
				
                if ($in['settle_amount'] !== ''
                    && (float) $in['exchange_rate'] > 0
                    && $in['settle_currency'] !== ''
                ) {
                    $payment_gross = (float) $in['mc_gross'] * (float) $in['exchange_rate'];
                    $currency = $in['settle_currency'];
                } else {
                    $payment_gross = $in['mc_gross'];
                    $currency      = $in['mc_currency'];
                }
                
                $shippingAmount = (float) $in['mc_shipping'] + (float) $in['mc_handling'];
				if ($this->isSufficientFunds(
                    $ids,
                    $quantity,
                    $payment_gross,
                    $currency,
                    $shippingAmount
                )) {
                    $this->handlePurchase($ids, $quantity, $in, $names);
                } else {
                    $this->handleFailure(PAYPAL_FAILURE_FUNDS, "($logId) Insufficient/incorrect funds for purchase");
                    return false;
                }
                break;

            // other, unknown, unsupported
            default:
                $this->handleFailure(PAYPAL_FAILURE_UNKNOWN, "($logId) Unknown transaction type");
                return false;
        }

        COM_errorLog("PAYPAL-IPN: purchases success",1);
        return true;
    }

    /**
     * Add a record of the purchase to the DB
     *
     * @param array $products Product Id(s) of Product(s) purchased
     * @param array $quantity Quantity of products purchases
     * @param array $paypal_data IPN POST variables
     * @todo implemente physical item vs. download, reflected in 'status'
     */
    function handlePurchase($products, $quantity, $paypal_data, $product_name) {
        global $_TABLES, $_CONF, $_PAY_CONF, $LANG_PAYPAL_EMAIL;

        // initialize file and names arrays
        $files = array();
        $names = array();
		$oldids = $products;
		$products = PAYPAL_realId($products);

        // for each item purchased, record purchase in purchase table
        for ($i = 0; $i < count($products); $i++) {
		    if(DEBUG) COM_errorLog('PAYPAL-IPN: Product id:' . $products[$i]);
            // grab relevant product data from product table to insert into purchase table.
            $productId = isset($products[$i]) ? (int) $products[$i] : 0;
            if ($productId <= 0) {
                continue;
            }

            $sql = "SELECT * FROM {$_TABLES['paypal_products']} WHERE id = {$productId}";
            $res = DB_query($sql);
            $A = DB_fetchArray($res);

            if (!is_array($A) || empty($A['id'])) {
                continue;
            }

            if ((int) $A['product_type'] === 1 && !empty($A['file'])) {
                $files[] = $_PAY_CONF['download_path'] . basename($A['file']);
            }
			
			//TODO + attribute name
			
            $itemQuantity = isset($quantity[$i]) ? (int) $quantity[$i] : 1;
            if ($itemQuantity < 1) {
                $itemQuantity = 1;
            }
            $quantity[$i] = $itemQuantity;

            $itemName = isset($product_name[$i]) && $product_name[$i] !== ''
                ? $product_name[$i]
                : $A['name'];
            $names[] = $itemName . ' x ' . $itemQuantity;

            // Do record anonymous users in purchase table
			//TODO record product name + product_id with attribute
            if ( is_numeric((int)$paypal_data['custom']) && (int)$paypal_data['custom'] > 0 ) {
                // Add the purchase to the paypal purchase table
                $userId = (int) $paypal_data['custom'];
                $safeTxnId = DB_escapeString(isset($paypal_data['txn_id']) ? $paypal_data['txn_id'] : '');
                $safeProductName = DB_escapeString($A['name']);

                $sql = "INSERT INTO {$_TABLES['paypal_purchases']} SET product_id = {$productId}, "
                     . "product_name = '{$safeProductName}', "
                     . "quantity = {$itemQuantity}, user_id = {$userId}, "
                     . "txn_id = '{$safeTxnId}', "
                     . "purchase_date = NOW(), status = 'complete'";

                /**
                 * @todo implemente physical item vs. download, reflected in 'status'
                 */
                // if physical item (aka, must be shipped) status = 'pending', otherwise 'complete'
                //if ( $physical == 1 ) {
                //    $sql .= ", status = 'pending'";
                //} else {
                //    $sql .= ", status = 'complete'";
                //}

                // add an expiration date if appropriate
                if (is_numeric($A['expiration']) && $A['type'] == 'product') {
                    $sql .= ", expiration = DATE_ADD(NOW(), INTERVAL {$A['expiration']} DAY)";
                }
				if(DEBUG) COM_errorLog('PAYPAL-IPN: ' . $sql);
                DB_query($sql);
				if(DEBUG) COM_errorLog('PAYPAL-IPN: Purchase recorded');
            }
			// stock movement
			$stock_id = PAYPAL_getStockId($oldids[$i]);
			$qty = $quantity[$i];
			PAYPAL_stockMovement ($stock_id, $oldids[$i], -$qty);
        }

        $paypal_data += array(
            'address_name' => '',
            'first_name' => '',
            'last_name' => '',
            'address_street' => '',
            'address_zip' => '',
            'address_city' => '',
            'address_country' => '',
            'payer_email' => '',
            'payment_gross' => '',
            'tax' => '',
            'mc_shipping' => '',
            'mc_handling' => '',
            'payment_date' => '',
        );
		
		// Update user details if empty user_id, user_name, user_contact, user_proid, user_street1, user_street2, user_postal, user_city, user_country, user_phone1, user_phone2, user_fax, status
		$fields = array('user_name' => $paypal_data['address_name'], 'user_contact' => $paypal_data['first_name'] . ' ' . $paypal_data['last_name'], 'user_street1' => $paypal_data['address_street'], 'user_postal' => $paypal_data['address_zip'], 'user_city' => $paypal_data['address_city'], 'user_country' => $paypal_data['address_country']);
		
		if ( is_numeric((int)$paypal_data['custom']) && (int)$paypal_data['custom'] != 1 ) PAYPAL_updateUserDetails ((int)$paypal_data['custom'], $fields, true);
		
        $subject = '';
        $text = '';

		// Send the purchaser a confirmation email (if set to do so in config)
        if ( ( is_numeric((int)$paypal_data['custom']) && (int)$paypal_data['custom'] != 1 &&
               $_PAY_CONF['purchase_email_user'] ) ||
             ( (!is_numeric($paypal_data['custom']) || (int)$paypal_data['custom'] == 1) &&
               $_PAY_CONF['purchase_email_anon'] )) {
            
			// setup templates
            $message = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates');
            $message->set_file(array('subject' => 'purchase_email_subject.txt',
                                     'message' => 'purchase_email_message.txt' ));
            // site variables
            $message->set_var('site_url', $_CONF['site_url']);
            $message->set_var('site_name', $_CONF['site_name']);

			//Email subject
			$message->set_var('purchase_receipt', $LANG_PAYPAL_EMAIL['purchase_receipt']);

            // list of product names
            $li_products = '';
			for ($i = 0; $i < count($products); $i++) {
			$li_products .= '<li>' . $names[$i];
			}
			$message->set_var('products', $li_products);
			
			//Email messages
			$message->set_var('thank_you', $LANG_PAYPAL_EMAIL['thank_you']);
			$message->set_var('thanks', $LANG_PAYPAL_EMAIL['thanks']);

            // paypal details
            $message->set_var('payment_gross', $paypal_data['payment_gross']);
            $message->set_var('tax', $paypal_data['tax']);
            $message->set_var('shipping', $paypal_data['mc_shipping']);
            $message->set_var('handling', $paypal_data['mc_handling']);
            $message->set_var('payment_date', $paypal_data['payment_date']);
            $message->set_var('payer_email', $paypal_data['payer_email']);
            $message->set_var('first_name', $paypal_data['first_name']);
            $message->set_var('last_name', $paypal_data['last_name']);
			
			$subject = trim($message->parse('output', 'subject'));

            // if specified to mail attachment, do so, otherwise skip attachment
            if ( (( is_numeric((int)$paypal_data['custom']) && (int)$paypal_data['custom'] != 1 &&
                    $_PAY_CONF['purchase_email_user_attach'] ) ||
                  ( (!is_numeric((int)$paypal_data['custom']) || (int)$paypal_data['custom'] == 1) &&
                    $_PAY_CONF['purchase_email_anon_attach'] )) &&
                  count($files) > 0  ) {
				$message->set_var('attached_files', $LANG_PAYPAL_EMAIL['attached_files']);
				$text = $message->parse('output', 'message');
                paypal_mailAttachment($paypal_data['payer_email'], $subject, $text, $files,
                                      $_PAY_CONF['receiverEmailAddr']);
            } else {
			    if (count($files) > 0  ) {
			        $message->set_var('attached_files', $LANG_PAYPAL_EMAIL['download_files']);
				} else {
				    $message->set_var('attached_files', '');
				}
				$text = $message->parse('output', 'message');
                COM_mail($paypal_data['payer_email'], $subject, $text,
                         $_PAY_CONF['receiverEmailAddr'], true);
            }
			if(DEBUG) COM_errorLog('PAYPAL-IPN: Email was sent');
        }
        // Send the merchant copy only when a receipt was built.
        if ($subject !== '' && $text !== '' && !empty($_PAY_CONF['receiverEmailAddr'])) {
            COM_mail(
                $_PAY_CONF['receiverEmailAddr'],
                $subject,
                $subject . ' >> ' . $text,
                $_PAY_CONF['receiverEmailAddr'],
                true
            );
        }

		//Subscription
		if ($A['type'] == 'subscription') {
		    //add subscription to db
		    PAYPAL_addsubscription ($A, $paypal_data);
			if(DEBUG) COM_errorLog('PAYPAL-IPN: Subscription recorded');
		    //add  user to group
		    if ($A['add_to_group'] > 1 && (int)$paypal_data['custom'] > 1) {
    			PAYPAL_addToGroup($A['add_to_group'], $paypal_data['custom']);
				if(DEBUG) COM_errorLog( 'PAYPAL-IPN: User with UID ' . $paypal_data['custom'] . ' added to group ID ' . $A['add_to_group'] );
			}
				
		}
    }

    /**
     * Not yet implemented
     *
     * @todo Implement handleSubscription
     */
    function handleSubscription($subscription, $paypal_data) {
		//Record subscription in DB
		
		return true;
    }

    /**
     * Not yet implemented
     *
     * @todo Implement handleDonation
     */
    function handleDonation() {
		$this->handleFailure(PAYPAL_FAILURE_UNKNOWN, "Donation not handled");
    }

    /**
     * Handle what to do in the event of a purchase/IPN failure
     *
     * This method does some basic failure handling.  For anything more
     * advanced it is recommend you override this method in IPN.class.php.
     *
     * @param int $type Type of failure that occurred
     * @param string $msg Failure message
     */
    function handleFailure($type = PAYPAL_FAILURE_UNKNOWN, $msg = '') {
        // Log the failure to geeklog's error log
        COM_errorLog('PAYPAL-IPN: ' . $msg,1);
    }
	
}


?>