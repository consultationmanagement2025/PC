<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../admin-side/DATABASE/notifications.php';

echo "=== LATEST 5 NOTIFICATIONS ===\n";
$notifs = getUserNotifications(0, 5);
foreach ($notifs as $n) {
    echo "[ID {$n['id']}] Type: {$n['type']} | Date: {$n['created_at']} | Message: {$n['message']}\n";
}

echo "\n=== LATEST 5 HEARING QUEUE ITEMS ===\n";
$res = $conn->query("SELECT queue_id, phms_hearing_id, full_name, approval_status, created_at FROM hearing_queue ORDER BY queue_id DESC LIMIT 5");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo "[ID {$r['queue_id']}] Hearing #{$r['phms_hearing_id']} | Name: {$r['full_name']} | Approval: {$r['approval_status']} | Created: {$r['created_at']}\n";
    }
}
