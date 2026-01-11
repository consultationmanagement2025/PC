<?php
/**
 * Audit Log System for PCMP
 * IMPORTANT: This logs ADMIN ACTIONS ONLY (user management, consultation creation, post approval, etc.)
 * Do NOT use this for citizen activity logging - use DATABASE/user-logs.php instead
 * 
 * Purpose: Track what admins did to the system (security, compliance, accountability)
 * Examples:
 * - Admin login/logout
 * - Admin creates/edits/deletes consultation
 * - Admin creates/edits/deletes user
 * - Admin changes user roles or status
 * - Admin approves/rejects posts
 */

require_once __DIR__ . '/../db.php';

// ========================================
// Initialize Audit Logs Table (if not exists)
// ========================================
function initializeAuditTable() {
    global $conn;
    
    $createTableSQL = "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_user VARCHAR(255) NOT NULL,
        admin_id INT,
        action VARCHAR(500) NOT NULL,
        entity_type VARCHAR(100),
        entity_id INT,
        old_value LONGTEXT,
        new_value LONGTEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(50) DEFAULT 'success',
        details LONGTEXT,
        INDEX idx_admin_id (admin_id),
        INDEX idx_timestamp (timestamp),
        INDEX idx_action (action),
        INDEX idx_entity (entity_type, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($createTableSQL)) {
        error_log("Failed to create audit_logs table: " . $conn->error);
        return false;
    }
    
    return true;
}

// ========================================
// Log an Action
// ========================================
function logAction($admin_id, $admin_user, $action, $entity_type = null, $entity_id = null, $old_value = null, $new_value = null, $status = 'success', $details = null) {
    global $conn;
    
    // Ensure table exists
    initializeAuditTable();
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (
            admin_id, admin_user, action, entity_type, entity_id, 
            old_value, new_value, ip_address, user_agent, status, details
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        error_log("Audit log prepare error: " . $conn->error);
        return false;
    }
    
    // Bind parameters dynamically to avoid argument count mismatches
    $types = '';
    $params = [];

    // admin_id (int or null)
    $types .= 'i';
    $params[] = $admin_id;

    // admin_user, action, entity_type
    $types .= 'sss';
    $params[] = $admin_user;
    $params[] = $action;
    $params[] = $entity_type;

    // entity_id (int or null)
    $types .= 'i';
    $params[] = $entity_id;

    // old_value, new_value, ip_address, user_agent, status, details
    $types .= 'ssssss';
    $params[] = $old_value;
    $params[] = $new_value;
    $params[] = $ip_address;
    $params[] = $user_agent;
    $params[] = $status;
    $params[] = $details;

    // Prepare arguments as references
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_names[] = & $params[$i];
    }

    call_user_func_array([$stmt, 'bind_param'], $bind_names);
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

// ========================================
// Get Audit Logs
// ========================================
function getAuditLogs($limit = 100, $offset = 0, $filters = []) {
    global $conn;
    
    $query = "SELECT * FROM audit_logs WHERE 1=1";
    $params = [];
    $types = "";
    
    // Filter by admin user
    if (!empty($filters['admin_user'])) {
        $query .= " AND admin_user LIKE ?";
        $params[] = '%' . $filters['admin_user'] . '%';
        $types .= "s";
    }
    
    // Filter by action
    if (!empty($filters['action'])) {
        $query .= " AND action LIKE ?";
        $params[] = '%' . $filters['action'] . '%';
        $types .= "s";
    }
    
    // Filter by entity type
    if (!empty($filters['entity_type'])) {
        $query .= " AND entity_type = ?";
        $params[] = $filters['entity_type'];
        $types .= "s";
    }
    
    // Filter by date range
    if (!empty($filters['start_date'])) {
        $query .= " AND DATE(timestamp) >= ?";
        $params[] = $filters['start_date'];
        $types .= "s";
    }
    
    if (!empty($filters['end_date'])) {
        $query .= " AND DATE(timestamp) <= ?";
        $params[] = $filters['end_date'];
        $types .= "s";
    }
    
    // Sort by newest first and limit
    $query .= " ORDER BY timestamp DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Audit log query error: " . $conn->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $logs;
}

