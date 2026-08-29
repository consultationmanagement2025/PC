<?php
require_once __DIR__ . '/../DATABASE/user-logs.php';

echo "=== INSPECTING REAL MYSQL DATABASE FEEDBACK RECORDS ===\n";

if (isset($conn) && $conn) {
    $res = $conn->query("SELECT * FROM consultation_feedback ORDER BY id DESC");
    if ($res) {
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        echo "Total real feedback records in MySQL: " . count($rows) . "\n\n";
        foreach ($rows as $r) {
            print_r($r);
        }
    } else {
        echo "Query failed: " . $conn->error . "\n";
    }
} else {
    echo "No database connection.\n";
}
