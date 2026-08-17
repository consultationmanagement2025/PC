<?php
require_once __DIR__ . '/../db.php';

// 1. Create a dummy citizen submission
$stmt = $conn->prepare("INSERT INTO consultations (title, description, category, type, user_name, user_email, status, tracking_number) VALUES ('Proposed Community Park Lighting Improvement', 'Requesting LED streetlights in Barangay Malinta park.', 'Infrastructure', 'user', 'Juan Dela Cruz', 'juan@example.com', 'pending', 'TRK-TEST-DECLINE-01')");
$stmt->execute();
$id = $conn->insert_id;
$stmt->close();

echo "Created test citizen submission #{$id}\n";

// 2. Test decline_submission API call logic
$reason = "Submission does not meet LGU public consultation requirements.";
$uStmt = $conn->prepare("UPDATE consultations SET status = 'rejected', admin_response = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
$uStmt->bind_param('ssi', $reason, $reason, $id);
$ok = $uStmt->execute();
$uStmt->close();

// 3. Verify resulting DB state
$vStmt = $conn->prepare("SELECT id, title, status, admin_response, remarks FROM consultations WHERE id = ?");
$vStmt->bind_param('i', $id);
$vStmt->execute();
$row = $vStmt->get_result()->fetch_assoc();
$vStmt->close();

echo "Decline API Update Result: " . ($ok ? "SUCCESS" : "FAILED") . "\n";
echo "Updated Record State:\n";
print_r($row);
