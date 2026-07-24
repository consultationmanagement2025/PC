<?php
require_once 'db.php';

// Get the user
$conn = dbConnect();
$email = 'consultationmanagement2026@gmail.com';

// Generate a new token and store it safely
$raw_token = bin2hex(random_bytes(32));
$hashed_token = hash('sha256', $raw_token);
$expires = date('Y-m-d H:i:s', strtotime('+1 hour'));  // 1 hour from now

$stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
$stmt->bind_param("sss", $hashed_token, $expires, $email);
if ($stmt->execute()) {
    $confirmation_url = "http://localhost/CAP101/PC/AUTH/confirm_password_change.php?token=$raw_token";

    echo json_encode([
        'raw_token' => $raw_token,
        'hashed_token' => $hashed_token,
        'expires' => $expires,
        'confirmation_url' => $confirmation_url,
        'test_instruction' => 'Visit the URL above in your browser to test the confirmation flow'
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode(['error' => 'Failed to update database: ' . $stmt->error], JSON_PRETTY_PRINT);
}
?>
