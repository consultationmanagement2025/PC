<?php
session_start();
require_once 'announcements.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    echo json_encode(['error' => 'Invalid announcement ID']);
    exit();
}

$ann = getAnnouncementById($id);
if (!$ann) {
    echo json_encode(['error' => 'Announcement not found']);
    exit();
}

$likes = json_decode($ann['liked_by'] ?? '[]', true) ?? [];
$saves = json_decode($ann['saved_by'] ?? '[]', true) ?? [];
$userId = $_SESSION['user_id'] ?? null;

echo json_encode([
    'success' => true,
    'announcement' => $ann,
    'likes' => count($likes),
    'saves' => count($saves),
    'userLiked' => in_array($userId, $likes),
    'userSaved' => in_array($userId, $saves)
]);
?>
