<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT approval_status, status, COUNT(*) as cnt FROM hearing_queue GROUP BY approval_status, status");
echo "=== HEARING QUEUE SUMMARY ===\n";
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "Approval Status: [" . ($row['approval_status'] ?? 'NULL') . "] | Status: [" . ($row['status'] ?? 'NULL') . "] => Count: " . $row['cnt'] . "\n";
    }
} else {
    echo "Query error: " . $conn->error . "\n";
}

$res2 = $conn->query("SELECT queue_id, phms_hearing_id, consultation_id, approval_status, status, created_at FROM hearing_queue ORDER BY queue_id DESC LIMIT 10");
echo "\n=== LAST 10 ROWS ===\n";
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        print_r($row);
    }
}
