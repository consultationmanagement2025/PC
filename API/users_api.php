<?php
header('Content-Type: application/json');
require_once '../db.php';

// Check admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $sql = "SELECT id, username, email, role, status, last_login, created_at FROM users ORDER BY created_at DESC";
            $result = $conn->query($sql);
            $users = [];
            
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
            }
            
            echo json_encode(['success' => true, 'data' => $users]);
            break;
            
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID required']);
                exit;
            }
            
            $sql = "SELECT id, username, email, role, status, last_login, created_at FROM users WHERE id = $id";
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                echo json_encode(['success' => true, 'data' => $result->fetch_assoc()]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
            break;
            
        case 'update':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID required']);
                exit;
            }
            
            $updatable_fields = [];
            
            if (isset($data['role'])) {
                $role = $conn->real_escape_string($data['role']);
                $updatable_fields[] = "role = '$role'";
            }
            
            if (isset($data['status'])) {
                $status = $conn->real_escape_string($data['status']);
                $updatable_fields[] = "status = '$status'";
            }
            
            if (empty($updatable_fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                exit;
            }
            
            $set_clause = implode(', ', $updatable_fields);
            $sql = "UPDATE users SET $set_clause WHERE id = $id";
            
            if ($conn->query($sql) === TRUE) {
                // Log the action
                require_once '../DATABASE/user-logs.php';
                logUserAction(
                    $_SESSION['user_id'],
                    $_SESSION['username'],
                    'modify_user',
                    'user',
                    $id,
                    "Updated user #$id",
                    'success'
                );
                
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update user']);
            }
            break;
            
        case 'stats':
            $sql = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN role = 'Administrator' THEN 1 ELSE 0 END) as admin_count,
                    SUM(CASE WHEN role = 'Citizen' THEN 1 ELSE 0 END) as citizen_count,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count
                    FROM users";
            
            $result = $conn->query($sql);
            $stats = $result->fetch_assoc();
            
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
