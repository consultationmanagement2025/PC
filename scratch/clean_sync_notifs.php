<?php
define('DB_SERVERS_CHECK', 1);
require_once __DIR__ . '/../db.php';

$sql = "DELETE FROM notifications WHERE message LIKE '%PHMS Integration Sync%' OR message LIKE '%ingested into Public Feedback Queue%'";
$res = $conn->query($sql);
if ($res) {
    $affected = $conn->affected_rows;
    echo "Successfully deleted {$affected} repetitive PHMS sync notifications from database.\n";
} else {
    echo "Error cleaning notifications: " . $conn->error . "\n";
}
?>