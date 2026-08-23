<?php
require_once __DIR__ . '/../db.php';

echo "=== ALL CONSULTATIONS IN DB ===\n";
$c = $conn->query("SELECT id, title, description, category, status FROM consultations");
while ($r = $c->fetch_assoc()) {
    echo "#{$r['id']} | {$r['title']} | Status: {$r['status']} | Cat: {$r['category']}\n";
}

echo "\n=== HEARING QUEUE ===\n";
$hq = $conn->query("SELECT * FROM hearing_queue");
while ($r = $hq->fetch_assoc()) {
    echo json_encode($r) . "\n";
}

echo "\n=== CONSULTATION VOTES ===\n";
$cv = $conn->query("SELECT * FROM consultation_votes");
while ($r = $cv->fetch_assoc()) {
    echo json_encode($r) . "\n";
}

echo "\n=== CONSULTATION GUEST VOTES ===\n";
$cgv = $conn->query("SELECT * FROM consultation_guest_votes");
while ($r = $cgv->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
