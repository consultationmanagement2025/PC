<?php
require_once 'email_config.php';

// Test email sending
$test_email = 'consultationmanagement2025@gmail.com'; // Test with your own email
$subject = 'Test Email - Valenzuela PC System';
$body = 'This is a test email from the Valenzuela Public Consultation Management System.';

echo "Sending test email to: $test_email<br>";

try {
    if (sendGmailEmail($test_email, $subject, $body, false)) {
        echo "✅ Email sent successfully!";
    }
} catch (Exception $e) {
    echo "❌ Email failed with error: " . $e->getMessage();
}
?>
