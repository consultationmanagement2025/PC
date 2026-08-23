<?php
require_once __DIR__ . '/../db.php';

echo "=== CONSULTATIONS TABLE ===\n";
$res = $conn->query("SELECT id, title, category, status, document_status, ai_analyzed, forwarded_to_expert, created_at FROM consultations");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo json_encode($r) . "\n";
    }
} else {
    echo "Error querying consultations: " . $conn->error . "\n";
}

echo "\n=== TABLES LIST ===\n";
$res2 = $conn->query("SHOW TABLES");
if ($res2) {
    while ($r = $res2->fetch_array()) {
        echo $r[0] . "\n";
    }
}
