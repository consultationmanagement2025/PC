<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/../db.php';
    require_once __DIR__ . '/../DATABASE/feedback.php';

    $ref = 'ORD-2026-011';
    $title = 'test 1';

    $cid = 0;
    $stmt = $conn->prepare("SELECT id FROM consultations WHERE tracking_number = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $ref);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $cid = (int)$row['id'];
        }
    }

    if ($cid <= 0) {
        $iStmt = $conn->prepare("INSERT INTO consultations (title, description, category, status, type, tracking_number, external_ref, source_system, created_at) VALUES (?, 'Test ordinance consultation', 'Infrastructure', 'active', 'ordinance', ?, ?, 'ORTS', NOW())");
        if ($iStmt) {
            $iStmt->bind_param('sss', $title, $ref, $ref);
            $iStmt->execute();
            $cid = $iStmt->insert_id;
            $iStmt->close();
        }
    }

    echo "Consultation ID: #$cid\n";

    $fbResult = submitFeedback(
        'Juan Dela Cruz',
        'juan.delacruz@valenzuela.ph',
        '09171234567',
        $cid,
        5,
        'Support',
        'Strongly support ordinance ORD-2026-011. Community safety initiative.',
        1
    );

    echo "Result: ";
    print_r($fbResult);
} catch (Throwable $t) {
    echo "ERROR: " . $t->getMessage() . "\n" . $t->getTraceAsString() . "\n";
}
