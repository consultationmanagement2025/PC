<?php
require_once 'db.php';
require_once 'AUTH/forgot_password.php';

header('Content-Type: application/json');

try {
    $conn = dbConnect();
    
    // Generate token for user 3
    $reset_token = bin2hex(random_bytes(32));
    $reset_token_hash = hash('sha256', $reset_token);
    $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $stmt->bind_param("ssi", $reset_token_hash, $reset_expires, 3);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'token' => $reset_token,
        'token_hash' => $reset_token_hash,
        'reset_url' => "reset_password.php?token=" . $reset_token
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
