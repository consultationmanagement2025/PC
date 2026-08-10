<?php
require_once __DIR__ . '/../DATABASE/feedback.php';
require_once __DIR__ . '/../db.php';

echo "--- 1. Initializing hearing_queue table schema ---\n";
initializeHearingQueueTable();

echo "\n--- 2. Simulating incoming PHMS Webhook event ---\n";
$phmsId = 999101;
$title = "Test Public Hearing on Barangay Healthcare Services";
$jsonStr = json_encode([
    'phms_hearing_id' => $phmsId,
    'hearing_title' => $title,
    'hearing_date' => '2026-08-10 14:00:00',
    'citizen_responses' => [
        ['citizen_name' => 'John Doe', 'testimony' => 'Strong support for barangay clinic hours expansion.', 'rating' => 5],
        ['citizen_name' => 'Jane Smith', 'testimony' => 'Requesting additional medicine stock.', 'rating' => 4]
    ]
]);

$stmt = $conn->prepare("INSERT INTO hearing_queue (phms_hearing_id, full_name, email, external_ref, source_system, payload_json, approval_status, status) VALUES (?, ?, 'phms@valenzuela.gov.ph', 'PHMS-TEST-999', 'PHMS', ?, 'pending', 'pending_approval') ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), payload_json=VALUES(payload_json), approval_status='pending', status='pending_approval'");
if (!$stmt) {
    echo "Prepare failed: " . $conn->error . "\n";
    exit(1);
}
$stmt->bind_param("iss", $phmsId, $title, $jsonStr);
if (!$stmt->execute()) {
    echo "Execute failed: " . $stmt->error . "\n";
    exit(1);
}
$stmt->close();
echo "Inserted test PHMS event with approval_status = 'pending'\n";

echo "\n--- 3. Checking Pending Approvals List ---\n";
$pending = getPendingPhmsApprovals();
echo "Found " . count($pending) . " pending item(s) waiting for approval.\n";
foreach ($pending as $item) {
    echo "- Queue #{$item['queue_id']} | PHMS ID #{$item['phms_hearing_id']} | Title: {$item['full_name']} | Status: {$item['approval_status']}\n";
}

echo "\n--- 4. Approving Test Package ---\n";
$ok = approvePhmsIngestion($phmsId);
echo "Approval result: " . ($ok ? "SUCCESS" : "FAILED") . "\n";

echo "\n--- 5. Verifying Status After Approval ---\n";
$chk = $conn->query("SELECT queue_id, phms_hearing_id, full_name, approval_status, status FROM hearing_queue WHERE phms_hearing_id = {$phmsId}");
$row = $chk ? $chk->fetch_assoc() : null;
if ($row) {
    echo "Current Record in DB: Queue #{$row['queue_id']} | Approval Status: {$row['approval_status']} | Status: {$row['status']}\n";
}

// Cleanup test item
$conn->query("DELETE FROM hearing_queue WHERE phms_hearing_id = {$phmsId}");
echo "\nTest completed successfully.\n";
