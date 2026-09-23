<?php

if (isset($_SERVER['PHP_SELF']) && strpos(strtolower($_SERVER['PHP_SELF']), 'features.php') !== false) {
    die('This file can not be used on its own.');
}

require_once __DIR__ . '/membership.php';
require_once __DIR__ . '/attributes.php';
require_once __DIR__ . '/attribute_images.php';
require_once __DIR__ . '/attribute_types.php';
require_once __DIR__ . '/product_attributes.php';
require_once __DIR__ . '/sales_plot.php';
