<?php
session_start();
require_once __DIR__ . '/../DATABASE/notifications.php';
require_once __DIR__ . '/../DATABASE/audit-log.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid method']);
    exit();
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$message = trim($_POST['message'] ?? '');
$type = trim($_POST['type'] ?? 'notice');

if (!$user_id || $message === '') {
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

$nid = createNotification($user_id, $message, $type);
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : null;
if ($nid) {
    // log admin action
    $admin_id = $_SESSION['user_id'] ?? null;
    $admin_user = $_SESSION['fullname'] ?? 'Admin';
    if (function_exists('logAction')) {
        if ($post_id) {
            logAction($admin_id, $admin_user, "Sent notification to user_id={$user_id} regarding post_id={$post_id}", 'post', $post_id, null, $message, 'success', $type);
        } else {
            logAction($admin_id, $admin_user, "Sent notification to user_id={$user_id}", 'notification', $nid, null, $message, 'success', $type);
        }
    }
    echo json_encode(['success' => true, 'id' => $nid]);
} else {
    echo json_encode(['error' => 'Failed to create notification']);
}

?>
