<?php
require_once __DIR__ . '/../db.php';

echo "=== CONSULTATIONS TABLE (id=8 or user_name like Aljan) ===\n";
$res1 = $conn->query("SELECT * FROM consultations WHERE id = 8 OR user_name LIKE '%Aljan%' OR title LIKE '%test%'");
if ($res1 && $res1->num_rows > 0) {
    while ($r = $res1->fetch_assoc()) {
        print_r($r);
    }
} else {
    echo "No matching row in consultations table.\n";
}

echo "=== ALL TABLES SEARCH FOR Aljan ===\n";
$tables = ['consultations', 'hearing_queue', 'feedback', 'users', 'consultation_feedback', 'consultation_votes'];
foreach ($tables as $tbl) {
    $tRes = $conn->query("SHOW TABLES LIKE '$tbl'");
    if ($tRes && $tRes->num_rows > 0) {
        $q = $conn->query("SELECT * FROM $tbl WHERE CONCAT_WS(' ', id, title, user_name, full_name, name, description) LIKE '%Aljan%' OR id = 8");
        if ($q && $q->num_rows > 0) {
            echo "MATCH IN TABLE $tbl:\n";
            while ($r = $q->fetch_assoc()) {
                print_r($r);
            }
        }
    }
}
