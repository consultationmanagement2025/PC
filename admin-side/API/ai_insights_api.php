<?php
header('Content-Type: application/json');
session_start();
require_once '../db.php';

$current_role = isset($_SESSION['role']) ? strtolower(trim((string)$_SESSION['role'])) : '';
$is_staff = in_array($current_role, ['staff', 'resource person', 'resource_person'], true);
if ($current_role !== 'admin' && $current_role !== 'administrator' && $current_role !== 'super admin' && $current_role !== 'superadmin' && !$is_staff) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function fetchOne($conn, $sql) {
    $res = $conn->query($sql);
    if (!$res) return [];
    $row = $res->fetch_assoc();
    return $row ?: [];
}

function fetchAllRows($conn, $sql) {
    $rows = [];
    $res = $conn->query($sql);
    if (!$res) return $rows;
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    return $rows;
}

try {
    $consult = fetchOne($conn, "SELECT
        COUNT(*) AS total_consultations,
        SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_consultations,
        SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) AS closed_consultations,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_consultations
        FROM consultations");

    $posts = fetchOne($conn, "SELECT
        COUNT(*) AS total_feedback,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_feedback,
        SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved_feedback
        FROM posts");

    $feedback = fetchOne($conn, "SELECT
        COUNT(*) AS feedback_entries,
        AVG(rating) AS avg_rating
        FROM feedback");

    $activeNoFeedback = fetchAllRows($conn, "SELECT
        c.id, c.title, c.start_date, c.end_date,
        COALESCE(p.cnt, 0) AS feedback_count
        FROM consultations c
        LEFT JOIN (
            SELECT consultation_id, COUNT(*) AS cnt
            FROM posts
            GROUP BY consultation_id
        ) p ON p.consultation_id = c.id
        WHERE c.status='active' AND COALESCE(p.cnt, 0)=0
        ORDER BY c.start_date ASC
        LIMIT 20");

    $staleActive = fetchAllRows($conn, "SELECT
        id, title, start_date, end_date
        FROM consultations
        WHERE status='active' AND start_date IS NOT NULL AND start_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY start_date ASC
        LIMIT 20");

    $feedbackTrend = fetchAllRows($conn, "SELECT DATE(created_at) AS dt, COUNT(*) AS count
        FROM posts
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        GROUP BY DATE(created_at)
        ORDER BY dt ASC");

    $consultTrend = fetchAllRows($conn, "SELECT DATE(created_at) AS dt, COUNT(*) AS count
        FROM consultations
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        GROUP BY DATE(created_at)
        ORDER BY dt ASC");

    $kpis = [
        'total_consultations' => (int)($consult['total_consultations'] ?? 0),
        'active_consultations' => (int)($consult['active_consultations'] ?? 0),
        'closed_consultations' => (int)($consult['closed_consultations'] ?? 0),
        'pending_consultations' => (int)($consult['pending_consultations'] ?? 0),
        'total_feedback' => (int)($posts['total_feedback'] ?? 0),
        'pending_feedback' => (int)($posts['pending_feedback'] ?? 0),
        'approved_feedback' => (int)($posts['approved_feedback'] ?? 0),
        'avg_rating' => round((float)($feedback['avg_rating'] ?? 0), 2),
    ];

    $insights = [];

    $pendingFeedback = $kpis['pending_feedback'];
    $totalFeedback = max(1, $kpis['total_feedback']);
    $pendingRatio = $pendingFeedback / $totalFeedback;
    if ($pendingFeedback >= 20 || $pendingRatio >= 0.4) {
        $insights[] = [
            'severity' => 'high',
            'title' => 'Feedback backlog is growing',
            'detail' => "Pending feedback is {$pendingFeedback} (" . round($pendingRatio * 100, 1) . "% of all feedback).",
            'recommendation' => 'Prioritize feedback triage and set SLA targets for first response.'
        ];
    }

    if (count($activeNoFeedback) > 0) {
        $insights[] = [
            'severity' => 'medium',
            'title' => 'Low engagement on active consultations',
            'detail' => count($activeNoFeedback) . ' active consultation(s) currently have zero feedback.',
            'recommendation' => 'Boost visibility via announcements and targeted outreach.'
        ];
    }

    if (count($staleActive) > 0) {
        $insights[] = [
            'severity' => 'medium',
            'title' => 'Stale active consultations detected',
            'detail' => count($staleActive) . ' active consultation(s) have been open for more than 30 days.',
            'recommendation' => 'Review status and close/archive consultations that are no longer active.'
        ];
    }

    if (empty($insights)) {
        $insights[] = [
            'severity' => 'low',
            'title' => 'System is stable',
            'detail' => 'No critical analytics risks detected from current operational metrics.',
            'recommendation' => 'Continue periodic monitoring and weekly review.'
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'kpis' => $kpis,
            'insights' => $insights,
            'active_without_feedback' => $activeNoFeedback,
            'stale_active' => $staleActive,
            'trends' => [
                'feedback_14d' => $feedbackTrend,
                'consultations_14d' => $consultTrend
            ],
            'generated_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

