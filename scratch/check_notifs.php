<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/notifications.php';

initializeNotificationsTable();
seedNotificationsIfEmpty(true); // force re-seed fresh clean notifications!

$res = $conn->query("SELECT * FROM notifications ORDER BY id DESC");
while ($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Type: " . $row['type'] . " | Message: " . $row['message'] . "\n";
}
