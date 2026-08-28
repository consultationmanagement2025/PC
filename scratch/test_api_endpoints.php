<?php
require_once __DIR__ . '/../db.php';

session_start();
$_SESSION['role'] = 'admin';
$_SESSION['user_id'] = 1;
$_SESSION['email'] = 'admin@valenzuela.gov.ph';

echo "=== TESTING FEEDBACK API ===\n";
$_GET['action'] = 'list';
$_GET['limit'] = 200;
$_GET['offset'] = 0;
ob_start();
include __DIR__ . '/../API/feedback_api.php';
$fb_out = ob_get_clean();
$fb_json = json_decode($fb_out, true);
echo "Feedback API Success: " . ($fb_json['success'] ? 'YES' : 'NO') . "\n";
echo "Feedback Items Count: " . (isset($fb_json['data']) && is_array($fb_json['data']) ? count($fb_json['data']) : 0) . "\n\n";

echo "=== TESTING DOCUMENTS API ===\n";
$_GET['action'] = 'list';
$_GET['limit'] = 200;
$_GET['offset'] = 0;
ob_start();
include __DIR__ . '/../API/documents_api.php';
$doc_out = ob_get_clean();
$doc_json = json_decode($doc_out, true);
echo "Documents API Success: " . ($doc_json['success'] ? 'YES' : 'NO') . "\n";
echo "Documents Items Count: " . (isset($doc_json['data']) && is_array($doc_json['data']) ? count($doc_json['data']) : 0) . "\n";
?>
