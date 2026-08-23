<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/consultations.php';

// 1. Create a test consultation
$conn->query("INSERT INTO consultations (title, description, category, type, user_name, user_email, status, tracking_number) VALUES ('Test Citizen Proposal for Decline', 'Test description', 'Infrastructure', 'user', 'Maria Santos', 'maria@example.com', 'pending', 'TRK-DECLINE-TEST-99')");
$testId = $conn->insert_id;

echo "Inserted test consultation ID: {$testId}\n";

// 2. Execute decline action directly
$_GET['action'] = 'decline_submission';
$_POST = [
    'id' => $testId,
    'reason' => 'Proposal budget exceeds fiscal limits for 2026.'
];

// Include API script to process
ob_start();
require __DIR__ . '/../API/consultations_api.php';
$output = ob_get_clean();

echo "API RESPONSE:\n" . $output . "\n";

// 3. Verify status in DB
$checkRes = $conn->query("SELECT status, admin_response, remarks FROM consultations WHERE id = {$testId}");
$row = $checkRes ? $checkRes->fetch_assoc() : null;
echo "VERIFIED DB RECORD:\n";
print_r($row);
