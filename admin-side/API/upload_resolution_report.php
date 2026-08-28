<?php
/**
 * Upload Resolution Report API
 * Allows resource persons to upload resolution reports for consultations
 */

session_start();
require_once __DIR__ . '/../db.php';

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

$consultation_id = isset($_POST['consultation_id']) ? (int)$_POST['consultation_id'] : 0;
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

if ($consultation_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid consultation ID']);
    exit;
}

if (!isset($_FILES['resolution_file']) || $_FILES['resolution_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['resolution_file'];
$allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF, DOC, and DOCX files are allowed.']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../uploads/resolution_reports/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'resolution_report_' . $consultation_id . '_' . time() . '_' . uniqid() . '.' . $extension;
$filepath = $upload_dir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

// Insert into database
$stmt = $conn->prepare("INSERT INTO resolution_reports (consultation_id, uploaded_by, file_path, notes, created_at) VALUES (?, ?, ?, ?, NOW())");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$stmt->bind_param('iiss', $consultation_id, $user_id, $filename, $notes);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to save record: ' . $stmt->error]);
    exit;
}

$stmt->close();

echo json_encode(['success' => true, 'message' => 'Resolution report uploaded successfully']);
