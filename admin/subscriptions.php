<?php

require_once '../../../lib-common.php';

paypal_access_check('paypal.admin');

$vars = array(
    'msg' => 'text',
    'mode' => 'alpha',
    'id' => 'number',
    'user_id' => 'number',
    'txn_id' => 'text',
    'product_id' => 'number',
    'price' => 'text',
    'status' => 'alpha',
    'purchase_date' => 'text',
    'expiration' => 'text',
    'add_to_group' => 'number',
    'notification' => 'number',
);
paypal_filterVars($vars, $_REQUEST);

function PAYPAL_adminNormalizeDate($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d 00:00:00', $timestamp);
}

function PAYPAL_getSubscriptionForm($subscription = array())
{
    global $_CONF, $_PAY_CONF, $LANG_PAYPAL_1, $_TABLES;

    $defaults = array(
        'id' => 0,
        'product_id' => 0,
        'user_id' => 0,
        'txn_id' => '',
        'purchase_date' => date('Y-m-d'),
        'expiration' => date('Y-m-d'),
        'price' => '0.00',
        'status' => 'complete',
        'add_to_group' => 0,
        'notification' => 0,
    );
    $subscription = array_merge($defaults, is_array($subscription) ? $subscription : array());

    $title = empty($subscription['id'])
        ? $LANG_PAYPAL_1['create_new_subscription']
        : $LANG_PAYPAL_1['edit_subscription'] . ' ' . (int) $subscription['id'];

    $template = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates');
    $template->set_file(array('subscription' => 'subscription_form.thtml'));

    $template->set_var(array(
        'site_url' => $_CONF['site_url'],
        'gltoken_name' => CSRF_TOKEN,
        'gltoken' => SEC_createToken(),
        'id' => (int) $subscription['id'],
        'informations' => $LANG_PAYPAL_1['subscription_informations'],
        'product_id_label' => $LANG_PAYPAL_1['product_id'],
        'user_id_label' => $LANG_PAYPAL_1['user_id'],
        'txn_id_label' => $LANG_PAYPAL_1['txn_id'],
        'txn_id' => htmlspecialchars($subscription['txn_id'], ENT_QUOTES, 'UTF-8'),
        'purchase_date_label' => $LANG_PAYPAL_1['purchase_date'],
        'purchase_date' => htmlspecialchars(substr($subscription['purchase_date'], 0, 10), ENT_QUOTES, 'UTF-8'),
        'expiration_label' => $LANG_PAYPAL_1['expiration'],
        'expiration' => htmlspecialchars(substr($subscription['expiration'], 0, 10), ENT_QUOTES, 'UTF-8'),
        'price_label' => $LANG_PAYPAL_1['price_label'],
        'price' => htmlspecialchars((string) $subscription['price'], ENT_QUOTES, 'UTF-8'),
        'status_label' => $LANG_PAYPAL_1['status'],
        'status' => htmlspecialchars($subscription['status'], ENT_QUOTES, 'UTF-8'),
        'add_to_group_label' => $LANG_PAYPAL_1['add_to_group_label'],
        'notification_label' => $LANG_PAYPAL_1['notification'],
        'currency' => $_PAY_CONF['currency'],
        'save_button' => $LANG_PAYPAL_1['save_button'],
        'delete_button' => $LANG_PAYPAL_1['delete_button'],
        'required_field' => $LANG_PAYPAL_1['required_field'],
        'delete_button_html' => !empty($subscription['id'])
            ? '<button type="submit" name="mode" value="delete" class="paypal-danger">'
                . htmlspecialchars($LANG_PAYPAL_1['delete_button'], ENT_QUOTES, 'UTF-8')
                . '</button>'
            : '',
    ));

    $productOptions = '';
    $result = DB_query(
        "SELECT id, name, price FROM {$_TABLES['paypal_products']} "
        . "WHERE type = 'subscription' ORDER BY name"
    );
    while ($row = DB_fetchArray($result)) {
        $selected = ((int) $subscription['product_id'] === (int) $row['id']) ? ' selected' : '';
        $productOptions .= '<option value="' . (int) $row['id'] . '"' . $selected . '>'
            . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8')
            . ' — ' . htmlspecialchars($row['price'], ENT_QUOTES, 'UTF-8')
            . ' ' . htmlspecialchars($_PAY_CONF['currency'], ENT_QUOTES, 'UTF-8')
            . '</option>';
    }

    if ($productOptions === '') {
        return COM_showMessageText(
            $LANG_PAYPAL_1['create_membership_first'],
            $LANG_PAYPAL_1['message']
        );
    }
    $template->set_var('product_id_select', '<select name="product_id" required>' . $productOptions . '</select>');

    $userOptions = '';
    $result = DB_query(
        "SELECT uid FROM {$_TABLES['users']} WHERE uid > 1 ORDER BY uid"
    );
    while ($row = DB_fetchArray($result)) {
        $selected = ((int) $subscription['user_id'] === (int) $row['uid']) ? ' selected' : '';
        $userOptions .= '<option value="' . (int) $row['uid'] . '"' . $selected . '>'
            . (int) $row['uid'] . '. '
            . htmlspecialchars(COM_getDisplayName($row['uid']), ENT_QUOTES, 'UTF-8')
            . '</option>';
    }
    $template->set_var('user_select', '<select name="user_id" required>' . $userOptions . '</select>');

    $template->set_var(
        'add_to_group_options',
        COM_optionList($_TABLES['groups'], 'grp_id,grp_name', (int) $subscription['add_to_group'], 1)
    );

    $notificationOptions = '';
    for ($i = 0; $i <= 3; ++$i) {
        $selected = ((int) $subscription['notification'] === $i) ? ' selected' : '';
        $notificationOptions .= '<option value="' . $i . '"' . $selected . '>' . $i . '</option>';
    }
    $template->set_var('notification_select', '<select name="notification">' . $notificationOptions . '</select>');

    return COM_startBlock($title)
        . $template->parse('', 'subscription')
        . COM_endBlock();
}

