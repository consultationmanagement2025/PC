<?php
require_once 'db.php';
require_once 'email_config.php';

header('Content-Type: application/json');

try {
    // Test database connection
    $conn = dbConnect();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connected',
        'users_table_check' => checkTableExists($conn, 'users'),
        'reset_token_column' => checkColumnExists($conn, 'users', 'reset_token'),
        'reset_expires_column' => checkColumnExists($conn, 'users', 'reset_expires')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function checkTableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

function checkColumnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    return $result && $result->num_rows > 0;
}
?>
