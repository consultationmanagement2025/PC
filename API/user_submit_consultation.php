<?php
// user_submit_consultation.php
require 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$topic = trim($_POST['topic'] ?? '');
$description = trim($_POST['description'] ?? '');
$preferred_datetime = $_POST['preferred_datetime'] ?? null;

if (!$topic || !$preferred_datetime) {
    echo json_encode(['error' => 'Topic and preferred date/time required']);
    exit();
}

$stmt = $conn->prepare("INSERT INTO consultations (user_id, topic, description, preferred_datetime) VALUES (?, ?, ?, ?)");
$stmt->bind_param('isss', $user_id, $topic, $description, $preferred_datetime);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to submit consultation']);
}
