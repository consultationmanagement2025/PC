<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function shutdown() {
    $error = error_get_last();
    if ($error !== NULL) {
        echo "SHUTDOWN ERROR: " . print_r($error, true) . "\n";
    }
}
register_shutdown_function('shutdown');

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/../db.php';
$conn->query("UPDATE consultations SET status = 'pending' WHERE id = 8");

$_GET['action'] = 'decline_submission';
$_POST = ['id' => 8, 'reason' => 'Direct script decline test'];

include __DIR__ . '/../API/consultations_api.php';
