<?php
require_once 'db.php';
$conn = dbConnect();

$res = $conn->query("SELECT id, title, category, status, type, created_at FROM consultations ORDER BY id ASC");
echo "TOTAL ROWS: " . $res->num_rows . "\n\n";
while ($r = $res->fetch_assoc()) {
    echo "ID: " . $r['id'] . " | Title: " . $r['title'] . " | Category: " . $r['category'] . " | Status: " . $r['status'] . " | Type: " . $r['type'] . "\n";
}
