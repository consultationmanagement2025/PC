<?php
$_GET['action'] = 'compile_and_lock';

ob_start();
require_once __DIR__ . '/../API/unified_feedback_compilation_api.php';
$jsonRaw = ob_get_clean();

echo "=== UNIFIED COMPILATION TEST RESULT ===\n";
echo $jsonRaw . "\n";
