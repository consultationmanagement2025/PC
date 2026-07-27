<?php
/**
 * Resource Person Management API
 * Handles approval/rejection of resource person applications
 */
header('Content-Type: application/json');
session_start();
require_once '../db.php';
require_once '../DATABASE/audit-log.php';

// Only allow admins
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$allowed_roles = ['admin', 'administrator', 'super admin', 'superadmin'];
if (!in_array($current_role, $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list_pending':
            // Get all pending resource person applications
            $stmt = $conn->prepare("SELECT id, fullname, email, expertise_areas, qualifications, department, phone, created_at FROM users WHERE role = 'resource person' AND verification_status = 'pending' ORDER BY created_at DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            $applications = [];
            while ($row = $result->fetch_assoc()) {
                $applications[] = $row;
            }
            $stmt->close();
            echo json_encode(['success' => true, 'data' => $applications]);
            break;

        case 'approve':
            // Approve a resource person application
            $user_id = (int)($_POST['user_id'] ?? 0);
            $admin_id = $_SESSION['user_id'] ?? null;
            $admin_name = $_SESSION['fullname'] ?? 'System';

            if (!$user_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE users SET verification_status = 'verified', approved_by = ?, approved_at = NOW() WHERE id = ? AND role = 'resource person' AND verification_status = 'pending'");
            $stmt->bind_param('ii', $admin_id, $user_id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    // Log the approval action
                    logAction($admin_id, $admin_name, 'approved_resource_person', 'user', $user_id, null, null, 'success', 'Admin approved resource person application');
                    echo json_encode(['success' => true, 'message' => 'Application approved successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Application not found or already processed']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to approve application']);
            }
            $stmt->close();
            break;

        case 'reject':
            // Reject a resource person application
            $user_id = (int)($_POST['user_id'] ?? 0);
            $rejection_reason = trim($_POST['reason'] ?? '');
            $admin_id = $_SESSION['user_id'] ?? null;
            $admin_name = $_SESSION['fullname'] ?? 'System';

            if (!$user_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE users SET verification_status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ? AND role = 'resource person' AND verification_status = 'pending'");
            $stmt->bind_param('ii', $admin_id, $user_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    // Log the rejection action
                    logAction($admin_id, $admin_name, 'rejected_resource_person', 'user', $user_id, null, null, 'success', 'Admin rejected resource person application: ' . $rejection_reason);
                    echo json_encode(['success' => true, 'message' => 'Application rejected successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Application not found or already processed']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject application']);
            }
            $stmt->close();
            break;

        case 'list_approved':
            // Get all approved resource persons
            $stmt = $conn->prepare("SELECT id, fullname, email, expertise_areas, qualifications, department, phone, approved_at FROM users WHERE role = 'resource person' AND verification_status = 'verified' ORDER BY approved_at DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            $resource_persons = [];
            while ($row = $result->fetch_assoc()) {
                $resource_persons[] = $row;
            }
            $stmt->close();
            echo json_encode(['success' => true, 'data' => $resource_persons]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
