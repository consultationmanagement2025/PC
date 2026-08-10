<?php
/**
 * Upload Resolution Report API
 * Allows resource persons to upload resolution reports for consultations
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

$consultation_id = isset($_POST['consultation_id']) ? (int)$_POST['consultation_id'] : 0;
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

if ($consultation_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid consultation ID']);
    exit;
}

$file = $_FILES['resolution_file'] ?? ($_FILES['report_file'] ?? null);
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}
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

// Determine version label
$version_label = isset($_POST['version_label']) && !empty($_POST['version_label']) ? trim($_POST['version_label']) : '';
if (empty($version_label)) {
    // Count existing reports for this consultation
    $vStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM resolution_reports WHERE consultation_id = ?");
    if ($vStmt) {
        $vStmt->bind_param('i', $consultation_id);
        $vStmt->execute();
        $vRes = $vStmt->get_result();
        $cnt = ($vRes && $row = $vRes->fetch_assoc()) ? (int)$row['cnt'] : 0;
        $vStmt->close();
        $version_label = 'v' . ($cnt + 1) . '.0';
    } else {
        $version_label = 'v1.0';
    }
}

// Insert into database
$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("INSERT INTO resolution_reports (consultation_id, uploaded_by, file_path, notes, version_label, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending_review', NOW())");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('iisss', $consultation_id, $user_id, $filename, $notes, $version_label);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to save record: ' . $stmt->error]);
    exit;
}

$stmt->close();

// Create expert notification
@$conn->query("INSERT INTO expert_notifications (user_id, title, message, type, consultation_id, is_read, created_at) VALUES ($user_id, 'Report Uploaded ($version_label)', 'Your resolution paper ($version_label) was uploaded and submitted to the Secretariat.', 'report_submission', $consultation_id, 0, NOW())");

// Mark consultation status as endorsed by technical expert
@$conn->query("UPDATE consultations SET status = 'endorsed' WHERE id = $consultation_id");

echo json_encode(['success' => true, 'message' => 'Resolution report uploaded and consultation endorsed successfully']);
