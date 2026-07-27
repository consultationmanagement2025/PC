<?php
require_once '../db.php';
require_once '../config/email_config.php';

function initializeNotificationsTable() {
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message LONGTEXT NOT NULL,
        type VARCHAR(100) DEFAULT 'info',
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
        error_log('Failed to create notifications table: ' . $conn->error);
        return false;
    }
    return true;
}

function createNotification($user_id, $message, $type = 'info') {
    global $conn;
    initializeNotificationsTable();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param('iss', $user_id, $message, $type);
    $res = $stmt->execute();
    $insertId = $conn->insert_id;
    $stmt->close();
    
    // Send email notification if user has email
    if ($res && $user_id) {
        $user_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        if ($user_stmt) {
            $user_stmt->bind_param('i', $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user = $user_result->fetch_assoc();
            $user_stmt->close();
            
            if ($user && !empty($user['email'])) {
                $email_subject = "Valenzuela City - New Notification";
                $email_body = "You have a new notification from Valenzuela City Government:\n\n" . $message . "\n\nPlease log in to your account for more details.";
                sendGmailEmail($user['email'], $email_subject, $email_body, false);
            }
        }
    }
    
    return $res ? $insertId : false;
}

function getUserNotifications($user_id, $limit = 20) {
    global $conn;
    initializeNotificationsTable();
    $stmt = $conn->prepare("SELECT id, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    if (!$stmt) return [];
    $stmt->bind_param('ii', $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

?>
