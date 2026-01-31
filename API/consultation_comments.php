<?php
// consultation_comments.php
require 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$consultation_id = isset($_POST['consultation_id']) ? intval($_POST['consultation_id']) : 0;
$comment = trim($_POST['comment'] ?? '');
$user_id = $_SESSION['user_id'];

if (!$consultation_id || !$comment) {
    echo json_encode(['error' => 'Missing data']);
    exit();
}

$stmt = $conn->prepare("INSERT INTO consultation_comments (consultation_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param('iis', $consultation_id, $user_id, $comment);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to add comment']);
}
