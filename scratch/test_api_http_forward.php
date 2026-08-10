<?php
// Simulate HTTP POST request to API/documents_api.php?action=forward_lrs with JSON payload
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'forward_lrs';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'administrator';
$_SESSION['fullname'] = 'System Administrator';

// Mock php://input content
$mockJson = '{"id": 5, "source": "consultation", "description": "consultation summary forwarded from PCMS"}';

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/documents.php';
require_once __DIR__ . '/../DATABASE/document-management.php';

// Override jsonInput via test wrapper
function jsonInput(): array {
    return ['id' => 5, 'source' => 'consultation', 'description' => 'consultation summary forwarded from PCMS'];
}

$inputData = array_merge($_GET, $_POST, jsonInput());
$id = (int)($inputData['id'] ?? $inputData['document_id'] ?? $inputData['consultation_id'] ?? 0);
$source = strtolower(trim((string)($inputData['source'] ?? 'consultation'))) === 'admin' ? 'admin' : 'consultation';
$description = trim((string)($inputData['description'] ?? ''));
$performer = trim((string)($inputData['performed_by'] ?? $_SESSION['fullname'] ?? 'Admin'));

echo "ID: $id | Source: $source | Description: $description\n";

if (function_exists('forwardDocumentToLRS')) {
    $res = forwardDocumentToLRS($id, $source, $description, $performer);
    echo "API Test Execution Result: " . json_encode($res, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "forwardDocumentToLRS function not found\n";
}
