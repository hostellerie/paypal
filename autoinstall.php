<?php

/* Reminder: always indent with 4 spaces (no tabs). */
// +---------------------------------------------------------------------------+
// | Paypal Plugin 1.6                                                         |
// +---------------------------------------------------------------------------+
// | autoinstall.php                                                           |
// |                                                                           |
// | This file provides helper functions for the automatic plugin install.     |
// +---------------------------------------------------------------------------+
// | Copyright (C) 2009-2015 by the following authors:                         |
// |                                                                           |
// | Authors: ::Ben - cordiste AT free DOT fr                                  |
// +---------------------------------------------------------------------------+
// | Created with the Geeklog Plugin Toolkit.                                  |
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

/**
* @package Paypal
*/

/**
* Plugin autoinstall function
*
* @param    string  $pi_name    Plugin name
* @return   array               Plugin information
*
*/
function plugin_autoinstall_paypal($pi_name)
{	
    $pi_name         = 'paypal';
    $pi_display_name = 'Paypal';
    $pi_admin        = $pi_display_name . ' Admin';
    $pi_user         = $pi_display_name . ' User';
    $pi_viewer       = $pi_display_name . ' Viewer';
	
    $info = array(
        'pi_name'         => $pi_name,
        'pi_display_name' => $pi_display_name,
        'pi_version'      => '1.7.0',
        'pi_gl_version'   => '2.1.1',
        'pi_homepage'     => 'https://github.com/Geeklog-Plugins/paypal'
    );

    $groups = array(
        $pi_admin => 'Users in this group can administer the '
                     . $pi_display_name . ' plugin',
		$pi_user => 'Users in this group can access to the '
                     . $pi_display_name . ' plugin',
		$pi_viewer => 'Users in this group can view the '
                     . $pi_display_name . ' plugin'
    );

    $features = array(
        $pi_name . '.admin'    => 'Full access to ' . $pi_display_name
                                  . ' plugin',
        $pi_name . '.user'    => 'People who can shop with the ' . $pi_display_name
                                  . ' plugin',
        $pi_name . '.viewer'    => 'People who can browse (but not buy) with the ' . $pi_display_name
                                  . ' plugin',
    );

    $mappings = array(
        $pi_name . '.admin'     => array($pi_admin),
        $pi_name . '.user'      => array($pi_admin, $pi_user),		
        $pi_name . '.viewer'    => array($pi_admin, $pi_user, $pi_viewer),

	);
	
    $tables = array(
        'paypal_ipnlog',
		'paypal_downloads',
		'paypal_products',
		'paypal_purchases',
		'paypal_images',
		'paypal_categories',
		'paypal_subscriptions',
		'paypal_users',
		'paypal_attributes',
		'paypal_attribute_type',
		'paypal_product_attribute',
		'paypal_stock',
		'paypal_delivery',
		'paypal_stock_movements',
		'paypal_providers',
		'paypal_shipper_service',
		'paypal_shipping_to',
		'paypal_shipping_cost',
		'paypal_recurrent',
    );

    $inst_parms = array(
        'info'      => $info,
        'groups'    => $groups,
        'features'  => $features,
        'mappings'  => $mappings,
        'tables'    => $tables
    );

    return $inst_parms;
}

/**
* Check if the plugin is compatible with this Geeklog version
*
* @param    string  $pi_name    Plugin name
* @return   boolean             true: plugin compatible; false: not compatible
*
*/
require_once __DIR__ . '/uninstall.php';

function plugin_compatible_with_this_version_paypal($pi_name)
{
    global $_CONF, $_DB_dbms;

    // check if we support the DBMS the site is running on
    $dbFile = $_CONF['path'] . 'plugins/' . $pi_name . '/sql/'
            . $_DB_dbms . '_install.php';
    if (! file_exists($dbFile)) {
        return false;
    }

    if (version_compare(VERSION, '2.1.1', '<')) {
        return false;
    }

    if (version_compare(PHP_VERSION, '5.6.0', '<')) {
        return false;
    }

    return true;
}

require_once __DIR__ . '/storage.php';

function plugin_postinstall_paypal($pi_name)
{
    global $_TABLES, $_CONF, $_USER;
	
    /* New Groups */
    $groups['Paypal User']   = 'Users in this group can purchase products';
    $groups['Paypal Viewer'] = 'Users in this group can view products';	

    // Resolve Geeklog core groups by name instead of relying on installation-specific IDs.
    $loggedInGroup = (int) DB_getItem($_TABLES['groups'], 'grp_id', "grp_name = 'Logged-in Users'");
    $allUsersGroup = (int) DB_getItem($_TABLES['groups'], 'grp_id', "grp_name = 'All Users'");

    $grp_assign['Paypal User'] = $loggedInGroup > 0 ? array($loggedInGroup) : array();
    $grp_assign['Paypal Viewer'] = $allUsersGroup > 0 ? array($allUsersGroup) : array();
	
     // Assign created paypal groups to other (logical) groups
    foreach ($grp_assign as $group => $grparray) {
		
        foreach ($grparray as $togroup) {
            COM_errorLog("Assigning group $togroup to $group",1);
			$result = DB_query("SELECT grp_id, grp_name FROM {$_TABLES['groups']} WHERE grp_name ='$group'");
            $A = DB_fetchArray($result);
            DB_query("INSERT INTO {$_TABLES['group_assignments']} "
                   . "VALUES ({$A[0]}, NULL, $togroup)");
            if (DB_error()) {
                COM_errorLog("Failed assigning group $togroup to $group",1);
                PLG_uninstall('paypal');
                return false;
            }
            COM_errorLog('...success',1);
        }
    }
	
    // Create persistent image/download storage used by the plugin.
    $storageFailures = PAYPAL_ensureStorageDirectories(true);
    if (!empty($storageFailures)) {
        COM_errorLog('PayPal: installation completed but some storage directories could not be prepared.');
    }

    // Create the plugin download log without making installation fatal.
    $paypalLog = rtrim($_CONF['path_log'], "/\\") . DIRECTORY_SEPARATOR . 'paypal_downloads.log';
    if (!file_exists($paypalLog)) {
        $paypalDownload = @fopen($paypalLog, 'a');
        if ($paypalDownload === false) {
            COM_errorLog('PayPal: unable to create download log at ' . $paypalLog);
        } else {
            fclose($paypalDownload);
        }
    }

	return true;
}

function plugin_load_configuration_paypal($pi_name)
{
    global $_CONF;

    $base_path = $_CONF['path'] . 'plugins/' . $pi_name . '/';

    require_once $_CONF['path_system'] . 'classes/config.class.php';
    require_once $base_path . 'install_defaults.php';

    return plugin_initconfig_paypal();
}

?>
