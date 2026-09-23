<?php

function PAYPAL_attributesMenu()
{
    global $_CONF, $LANG_PAYPAL_ADMIN;

    return ' | <a href="' . $_CONF['site_admin_url'] . '/plugins/paypal/index.php?mode=attributes">'
        . $LANG_PAYPAL_ADMIN['manage_attributes'] . '</a>';
}

function PAYPAL_attributes()
{
    global $_CONF, $_TABLES, $LANG_PAYPAL_ADMIN, $LANG_PAYPAL_1;

    $retval = '';
    $atId = isset($_REQUEST['at_id']) ? (int) $_REQUEST['at_id'] : 0;
    $op = isset($_REQUEST['op']) ? $_REQUEST['op'] : '';

    switch ($op) {
        case 'edit':
            $attribute = array();
            if ($atId > 0) {
                $res = DB_query("SELECT * FROM {$_TABLES['paypal_attributes']} WHERE at_id = {$atId}");
                $attribute = DB_fetchArray($res);
                if (!is_array($attribute)) {
                    $attribute = array();
                }
            }
            return PAYPAL_getAttributeForm($attribute);

        case 'save':
            $name = isset($_REQUEST['at_name']) ? trim($_REQUEST['at_name']) : '';
            $typeId = isset($_REQUEST['at_tid']) ? (int) $_REQUEST['at_tid'] : 0;

            if ($name === '' || $typeId <= 0) {
                return PAYPAL_getAttributeForm($_REQUEST);
            }

            $name = DB_escapeString($name);
            $code = DB_escapeString(isset($_REQUEST['at_code']) ? $_REQUEST['at_code'] : '');
            $enabled = !empty($_REQUEST['at_enabled']) ? 1 : 0;
            $price = isset($_REQUEST['at_price']) ? str_replace(',', '', $_REQUEST['at_price']) : '0';
            $price = preg_replace('/[^\d.]/', '', $price);
            if ($price === '') {
                $price = '0';
            }

            $fields = "at_name = '{$name}', "
                . "at_code = '{$code}', "
                . "at_type = '{$typeId}', "
                . "at_price = '{$price}', "
                . "at_enabled = '{$enabled}'";

            if ($atId > 0) {
                DB_query("UPDATE {$_TABLES['paypal_attributes']} SET {$fields} WHERE at_id = {$atId}");
            } else {
                DB_query("INSERT INTO {$_TABLES['paypal_attributes']} SET {$fields}");
                $atId = DB_insertId();
            }

            $msg = DB_error() ? $LANG_PAYPAL_1['save_fail'] : $LANG_PAYPAL_1['save_success'];

            if ($atId > 0 && !empty($_FILES)) {
                PAYPAL_saveAttributeImage($_REQUEST, $_FILES, $atId);
            }

            echo COM_refresh(
                $_CONF['site_admin_url'] . '/plugins/paypal/index.php?mode=attributes&amp;msg=' . urlencode($msg)
            );
            exit;

        case 'move':
            if (!SEC_checkToken()) {
                return COM_showMessageText($LANG_PAYPAL_1['access_denied'], $LANG_PAYPAL_1['error']);
            }

            $where = isset($_REQUEST['where']) ? COM_applyFilter($_REQUEST['where']) : '';
            if ($atId > 0 && DB_count($_TABLES['paypal_attributes'], 'at_id', $atId) == 1) {
                if ($where === 'up') {
                    DB_query("UPDATE {$_TABLES['paypal_attributes']} SET at_order = at_order - 11 WHERE at_id = {$atId}");
                } elseif ($where === 'dn') {
                    DB_query("UPDATE {$_TABLES['paypal_attributes']} SET at_order = at_order + 11 WHERE at_id = {$atId}");
                }
                PAYPAL_reorderAttributes();
            }
            // fall through to list
    }

    $retval .= $LANG_PAYPAL_1['you_can']
        . '<a href="' . $_CONF['site_admin_url'] . '/plugins/paypal/index.php?mode=attributes&amp;op=edit">'
        . $LANG_PAYPAL_ADMIN['create_attribute'] . '</a>';
    $retval .= PAYPAL_listAttributes();

    return $retval;
}

function PAYPAL_listAttributes()
{
    global $_CONF, $_TABLES, $LANG_PAYPAL_ADMIN, $LANG_ADMIN;

    require_once $_CONF['path_system'] . 'lib-admin.php';

    $header = array(
        array('text' => $LANG_ADMIN['edit'], 'field' => 'edit', 'sort' => false),
        array('text' => $LANG_PAYPAL_ADMIN['attribute_label'], 'field' => 'at_name', 'sort' => true),
        array('text' => $LANG_PAYPAL_ADMIN['order'], 'field' => 'at_order', 'sort' => true),
        array('text' => $LANG_PAYPAL_ADMIN['move'], 'field' => 'move', 'sort' => false),
        array('text' => $LANG_PAYPAL_ADMIN['code_label'], 'field' => 'at_code', 'sort' => true),
    );

    $text = array(
        'has_extras' => true,
        'form_url' => $_CONF['site_admin_url'] . '/plugins/paypal/index.php?mode=attributes',
    );

    $query = array(
        'table' => 'paypal_attributes',
        'sql' => "SELECT * FROM {$_TABLES['paypal_attributes']}",
        'query_fields' => array('at_id', 'at_name', 'at_code'),
    );

    return ADMIN_list(
        'paypal_attributes',
        'PAYPAL_getListField_paypal_attributes',
        $header,
        $text,
        $query,
        array('field' => 'at_order', 'direction' => 'asc')
    );
}

