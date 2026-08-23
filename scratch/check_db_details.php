<?php
require_once __DIR__ . '/../db.php';

echo "=== CONSULTATIONS IN DB ===\n";
$res = $conn->query("SELECT id, title, status, type, response_mode FROM consultations");
while ($c = $res->fetch_assoc()) {
    print_r($c);
}

echo "\n=== FEEDBACK COUNT PER CONSULTATION ===\n";
$res = $conn->query("SELECT consultation_id, COUNT(*) as cnt FROM feedback GROUP BY consultation_id");
while ($f = $res->fetch_assoc()) {
    print_r($f);
}

echo "\n=== POSTS COUNT PER CONSULTATION ===\n";
$res = $conn->query("SELECT consultation_id, COUNT(*) as cnt FROM posts GROUP BY consultation_id");
while ($p = $res->fetch_assoc()) {
    print_r($p);
}
