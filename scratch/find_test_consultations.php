<?php
require_once __DIR__ . '/../db.php';

echo "=== ALL CONSULTATIONS WITH type = 'user' OR title LIKE '%test%' ===\n";
$res = $conn->query("SELECT * FROM consultations WHERE type = 'user' OR title LIKE '%test%'");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo json_encode($r, JSON_PRETTY_PRINT) . "\n";
    }
}
