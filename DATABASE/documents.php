<?php
require_once __DIR__ . '/../db.php';

function initializeDocumentsTable() {
    global $conn;

    $sql = "CREATE TABLE IF NOT EXISTS documents (
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
    initializeDocumentsTable();

    $stmt = $conn->prepare("SELECT id, reference, title, type, status, document_date, description, tags, uploaded_by, file_path, file_size, views, downloads, created_at, updated_at FROM documents ORDER BY created_at DESC LIMIT ? OFFSET ?");
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

?>
