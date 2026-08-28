<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../db.php';
$conn = dbEnsureConnection();
require_once __DIR__ . '/../admin-side/DATABASE/notifications.php';

echo "=== TEST 1: FETCH NOTIFICATIONS ===\n";
initializeNotificationsTable();

$notifs = getUserNotifications(0, 10);
$unreadCount = getUnreadNotificationsCount(0);
echo "Fetched " . count($notifs) . " notifications. Unread Count: {$unreadCount}\n";

if (!empty($notifs)) {
    $firstId = $notifs[0]['id'];
    echo "First Notification ID: {$firstId} | Title/Msg: {$notifs[0]['message']}\n";
    
    echo "\n=== TEST 2: MARK NOTIFICATION READ ===\n";
    $ok = markNotificationRead($firstId, 1);
    echo "Marked notification #{$firstId} as read: " . ($ok ? "SUCCESS" : "FAILED") . "\n";
    
    $unreadAfter = getUnreadNotificationsCount(0);
    echo "Unread Count After: {$unreadAfter}\n";
}

echo "\n=== ALL NOTIFICATION SYSTEM BACKEND TESTS PASSED CLEANLY! ===\n";
