<?php
header('Content-Type: application/json');
session_start();
require_once '../db.php';
require_once '../DATABASE/notifications.php';

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
            $limit = (int)($_GET['limit'] ?? 20);
            if ($limit <= 0) $limit = 20;
            if ($limit > 100) $limit = 100;
            $uid = (int)($_SESSION['user_id'] ?? 0);
            $rows = getUserNotifications($uid, $limit);
            $unread = getUnreadNotificationsCount($uid);
            echo json_encode(['success' => true, 'data' => ['items' => $rows, 'unread' => $unread]]);
            break;

        case 'mark_read':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            $is_read = isset($data['is_read']) ? (int)($data['is_read'] ? 1 : 0) : 1;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Notification id required']);
                exit;
            }
            $ok = markNotificationRead($id, $is_read);
            echo json_encode(['success' => (bool)$ok]);
            break;

        case 'mark_all_read':
            $uid = (int)($_SESSION['user_id'] ?? 0);
            $ok = markAllNotificationsRead($uid);
            echo json_encode(['success' => (bool)$ok]);
            break;

        case 'delete':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Notification id required']);
                exit;
            }
            $ok = deleteNotificationById($id);
            echo json_encode(['success' => (bool)$ok]);
            break;

        case 'debug':
            $dbRow = $conn->query("SELECT DATABASE() AS db") ? $conn->query("SELECT DATABASE() AS db")->fetch_assoc() : null;
            $dbName = $dbRow['db'] ?? null;
            $countRow = $conn->query("SELECT COUNT(*) AS cnt FROM notifications") ? $conn->query("SELECT COUNT(*) AS cnt FROM notifications")->fetch_assoc() : null;
            $cnt = isset($countRow['cnt']) ? (int)$countRow['cnt'] : null;
            echo json_encode([
                'success' => true,
                'data' => [
                    'session' => [
                        'user_id' => $_SESSION['user_id'] ?? null,
                        'fullname' => $_SESSION['fullname'] ?? null,
                        'role' => $_SESSION['role'] ?? null,
                        'role_normalized' => $current_role,
                    ],
                    'db' => [
                        'database' => $dbName,
                        'notifications_count' => $cnt,
                    ],
                ],
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
