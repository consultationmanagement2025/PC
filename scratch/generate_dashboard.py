import sys

php_code = """<?php
/**
 * Resource Person Workspace - Redesigned Expert Contribution & Task Board Portal
 * City of Valenzuela PCMS (Policy & Consultation Management System)
 */
session_start();
require_once 'db.php';
require_once 'UTILS/session_check.php';

// Check if user is logged in and is a resource person or admin/staff
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$allowed = ['resource person', 'resource_person', 'staff', 'admin', 'administrator', 'super admin', 'superadmin'];
if (!in_array($current_role, $allowed, true)) {
    header('Location: login.php');
    exit;
}

function ensureResourcePersonSchema($conn) {
    if (!$conn) return;
    
    // 1. Users table additions
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM users");
    if ($res) {
        while ($r = $res->fetch_assoc()) { $cols[] = $r['Field']; }
    }
    if (!in_array('google_id', $cols)) @$conn->query("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) DEFAULT NULL");
    if (!in_array('google_token', $cols)) @$conn->query("ALTER TABLE users ADD COLUMN google_token TEXT DEFAULT NULL");
    if (!in_array('expertise_areas', $cols)) @$conn->query("ALTER TABLE users ADD COLUMN expertise_areas TEXT DEFAULT NULL");
    if (!in_array('qualifications', $cols)) @$conn->query("ALTER TABLE users ADD COLUMN qualifications TEXT DEFAULT NULL");
    if (!in_array('department', $cols)) @$conn->query("ALTER TABLE users ADD COLUMN department VARCHAR(255) DEFAULT NULL");
    if (!in_array('phone', $cols)) @$conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(50) DEFAULT NULL");
    if (!in_array('approved_by', $cols)) @$conn->query("ALTER TABLE users ADD COLUMN approved_by INT DEFAULT NULL");
    if (!in_array('approved_at', $cols)) @$conn->query("ALTER TABLE users ADD COLUMN approved_at DATETIME DEFAULT NULL");
    if (!in_array('verification_status', $cols)) @$conn->query("ALTER TABLE users ADD COLUMN verification_status VARCHAR(50) NOT NULL DEFAULT 'pending'");

    // 2. Consultations table additions
    $cCols = [];
    $cRes = $conn->query("SHOW COLUMNS FROM consultations");
    if ($cRes) {
        while ($r = $cRes->fetch_assoc()) { $cCols[] = $r['Field']; }
    }
    if (!in_array('assigned_to', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN assigned_to INT(11) DEFAULT NULL");
    if (!in_array('deadline', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN deadline DATETIME DEFAULT NULL");

    // 3. Resolution Reports table schema & column checks
    @$conn->query("CREATE TABLE IF NOT EXISTS resolution_reports (
        id INT(11) NOT NULL AUTO_INCREMENT,
        consultation_id INT(11) NOT NULL,
        uploaded_by INT(11) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        notes TEXT,
        version_label VARCHAR(50) DEFAULT 'v1.0',
        status ENUM('pending_review', 'approved', 'revision_requested') DEFAULT 'pending_review',
        committee_feedback TEXT DEFAULT NULL,
        reviewed_by INT(11) DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $rrCols = [];
    $rrRes = $conn->query("SHOW COLUMNS FROM resolution_reports");
    if ($rrRes) {
        while ($r = $rrRes->fetch_assoc()) { $rrCols[] = $r['Field']; }
    }
    if (!in_array('version_label', $rrCols)) @$conn->query("ALTER TABLE resolution_reports ADD COLUMN version_label VARCHAR(50) DEFAULT 'v1.0'");
    if (!in_array('status', $rrCols)) @$conn->query("ALTER TABLE resolution_reports ADD COLUMN status ENUM('pending_review', 'approved', 'revision_requested') DEFAULT 'pending_review'");
    if (!in_array('committee_feedback', $rrCols)) @$conn->query("ALTER TABLE resolution_reports ADD COLUMN committee_feedback TEXT DEFAULT NULL");
    if (!in_array('reviewed_by', $rrCols)) @$conn->query("ALTER TABLE resolution_reports ADD COLUMN reviewed_by INT(11) DEFAULT NULL");
    if (!in_array('reviewed_at', $rrCols)) @$conn->query("ALTER TABLE resolution_reports ADD COLUMN reviewed_at DATETIME DEFAULT NULL");

    // 4. Info Requests table schema & column checks
    @$conn->query("CREATE TABLE IF NOT EXISTS info_requests (
        id INT(11) NOT NULL AUTO_INCREMENT,
        consultation_id INT(11) NOT NULL,
        requested_by INT(11) NOT NULL,
        target_entity VARCHAR(100) DEFAULT 'Admin & Committee',
        user_email VARCHAR(255) DEFAULT NULL,
        message TEXT NOT NULL,
        priority ENUM('normal', 'urgent') DEFAULT 'normal',
        status ENUM('pending', 'responded', 'closed') DEFAULT 'pending',
        response_notes TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $irCols = [];
    $irRes = $conn->query("SHOW COLUMNS FROM info_requests");
    if ($irRes) {
        while ($r = $irRes->fetch_assoc()) { $irCols[] = $r['Field']; }
    }
    if (!in_array('target_entity', $irCols)) @$conn->query("ALTER TABLE info_requests ADD COLUMN target_entity VARCHAR(100) DEFAULT 'Admin & Committee'");
    if (!in_array('priority', $irCols)) @$conn->query("ALTER TABLE info_requests ADD COLUMN priority ENUM('normal', 'urgent') DEFAULT 'normal'");
    if (!in_array('response_notes', $irCols)) @$conn->query("ALTER TABLE info_requests ADD COLUMN response_notes TEXT DEFAULT NULL");

    // 5. Expert Notifications table
    @$conn->query("CREATE TABLE IF NOT EXISTS expert_notifications (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) DEFAULT 'assignment',
        consultation_id INT(11) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 6. Knowledge Base Articles table
    @$conn->query("CREATE TABLE IF NOT EXISTS knowledge_base_articles (
        id INT(11) NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        file_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensureResourcePersonSchema($conn);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$fullname = $_SESSION['fullname'] ?? 'Resource Person';
$email = $_SESSION['email'] ?? '';

// Seed sample notifications if empty
$chkNotif = $conn->query("SELECT COUNT(*) as cnt FROM expert_notifications WHERE user_id = $user_id");
if ($chkNotif && ($row = $chkNotif->fetch_assoc()) && $row['cnt'] == 0) {
    @$conn->query("INSERT INTO expert_notifications (user_id, title, message, type, is_read, created_at) VALUES 
    ($user_id, 'New Consultation Assigned', 'You have been assigned to review Ordinance Draft: City Waste Segregation Policy.', 'assignment', 0, NOW()),
    ($user_id, 'Committee Feedback Received', 'City Secretariat approved Resolution Paper v1.0 with minor suggestions.', 'feedback', 0, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
    ($user_id, 'Information Request Update', 'Admin responded to your query regarding flood mapping data.', 'info_response', 0, DATE_SUB(NOW(), INTERVAL 1 DAY))");
}

// Get user profile details
$userProfile = [];
$stmt = $conn->prepare("SELECT expertise_areas, qualifications, department, phone, verification_status FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $userProfile = $res->fetch_assoc() ?: [];
    }
    $stmt->close();
}

$expertise_areas = $userProfile['expertise_areas'] ?? 'Health, Infrastructure & Governance';
$department = $userProfile['department'] ?? 'Public Sector & Technical Advisory';

// Convert expertise_areas string to array of trimmed categories
$my_expertise_raw = strtolower($expertise_areas);
$my_expertise_list = array_values(array_filter(array_map('trim', explode(',', $my_expertise_raw))));

// Get consultations with detailed metadata
$consultations = [];
$consultations_stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM resolution_reports r WHERE r.consultation_id = c.id) as reports_count,
           (SELECT COUNT(*) FROM resolution_reports r WHERE r.consultation_id = c.id AND r.status = 'approved') as approved_reports_count,
           (SELECT COUNT(*) FROM info_requests i WHERE i.consultation_id = c.id) as info_requests_count,
           (SELECT COUNT(*) FROM info_requests i WHERE i.consultation_id = c.id AND i.status = 'pending') as pending_info_count
    FROM consultations c 
    WHERE c.assigned_to = ? OR c.assigned_to IS NULL OR ? IN ('admin', 'administrator', 'super admin', 'superadmin')
    ORDER BY c.created_at DESC
");
if ($consultations_stmt) {
    $consultations_stmt->bind_param('is', $user_id, $current_role);
    $consultations_stmt->execute();
    $cRes = $consultations_stmt->get_result();
    if ($cRes) {
        while ($row = $cRes->fetch_assoc()) {
            $consultations[] = $row;
        }
    }
    $consultations_stmt->close();
} else {
    $cRes = $conn->query("SELECT c.*, 0 as reports_count, 0 as approved_reports_count, 0 as info_requests_count, 0 as pending_info_count FROM consultations c ORDER BY c.created_at DESC");
    if ($cRes) {
        while ($row = $cRes->fetch_assoc()) {
            $consultations[] = $row;
        }
    }
}

// Fetch unread notifications count
$unread_notif_count = 0;
$nStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM expert_notifications WHERE user_id = ? AND is_read = 0");
if ($nStmt) {
    $nStmt->bind_param('i', $user_id);
    $nStmt->execute();
    $nRes = $nStmt->get_result();
    if ($nRes && $nRow = $nRes->fetch_assoc()) {
        $unread_notif_count = (int)$nRow['cnt'];
    }
    $nStmt->close();
}

// Calculate Metrics & Workload Categorization
$total_assigned = 0;
$active_count = 0;
$completed_count = 0;
$total_reports = 0;
$approved_reports = 0;
$pending_reviews_count = 0;
$overdue_count = 0;
$due_soon_count = 0;
$now_ts = time();

foreach ($consultations as &$c) {
    $cCat = strtolower(trim($c['category'] ?? ''));
    $c['is_expertise_match'] = false;
    if (!empty($cCat) && !empty($my_expertise_list)) {
        foreach ($my_expertise_list as $exp) {
            if (!empty($exp) && (strpos($cCat, $exp) !== false || strpos($exp, $cCat) !== false)) {
                $c['is_expertise_match'] = true;
                break;
            }
        }
    }

    $isAssigned = ($c['assigned_to'] == $user_id);
    if ($isAssigned) {
        $total_assigned++;
    }

    // Determine Deadline (Use column or calculate +7 days default)
    $createdAtTs = !empty($c['created_at']) ? strtotime($c['created_at']) : $now_ts;
    $deadlineTs = !empty($c['deadline']) ? strtotime($c['deadline']) : ($createdAtTs + (7 * 86400));
    $c['deadline_formatted'] = date('M j, Y', $deadlineTs);
    $diffDays = ceil(($deadlineTs - $now_ts) / 86400);
    $c['deadline_days'] = $diffDays;

    if ($diffDays < 0 && !in_array($c['status'], ['completed', 'closed', 'endorsed'])) {
        $c['deadline_status'] = 'overdue';
        if ($isAssigned) $overdue_count++;
    } elseif ($diffDays <= 3 && !in_array($c['status'], ['completed', 'closed', 'endorsed'])) {
        $c['deadline_status'] = 'due_soon';
        if ($isAssigned) $due_soon_count++;
    } else {
        $c['deadline_status'] = 'normal';
    }

    // Workflow status classification
    $status = strtolower(trim($c['status'] ?? 'active'));
    $reportsCount = (int)($c['reports_count'] ?? 0);
    $approvedCount = (int)($c['approved_reports_count'] ?? 0);

    $total_reports += $reportsCount;
    $approved_reports += $approvedCount;

    if (in_array($status, ['completed', 'closed', 'endorsed'])) {
        $completed_count++;
        $c['kanban_stage'] = 'finalized';
    } elseif ($reportsCount > 0) {
        $c['kanban_stage'] = 'committee_review';
        $active_count++;
    } elseif ($status === 'pending' || $status === 'scheduled') {
        $c['kanban_stage'] = 'pending_review';
        $pending_reviews_count++;
        $active_count++;
    } else {
        $c['kanban_stage'] = 'active_analysis';
        $active_count++;
    }
}
unset($c);

$endorsement_rate = ($total_assigned > 0) ? round(($approved_reports / $total_assigned) * 100) : 100;
?>
"""

print("PHP Header generated cleanly.")