function PAYPAL_getListField_paypal_attributes($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $LANG21;

    switch ($fieldname) {
        case 'edit':
            $url = $_CONF['site_admin_url']
                . '/plugins/paypal/index.php?mode=attributes&amp;op=edit&amp;at_id=' . (int) $A['at_id'];
            return COM_createLink($icon_arr['edit'], $url);

        case 'move':
            $token = '&amp;' . CSRF_TOKEN . '=' . SEC_createToken();
            $base = $_CONF['site_admin_url']
                . '/plugins/paypal/index.php?mode=attributes&amp;op=move&amp;at_id=' . (int) $A['at_id'];
            return '<a href="' . $base . '&amp;where=up' . $token . '" title="' . $LANG21[58] . '">'
                . '<img src="' . $_CONF['layout_url'] . '/images/admin/up.png" alt="' . $LANG21[58] . '"></a> '
                . '<a href="' . $base . '&amp;where=dn' . $token . '" title="' . $LANG21[57] . '">'
                . '<img src="' . $_CONF['layout_url'] . '/images/admin/down.png" alt="' . $LANG21[57] . '"></a>';

        default:
            return stripslashes((string) $fieldvalue);
    }
}

function PAYPAL_getAttributeForm($attribute = array())
{
    global $_CONF, $_PAY_CONF, $_TABLES, $LANG_PAYPAL_1, $LANG_PAYPAL_ADMIN;

    $attribute = array_merge(array(
        'at_id' => 0,
        'at_name' => '',
        'at_image' => '',
        'at_code' => '',
        'at_type' => 0,
        'at_enabled' => 1,
        'at_price' => 0,
    ), is_array($attribute) ? $attribute : array());

    $title = $attribute['at_name'] === ''
        ? $LANG_PAYPAL_ADMIN['create_attribute']
        : $LANG_PAYPAL_1['edit_label'] . ' ' . $attribute['at_name'];

    $retval = COM_startBlock($title);
    $template = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates');
    $template->set_var('gltoken_name', CSRF_TOKEN);
    $template->set_var('gltoken', SEC_createToken());
    $template->set_file(array('attribute' => 'attribute_form.thtml'));

    $attributeId = (int) $attribute['at_id'];
    $template->set_var('at_id', $attributeId > 0
        ? '<input type="hidden" name="at_id" value="' . $attributeId . '">'
        : '');
    $template->set_var('id_image', $attributeId > 0
        ? '<input type="hidden" name="at_image" value="' . htmlspecialchars($attribute['at_image'], ENT_QUOTES, 'UTF-8') . '">'
        : '');
    $template->set_var('delete_button', '');

    $template->set_var(array(
        'admin_url' => $_CONF['site_admin_url'],
        'at_name' => htmlspecialchars($attribute['at_name'], ENT_QUOTES, 'UTF-8'),
        'attribute_label' => $LANG_PAYPAL_ADMIN['attribute_label'],
        'code' => htmlspecialchars($attribute['at_code'], ENT_QUOTES, 'UTF-8'),
        'code_label' => $LANG_PAYPAL_ADMIN['code_label'],
        'type_label' => $LANG_PAYPAL_ADMIN['type'],
        'required_field' => $LANG_PAYPAL_1['required_field'],
        'ok_button' => $LANG_PAYPAL_1['ok_button'],
        'save_button' => $LANG_PAYPAL_1['save_button'],
        'yes' => $LANG_PAYPAL_1['yes'],
        'no' => $LANG_PAYPAL_1['no'],
        'enabled' => $LANG_PAYPAL_ADMIN['enabled_attribute'],
        'image_message' => $LANG_PAYPAL_ADMIN['image_message'],
        'image' => $LANG_PAYPAL_ADMIN['image'],
        'main_settings' => $LANG_PAYPAL_ADMIN['main_settings'],
        'price_label' => $LANG_PAYPAL_1['price_label'],
        'price_edit' => $LANG_PAYPAL_1['price_edit'],
        'price' => number_format((float) $attribute['at_price'], $_CONF['decimal_count'], '.', ''),
        'currency' => $_PAY_CONF['currency'],
        'xhtml' => XHTML,
    ));

    $types = '<option value="0">' . $LANG_PAYPAL_ADMIN['choose_type'] . '</option>';
    $types .= PAYPAL_adOptionList(
        $_TABLES['paypal_attribute_type'],
        'at_tid,at_tname',
        $attribute['at_type'],
        'at_tname'
    );
    $template->set_var('types', $types);

    if ((int) $attribute['at_enabled'] === 1) {
        $template->set_var('enabled_yes', ' selected');
        $template->set_var('enabled_no', '');
    } else {
        $template->set_var('enabled_yes', '');
        $template->set_var('enabled_no', ' selected');
    }

    $imagePath = $_PAY_CONF['path_at_images'] . $attribute['at_image'];
    if ($attribute['at_image'] !== '' && is_file($imagePath)) {
        $template->set_var(
            'at_image',
            '<p>' . $LANG_PAYPAL_ADMIN['image_replace'] . '</p><p><img src="'
            . $_PAY_CONF['images_at_url'] . rawurlencode($attribute['at_image'])
            . '" style="max-width:150px" alt=""></p>'
        );
    } else {
        $template->set_var('at_image', '');
    }

    $retval .= $template->parse('', 'attribute');
    $retval .= COM_endBlock();

    return $retval;
}
