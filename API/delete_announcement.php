<?php
/**
 * API to delete an announcement
 */
session_start();
require_once __DIR__ . '/../DATABASE/announcements.php';
require_once __DIR__ . '/../DATABASE/audit-log.php';

header('Content-Type: application/json');

// Only allow admins
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($current_role !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid method']);
    exit();
}

$ann_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$ann_id) {
    echo json_encode(['error' => 'Invalid announcement ID']);
    exit();
}

global $conn;
require_once __DIR__ . '/../db.php';

// Delete the announcement
$stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
if (!$stmt) {
    echo json_encode(['error' => 'Database error']);
    exit();
}

$stmt->bind_param('i', $ann_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    $admin_id = $_SESSION['user_id'] ?? null;
    $admin_user = $_SESSION['fullname'] ?? 'System';
    
    // Log deletion
    logAction($admin_id, $admin_user, 'deleted_announcement', 'announcement', $ann_id, null, null, 'success', 'Admin deleted announcement');
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to delete announcement']);
}
?>
