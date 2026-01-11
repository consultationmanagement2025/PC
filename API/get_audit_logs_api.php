<?php
/**
 * API to fetch audit logs from database
 * Returns audit logs as JSON
 */
session_start();
require_once __DIR__ . '/../DATABASE/audit-log.php';

header('Content-Type: application/json');

// Only allow admins to view
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($current_role !== 'admin') {
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
$logs = getAuditLogs($limit, $offset, $filters);

if (!$logs || empty($logs)) {
    echo json_encode([]);
    exit();
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
