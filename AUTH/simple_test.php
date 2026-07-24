<?php
header('Content-Type: application/json');

// Simple test without database
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo json_encode([
        'success' => true,
        'message' => 'Simple test working! No database used.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests allowed'
    ]);
}
?>
