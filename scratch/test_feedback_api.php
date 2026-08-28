<?php
$_SESSION['role'] = 'admin';
$_SESSION['user_id'] = 1;
$_SESSION['email'] = 'admin@valenzuela.gov.ph';
$_GET['action'] = 'list';
$_GET['limit'] = 200;
$_GET['offset'] = 0;

ob_start();
require __DIR__ . '/../API/feedback_api.php';
$output = ob_get_clean();

echo "=== FEEDBACK API OUTPUT ===\n";
echo substr($output, 0, 500) . "\n";
?>
