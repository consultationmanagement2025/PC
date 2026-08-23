<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
}
if (file_exists(__DIR__ . '/../UTILS/unified_feedback_compilation_utils.php')) {
    require_once __DIR__ . '/../UTILS/unified_feedback_compilation_utils.php';
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'status_check';
$userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
$userName = $_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'System Admin';

if ($action === 'status_check') {
    ensureUnifiedFeedbackTables($conn);
    
    $pcmsUnprocessed = 0;
    $pcmsProcessed = 0;
    $r1 = $conn->query("SELECT SUM(CASE WHEN is_processed = 1 THEN 1 ELSE 0 END) as locked_cnt, SUM(CASE WHEN is_processed = 0 OR is_processed IS NULL THEN 1 ELSE 0 END) as unproc_cnt FROM feedback");
    if ($r1 && $row = $r1->fetch_assoc()) {
        $pcmsProcessed = (int)($row['locked_cnt'] ?? 0);
        $pcmsUnprocessed = (int)($row['unproc_cnt'] ?? 0);
    }

    $phmsUnprocessed = 0;
    $phmsProcessed = 0;
    $hqCheck = $conn->query("SHOW TABLES LIKE 'hearing_queue'");
    if ($hqCheck && $hqCheck->num_rows > 0) {
        $r2 = $conn->query("SELECT SUM(CASE WHEN is_processed = 1 THEN 1 ELSE 0 END) as locked_cnt, SUM(CASE WHEN is_processed = 0 OR is_processed IS NULL THEN 1 ELSE 0 END) as unproc_cnt FROM hearing_queue");
        if ($r2 && $row2 = $r2->fetch_assoc()) {
            $phmsProcessed = (int)($row2['locked_cnt'] ?? 0);
            $phmsUnprocessed = (int)($row2['unproc_cnt'] ?? 0);
        }
    }

    echo json_encode([
        'success' => true,
        'unprocessed_total' => ($pcmsUnprocessed + $phmsUnprocessed),
        'processed_total' => ($pcmsProcessed + $phmsProcessed),
        'pcms_unprocessed' => $pcmsUnprocessed,
        'phms_unprocessed' => $phmsUnprocessed,
        'pcms_processed' => $pcmsProcessed,
        'phms_processed' => $phmsProcessed
    ]);
    exit;
}

if ($action === 'compile_and_lock') {
    $result = compileUnifiedFeedback($conn, $userId, $userName);
    echo json_encode($result);
    exit;
}

if ($action === 'list_compilations') {
    ensureUnifiedFeedbackTables($conn);
    
    $stmt = $conn->query("SELECT id, merge_id, total_feedback_count, pdf_filename, pdf_path, compiled_by_name, created_at FROM unified_feedback_compilations ORDER BY created_at DESC LIMIT 50");
    $list = [];
    if ($stmt) {
        while ($r = $stmt->fetch_assoc()) {
            $r['pdf_url'] = 'download-document.php?file=' . urlencode($r['pdf_filename']);
            $list[] = $r;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $list
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Invalid action requested.'
]);
exit;
