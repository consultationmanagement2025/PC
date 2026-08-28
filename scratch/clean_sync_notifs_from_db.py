import subprocess

php_code = """<?php
define('DB_SERVERS_CHECK', 1);
require_once __DIR__ . '/../db.php';

$sql = "DELETE FROM notifications WHERE message LIKE '%PHMS Integration Sync%' OR message LIKE '%ingested into Public Feedback Queue%'";
$res = $conn->query($sql);
if ($res) {
    $affected = $conn->affected_rows;
    echo "Successfully deleted {$affected} repetitive PHMS sync notifications from database.\\n";
} else {
    echo "Error cleaning notifications: " . $conn->error . "\\n";
}
?>"""

with open(r'c:\xampp\htdocs\CAP101\PC\scratch\clean_sync_notifs.php', 'w', encoding='utf-8') as f:
    f.write(php_code)

print("Created clean_sync_notifs.php")
