<?php

function PAYPAL_saveAttributeImage($attribute, $files, $atId)
{
    global $_CONF, $_PAY_CONF, $_TABLES, $LANG24;

    if ($atId <= 0 || empty($files)) {
        return true;
    }

    PAYPAL_ensureStorageDirectories(true);

    require_once $_CONF['path_system'] . 'classes/upload.class.php';
    $upload = new upload();

    if (!empty($_CONF['debug_image_upload'])) {
        $upload->setLogFile($_CONF['path'] . 'logs/error.log');
        $upload->setDebug(true);
    }

    $upload->setMaxFileUploads(1);

    if (!empty($_CONF['image_lib'])) {
        if ($_CONF['image_lib'] === 'imagemagick') {
            $upload->setMogrifyPath($_CONF['path_to_mogrify']);
        } elseif ($_CONF['image_lib'] === 'netpbm') {
            $upload->setNetPBM($_CONF['path_to_netpbm']);
        } elseif ($_CONF['image_lib'] === 'gdlib') {
            $upload->setGDLib();
        }

        $upload->setAutomaticResize(true);
        $upload->keepOriginalImage(false);

        if (isset($_CONF['jpeg_quality'])) {
            $upload->setJpegQuality($_CONF['jpeg_quality']);
        }
    }

    $upload->setAllowedMimeTypes(array(
        'image/gif' => '.gif',
        'image/jpeg' => '.jpg,.jpeg',
        'image/pjpeg' => '.jpg,.jpeg',
        'image/x-png' => '.png',
        'image/png' => '.png',
    ));

    if (!$upload->setPath($_PAY_CONF['path_at_images'])) {
        $output = COM_startBlock($LANG24[30], '', COM_getBlockTemplate('_msg_block', 'header'));
        $output .= $upload->printErrors(false);
        $output .= COM_endBlock(COM_getBlockTemplate('_msg_block', 'footer'));
        echo PAYPAL_createHTMLDocument($output, $LANG24[30]);
        exit;
    }

    $upload->setMaxDimensions($_PAY_CONF['max_image_width'], $_PAY_CONF['max_image_height']);
    $upload->setMaxFileSize($_PAY_CONF['max_image_size']);
    $upload->setPerms('0644');

    $current = current($files);
    if (empty($current['name'])) {
        return true;
    }

    $extension = pathinfo($current['name'], PATHINFO_EXTENSION);
    if ($extension === '') {
        return true;
    }

    $filename = 'attr_' . (int) $atId . '.' . strtolower($extension);
    $upload->setFileNames($filename);
    reset($files);
    $upload->uploadFiles();

    if ($upload->areErrors()) {
        $output = COM_startBlock($LANG24[30], '', COM_getBlockTemplate('_msg_block', 'header'));
        $output .= $upload->printErrors(false);
        $output .= COM_endBlock(COM_getBlockTemplate('_msg_block', 'footer'));
        echo PAYPAL_createHTMLDocument($output, $LANG24[30]);
        exit;
    }

    $safeFilename = DB_escapeString($filename);
    DB_query(
        "UPDATE {$_TABLES['paypal_attributes']} "
        . "SET at_image = '{$safeFilename}' WHERE at_id = " . (int) $atId
    );

    return true;
}

function PAYPAL_deleteAttributeImage($image)
{
    global $_PAY_CONF;

    if ($image === '') {
        return true;
    }

    $file = $_PAY_CONF['path_at_images'] . basename($image);

    if (is_file($file) && !@unlink($file)) {
        COM_errorLog('PayPal: unable to remove attribute image ' . basename($image));
        return false;
    }

    return true;
}
