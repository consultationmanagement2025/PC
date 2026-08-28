<?php
/**
 * Dedicated ORTS Consultation Sync API
 * Synchronizes active ORTS ordinances into PCMS public consultations
 */
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../UTILS/orts_integration_utils.php';

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
$has_session = !empty($_SESSION['user_id']) || !empty($_SESSION['email']);

if (!$has_session && !in_array($role, ['admin', 'administrator', 'super admin', 'superadmin', 'staff'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized admin/staff access required']);
    exit;
}

$res = syncOrtsConsultationsToPcms($conn);
http_response_code($res['success'] ? 200 : 500);
echo json_encode($res, JSON_PRETTY_PRINT);
