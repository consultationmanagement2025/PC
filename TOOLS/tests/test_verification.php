<?php
session_start();
require_once 'email_config.php';

// Simulate email verification test
$test_email = 'consultationmanagement2025@gmail.com';

// Generate verification token
$token = bin2hex(random_bytes(32));
$_SESSION['email_verification_token'] = $token;
$_SESSION['email_verification_expires'] = time() + 900; // 15 minutes
$_SESSION['pending_email'] = $test_email;

// Create verification link
$verify_link = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?verify_email=" . $token;

// Send verification email
$subject = "Verify Your Email - Test";
$body = "Hello,\n\nPlease click the link below to verify your email address:\n\n" . $verify_link . "\n\nThis link will expire in 15 minutes.\n\nIf you did not request this, please ignore this email.\n\nRegards,\nValenzuela City Government";

echo "<div style='text-align: center; padding: 2rem; font-family: Arial, sans-serif;'>";
echo "<h2 style='color: #10b981; margin-bottom: 1rem;'>📧 Email Verification Test</h2>";
echo "<p style='color: #6b7280; font-size: 1.1rem; margin-bottom: 1.5rem;'>Sending verification email to: <strong>$test_email</strong></p>";

if (sendGmailEmail($test_email, $subject, $body, false)) {
    echo "<div style='background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;'>";
    echo "✅ <strong>Verification email sent successfully!</strong><br>";
    echo "Please check your Gmail inbox and click the verification link to continue.";
    echo "</div>";
    echo "<p style='color: #059669; font-size: 0.9rem;'>📩 Check your spam folder if you don't see the email within 2 minutes.</p>";
} else {
    echo "<div style='background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px;'>";
    echo "❌ <strong>Failed to send verification email.</strong><br>";
    echo "Please check your email configuration and try again.";
    echo "</div>";
}
echo "</div>";

// Check if verification link was clicked
if (isset($_GET['verify_email'])) {
    $token = trim($_GET['verify_email']);
    
    echo "<div style='text-align: center; padding: 2rem; font-family: Arial, sans-serif;'>";
    echo "<h2 style='color: #10b981; margin-bottom: 1rem;'>🔐 Email Verification Result</h2>";
    
    if (!isset($_SESSION['email_verification_token'])) {
        echo "<div style='background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px;'>";
        echo "❌ <strong>No active verification request found.</strong><br>";
        echo "Please request a new verification email.";
        echo "</div>";
    } elseif (time() > $_SESSION['email_verification_expires']) {
        echo "<div style='background: #fef3c7; color: #92400e; padding: 1rem; border-radius: 8px;'>";
        echo "⏰ <strong>Verification link has expired.</strong><br>";
        echo "Please request a new verification email. Links expire after 15 minutes for security.";
        echo "</div>";
        unset($_SESSION['email_verification_token'], $_SESSION['email_verification_expires']);
    } elseif ($token !== $_SESSION['email_verification_token']) {
        echo "<div style='background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px;'>";
        echo "🔒 <strong>Invalid verification link.</strong><br>";
        echo "The link you used is not valid. Please request a new verification email.";
        echo "</div>";
    } else {
        // Email verified!
        echo "<div style='background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 8px;'>";
        echo "✅ <strong>Email verified successfully!</strong><br>";
        echo "Your email address has been confirmed and you can now proceed.";
        echo "</div>";
        $_SESSION['verified_email'] = $_SESSION['pending_email'];
        unset($_SESSION['email_verification_token'], $_SESSION['email_verification_expires']);
        echo "<p style='color: #059669; margin-top: 1rem;'>📧 Verified email: <strong>" . htmlspecialchars($_SESSION['verified_email']) . "</strong></p>";
    }
    echo "</div>";
}
?>
