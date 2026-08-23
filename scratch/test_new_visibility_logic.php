<?php
require_once __DIR__ . '/../db.php';

function isConsultationVisibleToExpertTest($cRow, $user_id, $user_role, $expertise_areas_str) {
    // 1. MUST BE AN APPROVED, PUBLICIZED CONSULTATION
    $status = strtolower(trim($cRow['status'] ?? ''));
    $disallowedStatuses = ['draft', 'pending', 'declined', 'rejected', 'archived', 'cancelled'];
    if (in_array($status, $disallowedStatuses, true)) {
        return false;
    }

    $allowedStatuses = ['active', 'closed', 'completed', 'endorsed', 'viewed', 'replied', 'scheduled'];
    if (!in_array($status, $allowedStatuses, true)) {
        return false;
    }

    // 2. MUST BE AI ANALYZED
    $aiAnalyzed = isset($cRow['ai_analyzed']) ? (int)$cRow['ai_analyzed'] : 0;
    if ($aiAnalyzed !== 1) {
        return false;
    }

    // 3. MUST BE EXPLICITLY FORWARDED / DISPATCHED BY ADMIN
    $assignedTo = (int)($cRow['assigned_to'] ?? 0);
    $forwarded = isset($cRow['forwarded_to_expert']) ? (int)$cRow['forwarded_to_expert'] : 0;
    $docStatus = strtolower(trim($cRow['document_status'] ?? ''));

    $isForwardedByAdmin = ($assignedTo === $user_id || $forwarded === 1 || in_array($docStatus, ['sent_to_expert', 'expert_annotated', 'admin_validated', 'forwarded_to_committee'], true));
    if (!$isForwardedByAdmin) {
        return false;
    }

    // 4. CATEGORY MATCH OR EXPLICIT ASSIGNMENT
    if ($assignedTo === $user_id) {
        return true;
    }

    $cCat = strtolower(trim($cRow['category'] ?? ''));
    if ($cCat === '') return false;

    $expList = array_values(array_filter(array_map('trim', explode(',', strtolower((string)$expertise_areas_str)))));
    if (empty($expList)) return false;

    foreach ($expList as $exp) {
        if ($exp === '') continue;
        if (strpos($cCat, $exp) !== false || strpos($exp, $cCat) !== false) {
            return true;
        }
        $expWords = preg_split('/[\s\/&,-]+/', $exp);
        foreach ($expWords as $w) {
            $w = trim($w);
            if (strlen($w) >= 3 && strpos($cCat, $w) !== false) {
                return true;
            }
        }
    }

    return false;
}

$raw_consultations = [];
$cRes = $conn->query("SELECT * FROM consultations");
while ($r = $cRes->fetch_assoc()) $raw_consultations[] = $r;

$expertise = "Health & Sanitation, Infrastructure, Public Utilities & Facilities, General Governance, Environment";

echo "=== VISIBILITY TEST RESULTS ===\n";
foreach ($raw_consultations as $c) {
    $visible = isConsultationVisibleToExpertTest($c, 2, 'resource_person', $expertise);
    echo sprintf("ID: %-2d | Title: %-40s | Status: %-8s | Type: %-5s | AI: %d | Fwd: %d => VISIBLE: %s\n",
        $c['id'], substr($c['title'], 0, 40), $c['status'], $c['type'], $c['ai_analyzed'], $c['forwarded_to_expert'], $visible ? "YES" : "NO (Filtered)");
}
