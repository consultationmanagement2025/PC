<?php
require_once __DIR__ . '/bootstrap.php';

$conn = $GLOBALS['integration_conn'];
$requestId = $GLOBALS['integration_request_id'];
$client = lgu2_require_auth($conn, $requestId, []);

lgu2_log_request($conn, (int) $client['client_id'], $_SERVER['SCRIPT_NAME'], 'GET', $requestId, 200);
lgu2_json_success([
    'system' => 'PCMS',
    'authenticated_as' => $client['source_system'],
    'time' => date('c'),
]);
