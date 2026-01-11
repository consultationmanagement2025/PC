<?php
/**
 * API to fetch user activity logs from database
 * Returns user action logs as JSON
 */
session_start();
require_once __DIR__ . '/../DATABASE/user-logs.php';

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
if (!empty($_GET['filter_user'])) $filters['username'] = $_GET['filter_user'];
if (!empty($_GET['filter_action'])) $filters['action'] = $_GET['filter_action'];
if (!empty($_GET['filter_type'])) $filters['entity_type'] = $_GET['filter_type'];
if (!empty($_GET['filter_date'])) $filters['date'] = $_GET['filter_date'];

// Get user logs from database
$logs = getUserLogs($limit, $offset, $filters);

if (!$logs || empty($logs)) {
    echo json_encode([]);
    exit();
}

// Transform database format to frontend format
$userLogs = array_map(function($log) {
    return [
        'id' => $log['id'],
        'user_id' => $log['user_id'],
        'username' => $log['username'],
        'action' => $log['action'],
        'action_type' => $log['action_type'],
        'entity_type' => $log['entity_type'],
        'entity_id' => $log['entity_id'],
        'description' => $log['description'] ?? '',
        'status' => $log['status'],
        'timestamp' => $log['timestamp'],
        'ip_address' => $log['ip_address'],
        'user_agent' => $log['user_agent'] ?? ''
    ];
}, $logs);

echo json_encode($userLogs);
?>
