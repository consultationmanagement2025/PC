<?php
chdir(__DIR__ . '/../API');
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/../db.php';
$conn->query("UPDATE consultations SET document_status = 'expert_annotated', expert_notes = 'Annotated and verified by Resource Person' WHERE id = 3");

$_GET['action'] = 'forward_brief_to_orts';
$GLOBALS['HTTP_RAW_POST_DATA'] = json_encode([
    'consultation_id' => 3,
    'target' => 'ORTS',
    'committee' => 'Public Works & Infrastructure Committee'
]);

// Test API via direct include or curl
$ch = curl_init('http://localhost/CAP101/PC/API/consultation_feedback_ai.php?action=forward_brief_to_orts');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'consultation_id' => 3,
    'target' => 'ORTS',
    'committee' => 'Public Works & Infrastructure Committee'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);

echo "HTTP Response:\n" . $res . "\n";
