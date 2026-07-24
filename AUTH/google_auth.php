<?php
header('Content-Type: application/json');
session_start();

// Placeholder endpoint so production does not fail with 404.
// Implement Google token verification before enabling this flow.
echo json_encode([
    'success' => false,
    'message' => 'Google login is not configured on this deployment.'
]);
?>
