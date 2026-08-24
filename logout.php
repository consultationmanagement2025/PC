<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$fullname = $_SESSION['fullname'] ?? ($_SESSION['full_name'] ?? 'User');
$role = strtolower(trim((string)($_SESSION['role'] ?? 'user')));
$isAdminRole = in_array($role, ['admin', 'super admin', 'superadmin', 'staff', 'barangay staff', 'resource person', 'resource_person', 'expert'], true);
$isAdminPath = strpos($_SERVER['HTTP_REFERER'] ?? '', '/admin') !== false || strpos($_SERVER['HTTP_REFERER'] ?? '', '/admin-side') !== false;

@require_once __DIR__ . '/db.php';
@require_once __DIR__ . '/DATABASE/audit-log.php';

if ($user_id && function_exists('logAction')) {
    @logAction($user_id, $fullname, 'logout', 'user', $user_id, null, null, 'success', 'User logged out');
}

session_unset();
session_destroy();

if ($isAdminRole || $isAdminPath) {
    header("Location: login.php?logout=success");
} else {
    header("Location: index.php?logout=success");
}
exit;
