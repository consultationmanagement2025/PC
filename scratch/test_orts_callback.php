<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_API_KEY'] = 'pcms_live_key_2026';
$_GET['endpoint'] = 'update_ordinance_status';

$payload = [
    'consultation_id' => 1,
    'status' => 'proceeded_to_ordinance',
    'ordinance_no' => 'ORD-2026-089',
    'committee_name' => 'Committee on Laws, Rules & Ethics'
];

// Mock php://input
class InputStreamMock {
    public static $content;
}
$rawJson = json_encode($payload);

// Test executing v1_external_api.php directly
ob_start();
$_POST = $payload;
require __DIR__ . '/../API/v1_external_api.php';
$out = ob_get_clean();

echo "=== INBOUND ORTS UPDATE API TEST ===\n";
echo $out . "\n";
?>
