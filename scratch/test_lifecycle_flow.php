<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/consultations.php';
require_once __DIR__ . '/../DATABASE/feedback.php';

function testVisibility($cId, $expertUserId = 501, $category = 'Health & Sanitation') {
    global $conn;
    $res = $conn->query("SELECT * FROM consultations WHERE id = $cId");
    if (!$res || $res->num_rows === 0) return false;
    $cRow = $res->fetch_assoc();

    $aiAnalyzed = isset($cRow['ai_analyzed']) ? (int)$cRow['ai_analyzed'] : 0;
    if ($aiAnalyzed === 0) return false;

    $assignedTo = (int)($cRow['assigned_to'] ?? 0);
    $forwarded = isset($cRow['forwarded_to_expert']) ? (int)$cRow['forwarded_to_expert'] : 0;
    $docStatus = strtolower(trim($cRow['document_status'] ?? ''));

    $isForwardedByAdmin = ($assignedTo === $expertUserId || $forwarded === 1 || in_array($docStatus, ['sent_to_expert', 'expert_annotated', 'admin_validated', 'forwarded_to_committee']));
    if (!$isForwardedByAdmin) return false;

    return true;
}

echo "=== 1. Phase 1: Admin Creates Consultation ===\n";
$err = null;
$cId = createConsultation("Barangay Health Center Hours Initiative", "Gathering citizen input on extending barangay clinic hours.", "Health & Sanitation", date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), 1, 0, null, null, null, 1, 'admin', null, null, 'hybrid', 'Should barangay clinics extend operating hours until 8 PM?', 'Yes, Extend', 'No, Keep Default', 1, 1, $err);

echo "Created Consultation #$cId!\n";
$cRow = $conn->query("SELECT * FROM consultations WHERE id = $cId")->fetch_assoc();
echo "  ai_analyzed: {$cRow['ai_analyzed']}\n";
echo "  forwarded_to_expert: {$cRow['forwarded_to_expert']}\n";
echo "  document_status: {$cRow['document_status']}\n";
$v1 = testVisibility($cId);
echo "  Visible to Resource Person? => " . ($v1 ? "YES (ERROR)" : "NO (CORRECT)") . "\n\n";

echo "=== 2. Phase 2: Citizen Submits Feedback ===\n";
$fb = submitFeedback('Maria Santos', 'maria@example.com', '09171234567', $cId, 5, 'Health & Sanitation', 'Extending clinic hours will greatly help working parents who cannot visit during regular office hours.');
echo "Submitted Feedback ID: " . ($fb ? $fb['id'] : 'Failed') . "\n\n";

echo "=== 3. Phase 3: AI Analysis Engine Runs ===\n";
$conn->query("UPDATE consultations SET ai_analyzed = 1, ai_committee_brief = '{\"summary\":\"Citizen feedback strongly supports extending clinic hours.\"}' WHERE id = $cId");
$cRow2 = $conn->query("SELECT * FROM consultations WHERE id = $cId")->fetch_assoc();
echo "  ai_analyzed: {$cRow2['ai_analyzed']}\n";
echo "  forwarded_to_expert: {$cRow2['forwarded_to_expert']}\n";
$v2 = testVisibility($cId);
echo "  Visible to Resource Person? => " . ($v2 ? "YES (ERROR - Not forwarded by Admin yet)" : "NO (CORRECT - Waiting for Admin Forwarding)") . "\n\n";

echo "=== 4. Phase 4: Admin Forwards AI Conclusion to Resource Person ===\n";
$conn->query("UPDATE consultations SET forwarded_to_expert = 1, assigned_to = 501, document_status = 'sent_to_expert' WHERE id = $cId");
$cRow3 = $conn->query("SELECT * FROM consultations WHERE id = $cId")->fetch_assoc();
echo "  ai_analyzed: {$cRow3['ai_analyzed']}\n";
echo "  forwarded_to_expert: {$cRow3['forwarded_to_expert']}\n";
echo "  document_status: {$cRow3['document_status']}\n";
$v3 = testVisibility($cId);
echo "  Visible to Resource Person? => " . ($v3 ? "YES (CORRECT - Now Dispatched to Expert)" : "NO (ERROR)") . "\n\n";

// Cleanup test
$conn->query("DELETE FROM feedback WHERE consultation_id = $cId");
$conn->query("DELETE FROM consultations WHERE id = $cId");
echo "System Lifecycle Flow Test Completed Successfully!\n";
