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

// Get version ID, document ID, and source
$version_id = isset($_GET['version_id']) ? (int)$_GET['version_id'] : 0;
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$source = strtolower(trim((string)($_GET['source'] ?? 'admin')));

if ($version_id > 0) {
    initializeDocumentVersionsTable();
    $stmt = $conn->prepare("SELECT * FROM document_versions WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $version_id);
    $stmt->execute();
    $ver = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($ver) {
        $download_name = !empty($ver['original_filename']) ? $ver['original_filename'] : 'version_' . $ver['version_number'] . '.pdf';
        $relPath = $ver['file_path'];
        $candidate = __DIR__ . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relPath);
        if (is_file($candidate)) {
            $file_path = $candidate;
            $doc = $ver;
        }
    }
} else if ($document_id <= 0) {
    http_response_code(400);
    echo 'Invalid document ID';
    exit;
}

$doc = $doc ?? null;
$file_path = $file_path ?? null;
$download_name = $download_name ?? 'document';

if (!$file_path && $source === 'consultation') {

    $doc = getDocumentById($document_id);
    if ($doc) {
        $stored_name = trim((string)($doc['stored_filename'] ?? ''));
        $download_name = trim((string)($doc['original_filename'] ?? $stored_name ?: 'document.pdf'));
        $consultation_id = (int)($doc['consultation_id'] ?? 0);

        if ($stored_name !== '') {
            $candidate = __DIR__ . '/uploads/documents/' . $stored_name;
            // Dynamically regenerate PDF summary with clean layout if target is a consultation summary PDF
            if ($consultation_id > 0 && strtolower(pathinfo($stored_name, PATHINFO_EXTENSION)) === 'pdf') {
                require_once __DIR__ . '/UTILS/pdf_generator.php';
                if (isset($conn) && $conn instanceof mysqli) {
                    $cStmt = $conn->prepare("SELECT * FROM consultations WHERE id = ? LIMIT 1");
                    if ($cStmt) {
                        $cStmt->bind_param('i', $consultation_id);
                        $cStmt->execute();
                        $cRes = $cStmt->get_result();
                        $cRow = $cRes ? $cRes->fetch_assoc() : null;
                        $cStmt->close();
                        if ($cRow) {
                            $pdfGen = new ConsultationPDFGenerator($consultation_id);
                            $pdfGen->save($cRow, $candidate);
                        }
                    }
                }
            }
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
