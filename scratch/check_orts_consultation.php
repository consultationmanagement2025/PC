<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT id, title, committee_assigned, status, document_status FROM consultations");
echo "=== CONSULTATIONS TABLE ===\n";
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "ID: {$row['id']} | Title: {$row['title']} | Committee: {$row['committee_assigned']} | Status: {$row['status']} | DocStatus: {$row['document_status']}\n";
    }
} else {
    echo "Query error: " . $conn->error . "\n";
}
