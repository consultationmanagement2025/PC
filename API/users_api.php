<?php
header('Content-Type: application/json');
session_start();
require_once '../db.php';

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
        case 'list':
            $sql = "SELECT id, fullname, username, email, role, status, last_login, created_at FROM users ORDER BY created_at DESC";
            $result = $conn->query($sql);
            $users = [];
            
            if ($result && $result->num_rows > 0) {
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
            
            $stmt = $conn->prepare("SELECT id, fullname, username, email, role, status, last_login, created_at FROM users WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo json_encode(['success' => true, 'data' => $result->fetch_assoc()]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
            $stmt->close();
            break;
            
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            $name = trim($data['name'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $role = trim($data['role'] ?? 'staff');
            $status = trim($data['status'] ?? 'active');
            
            if (!$name || !$email || !$password) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Name, email, and password are required']);
                exit;
            }
            
            if (strlen($password) < 12) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Password must be at least 12 characters']);
                exit;
            }
            
            // Check duplicate email
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $chk->bind_param('s', $email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
                $chk->close();
                exit;
            }
            $chk->close();
            
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $username = explode('@', $email)[0];
            
            $stmt = $conn->prepare("INSERT INTO users (fullname, username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('ssssss', $name, $username, $email, $hashed, $role, $status);
            
            if ($stmt->execute()) {
                require_once '../DATABASE/audit-log.php';
                logAction(
                    $_SESSION['user_id'],
                    $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'admin',
                    'create_user',
                    'user',
                    $stmt->insert_id,
                    null,
                    json_encode(['name' => $name, 'email' => $email, 'role' => $role]),
                    'success',
                    'Created staff account: ' . $name . ' (' . $email . ')'
                );
                echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create account: ' . $stmt->error]);
            }
            $stmt->close();
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
            $params = [];
            $types = '';
            
            if (isset($data['name']) && trim($data['name']) !== '') {
                $updatable_fields[] = 'fullname = ?';
                $params[] = trim($data['name']);
                $types .= 's';
            }
            
            if (isset($data['email']) && trim($data['email']) !== '') {
                $updatable_fields[] = 'email = ?';
                $params[] = trim($data['email']);
                $types .= 's';
            }
            
            if (isset($data['role'])) {
                $updatable_fields[] = 'role = ?';
                $params[] = $data['role'];
                $types .= 's';
            }
            
            if (isset($data['status'])) {
                $updatable_fields[] = 'status = ?';
                $params[] = $data['status'];
                $types .= 's';
            }
            
            if (isset($data['password']) && $data['password'] !== '') {
                if (strlen($data['password']) < 12) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Password must be at least 12 characters']);
                    exit;
                }
                $updatable_fields[] = 'password = ?';
                $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
                $types .= 's';
            }
            
            if (empty($updatable_fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                exit;
            }
            
            $set_clause = implode(', ', $updatable_fields);
            $params[] = $id;
            $types .= 'i';
            
            $stmt = $conn->prepare("UPDATE users SET $set_clause WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                require_once '../DATABASE/audit-log.php';
                $change_description = 'Updated staff account #' . $id;
                if (isset($data['role'])) $change_description .= ' - Role: ' . $data['role'];
                if (isset($data['status'])) $change_description .= ' - Status: ' . $data['status'];
                if (isset($data['password']) && $data['password'] !== '') $change_description .= ' - Password reset';
                logAction(
                    $_SESSION['user_id'],
                    $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'admin',
                    'modify_user',
                    'user',
                    $id,
                    null,
                    json_encode(array_diff_key($data, ['password' => 1])),
                    'success',
                    $change_description
                );
                
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $stmt->error]);
            }
            $stmt->close();
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
