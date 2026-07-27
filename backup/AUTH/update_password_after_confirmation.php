<?php
// Start output buffering to catch any unwanted output
ob_start();

// Enable error reporting but disable display to prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Catch any output and clear it
$output = ob_get_clean();
if (!empty($output)) {
    error_log("Unexpected output in update_password_after_confirmation.php: " . $output);
}

require_once '../config.php';
require_once '../db.php';

// Function to send clean JSON response
function sendJsonResponse($success, $message, $data = null) {
    // Clear any output buffer
    if (ob_get_length()) ob_clean();
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Log that the script is being accessed
error_log("update_password_after_confirmation.php accessed at " . date('Y-m-d H:i:s'));

// Start session
session_start();

// Handle password update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    
    $token = trim($_POST['token'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Validate inputs
    if (empty($token)) {
        sendJsonResponse(false, 'Invalid confirmation token.');
    }
    
    if (empty($password)) {
        sendJsonResponse(false, 'Password is required.');
    }
    
    if (strlen($password) < 6) {
        sendJsonResponse(false, 'Password must be at least 6 characters long.');
    }
    
    try {
        // Connect to database
        $conn = dbConnect();
        if (!$conn) {
            throw new Exception('Database connection failed.');
        }
        
        // Hash the token to find the user
        $token_hash = hash('sha256', $token);
        
        // Check if token exists and is not expired
        $stmt = $conn->prepare("SELECT id, email FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            sendJsonResponse(false, 'Invalid or expired confirmation token.');
        }
        
        $user = $result->fetch_assoc();
        
        // Hash the new password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        $stmt->bind_param("si", $password_hash, $user['id']);
        
        if ($stmt->execute()) {
            sendJsonResponse(true, 'Password has been updated successfully!');
        } else {
            sendJsonResponse(false, 'Failed to update password. Please try again.');
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        error_log("Password update error: " . $e->getMessage());
        sendJsonResponse(false, 'An error occurred. Please try again.');
    }
    
} else {
    sendJsonResponse(false, 'Invalid request.');
}
?>