// ========================================
// Get Audit Log Count
// ========================================
function getAuditLogCount($filters = []) {
    global $conn;
    
    $query = "SELECT COUNT(*) as total FROM audit_logs WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($filters['admin_user'])) {
        $query .= " AND admin_user LIKE ?";
        $params[] = '%' . $filters['admin_user'] . '%';
        $types .= "s";
    }
    
    if (!empty($filters['action'])) {
        $query .= " AND action LIKE ?";
        $params[] = '%' . $filters['action'] . '%';
        $types .= "s";
    }
    
    if (!empty($filters['entity_type'])) {
        $query .= " AND entity_type = ?";
        $params[] = $filters['entity_type'];
        $types .= "s";
    }
    
    if (!empty($filters['start_date'])) {
        $query .= " AND DATE(timestamp) >= ?";
        $params[] = $filters['start_date'];
        $types .= "s";
    }
    
    if (!empty($filters['end_date'])) {
        $query .= " AND DATE(timestamp) <= ?";
        $params[] = $filters['end_date'];
        $types .= "s";
    }
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return 0;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['total'] ?? 0;
}

// ========================================
// Get Admin Logs Only
// ========================================
function getAdminAuditLogs($limit = 100, $offset = 0, $filters = []) {
    global $conn;
    
    $query = "SELECT * FROM audit_logs WHERE admin_id IS NOT NULL";
    $params = [];
    $types = "";
    
    // Filter by admin user
    if (!empty($filters['admin_user'])) {
        $query .= " AND admin_user LIKE ?";
        $params[] = '%' . $filters['admin_user'] . '%';
        $types .= "s";
    }
    
    // Filter by action
    if (!empty($filters['action'])) {
        $query .= " AND action LIKE ?";
        $params[] = '%' . $filters['action'] . '%';
        $types .= "s";
    }
    
    // Filter by entity type
    if (!empty($filters['entity_type'])) {
        $query .= " AND entity_type = ?";
        $params[] = $filters['entity_type'];
        $types .= "s";
    }
    
    // Sort by newest first and limit
    $query .= " ORDER BY timestamp DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Admin audit log query error: " . $conn->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $logs;
}

// ========================================
// Get User Activity Logs Only
// ========================================
function getUserActivityLogs($limit = 100, $offset = 0, $filters = []) {
    global $conn;
    
    $query = "SELECT * FROM audit_logs WHERE (admin_id IS NULL OR action IN ('Posted Post', 'Posted Suggestion'))";
    $params = [];
    $types = "";
    
    // Filter by action
    if (!empty($filters['action'])) {
        $query .= " AND action LIKE ?";
        $params[] = '%' . $filters['action'] . '%';
        $types .= "s";
    }
    
    // Filter by entity type
    if (!empty($filters['entity_type'])) {
        $query .= " AND entity_type = ?";
        $params[] = $filters['entity_type'];
        $types .= "s";
    }
    
    // Sort by newest first and limit
    $query .= " ORDER BY timestamp DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("User activity log query error: " . $conn->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $logs;
}

// ========================================
// Get Count for Admin Logs
// ========================================
function getAdminAuditLogCount($filters = []) {
    global $conn;
    
    $query = "SELECT COUNT(*) as total FROM audit_logs WHERE admin_id IS NOT NULL";
    $params = [];
    $types = "";
    
    if (!empty($filters['admin_user'])) {
        $query .= " AND admin_user LIKE ?";
        $params[] = '%' . $filters['admin_user'] . '%';
        $types .= "s";
    }
    
    if (!empty($filters['action'])) {
        $query .= " AND action LIKE ?";
        $params[] = '%' . $filters['action'] . '%';
        $types .= "s";
    }
    
    if (!empty($filters['entity_type'])) {
        $query .= " AND entity_type = ?";
        $params[] = $filters['entity_type'];
        $types .= "s";
    }
    
    $stmt = $conn->prepare($query);
    if (!$stmt) return 0;
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['total'] ?? 0;
}

