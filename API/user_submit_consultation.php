<?php
// user_submit_consultation.php
require 'db.php';
require_once __DIR__ . '/../DATABASE/user-logs.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$topic = trim($_POST['topic'] ?? '');
$description = trim($_POST['description'] ?? '');

// Support both preferred_datetime or separate date and time fields
if (isset($_POST['preferred_date']) && isset($_POST['preferred_time'])) {
    $preferred_datetime = $_POST['preferred_date'] . ' ' . $_POST['preferred_time'];
} else {
    $preferred_datetime = $_POST['preferred_datetime'] ?? null;
}

if (!$topic || !$preferred_datetime) {
    echo json_encode(['error' => 'Topic and preferred date/time required']);
    exit();
}

$stmt = $conn->prepare("INSERT INTO consultations (user_id, topic, description, preferred_datetime) VALUES (?, ?, ?, ?)");
$stmt->bind_param('isss', $user_id, $topic, $description, $preferred_datetime);

if ($stmt->execute()) {
    // Log user action
    $username = $_SESSION['fullname'] ?? '';
    $action = 'submit_consultation';
    $action_type = 'create';
    $entity_type = 'consultation';
    $entity_id = $stmt->insert_id;
    $desc = 'User submitted a new consultation: ' . $topic;
    logUserAction($user_id, $username, $action, $action_type, $entity_type, $entity_id, $desc, 'success', json_encode([
        'topic' => $topic,
        'description' => $description,
        'preferred_datetime' => $preferred_datetime
    ]));
    echo json_encode(['success' => true, 'message' => 'Consultation submitted successfully.']);
} else {
    echo json_encode(['error' => 'Failed to submit consultation']);
}
