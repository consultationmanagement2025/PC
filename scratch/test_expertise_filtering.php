<?php
session_start();
$_SESSION['role'] = 'resource person';
$_SESSION['user_id'] = 501;

require_once __DIR__ . '/../db.php';

// Expert Profile with registered expertise: "Health, Sanitation"
$expert_id = 501;
$expert_role = 'resource person';
$expert_areas = "Health, Sanitation";

// Include visibility filter logic
function isConsultationVisibleToExpert($cRow, $user_id, $user_role, $expertise_areas_str) {
    if (in_array(strtolower($user_role), ['admin', 'administrator', 'super admin', 'superadmin'])) {
        return true;
    }

    $status = strtolower(trim($cRow['status'] ?? ''));
    if ($status === 'draft') {
        return false;
    }

    $aiAnalyzed = isset($cRow['ai_analyzed']) ? (int)$cRow['ai_analyzed'] : 1;
    if ($aiAnalyzed === 0) {
        return false;
    }

    $assignedTo = (int)($cRow['assigned_to'] ?? 0);
    $forwarded = isset($cRow['forwarded_to_expert']) ? (int)$cRow['forwarded_to_expert'] : 1;
    $docStatus = strtolower(trim($cRow['document_status'] ?? ''));

    $isForwardedByAdmin = ($assignedTo > 0 || $forwarded === 1 || in_array($docStatus, ['sent_to_expert', 'expert_annotated', 'admin_validated', 'forwarded_to_committee']));
    if (!$isForwardedByAdmin) {
        return false;
    }

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

// Test cases
$testConsultations = [
    [
        'title' => 'City Hospital Sanitation Rules',
        'category' => 'Health & Sanitation',
        'status' => 'active',
        'ai_analyzed' => 1,
        'forwarded_to_expert' => 1,
        'assigned_to' => 0
    ],
    [
        'title' => 'Road Drainage Construction',
        'category' => 'Infrastructure',
        'status' => 'active',
        'ai_analyzed' => 1,
        'forwarded_to_expert' => 1,
        'assigned_to' => 0
    ],
    [
        'title' => 'Vaccination Program Draft',
        'category' => 'Health',
        'status' => 'draft', // Not AI analyzed/draft
        'ai_analyzed' => 0,
        'forwarded_to_expert' => 0,
        'assigned_to' => 0
    ]
];

echo "--- EXPERTISE & AI-FORWARD VISIBILITY TEST RESULTS ---\n";
echo "Expert Registered Expertise: '$expert_areas'\n\n";

foreach ($testConsultations as $idx => $c) {
    $visible = isConsultationVisibleToExpert($c, $expert_id, $expert_role, $expert_areas);
    $statusStr = $visible ? "✅ VISIBLE" : "❌ HIDDEN";
    echo "Item #" . ($idx + 1) . ": '{$c['title']}' (Category: '{$c['category']}', Status: '{$c['status']}', AI Analyzed: {$c['ai_analyzed']}, Forwarded: {$c['forwarded_to_expert']})\n";
    echo "  Result: $statusStr\n\n";
}
