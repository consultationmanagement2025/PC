<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/../db.php';
$conn->query("UPDATE consultations SET status = 'pending' WHERE id = 8");

$_GET['action'] = 'decline_submission';
$_POST = ['id' => 8, 'reason' => 'Direct script decline test'];

ob_start();
include __DIR__ . '/../API/consultations_api.php';
$out = ob_get_clean();

echo "DIRECT RUN RESULT:\n" . $out . "\n\n";

$res = $conn->query("SELECT id, title, user_name, status, admin_response FROM consultations WHERE id = 8");
echo "DB RECORD AFTER DIRECT RUN:\n";
print_r($res->fetch_assoc());
