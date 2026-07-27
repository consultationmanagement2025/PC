<?php
require_once __DIR__ . '/../db.php';

// Initialize communication table
function initializeCommunicationTable() {
    global $conn;
    
    $sql = "CREATE TABLE IF NOT EXISTS consultation_communication (
        id INT PRIMARY KEY AUTO_INCREMENT,
        consultation_id INT NOT NULL,
        sender_type ENUM('admin', 'user') NOT NULL,
        sender_id INT NOT NULL,
        sender_name VARCHAR(255) NOT NULL,
        sender_email VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        message_type ENUM('initial_review', 'admin_response', 'user_reply', 'status_update') DEFAULT 'admin_response',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
        INDEX idx_consultation_id (consultation_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        // Add column to consultations table for communication status
        $conn->query("ALTER TABLE consultations ADD COLUMN IF NOT EXISTS has_communication TINYINT(1) DEFAULT 0");
        $conn->query("ALTER TABLE consultations ADD COLUMN IF NOT EXISTS last_communication_at DATETIME NULL");
        return true;
    } else {
        error_log("Error creating communication table: " . $conn->error);
        return false;
    }
}

// Send message from admin to user
function sendAdminMessage($consultation_id, $admin_id, $admin_name, $admin_email, $message, $message_type = 'admin_response') {
    global $conn;
    
    initializeCommunicationTable();
    
    $stmt = $conn->prepare("INSERT INTO consultation_communication (consultation_id, sender_type, sender_id, sender_name, sender_email, message, message_type) VALUES (?, 'admin', ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Error preparing admin message: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param('isssss', $consultation_id, $admin_id, $admin_name, $admin_email, $message, $message_type);
    
    if ($stmt->execute()) {
        // Update consultation record
        $updateStmt = $conn->prepare("UPDATE consultations SET has_communication = 1, last_communication_at = NOW() WHERE id = ?");
        if ($updateStmt) {
            $updateStmt->bind_param('i', $consultation_id);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        $stmt->close();
        return true;
    }
    
    error_log("Error sending admin message: " . $stmt->error);
    $stmt->close();
    return false;
}

// Send message from user to admin
function sendUserMessage($consultation_id, $user_id, $user_name, $user_email, $message) {
    global $conn;
    
    initializeCommunicationTable();
    
    $stmt = $conn->prepare("INSERT INTO consultation_communication (consultation_id, sender_type, sender_id, sender_name, sender_email, message, message_type) VALUES (?, 'user', ?, ?, ?, ?, 'user_reply')");
    if (!$stmt) {
        error_log("Error preparing user message: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param('issss', $consultation_id, $user_id, $user_name, $user_email, $message);
    
    if ($stmt->execute()) {
        // Update consultation record
        $updateStmt = $conn->prepare("UPDATE consultations SET has_communication = 1, last_communication_at = NOW() WHERE id = ?");
        if ($updateStmt) {
            $updateStmt->bind_param('i', $consultation_id);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        $stmt->close();
        return true;
    }
    
    error_log("Error sending user message: " . $stmt->error);
    $stmt->close();
    return false;
}

// Get communication thread for a consultation
function getCommunicationThread($consultation_id) {
    global $conn;
    
    initializeCommunicationTable();
    
    $stmt = $conn->prepare("SELECT * FROM consultation_communication WHERE consultation_id = ? ORDER BY created_at ASC");
    if (!$stmt) {
        error_log("Error preparing communication thread: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param('i', $consultation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    $stmt->close();
    return $messages;
}

// Mark messages as read
function markMessagesAsRead($consultation_id, $sender_type) {
    global $conn;
    
    initializeCommunicationTable();
    
    // Mark messages from opposite sender as read
    $opposite_type = ($sender_type === 'admin') ? 'user' : 'admin';
    $stmt = $conn->prepare("UPDATE consultation_communication SET is_read = 1 WHERE consultation_id = ? AND sender_type = ? AND is_read = 0");
    if (!$stmt) {
        error_log("Error marking messages as read: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param('is', $consultation_id, $opposite_type);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

// Get unread message count for admin
function getUnreadMessageCount($sender_type = 'user') {
    global $conn;
    
    initializeCommunicationTable();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM consultation_communication WHERE sender_type = ? AND is_read = 0");
    if (!$stmt) {
        error_log("Error getting unread count: " . $conn->error);
        return 0;
    }
    
    $stmt->bind_param('s', $sender_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    return $count;
}

// Get consultations with pending communication
function getConsultationsWithCommunication($status = 'pending') {
    global $conn;
    
    initializeCommunicationTable();
    
    $stmt = $conn->prepare("SELECT c.*, cc.created_at as last_communication, 
                           (SELECT COUNT(*) FROM consultation_communication WHERE consultation_id = c.id AND sender_type = 'user' AND is_read = 0) as unread_user_messages
                           FROM consultations c 
                           LEFT JOIN consultation_communication cc ON c.id = cc.id 
                           WHERE c.status = ? AND c.has_communication = 1 
                           ORDER BY c.last_communication_at DESC");
    if (!$stmt) {
        error_log("Error getting consultations with communication: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $consultations = [];
    while ($row = $result->fetch_assoc()) {
        $consultations[] = $row;
    }
    
    $stmt->close();
    return $consultations;
}
?>