$display = paypal_admin_menu();

if (!empty($_REQUEST['msg'])) {
    $display .= COM_showMessageText(
        stripslashes($_REQUEST['msg']),
        $LANG_PAYPAL_1['message']
    );
}

$mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : '';

switch ($mode) {
    case 'new':
        $display .= PAYPAL_getSubscriptionForm();
        break;

    case 'edit':
        $id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
        if ($id <= 0) {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins/paypal/subscriptions.php');
            exit;
        }

        $res = DB_query(
            "SELECT * FROM {$_TABLES['paypal_subscriptions']} WHERE id = {$id}"
        );
        $subscription = DB_fetchArray($res);

        if (!is_array($subscription)) {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins/paypal/subscriptions.php');
            exit;
        }

        $display .= PAYPAL_getSubscriptionForm($subscription);
        break;

    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !SEC_checkToken()) {
            $display .= COM_showMessageText(
                $LANG_PAYPAL_1['access_denied'],
                $LANG_PAYPAL_1['error']
            );
            break;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $groupId = isset($_POST['add_to_group']) ? (int) $_POST['add_to_group'] : 0;
        $notification = isset($_POST['notification']) ? (int) $_POST['notification'] : 0;
        $purchaseDate = PAYPAL_adminNormalizeDate(isset($_POST['purchase_date']) ? $_POST['purchase_date'] : '');
        $expiration = PAYPAL_adminNormalizeDate(isset($_POST['expiration']) ? $_POST['expiration'] : '');
        $price = isset($_POST['price']) ? preg_replace('/[^0-9.]/', '', $_POST['price']) : '';
        $status = isset($_POST['status']) ? COM_applyFilter($_POST['status']) : 'complete';

        if ($status === '') {
            $status = 'complete';
        }

        $productValid = $productId > 0
            && DB_getItem($_TABLES['paypal_products'], 'id', "id = {$productId} AND type = 'subscription'");
        $userValid = $userId > 1
            && DB_getItem($_TABLES['users'], 'uid', "uid = {$userId}");
        $groupValid = $groupId > 0
            && DB_getItem($_TABLES['groups'], 'grp_id', "grp_id = {$groupId}");

        if (!$productValid || !$userValid || !$groupValid || $purchaseDate === '' || $expiration === '') {
            $display .= COM_showMessageText(
                $LANG_PAYPAL_1['missing_field'],
                $LANG_PAYPAL_1['error']
            );
            $display .= PAYPAL_getSubscriptionForm($_POST);
            break;
        }

        if ($price === '' || !is_numeric($price)) {
            $price = '0.00';
        }
        $price = number_format((float) $price, 2, '.', '');

        $safePurchaseDate = DB_escapeString($purchaseDate);
        $safeExpiration = DB_escapeString($expiration);
        $safePrice = DB_escapeString($price);
        $safeStatus = DB_escapeString($status);

        if ($id > 0) {
            $txnId = DB_getItem($_TABLES['paypal_subscriptions'], 'txn_id', "id = {$id}");
            $safeTxnId = DB_escapeString((string) $txnId);

            DB_query(
                "UPDATE {$_TABLES['paypal_subscriptions']} SET "
                . "product_id = {$productId}, "
                . "user_id = {$userId}, "
                . "txn_id = '{$safeTxnId}', "
                . "purchase_date = '{$safePurchaseDate}', "
                . "expiration = '{$safeExpiration}', "
                . "price = '{$safePrice}', "
                . "status = '{$safeStatus}', "
                . "add_to_group = {$groupId}, "
                . "notification = {$notification} "
                . "WHERE id = {$id}"
            );
        } else {
            $txnId = 'manual-' . COM_makesid();
            $safeTxnId = DB_escapeString($txnId);

            DB_query(
                "INSERT INTO {$_TABLES['paypal_subscriptions']} SET "
                . "product_id = {$productId}, "
                . "user_id = {$userId}, "
                . "txn_id = '{$safeTxnId}', "
                . "purchase_date = '{$safePurchaseDate}', "
                . "expiration = '{$safeExpiration}', "
                . "price = '{$safePrice}', "
                . "status = '{$safeStatus}', "
                . "add_to_group = {$groupId}, "
                . "notification = {$notification}"
            );
        }

        if (DB_error()) {
            $msg = $LANG_PAYPAL_1['save_fail'];
        } else {
            if ($notification !== 3) {
                PAYPAL_addToGroup($groupId, $userId);
            }
            $msg = $LANG_PAYPAL_1['subscription_label'] . ' >> ' . $LANG_PAYPAL_1['save_success'];
        }

        echo COM_refresh(
            $_CONF['site_admin_url']
            . '/plugins/paypal/subscriptions.php?msg='
            . urlencode($msg)
        );
        exit;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !SEC_checkToken()) {
            $display .= COM_showMessageText(
                $LANG_PAYPAL_1['access_denied'],
                $LANG_PAYPAL_1['error']
            );
            break;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins/paypal/subscriptions.php');
            exit;
        }

        $res = DB_query(
            "SELECT user_id, add_to_group FROM {$_TABLES['paypal_subscriptions']} WHERE id = {$id}"
        );
        $subscription = DB_fetchArray($res);

        DB_delete($_TABLES['paypal_subscriptions'], 'id', $id);

        if (DB_affectedRows('') == 1) {
            if (is_array($subscription)) {
                PAYPAL_removeFromGroup(
                    (int) $subscription['add_to_group'],
                    (int) $subscription['user_id']
                );
            }
            $msg = $LANG_PAYPAL_1['deletion_succes'];
        } else {
            $msg = $LANG_PAYPAL_1['deletion_fail'];
        }

        echo COM_refresh(
            $_CONF['site_admin_url']
            . '/plugins/paypal/subscriptions.php?msg='
            . urlencode($msg)
        );
        exit;

    default:
        $display .= COM_startBlock($LANG_PAYPAL_1['memberships_list']);
        $display .= '<p><a href="'
            . $_CONF['site_admin_url']
            . '/plugins/paypal/subscriptions.php?mode=new">'
            . $LANG_PAYPAL_1['create_subscription']
            . '</a> · <a href="'
            . $_PAY_CONF['site_url']
            . '/memberships_history.php">'
            . $LANG_PAYPAL_1['see_members_list']
            . '</a></p>';
        $display .= PAYPAL_listSubscriptions('all');
        $display .= COM_endBlock();
        break;
}

plugin_runScheduledTask_paypal();

COM_output(
    PAYPAL_createHTMLDocument(
        $display,
        $LANG_PAYPAL_1['memberships_list']
    )
);
