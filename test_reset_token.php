<?php
require_once 'db.php';

header('Content-Type: application/json');

try {
    $conn = dbConnect();
    if (!$conn) {
        die(json_encode(['success' => false, 'message' => 'Database connection failed']));
    }
    
    // Get a user
    $result = $conn->query("SELECT id, email, fullname FROM users LIMIT 1");
    if ($result->num_rows === 0) {
        die(json_encode(['success' => false, 'message' => 'No users found']));
    }
    
    $user = $result->fetch_assoc();
    
    // Generate a reset token
    $reset_token = bin2hex(random_bytes(32));
    $reset_token_hash = hash('sha256', $reset_token);
    $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Update user with reset token
    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $stmt->bind_param("ssi", $reset_token_hash, $reset_expires, $user['id']);
    $stmt->execute();
    
    // Generate reset link
    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/CAP101/PC/reset_password.php?token=" . $reset_token;
    
    echo json_encode([
        'success' => true,
        'message' => 'Reset token generated',
        'user' => $user,
        'reset_link' => $reset_link,
        'token' => $reset_token
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
