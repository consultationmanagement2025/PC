<?php
require_once __DIR__ . '/../db.php';
$c = dbEnsureConnection();

$email = 'consultationmanagement2026@gmail.com';
$password = 'consultation2026';

$stmt = $c->prepare("SELECT id, fullname, password, role, email FROM users WHERE email=? OR username=?");
$stmt->bind_param("ss", $email, $email);
$stmt->execute();
$res = $stmt->get_result();

if ($user = $res->fetch_assoc()) {
    echo "Found user via dbConnect(): ID={$user['id']}, Email={$user['email']}\n";
    if (password_verify($password, $user['password'])) {
        echo "Password verified successfully!\n";
    } else {
        echo "Password verification failed!\n";
    }
} else {
    echo "User NOT found!\n";
}
