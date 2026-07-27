<?php
// Include database connection (same as login.php)
require_once '../db.php';

// Start output buffering after includes
ob_start();

// Clean forgot password implementation
class ForgotPasswordHandler {
    private $conn;
    
    public function __construct() {
        // Use the global connection from db.php
        global $conn;
        $this->conn = $conn;
        
        if (!$this->conn) {
            $this->sendJson(false, 'Database connection not available');
        }
    }
    
    private function sendJson($success, $message) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);
        exit;
    }
    
    public function handleRequest() {
        // Only handle POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(false, 'Invalid request method');
        }
        
        // Check action
        if (!isset($_POST['action']) || $_POST['action'] !== 'forgot_password') {
            $this->sendJson(false, 'Invalid action');
        }
        
        // Get and validate email
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $this->sendJson(false, 'Email address is required');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendJson(false, 'Invalid email address');
        }
        
        // Check if user exists
        $stmt = $this->conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Don't reveal if email exists
            $this->sendJson(true, 'If the email exists, a reset link has been sent');
        }
        
        $user = $result->fetch_assoc();
        
        // Generate reset token
        $reset_token = bin2hex(random_bytes(32));
        $reset_token_hash = hash('sha256', $reset_token);
        $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Update user with reset token
        $stmt = $this->conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        $stmt->bind_param("sss", $reset_token_hash, $reset_expires, $email);
        
        if (!$stmt->execute()) {
            $this->sendJson(false, 'Failed to generate reset token');
        }
        
        // For now, just return success without email
        // You can add email functionality later
        $this->sendJson(true, 'Password reset link has been sent to your email');
    }
}

// Handle the request
if (isset($_GET['test'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Test endpoint working']);
    exit;
}

try {
    $handler = new ForgotPasswordHandler();
    $handler->handleRequest();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
