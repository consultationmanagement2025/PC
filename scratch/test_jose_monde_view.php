<?php
session_start();
$_SESSION['user_id'] = 5; // Jose Monde
$_SESSION['role'] = 'resource person';
$_SESSION['fullname'] = 'Jose Monde';
$_SESSION['email'] = 'samplestaff02@gmail.com';

require_once __DIR__ . '/../db.php';

// Fetch profile
$stmt = $conn->prepare("SELECT expertise_areas, qualifications, department, phone, verification_status FROM users WHERE email = ?");
$stmt->bind_param('s', $_SESSION['email']);
$stmt->execute();
$userProfile = $stmt->get_result()->fetch_assoc();
$stmt->close();

$expertise_areas = $userProfile['expertise_areas'] ?? 'Justice & Human Rights, Livelihood, Trade, Commerce & Industry';
$department = $userProfile['department'] ?? 'Education & Technical Training Division';

echo "Jose Monde Profile:\n";
echo "Expertise Areas: $expertise_areas\n";
echo "Department: $department\n\n";

// Fetch consultations
$raw_consultations = [];
$cRes = $conn->query("SELECT * FROM consultations ORDER BY created_at DESC");
while ($row = $cRes->fetch_assoc()) {
    $raw_consultations[] = $row;
}

// Function from resource_person_dashboard.php
function isConsultationVisibleToExpert($cRow, $user_id, $user_role, $expertise_areas_str) {
    if (in_array(strtolower($user_role), ['admin', 'administrator', 'super admin', 'superadmin'])) {
        return true;
    }
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

$visible = [];
foreach ($raw_consultations as $cRow) {
    if (isConsultationVisibleToExpert($cRow, 5, 'resource person', $expertise_areas)) {
        $visible[] = $cRow;
    }
}

echo "Total Raw Consultations in DB: " . count($raw_consultations) . "\n";
echo "Total Visible Consultations for Jose Monde NOW: " . count($visible) . "\n";

if (empty($visible)) {
    echo "SUCCESS: 0 consultations are visible for Jose Monde because AI has NOT analyzed them (ai_analyzed = 0) and Admin has NOT forwarded them (forwarded_to_expert = 0)!\n";
} else {
    foreach ($visible as $v) {
        echo "- Visible: #{$v['id']} | Title: {$v['title']}\n";
    }
}
