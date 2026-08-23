<?php
require_once __DIR__ . '/../db.php';

echo "=== HEARING QUEUE ROWS ===\n";
$res = $conn->query("SELECT * FROM hearing_queue");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", ConsultID: " . $row['consultation_id'] . ", Name: " . $row['full_name'] . "\n";
        echo "Payload: " . substr($row['payload_json'] ?? '', 0, 200) . "\n\n";
    }
}
