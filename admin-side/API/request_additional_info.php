<?php
/**
 * Request Additional Information API
 * Allows resource persons to request additional information from citizens
 */

session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user is resource person or admin
$current_role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';
if (!in_array($current_role, ['resource person', 'resource_person', 'staff', 'admin', 'super admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$consultation_id = isset($input['consultation_id']) ? (int)$input['consultation_id'] : 0;
$user_email = isset($input['user_email']) ? trim($input['user_email']) : '';
$message = isset($input['message']) ? trim($input['message']) : '';

if ($consultation_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid consultation ID']);
    exit;
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

// Get consultation details
$stmt = $conn->prepare("SELECT title, user_email FROM consultations WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param('i', $consultation_id);
$stmt->execute();
$result = $stmt->get_result();
$consultation = $result->fetch_assoc();
$stmt->close();

if (!$consultation) {
    echo json_encode(['success' => false, 'message' => 'Consultation not found']);
    exit;
}

// Insert into database
$stmt = $conn->prepare("INSERT INTO info_requests (consultation_id, requested_by, user_email, message, created_at) VALUES (?, ?, ?, ?, NOW())");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$requested_by = (int)$_SESSION['user_id'];
$target_email = !empty($user_email) ? $user_email : $consultation['user_email'];
$stmt->bind_param('iiss', $consultation_id, $requested_by, $target_email, $message);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to save request: ' . $stmt->error]);
    exit;
}

$stmt->close();

// In a real implementation, you would send an email notification here
// For now, we'll just log it
error_log("Info request sent for consultation $consultation_id to $target_email");

echo json_encode(['success' => true, 'message' => 'Request for additional information sent successfully']);
