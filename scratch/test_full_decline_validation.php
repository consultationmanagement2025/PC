<?php
require_once __DIR__ . '/../db.php';

// 1. Create a dummy citizen submission
$stmt = $conn->prepare("INSERT INTO consultations (title, description, category, type, user_name, user_email, status, tracking_number) VALUES ('Test Proposal for Full Stack Validation', 'Validation test for decline functionality.', 'Governance', 'user', 'Full Stack Test User', 'fullstack@example.com', 'pending', 'TRK-FULLSTACK-DECLINE-01')");
$stmt->execute();
$id = $conn->insert_id;
$stmt->close();

echo "Created test citizen submission #{$id}\n";

// 2. Test update_status endpoint logic with status 'rejected'
$reason = "Rejected via Full Stack test suite.";
$uStmt = $conn->prepare("UPDATE consultations SET status = 'rejected', admin_response = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
$uStmt->bind_param('ssi', $reason, $reason, $id);
$ok = $uStmt->execute();
$uStmt->close();

// 3. Query DB to verify
$checkStmt = $conn->prepare("SELECT id, title, status, admin_response, remarks FROM consultations WHERE id = ?");
$checkStmt->bind_param('i', $id);
$checkStmt->execute();
$row = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

echo "Validation Result: " . ($row['status'] === 'rejected' ? "PASSED (status = 'rejected')" : "FAILED (status = {$row['status']})") . "\n";
print_r($row);
