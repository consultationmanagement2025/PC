<?php
require_once __DIR__ . '/../DATABASE/posts.php';
header('Content-Type: application/json');
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$posts = getPosts($limit, $offset);
echo json_encode($posts);
?>
