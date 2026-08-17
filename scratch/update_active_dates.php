<?php
require_once __DIR__ . '/../db.php';

$conn->query("UPDATE consultations SET end_date = '2026-12-31 23:59:59' WHERE status = 'active' OR status IS NULL OR status = ''");

echo "Updated active consultations end_date to 2026-12-31!\n";

$res = $conn->query("SELECT id, title, response_mode, status, end_date FROM consultations ORDER BY id DESC");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']} | Mode: {$r['response_mode']} | Status: {$r['status']} | EndDate: {$r['end_date']} | Title: {$r['title']}\n";
}
