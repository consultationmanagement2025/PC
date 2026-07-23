<?php
/**
 * Secure Report Download Handler
 * Handles downloads of generated module reports
 */

session_start();
require_once 'session_check.php';

// Check user is authenticated and has appropriate role
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$is_admin = ($current_role === 'admin' || $current_role === 'administrator');
$is_super_admin = ($current_role === 'super admin' || $current_role === 'superadmin');
$is_staff = in_array($current_role, ['resource person', 'resource_person', 'staff'], true);

if (!($is_admin || $is_super_admin || $is_staff)) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$filename = isset($_GET['file']) ? basename($_GET['file']) : '';

if (empty($filename)) {
    http_response_code(404);
    echo 'Report not found';
    exit;
}

// Validate filename format (prevent directory traversal)
if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
    http_response_code(400);
    echo 'Invalid filename';
    exit;
}

$report_path = __DIR__ . '/uploads/reports/' . $filename;

if (!file_exists($report_path)) {
    http_response_code(404);
    echo 'Report file not found';
    exit;
}

// Determine MIME type based on file extension
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime_types = [
    'pdf' => 'application/pdf',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'txt' => 'text/plain',
];

$mime_type = $mime_types[$extension] ?? 'application/octet-stream';

// Set headers for download
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($report_path));
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Stream file to client
readfile($report_path);
exit;
?>
