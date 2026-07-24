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
    $valid_statuses = ['draft', 'submitted', 'reviewed', 'approved', 'rejected'];
    
    if (!in_array($status, $valid_statuses)) {
        throw new Exception('Invalid status');
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
?>
