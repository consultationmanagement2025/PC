<?php
session_start();
require_once 'posts.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid method']);
    exit();
}

$content = trim($_POST['content'] ?? '');
if ($content === '') {
    echo json_encode(['error' => 'Content empty']);
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$author = $_SESSION['fullname'] ?? 'Anonymous';

$insertId = createPost($user_id, $author, $content);
if ($insertId) {
    echo json_encode(['success' => true, 'id' => $insertId]);
} else {
    echo json_encode(['error' => 'Failed to create post']);
}

?>
