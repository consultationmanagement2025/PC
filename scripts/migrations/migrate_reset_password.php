<?php
require_once 'db.php';

header('Content-Type: application/json');

try {
    $conn = dbConnect();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Add reset_token and reset_expires columns to users table
    $sql_statements = [
        "ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL AFTER verification_status",
        "ALTER TABLE users ADD COLUMN reset_expires DATETIME DEFAULT NULL AFTER reset_token"
    ];
    
    $errors = [];
    foreach ($sql_statements as $sql) {
        if (!$conn->query($sql)) {
            // Check if column already exists
            if ($conn->errno === 1060) {
                // Column already exists
                continue;
            }
            $errors[] = $conn->error;
        }
    }
    
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => 'Migration error',
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Migration successful - reset_token and reset_expires columns added'
        ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
