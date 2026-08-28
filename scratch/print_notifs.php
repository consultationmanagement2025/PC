<?php
define('DB_SERVERS_CHECK', 1);
require_once __DIR__ . '/../admin-side/DATABASE/notifications.php';

$notifs = getUserNotifications(0, 20);
foreach ($notifs as $n) {
    echo "[#{$n['id']}] [{$n['type']}] {$n['created_at']} -> {$n['message']}\n";
}
