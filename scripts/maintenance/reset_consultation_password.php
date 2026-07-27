<?php
/**
 * TEMPORARY PASSWORD RESET SCRIPT
 * This script resets the consultation2026 super admin account password
 * DELETE THIS FILE after use for security
 */

require_once 'db.php';

$conn = dbEnsureConnection();

// New password - change this to something secure
$new_password = "Consultation2026@Reset";

// Hash the password using bcrypt (same as your system)
$hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

// Update the consultation2026 user
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ? OR username = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$email = "consultation2026@valenzuela.gov.ph"; // Adjust if different
$username = "consultation2026";

$stmt->bind_param("sss", $hashed_password, $email, $username);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo "<div style='padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;'>";
        echo "<h3 style='color: #155724; margin: 0 0 10px 0;'>✓ Password Reset Successful</h3>";
        echo "<p><strong>Account:</strong> consultation2026</p>";
        echo "<p><strong>New Password:</strong> " . htmlspecialchars($new_password) . "</p>";
        echo "<p style='color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px;'>";
        echo "<strong>⚠️ IMPORTANT:</strong> Delete this script immediately after using it!";
        echo "</p>";
        echo "</div>";
    } else {
        echo "<div style='padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;'>";
        echo "<h3 style='color: #721c24; margin: 0 0 10px 0;'>✗ User Not Found</h3>";
        echo "<p>Could not find user with email: " . htmlspecialchars($email) . "</p>";
        echo "<p>or username: " . htmlspecialchars($username) . "</p>";
        echo "</div>";
    }
} else {
    echo "<div style='padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;'>";
    echo "<h3 style='color: #721c24;'>✗ Database Error</h3>";
    echo "<p>" . htmlspecialchars($stmt->error) . "</p>";
    echo "</div>";
}

$stmt->close();
$conn->close();
?>
