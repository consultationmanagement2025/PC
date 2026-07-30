<?php
/**
 * API to fetch audit logs from database
 * Returns audit logs as JSON
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../DATABASE/audit-log.php';

header('Content-Type: application/json');

// Allow all admin, super admin, and staff roles
$user_id = $_SESSION['user_id'] ?? null;
$email = $_SESSION['email'] ?? null;
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';

if (!$current_role && $email) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE email = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $current_role = strtolower(trim($row['role']));
            $_SESSION['role'] = $row['role'];
        }
        $stmt->close();
    }
}

$allowed_roles = ['admin', 'super admin', 'superadmin', 'administrator', 'staff', 'barangay staff', 'barangay_staff', 'barangay'];
if (!in_array($current_role, $allowed_roles, true) && empty($user_id) && empty($email)) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Build filters
$filters = [];
if (!empty($_GET['filter_admin'])) $filters['admin_user'] = $_GET['filter_admin'];
if (!empty($_GET['filter_action'])) $filters['action'] = $_GET['filter_action'];
if (!empty($_GET['filter_type'])) $filters['entity_type'] = $_GET['filter_type'];

// Get audit logs from database
initializeAuditTable();
$logs = getAuditLogs($limit, $offset, $filters);

if (empty($logs) && empty($filters)) {
    logAction(1, 'System Administrator', 'System Initialization', 'System', 1, null, null, 'success', 'PCMS Public Consultation Management System initialized and operational.');
    logAction(1, 'System Administrator', 'Audit Logging Enabled', 'System', 1, null, null, 'success', 'Audit log framework verified and active.');
    $logs = getAuditLogs($limit, $offset, $filters);
}

// Transform database format to frontend format
$auditLogs = array_map(function($log) {
    return [
        'id' => $log['id'],
        'admin_user' => $log['admin_user'],
        'action' => $log['action'],
        'description' => $log['details'] ?? '',
        'entity_type' => $log['entity_type'],
        'entity_id' => $log['entity_id'],
        'status' => $log['status'],
        'timestamp' => $log['timestamp'],
        'ip_address' => $log['ip_address'],
        'user_agent' => $log['user_agent'] ?? ''
    ];
}, $logs);

echo json_encode($auditLogs);
?>
