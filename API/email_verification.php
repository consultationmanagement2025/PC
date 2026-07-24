<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

// Generate 6-digit verification code
function generateVerificationCode() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Store verification code in database
function storeVerificationCode($conn, $email, $code, $expires_in_minutes = 10) {
    // Create table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS email_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        code VARCHAR(6) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Delete any existing codes for this email
    $deleteStmt = $conn->prepare("DELETE FROM email_verifications WHERE email = ?");
    $deleteStmt->bind_param("s", $email);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Insert new verification code
    $expires_at = date('Y-m-d H:i:s', time() + ($expires_in_minutes * 60));
    $stmt = $conn->prepare("INSERT INTO email_verifications (email, code, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $code, $expires_at);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

// Verify code
function verifyCode($conn, $email, $code) {
    $stmt = $conn->prepare("SELECT id FROM email_verifications WHERE email = ? AND code = ? AND expires_at > NOW() AND used = 0 LIMIT 1");
    $stmt->bind_param("ss", $email, $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $valid = $result->num_rows > 0;
    $stmt->close();
    
    if ($valid) {
        // Mark code as used
        $updateStmt = $conn->prepare("UPDATE email_verifications SET used = 1 WHERE email = ? AND code = ?");
        $updateStmt->bind_param("ss", $email, $code);
        $updateStmt->execute();
        $updateStmt->close();
    }
    
    return $valid;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'send':
            // Send verification code
            $email = $_POST['email'] ?? '';
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email address']);
                exit;
            }
            
            $code = generateVerificationCode();
            
            if (storeVerificationCode($conn, $email, $code)) {
                // For now, just log the code (in production, use real email)
                error_log("Verification code for $email: $code");
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Verification code generated. Check logs for code.',
                    'code' => $code, // For testing only
                    'expires_in' => 600 // 10 minutes in seconds
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to generate verification code']);
            }
            break;
            
        case 'verify':
            // Verify code
            $email = $_POST['email'] ?? '';
            $code = $_POST['code'] ?? '';
            
            if (empty($email) || empty($code)) {
                echo json_encode(['success' => false, 'message' => 'Email and code are required']);
                exit;
            }
            
            if (verifyCode($conn, $email, $code)) {
                echo json_encode(['success' => true, 'message' => 'Email verified successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Email verification error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
