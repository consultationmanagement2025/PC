<?php
session_start();
require_once __DIR__ . '/../announcements.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid method']);
    exit();
}

$ann_id = isset($_POST['ann_id']) ? (int)$_POST['ann_id'] : 0;
$action = trim($_POST['action'] ?? ''); // 'like' or 'save'
$user_id = $_SESSION['user_id'] ?? null;

if (!$ann_id || !$action || !$user_id) {
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

if ($action === 'like') {
    $res = toggleAnnouncementLike($ann_id, $user_id);
} elseif ($action === 'save') {
    $res = toggleAnnouncementSave($ann_id, $user_id);
} else {
    echo json_encode(['error' => 'Invalid action']);
    exit();
}

if ($res) {
    $ann = getAnnouncementById($ann_id);
    if ($ann) {
        $likes = json_decode($ann['liked_by'] ?? '[]', true) ?? [];
        $saves = json_decode($ann['saved_by'] ?? '[]', true) ?? [];
        echo json_encode(['success' => true, 'likes' => count($likes), 'saves' => count($saves)]);
    } else {
        echo json_encode(['error' => 'Announcement not found']);
    }
} else {
    echo json_encode(['error' => 'Failed to update']);
}
?>
