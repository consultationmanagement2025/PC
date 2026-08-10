<?php
/**
 * API: Approve or Reject Resource Person / Human Resource Applications
 * Action: 'approve' or 'reject'
 */
session_start();
require_once '../db.php';

header('Content-Type: application/json');

$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$allowed_roles = ['admin', 'administrator', 'super admin', 'superadmin'];
if (!in_array($current_role, $allowed_roles, true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized admin access']);
    exit;
}

$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$input = array_merge($_POST, $_GET, $raw);

$applicant_id = (int)($input['user_id'] ?? 0);
$action = strtolower(trim($input['action'] ?? ''));

if ($applicant_id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$admin_id = (int)($_SESSION['user_id'] ?? 0);
$admin_name = $_SESSION['fullname'] ?? 'Admin';

// Ensure schema columns exist
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM users");
if ($res) {
    while ($r = $res->fetch_assoc()) { $cols[] = $r['Field']; }
}
if (!in_array('approved_by', $cols)) @$conn->query("ALTER TABLE users ADD COLUMN approved_by INT DEFAULT NULL");
if (!in_array('approved_at', $cols)) @$conn->query("ALTER TABLE users ADD COLUMN approved_at DATETIME DEFAULT NULL");
if (!in_array('verification_status', $cols)) @$conn->query("ALTER TABLE users ADD COLUMN verification_status VARCHAR(50) NOT NULL DEFAULT 'pending'");

if ($action === 'approve') {
    $upd = $conn->prepare("UPDATE users SET status = 'active', verification_status = 'verified', approved_by = ?, approved_at = NOW() WHERE id = ?");
    $upd->bind_param('ii', $admin_id, $applicant_id);
    if ($upd->execute()) {
        $upd->close();

        // Create notification for applicant
        @$conn->query("CREATE TABLE IF NOT EXISTS expert_notifications (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'assignment',
            consultation_id INT(11) DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        @$conn->query("INSERT INTO expert_notifications (user_id, title, message, type, is_read, created_at) VALUES ($applicant_id, 'Application Approved!', 'Congratulations! Your Resource Person application has been approved by City Admin $admin_name. You can now access your Expert Workspace.', 'approval', 0, NOW())");

        echo json_encode(['success' => true, 'message' => 'Resource Person application approved successfully! User is now verified and active.']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to approve application: ' . $upd->error]);
        exit;
    }
} else {
    // Reject
    $upd = $conn->prepare("UPDATE users SET status = 'rejected', verification_status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?");
    $upd->bind_param('ii', $admin_id, $applicant_id);
    if ($upd->execute()) {
        $upd->close();

        echo json_encode(['success' => true, 'message' => 'Application rejected successfully.']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject application: ' . $upd->error]);
        exit;
    }
}
