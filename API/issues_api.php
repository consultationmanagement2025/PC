<?php
header('Content-Type: application/json');
session_start();
require_once '../db.php';
require_once '../DATABASE/issues.php';

$action = $_GET['action'] ?? 'list';
$public_actions = ['create_public'];
$is_public = in_array($action, $public_actions, true);

$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$is_staff = in_array($current_role, ['staff', 'barangay staff', 'barangay_staff', 'barangay'], true);
if (!$is_public && $current_role !== 'admin' && $current_role !== 'administrator' && $current_role !== 'super admin' && $current_role !== 'superadmin' && !$is_staff) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$is_super_admin = ($current_role === 'super admin' || $current_role === 'superadmin');

function readJsonBody() {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $arr = json_decode($raw, true);
    return is_array($arr) ? $arr : [];
}

try {
    initializeIssuesTable();

    switch ($action) {
        case 'list':
            $limit = (int)($_GET['limit'] ?? 200);
            $offset = (int)($_GET['offset'] ?? 0);
            $filters = [];
            if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
            if (!empty($_GET['priority'])) $filters['priority'] = $_GET['priority'];
            if (!empty($_GET['category'])) $filters['category'] = $_GET['category'];
            if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];
            $rows = listIssues($filters, $limit, $offset);
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        case 'create':
            $payload = readJsonBody();
            $reporterName = $_SESSION['fullname'] ?? 'Admin';
            $reporterEmail = $_SESSION['email'] ?? null;
            $created = createIssue($payload, $reporterName, $reporterEmail);
            if (!$created['ok']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $created['message']]);
                exit;
            }
            echo json_encode(['success' => true, 'id' => $created['id'], 'reference_no' => $created['reference_no']]);
            break;

        case 'create_public':
            $payload = readJsonBody();
            $reporterName = trim((string)($payload['reported_by_name'] ?? ''));
            $reporterEmail = trim((string)($payload['reported_by_email'] ?? ''));
            $created = createIssue($payload, $reporterName, $reporterEmail);
            if (!$created['ok']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $created['message']]);
                exit;
            }
            echo json_encode(['success' => true, 'id' => $created['id'], 'reference_no' => $created['reference_no']]);
            break;

        case 'update_status':
            $payload = readJsonBody();
            $id = (int)($payload['id'] ?? 0);
            $status = (string)($payload['status'] ?? '');
            $notes = (string)($payload['notes'] ?? '');
            $actorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $updated = updateIssueStatus($id, $status, $notes, $actorId);
            if (!$updated['ok']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $updated['message']]);
                exit;
            }
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Throwable $e) {
    error_log('issues_api error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

