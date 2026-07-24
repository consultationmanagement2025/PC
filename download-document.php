<?php
/**
 * Document Download Handler
 * Securely serves documents for download
 */

session_start();
require_once 'db.php';
require_once 'DATABASE/document-management.php';
require_once 'DATABASE/documents.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Unauthorized access';
    exit;
}

// Get document ID and source
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$source = strtolower(trim((string)($_GET['source'] ?? 'admin')));

if ($document_id <= 0) {
    http_response_code(400);
    echo 'Invalid document ID';
    exit;
}

$doc = null;
$file_path = null;
$download_name = 'document';

if ($source === 'consultation') {
    $doc = getDocumentById($document_id);
    if ($doc) {
        $stored_name = trim((string)($doc['stored_filename'] ?? ''));
        $download_name = trim((string)($doc['original_filename'] ?? $stored_name ?: 'document'));
        if ($stored_name !== '') {
            $candidate = __DIR__ . '/uploads/documents/' . $stored_name;
            if (is_file($candidate)) {
                $file_path = $candidate;
            }
        }
    }
} else {
    $doc = getAdminDocumentById($document_id);
    if ($doc) {
        $download_name = trim((string)($doc['title'] ?: $doc['reference'] ?: 'document'));
        $stored_path = trim((string)($doc['file_path'] ?? ''));
        if ($stored_path !== '') {
            $candidate = __DIR__ . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $stored_path);
            if (is_file($candidate)) {
                $file_path = $candidate;
            }
        }
    }
}

if (!$doc || !$file_path || !is_file($file_path)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file_path);
if ($mime === false) {
    $mime = 'application/octet-stream';
}

$size = filesize($file_path);
header('Content-Type: ' . $mime);

// Use inline disposition for PDFs to allow browser viewing
if (strtolower(pathinfo($download_name, PATHINFO_EXTENSION)) === 'pdf') {
    header('Content-Disposition: inline; filename="' . str_replace('"', '""', $download_name) . '"');
} else {
    header('Content-Disposition: attachment; filename="' . str_replace('"', '""', $download_name) . '"');
}

header('Content-Length: ' . $size);
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($file_path);
exit;
?>
