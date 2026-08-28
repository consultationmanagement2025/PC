<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/feedback.php';

// 1. Insert or ensure test consultation with tracking_number = 'ORD-2026-011'
$ref = 'ORD-2026-011';
$title = 'test 1';

$cid = 0;
$stmt = $conn->prepare("SELECT id FROM consultations WHERE tracking_number = ? LIMIT 1");
$stmt->bind_param('s', $ref);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row) {
    $cid = (int)$row['id'];
} else {
    $iStmt = $conn->prepare("INSERT INTO consultations (title, description, category, status, type, tracking_number, external_ref, source_system, created_at) VALUES (?, 'Test ordinance consultation', 'Infrastructure', 'active', 'ordinance', ?, ?, 'ORTS', NOW())");
    $iStmt->bind_param('sss', $title, $ref, $ref);
    $iStmt->execute();
    $cid = $iStmt->insert_id;
    $iStmt->close();
}

echo "Test Consultation ID: #{$cid} | Tracking Number: {$ref}\n";

// 2. Submit citizen feedback on this consultation
echo "Submitting citizen feedback...\n";
$fbResult = submitFeedback(
    'Juan Dela Cruz',
    'juan.delacruz@valenzuela.ph',
    '09171234567',
    $cid,
    5,
    'Support',
    'Strongly support ordinance ORD-2026-011. Excellent initiative for community safety.',
    1
);

echo "Feedback Submission Result:\n";
print_r($fbResult);
