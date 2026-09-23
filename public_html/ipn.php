<?php

require_once '../lib-common.php';

if (!in_array('paypal', $_PLUGINS)) {
    http_response_code(404);
    exit;
}

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ($contentLength > 1048576) {
    http_response_code(413);
    exit;
}

require_once $_CONF['path'] . 'plugins/paypal/classes/IPN.class.php';

$ipn = new IPN();
$ipn->Process($_POST);

// PayPal only needs a successful HTTP response after the listener has handled
// the notification. The authenticity decision is made by server-to-server
// verification inside BaseIPN::Verify().
http_response_code(200);
header('Content-Type: text/plain; charset=UTF-8');
echo 'OK';
