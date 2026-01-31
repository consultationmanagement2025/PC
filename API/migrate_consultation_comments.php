<?php
// migration for consultation_comments table
require_once __DIR__ . '/../db.php';

$sql = "CREATE TABLE IF NOT EXISTS consultation_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_consultation_id (consultation_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "consultation_comments table ready.";
} else {
    echo "Error: " . $conn->error;
}
