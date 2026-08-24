<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT id, title, status, ai_committee_brief FROM consultations");
echo "=== CHECKING AI_COMMITTEE_BRIEF IN DB ===\n";
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $brief = !empty($row['ai_committee_brief']) ? json_decode($row['ai_committee_brief'], true) : null;
        $cIdInBrief = $brief['consultation_id'] ?? 'MISSING/NULL';
        echo "Consultation DB ID: {$row['id']} | Title: {$row['title']} | ID in Brief JSON: {$cIdInBrief}\n";
    }
}
