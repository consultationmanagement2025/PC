<?php
/**
 * Document Management System
 * Handles document uploads, storage, and retrieval for consultations
 */

require_once __DIR__ . '/../db.php';

/**
 * Create documents table if it doesn't exist
 */
function initializeDocumentsTable() {
    global $conn;
    
    $sql = "CREATE TABLE IF NOT EXISTS documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        consultation_id INT NOT NULL,
        reference_number VARCHAR(50) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        stored_filename VARCHAR(255) NOT NULL,
        file_type VARCHAR(50) NOT NULL,
        file_size INT NOT NULL,
        upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        uploaded_by INT,
        document_type ENUM('consultation_form', 'attachment', 'response', 'final_document') DEFAULT 'consultation_form',
        status ENUM('draft', 'submitted', 'reviewed', 'approved', 'rejected') DEFAULT 'submitted',
        description TEXT,
        FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_consultation (consultation_id),
        INDEX idx_reference (reference_number),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        error_log("Error creating documents table: " . $conn->error);
        return false;
    }
    
    @$conn->query("ALTER TABLE documents MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'submitted'");
    
    return true;
}

/**
 * Generate reference number for document
 */
function generateDocumentReference($consultation_id) {
    return sprintf("CONSULT-%06d", $consultation_id);
}

/**
 * Sanitize filename for storage
 */
function sanitizeFilename($filename) {
    // Remove special characters, keep spaces, dots, and underscores
    $filename = preg_replace('/[^a-zA-Z0-9\s._-]/', '', $filename);
    // Replace multiple spaces with single space
    $filename = preg_replace('/\s+/', ' ', $filename);
    // Trim spaces
    $filename = trim($filename);
    return $filename;
}

/**
 * Upload document for consultation
 */
function uploadConsultationDocument($consultation_id, $file_data, $document_type = 'consultation_form', $description = '') {
    global $conn;
    
    initializeDocumentsTable();
    
    $consultation_id = (int)$consultation_id;
    $reference_number = generateDocumentReference($consultation_id);
    
    // Validate file
    if (!isset($file_data['tmp_name']) || !is_uploaded_file($file_data['tmp_name'])) {
        throw new Exception('Invalid file upload');
    }
    
    // Check file size (10MB max)
    $max_size = 10 * 1024 * 1024; // 10MB
    if ($file_data['size'] > $max_size) {
        throw new Exception('File size exceeds 10MB limit');
    }
    
    // Check file type (PDF preferred, but allow common document types)
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'image/jpeg', 'image/png'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $file_type = $finfo->file($file_data['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('File type not allowed. Please upload PDF, Word, text, or image files.');
    }
    
    // Create storage directory
    $upload_dir = __DIR__ . '/../uploads/documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $original_filename = sanitizeFilename($file_data['name']);
    $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    $timestamp = date('Y-m-d_H-i-s');
    $stored_filename = sprintf("%s_%s_%s.%s", 
        $reference_number, 
        pathinfo($original_filename, PATHINFO_FILENAME), 
        $timestamp, 
        $file_extension
    );
    
    $upload_path = $upload_dir . $stored_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file_data['tmp_name'], $upload_path)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    // Save to database
    $stmt = $conn->prepare("
        INSERT INTO documents (
            consultation_id, reference_number, original_filename, 
            stored_filename, file_type, file_size, uploaded_by, 
            document_type, description
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        unlink($upload_path); // Clean up uploaded file
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt->bind_param('issssisss', 
        $consultation_id, $reference_number, $original_filename, 
        $stored_filename, $file_type, $file_data['size'], $user_id, 
        $document_type, $description
    );
    
    if (!$stmt->execute()) {
        unlink($upload_path); // Clean up uploaded file
        throw new Exception('Failed to save document record: ' . $stmt->error);
    }
    
    $document_id = $conn->insert_id;
    $stmt->close();
    
    return [
        'success' => true,
        'document_id' => $document_id,
        'reference_number' => $reference_number,
        'stored_filename' => $stored_filename,
        'original_filename' => $original_filename
    ];
}

/**
 * Get documents for consultation
 */
function getConsultationDocuments($consultation_id) {
    global $conn;
    
    initializeDocumentsTable();
    
    $consultation_id = (int)$consultation_id;
    $sql = "SELECT d.*, u.fullname as uploader_name 
            FROM documents d 
            LEFT JOIN users u ON d.uploaded_by = u.id 
            WHERE d.consultation_id = $consultation_id 
            ORDER BY d.upload_date DESC";
    
    $result = $conn->query($sql);
    $documents = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $documents[] = $row;
        }
    }
    
    return $documents;
}

