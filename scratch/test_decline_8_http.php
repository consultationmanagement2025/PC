<?php
require_once __DIR__ . '/../db.php';

// Reset ID 8 to pending
$conn->query("UPDATE consultations SET status = 'pending' WHERE id = 8");

$url = 'http://localhost/CAP101/PC/API/consultations_api.php?action=decline_submission';
$data = json_encode(['id' => 8, 'reason' => 'Proposal test decline final.']);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$resp = curl_exec($ch);
curl_close($ch);

echo "HTTP API RESPONSE:\n" . $resp . "\n\n";

$res = $conn->query("SELECT id, title, user_name, status, admin_response FROM consultations WHERE id = 8");
echo "DB RECORD AFTER HTTP DECLINE:\n";
print_r($res->fetch_assoc());
