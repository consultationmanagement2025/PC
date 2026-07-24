<?php
require_once __DIR__ . '/../db.php';

echo "=== Testing Citizens API ===\n\n";

// Simulate admin session
$_SESSION['role'] = 'admin';
$_SESSION['user_id'] = 1;

// Include the API
require_once __DIR__ . '/../API/citizens_api.php';
?>
