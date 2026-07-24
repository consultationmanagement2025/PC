<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'forgot_password') {
    
    $email = trim($_POST['email'] ?? '');
    
    // Validate email
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email address is required.']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit;
    }
    
    try {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
            exit;
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Don't reveal if email exists for security
            echo json_encode(['success' => true, 'message' => 'If the email exists, a reset link has been sent.']);
            exit;
        }
        
        $user = $result->fetch_assoc();
        
        // Generate reset token
        $reset_token = bin2hex(random_bytes(32));
        $reset_token_hash = hash('sha256', $reset_token);
        $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Update user with reset token
        $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        if (!$update_stmt) {
            echo json_encode(['success' => false, 'message' => 'Failed to generate reset token.']);
            exit;
        }
        
        $update_stmt->bind_param("sss", $reset_token_hash, $reset_expires, $email);
        
        if ($update_stmt->execute()) {
            // For now, just return success (email functionality can be added later)
            echo json_encode(['success' => true, 'message' => 'Password reset link has been sent to your email.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save reset token.']);
        }
        
        $update_stmt->close();
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Forgot password error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>
