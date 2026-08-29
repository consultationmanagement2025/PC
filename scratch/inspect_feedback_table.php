<?php
require_once __DIR__ . '/../DATABASE/user-logs.php';

echo "=== INSPECTING MYSQL 'feedback' TABLE ===\n";

if (isset($conn) && $conn) {
    $res = $conn->query("SELECT * FROM feedback ORDER BY id DESC");
    if ($res) {
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        echo "Total rows in feedback table: " . count($rows) . "\n\n";
        foreach ($rows as $r) {
            print_r($r);
        }
    }
}
