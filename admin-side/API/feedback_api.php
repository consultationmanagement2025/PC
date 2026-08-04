<?php
header('Content-Type: application/json');
session_start();
require_once '../db.php';
require_once '../DATABASE/feedback.php';
if (file_exists('../email_config_simple.php')) {
    require_once '../email_config_simple.php';
}

$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$has_session_user = !empty($_SESSION['user_id']) || !empty($_SESSION['fullname']) || !empty($_SESSION['email']) || !empty($_SESSION['user']);

$allowed_roles = [
    'admin', 'administrator', 'super admin', 'superadmin', 'system administrator', 'system admin',
    'staff', 'barangay staff', 'barangay_staff', 'barangay', 'lgu staff', 'lgu', 'official', 'resource person', 'user', 'citizen'
];

if (!$has_session_user && !in_array($current_role, $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$is_super_admin = ($current_role === 'super admin' || $current_role === 'superadmin');

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

        case 'phms_list':
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);
            $result = fetchPhmsFeedbackFromApi(null, $limit, $offset);
            if (!empty($result['success']) && isset($result['data']) && isset($result['data']['hearings'])) {
                echo json_encode(['success' => true, 'data' => $result['data']]);
            } else {
                $hearings = getPhmsFeedbackQueueAsHearings([], $limit, $offset);
                echo json_encode([
                    'success' => true,
                    'is_cached' => true,
                    'message' => 'Displaying stored PHMS hearings ledger.',
                    'data' => [
                        'source_system' => 'PHMS (Stored Ledger)',
                        'data_type' => 'citizen_hearing_feedback',
                        'count' => count($hearings),
                        'limit' => $limit,
                        'offset' => $offset,
                        'hearings' => $hearings
                    ]
                ]);
            }
            break;

        case 'phms_detail':
            $hearing_id = trim((string)($_GET['hearing_id'] ?? ''));
            if ($hearing_id === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Hearing ID parameter is required.']);
                exit;
            }
            $result = fetchPhmsFeedbackFromApi($hearing_id);
            if (!empty($result['success']) && isset($result['data']) && !empty($result['data']['hearings'])) {
                echo json_encode(['success' => true, 'data' => $result['data']]);
            } else {
                $cached = getPhmsFeedbackQueueAsHearings([], 200, 0);
                $filtered = array_values(array_filter($cached, function($h) use ($hearing_id) {
                    $hid1 = (string)($h['hearing_id'] ?? '');
                    $hid2 = (string)($h['phms_hearing_id'] ?? '');
                    $hid3 = (string)($h['queue_id'] ?? '');
                    return $hid1 === (string)$hearing_id || $hid2 === (string)$hearing_id || $hid3 === (string)$hearing_id;
                }));
                echo json_encode([
                    'success' => !empty($filtered),
                    'data' => [
                        'source_system' => 'PHMS (Stored Ledger)',
                        'data_type' => 'citizen_hearing_feedback',
                        'count' => count($filtered),
                        'hearings' => $filtered
                    ]
                ]);
            }
            break;

        case 'phms_sync':
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);
            $result = fetchPhmsFeedbackFromApi(null, $limit, $offset);
            if (!empty($result['success']) && isset($result['data']) && isset($result['data']['hearings'])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'PHMS citizen feedback data successfully synchronized.',
                    'data' => $result['data']
                ]);
            } else {
                $hearings = getPhmsFeedbackQueueAsHearings([], $limit, $offset);
                echo json_encode([
                    'success' => true,
                    'message' => 'PHMS citizen feedback data loaded from stored ledger.',
                    'data' => [
                        'source_system' => 'PHMS (Stored Ledger)',
                        'data_type' => 'citizen_hearing_feedback',
                        'count' => count($hearings),
                        'limit' => $limit,
                        'offset' => $offset,
                        'hearings' => $hearings
                    ]
                ]);
            }
            break;

        case 'phms_update_status':
            $data = json_decode(file_get_contents('php://input'), true);
            $queue_id = (int)($data['queue_id'] ?? $data['id'] ?? 0);
            $status = trim((string)($data['status'] ?? ''));
            if (!$queue_id || $status === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Queue ID and status required']);
                exit;
            }
            $ok = updatePhmsQueueStatus($queue_id, $status);
            echo json_encode(['success' => (bool)$ok]);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Feedback ID required']);
                exit;
            }
            $feedback = getFeedback(['id' => $id], 1, 0);
            if (!empty($feedback[0])) {
                echo json_encode(['success' => true, 'data' => $feedback[0]]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Feedback not found']);
            }
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

        case 'respond':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            $response = trim((string)($data['response'] ?? ''));
            $send_email = !empty($data['send_email']);
            $admin_id = (int)($_SESSION['user_id'] ?? 1);

            if (!$id || $response === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Feedback ID and response message required']);
                exit;
            }

            $ok = respondToFeedback($id, $response, $admin_id);
            if ($ok) {
                $fbList = getFeedback(['id' => $id], 1, 0);
                $fb = $fbList[0] ?? null;
                $emailSent = false;

                if ($send_email && $fb && !empty($fb['guest_email'])) {
                    $to = $fb['guest_email'];
                    $subject = "Official Response to your Consultation Feedback - LGU Public Portal";
                    $body = "Dear " . ($fb['guest_name'] ?: 'Citizen') . ",\n\n";
                    $body .= "Thank you for participating in our Public Consultation.\n\n";
                    $body .= "YOUR FEEDBACK:\n\"" . $fb['message'] . "\"\n\n";
                    $body .= "OFFICIAL LGU RESPONSE:\n\"" . $response . "\"\n\n";
                    $body .= "Best regards,\nLocal Government Unit / Public Consultation Team";

                    if (function_exists('sendGmailEmailSimple')) {
                        $sentResult = sendGmailEmailSimple($to, $subject, $body, false);
                        $emailSent = ($sentResult === true);
                    }
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Response recorded successfully' . ($emailSent ? ' and email sent to citizen.' : '.'),
                    'email_sent' => $emailSent
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to record response']);
            }
            break;

        case 'archive':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Feedback ID required']);
                exit;
            }
            $ok = archiveFeedback($id);
            echo json_encode(['success' => (bool)$ok]);
            break;

        case 'forward':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            $committee = trim((string)($data['committee'] ?? ''));
            $notes = trim((string)($data['notes'] ?? ''));
            $admin_id = (int)($_SESSION['user_id'] ?? 1);

            if (!$id || $committee === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Feedback ID and target Committee are required']);
                exit;
            }

            $ok = forwardFeedbackToCommittee($id, $committee, $admin_id, $notes);
            if ($ok) {
                echo json_encode([
                    'success' => true,
                    'message' => "Feedback successfully routed & forwarded to {$committee}."
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to forward feedback to committee']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
