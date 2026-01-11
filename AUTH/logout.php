<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/user-logs.php';
require_once __DIR__ . '/../DATABASE/audit-log.php';

// Log logout - ADMIN to audit log, CITIZEN to user activity log
if (isset($_SESSION['user_id']) && isset($_SESSION['fullname'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Administrator') {
        logAction($_SESSION['user_id'], $_SESSION['fullname'], 'logout', 'user', $_SESSION['user_id'], null, null, 'success', 'Admin logged out');
    } else {
        logUserAction($_SESSION['user_id'], $_SESSION['fullname'], 'logout', 'authentication', 'user', $_SESSION['user_id'], 'Citizen logged out', 'success');
    }
}

