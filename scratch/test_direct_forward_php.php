<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/../db.php';

// Set test input
$_POST = [
    'consultation_id' => 3,
    'title' => 'Proposed Flood Control and Drainage Improvement Plan',
    'target' => 'ORTS',
    'committee' => 'Public Works & Infrastructure Committee'
];

$_GET['action'] = 'forward_brief_to_orts';

// Ensure expert checked status is active
$conn->query("UPDATE consultations SET document_status = 'expert_annotated', expert_notes = 'Annotated by expert' WHERE id = 3");

ob_start();
require __DIR__ . '/../API/consultation_feedback_ai.php';
$res = ob_get_clean();

echo "Direct PHP Response:\n" . $res . "\n";
