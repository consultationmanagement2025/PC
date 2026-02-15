<?php
header('Content-Type: application/json');
session_start();
require_once '../db.php';
require_once '../DATABASE/consultations.php';

// Check admin role
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
            $countRow = $conn->query("SELECT COUNT(*) AS cnt FROM consultations") ? $conn->query("SELECT COUNT(*) AS cnt FROM consultations")->fetch_assoc() : null;
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
                        'consultations_count' => $cnt,
                    ],
                ],
            ]);
            break;

        case 'list':
            $status = $_GET['status'] ?? null;
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);
            
            $consultations = getConsultations($status, $limit, $offset);
            echo json_encode(['success' => true, 'data' => $consultations]);
            break;
            
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }
            
            $consultation = getConsultationById($id);
            if ($consultation) {
                echo json_encode(['success' => true, 'data' => $consultation]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Consultation not found']);
            }
            break;
            
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['title', 'description', 'category', 'start_date', 'end_date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
                    exit;
                }
            }
            
            $id = createConsultation(
                $data['title'],
                $data['description'],
                $data['category'],
                $data['start_date'],
                $data['end_date'],
                $_SESSION['user_id'],
                $data['expected_posts'] ?? 0
            );
            
            if ($id) {
                echo json_encode(['success' => true, 'data' => ['id' => $id]]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create consultation']);
            }
            break;
            
        case 'update':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }
            
            $success = updateConsultation(
                $id,
                $data['title'],
                $data['description'],
                $data['category'],
                $data['status'],
                $data['start_date'],
                $data['end_date']
            );
            
            echo json_encode(['success' => $success]);
            break;
            
        case 'close':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }
            
            $success = closeConsultation($id);
            echo json_encode(['success' => $success]);
            break;
            
        case 'delete':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }
            
            $success = deleteConsultation($id);
            echo json_encode(['success' => $success]);
            break;
            
        case 'stats':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }
            
            $stats = getConsultationStats($id);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
