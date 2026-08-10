<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/documents.php';
require_once __DIR__ . '/../DATABASE/document-management.php';

echo "Testing forwardDocumentToLRS for Consultation #5...\n";
if (function_exists('forwardDocumentToLRS')) {
    $res = forwardDocumentToLRS(5, 'consultation', 'Test forwarding consultation #5 from PCMS', 'System Administrator');
    echo "Return result: " . json_encode($res, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "forwardDocumentToLRS function NOT found!\n";
}
