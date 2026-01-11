<?php
header('Content-Type: application/json');
require_once '../db.php';

// Check admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get comprehensive analytics data
    
    // 1. User statistics
    $users_sql = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN role = 'Administrator' THEN 1 ELSE 0 END) as admins,
                    SUM(CASE WHEN role = 'Citizen' THEN 1 ELSE 0 END) as citizens,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30d
                    FROM users";
    $users_result = $conn->query($users_sql);
    $users_stats = $users_result->fetch_assoc();
    
    // 2. Posts statistics
    $posts_sql = "SELECT 
                    COUNT(*) as total_posts,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_posts,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_posts,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_posts,
                    COUNT(DISTINCT user_id) as unique_contributors,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as posts_30d
                    FROM posts";
    $posts_result = $conn->query($posts_sql);
    $posts_stats = $posts_result->fetch_assoc();
    
    // 3. Daily posts trend (last 30 days)
    $daily_posts_sql = "SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as count
                        FROM posts
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        GROUP BY DATE(created_at)
                        ORDER BY date ASC";
    $daily_posts_result = $conn->query($daily_posts_sql);
    $daily_posts = [];
    while ($row = $daily_posts_result->fetch_assoc()) {
        $daily_posts[] = $row;
    }
    
    // 4. User registration trend (last 30 days)
    $daily_users_sql = "SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as count
                        FROM users
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        GROUP BY DATE(created_at)
                        ORDER BY date ASC";
    $daily_users_result = $conn->query($daily_users_sql);
    $daily_users = [];
    while ($row = $daily_users_result->fetch_assoc()) {
        $daily_users[] = $row;
    }
    
    // 5. Posts by status breakdown
    $status_sql = "SELECT 
                    status,
                    COUNT(*) as count
                    FROM posts
                    GROUP BY status";
    $status_result = $conn->query($status_sql);
    $status_breakdown = [];
    while ($row = $status_result->fetch_assoc()) {
        $status_breakdown[] = $row;
    }
    
    // 6. Announcements
    $announcements_sql = "SELECT COUNT(*) as count FROM announcements";
    $announcements_result = $conn->query($announcements_sql);
    $announcements_count = $announcements_result->fetch_assoc()['count'];
    
    // 7. Consultations
    $consultations_sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
                        FROM consultations";
    $consultations_result = $conn->query($consultations_sql);
    $consultations_stats = $consultations_result->fetch_assoc();
    
    // 8. Top contributors (users with most posts)
    $top_users_sql = "SELECT 
                        u.id,
                        u.username,
                        COUNT(p.id) as post_count
                        FROM users u
                        LEFT JOIN posts p ON u.id = p.user_id
                        GROUP BY u.id
                        ORDER BY post_count DESC
                        LIMIT 10";
    $top_users_result = $conn->query($top_users_sql);
    $top_users = [];
    while ($row = $top_users_result->fetch_assoc()) {
        $top_users[] = $row;
    }
    
    // 9. Login activity (last 7 days)
    $login_sql = "SELECT 
                    DATE(timestamp) as date,
                    COUNT(*) as logins
                    FROM user_logs
                    WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY DATE(timestamp)
                    ORDER BY date ASC";
    $login_result = $conn->query($login_sql);
    $login_activity = [];
    if ($login_result) {
        while ($row = $login_result->fetch_assoc()) {
            $login_activity[] = $row;
        }
    }
    
    // 10. Feedback statistics
    $feedback_sql = "SELECT 
                    COUNT(*) as total,
                    AVG(rating) as avg_rating,
                    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_feedback
                    FROM feedback";
    $feedback_result = $conn->query($feedback_sql);
    $feedback_stats = $feedback_result->fetch_assoc();
    
    $response = [
        'success' => true,
        'data' => [
            'users' => $users_stats,
            'posts' => $posts_stats,
            'daily_posts' => $daily_posts,
            'daily_users' => $daily_users,
            'status_breakdown' => $status_breakdown,
            'announcements' => $announcements_count,
            'consultations' => $consultations_stats,
            'top_users' => $top_users,
            'login_activity' => $login_activity,
            'feedback' => $feedback_stats,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
