<?php
require_once 'db.php';

function initializePostsTable() {
    global $conn;
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
