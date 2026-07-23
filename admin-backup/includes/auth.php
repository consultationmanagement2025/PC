<?php
session_start();

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'], true)) {
    header('Location: ../public/sign-in.php');
    exit;
}

$user = $_SESSION['user'];
