<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'decline_submission';

// Simulate raw php://input using a custom wrapper or testing JSON input
$postData = json_encode(['id' => 1, 'reason' => 'Testing warnings and notices']);

// Put input into a mock file or stream
ob_start();
include __DIR__ . '/../API/consultations_api.php';
$output = ob_get_clean();

echo "RAW OUTPUT LENGTH: " . strlen($output) . "\n";
echo "RAW OUTPUT:\n" . $output . "\n";

// Validate JSON parse
$parsed = json_decode($output, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "VALID JSON SUCCESS: " . json_encode($parsed) . "\n";
} else {
    echo "JSON SYNTAX ERROR: " . json_last_error_msg() . "\n";
}
