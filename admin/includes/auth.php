<?php
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'], true)) {
    header('Location: ../public/sign-in.php');
    exit;
}

$user = [
    'fullname' => $_SESSION['fullname'] ?? 'User',
    'email' => $_SESSION['email'] ?? '',
    'role' => $_SESSION['role'] ?? ''
];
