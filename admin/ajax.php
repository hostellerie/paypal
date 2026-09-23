<?php

require_once '../../../lib-common.php';

if (!SEC_hasRights('paypal.admin')) {
    http_response_code(403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !SEC_checkToken()) {
    http_response_code(403);
    echo 'Invalid request token';
    exit;
}

$vars = array(
    'action' => 'alpha',
    'id' => 'number',
    'pid' => 'number',
    'ipn' => 'text',
    'content' => 'text',
);
paypal_filterVars($vars, $_POST);

$action = isset($_POST['action']) ? $_POST['action'] : '';
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$pid = isset($_POST['pid']) ? (int) $_POST['pid'] : 0;

function PAYPAL_ajaxAttributeLists($pid)
{
    return '<div id="attributes_actions">'
        . '<div id="attributes_list">' . PAYPAL_displayAttributes($pid) . '</div>'
        . '<div id="attributes_available">' . PAYPAL_displayAttributesToAdd($pid) . '</div>'
        . '</div>';
}

switch ($action) {
    case 'delete':
        if ($id <= 0 || $pid <= 0) {
            http_response_code(400);
            exit;
        }

        DB_query(
            "DELETE FROM {$_TABLES['paypal_product_attribute']} "
            . "WHERE pa_id = {$id} AND pa_pid = {$pid}"
        );

        echo PAYPAL_ajaxAttributeLists($pid);
        break;

    case 'add':
        if ($id <= 0 || $pid <= 0) {
            http_response_code(400);
            exit;
        }

        $exists = DB_getItem(
            $_TABLES['paypal_product_attribute'],
            'pa_id',
            "pa_pid = {$pid} AND pa_aid = {$id}"
        );

        if (empty($exists)) {
            DB_query(
                "INSERT INTO {$_TABLES['paypal_product_attribute']} "
                . "SET pa_pid = {$pid}, pa_aid = {$id}"
            );
        }

        echo PAYPAL_ajaxAttributeLists($pid);
        break;

    case 'paypal_handle_purchase':
        $txnId = isset($_POST['ipn']) ? trim($_POST['ipn']) : '';
        if ($txnId === '') {
            http_response_code(400);
            exit;
        }

        $safeTxnId = DB_escapeString($txnId);
        $res = DB_query(
            "SELECT * FROM {$_TABLES['paypal_ipnlog']} "
            . "WHERE txn_id = '{$safeTxnId}'"
        );
        $A = DB_fetchArray($res);

        if (!is_array($A) || empty($A['ipn_data'])) {
            http_response_code(404);
            exit;
        }

        $ipn = @unserialize($A['ipn_data']);
        if (!is_array($ipn)) {
            http_response_code(400);
            exit;
        }

        // Manual recovery is allowed only for an IPN that has not already
        // been marked verified/processed.
        if ((int) $A['verified'] !== 1) {
            $products = array();
            $quantity = array();
            $names = array();
            $prices = array();

            for ($i = 1; ; ++$i) {
                $numberKey = 'item_number' . $i;
                if (empty($ipn[$numberKey])) {
                    break;
                }

                $products[$i] = $ipn[$numberKey];
                $quantity[$i] = isset($ipn['quantity' . $i]) ? $ipn['quantity' . $i] : 1;
                $names[$i] = isset($ipn['item_name' . $i]) ? $ipn['item_name' . $i] : '';
                $prices[$i] = isset($ipn['mc_gross_' . $i]) ? $ipn['mc_gross_' . $i] : 0;
            }

            if (!empty($products)) {
                $timestamp = !empty($ipn['payment_date'])
                    ? strtotime($ipn['payment_date'])
                    : time();
                if ($timestamp === false) {
                    $timestamp = time();
                }

                PAYPAL_handlePurchase(
                    $products,
                    $quantity,
                    $ipn,
                    $names,
                    $prices,
                    0,
                    'complete',
                    isset($ipn['custom']) ? $ipn['custom'] : 0,
                    isset($ipn['txn_id']) ? $ipn['txn_id'] : $txnId,
                    date('Y-m-d H:i:s', $timestamp)
                );

                DB_query(
                    "UPDATE {$_TABLES['paypal_ipnlog']} "
                    . "SET verified = 1 WHERE txn_id = '{$safeTxnId}'"
                );
            }
        }

        echo $LANG_PAYPAL_1['done'];
        break;

    case 'paypal_new_ipn':
        $txnId = isset($_POST['ipn']) ? trim($_POST['ipn']) : '';
        $rawContent = isset($_POST['content']) ? $_POST['content'] : '';

        if ($txnId === '' || $rawContent === '') {
            http_response_code(400);
            exit;
        }

        $newIpn = array();
        parse_str($rawContent, $newIpn);

        if (!is_array($newIpn)) {
            http_response_code(400);
            exit;
        }

        $safeTxnId = DB_escapeString($txnId);
        $safePayload = DB_escapeString(serialize($newIpn));

        DB_query(
            "UPDATE {$_TABLES['paypal_ipnlog']} "
            . "SET ipn_data = '{$safePayload}', verified = 0 "
            . "WHERE txn_id = '{$safeTxnId}'"
        );

        echo '<p>' . $LANG_PAYPAL_1['ipn_replaced'] . '</p>';
        break;

    default:
        http_response_code(400);
        break;
}
