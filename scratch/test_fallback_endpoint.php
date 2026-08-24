<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'update_consultation_status';
$_POST['consultation_id'] = 1;
$_POST['status'] = 'declined';
$_POST['reason'] = 'Testing fallback endpoint in system-template-full.php';

ob_start();
include __DIR__ . '/../system-template-full.php';
$output = ob_get_clean();

echo "FALLBACK OUTPUT:\n" . $output . "\n";
