<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../admin-side/DATABASE/notifications.php';
require_once __DIR__ . '/../admin-side/DATABASE/feedback.php';

echo "=== TEST START ===\n";
initializeHearingQueueTable();

$res = $conn->query("SELECT queue_id, phms_hearing_id, full_name, approval_status, is_newly_approved FROM hearing_queue LIMIT 5");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo "Queue #{$r['queue_id']} | Hearing #{$r['phms_hearing_id']} | Title: {$r['full_name']} | Approval: {$r['approval_status']} | RedDot: {$r['is_newly_approved']}\n";
    }
}
echo "=== TEST END ===\n";
