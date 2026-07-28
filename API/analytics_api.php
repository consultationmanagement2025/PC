<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_path = file_exists(__DIR__ . '/../db.php') ? (__DIR__ . '/../db.php') : (file_exists(__DIR__ . '/../../db.php') ? (__DIR__ . '/../../db.php') : (__DIR__ . '/db.php'));
if (file_exists($db_path)) {
    require_once $db_path;
}

// Flexible role check for all admin/staff roles
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$is_authorized = in_array($current_role, ['admin', 'administrator', 'super admin', 'superadmin', 'staff', 'barangay staff', 'barangay_staff', 'barangay', 'user'], true) || !empty($_SESSION['user_id']) || !empty($_SESSION['admin_id']);

if (!$is_authorized) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // 1. User statistics
    $users_stats = ['total_users' => 0, 'admins' => 0, 'citizens' => 0, 'active_users' => 0, 'new_users_30d' => 0];
    if (isset($conn) && $conn) {
        $users_sql = "SELECT 
                        COUNT(*) as total_users,
                        SUM(CASE WHEN LOWER(role) LIKE '%admin%' THEN 1 ELSE 0 END) as admins,
                        SUM(CASE WHEN role IS NULL OR LOWER(role) IN ('citizen','user','') THEN 1 ELSE 0 END) as citizens,
                        SUM(CASE WHEN status = 'active' OR status IS NULL THEN 1 ELSE 0 END) as active_users,
                        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30d
                        FROM users";
        $users_result = $conn->query($users_sql);
        if ($users_result) {
            $users_stats = array_merge($users_stats, $users_result->fetch_assoc() ?: []);
        }
    }
    
    // 2. Consultation / Posts statistics
    $posts_stats = ['total_posts' => 0, 'approved_posts' => 0, 'pending_posts' => 0, 'rejected_posts' => 0, 'unique_contributors' => 0, 'posts_30d' => 0];
    if (isset($conn) && $conn) {
        $posts_sql = "SELECT 
                        COUNT(*) as total_posts,
                        SUM(CASE WHEN LOWER(status) = 'approved' THEN 1 ELSE 0 END) as approved_posts,
                        SUM(CASE WHEN LOWER(status) = 'pending' THEN 1 ELSE 0 END) as pending_posts,
                        SUM(CASE WHEN LOWER(status) = 'rejected' THEN 1 ELSE 0 END) as rejected_posts,
                        COUNT(DISTINCT user_email) as unique_contributors,
                        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as posts_30d
                        FROM consultations";
        $posts_result = $conn->query($posts_sql);
        if ($posts_result) {
            $posts_stats = array_merge($posts_stats, $posts_result->fetch_assoc() ?: []);
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'users' => $users_stats,
            'posts' => $posts_stats
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
