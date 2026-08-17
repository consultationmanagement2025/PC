<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT id, title, category, response_mode, status, type, created_at, end_date FROM consultations ORDER BY id DESC");
echo "TOTAL ROWS: " . ($res ? $res->num_rows : 0) . "\n\n";

while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']} | Mode: {$r['response_mode']} | Status: {$r['status']} | Type: {$r['type']} | EndDate: {$r['end_date']} | Title: {$r['title']}\n";
}
