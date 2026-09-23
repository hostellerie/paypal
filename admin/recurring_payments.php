<?php
// +--------------------------------------------------------------------------+
// | PayPal Plugin 1.6 - geeklog CMS                                          |
// +--------------------------------------------------------------------------+
// | recurrng_payments.php                                                    |
// |                                                                          |
// | Admin index page for the paypal plugin.  By default, lists products      |
// | available for editing                                                    |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2014 by the following authors:                             |
// |                                                                          |
// | Authors: ::Ben - ben AT geeklog DOT fr                                   |
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
 * @package paypal
 */

/**
 * Required geeklog
 */
require_once('../../../lib-common.php');

// Check for required permissions
paypal_access_check('paypal.admin');

$vars = array('msg' => 'text',
              'mode' => 'alpha');
			  
paypal_filterVars($vars, $_REQUEST);

function PAYPAL_listRecurringPayments()
{
    global $_CONF, $_TABLES, $LANG_PAYPAL_ADMIN, $LANG_ADMIN, $LANG_PAYPAL_1;
	
    require_once $_CONF['path_system'] . 'lib-admin.php';

    $retval = '';

    $header_arr = array(      // display 'text' and use table field 'field'
		array('text' => $LANG_PAYPAL_1['user_name'], 'field' => 'user_id', 'sort' => true),
        array('text' => $LANG_PAYPAL_1['profile_id'], 'field' => 'profileid', 'sort' => true),
        array('text' => $LANG_PAYPAL_1['recdate'], 'field' => 'recdate', 'sort' => true),
        array('text' => $LANG_PAYPAL_1['status'], 'field' => 'status', 'sort' => true)
    );
	

    $defsort_arr = array('field' => 'recdate', 'direction' => 'desc');

    $text_arr = array(
        'has_extras' => true,
        'form_url' => $_CONF['site_admin_url'] . '/plugins/paypal/recurring_payments.php'
    );
	
	$sql = "SELECT
	            r.*, u.username
            FROM 
			    {$_TABLES['paypal_recurrent']} as r
			LEFT JOIN
				{$_TABLES['users']} AS u 
			ON
				r.user_id = u.uid
			WHERE 1=1 	
			";

    $query_arr = array(
        'sql'            => $sql,
		'query_fields'   => array('r.user_id', 'r.profileid', 'r.recdate', 'r.status'),
    );

    $retval .= ADMIN_list('paypal_recurring', 'PAYPAL_getListField_paypal_recurring',
                          $header_arr, $text_arr, $query_arr, $defsort_arr);

    return $retval;
}

function PAYPAL_getListField_paypal_recurring($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $LANG_ADMIN, $LANG_STATIC, $_TABLES, $_PAY_CONF;

    switch($fieldname) {
        case "user_id":
            
			if ($A['user_id'] >= 2) {
			    $retval = '<a href="' . $_CONF['site_url'] . '/users.php?mode=profile&uid=' . $A['user_id'] . '">' . $A['username'] .'</a>';
			} else {
			    $retval = $A['username'];
			}
			
            break;

        default:
            $retval = stripslashes($fieldvalue);
            break;
    }
    return $retval;
}

//Main

$display = paypal_admin_menu();

if (!empty($_REQUEST['msg'])) $display .= COM_showMessageText( stripslashes($_REQUEST['msg']), $LANG_PAYPAL_1['message']);


switch ($_REQUEST['mode']) {
		
	default :
        $display .= COM_startBlock($LANG_PAYPAL_1['recurring_list']);
        $display .= PAYPAL_listRecurringPayments();
        $display .= COM_endBlock();
	}

//For testing 
//plugin_runScheduledTask_paypal();

COM_output(PAYPAL_createHTMLDocument($display, $LANG_PAYPAL_1['recurring_list']));

?>