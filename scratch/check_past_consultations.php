<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT id, title, response_mode, status, end_date, created_at FROM consultations ORDER BY id DESC");
while ($r = $res->fetch_assoc()) {
    echo "ID: " . $r['id'] . " | Mode: " . $r['response_mode'] . " | Status: " . $r['status'] . " | Title: " . $r['title'] . " | End Date: " . $r['end_date'] . "\n";
}
