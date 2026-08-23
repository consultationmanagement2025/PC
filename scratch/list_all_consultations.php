<?php
require_once __DIR__ . '/../db.php';

echo "=== ALL CONSULTATIONS ===\n";
$res = $conn->query("SELECT id, title, category, status, type, document_status, ai_analyzed, forwarded_to_expert, assigned_to FROM consultations");
while ($r = $res->fetch_assoc()) {
    echo sprintf("ID: %d | Title: %-45s | Cat: %-30s | Status: %-10s | Type: %-6s | AI: %d | Fwd: %d | Assigned: %s\n",
        $r['id'], $r['title'], $r['category'], $r['status'], $r['type'], $r['ai_analyzed'], $r['forwarded_to_expert'], var_export($r['assigned_to'], true));
}
