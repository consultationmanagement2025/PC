<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT id, title, category, status, document_status, ai_analyzed, forwarded_to_expert, assigned_to FROM consultations");
echo "Total consultations in DB: " . ($res ? $res->num_rows : 0) . "\n\n";

while ($row = $res->fetch_assoc()) {
    echo "ID: #{$row['id']}\n";
    echo "Title: {$row['title']}\n";
    echo "Category: {$row['category']}\n";
    echo "Status: {$row['status']}\n";
    echo "Document Status: {$row['document_status']}\n";
    echo "AI Analyzed: " . var_export($row['ai_analyzed'], true) . "\n";
    echo "Forwarded to Expert: " . var_export($row['forwarded_to_expert'], true) . "\n";
    echo "Assigned To: " . var_export($row['assigned_to'], true) . "\n";
    echo "-----------------------------------------\n";
}
