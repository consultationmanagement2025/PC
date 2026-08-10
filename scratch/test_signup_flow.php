<?php
require_once __DIR__ . '/../db.php';

echo "=== TESTING CITIZEN SIGNUP DATABASE RECORDING ===\n";

$testEmail = 'citizen_test_' . time() . '@valenzuela.gov.ph';
$fullname = 'Test Citizen ' . rand(100, 999);
$username = strtolower(explode('@', $testEmail)[0]);
$passwordHash = password_hash('password123', PASSWORD_DEFAULT);
$role = 'citizen';
$district = 'District 1';
$barangay = 'Dalandanan';

$stmt = $conn->prepare("INSERT INTO users (fullname, name, username, email, password, role, district, barangay, status, verification_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 'verified', NOW())");
if ($stmt) {
    $stmt->bind_param("ssssssss", $fullname, $fullname, $username, $testEmail, $passwordHash, $role, $district, $barangay);
    $res = $stmt->execute();
    if ($res) {
        $newId = $stmt->insert_id;
        echo "SUCCESS: Citizen account inserted cleanly with ID #{$newId}\n";
    } else {
        echo "FAILED execute: " . $stmt->error . "\n";
    }
    $stmt->close();
} else {
    echo "FAILED prepare: " . $conn->error . "\n";
}

// Check if user is returned in user management query
$checkQuery = $conn->query("SELECT id, fullname, name, username, email, role, status, verification_status, created_at FROM users WHERE email = '$testEmail'");
if ($checkQuery && $row = $checkQuery->fetch_assoc()) {
    echo "VERIFIED IN USER MANAGEMENT QUERY:\n";
    print_r($row);
} else {
    echo "FAILED TO FIND INSERTED USER IN QUERY!\n";
}
