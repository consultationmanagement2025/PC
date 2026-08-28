<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../UTILS/orts_integration_utils.php';

echo "Fetching documents from ORTS API...\n";
$res = fetchOrtsDocuments([]);
echo "HTTP Code: " . ($res['http_code'] ?? 'N/A') . "\n";
echo "Data:\n";
print_r($res['data'] ?? []);
