<?php
require_once __DIR__ . '/../db.php';

$consultationId = 3;
$searchTitle = 'Proposed Flood Control and Drainage Improvement Plan';

echo "=== DEBUGGING CONSULTATION RESOLUTION ===\n";

// 1. Direct ID
$chkStmt = $conn->prepare("SELECT id, status, committee_assigned, title, reference_number, ai_committee_brief, document_status, expert_notes FROM consultations WHERE id = ? LIMIT 1");
$chkStmt->bind_param('i', $consultationId);
$chkStmt->execute();
$cRes = $chkStmt->get_result();
$cRow1 = $cRes ? $cRes->fetch_assoc() : null;
$chkStmt->close();
echo "Step 1 (ID=3): " . ($cRow1 ? "FOUND: ID {$cRow1['id']}" : "NOT FOUND") . "\n";

// 2. Feedback Join
$fbStmt = $conn->prepare("SELECT c.id, c.status, c.committee_assigned, c.title, c.reference_number, c.ai_committee_brief, c.document_status, c.expert_notes FROM feedback f JOIN consultations c ON f.consultation_id = c.id WHERE f.id = ? LIMIT 1");
$fbStmt->bind_param('i', $consultationId);
$fbStmt->execute();
$fbRes = $fbStmt->get_result();
$cRow2 = $fbRes ? $fbRes->fetch_assoc() : null;
$fbStmt->close();
echo "Step 2 (Feedback ID=3): " . ($cRow2 ? "FOUND: ID {$cRow2['id']}" : "NOT FOUND") . "\n";

// 3. Title Search
$tStmt = $conn->prepare("SELECT id, status, committee_assigned, title, reference_number, ai_committee_brief, document_status, expert_notes FROM consultations WHERE title = ? OR title LIKE ? LIMIT 1");
$likeT = '%' . $searchTitle . '%';
$tStmt->bind_param('ss', $searchTitle, $likeT);
$tStmt->execute();
$tRes = $tStmt->get_result();
$cRow3 = $tRes ? $tRes->fetch_assoc() : null;
$tStmt->close();
echo "Step 3 (Title): " . ($cRow3 ? "FOUND: ID {$cRow3['id']}" : "NOT FOUND") . "\n";

// 4. Latest Record
$fRes = $conn->query("SELECT id, status, committee_assigned, title, reference_number, ai_committee_brief, document_status, expert_notes FROM consultations ORDER BY id DESC LIMIT 1");
$cRow4 = $fRes ? $fRes->fetch_assoc() : null;
echo "Step 4 (Latest): " . ($cRow4 ? "FOUND: ID {$cRow4['id']}" : "NOT FOUND") . "\n";
