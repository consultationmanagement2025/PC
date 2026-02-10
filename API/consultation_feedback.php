<?php
/**
 * Consultation Feedback API
 * Handles submission and retrieval of public consultation feedback
 */
header('Content-Type: application/json');
require_once '../db.php';
require_once '../DATABASE/consultations.php';
require_once '../DATABASE/user-logs.php';

session_start();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    switch ($action) {
        // Get all feedback for a consultation
        case 'get_feedback':
            $consultation_id = (int)($_GET['consultation_id'] ?? 0);
            if (!$consultation_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }

            $sql = "SELECT p.*, u.fullname, u.email FROM posts p 
                    LEFT JOIN users u ON p.user_id = u.id 
                    WHERE p.consultation_id = $consultation_id AND p.status = 'approved'
                    ORDER BY p.created_at DESC";
            
            $result = $conn->query($sql);
            $feedback = [];
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $feedback[] = $row;
                }
            }

            echo json_encode(['success' => true, 'data' => $feedback, 'count' => count($feedback)]);
            break;

        // Submit feedback
        case 'submit_feedback':
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Not authenticated']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['consultation_id', 'message'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
                    exit;
                }
            }

            $consultation_id = (int)$data['consultation_id'];
            $message = $conn->real_escape_string($data['message']);
            $category = $conn->real_escape_string($data['category'] ?? 'General Feedback');
            $user_id = $_SESSION['user_id'];

            // Create post (feedback) for consultation
            $sql = "INSERT INTO posts (consultation_id, user_id, content, status, category, created_at)
                    VALUES ($consultation_id, $user_id, '$message', 'pending', '$category', NOW())";

            if ($conn->query($sql)) {
                $post_id = $conn->insert_id;
                
                // Log the action
                if (function_exists('logUserAction')) {
                    logUserAction(
                        $user_id,
                        $_SESSION['fullname'] ?? 'User',
                        'submit_feedback',
                        'create',
                        'feedback',
                        $post_id,
                        'User submitted feedback for consultation #' . $consultation_id,
                        'success',
                        json_encode(['consultation_id' => $consultation_id, 'category' => $category])
                    );
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Feedback submitted successfully and awaits approval',
                    'feedback_id' => $post_id
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error submitting feedback']);
            }
            break;

        // Get consultation statistics for display
        case 'get_stats':
            $consultation_id = (int)($_GET['consultation_id'] ?? 0);
            if (!$consultation_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }

            $stats = getConsultationStats($consultation_id);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;

        // Get recent feedback
        case 'get_recent':
            $limit = (int)($_GET['limit'] ?? 5);
            $sql = "SELECT p.*, u.fullname, c.title as consultation_title FROM posts p 
                    LEFT JOIN users u ON p.user_id = u.id 
                    LEFT JOIN consultations c ON p.consultation_id = c.id 
                    WHERE p.status = 'approved'
                    ORDER BY p.created_at DESC 
                    LIMIT $limit";
            
            $result = $conn->query($sql);
            $feedback = [];
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $feedback[] = $row;
                }
            }

            echo json_encode(['success' => true, 'data' => $feedback]);
            break;

        // Get consultation feedback count
        case 'get_feedback_count':
            $consultation_id = (int)($_GET['consultation_id'] ?? 0);
            if (!$consultation_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }

            $sql = "SELECT COUNT(*) as count FROM posts WHERE consultation_id = $consultation_id AND status = 'approved'";
            $result = $conn->query($sql);
            
            if ($result) {
                $row = $result->fetch_assoc();
                echo json_encode(['success' => true, 'count' => $row['count']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error retrieving count']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
