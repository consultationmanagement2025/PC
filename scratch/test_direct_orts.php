<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/consultations.php';

// Check consultation ID 3
$cId = 3;
$res = $conn->query("SELECT id, title, committee_assigned, status, document_status FROM consultations WHERE id = $cId");
$row = $res->fetch_assoc();
echo "Consultation #3: " . json_encode($row) . "\n";

// Ensure document status is annotated
$conn->query("UPDATE consultations SET document_status = 'expert_annotated', expert_notes = 'Verified and annotated by Resource Person' WHERE id = 3");

// Test lookup query used in API
$chkStmt = $conn->prepare("SELECT id, status, committee_assigned, title, reference_number, ai_committee_brief, document_status, expert_notes FROM consultations WHERE id = ? LIMIT 1");
$chkStmt->bind_param('i', $cId);
$chkStmt->execute();
$cRes = $chkStmt->get_result();
$cRow = $cRes ? $cRes->fetch_assoc() : null;
$chkStmt->close();

echo "API Consultation Lookup Result: " . ($cRow ? "FOUND" : "NOT FOUND") . "\n";
print_r($cRow);
