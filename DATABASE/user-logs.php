<?php
/**
 * User Activity Logging System for PCMP
 * IMPORTANT: This logs CITIZEN ACTIONS ONLY (not admin actions)
 * Admin actions should go to DATABASE/audit-log.php via logAction() function
 * 
 * Purpose: Track citizen engagement and activity (metrics, moderation, posts)
 * Examples:
 * - Citizen login/logout
 * - Citizen creates post
 * - Citizen submits feedback
 * - Citizen comments on post
 * 
 * Do NOT log here:
 * - Admin logins/logouts
 * - Admin user management actions
 * - Admin consultation management
 */

require_once __DIR__ . '/../db.php';

// ========================================
// Initialize User Logs Table (if not exists)
// ========================================
function initializeUserLogsTable() {
    global $conn;
    
    $createTableSQL = "CREATE TABLE IF NOT EXISTS user_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        username VARCHAR(255) NOT NULL,
        action VARCHAR(500) NOT NULL,
        action_type VARCHAR(100),
        entity_type VARCHAR(100),
        entity_id INT,
        description LONGTEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(50) DEFAULT 'success',
        details LONGTEXT,
        INDEX idx_user_id (user_id),
        INDEX idx_timestamp (timestamp),
        INDEX idx_action (action),
        INDEX idx_entity (entity_type, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($createTableSQL)) {
        error_log("Failed to create user_logs table: " . $conn->error);
        return false;
    }
    
    return true;
}

// ========================================
// Log a User Action (CITIZENS ONLY - NOT ADMINS)
// ========================================
function logUserAction($user_id, $username, $action, $action_type = null, $entity_type = null, $entity_id = null, $description = null, $status = 'success', $details = null) {
    global $conn;
    
    // IMPORTANT: Only log citizen actions, NOT admin actions
    // Admin actions should be logged to audit_logs instead via logAction()
    // Exclude admin login/logout - those go to audit log
    if (($action === 'login' || $action === 'logout') && $user_id) {
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if ($row['role'] === 'Administrator') {
                    $stmt->close();
                    return true; // Skip - will be logged in audit log instead
                }
            }
            $stmt->close();
        }
    }
    
    // Ensure table exists
    initializeUserLogsTable();
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    
    $stmt = $conn->prepare("
        INSERT INTO user_logs (
            user_id, username, action, action_type, entity_type, entity_id, 
            description, ip_address, user_agent, status, details
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        error_log("User log prepare error: " . $conn->error);
        return false;
    }
    
    $types = 'isssssssss';
    $bind_names = [$types];
    $bind_names[] = &$user_id;
    $bind_names[] = &$username;
    $bind_names[] = &$action;
    $bind_names[] = &$action_type;
    $bind_names[] = &$entity_type;
    $bind_names[] = &$entity_id;
    $bind_names[] = &$description;
    $bind_names[] = &$ip_address;
    $bind_names[] = &$user_agent;
    $bind_names[] = &$status;
    $bind_names[] = &$details;
    
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
    
    if (!$stmt->execute()) {
        error_log("User log insert error: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
}

// ========================================
// Get User Logs
// ========================================
function getUserLogs($limit = 100, $offset = 0, $filters = []) {
    global $conn;
    
    // Ensure table exists
    initializeUserLogsTable();
    
    $query = "SELECT * FROM user_logs WHERE 1=1";
    $params = [];
    $types = '';
    
    if (!empty($filters['user_id'])) {
        $query .= " AND user_id = ?";
        $types .= 'i';
        $params[] = $filters['user_id'];
    }
    
    if (!empty($filters['username'])) {
        $query .= " AND username LIKE ?";
        $types .= 's';
        $params[] = '%' . $filters['username'] . '%';
    }
    
    if (!empty($filters['action'])) {
        $query .= " AND action = ?";
        $types .= 's';
        $params[] = $filters['action'];
    }
    
    if (!empty($filters['entity_type'])) {
        $query .= " AND entity_type = ?";
        $types .= 's';
        $params[] = $filters['entity_type'];
    }
    
    if (!empty($filters['date'])) {
        $query .= " AND DATE(timestamp) = ?";
        $types .= 's';
        $params[] = $filters['date'];
    }
    
    $query .= " ORDER BY timestamp DESC LIMIT ? OFFSET ?";
    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("User logs query error: " . $conn->error);
        return [];
    }
    
    if (!empty($types)) {
        $bind_names = [$types];
        for ($i = 0; $i < count($params); $i++) {
            $bind_names[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    
    if (!$stmt->execute()) {
        error_log("User logs execute error: " . $stmt->error);
        $stmt->close();
        return [];
    }
    
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $logs;
}

// ========================================
// Get User Logs Count
// ========================================
function getUserLogsCount($filters = []) {
    global $conn;
    
    // Ensure table exists
    initializeUserLogsTable();
    
    $query = "SELECT COUNT(*) as count FROM user_logs WHERE 1=1";
    $params = [];
    $types = '';
    
    if (!empty($filters['user_id'])) {
        $query .= " AND user_id = ?";
        $types .= 'i';
        $params[] = $filters['user_id'];
    }
    
    if (!empty($filters['username'])) {
        $query .= " AND username LIKE ?";
        $types .= 's';
        $params[] = '%' . $filters['username'] . '%';
    }
    
    if (!empty($filters['action'])) {
        $query .= " AND action = ?";
        $types .= 's';
        $params[] = $filters['action'];
    }
    
    if (!empty($filters['entity_type'])) {
        $query .= " AND entity_type = ?";
        $types .= 's';
        $params[] = $filters['entity_type'];
    }
    
    if (!empty($filters['date'])) {
        $query .= " AND DATE(timestamp) = ?";
        $types .= 's';
        $params[] = $filters['date'];
    }
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("User logs count error: " . $conn->error);
        return 0;
    }
    
    if (!empty($types)) {
        $bind_names = [$types];
        for ($i = 0; $i < count($params); $i++) {
            $bind_names[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    
    if (!$stmt->execute()) {
        error_log("User logs count execute error: " . $stmt->error);
        $stmt->close();
        return 0;
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'] ?? 0;
}

// ========================================
// Get User Activity Summary
// ========================================
function getUserActivitySummary() {
    global $conn;
    
    // Ensure table exists
    initializeUserLogsTable();
    
    $today = date('Y-m-d');
    
    $query = "SELECT 
        COUNT(*) as total_actions,
        COUNT(DISTINCT user_id) as active_users,
        COUNT(CASE WHEN DATE(timestamp) = ? THEN 1 END) as today_actions
    FROM user_logs";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Activity summary error: " . $conn->error);
        return ['total_actions' => 0, 'active_users' => 0, 'today_actions' => 0];
    }
    
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    $stmt->close();
    
    return $summary ?: ['total_actions' => 0, 'active_users' => 0, 'today_actions' => 0];
}

// ========================================
// Get Action Statistics
// ========================================
function getActionStats() {
    global $conn;
    
    // Ensure table exists
    initializeUserLogsTable();
    
    $query = "SELECT 
        action,
        COUNT(*) as count
    FROM user_logs
    GROUP BY action
    ORDER BY count DESC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        error_log("Action stats error: " . $conn->error);
        return [];
    }
    
    $stats = $result->fetch_all(MYSQLI_ASSOC);
    return $stats;
}
?>
