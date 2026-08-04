<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../DATABASE/document-management.php';

echo "Initializing document_versions table...\n";
if (initializeDocumentVersionsTable()) {
    echo "SUCCESS: document_versions table is ready.\n";
} else {
    echo "ERROR: Failed to initialize document_versions table.\n";
}
