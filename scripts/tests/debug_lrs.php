<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../DATABASE/documents.php';
require_once __DIR__ . '/../../DATABASE/document-management.php';

echo "Debugging forwardDocumentToLRS(1, 'consultation')...\n";

try {
    $res = forwardDocumentToLRS(1, 'consultation', 'Test debug forward');
    print_r($res);
} catch (Throwable $t) {
    echo "EXCEPTION: " . $t->getMessage() . "\n" . $t->getTraceAsString() . "\n";
}
