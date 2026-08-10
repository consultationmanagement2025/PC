<?php
require_once __DIR__ . '/../db.php';

echo "--- 1. Altering Column Defaults for consultations table ---\n";
@$conn->query("ALTER TABLE consultations ALTER COLUMN ai_analyzed SET DEFAULT 0");
@$conn->query("ALTER TABLE consultations ALTER COLUMN forwarded_to_expert SET DEFAULT 0");
@$conn->query("ALTER TABLE consultations MODIFY COLUMN ai_analyzed TINYINT(1) DEFAULT 0");
@$conn->query("ALTER TABLE consultations MODIFY COLUMN forwarded_to_expert TINYINT(1) DEFAULT 0");

echo "Column defaults set to 0!\n";

echo "\n--- 2. Updating Unforwarded Consultations in Database ---\n";
// Set ai_analyzed = 0 and forwarded_to_expert = 0 for consultations where document_status is 'draft' or assigned_to is NULL
$res = $conn->query("UPDATE consultations SET ai_analyzed = 0, forwarded_to_expert = 0 WHERE document_status = 'draft' AND (assigned_to IS NULL OR assigned_to = 0)");
if ($res) {
    echo "Updated " . $conn->affected_rows . " consultations to unforwarded state (ai_analyzed = 0, forwarded_to_expert = 0).\n";
} else {
    echo "Error updating consultations: " . $conn->error . "\n";
}

echo "\n--- 3. Verifying Consultation Visibility Status ---\n";
$cRes = $conn->query("SELECT id, title, category, status, document_status, ai_analyzed, forwarded_to_expert, assigned_to FROM consultations");
while ($c = $cRes->fetch_assoc()) {
    $fwdLabel = ($c['forwarded_to_expert'] == 1 && $c['ai_analyzed'] == 1) ? "VISABLE TO EXPERT (Forwarded)" : "HIDDEN FROM EXPERT (Pending AI/Admin Forwarding)";
    echo "Consultation #{$c['id']} ('{$c['title']}') | Document Status: {$c['document_status']} | AI Analyzed: {$c['ai_analyzed']} | Forwarded: {$c['forwarded_to_expert']} => $fwdLabel\n";
}

echo "\nDatabase AI Flags Update Completed Successfully!\n";
