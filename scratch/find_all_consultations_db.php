<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT * FROM consultations");
echo "Total Rows in DB: " . ($res ? $res->num_rows : 0) . "\n\n";

while ($c = $res->fetch_assoc()) {
    echo "ID: " . $c['id'] . " | Title: " . $c['title'] . " | Category: " . $c['category'] . "\n";
    echo "  Status: " . $c['status'] . " | DocStatus: " . ($c['document_status'] ?? 'N/A') . " | AI Analyzed: " . ($c['ai_analyzed'] ?? 'N/A') . " | Forwarded: " . ($c['forwarded_to_expert'] ?? 'N/A') . " | AssignedTo: " . ($c['assigned_to'] ?? 'N/A') . "\n\n";
}
