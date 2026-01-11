<?php
session_start();
require_once 'announcements.php';
require_once 'audit-log.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid method']);
    exit();
}

// Check if admin
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($current_role !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$admin_id = $_SESSION['user_id'] ?? null;
$admin_user = $_SESSION['fullname'] ?? 'Admin';
$title = trim($_POST['announcement_title'] ?? '');
$content = trim($_POST['announcement_content'] ?? '');

if (!$title || !$content) {
    echo json_encode(['error' => 'Missing title or content']);
    exit();
}

$newId = createAnnouncement($admin_id, $admin_user, $title, $content, 'public');
if ($newId) {
    // Log the announcement creation
    if (function_exists('logAction')) {
        logAction($admin_id, $admin_user, "Posted Announcement: $title", 'announcement', $newId, null, $content, 'success', '');
    }
    echo json_encode(['success' => true, 'id' => $newId]);
} else {
    echo json_encode(['error' => 'Failed to create announcement']);
}
?>
