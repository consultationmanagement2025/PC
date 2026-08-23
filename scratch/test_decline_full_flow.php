<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/../db.php';

// Insert temporary test submission
$conn->query("INSERT INTO consultations (title, description, category, type, user_name, user_email, status, tracking_number) VALUES ('Test Citizen Submission for Flow Check', 'Testing full decline flow', 'Environment', 'user', 'Elena Bautista', 'elena@example.com', 'pending', 'TRK-FLOW-TEST-001')");
$id = $conn->insert_id;

echo "Inserted test consultation ID: {$id}\n";

// Execute decline via API logic
$_GET['action'] = 'decline_submission';
$_POST = ['id' => $id, 'reason' => 'Duplicate proposal submission.'];

ob_start();
require __DIR__ . '/../API/consultations_api.php';
$output = ob_get_clean();

echo "API RESULT: " . $output . "\n";

$res = $conn->query("SELECT status, admin_response FROM consultations WHERE id = {$id}");
$row = $res->fetch_assoc();
echo "UPDATED DB STATUS: " . $row['status'] . "\n";
echo "ADMIN RESPONSE: " . $row['admin_response'] . "\n";

// Cleanup test row
$conn->query("DELETE FROM consultations WHERE id = {$id}");
echo "Cleaned up test row.\n";
