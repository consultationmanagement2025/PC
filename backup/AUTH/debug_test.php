<?php
// Simple debug test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Debug test starting...\n";

try {
    echo "Including db.php...\n";
    require_once '../db.php';
    echo "db.php included successfully\n";
    
    echo "Checking global conn...\n";
    global $conn;
    if ($conn) {
        echo "Database connection exists\n";
    } else {
        echo "Database connection is null\n";
    }
    
    echo "Test completed successfully\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
}

echo "End of debug test\n";
?>
