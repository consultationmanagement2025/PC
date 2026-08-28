<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT id, title, status FROM consultations");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']} | Title: {$r['title']} | Status: {$r['status']}\n";
}