// ========================================
// Get Count for User Activity Logs
// ========================================
function getUserActivityLogCount($filters = []) {
    global $conn;
    
    $query = "SELECT COUNT(*) as total FROM audit_logs WHERE (admin_id IS NULL OR action IN ('Posted Post', 'Posted Suggestion'))";
    $params = [];
    $types = "";
    
    if (!empty($filters['action'])) {
        $query .= " AND action LIKE ?";
        $params[] = '%' . $filters['action'] . '%';
        $types .= "s";
    }
    
    if (!empty($filters['entity_type'])) {
        $query .= " AND entity_type = ?";
        $params[] = $filters['entity_type'];
        $types .= "s";
    }
    
    $stmt = $conn->prepare($query);
    if (!$stmt) return 0;
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['total'] ?? 0;
}

// ========================================
// Common Action Loggers
// ========================================

function logUserDeletion($admin_id, $admin_user, $user_id, $user_email, $user_fullname) {
    return logAction(
        $admin_id,
        $admin_user,
        "Deleted User",
        "user",
        $user_id,
        null,
        null,
        'success',
        "Deleted user: $user_fullname ($user_email)"
    );
}

function logUserCreation($admin_id, $admin_user, $user_id, $user_email, $user_fullname, $role) {
    return logAction(
        $admin_id,
        $admin_user,
        "Created User",
        "user",
        $user_id,
        null,
        json_encode(['email' => $user_email, 'fullname' => $user_fullname, 'role' => $role]),
        'success',
        "Created user: $user_fullname ($user_email) with role: $role"
    );
}

function logDocumentUpload($admin_id, $admin_user, $doc_id, $doc_name, $doc_type) {
    return logAction(
        $admin_id,
        $admin_user,
        "Uploaded Document",
        "document",
        $doc_id,
        null,
        json_encode(['name' => $doc_name, 'type' => $doc_type]),
        'success',
        "Uploaded document: $doc_name (ID: $doc_id)"
    );
}

function logDocumentDeletion($admin_id, $admin_user, $doc_id, $doc_name) {
    return logAction(
        $admin_id,
        $admin_user,
        "Deleted Document",
        "document",
        $doc_id,
        null,
        null,
        'success',
        "Deleted document: $doc_name (ID: $doc_id)"
    );
}

function logConsultationCreation($admin_id, $admin_user, $consultation_id, $title) {
    return logAction(
        $admin_id,
        $admin_user,
        "Created Consultation",
        "consultation",
        $consultation_id,
        null,
        json_encode(['title' => $title]),
        'success',
        "Created consultation: $title"
    );
}

function logConsultationUpdate($admin_id, $admin_user, $consultation_id, $title, $changes) {
    return logAction(
        $admin_id,
        $admin_user,
        "Updated Consultation",
        "consultation",
        $consultation_id,
        null,
        json_encode($changes),
        'success',
        "Updated consultation: $title with changes: " . json_encode($changes)
    );
}

function logConsultationDeletion($admin_id, $admin_user, $consultation_id, $title) {
    return logAction(
        $admin_id,
        $admin_user,
        "Deleted Consultation",
        "consultation",
        $consultation_id,
        null,
        null,
        'success',
        "Deleted consultation: $title"
    );
}

function logAdminLogin($admin_id, $admin_user) {
    return logAction(
        $admin_id,
        $admin_user,
        "Admin Login",
        "system",
        null,
        null,
        null,
        'success',
        "Admin user logged in"
    );
}

function logAdminLogout($admin_id, $admin_user) {
    return logAction(
        $admin_id,
        $admin_user,
        "Admin Logout",
        "system",
        null,
        null,
        null,
        'success',
        "Admin user logged out"
    );
}

// Initialize table on include
initializeAuditTable();
?>
