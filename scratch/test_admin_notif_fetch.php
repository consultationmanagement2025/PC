<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['email'] = 'admin@valenzuela.gov.ph';

// Include API
ob_start();
require __DIR__ . '/../API/notifications_api.php';
$output = ob_get_clean();

echo "API Response:\n" . $output . "\n";
