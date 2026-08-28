<?php
require_once 'db.php';
$r = $conn->query("SELECT id, title, type, source_system, tracking_number, category FROM consultations");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | " . $row['title'] . " | Type: " . ($row['type'] ?? 'N/A') . " | Source: " . ($row['source_system'] ?? 'PCMS') . " | Ref: " . ($row['tracking_number'] ?? 'N/A') . "\n";
    }
} else {
    echo "Query error: " . $conn->error;
}
