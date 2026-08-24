<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/feedback.php';

$pending = getPendingPhmsApprovals();
echo "getPendingPhmsApprovals() count: " . count($pending) . "\n";

$res = $conn->query("SELECT COUNT(*) as total FROM hearing_queue");
$row = $res->fetch_assoc();
echo "hearing_queue TOTAL rows: " . $row['total'] . "\n";

$res2 = $conn->query("SELECT approval_status, status, COUNT(*) as cnt FROM hearing_queue GROUP BY approval_status, status");
while ($r = $res2->fetch_assoc()) {
    echo "approval_status: [" . ($r['approval_status'] ?? 'NULL') . "] | status: [" . ($r['status'] ?? 'NULL') . "] => " . $r['cnt'] . "\n";
}
