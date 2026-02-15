<?php
header('Content-Type: application/json');
session_start();
require_once '../db.php';
require_once '../DATABASE/feedback.php';

$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($current_role !== 'admin' && $current_role !== 'administrator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'debug':
            $dbRow = $conn->query("SELECT DATABASE() AS db") ? $conn->query("SELECT DATABASE() AS db")->fetch_assoc() : null;
            $dbName = $dbRow['db'] ?? null;
            $countRow = $conn->query("SELECT COUNT(*) AS cnt FROM feedback") ? $conn->query("SELECT COUNT(*) AS cnt FROM feedback")->fetch_assoc() : null;
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
                        'feedback_count' => $cnt,
                    ],
                ],
            ]);
            break;

        case 'list':
            $limit = (int)($_GET['limit'] ?? 200);
            $offset = (int)($_GET['offset'] ?? 0);

            $filters = [];
            if (!empty($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (!empty($_GET['consultation_id'])) {
                $filters['consultation_id'] = (int)$_GET['consultation_id'];
            }
            if (!empty($_GET['rating'])) {
                $filters['rating'] = (int)$_GET['rating'];
            }
            if (!empty($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }

            $feedback = getFeedback($filters, $limit, $offset);
            echo json_encode(['success' => true, 'data' => $feedback]);
            break;

        case 'update_status':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            $status = trim((string)($data['status'] ?? ''));

            if (!$id || $status === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Feedback ID and status required']);
                exit;
            }

            $ok = updateFeedbackStatus($id, $status);
            echo json_encode(['success' => (bool)$ok]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
