<?php
/**
 * API to create announcements with optional image upload
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

$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');

if (empty($title) || empty($content)) {
    echo json_encode(['error' => 'Title and content required']);
    exit();
}

$admin_id = $_SESSION['user_id'] ?? null;
$admin_user = $_SESSION['fullname'] ?? 'System';
$image_path = null;

// Handle image upload if provided
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploaded_file = $_FILES['image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($uploaded_file['type'], $allowed_types)) {
        echo json_encode(['error' => 'Invalid image type']);
        exit();
    }
    
    if ($uploaded_file['size'] > 5 * 1024 * 1024) { // 5MB limit
        echo json_encode(['error' => 'Image too large (max 5MB)']);
        exit();
    }
    
    // Create images directory if it doesn't exist
    $upload_dir = __DIR__ . '/../images/announcements';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $ext = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
    $filename = 'ann_' . time() . '_' . uniqid() . '.' . $ext;
    $filepath = $upload_dir . '/' . $filename;
    
    if (move_uploaded_file($uploaded_file['tmp_name'], $filepath)) {
        $image_path = 'images/announcements/' . $filename;
    }
}

// Create announcement
$ann_id = createAnnouncement($admin_id, $admin_user, $title, $content, 'public', $image_path);

if ($ann_id) {
    // Log admin action
    logAction($admin_id, $admin_user, 'created_announcement', 'announcement', $ann_id, null, null, 'success', 'Admin created announcement: ' . $title);
    
    echo json_encode([
        'success' => true,
        'id' => $ann_id,
        'image_path' => $image_path
    ]);
} else {
    echo json_encode(['error' => 'Failed to create announcement']);
}
?>
