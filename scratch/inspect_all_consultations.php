<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT * FROM consultations");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo "=== ID: {$r['id']} | Title: {$r['title']} ===\n";
        foreach ($r as $k => $v) {
            if ($v !== null && $v !== '') {
                echo "  $k: " . (strlen($v) > 100 ? substr($v, 0, 100) . "..." : $v) . "\n";
            }
        }
        echo "\n";
    }
}
