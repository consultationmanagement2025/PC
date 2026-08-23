<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/../db.php';

// Verify ID 8 is in DB with pending status
$conn->query("UPDATE consultations SET status = 'pending' WHERE id = 8");

// Run decline via API
$_GET['action'] = 'decline_submission';
$_POST = ['id' => 8, 'reason' => 'Proposal test decline final.'];

ob_start();
require __DIR__ . '/../API/consultations_api.php';
$output = ob_get_clean();

echo "API RESPONSE: " . $output . "\n";

$res = $conn->query("SELECT id, title, user_name, status, admin_response FROM consultations WHERE id = 8");
print_r($res->fetch_assoc());
