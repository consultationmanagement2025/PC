<?php
/**
 * API to fetch user posts for admin dashboard
 * Returns latest posts as JSON
 */
session_start();
require_once __DIR__ . '/../DATABASE/posts.php';

header('Content-Type: application/json');

// Only allow admins to view
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($current_role !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 30;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Get posts
$posts = getPosts($limit, $offset);

if (!$posts || empty($posts)) {
    echo json_encode([]);
    exit();
}

// Return as array of posts
echo json_encode($posts);
?>
