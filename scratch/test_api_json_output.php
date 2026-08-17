<?php
// Simulate HTTP POST request to API/consultations_api.php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'superadmin';

$_POST = [];
$inputData = json_encode(['id' => 1, 'status' => 'rejected', 'reason' => 'Test reason']);

// Use cURL or file_get_contents to test API/consultations_api.php via http
$ch = curl_init('http://localhost/CAP101/PC/API/consultations_api.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $inputData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
// Include session cookie if possible or check output
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP CODE: {$httpCode}\n";
echo "RESPONSE:\n{$resp}\n";
