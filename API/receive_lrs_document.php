<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/document-management.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        exit;
    }

    $ref_num = trim($_POST['external_id'] ?? $_POST['reference_number'] ?? '');
    if (empty($ref_num)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing external_id or reference_number']);
        exit;
    }

    $title = trim($_POST['title'] ?? 'LRS Returned Document');
    $version_number = trim($_POST['version'] ?? $_POST['version_number'] ?? '1.1');
    $notes = trim($_POST['notes'] ?? $_POST['description'] ?? 'Document version returned from LRS');
    $doc_type = trim($_POST['document_type'] ?? 'consultation_summary');

    $file_field = null;
    if (isset($_FILES['file'])) {
        $file_field = $_FILES['file'];
    } else if (isset($_FILES['document'])) {
        $file_field = $_FILES['document'];
    } else if (isset($_FILES['document_file'])) {
        $file_field = $_FILES['document_file'];
    }

    if (!$file_field || $file_field['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No valid document file uploaded']);
        exit;
    }

    $versions_dir = __DIR__ . '/../uploads/documents/versions/';
    if (!is_dir($versions_dir)) {
        mkdir($versions_dir, 0755, true);
    }

    $orig_filename = sanitizeFilename(basename($file_field['name']));
    $ext = strtolower(pathinfo($orig_filename, PATHINFO_EXTENSION));
    if (!$ext) $ext = 'pdf';
    $stored_filename = 'lrs_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $ref_num) . '_v' . str_replace('.', '_', $version_number) . '_' . time() . '.' . $ext;
    $target_filepath = $versions_dir . $stored_filename;

    if (!move_uploaded_file($file_field['tmp_name'], $target_filepath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save returned document file']);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $file_type = $finfo->file($target_filepath) ?: 'application/pdf';
    $file_size = filesize($target_filepath);

    initializeDocumentVersionsTable();

    // Check parent document in `documents`
    $stmt = $conn->prepare("SELECT id, consultation_id FROM documents WHERE reference_number = ? LIMIT 1");
    $stmt->bind_param('s', $ref_num);
    $stmt->execute();
    $parentDoc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $doc_id = $parentDoc['id'] ?? null;
    $consult_id = $parentDoc['consultation_id'] ?? null;

    $version_id = addDocumentVersion([
        'document_id' => $doc_id,
        'consultation_id' => $consult_id,
        'reference_number' => $ref_num,
        'title' => $title,
        'version_number' => $version_number,
        'document_type' => $doc_type,
        'original_filename' => $orig_filename,
        'stored_filename' => $stored_filename,
        'file_path' => 'uploads/documents/versions/' . $stored_filename,
        'file_size' => $file_size,
        'file_type' => $file_type,
        'source_system' => 'lrs',
        'status' => 'returned_from_lrs',
        'lrs_response' => json_encode($_POST),
        'notes' => $notes
    ]);

    if ($doc_id) {
        $updateStmt = $conn->prepare("UPDATE documents SET status = 'returned_from_lrs' WHERE id = ?");
        $updateStmt->bind_param('i', $doc_id);
        $updateStmt->execute();
        $updateStmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Document version received from LRS successfully',
        'version_id' => $version_id,
        'reference_number' => $ref_num,
        'version_number' => $version_number
    ]);
} catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
