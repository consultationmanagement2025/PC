<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'decline_submission';
$rawInput = json_encode(['id' => 1, 'reason' => 'Test decline reason from script']);

// Intercept php://input by defining standard input stream testing or setting up request
file_put_contents('scratch/test_input.json', $rawInput);

// Let's call the API logic
ob_start();
include __DIR__ . '/../API/consultations_api.php';
$res = ob_get_clean();
echo "API OUTPUT:\n" . $res . "\n";
