<?php

if (isset($_SERVER['PHP_SELF']) && strpos(strtolower($_SERVER['PHP_SELF']), 'storage.php') !== false) {
    die('This file can not be used on its own.');
}

/**
 * Ensure PayPal persistent storage directories exist and are writable.
 *
 * @param bool $logErrors Whether to log failures through COM_errorLog
 * @return array Empty array on success, otherwise failed directory paths
 */
function PAYPAL_ensureStorageDirectories($logErrors = true)
{
    global $_CONF;

    $directories = array(
        rtrim($_CONF['path_images'], "/\\") . DIRECTORY_SEPARATOR . 'paypal',
        rtrim($_CONF['path_images'], "/\\") . DIRECTORY_SEPARATOR . 'paypal' . DIRECTORY_SEPARATOR . 'products',
        rtrim($_CONF['path_images'], "/\\") . DIRECTORY_SEPARATOR . 'paypal' . DIRECTORY_SEPARATOR . 'categories',
        rtrim($_CONF['path_images'], "/\\") . DIRECTORY_SEPARATOR . 'paypal' . DIRECTORY_SEPARATOR . 'attributes',
        rtrim($_CONF['path_data'], "/\\") . DIRECTORY_SEPARATOR . 'private',
        rtrim($_CONF['path_data'], "/\\") . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'paypal',
        rtrim($_CONF['path_data'], "/\\") . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'paypal' . DIRECTORY_SEPARATOR . 'files'
    );

    $failed = array();

    foreach ($directories as $directory) {
        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
                $failed[] = $directory;
                if ($logErrors && function_exists('COM_errorLog')) {
                    COM_errorLog('PayPal: unable to create storage directory ' . $directory);
                }
                continue;
            }
        }

        if (!is_writable($directory)) {
            $failed[] = $directory;
            if ($logErrors && function_exists('COM_errorLog')) {
                COM_errorLog('PayPal: storage directory is not writable ' . $directory);
            }
        }
    }

    return array_values(array_unique($failed));
}
