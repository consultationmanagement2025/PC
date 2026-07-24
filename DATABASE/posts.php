<?php
require_once __DIR__ . '/../db.php';

function initializePostsTable() {
    global $conn;
    
    // First create the base posts table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        author VARCHAR(255),
        content LONGTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_at (created_at),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        error_log('Failed to create posts table: ' . $conn->error);
        return false;
    }
    
    // Add columns for consultation support if they don't exist
    $checkConsultation = $conn->query("SHOW COLUMNS FROM posts LIKE 'consultation_id'");
    if ($checkConsultation && $checkConsultation->num_rows === 0) {
        $conn->query("ALTER TABLE posts ADD COLUMN consultation_id INT DEFAULT NULL");
        $conn->query("ALTER TABLE posts ADD INDEX idx_consultation (consultation_id)");
    }
    
    $checkStatus = $conn->query("SHOW COLUMNS FROM posts LIKE 'status'");
    if ($checkStatus && $checkStatus->num_rows === 0) {
        $conn->query("ALTER TABLE posts ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
    }
    
    $checkCategory = $conn->query("SHOW COLUMNS FROM posts LIKE 'category'");
    if ($checkCategory && $checkCategory->num_rows === 0) {
        $conn->query("ALTER TABLE posts ADD COLUMN category VARCHAR(100) DEFAULT 'General'");
    }
    
    return true;
}

function createPost($user_id, $author, $content) {
    global $conn;
    initializePostsTable();
    $stmt = $conn->prepare("INSERT INTO posts (user_id, author, content) VALUES (?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param('iss', $user_id, $author, $content);
    $res = $stmt->execute();
    $insertId = $conn->insert_id;
    $stmt->close();
    return $res ? $insertId : false;
}

function getPosts($limit = 50, $offset = 0) {
    global $conn;
    initializePostsTable();
    $stmt = $conn->prepare("SELECT id, user_id, author, content, created_at FROM posts ORDER BY created_at DESC LIMIT ? OFFSET ?");
    if (!$stmt) return [];
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getPostById($id) {
    global $conn;
    initializePostsTable();
    $stmt = $conn->prepare("SELECT id, user_id, author, content, created_at FROM posts WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

?>
