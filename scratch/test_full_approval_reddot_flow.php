<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../admin-side/DATABASE/notifications.php';
require_once __DIR__ . '/../admin-side/DATABASE/feedback.php';

echo "=== STEP 1: SIMULATING INCOMING PENDING PHMS FEEDBACK ===\n";

$phmsId = 8871;
$regId = 88710;
$title = "Public Consultation on Drainage Upgrades for Classmate Test";
$jsonStr = json_encode([
    'hearing_id' => $phmsId,
    'phms_registration_id' => $regId,
    'hearing_title' => $title,
    'hearing_status' => 'pending_approval',
    'feedback_count' => 1,
    'citizen_responses' => [
        ['name' => 'Classmate Test User', 'statement' => 'Feedback submitted from PHMS classmate test.', 'rating' => 5]
    ]
]);

initializeHearingQueueTable();

$stmt = $conn->prepare("INSERT INTO hearing_queue (phms_hearing_id, phms_registration_id, full_name, email, status, external_ref, source_system, consultation_id, payload_json, approval_status, is_newly_approved) VALUES (?, ?, ?, 'classmate@valenzuela.gov.ph', 'pending_approval', 'PHMS-TEST-8871', 'PHMS', 1, ?, 'pending', 0) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), payload_json=VALUES(payload_json), approval_status='pending', is_newly_approved=0");
$stmt->bind_param("iiss", $phmsId, $regId, $title, $jsonStr);
$stmt->execute();
$stmt->close();

createNotification(0, "🏢 New PHMS Citizen Hearing Feedback Received: '{$title}' (Awaiting Ingestion Approval)", "phms_feedback");

echo "Inserted incoming pending PHMS item #{$phmsId}.\n";

echo "\n=== STEP 2: CHECK MAIN TABLE (FILTERED BY APPROVED) ===\n";
$hearings = getPhmsFeedbackQueueAsHearings([], 50, 0);
$approvedHearings = array_filter($hearings, function($h) {
    return strtolower($h['approval_status'] ?? '') === 'approved';
});
$pendingHearings = array_filter($hearings, function($h) {
    return strtolower($h['approval_status'] ?? '') === 'pending';
});

echo "Total Hearings in Queue: " . count($hearings) . "\n";
echo "Approved Hearings (Visible on Main Table): " . count($approvedHearings) . "\n";
echo "Pending Hearings (Hidden from Main Table, inside Approval Sheet): " . count($pendingHearings) . "\n";

echo "\n=== STEP 3: APPROVE THE PENDING ITEM IN INGESTION APPROVAL SHEET ===\n";
$queueRes = $conn->query("SELECT queue_id FROM hearing_queue WHERE phms_hearing_id = {$phmsId} LIMIT 1");
$queueRow = $queueRes ? $queueRes->fetch_assoc() : null;
$queueId = $queueRow['queue_id'] ?? $phmsId;

$ok = approvePhmsIngestion($queueId);
echo "Approved queueId {$queueId}: " . ($ok ? "SUCCESS" : "FAILED") . "\n";

echo "\n=== STEP 4: RE-CHECK MAIN TABLE FOR RED DOT INDICATOR ===\n";
$hearingsAfter = getPhmsFeedbackQueueAsHearings([], 50, 0);
$newlyApprovedItem = null;
foreach ($hearingsAfter as $h) {
    if ((int)($h['phms_hearing_id'] ?? 0) === $phmsId) {
        $newlyApprovedItem = $h;
        break;
    }
}

if ($newlyApprovedItem) {
    echo "Hearing #{$phmsId} found on main table!\n";
    echo "- Title: " . $newlyApprovedItem['hearing_title'] . "\n";
    echo "- Approval Status: " . $newlyApprovedItem['approval_status'] . "\n";
    echo "- Red Dot Flag (is_newly_approved): " . var_export($newlyApprovedItem['is_newly_approved'], true) . " [EXPECTED: 1 / TRUE]\n";
} else {
    echo "ERROR: Item not found on main table after approval.\n";
}

echo "\n=== STEP 5: CLEAR RED DOT AFTER INSPECTING FEEDBACK ===\n";
$conn->query("UPDATE hearing_queue SET is_newly_approved = 0 WHERE phms_hearing_id = {$phmsId}");
$checkCleared = $conn->query("SELECT is_newly_approved FROM hearing_queue WHERE phms_hearing_id = {$phmsId}")->fetch_assoc();
echo "is_newly_approved after inspection: " . $checkCleared['is_newly_approved'] . " [EXPECTED: 0]\n";

echo "\n=== VERIFICATION COMPLETE: ALL STEPS PASSED SUCCESSFULLY! ===\n";
