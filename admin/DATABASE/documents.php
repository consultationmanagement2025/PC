<?php
require_once __DIR__ . '/../db.php';

function initializeAdminDocumentsTable() {
    global $conn;

    $sql = "CREATE TABLE IF NOT EXISTS admin_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reference VARCHAR(100) DEFAULT '',
        title VARCHAR(255) NOT NULL,
        type VARCHAR(50) DEFAULT 'ordinance',
        status VARCHAR(50) DEFAULT 'draft',
        document_date DATE DEFAULT NULL,
        description LONGTEXT,
        tags TEXT,
        uploaded_by VARCHAR(255) DEFAULT NULL,
        file_path VARCHAR(500) DEFAULT NULL,
        file_size VARCHAR(50) DEFAULT NULL,
        views INT DEFAULT 0,
        downloads INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_type (type),
        INDEX idx_status (status),
        INDEX idx_document_date (document_date),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        error_log('Failed to create documents table: ' . $conn->error);
        return false;
    }
    return true;
}

function getDocuments($limit = 200, $offset = 0) {
    global $conn;
    initializeAdminDocumentsTable();

    $stmt = $conn->prepare("SELECT id, reference, title, type, status, document_date, description, tags, uploaded_by, file_path, file_size, views, downloads, created_at, updated_at FROM admin_documents ORDER BY created_at DESC LIMIT ? OFFSET ?");
    if (!$stmt) return [];
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function createDocument($reference, $title, $type, $status, $document_date, $description, $tags, $uploaded_by, $file_path, $file_size) {
    global $conn;
    initializeAdminDocumentsTable();

    $stmt = $conn->prepare("INSERT INTO admin_documents (reference, title, type, status, document_date, description, tags, uploaded_by, file_path, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Error preparing createDocument: " . $conn->error);
        return false;
    }

    $stmt->bind_param('ssssssssss', $reference, $title, $type, $status, $document_date, $description, $tags, $uploaded_by, $file_path, $file_size);
    if ($stmt->execute()) {
        $id = $conn->insert_id;
        $stmt->close();
        return $id;
    }

    error_log("Error creating document: " . $stmt->error);
    $stmt->close();
    return false;
}

function updateDocument($id, $reference, $title, $type, $status, $document_date, $description, $tags) {
    global $conn;
    initializeAdminDocumentsTable();

    $stmt = $conn->prepare("UPDATE admin_documents SET reference = ?, title = ?, type = ?, status = ?, document_date = ?, description = ?, tags = ? WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('sssssssi', $reference, $title, $type, $status, $document_date, $description, $tags, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function deleteAdminDocumentById($id) {
    global $conn;
    initializeAdminDocumentsTable();

    $stmt = $conn->prepare("SELECT file_path FROM admin_documents WHERE id = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM admin_documents WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok && $row && !empty($row['file_path'])) {
        $abs = realpath(__DIR__ . '/../');
        $candidate = $abs . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$row['file_path']);
        if (is_file($candidate)) {
            @unlink($candidate);
        }
    }

    return (bool)$ok;
}

function incrementAdminDocumentDownloads($id) {
    global $conn;
    initializeAdminDocumentsTable();
    $stmt = $conn->prepare("UPDATE admin_documents SET downloads = downloads + 1 WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function incrementAdminDocumentViews($id) {
    global $conn;
    initializeAdminDocumentsTable();
    $stmt = $conn->prepare("UPDATE admin_documents SET views = views + 1 WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function getAdminDocumentById($id) {
    global $conn;
    initializeAdminDocumentsTable();
    $stmt = $conn->prepare("SELECT id, reference, title, type, status, document_date, description, tags, uploaded_by, file_path, file_size, views, downloads, created_at, updated_at FROM admin_documents WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function getConsultationDocumentsForAdminList($limit = 200, $offset = 0) {
    global $conn;

    require_once __DIR__ . '/document-management.php';
    if (function_exists('initializeDocumentsTable')) {
        initializeDocumentsTable();
    }

    $stmt = $conn->prepare("
        SELECT
            d.id,
            d.reference_number as reference,
            CONCAT('Consultation: ', c.title) as title,
            CASE d.document_type
                WHEN 'consultation_form' THEN 'consultation_form'
                WHEN 'attachment' THEN 'attachment'
                WHEN 'response' THEN 'response'
                WHEN 'final_document' THEN 'final_document'
                ELSE d.document_type
            END as type,
            d.status,
            d.upload_date as document_date,
            d.description,
            u.fullname as uploaded_by,
            u.role as uploader_role,
            CONCAT('uploads/documents/', d.stored_filename) as file_path,
            d.file_size,
            0 as views,
            0 as downloads,
            d.upload_date as created_at,
            d.upload_date as updated_at,
            d.original_filename,
            d.stored_filename
        FROM documents d
        LEFT JOIN consultations c ON d.consultation_id = c.id
        LEFT JOIN users u ON d.uploaded_by = u.id
        WHERE d.consultation_id > 0 AND c.id IS NOT NULL
        ORDER BY d.upload_date DESC
        LIMIT ? OFFSET ?
    ");

    if (!$stmt) {
        error_log("Error preparing getConsultationDocuments: " . $conn->error);
        return [];
    }

    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}
