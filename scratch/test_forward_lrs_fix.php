<?php
// Test parsing input in API/documents_api.php
$rawInput = '{"id":"5","source":"consultation","description":"consultation summary forwarded from PCMS"}';

$data = json_decode($rawInput, true) ?: [];
if (empty($data)) {
    $data = $_POST;
}

$id = (int)($data['id'] ?? $data['document_id'] ?? $data['consultation_id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
$source = $data['source'] ?? $_POST['source'] ?? $_GET['source'] ?? 'consultation';
$description = trim((string)($data['description'] ?? $_POST['description'] ?? ''));

echo "Parsed ID: $id\n";
echo "Parsed Source: $source\n";
echo "Parsed Description: $description\n";

if ($id <= 0) {
    echo "RESULT: FAIL (HTTP 400 Invalid Document ID)\n";
} else {
    echo "RESULT: SUCCESS (Parsed ID correctly: $id)\n";
}
