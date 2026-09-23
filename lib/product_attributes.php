<?php

function PAYPAL_displayAttributes($productId)
{
    global $_CONF, $_TABLES, $LANG_PAYPAL_ADMIN, $LANG_ADMIN;

    require_once $_CONF['path_system'] . 'lib-admin.php';

    $productId = (int) $productId;

    $header = array(
        array('text' => $LANG_ADMIN['edit'], 'field' => 'edit', 'sort' => false),
        array('text' => $LANG_PAYPAL_ADMIN['attribute_label'], 'field' => 'pa_aid', 'sort' => false),
        array('text' => $LANG_PAYPAL_ADMIN['code_label'], 'field' => 'at_code', 'sort' => false),
    );

    $query = array(
        'sql' => "SELECT pa.*, at.at_name, at.at_code, at.at_order
            FROM {$_TABLES['paypal_product_attribute']} AS pa
            LEFT JOIN {$_TABLES['paypal_attributes']} AS at
                ON pa.pa_aid = at.at_id
            WHERE pa.pa_pid = {$productId}",
        'query_fields' => array('pa_aid', 'at_name', 'at_code'),
    );

    return ADMIN_list(
        'paypal_product_attributes',
        'PAYPAL_getListField_paypal_displayAttributes',
        $header,
        array('has_extras' => true),
        $query,
        array('field' => 'at_order', 'direction' => 'asc')
    );
}

function PAYPAL_getListField_paypal_displayAttributes($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $LANG_PAYPAL_ADMIN;

    switch ($fieldname) {
        case 'edit':
            return COM_createLink(
                $icon_arr['enabled'],
                '#',
                array(
                    'class' => 'delete',
                    'id' => (int) $A['pa_id'],
                    'pid' => (int) $A['pa_pid'],
                    'title' => $LANG_PAYPAL_ADMIN['remove_attribute'],
                )
            );

        case 'pa_aid':
            return isset($A['at_name']) ? $A['at_name'] : '';

        case 'at_code':
            return isset($A['at_code']) ? $A['at_code'] : '';

        default:
            return stripslashes((string) $fieldvalue);
    }
}

function PAYPAL_displayAttributesToAdd($productId)
{
    global $_CONF, $_TABLES, $LANG_PAYPAL_ADMIN, $LANG_ADMIN;

    require_once $_CONF['path_system'] . 'lib-admin.php';

    $productId = (int) $productId;

    $header = array(
        array('text' => $LANG_ADMIN['edit'], 'field' => 'edit', 'sort' => false),
        array('text' => $LANG_PAYPAL_ADMIN['attribute_label'], 'field' => 'at_name', 'sort' => false),
        array('text' => $LANG_PAYPAL_ADMIN['code_label'], 'field' => 'at_code', 'sort' => false),
    );

    $query = array(
        'sql' => "SELECT at.*, {$productId} AS product_id
            FROM {$_TABLES['paypal_attributes']} AS at
            LEFT JOIN {$_TABLES['paypal_product_attribute']} AS pa
                ON pa.pa_aid = at.at_id
                AND pa.pa_pid = {$productId}
            WHERE pa.pa_id IS NULL",
        'query_fields' => array('at_name', 'at_code'),
    );

    return ADMIN_list(
        'paypal_available_attributes',
        'PAYPAL_getListField_paypal_displayAttributesToAdd',
        $header,
        array('has_extras' => true),
        $query,
        array('field' => 'at_order', 'direction' => 'asc')
    );
}

function PAYPAL_getListField_paypal_displayAttributesToAdd($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $LANG_PAYPAL_ADMIN;

    if ($fieldname === 'edit') {
        return COM_createLink(
            $icon_arr['disabled'],
            '#',
            array(
                'class' => 'add',
                'id' => (int) $A['at_id'],
                'pid' => (int) $A['product_id'],
                'title' => $LANG_PAYPAL_ADMIN['add_attribute'],
            )
        );
    }

    return stripslashes((string) $fieldvalue);
}

function PAYPAL_displayCustomAttributes($productId)
{
    global $_TABLES, $_PAY_CONF;

    $productId = (int) $productId;
    $retval = '';

    $sql = "SELECT at.*
        FROM {$_TABLES['paypal_product_attribute']} AS pa
        INNER JOIN {$_TABLES['paypal_attributes']} AS at
            ON pa.pa_aid = at.at_id
        WHERE pa.pa_pid = {$productId}
          AND at.at_enabled = 1
        ORDER BY at.at_type, at.at_order, at.at_name";

    $result = DB_query($sql);
    $rows = array();

    while ($A = DB_fetchArray($result)) {
        $rows[] = $A;

        if (!empty($A['at_image'])
            && is_file($_PAY_CONF['path_at_images'] . $A['at_image'])) {
            $size = (int) $_PAY_CONF['attribute_thumbnail_size'];
            $imageUrl = $_PAY_CONF['images_at_url'] . rawurlencode($A['at_image']);
            $retval .= '<div class="attribute_thumbnail"><a class="paypal-image-link" href="'
                . $imageUrl . '"><img src="' . $imageUrl . '" width="' . $size
                . '" alt="' . htmlspecialchars($A['at_name'], ENT_QUOTES, 'UTF-8') . '"></a></div>';
        }
    }

    if (empty($rows)) {
        return $retval;
    }

    $retval .= '<div style="clear:both"></div>';

    $currentType = null;
    foreach ($rows as $A) {
        $typeId = (int) $A['at_type'];

        if ($currentType !== $typeId) {
            if ($currentType !== null) {
                $retval .= '</select>';
            }

            $retval .= '<select class="attributes_select" name="attribute[' . $typeId . ']">';
            $currentType = $typeId;
        }

        $retval .= '<option ref="' . htmlspecialchars($A['at_code'], ENT_QUOTES, 'UTF-8')
            . '" id="' . htmlspecialchars($A['at_image'], ENT_QUOTES, 'UTF-8')
            . '" value="' . (int) $A['at_id'] . '">'
            . htmlspecialchars($A['at_name'], ENT_QUOTES, 'UTF-8')
            . '</option>';
    }

    if ($currentType !== null) {
        $retval .= '</select>';
    }

    return $retval;
}

function PAYPAL_reorderAttributes()
{
    global $_TABLES;

    $sql = "SELECT at.at_id, at.at_order
        FROM {$_TABLES['paypal_attributes']} AS at
        LEFT JOIN {$_TABLES['paypal_attribute_type']} AS t
            ON at.at_type = t.at_tid
        ORDER BY t.at_torder ASC, at.at_order ASC, at.at_id ASC";

    $result = DB_query($sql);
    $order = 10;

    while ($A = DB_fetchArray($result)) {
        if ((int) $A['at_order'] !== $order) {
            DB_query(
                "UPDATE {$_TABLES['paypal_attributes']} "
                . "SET at_order = {$order} WHERE at_id = " . (int) $A['at_id']
            );
        }
        $order += 10;
    }
}
