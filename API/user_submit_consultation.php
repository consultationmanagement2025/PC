<?php
// user_submit_consultation.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/user-logs.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_email = isset($_POST['email']) ? trim($_POST['email']) : null;
$topic = trim($_POST['topic'] ?? '');
$description = trim($_POST['description'] ?? '');
$allow_email_notifications = isset($_POST['allow_email_notifications']) ? 1 : 0;

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

// Store user-submitted consultation requests into the existing consultations table
// (admin publishes official consultations by setting status to 'active')
$user_name = trim($_SESSION['fullname'] ?? '');
if ($user_name === '') {
    $user_name = 'Citizen';
}

$full_description = "Preferred Date/Time: " . $preferred_datetime . "\n\n" . $description;

$stmt = $conn->prepare("INSERT INTO consultations (title, description, user_name, user_email, allow_email_notifications, status, created_at) VALUES (?, ?, ?, ?, ?, 'draft', NOW())");
if (!$stmt) {
    echo json_encode(['error' => 'Database error']);
    exit();
}
$stmt->bind_param('ssssi', $topic, $full_description, $user_name, $user_email, $allow_email_notifications);

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
        'preferred_datetime' => $preferred_datetime,
        'allow_email_notifications' => $allow_email_notifications
    ]));
    
    // Send confirmation email
    if ($user_email) {
        $subject = "Consultation Request Received - City of Valenzuela";
        $body = "Thank you for submitting your consultation request.\n\n";
        $body .= "Topic: " . $topic . "\n";
        $body .= "Preferred Date/Time: " . $preferred_datetime . "\n";
        $body .= "Submitted: " . date('F j, Y \a\t g:i A') . "\n\n";
        if ($allow_email_notifications) {
            $body .= "You have opted in to receive email updates about your consultation.\n";
            $body .= "We will notify you about your request status via email.\n\n";
        } else {
            $body .= "You will not receive email notifications about this consultation.\n\n";
        }
        $body .= "Thank you,\nCity Government of Valenzuela";
        
        $headers = "From: noreply@valenzuelacity.gov\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($user_email, $subject, $body, $headers);
    }
    
    echo json_encode(['success' => true, 'message' => 'Consultation submitted successfully.']);
} else {
    echo json_encode(['error' => 'Failed to submit consultation']);
}

$stmt->close();
