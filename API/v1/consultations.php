<?php
require_once __DIR__ . '/bootstrap.php';

$conn = $GLOBALS['integration_conn'];
$requestId = $GLOBALS['integration_request_id'];
$client = lgu2_require_auth($conn, $requestId, ['consultations:read']);

$res = $conn->query("SELECT id, title, topic, status, category, created_at, updated_at FROM consultations ORDER BY id DESC LIMIT 50");
$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}

lgu2_log_request($conn, (int) $client['client_id'], $_SERVER['SCRIPT_NAME'], 'GET', $requestId, 200);
lgu2_json_success(['consultations' => $rows]);
