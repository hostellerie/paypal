<?php

/* PayPal Plugin 1.7.0 - structured interoperability helpers. */

if (isset($_SERVER['PHP_SELF']) && strpos(strtolower($_SERVER['PHP_SELF']), 'interoperability.php') !== false) {
    die('This file can not be used on its own.');
}

function PAYPAL_interopRequestedFields($what)
{
    if (is_array($what)) {
        return array_values(array_map('trim', $what));
    }
    $what = trim((string) $what);
    if ($what === '' || $what === '*') {
        return array();
    }
    return array_map('trim', explode(',', $what));
}

function PAYPAL_interopSelectFields($item, $what)
{
    $fields = PAYPAL_interopRequestedFields($what);
    if (empty($fields)) {
        return $item;
    }
    $result = array();
    foreach ($fields as $field) {
        if (array_key_exists($field, $item)) {
            $result[$field] = $item[$field];
        }
    }
    return $result;
}

function PAYPAL_interopSelectSingle($item, $what)
{
    $fields = PAYPAL_interopRequestedFields($what);
    if (empty($fields)) {
        return $item;
    }
    $values = array();
    foreach ($fields as $field) {
        $values[] = array_key_exists($field, $item) ? $item[$field] : '';
    }
    return count($values) === 1 ? $values[0] : $values;
}

function PAYPAL_interopProductUrl($id)
{
    global $_PAY_CONF;
    return rtrim($_PAY_CONF['site_url'], '/') . '/product_detail.php?product=' . (int) $id;
}

function PAYPAL_interopProduct($id, $uid = 0)
{
    global $_TABLES;

    $id = (int) $id;
    $uid = (int) $uid;
    if ($id <= 0) {
        return array();
    }

    $sql = "SELECT p.id,p.item_id,p.name,p.short_description,p.description,p.created,"
        . "p.price,p.type,p.hits,p.owner_id,p.cat_id,c.cat_name AS category "
        . "FROM {$_TABLES['paypal_products']} AS p "
        . "LEFT JOIN {$_TABLES['paypal_categories']} AS c ON c.cat_id=p.cat_id "
        . "WHERE p.id=" . $id . " AND p.active=1 AND p.hidden=0"
        . COM_getPermSQL('AND', $uid, 2, 'p') . " LIMIT 1";

    $row = DB_fetchArray(DB_query($sql));
    if (!is_array($row) || empty($row['id'])) {
        return array();
    }

    $created = 0;
    if (!empty($row['created'])) {
        $created = strtotime((string) $row['created']);
        if ($created === false) {
            $created = 0;
        }
    }

    $description = trim(stripslashes((string) $row['description']));
    $excerpt = trim(stripslashes((string) $row['short_description']));
    if ($excerpt === '') {
        $excerpt = trim(preg_replace('/\\s+/', ' ', strip_tags($description)));
        if (strlen($excerpt) > 240) {
            $excerpt = rtrim(substr($excerpt, 0, 237)) . '...';
        }
    }

    return array(
        'id' => (string) $row['id'],
        'type' => 'paypal',
        'subtype' => isset($row['type']) ? (string) $row['type'] : 'product',
        'title' => stripslashes((string) $row['name']),
        'url' => PAYPAL_interopProductUrl($row['id']),
        'description' => $description,
        'excerpt' => $excerpt,
        'date-created' => $created,
        'date-modified' => $created,
        'uid' => isset($row['owner_id']) ? (int) $row['owner_id'] : 0,
        'author' => isset($row['owner_id']) ? COM_getDisplayName((int) $row['owner_id']) : '',
        'category' => isset($row['category']) ? stripslashes((string) $row['category']) : '',
        'category-id' => isset($row['cat_id']) ? (int) $row['cat_id'] : 0,
        'hits' => isset($row['hits']) ? (int) $row['hits'] : 0,
        'price' => isset($row['price']) ? (string) $row['price'] : '',
        'item-id' => isset($row['item_id']) ? (string) $row['item_id'] : ''
    );
}

function PAYPAL_interopProducts($what, $uid, $options)
{
    global $_TABLES;

    $uid = (int) $uid;
    $options = is_array($options) ? $options : array();
    $limit = isset($options['limit']) ? (int) $options['limit'] : 20;
    if ($limit < 1) {
        $limit = 20;
    } elseif ($limit > 200) {
        $limit = 200;
    }

    $since = isset($options['since']) ? (int) $options['since'] : 0;
    $order = isset($options['order']) ? strtolower(trim((string) $options['order'])) : 'created-desc';

    $sql = "SELECT p.id FROM {$_TABLES['paypal_products']} AS p "
        . "WHERE p.active=1 AND p.hidden=0"
        . COM_getPermSQL('AND', $uid, 2, 'p');

    if ($since > 0) {
        $sql .= " AND UNIX_TIMESTAMP(p.created)>=" . $since;
    }

    if ($order === 'hits-desc') {
        $sql .= " ORDER BY p.hits DESC,p.id DESC";
    } elseif ($order === 'created-asc') {
        $sql .= " ORDER BY p.created ASC,p.id ASC";
    } else {
        $sql .= " ORDER BY p.created DESC,p.id DESC";
    }
    $sql .= " LIMIT " . $limit;

    $result = DB_query($sql);
    $items = array();
    while ($row = DB_fetchArray($result)) {
        $item = PAYPAL_interopProduct($row['id'], $uid);
        if (!empty($item)) {
            $items[] = PAYPAL_interopSelectFields($item, $what);
        }
    }
    return $items;
}

function plugin_getiteminfo_paypal($id, $what = '', $uid = 0, $options = array())
{
    if ((string) $id === '*') {
        return PAYPAL_interopProducts($what, $uid, $options);
    }

    $item = PAYPAL_interopProduct($id, $uid);
    if (empty($item)) {
        return false;
    }

    return PAYPAL_interopSelectSingle($item, $what);
}

function plugin_idtourl_paypal($sub_type, $item_id)
{
    $item = PAYPAL_interopProduct($item_id, 0);
    return !empty($item['url']) ? $item['url'] : '';
}

function plugin_getcapabilities_paypal()
{
    return array(
        'schema' => 1,
        'roles' => array('content', 'service'),
        'capabilities' => array(
            'content.read',
            'content.collection',
            'content.search',
            'content.popular',
            'content.url.resolve',
            'content.lifecycle',
            'dashboard.summary'
        )
    );
}

function PAYPAL_interopCapabilities()
{
    return array(
        'content_info' => true,
        'collections' => true,
        'content_popular' => true,
        'item_saved' => true,
        'item_deleted' => true,
        'id_to_url' => true,
        'dashboard_summary' => true
    );
}
