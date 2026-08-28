<?php
define('DB_SERVERS_CHECK', 1);
require_once __DIR__ . '/../admin-side/DATABASE/notifications.php';

$id = createNotification(0, '🏢 Test PHMS Citizen Feedback Received from Classmate', 'phms_feedback');
echo "Created notification ID: " . var_export($id, true) . "\n\n";

$notifs = getUserNotifications(0, 5);
foreach ($notifs as $n) {
    echo "[#{$n['id']}] [{$n['type']}] {$n['created_at']} -> {$n['message']}\n";
}
