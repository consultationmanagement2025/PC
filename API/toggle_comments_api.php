<?php
session_start();
require_once '../db.php';
require_once '../DATABASE/announcements.php';

// Ensure user is logged in and is admin
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($current_role !== 'admin' || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$ann_id = isset($_POST['ann_id']) ? (int)$_POST['ann_id'] : 0;

if (!$ann_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid announcement ID']);
    exit;
}

// Toggle allow_comments
$result = toggleAllowComments($ann_id);

if ($result) {
    // Get the updated allow_comments value
    global $conn;
    $stmt = $conn->prepare("SELECT allow_comments FROM announcements WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $ann_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'allow_comments' => (bool)($row['allow_comments'] ?? true),
            'message' => 'Comments setting updated'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch updated status']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update announcement']);
}
?>
