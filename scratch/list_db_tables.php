<?php
require_once __DIR__ . '/../DATABASE/user-logs.php';

echo "=== LISTING ALL TABLES IN cons_pc_db DATABASE ===\n";

if (isset($conn) && $conn) {
    $res = $conn->query("SHOW TABLES");
    if ($res) {
        $rows = $res->fetch_all();
        foreach ($rows as $r) {
            echo "Table: " . $r[0] . "\n";
        }
    }
}
