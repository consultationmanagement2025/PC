<?php
require_once '../config/config.php';
require_once '../db.php';
require_once '../config/email_config.php';

header('Content-Type: application/json');

try {
    $conn = dbConnect();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Check users
    $result = $conn->query("SELECT id, email, fullname FROM users LIMIT 5");
    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Query error: ' . $conn->error
        ]);
        exit;
    }
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'users_count' => count($users),
        'users' => $users
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
