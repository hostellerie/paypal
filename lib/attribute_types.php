<?php

function PAYPAL_attributeTypesMenu()
{
    global $_CONF, $LANG_PAYPAL_ADMIN;

    return ' | <a href="' . $_CONF['site_admin_url'] . '/plugins/paypal/index.php?mode=attributetypes">'
        . $LANG_PAYPAL_ADMIN['manage_attributetypes'] . '</a>';
}

function PAYPAL_attributeTypes()
{
    global $_CONF, $_TABLES, $LANG_PAYPAL_ADMIN, $LANG_PAYPAL_1;

    $retval = '';
    $typeId = isset($_REQUEST['at_tid']) ? (int) $_REQUEST['at_tid'] : 0;
    $op = isset($_REQUEST['op']) ? $_REQUEST['op'] : '';

    switch ($op) {
        case 'edit':
            $type = array();
            if ($typeId > 0) {
                $res = DB_query(
                    "SELECT * FROM {$_TABLES['paypal_attribute_type']} WHERE at_tid = {$typeId}"
                );
                $type = DB_fetchArray($res);
                if (!is_array($type)) {
                    $type = array();
                }
            }
            return PAYPAL_getAttributeTypeForm($type);

        case 'save':
            $name = isset($_REQUEST['type']) ? trim($_REQUEST['type']) : '';
            if ($name === '') {
                return PAYPAL_getAttributeTypeForm($_REQUEST);
            }

            $name = DB_escapeString($name);
            $order = isset($_REQUEST['order']) ? (int) $_REQUEST['order'] : 0;
            $fields = "at_tname = '{$name}', at_torder = '{$order}'";

            if ($typeId > 0) {
                DB_query(
                    "UPDATE {$_TABLES['paypal_attribute_type']} "
                    . "SET {$fields} WHERE at_tid = {$typeId}"
                );
            } else {
                DB_query("INSERT INTO {$_TABLES['paypal_attribute_type']} SET {$fields}");
            }

            $msg = DB_error() ? $LANG_PAYPAL_1['save_fail'] : $LANG_PAYPAL_1['save_success'];
            echo COM_refresh(
                $_CONF['site_admin_url']
                . '/plugins/paypal/index.php?mode=attributetypes&amp;msg='
                . urlencode($msg)
            );
            exit;
    }

    $retval .= $LANG_PAYPAL_1['you_can']
        . '<a href="' . $_CONF['site_admin_url']
        . '/plugins/paypal/index.php?mode=attributetypes&amp;op=edit">'
        . $LANG_PAYPAL_ADMIN['create_attributetype'] . '</a>';
    $retval .= PAYPAL_listAttributeTypes();

    return $retval;
}

function PAYPAL_listAttributeTypes()
{
    global $_CONF, $_TABLES, $LANG_PAYPAL_ADMIN, $LANG_ADMIN, $LANG_PAYPAL_1;

    require_once $_CONF['path_system'] . 'lib-admin.php';

    $header = array(
        array('text' => $LANG_ADMIN['edit'], 'field' => 'edit', 'sort' => false),
        array('text' => $LANG_PAYPAL_1['category_heading'], 'field' => 'at_tname', 'sort' => true),
        array('text' => $LANG_PAYPAL_ADMIN['order_label'], 'field' => 'at_torder', 'sort' => true),
    );

    $query = array(
        'table' => 'paypal_attribute_type',
        'sql' => "SELECT * FROM {$_TABLES['paypal_attribute_type']}",
        'query_fields' => array('at_tid', 'at_tname', 'at_torder'),
    );

    return ADMIN_list(
        'paypal_attribute_types',
        'PAYPAL_getListField_paypal_attributetypes',
        $header,
        array(
            'has_extras' => true,
            'form_url' => $_CONF['site_admin_url'] . '/plugins/paypal/index.php?mode=attributetypes',
        ),
        $query,
        array('field' => 'at_torder', 'direction' => 'asc')
    );
}

function PAYPAL_getListField_paypal_attributetypes($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF;

    if ($fieldname === 'edit') {
        $url = $_CONF['site_admin_url']
            . '/plugins/paypal/index.php?mode=attributetypes&amp;op=edit&amp;at_tid='
            . (int) $A['at_tid'];
        return COM_createLink($icon_arr['edit'], $url);
    }

    return stripslashes((string) $fieldvalue);
}

function PAYPAL_getAttributeTypeForm($attributeType = array())
{
    global $_CONF, $LANG_PAYPAL_1, $LANG_PAYPAL_ADMIN;

    $attributeType = array_merge(array(
        'at_tid' => 0,
        'at_tname' => '',
        'at_torder' => 0,
    ), is_array($attributeType) ? $attributeType : array());

    $title = $attributeType['at_tname'] === ''
        ? $LANG_PAYPAL_ADMIN['create_attributetype']
        : $LANG_PAYPAL_1['edit_label'] . ' ' . $attributeType['at_tname'];

    $retval = COM_startBlock($title);
    $template = COM_newTemplate($_CONF['path'] . 'plugins/paypal/templates');
    $template->set_var('gltoken_name', CSRF_TOKEN);
    $template->set_var('gltoken', SEC_createToken());
    $template->set_file(array('type' => 'attribute_type_form.thtml'));

    $typeId = (int) $attributeType['at_tid'];
    $template->set_var(array(
        'admin_url' => $_CONF['site_admin_url'],
        'at_tid' => $typeId > 0
            ? '<input type="hidden" name="at_tid" value="' . $typeId . '">'
            : '',
        'delete_button' => '',
        'at_tname' => htmlspecialchars($attributeType['at_tname'], ENT_QUOTES, 'UTF-8'),
        'type_label' => $LANG_PAYPAL_ADMIN['type'],
        'order_label' => $LANG_PAYPAL_ADMIN['order_label'],
        'order' => (int) $attributeType['at_torder'],
        'main_settings' => $LANG_PAYPAL_ADMIN['main_settings'],
        'required_field' => $LANG_PAYPAL_1['required_field'],
        'save_button' => $LANG_PAYPAL_1['save_button'],
        'ok_button' => $LANG_PAYPAL_1['ok_button'],
        'xhtml' => XHTML,
    ));

    $retval .= $template->parse('', 'type');
    $retval .= COM_endBlock();

    return $retval;
}
