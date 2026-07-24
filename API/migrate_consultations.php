<?php
// migration for consultations table
require_once __DIR__ . '/../db.php';

$sql = "CREATE TABLE IF NOT EXISTS consultations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    topic VARCHAR(255) NOT NULL,
    description TEXT,
    preferred_datetime DATETIME,
    status ENUM('pending','approved','disapproved') DEFAULT 'pending',
    scheduled_datetime DATETIME DEFAULT NULL,
    admin_note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "consultations table ready.";
} else {
    echo "Error: " . $conn->error;
}