/**
 * Get document count for a consultation
 */
function countConsultationDocuments($consultation_id) {
    global $conn;
    initializeDocumentsTable();
    $consultation_id = (int)$consultation_id;
    $sql = "SELECT COUNT(*) AS count FROM documents WHERE consultation_id = $consultation_id";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['count'];
    }
    return 0;
}

/**
 * Get document by ID
 */
function getDocumentById($document_id) {
    global $conn;
    
    initializeDocumentsTable();
    
    $document_id = (int)$document_id;
    $sql = "SELECT d.*, u.fullname as uploader_name, c.title as consultation_title
            FROM documents d 
            LEFT JOIN users u ON d.uploaded_by = u.id 
            LEFT JOIN consultations c ON d.consultation_id = c.id 
            WHERE d.id = $document_id";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Update document status
 */
function updateDocumentStatus($document_id, $status) {
    global $conn;
    
    initializeDocumentsTable();
    
    $document_id = (int)$document_id;
    $valid_statuses = ['draft', 'submitted', 'reviewed', 'approved', 'rejected', 'forwarded_to_lrs', 'forward_failed', 'returned_from_lrs'];
    
    if (!in_array($status, $valid_statuses, true)) {
        // Also allow dynamic statuses
        @$conn->query("ALTER TABLE documents MODIFY COLUMN status VARCHAR(50) DEFAULT 'submitted'");
    }
    
    $stmt = $conn->prepare("UPDATE documents SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $document_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update document status: ' . $stmt->error);
    }
    
    $stmt->close();
    return true;
}

/**
 * Delete document
 */
function deleteDocument($document_id) {
    global $conn;
    
    initializeDocumentsTable();
    
    $document = getDocumentById($document_id);
    if (!$document) {
        throw new Exception('Document not found');
    }
    
    // Delete physical file
    $file_path = __DIR__ . '/../uploads/documents/' . $document['stored_filename'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Delete database record
    $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
    $stmt->bind_param('i', $document_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete document record: ' . $stmt->error);
    }
    
    $stmt->close();
    return true;
}

/**
 * Get document download URL
 */
function getDocumentDownloadUrl($document_id) {
    $document = getDocumentById($document_id);
    if (!$document) {
        return null;
    }
    
    return "download-document.php?id=" . $document_id;
}

/**
 * Create document download handler
 */
function handleDocumentDownload($document_id) {
    $document = getDocumentById($document_id);
    if (!$document) {
        http_response_code(404);
        echo 'Document not found';
        exit;
    }
    
    $file_path = __DIR__ . '/../uploads/documents/' . $document['stored_filename'];
    if (!file_exists($file_path)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    
    // Set headers for download
    header('Content-Type: ' . $document['file_type']);
    header('Content-Disposition: attachment; filename="' . $document['original_filename'] . '"');
    header('Content-Length: ' . $document['file_size']);
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($file_path);
    exit;
}

/**
 * Create document_versions table if it doesn't exist
 */
function initializeDocumentVersionsTable() {
    global $conn;

    $sql = "CREATE TABLE IF NOT EXISTS document_versions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT DEFAULT NULL,
        consultation_id INT DEFAULT NULL,
        reference_number VARCHAR(100) NOT NULL,
        title VARCHAR(255) NOT NULL,
        version_number VARCHAR(50) DEFAULT '1.0',
        document_type VARCHAR(100) DEFAULT 'consultation_summary',
        original_filename VARCHAR(255) NOT NULL,
        stored_filename VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT DEFAULT 0,
        file_type VARCHAR(100) DEFAULT 'application/pdf',
        source_system VARCHAR(50) DEFAULT 'pcms',
        status VARCHAR(50) DEFAULT 'forwarded',
        lrs_response TEXT DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_reference (reference_number),
        INDEX idx_document_id (document_id),
        INDEX idx_consultation_id (consultation_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        error_log("Error creating document_versions table: " . $conn->error);
        return false;
    }

    return true;
}

/**
 * Add a version entry to document_versions
 */
function addDocumentVersion($data) {
    global $conn;
    initializeDocumentVersionsTable();

    $stmt = $conn->prepare("INSERT INTO document_versions 
        (document_id, consultation_id, reference_number, title, version_number, document_type, original_filename, stored_filename, file_path, file_size, file_type, source_system, status, lrs_response, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Error preparing addDocumentVersion statement: " . $conn->error);
        return false;
    }

    $doc_id = isset($data['document_id']) ? (int)$data['document_id'] : null;
    $consult_id = isset($data['consultation_id']) ? (int)$data['consultation_id'] : null;
    $ref_num = $data['reference_number'] ?? 'CONSULT-000000';
    $title = $data['title'] ?? 'Untitled Document';
    $ver_num = $data['version_number'] ?? '1.0';
    $doc_type = $data['document_type'] ?? 'consultation_summary';
    $orig_fn = $data['original_filename'] ?? 'document.pdf';
    $stored_fn = $data['stored_filename'] ?? 'document.pdf';
    $fp = $data['file_path'] ?? '';
    $fs = (int)($data['file_size'] ?? 0);
    $ft = $data['file_type'] ?? 'application/pdf';
    $src = $data['source_system'] ?? 'pcms';
    $status = $data['status'] ?? 'forwarded';
    $lrs_resp = $data['lrs_response'] ?? null;
    $notes = $data['notes'] ?? null;

    $stmt->bind_param(
        'iisssssssisssss',
        $doc_id,
        $consult_id,
        $ref_num,
        $title,
        $ver_num,
        $doc_type,
        $orig_fn,
        $stored_fn,
        $fp,
        $fs,
        $ft,
        $src,
        $status,
        $lrs_resp,
        $notes
    );

    if ($stmt->execute()) {
        $insert_id = $conn->insert_id;
        $stmt->close();
        return $insert_id;
    }

    error_log("Error executing addDocumentVersion statement: " . $stmt->error);
    $stmt->close();
    return false;
}

/**
 * Get all document versions or versions filtered by reference/document_id
 */
function getDocumentVersions($reference_number = null, $limit = 200, $offset = 0) {
    global $conn;
    initializeDocumentVersionsTable();

    if ($reference_number) {
        $stmt = $conn->prepare("SELECT * FROM document_versions WHERE reference_number = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('sii', $reference_number, $limit, $offset);
    } else {
        $stmt = $conn->prepare("SELECT * FROM document_versions ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('ii', $limit, $offset);
    }

    if (!$stmt) return [];
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/**
 * Forward a document to LRS (Legislative Records System) for archiving
 */
function forwardDocumentToLRS($id, $source = 'consultation', $customDescription = '') {
    global $conn;
    $id = (int)$id;
    if ($id <= 0) return ['success' => false, 'message' => 'Invalid document ID'];

    $source = strtolower(trim((string)$source)) === 'admin' ? 'admin' : 'consultation';

    $doc = null;
    $isConsultation = false;

    if ($source === 'admin') {
        $doc = getAdminDocumentById($id);
        if ($doc) {
            $isConsultation = false;
        } else {
            $doc = getDocumentById($id);
            if ($doc) $isConsultation = true;
        }
    } else {
        $doc = getDocumentById($id);
        if ($doc) {
            $isConsultation = true;
        } else {
            $doc = getAdminDocumentById($id);
            if ($doc) $isConsultation = false;
        }
    }

    if (!$doc) {
        return ['success' => false, 'message' => 'Document ID ' . $id . ' not found'];
    }

    $filePath = '';
    $origFilename = '';
    $storedFilename = '';
    $title = '';
    $reference = '';
    $docType = 'consultation_summary';
    $docDate = date('Y-m-d');
    $description = trim((string)$customDescription);

    if ($isConsultation) {
        $consultation_id = (int)($doc['consultation_id'] ?? 0);
        $title = !empty($doc['original_filename']) ? str_replace(['.pdf', '.docx', '_'], ['', '', ' '], $doc['original_filename']) : ('Consultation Document ' . ($doc['reference_number'] ?? $id));
        $reference = !empty($doc['reference_number']) ? $doc['reference_number'] : generateDocumentReference($id);
        $docType = !empty($doc['document_type']) ? $doc['document_type'] : 'consultation_summary';
        $docDate = !empty($doc['upload_date']) ? date('Y-m-d', strtotime($doc['upload_date'])) : date('Y-m-d');
        $storedFilename = $doc['stored_filename'] ?? '';
        $origFilename = $doc['original_filename'] ?? $storedFilename ?: 'document.pdf';
        $filePath = 'uploads/documents/' . $storedFilename;
        if (empty($description)) $description = $doc['description'] ?? 'Consultation summary document forwarded from PCMS';

        $absRoot = realpath(__DIR__ . '/../');
        $fullPath = $absRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            if ($consultation_id > 0) {
                require_once __DIR__ . '/../UTILS/generate_consultation_documents.php';
                if (function_exists('generateConsultationDocuments')) {
                    generateConsultationDocuments($consultation_id);
                    $refetchedDoc = getDocumentById($id);
                    if ($refetchedDoc && !empty($refetchedDoc['stored_filename'])) {
                        $storedFilename = $refetchedDoc['stored_filename'];
                        $filePath = 'uploads/documents/' . $storedFilename;
                        $fullPath = $absRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);
                    }
                }
            }
        }
    } else {
        $title = $doc['title'] ?? 'Document';
        $reference = !empty($doc['reference']) ? $doc['reference'] : 'DOC-' . sprintf('%06d', $id);
        $docType = !empty($doc['type']) ? $doc['type'] : 'consultation_summary';
        $docDate = !empty($doc['document_date']) ? date('Y-m-d', strtotime($doc['document_date'])) : date('Y-m-d');
        $filePath = $doc['file_path'] ?? '';
        $origFilename = pathinfo($filePath, PATHINFO_BASENAME) ?: 'document.pdf';
        $storedFilename = pathinfo($filePath, PATHINFO_BASENAME);
        if (empty($description)) $description = $doc['description'] ?? 'Admin document forwarded to LRS';

        $absRoot = realpath(__DIR__ . '/../');
        $fullPath = $absRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);
    }

    if (!file_exists($fullPath) || !is_file($fullPath)) {
        return ['success' => false, 'message' => 'Physical document file not found at: ' . $filePath];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($fullPath) ?: 'application/pdf';

    $lrsUrl = function_exists('app_env') ? app_env('LRS_RECEIVE_URL', 'https://llrm.spvalenzuela.com/modules/integration/api/receive_document.php') : 'https://llrm.spvalenzuela.com/modules/integration/api/receive_document.php';
    $apiKey = function_exists('app_env') ? app_env('LRS_API_KEY', 'pcm_f9e0185dca4546c83a1c5afa187ff10f') : 'pcm_f9e0185dca4546c83a1c5afa187ff10f';

    $lrsDocType = !empty($docType) ? $docType : 'consultation_summary';
    $lowType = strtolower(trim((string)$docType));
    if (strpos($lowType, 'ordinance') !== false) {
        $lrsDocType = 'Ordinance';
    } elseif (strpos($lowType, 'resolution') !== false) {
        $lrsDocType = 'Resolution';
    } elseif (strpos($lowType, 'report') !== false) {
        $lrsDocType = 'Committee Report';
    }

    $cFile = new CURLFile($fullPath, $mimeType, $origFilename);

    $postFields = [
        'title' => $title,
        'document_type' => $lrsDocType,
        'source_system' => 'pcms',
        'external_id' => $reference,
        'document_date' => $docDate,
        'description' => $description,
        'file' => $cFile
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $lrsUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => [
            'X-API-Key: ' . $apiKey
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $responseBody = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $lrsResponseDecoded = null;
    if ($responseBody) {
        $lrsResponseDecoded = json_decode($responseBody, true);
    }

    initializeDocumentVersionsTable();
    $verId = addDocumentVersion([
        'document_id' => $isConsultation ? $id : null,
        'consultation_id' => $doc['consultation_id'] ?? null,
        'reference_number' => $reference,
        'title' => $title,
        'version_number' => '1.0',
        'document_type' => $docType,
        'original_filename' => $origFilename,
        'stored_filename' => $storedFilename,
        'file_path' => $filePath,
        'file_size' => filesize($fullPath),
        'file_type' => $mimeType,
        'source_system' => 'pcms',
        'status' => ($httpCode >= 200 && $httpCode < 300) ? 'forwarded_to_lrs' : 'forward_failed',
        'lrs_response' => $responseBody ?: $curlErr,
        'notes' => 'Forwarded to LRS via API (HTTP ' . $httpCode . ')'
    ]);

    if ($isConsultation) {
        updateDocumentStatus($id, 'forwarded_to_lrs');
    } else {
        updateDocument($id, $reference, $title, $docType, 'forwarded_to_lrs', $docDate, $description, $doc['tags'] ?? '');
    }

    if ($curlErr) {
        return [
            'success' => false,
            'message' => 'cURL Error sending document to LRS: ' . $curlErr,
            'version_id' => $verId
        ];
    }

    return [
        'success' => true,
        'message' => 'Document successfully forwarded to LRS',
        'http_code' => $httpCode,
        'lrs_response' => $lrsResponseDecoded ?: $responseBody,
        'version_id' => $verId
    ];
}
?>

