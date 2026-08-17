<?php
require_once 'db.php';
$conn = dbConnect();

echo "=== ALL CONSULTATIONS IN DB ===\n";
$res = $conn->query("SELECT id, title, category, description, status, created_at FROM consultations ORDER BY id DESC");
while ($r = $res->fetch_assoc()) {
    echo "ID: " . $r['id'] . " | Title: " . $r['title'] . " | Category: " . $r['category'] . " | Status: " . $r['status'] . "\n";
    echo "Description: " . substr($r['description'], 0, 100) . "...\n";
    echo "-------------------\n";
}
