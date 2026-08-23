<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT id, title, category, status, created_at FROM consultations ORDER BY id DESC LIMIT 20");
while ($r = $res->fetch_assoc()) {
    echo "#{$r['id']} | {$r['title']} | Status: {$r['status']} | Cat: {$r['category']} | Created: {$r['created_at']}\n";
}
