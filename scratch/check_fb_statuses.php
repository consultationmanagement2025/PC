<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("SELECT status, COUNT(*) FROM feedback GROUP BY status");
while ($r = $res->fetch_assoc()) {
    echo "Status: [{$r['status']}] => Count: {$r['COUNT(*)']}\n";
}
