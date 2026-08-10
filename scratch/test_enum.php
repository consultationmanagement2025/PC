<?php
require_once __DIR__ . '/../db.php';

echo "Testing INSERT into users with 'approved' vs 'verified'...\n";
$testEmail = 'enumtest_' . time() . '@example.com';

// Attempt 1: inserting 'approved'
$stmt1 = $conn->prepare("INSERT INTO users (fullname, username, email, password, role, status, verification_status, created_at) VALUES ('Test Enum', 'testenum', ?, 'pass', 'citizen', 'active', 'approved', NOW())");
if ($stmt1) {
    $stmt1->bind_param("s", $testEmail);
    $res1 = $stmt1->execute();
    echo "Result with 'approved': " . ($res1 ? "SUCCESS" : ("FAILED: " . $stmt1->error)) . "\n";
    $stmt1->close();
} else {
    echo "Prepare failed for 'approved': " . $conn->error . "\n";
}

// Clean up if inserted
$conn->query("DELETE FROM users WHERE email = '$testEmail'");
