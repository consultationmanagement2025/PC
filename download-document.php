<?php
/**
 * Document Download Handler
 * Securely serves documents for download
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

if (!$file_path) {
    if (function_exists('getDocumentById')) {
        $doc = getDocumentById($document_id);
    }
    if (!$doc && function_exists('getAdminDocumentById')) {
        $doc = getAdminDocumentById($document_id);
    }
    if (!$doc) {
        $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $document_id);
            $stmt->execute();
            $doc = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }

    if ($doc) {
        $stored_name = trim((string)($doc['stored_filename'] ?? ''));
        $stored_path = trim((string)($doc['file_path'] ?? ''));
        $consultation_id = (int)($doc['consultation_id'] ?? 0);
        $title = trim((string)($doc['title'] ?? $doc['description'] ?? 'Document'));

        $download_name = trim((string)($doc['original_filename'] ?? $stored_name ?: ($title . '.pdf')));
        if (strtolower(pathinfo($download_name, PATHINFO_EXTENSION)) !== 'pdf' && strtolower(pathinfo($download_name, PATHINFO_EXTENSION)) !== 'docx') {
            $download_name .= '.pdf';
        }

        $candidates = [];
        if ($stored_name !== '') {
            $candidates[] = __DIR__ . '/uploads/documents/' . $stored_name;
            $candidates[] = __DIR__ . '/uploads/' . $stored_name;
        }
        if ($stored_path !== '') {
            $candidates[] = __DIR__ . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($stored_path, '/\\'));
        }

        foreach ($candidates as $cand) {
            if (is_file($cand)) {
                $file_path = $cand;
                break;
            }
        }

        // Dynamic PDF Generator Fallback for Consultation Documents & AI Briefs
        if ((!$file_path || !is_file($file_path)) && $consultation_id > 0) {
            $uploadsDir = __DIR__ . '/uploads/documents';
            if (!is_dir($uploadsDir)) {
                @mkdir($uploadsDir, 0777, true);
            }

            $safeTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $title ?: ('Consultation_' . $consultation_id));
            $genFileName = 'CONSULT-' . sprintf('%06d', $consultation_id) . '_' . $safeTitle . '.pdf';
            $genCandidate = $uploadsDir . '/' . $genFileName;

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
                        $pdfGen->save($cRow, $genCandidate);

                        if (is_file($genCandidate)) {
                            $file_path = $genCandidate;
                            $download_name = $genFileName;
                            $conn->query("UPDATE documents SET stored_filename = '" . $conn->real_escape_string($genFileName) . "', file_path = 'uploads/documents/" . $conn->real_escape_string($genFileName) . "', original_filename = '" . $conn->real_escape_string($genFileName) . "' WHERE id = {$document_id}");
                        }
                    }
                }
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
