<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("SHOW COLUMNS FROM feedback");
while ($r = $res->fetch_assoc()) {
    echo "{$r['Field']} ({$r['Type']})\n";
}
