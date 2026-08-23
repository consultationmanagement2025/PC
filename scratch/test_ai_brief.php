<?php
$_GET['action'] = 'compile_committee_brief';
$_GET['consultation_id'] = 4;
$_GET['force'] = 1;

ob_start();
require_once __DIR__ . '/../API/consultation_feedback_ai.php';
$output = ob_get_clean();

echo "=== RESULT FOR CONSULTATION 4 ===\n";
echo substr($output, 0, 2000);
