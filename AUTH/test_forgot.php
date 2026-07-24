<?php
// Start output buffering to catch any unwanted output
ob_start();

// Disable error display
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Test simple response
echo json_encode(['success' => true, 'message' => 'Test working!']);

// Clean any output and send
$output = ob_get_clean();
if (!empty($output)) {
    echo "DEBUG: Caught output: " . $output;
} else {
    echo json_encode(['success' => true, 'message' => 'Clean output!']);
}
?>
