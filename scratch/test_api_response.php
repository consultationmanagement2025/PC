<?php
chdir(__DIR__ . '/../API');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$_GET['action'] = 'phms_pending_approvals';
require __DIR__ . '/../API/feedback_api.php';
