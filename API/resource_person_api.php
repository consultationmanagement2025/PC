<?php
/**
 * Resource Person Management API
 * Handles approval/rejection of resource person applications
 */
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/audit-log.php';

// Allow admins, staff, and resource persons
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$allowed_roles = ['admin', 'administrator', 'super admin', 'superadmin', 'resource person', 'resource_person', 'staff'];
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
            $user_id = (int)($_POST['user_id'] ?? ($_GET['user_id'] ?? 0));
            $admin_id = $_SESSION['user_id'] ?? null;
            $admin_name = $_SESSION['fullname'] ?? 'Admin';

            if (!$user_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE users SET status = 'active', verification_status = 'verified', approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->bind_param('ii', $admin_id, $user_id);

            if ($stmt->execute()) {
                // Notify applicant
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
                @$conn->query("INSERT INTO expert_notifications (user_id, title, message, type, is_read, created_at) VALUES ($user_id, 'Application Approved!', 'Congratulations! Your Resource Person application has been approved by City Admin $admin_name. You can now access your Expert Workspace.', 'approval', 0, NOW())");

                if (file_exists(__DIR__ . '/../DATABASE/audit-log.php')) {
                    require_once __DIR__ . '/../DATABASE/audit-log.php';
                    if (function_exists('logAction')) {
                        logAction(
                            $admin_id ?: 1,
                            $admin_name,
                            'Approved Resource Person',
                            'User',
                            $user_id,
                            'pending',
                            'verified',
                            'success',
                            "Approved Resource Person application for user ID #{$user_id}"
                        );
                    }
                }
                echo json_encode(['success' => true, 'message' => 'Resource Person application approved successfully! User is now verified and active.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to approve application: ' . $stmt->error]);
            }
            $stmt->close();
            break;

        case 'reject':
            // Reject a resource person application
            $user_id = (int)($_POST['user_id'] ?? ($_GET['user_id'] ?? 0));
            $admin_id = $_SESSION['user_id'] ?? null;

            if (!$user_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE users SET status = 'rejected', verification_status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->bind_param('ii', $admin_id, $user_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Application rejected successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject application']);
            }
            $stmt->close();
            break;

        case 'list_approved':
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

        case 'get_consultation_details':
            $c_id = (int)($_GET['consultation_id'] ?? 0);
            if (!$c_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid consultation ID']);
                exit;
            }
            $stmt = $conn->prepare("SELECT * FROM consultations WHERE id = ?");
            $stmt->bind_param('i', $c_id);
            $stmt->execute();
            $cData = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$cData) {
                echo json_encode(['success' => false, 'message' => 'Consultation not found']);
                exit;
            }

            // Fetch report version history
            $reports = [];
            $rStmt = $conn->prepare("SELECT r.*, u.fullname as uploader_name FROM resolution_reports r LEFT JOIN users u ON r.uploaded_by = u.id WHERE r.consultation_id = ? ORDER BY r.id DESC");
            if ($rStmt) {
                $rStmt->bind_param('i', $c_id);
                $rStmt->execute();
                $rRes = $rStmt->get_result();
                while ($rRow = $rRes->fetch_assoc()) {
                    $reports[] = $rRow;
                }
                $rStmt->close();
            }

            // Fetch info requests
            $info_requests = [];
            $iStmt = $conn->prepare("SELECT i.*, u.fullname as requester_name FROM info_requests i LEFT JOIN users u ON i.requested_by = u.id WHERE i.consultation_id = ? ORDER BY i.id DESC");
            if ($iStmt) {
                $iStmt->bind_param('i', $c_id);
                $iStmt->execute();
                $iRes = $iStmt->get_result();
                while ($iRow = $iRes->fetch_assoc()) {
                    $info_requests[] = $iRow;
                }
                $iStmt->close();
            }

            // Fetch audit trail history for master document
            $audit_trail = [];
            $aStmt = $conn->prepare("SELECT * FROM consultation_document_audit_trail WHERE consultation_id = ? ORDER BY id DESC");
            if ($aStmt) {
                $aStmt->bind_param('i', $c_id);
                $aStmt->execute();
                $aRes = $aStmt->get_result();
                while ($aRow = $aRes->fetch_assoc()) {
                    $audit_trail[] = $aRow;
                }
                $aStmt->close();
            }

            // Parse inline expert notes JSON if exists
            $parsed_expert_notes = null;
            if (!empty($cData['expert_notes'])) {
                $parsed_expert_notes = json_decode($cData['expert_notes'], true);
            }

            echo json_encode([
                'success' => true,
                'consultation' => $cData,
                'parsed_expert_notes' => $parsed_expert_notes,
                'reports' => $reports,
                'info_requests' => $info_requests,
                'audit_trail' => $audit_trail
            ]);
            break;

        case 'get_notifications':
            $user_id = (int)($_SESSION['user_id'] ?? 0);
            $stmt = $conn->prepare("SELECT * FROM expert_notifications WHERE user_id = ? ORDER BY id DESC LIMIT 30");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $nRes = $stmt->get_result();
            $notifs = [];
            while ($nRow = $nRes->fetch_assoc()) {
                $notifs[] = $nRow;
            }
            $stmt->close();
            echo json_encode(['success' => true, 'data' => $notifs]);
            break;

        case 'mark_notif_read':
            $notif_id = (int)($_POST['id'] ?? 0);
            $user_id = (int)($_SESSION['user_id'] ?? 0);
            if ($notif_id > 0) {
                $conn->query("UPDATE expert_notifications SET is_read = 1 WHERE id = {$notif_id}");
                $conn->query("UPDATE notifications SET is_read = 1 WHERE id = {$notif_id}");
            } else {
                $conn->query("UPDATE expert_notifications SET is_read = 1");
                $conn->query("UPDATE notifications SET is_read = 1");
            }
            echo json_encode(['success' => true, 'message' => 'Notifications updated']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
