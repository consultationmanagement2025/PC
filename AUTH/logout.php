<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/user-logs.php';

// Log user logout before destroying session
if (isset($_SESSION['user_id']) && isset($_SESSION['fullname'])) {
    logUserAction($_SESSION['user_id'], $_SESSION['fullname'], 'logout', 'authentication', 'user', $_SESSION['user_id'], 'User logged out', 'success');
}

