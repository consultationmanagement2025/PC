<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT id, title, category, status, type, document_status, ai_analyzed, forwarded_to_expert, description FROM consultations");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo sprintf("ID: %d | Title: %s | Cat: %s | Status: %s | Type: %s | DocStatus: %s | AI: %s | Fwd: %s\n  Desc: %s\n\n",
            $r['id'], $r['title'], $r['category'], $r['status'], $r['type'], $r['document_status'], $r['ai_analyzed'], $r['forwarded_to_expert'], $r['description']);
    }
}
