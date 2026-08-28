<?php
require_once __DIR__ . '/../db.php';

$res1 = $conn->query("SELECT id, title FROM consultations");
echo "=== CONSULTATIONS ===\n";
while ($c = $res1->fetch_assoc()) {
    echo "ID: {$c['id']} - Title: {$c['title']}\n";
}

$res2 = $conn->query("SELECT consultation_id, COUNT(*) FROM feedback GROUP BY consultation_id");
echo "\n=== FEEDBACK BY CONSULTATION_ID ===\n";
while ($f = $res2->fetch_assoc()) {
    echo "Consultation_ID: [{$f['consultation_id']}] => Count: {$f['COUNT(*)']}\n";
}
