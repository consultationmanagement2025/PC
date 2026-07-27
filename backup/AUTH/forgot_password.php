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
    error_log("Unexpected output in forgot_password.php: " . $output);
}

require_once '../config.php';
require_once '../email_config.php';
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
error_log("forgot_password.php accessed at " . date('Y-m-d H:i:s'));

// Start session
session_start();

// Simple test to see if file is accessible
if (isset($_GET['test'])) {
    sendJsonResponse(true, 'forgot_password.php is working!');
}

// Handle forgot password request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'forgot_password') {
    
    $email = trim($_POST['email'] ?? '');
    
    // Validate email
    if (empty($email)) {
        sendJsonResponse(false, 'Email address is required.');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, 'Invalid email address.');
    }
    
    try {
        // Connect to database using the working connection
        $conn = dbConnect();
        if (!$conn) {
            throw new Exception('Database connection failed.');
        }
        
        // Check if email exists in users table
        $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ?");
        if (!$stmt) {
            error_log("Prepare failed on SELECT: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Don't reveal if email exists or not for security
            sendJsonResponse(true, 'If the email exists, a reset link has been sent.');
        }
        
        $user = $result->fetch_assoc();
        
        // Generate reset token
        $reset_token = bin2hex(random_bytes(32));
        $reset_token_hash = hash('sha256', $reset_token);
        $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store reset token in database
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        if (!$stmt) {
            error_log("Prepare failed on UPDATE: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        $stmt->bind_param("sss", $reset_token_hash, $reset_expires, $email);
        $stmt->execute();
        
        // Create confirmation link (NOT reset link)
        $reset_link = siteUrl('AUTH/confirm_password_change.php?token=' . urlencode($reset_token));
        
        // Prepare CONFIRMATION email content
        $emailSubject = 'Password Change Confirmation - Valenzuela City Government';
        $emailBody = '
            <div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
                <div style="background: linear-gradient(135deg, #991b1b, #7f1d1d); color: white; padding: 2rem; text-align: center; border-radius: 8px 8px 0 0;">
                    <h1 style="margin: 0; font-size: 1.5rem;">Valenzuela City Government</h1>
                    <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Public Consultation Management Portal</p>
                </div>
                
                <div style="background: #f9fafb; padding: 2rem; border-radius: 0 0 8px 8px;">
                    <h2 style="color: #1f2937; margin: 0 0 1rem 0;">Password Change Request</h2>
                    
                    <p style="color: #4b5563; margin: 0 0 1.5rem 0; line-height: 1.6;">
                        Hello ' . htmlspecialchars($user['fullname']) . ',<br><br>
                        Was it you who tried to change your password?
                    </p>
                    
                    <div style="text-align: center; margin: 2rem 0;">
                        <a href="' . htmlspecialchars($reset_link) . '" 
                           style="background: #dc2626; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block;">
                            Yes, It Was Me
                        </a>
                    </div>
                    
                    <p style="color: #6b7280; font-size: 0.875rem; margin: 1.5rem 0 0 0; line-height: 1.5;">
                        <strong>Important:</strong><br>
                        • This confirmation link will expire in 1 hour for security reasons.<br>
                        • If it wasn\'t you, please ignore this email and secure your account.<br>
                        • For security, never share this link with anyone.
                    </p>
                    
                    <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 0.8rem;">
                        <p style="margin: 0;">
                            Valenzuela City Government<br>
                            City Hall, MacArthur Highway, Karuhatan, Valenzuela City
                        </p>
                    </div>
                </div>
            </div>';
        
        
        // Send email using the configured function
        try {
            if (sendGmailEmail($email, $emailSubject, $emailBody, true)) {
                sendJsonResponse(true, 'Password reset link has been sent to your email.');
            } else {
                error_log("Email sending failed for user: " . $email);
                sendJsonResponse(false, 'Failed to send reset email. Please try again later.');
            }
        } catch (Exception $e) {
            error_log("Email sending exception: " . $e->getMessage());
            sendJsonResponse(false, 'Failed to send reset email. Please try again later.');
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        error_log("Forgot password error: " . $e->getMessage());
        sendJsonResponse(false, 'An error occurred. Please try again.');
    }
    
} else {
    sendJsonResponse(false, 'Invalid request.');
}
?>
