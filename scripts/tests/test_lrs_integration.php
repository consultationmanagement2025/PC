<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../DATABASE/document-management.php';

echo "Testing document_versions functions...\n";

$testData = [
    'document_id' => 1,
    'consultation_id' => 1,
    'reference_number' => 'CONSULT-000001',
    'title' => 'Test Consultation Summary',
    'version_number' => '1.0',
    'document_type' => 'consultation_summary',
    'original_filename' => 'test_document.pdf',
    'stored_filename' => 'test_document_stored.pdf',
    'file_path' => 'uploads/documents/test_document_stored.pdf',
    'file_size' => 1024,
    'file_type' => 'application/pdf',
    'source_system' => 'pcms',
    'status' => 'forwarded_to_lrs',
    'lrs_response' => '{"success":true,"message":"Received"}',
    'notes' => 'Test forward to LRS'
];

$id = addDocumentVersion($testData);
if ($id) {
    echo "SUCCESS: Inserted document version ID: {$id}\n";
} else {
    echo "ERROR: Failed to insert document version.\n";
}

$versions = getDocumentVersions('CONSULT-000001');
echo "Found " . count($versions) . " version(s) for CONSULT-000001.\n";

if (count($versions) > 0) {
    echo "Latest version details:\n";
    echo "  Version #: " . $versions[0]['version_number'] . "\n";
    echo "  Title: " . $versions[0]['title'] . "\n";
    echo "  Source System: " . $versions[0]['source_system'] . "\n";
    echo "  Status: " . $versions[0]['status'] . "\n";
}
