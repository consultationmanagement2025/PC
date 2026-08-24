<?php
require_once __DIR__ . '/../db.php';

// Let's test decline API call with HTTP simulation or curl or direct include
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'decline_submission';

// Check row 1 in DB first
$res = $conn->query("SELECT id, status, title FROM consultations WHERE id = 1 LIMIT 1");
$row = $res ? $res->fetch_assoc() : null;
echo "Initial DB Row 1: " . json_encode($row) . "\n";

// Now test with ID 1
$_POST['id'] = 1;
$_POST['reason'] = 'Decline test execution';

ob_start();
include __DIR__ . '/../API/consultations_api.php';
$out = ob_get_clean();

echo "API Response: " . $out . "\n";

$resAfter = $conn->query("SELECT id, status, admin_response, remarks FROM consultations WHERE id = 1 LIMIT 1");
$rowAfter = $resAfter ? $resAfter->fetch_assoc() : null;
echo "After DB Row 1: " . json_encode($rowAfter) . "\n";
