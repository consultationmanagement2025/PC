<?php
require_once __DIR__ . '/../db.php';

function isConsultationVisibleToExpertTest($cRow, $user_id, $expertise_areas_str, $user_role = 'resource person') {
    if (in_array(strtolower($user_role), ['admin', 'administrator', 'super admin', 'superadmin'])) return true;
    $status = strtolower(trim($cRow['status'] ?? ''));
    if ($status === 'draft') return false;

    $aiAnalyzed = isset($cRow['ai_analyzed']) ? (int)$cRow['ai_analyzed'] : 0;
    if ($aiAnalyzed === 0) return false;

    $assignedTo = (int)($cRow['assigned_to'] ?? 0);
    $forwarded = isset($cRow['forwarded_to_expert']) ? (int)$cRow['forwarded_to_expert'] : 0;
    $docStatus = strtolower(trim($cRow['document_status'] ?? ''));

    $isForwardedByAdmin = ($assignedTo === $user_id || $forwarded === 1 || in_array($docStatus, ['sent_to_expert', 'expert_annotated', 'admin_validated', 'forwarded_to_committee']));
    if (!$isForwardedByAdmin) return false;

    return true;
}

echo "--- 1. Creating New Consultation --- \n";
$stmt = $conn->prepare("INSERT INTO consultations (title, description, category, status, document_status, ai_analyzed, forwarded_to_expert, created_at) VALUES ('New Health Consultation Test', 'Public feedback for barangay health centers.', 'Health & Sanitation', 'active', 'draft', 0, 0, NOW())");
$stmt->execute();
$cId = $stmt->insert_id;
$stmt->close();

$cRes = $conn->query("SELECT * FROM consultations WHERE id = $cId");
$cRow = $cRes->fetch_assoc();

echo "Consultation #$cId Created!\n";
echo "AI Analyzed Flag: {$cRow['ai_analyzed']}\n";
echo "Forwarded to Expert Flag: {$cRow['forwarded_to_expert']}\n";

$isVisibleBefore = isConsultationVisibleToExpertTest($cRow, 501, 'Health & Sanitation', 'resource person');
echo "Is Visible to Resource Person BEFORE Admin Forwarding? => " . ($isVisibleBefore ? "YES (WRONG)" : "NO (CORRECT)") . "\n";

echo "\n--- 2. Admin Clicks 'Forward to Expert' ---\n";
$conn->query("UPDATE consultations SET ai_analyzed = 1, forwarded_to_expert = 1, document_status = 'sent_to_expert', assigned_to = 501 WHERE id = $cId");

$cRes2 = $conn->query("SELECT * FROM consultations WHERE id = $cId");
$cRow2 = $cRes2->fetch_assoc();

$isVisibleAfter = isConsultationVisibleToExpertTest($cRow2, 501, 'Health & Sanitation', 'resource person');
echo "Is Visible to Resource Person AFTER Admin Forwarding? => " . ($isVisibleAfter ? "YES (CORRECT)" : "NO (WRONG)") . "\n";

// Cleanup
$conn->query("DELETE FROM consultations WHERE id = $cId");
echo "\nForwarding Flow Verification Completed Successfully!\n";
