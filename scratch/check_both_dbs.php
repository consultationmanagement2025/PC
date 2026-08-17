<?php
require_once 'db.php';
$conn = dbConnect();

foreach (['pc_db', 'cons_pc_db'] as $dbName) {
    echo "=== DB: $dbName ===\n";
    $conn->select_db($dbName);
    $res = $conn->query("SELECT id, title, category, status, created_at FROM consultations ORDER BY id ASC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            echo "  ID: {$r['id']} | Title: {$r['title']} | Category: {$r['category']} | Status: {$r['status']} | Created: {$r['created_at']}\n";
        }
    } else {
        echo "  Table consultations not found or empty.\n";
    }
}
