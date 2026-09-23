<?php
// +--------------------------------------------------------------------------+
// | PayPal Plugin - geeklog CMS                                              |
// +--------------------------------------------------------------------------+
// | purchase_history.php                                                     |
// |                                                                          |
// | Allows paypal administrators to view site-wide purchase history          |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2014 by the following authors:                             |
// |                                                                          |
// | Authors: Ben        - cordiste AT free DOT fr                            |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | Based on the gl-paypal Plugin for Geeklog CMS                            |
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

/**
 * Allows paypal administrators to view site-wide purchase history
 *
 * @author Vincent Furia <vinny01 AT users DOT sourceforge DOT net>
 * @copyright Vincent Furia 2005 - 2006
 * @package paypal
 */

/**
 * Required geeklog
 */
require_once('../../../lib-common.php');

// Check for required permissions
paypal_access_check('paypal.admin');

// Incoming variable filter
$vars = array(
    'txn_id' => 'alpha',
    'msg' => 'text',
    'mode' => 'alpha',
    'period' => 'alpha',
);
paypal_filterVars($vars, $_REQUEST);

/**
 * Displays the list of ipn history from the log stored in the database
 *
 */
function PAYPAL_listTransactions()
{
    global $_CONF, $_TABLES, $LANG_PAYPAL_1, $_USER;

    require_once $_CONF['path_system'] . 'lib-admin.php';

    $retval = '';

    if (DB_count($_TABLES['paypal_ipnlog']) == 0) {
        return '<p>' . $LANG_PAYPAL_1['ipnlog_empty'] . '</p>';
    }

    // Todo make mc_gross sortable (need a new field in paypal_purchases table
    $header_arr = array(      // display 'text' and use table field 'field'
        array('text' => $LANG_PAYPAL_1['date_time'], 'field' => 'time', 'sort' => true),
		array('text' => $LANG_PAYPAL_1['user_id'], 'field' => 'user_id', 'sort' => true),
		array('text' => $LANG_PAYPAL_1['gross_payment'], 'field' => 'mc_gross', 'sort' => false),
		array('text' => $LANG_PAYPAL_1['txn_id'], 'field' => 'txnid', 'sort' => true),
		array('text' => $LANG_PAYPAL_1['payment_status'], 'field' => 'status', 'sort' => true)
    );
	
    $defsort_arr = array('field' => 'time', 'direction' => 'desc');

    $text_arr = array(
        'has_extras' => true,
        'form_url' => $_CONF['site_admin_url'] . '/plugins/paypal/purchase_history.php'
    );

	$sql = "SELECT i.txn_id AS txnid, i.ipn_data, i.time, u.username, u.uid, p.*
				FROM {$_TABLES['paypal_ipnlog']} AS i
			LEFT JOIN 
			    {$_TABLES['paypal_purchases']} AS p
			ON
			    i.txn_id = p.txn_id
			LEFT JOIN
				{$_TABLES['users']} AS u 
			ON
				p.user_id = u.uid
			
			WHERE 1 = 1 AND p.quantity <> ''
			
			";


    $query_arr = array(
        'sql'            => $sql,
		'default_filter' => 'GROUP BY i.txn_id',
		'query_fields' => array('time','user_id','i.txn_id','p.status', 'u.username','i.ipn_data'),
    );

	$_SESSION['gross_total'] = 0;
	
	//TODO $extra params to pass values to getListField
	
	$retval .= ADMIN_list('paypal', 'PAYPAL_getListField_paypal_transactions',
                          $header_arr, $text_arr, $query_arr, $defsort_arr, $filter = '', $extra = '',
            $options = '', $form_arr='', $showsearch = true);
			
	if ($_SESSION['gross_total'] > 0 ) $retval .= "<h2>Total page :  {$_SESSION['gross_total']}</h2>";

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
function PAYPAL_getListField_paypal_transactions($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PAY_CONF, $LANG_PAYPAL_1;

    $serialized = isset($A['ipn_data']) ? $A['ipn_data'] : '';
    $out = preg_replace_callback(
        '!s:(\\d+):"(.*?)";!s',
        function ($matches) {
            return 's:' . strlen($matches[2]) . ':"' . $matches[2] . '";';
        },
        $serialized
    );

    $ipn = @unserialize($out);
    if (!is_array($ipn)) {
        $ipn = array();
    }

    $addressName = isset($ipn['address_name']) ? $ipn['address_name'] : '';
    $firstName = isset($ipn['first_name']) ? $ipn['first_name'] : '';
    $lastName = isset($ipn['last_name']) ? $ipn['last_name'] : '';
    $gross = isset($ipn['mc_gross']) && is_numeric($ipn['mc_gross'])
        ? (float) $ipn['mc_gross']
        : 0.0;

    switch ($fieldname) {
        case 'id':
            $retval = isset($A['id']) ? $A['id'] : '';
            break;

        case 'user_id':
            $userId = isset($A['user_id']) ? (int) $A['user_id'] : 0;
            $username = isset($A['username']) ? $A['username'] : '';

            if ($userId >= 2) {
                $retval = '<a href="' . $_CONF['site_url'] . '/users.php?mode=profile&amp;uid='
                    . $userId . '">' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</a>';
            } else {
                $retval = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
            }

            if ($addressName !== '') {
                $retval .= ' | ' . htmlspecialchars($addressName, ENT_QUOTES, 'UTF-8');
            } elseif ($firstName !== '' || $lastName !== '') {
                $retval .= ' | ' . htmlspecialchars(trim($firstName . ' ' . $lastName), ENT_QUOTES, 'UTF-8');
            }
            break;

        case 'time':
            if (!empty($A['time'])) {
                $date = COM_getUserDateTimeFormat($A['time']);
                $retval = $date[0];
            } else {
                $retval = '';
            }
            break;

        case 'txnid':
            $txnId = (string) $fieldvalue;
            $retval = '<a href="' . $_CONF['site_admin_url']
                . '/plugins/paypal/ipnlog.php?view=ipnlog&amp;op=single&amp;txn_id='
                . rawurlencode($txnId) . '">' . htmlspecialchars($txnId, ENT_QUOTES, 'UTF-8') . '</a>';
            break;

        case 'status':
            $status = isset($A['status']) ? $A['status'] : '';
            $statusLabel = isset($LANG_PAYPAL_1[$status]) ? $LANG_PAYPAL_1[$status] : $status;

            if ($status === 'pending' && !empty($A['id'])) {
                $retval = '<a href="' . $_PAY_CONF['site_url']
                    . '/transaction.php?type=purchase&amp;id=' . (int) $A['id']
                    . '" title="' . $LANG_PAYPAL_1['see_transaction'] . '">'
                    . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</a>';
            } else {
                $retval = htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8');
            }
            break;

        case 'mc_gross':
            $formattedGross = number_format(
                $gross,
                $_CONF['decimal_count'],
                $_CONF['decimal_separator'],
                $_CONF['thousand_separator']
            );

            if ($gross != 0.0 && !empty($A['id'])) {
                $retval = '<div style="text-align:right;"><a href="' . $_PAY_CONF['site_url']
                    . '/transaction.php?type=purchase&amp;id=' . (int) $A['id']
                    . '" title="' . $LANG_PAYPAL_1['see_transaction'] . '">'
                    . $formattedGross . '</a></div>';
            } else {
                $retval = '<div style="text-align:right;">' . $formattedGross . '</div>';
            }

            $_SESSION['gross_total'] = (isset($_SESSION['gross_total'])
                ? (float) $_SESSION['gross_total']
                : 0.0) + $gross;
            break;

        default:
            $retval = stripslashes((string) $fieldvalue);
            break;
    }

    return $retval;
}

$paypalMode = isset($_POST['mode']) ? COM_applyFilter($_POST['mode']) : '';
if ($paypalMode == 'edit' && SEC_checkToken()) {
    // Update a manually pending transaction only.
    $paypalTxnId = isset($_POST['txn_id']) ? COM_applyFilter($_POST['txn_id']) : '';
    $paypalTxnSql = DB_escapeString($paypalTxnId);
	$sql = "SELECT * FROM {$_TABLES['paypal_ipnlog']} WHERE txn_id = '{$paypalTxnSql}'";
	$res = DB_query($sql);
	$A = DB_fetchArray($res);
    if (!is_array($A)) {
        $A = array();
    }

	// Allow all serialized data to be available to the template
	$ipn = array();
	if (!empty($A['ipn_data'])) {
        $out = preg_replace_callback(
            '!s:(\\d+):"(.*?)";!s',
            function ($matches) {
                return 's:' . strlen($matches[2]) . ':"' . $matches[2] . '";';
            },
            $A['ipn_data']
        );
		$ipn = @unserialize($out);
	}
    $paypalPendingTransaction = is_array($ipn)
        && isset($ipn['payment_status'])
        && $ipn['payment_status'] == 'pending';

    if ($paypalPendingTransaction) {
	if (isset($ipn['quantity1']) && $ipn['quantity1'] != '') {
	    //multi products
		$i = 1;
		for (; ; ) {
			if ($ipn['quantity'.$i] == '') {
				break;
			}
			$sql = "SELECT * FROM {$_TABLES['paypal_products']} WHERE id = '{$ipn['item_number'.$i]}'";
	        $res = DB_query($sql);
	        $product = DB_fetchArray($res);
			
		    //TODO if product is downloadable give access to product
			if ($product['product_type'] == 1) $files[] = $_PAY_CONF['download_path'] . $product['file'];
            $names[] = $ipn['quantity'.$i] . ' x ' . $ipn['item_name'.$i] . ' | ' .  ($ipn['mc_gross_'.$i]/$ipn['quantity'.$i]) . ' ' . $_PAY_CONF['currency'];

			$sql = "UPDATE {$_TABLES['paypal_purchases']} SET purchase_date = NOW()";
			// add an expiration date if appropriate
			if (is_numeric($product['expiration']) && $product['type'] == 'product') {
				$sql .= ", expiration = DATE_ADD(NOW(), INTERVAL {$product['expiration']} DAY)";
			}
			$sql .= " WHERE txn_id = '{$ipn['txn_id']}' AND product_id = '{$ipn['item_number'.$i]}'";
			if ($_PAY_CONF['debug']) COM_errorLog($sql);
			DB_query($sql);

			//Subscription
			if ($product['type'] == 'subscription') {
				//add subscription to db
				PAYPAL_addsubscription ($product, $ipn);
				if ($_PAY_CONF['debug']) COM_errorLog('Subscription recorded');
				//add  user to group
				($product['add_to_group'] > 1) ? PAYPAL_addToGroup($product['add_to_group'], $ipn['custom']) : '';
			}
			// stock movement
			$stock_id = PAYPAL_getStockId($ipn['item_number'.$i]);
			$qty = $ipn['quantity'.$i];
			PAYPAL_stockMovement ($stock_id, $ipn['item_number'.$i], -$qty);

			$i++;
		}
	} else {
	    //one product only
	    $sql = "SELECT * FROM {$_TABLES['paypal_products']} WHERE id = '{$ipn['item_number']}'";
		$res = DB_query($sql);
		$product = DB_fetchArray($res);
        if (!is_array($product)) {
            $product = array();
        }
		
		//product is downloadable give access to product
		if (isset($product['product_type']) && $product['product_type'] == 1 && !empty($product['file'])) $files[] = $_PAY_CONF['download_path'] . $product['file'];
		$names[] = $ipn['quantity'] . ' x ' . $ipn['item_name'] . ' | ' .  ($ipn['mc_gross']/$ipn['quantity']) . ' ' . $_PAY_CONF['currency'];

		$sql = "UPDATE {$_TABLES['paypal_purchases']} SET purchase_date = NOW()";
		// add an expiration date if appropriate
		if (is_numeric($product['expiration']) && $product['type'] == 'product') {
			$sql .= ", expiration = DATE_ADD(NOW(), INTERVAL {$product['expiration']} DAY)";
		}
		$sql .= " WHERE txn_id = '{$ipn['txn_id']}' AND product_id = '{$ipn['item_number']}'";
		if ($_PAY_CONF['debug']) COM_errorLog($sql);
		DB_query($sql);

		//Subscription
		if ($product['type'] == 'subscription') {
			//add subscription to db
			PAYPAL_addsubscription ($product, $ipn);
			if ($_PAY_CONF['debug']) COM_errorLog('Subscription recorded');
			//add  user to group
			($product['add_to_group'] > 1) ? PAYPAL_addToGroup($product['add_to_group'], $ipn['custom']) : '';
		}
		// stock movement
		$stock_id = PAYPAL_getStockId($ipn['item_number']);
		$qty = $ipn['quantity1'];
		PAYPAL_stockMovement ($stock_id, $ipn['item_number'], -$qty);
	}
	//Update IPN
	$ipn['payment_status'] = 'complete';
	$ipn['payment_date'] = date('H:i:s M d, Y T'); //13:49:40 Jul 06, 2011 PDT
	$sql = "UPDATE {$_TABLES['paypal_ipnlog']} SET ipn_data='" . serialize($ipn) . "' "
					 . "WHERE txn_id = '{$paypalTxnSql}'";
	DB_query($sql);
	
	//update purchase
	$sql = "UPDATE {$_TABLES['paypal_purchases']} SET status='complete' "
			. " WHERE txn_id = '{$paypalTxnSql}'";
	DB_query($sql);
	
    // Build the message once and honor user/anonymous delivery settings.
    $purchaseUserId = isset($ipn['custom']) ? (int) $ipn['custom'] : 1;
    $isAnonymousPurchase = ($purchaseUserId === 1);
    $sendPurchaserEmail = $isAnonymousPurchase
        ? !empty($_PAY_CONF['purchase_email_anon'])
        : !empty($_PAY_CONF['purchase_email_user']);

		// setup templates
		$message = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates/email');
		$message->set_file(array('subject' => 'purchase_by_check_complete_subject.thtml',
								 'message' => 'purchase_by_check_complete_message.thtml' ));
		// site variables
		$message->set_var('site_name', $_CONF['site_name']);
		
		//Email subject
		$message->set_var('purchase_receipt', $LANG_PAYPAL_EMAIL['purchase_receipt']);

		// list of product names
        $products = '';
		for ($i2 = 0; $i2 < ($i-1); $i2++) {
		    $products .= '<p>' . $names[$i2] . '</p>';
		}
		$products .= '<p>' . $LANG_PAYPAL_1['total_row_label'] . ' ' . $ipn['mc_gross'] . ' ' . $_PAY_CONF['currency'] . '</p>';
		$message->set_var('products', $products);
		
		//Email messages
		$message->set_var('thank_you', $LANG_PAYPAL_EMAIL['thank_you']);
		$message->set_var('thanks', $LANG_PAYPAL_EMAIL['thanks']);

		// paypal details
		$payment_date = COM_getUserDateTimeFormat($ipn['payment_date']);
		$message->set_var('payment_date', $payment_date[0]);
		$message->set_var('payer_email', $ipn['payer_email']);
		$message->set_var('address_name', $ipn['address_name']);
		
		$subject = trim($message->parse('output', 'subject'));

        // Attach purchased files only when both the purchaser email and the
        // matching attachment setting are enabled.
        $attachFiles = (
            (!$isAnonymousPurchase && !empty($_PAY_CONF['purchase_email_user_attach']))
            || ($isAnonymousPurchase && !empty($_PAY_CONF['purchase_email_anon_attach']))
        ) && count($files) > 0;

        $message->set_var(
            'attached_files',
            $attachFiles ? $LANG_PAYPAL_EMAIL['attached_files'] : $LANG_PAYPAL_EMAIL['download_files']
        );
        $text = $message->parse('output', 'message');

        if ($sendPurchaserEmail && !empty($ipn['payer_email'])) {
            if ($attachFiles) {
                paypal_mailAttachment(
                    $ipn['payer_email'],
                    $subject,
                    $text,
                    $files,
                    $_PAY_CONF['receiverEmailAddr']
                );
            } else {
                COM_mail(
                    $ipn['payer_email'],
                    $subject,
                    $text,
                    $_PAY_CONF['receiverEmailAddr'],
                    true
                );
            }

            if ($_PAY_CONF['debug']) {
                COM_errorLog('Email was sent to ' . $ipn['payer_email']);
            }
        }

	//Send email to receiver
	COM_mail($_PAY_CONF['receiverEmailAddr'], $subject, $subject . ' >> ' . $text, '', true);
	$_REQUEST['msg'] = $LANG_PAYPAL_1['order_validated'];
    }
}

//Main

$display = paypal_admin_menu();

$display .= COM_startBlock($LANG_PAYPAL_1['sales_history']);

if (!empty($_REQUEST['msg'])) $display .= COM_showMessageText( stripslashes($_REQUEST['msg']), $LANG_PAYPAL_1['message']);
			
$display .= PAYPAL_plot(isset($_REQUEST['period']) ? $_REQUEST['period'] : '12m');
$display .= PAYPAL_listTransactions();
$display .= COM_endBlock();

COM_output(PAYPAL_createHTMLDocument($display, $LANG_PAYPAL_1['sales_history']));
?>