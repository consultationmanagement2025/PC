<?php
header('Content-Type: application/json');
session_start();
require_once '../db.php';
require_once '../DATABASE/documents.php';

$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($current_role !== 'admin' && $current_role !== 'administrator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $limit = (int)($_GET['limit'] ?? 200);
            $offset = (int)($_GET['offset'] ?? 0);
            if ($limit <= 0) $limit = 200;
            if ($limit > 500) $limit = 500;
            if ($offset < 0) $offset = 0;

            $rows = getDocuments($limit, $offset);
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
