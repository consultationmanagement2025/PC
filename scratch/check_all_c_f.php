<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("SELECT id, title, category, status FROM consultations");
while ($r = $res->fetch_assoc()) {
    $cid = $r['id'];
    $fCount = $conn->query("SELECT COUNT(*) FROM feedback WHERE consultation_id = $cid")->fetch_row()[0];
    echo "ID: #{$cid} | Title: '{$r['title']}' | Status: '{$r['status']}' | Feedback in DB: {$fCount}\n";
}
