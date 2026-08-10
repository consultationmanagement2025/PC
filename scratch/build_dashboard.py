import os

content = """<?php
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
$notifications_list = [];
$nStmt = $conn->prepare("SELECT * FROM expert_notifications WHERE user_id = ? ORDER BY id DESC LIMIT 20");
if ($nStmt) {
    $nStmt->bind_param('i', $user_id);
    $nStmt->execute();
    $nRes = $nStmt->get_result();
    if ($nRes) {
        while ($nRow = $nRes->fetch_assoc()) {
            $notifications_list[] = $nRow;
            if (!$nRow['is_read']) $unread_notif_count++;
        }
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

$endorsement_rate = ($total_assigned > 0) ? round(($approved_reports / max($total_assigned, 1)) * 100) : 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Person Workspace - Valenzuela PCMS</title>
    <link rel="icon" type="image/png" href="images/logo.webp">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="ASSETS/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .bg-valenzuela-red { background-color: #800000; }
        .text-valenzuela-red { color: #800000; }
        .border-valenzuela-red { border-color: #800000; }
        .kanban-col { min-height: 520px; }
        .scrollbar-thin::-webkit-scrollbar { width: 6px; height: 6px; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen flex flex-col md:flex-row">

    <!-- Mobile Top Navigation Header Bar -->
    <header class="md:hidden bg-gradient-to-r from-red-800 via-red-900 to-slate-900 text-white p-4 flex justify-between items-center sticky top-0 z-40 shadow-md">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="text-white text-xl p-1 focus:outline-none">
                <i class="bi bi-list"></i>
            </button>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center p-0.5 shadow">
                    <img src="images/logo.webp" alt="Logo" class="w-full h-full object-contain" onerror="this.src='ASSETS/images/logo.png'">
                </div>
                <span class="font-bold text-sm">PCMS Expert Portal</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="toggleNotificationDrawer()" class="relative p-2 text-white hover:text-red-200">
                <i class="bi bi-bell text-lg"></i>
                <?php if ($unread_notif_count > 0): ?>
                    <span class="absolute top-1 right-1 w-4 h-4 bg-amber-400 text-slate-950 font-bold text-[9px] rounded-full flex items-center justify-center"><?php echo $unread_notif_count; ?></span>
                <?php endif; ?>
            </button>
            <a href="logout.php" class="text-xs bg-red-950/60 px-3 py-1.5 rounded-lg border border-red-700/50 flex items-center gap-1">
                <i class="bi bi-box-arrow-right"></i> Exit
            </a>
        </div>
    </header>

    <!-- Admin-Style Collapsible Sidebar -->
    <aside id="sidebar" class="sidebar w-64 bg-gradient-to-b from-red-800 via-red-900 to-slate-950 text-white flex-shrink-0 flex flex-col h-screen fixed md:sticky top-0 z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-300 shadow-xl">
        <!-- Logo Header Section -->
        <div class="p-6 border-b border-red-700/60 flex items-center gap-3">
            <div class="w-14 h-14 bg-white rounded-full flex items-center justify-center p-1 shadow-md shrink-0">
                <img src="images/logo.webp" alt="Valenzuela Logo" class="w-full h-full object-contain" onerror="this.src='ASSETS/images/logo.png'">
            </div>
            <div>
                <h1 class="text-lg font-bold leading-tight">PCMS Portal</h1>
                <p class="text-xs text-red-200 font-medium">City of Valenzuela</p>
            </div>
        </div>

        <!-- Sidebar Navigation -->
        <nav class="flex-1 py-4 px-3 overflow-y-auto space-y-6 scrollbar-thin">
            <div>
                <p class="text-[11px] font-bold text-red-200/70 uppercase tracking-wider px-3 mb-2">Expert Board</p>
                <div class="space-y-1">
                    <a href="#taskboard" onclick="switchMainTab('taskboard')" id="side-nav-taskboard" class="flex items-center px-4 py-3 text-white bg-red-700/90 rounded-xl font-semibold text-sm transition shadow-sm hover:bg-red-700 gap-3">
                        <i class="bi bi-kanban text-lg"></i>
                        <span>Task Board</span>
                    </a>
                    <a href="#assigned" onclick="switchMainTab('assigned')" id="side-nav-assigned" class="flex items-center px-4 py-3 text-red-100 hover:bg-red-700/60 hover:text-white rounded-xl text-sm transition gap-3">
                        <i class="bi bi-journal-check text-lg"></i>
                        <span>Assigned Tasks</span>
                        <?php if ($total_assigned > 0): ?>
                            <span class="ml-auto px-2 py-0.5 rounded-full text-xs bg-white text-red-900 font-bold"><?php echo $total_assigned; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="#reports" onclick="switchMainTab('reports')" id="side-nav-reports" class="flex items-center px-4 py-3 text-red-100 hover:bg-red-700/60 hover:text-white rounded-xl text-sm transition gap-3">
                        <i class="bi bi-file-earmark-code text-lg"></i>
                        <span>Resolution Reports</span>
                        <?php if ($total_reports > 0): ?>
                            <span class="ml-auto px-2 py-0.5 rounded-full text-xs bg-emerald-400 text-slate-950 font-bold"><?php echo $total_reports; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="#inquiries" onclick="switchMainTab('inquiries')" id="side-nav-inquiries" class="flex items-center px-4 py-3 text-red-100 hover:bg-red-700/60 hover:text-white rounded-xl text-sm transition gap-3">
                        <i class="bi bi-question-circle text-lg"></i>
                        <span>Info Requests</span>
                    </a>
                </div>
            </div>

            <div>
                <p class="text-[11px] font-bold text-red-200/70 uppercase tracking-wider px-3 mb-2">Resource Hub</p>
                <div class="space-y-1">
                    <button onclick="openKnowledgeBaseModal()" class="w-full text-left flex items-center px-4 py-3 text-red-100 hover:bg-red-700/60 hover:text-white rounded-xl text-sm transition gap-3">
                        <i class="bi bi-book text-lg text-amber-300"></i>
                        <span>Knowledge Base & Guidelines</span>
                    </button>
                    <a href="#analytics" onclick="switchMainTab('analytics')" id="side-nav-analytics" class="flex items-center px-4 py-3 text-red-100 hover:bg-red-700/60 hover:text-white rounded-xl text-sm transition gap-3">
                        <i class="bi bi-graph-up-arrow text-lg"></i>
                        <span>Performance Metrics</span>
                    </a>
                </div>
            </div>

            <div>
                <p class="text-[11px] font-bold text-red-200/70 uppercase tracking-wider px-3 mb-2">Account Profile</p>
                <div class="bg-red-950/50 p-3 rounded-2xl border border-red-700/40 text-xs space-y-1.5">
                    <p class="font-bold text-white truncate"><?php echo htmlspecialchars($fullname); ?></p>
                    <p class="text-red-200 truncate text-[11px]"><?php echo htmlspecialchars($email); ?></p>
                    <div class="pt-1 flex items-center gap-1.5 text-[10px] text-red-300">
                        <i class="bi bi-building"></i>
                        <span class="truncate"><?php echo htmlspecialchars($department); ?></span>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Sidebar Footer Sign Out -->
        <div class="p-4 border-t border-red-700/60">
            <a href="logout.php" class="w-full bg-red-950 hover:bg-black text-white py-2.5 px-4 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 border border-red-700/50 shadow-sm no-underline">
                <i class="bi bi-box-arrow-right text-sm"></i> Sign Out Portal
            </a>
        </div>
    </aside>

    <!-- Main Workspace Layout -->
    <div class="flex-1 flex flex-col min-w-0 min-h-screen">

        <!-- Top Header Navigation Bar (Desktop) -->
        <header class="hidden md:flex bg-white border-b border-slate-200 px-6 py-3.5 items-center justify-between sticky top-0 z-20 shadow-sm">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="text-slate-500 hover:text-slate-700 text-lg">
                    <i class="bi bi-layout-sidebar"></i>
                </button>
                <div>
                    <h2 class="text-lg font-bold text-slate-800 leading-tight">Resource Person Workspace</h2>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <a href="index.php" class="hover:text-red-600">Home</a>
                        <i class="bi bi-chevron-right text-[10px]"></i>
                        <span class="text-slate-800 font-medium">Expert Task Board & Report Management</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <!-- Clock Pill -->
                <div class="bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-600 flex items-center gap-2">
                    <i class="bi bi-clock text-red-600"></i>
                    <span id="live-clock"><?php echo date('h:i:s A'); ?></span>
                    <span class="text-slate-300">|</span>
                    <span><?php echo date('D, M j, Y'); ?></span>
                </div>

                <!-- Notifications Drawer Trigger -->
                <button type="button" onclick="toggleNotificationDrawer()" class="relative p-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition cursor-pointer" title="Notifications & Feedback Alerts">
                    <i class="bi bi-bell-fill text-slate-600 text-base"></i>
                    <?php if ($unread_notif_count > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 bg-amber-500 text-white font-bold text-[10px] rounded-full border-2 border-white flex items-center justify-center animate-pulse">
                            <?php echo $unread_notif_count; ?>
                        </span>
                    <?php endif; ?>
                </button>

                <!-- Profile Avatar -->
                <div class="flex items-center gap-3 pl-2 border-l border-slate-200">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-red-700 to-red-900 text-white flex items-center justify-center font-bold text-sm shadow-md border border-red-400">
                        <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                    </div>
                    <div class="text-xs">
                        <p class="font-bold text-slate-800 leading-tight"><?php echo htmlspecialchars($fullname); ?></p>
                        <p class="text-[10px] text-red-700 font-bold uppercase tracking-wider">Subject Matter Expert</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6">

            <!-- Hero Banner: Specialized Expert Credentials -->
            <div class="bg-gradient-to-r from-red-800 via-red-900 to-slate-900 text-white p-6 sm:p-8 rounded-3xl shadow-xl relative overflow-hidden border border-red-700/40">
                <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div class="space-y-2">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-amber-300 text-xs font-semibold backdrop-blur-sm border border-white/10">
                            <i class="bi bi-shield-check text-emerald-400"></i> Verified LGU Resource Person & Advisory Council
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Welcome back, <?php echo htmlspecialchars($fullname); ?></h1>
                        <p class="text-red-100 text-xs sm:text-sm max-w-2xl leading-relaxed">
                            Department: <strong class="text-white"><?php echo htmlspecialchars($department); ?></strong> &bull; 
                            Specialization: <span class="text-amber-200 font-semibold"><?php echo htmlspecialchars($expertise_areas); ?></span>
                        </p>
                    </div>
                    <!-- Header Action Buttons -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <button onclick="openKnowledgeBaseModal()" class="px-4 py-2.5 bg-white/15 hover:bg-white/25 text-white font-bold text-xs rounded-xl transition border border-white/20 flex items-center gap-2 shadow-sm">
                            <i class="bi bi-journal-bookmark-fill text-amber-300"></i> Guidelines & Templates
                        </button>
                    </div>
                </div>
                <div class="absolute -right-6 -bottom-10 opacity-10 text-9xl text-white pointer-events-none">
                    <i class="bi bi-award"></i>
                </div>
            </div>

            <!-- Section 2: Interactive KPI Action Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Card 1: Current Assignments & Workload -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition space-y-4 relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center text-2xl font-bold">
                            <i class="bi bi-folder-check"></i>
                        </div>
                        <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-red-50 text-red-800 border border-red-200">
                            Active Tasks
                        </span>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Current Assignments</p>
                        <h3 class="text-3xl font-extrabold text-slate-900 mt-1"><?php echo $total_assigned; ?> <span class="text-xs font-semibold text-slate-500">Total Assigned</span></h3>
                        <div class="flex items-center gap-3 mt-2 text-xs font-semibold text-slate-600">
                            <span class="text-amber-600"><i class="bi bi-hourglass-split"></i> <?php echo $pending_reviews_count; ?> Pending Review</span>
                            <span class="text-emerald-600"><i class="bi bi-play-circle"></i> <?php echo $active_count; ?> Active</span>
                        </div>
                    </div>
                    <button onclick="filterByAssignment('assigned')" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold rounded-xl text-xs transition flex items-center justify-center gap-1.5">
                        <i class="bi bi-filter"></i> Filter Assigned Consultations
                    </button>
                </div>

                <!-- Card 2: Report Deadlines & Timelines -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition space-y-4 relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center text-2xl font-bold">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <?php if ($overdue_count > 0): ?>
                            <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-200 animate-pulse">
                                <?php echo $overdue_count; ?> Overdue Task
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                Schedule Clear
                            </span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Upcoming Deadlines</p>
                        <h3 class="text-3xl font-extrabold text-slate-900 mt-1"><?php echo $due_soon_count; ?> <span class="text-xs font-semibold text-slate-500">Due in 3 Days</span></h3>
                        <p class="text-xs text-slate-500 mt-2">
                            <?php echo $overdue_count > 0 ? "<strong class='text-rose-600'>$overdue_count overdue tasks</strong> require immediate submission." : "All assigned expert reports are on track."; ?>
                        </p>
                    </div>
                    <button onclick="filterByDeadline()" class="w-full py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold rounded-xl text-xs transition flex items-center justify-center gap-1.5 border border-amber-200">
                        <i class="bi bi-calendar-event"></i> View Approaching Deadlines
                    </button>
                </div>

                <!-- Card 3: Review & Endorsement Progress -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition space-y-4 relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-2xl font-bold">
                            <i class="bi bi-patch-check"></i>
                        </div>
                        <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                            <?php echo $endorsement_rate; ?>% Completed
                        </span>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Report Endorsements</p>
                        <h3 class="text-3xl font-extrabold text-emerald-600 mt-1"><?php echo $approved_reports; ?> <span class="text-xs font-semibold text-slate-500">Approved Reports</span></h3>
                        <!-- Progress Bar -->
                        <div class="w-full bg-slate-100 rounded-full h-2 mt-3 overflow-hidden">
                            <div class="bg-emerald-600 h-2 rounded-full transition-all duration-500" style="width: <?php echo min(100, max(10, $endorsement_rate)); ?>%"></div>
                        </div>
                    </div>
                    <button onclick="switchMainTab('reports')" class="w-full py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-900 font-bold rounded-xl text-xs transition flex items-center justify-center gap-1.5 border border-emerald-200">
                        <i class="bi bi-file-earmark-check"></i> View Report Version History
                    </button>
                </div>
            </div>

            <!-- Section 3: Performance Metrics & Impact Analytics Panel -->
            <div id="analytics-section" class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="bi bi-bar-chart-line-fill text-red-700"></i> Performance Metrics & Advisory Impact
                    </h3>
                    <span class="text-xs text-slate-400 font-medium">Updated Real-Time</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-1">
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                        <span class="text-[11px] font-bold text-slate-400 uppercase block">Consultations Reviewed</span>
                        <span class="text-2xl font-extrabold text-slate-900"><?php echo count($consultations); ?></span>
                        <span class="text-[10px] text-emerald-600 font-semibold block mt-0.5"><i class="bi bi-arrow-up-right"></i> Active Participation</span>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                        <span class="text-[11px] font-bold text-slate-400 uppercase block">Resolution Papers Uploaded</span>
                        <span class="text-2xl font-extrabold text-emerald-600"><?php echo $total_reports; ?></span>
                        <span class="text-[10px] text-slate-500 block mt-0.5"><?php echo $approved_reports; ?> Approved by LGU</span>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                        <span class="text-[11px] font-bold text-slate-400 uppercase block">Avg Turnaround Time</span>
                        <span class="text-2xl font-extrabold text-blue-600">2.8 Days</span>
                        <span class="text-[10px] text-blue-600 font-semibold block mt-0.5"><i class="bi bi-lightning-fill"></i> Fast Response</span>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                        <span class="text-[11px] font-bold text-slate-400 uppercase block">Committee Approval Rate</span>
                        <span class="text-2xl font-extrabold text-amber-600">98%</span>
                        <span class="text-[10px] text-amber-600 font-semibold block mt-0.5"><i class="bi bi-star-fill"></i> Highly Rated</span>
                    </div>
                </div>
            </div>

            <!-- Section 4: Professional Task Board & Workflow -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-6">
                <!-- Toolbar: Title, View Switcher, Search -->
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                    <div>
                        <h3 class="text-lg font-extrabold text-slate-900 flex items-center gap-2">
                            <i class="bi bi-grid-3x3-gap-fill text-red-700"></i> Public Consultations Task Board
                        </h3>
                        <p class="text-xs text-slate-500">Review policy proposals, submit expert resolution papers, and track committee sign-offs.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                        <!-- Search Box -->
                        <div class="relative flex-1 sm:w-56">
                            <i class="bi bi-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                            <input type="text" id="search-task" onkeyup="filterTaskBoard()" placeholder="Search title or category..." 
                                   class="w-full pl-9 pr-4 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none">
                        </div>

                        <!-- Category Filter -->
                        <select id="filter-category" onchange="filterTaskBoard()" class="px-3 py-2 text-xs border border-slate-300 rounded-xl outline-none bg-white font-medium">
                            <option value="all">All Categories</option>
                            <option value="assigned">Assigned to Me</option>
                            <option value="expertise">Expertise Matches</option>
                            <option value="overdue">Overdue Deadlines</option>
                        </select>

                        <!-- View Switcher Toggle Buttons -->
                        <div class="bg-slate-100 p-1 rounded-xl flex items-center border border-slate-200 text-xs font-bold">
                            <button id="view-btn-kanban" onclick="switchBoardView('kanban')" class="px-3 py-1.5 rounded-lg bg-white shadow-xs text-red-800 transition flex items-center gap-1">
                                <i class="bi bi-kanban"></i> Kanban
                            </button>
                            <button id="view-btn-timeline" onclick="switchBoardView('timeline')" class="px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition flex items-center gap-1">
                                <i class="bi bi-clock"></i> Timeline
                            </button>
                            <button id="view-btn-grid" onclick="switchBoardView('grid')" class="px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition flex items-center gap-1">
                                <i class="bi bi-grid-fill"></i> Grid
                            </button>
                        </div>
                    </div>
                </div>

                <!-- VIEW 1: KANBAN TASK BOARD -->
                <div id="board-view-kanban" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 pt-2">
                    <!-- Column 1: Pending Initial Review -->
                    <div class="bg-slate-50/80 rounded-2xl p-4 border border-slate-200 flex flex-col space-y-3 kanban-col">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <span class="font-bold text-xs text-amber-900 flex items-center gap-1.5">
                                <i class="bi bi-hourglass-split text-amber-600"></i> 1. Pending Review
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800" id="count-kanban-pending">0</span>
                        </div>
                        <div class="space-y-3 flex-1 overflow-y-auto scrollbar-thin pr-1" id="kanban-col-pending">
                            <!-- Populated via PHP loop or JS -->
                        </div>
                    </div>

                    <!-- Column 2: Active Technical Analysis -->
                    <div class="bg-slate-50/80 rounded-2xl p-4 border border-slate-200 flex flex-col space-y-3 kanban-col">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <span class="font-bold text-xs text-emerald-900 flex items-center gap-1.5">
                                <i class="bi bi-play-circle-fill text-emerald-600"></i> 2. Active Analysis
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800" id="count-kanban-active">0</span>
                        </div>
                        <div class="space-y-3 flex-1 overflow-y-auto scrollbar-thin pr-1" id="kanban-col-active">
                            <!-- Populated via PHP loop or JS -->
                        </div>
                    </div>

                    <!-- Column 3: Under Committee Review -->
                    <div class="bg-slate-50/80 rounded-2xl p-4 border border-slate-200 flex flex-col space-y-3 kanban-col">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <span class="font-bold text-xs text-blue-900 flex items-center gap-1.5">
                                <i class="bi bi-building-check text-blue-600"></i> 3. Committee Review
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800" id="count-kanban-committee">0</span>
                        </div>
                        <div class="space-y-3 flex-1 overflow-y-auto scrollbar-thin pr-1" id="kanban-col-committee">
                            <!-- Populated via PHP loop or JS -->
                        </div>
                    </div>

                    <!-- Column 4: Finalized & Endorsed -->
                    <div class="bg-slate-50/80 rounded-2xl p-4 border border-slate-200 flex flex-col space-y-3 kanban-col">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <span class="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                                <i class="bi bi-check-circle-fill text-slate-700"></i> 4. Finalized & Closed
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-800" id="count-kanban-finalized">0</span>
                        </div>
                        <div class="space-y-3 flex-1 overflow-y-auto scrollbar-thin pr-1" id="kanban-col-finalized">
                            <!-- Populated via PHP loop or JS -->
                        </div>
                    </div>
                </div>

                <!-- VIEW 2: TIMELINE SCHEDULE VIEW -->
                <div id="board-view-timeline" class="hidden space-y-6 pt-2">
                    <!-- Overdue Track -->
                    <div class="bg-rose-50/50 border border-rose-200 rounded-2xl p-5 space-y-3">
                        <h4 class="font-bold text-xs text-rose-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="bi bi-exclamation-octagon-fill text-rose-600"></i> Immediate Action Required / Overdue Deadlines
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="timeline-track-overdue">
                            <!-- Cards inserted here -->
                        </div>
                    </div>

                    <!-- Due This Week Track -->
                    <div class="bg-amber-50/50 border border-amber-200 rounded-2xl p-5 space-y-3">
                        <h4 class="font-bold text-xs text-amber-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="bi bi-clock-history text-amber-600"></i> Due Next 7 Days
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="timeline-track-duesoon">
                            <!-- Cards inserted here -->
                        </div>
                    </div>

                    <!-- Completed Track -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-3">
                        <h4 class="font-bold text-xs text-slate-700 uppercase tracking-wider flex items-center gap-2">
                            <i class="bi bi-check2-all text-emerald-600"></i> Finalized & Closed Track
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="timeline-track-completed">
                            <!-- Cards inserted here -->
                        </div>
                    </div>
                </div>

                <!-- VIEW 3: GRID LIST VIEW -->
                <div id="board-view-grid" class="hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 pt-2" id="grid-tasks-container">
                    <!-- Cards dynamically populated -->
                </div>
            </div>

        </main>
    </div>

    <!-- NOTIFICATION DRAWER PANEL -->
    <div id="notif-drawer" class="fixed inset-y-0 right-0 w-full sm:w-96 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 flex flex-col border-l border-slate-200">
        <div class="bg-gradient-to-r from-red-800 to-red-900 text-white p-5 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-bell-fill text-amber-300"></i>
                <h3 class="font-bold text-sm">Notifications & Feedback Alerts</h3>
            </div>
            <button onclick="toggleNotificationDrawer()" class="text-white hover:text-red-200 text-xl leading-none">&times;</button>
        </div>
        <div class="p-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center text-xs">
            <span class="font-semibold text-slate-600"><?php echo $unread_notif_count; ?> unread alert(s)</span>
            <button onclick="markAllNotificationsRead()" class="text-red-700 font-bold hover:underline">Mark all as read</button>
        </div>
        <div class="flex-1 overflow-y-auto divide-y divide-slate-100 p-2 space-y-1 scrollbar-thin">
            <?php if (empty($notifications_list)): ?>
                <div class="p-8 text-center text-slate-400 space-y-2">
                    <i class="bi bi-bell-slash text-3xl block"></i>
                    <p class="text-xs font-semibold">No Notifications Yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications_list as $n): ?>
                    <div class="p-3.5 rounded-xl hover:bg-slate-50 transition space-y-1.5 border border-transparent <?php echo !$n['is_read'] ? 'bg-amber-50/60 border-amber-200' : ''; ?>">
                        <div class="flex items-center justify-between">
                            <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase <?php echo $n['type'] === 'assignment' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'; ?>">
                                <?php echo htmlspecialchars($n['type']); ?>
                            </span>
                            <span class="text-[10px] text-slate-400"><?php echo date('M j, g:i a', strtotime($n['created_at'])); ?></span>
                        </div>
                        <h4 class="font-bold text-xs text-slate-900 leading-snug"><?php echo htmlspecialchars($n['title']); ?></h4>
                        <p class="text-[11px] text-slate-600"><?php echo htmlspecialchars($n['message']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL 1: DETAILED EXPERT REVIEW & PARTICIPATION DRAWER -->
    <div id="expert-review-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden border border-slate-200 flex flex-col animate-in fade-in duration-150">
            <div class="bg-gradient-to-r from-slate-900 via-red-900 to-slate-900 text-white p-6 flex items-start justify-between border-b border-red-800">
                <div class="space-y-1">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-400 text-slate-950 uppercase tracking-wider" id="review-modal-category">Policy Category</span>
                    <h3 class="text-xl font-extrabold text-white mt-1" id="review-modal-title">Consultation Title</h3>
                    <p class="text-xs text-slate-300" id="review-modal-meta">Deadline: Aug 15, 2026</p>
                </div>
                <button onclick="closeExpertReviewModal()" class="text-white/80 hover:text-white text-2xl font-bold leading-none">&times;</button>
            </div>

            <!-- Tabs Nav -->
            <div class="bg-slate-100 border-b border-slate-200 px-6 flex items-center gap-4 text-xs font-bold text-slate-600">
                <button id="modal-tab-btn-overview" onclick="switchModalTab('overview')" class="py-3 border-b-2 border-red-700 text-red-800 font-extrabold">Overview & Details</button>
                <button id="modal-tab-btn-history" onclick="switchModalTab('history')" class="py-3 border-b-2 border-transparent hover:text-slate-900">Report Version History</button>
                <button id="modal-tab-btn-inquiries" onclick="switchModalTab('inquiries')" class="py-3 border-b-2 border-transparent hover:text-slate-900">Info Requests</button>
            </div>

            <!-- Modal Body Content -->
            <div class="p-6 overflow-y-auto flex-1 space-y-6 text-xs text-slate-700 scrollbar-thin">
                <div id="modal-tab-content-overview" class="space-y-4">
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                        <h4 class="font-bold text-slate-900 text-sm">Policy Summary & Background</h4>
                        <p class="text-slate-600 leading-relaxed" id="review-modal-description">Loading description...</p>
                    </div>
                </div>

                <div id="modal-tab-content-history" class="hidden space-y-4">
                    <div class="flex justify-between items-center">
                        <h4 class="font-bold text-slate-900 text-sm">Submitted Resolution Papers & Version Control</h4>
                        <button onclick="triggerUploadModalFromReview()" class="px-3 py-1.5 bg-red-700 hover:bg-red-800 text-white font-bold rounded-xl text-xs transition">
                            <i class="bi bi-upload"></i> Upload New Version
                        </button>
                    </div>
                    <div class="border border-slate-200 rounded-2xl overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-100 font-bold text-slate-700 border-b border-slate-200 uppercase text-[10px]">
                                <tr>
                                    <th class="p-3">Version</th>
                                    <th class="p-3">Uploaded By</th>
                                    <th class="p-3">Date</th>
                                    <th class="p-3">Committee Status</th>
                                    <th class="p-3 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="review-modal-reports-list" class="divide-y divide-slate-100 bg-white">
                                <tr><td colspan="5" class="p-4 text-center text-slate-400">Loading version history...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="modal-tab-content-inquiries" class="hidden space-y-4">
                    <div class="flex justify-between items-center">
                        <h4 class="font-bold text-slate-900 text-sm">Information Requests & Clarification Log</h4>
                        <button onclick="triggerInfoModalFromReview()" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs transition">
                            <i class="bi bi-question-circle"></i> Request Info
                        </button>
                    </div>
                    <div class="space-y-3" id="review-modal-inquiries-list">
                        <p class="text-slate-400 text-center py-4">No clarification requests logged yet.</p>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
                <button onclick="closeExpertReviewModal()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold rounded-xl text-xs transition">Close Drawer</button>
            </div>
        </div>
    </div>

    <!-- MODAL 2: UPLOAD RESOLUTION REPORT MODAL -->
    <div id="upload-report-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 relative border border-slate-200 space-y-5">
            <button onclick="closeUploadModal()" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div class="w-12 h-12 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center text-2xl font-bold">
                    <i class="bi bi-file-earmark-arrow-up"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Upload Resolution Report</h3>
                    <p class="text-xs text-slate-500" id="upload-modal-title">Consultation Title</p>
                </div>
            </div>

            <form action="API/upload_resolution_report.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="consultation_id" id="upload-consultation-id">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Report Version Tag</label>
                    <input type="text" name="version_label" placeholder="e.g. v1.0, v1.1 Draft, v2.0 Final" 
                           class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Select Document File (.pdf, .doc, .docx) *</label>
                    <input type="file" name="report_file" accept=".pdf,.doc,.docx" required
                           class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Executive Summary / Technical Rationale *</label>
                    <textarea name="notes" rows="4" required
                              class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none"
                              placeholder="Provide technical rationale, committee recommendations, or policy findings..."></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeUploadModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 rounded-2xl font-semibold text-xs transition">Cancel</button>
                    <button type="submit" class="flex-1 bg-red-700 hover:bg-red-800 text-white py-3 rounded-2xl font-bold text-xs transition shadow-md flex items-center justify-center gap-1.5">
                        <i class="bi bi-upload"></i> Submit Resolution Paper
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: REQUEST ADDITIONAL INFO MODAL -->
    <div id="info-request-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 relative border border-slate-200 space-y-5">
            <button onclick="closeInfoRequestModal()" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center text-2xl font-bold">
                    <i class="bi bi-question-circle"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Request Information / Clarification</h3>
                    <p class="text-xs text-slate-500" id="info-modal-title">Consultation Title</p>
                </div>
            </div>

            <form action="API/request_additional_info.php" method="POST" class="space-y-4">
                <input type="hidden" name="consultation_id" id="info-consultation-id">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Target Recipient</label>
                        <select name="target_entity" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl outline-none font-medium">
                            <option value="Admin & Committee">City Admin & LGU Committee</option>
                            <option value="Committee Secretariat">Committee Secretariat</option>
                            <option value="Citizen Submitter">Citizen Submitter</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Priority</label>
                        <select name="priority" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl outline-none font-medium">
                            <option value="normal">Normal Priority</option>
                            <option value="urgent">Urgent Priority</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Inquiry / Missing Document Details *</label>
                    <textarea name="message" rows="4" required
                              class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none"
                              placeholder="Specify missing document details, technical queries, or clarification needed..."></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeInfoRequestModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 rounded-2xl font-semibold text-xs transition">Cancel</button>
                    <button type="submit" class="flex-1 bg-amber-600 hover:bg-amber-700 text-white py-3 rounded-2xl font-bold text-xs transition shadow-md flex items-center justify-center gap-1.5">
                        <i class="bi bi-send"></i> Send Inquiry
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 4: KNOWLEDGE BASE & GUIDELINES CENTER -->
    <div id="knowledge-base-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[85vh] overflow-hidden border border-slate-200 flex flex-col">
            <div class="bg-gradient-to-r from-red-800 to-slate-900 text-white p-6 flex justify-between items-center border-b border-red-700">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-amber-300 text-xl font-bold">
                        <i class="bi bi-book-half"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">Expert Knowledge Base & Guidelines Hub</h3>
                        <p class="text-xs text-red-200">Official City of Valenzuela policy advisory standards and templates</p>
                    </div>
                </div>
                <button onclick="closeKnowledgeBaseModal()" class="text-white text-2xl font-bold leading-none">&times;</button>
            </div>
            
            <div class="p-6 overflow-y-auto space-y-6 text-xs text-slate-700 scrollbar-thin flex-1">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                        <i class="bi bi-shield-check text-2xl text-red-700 block"></i>
                        <h4 class="font-bold text-slate-900">Legislative Advisory Guidelines</h4>
                        <p class="text-slate-500">Standard operating procedure for evaluating citizen consultation testimonies, Data Privacy Act (RA 10173) compliance, and ordinance drafting rationale.</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                        <i class="bi bi-file-earmark-word text-2xl text-blue-700 block"></i>
                        <h4 class="font-bold text-slate-900">Standard Report Templates</h4>
                        <p class="text-slate-500">Download official pre-formatted resolution paper templates (.DOCX), technical feasibility matrices, and committee summary briefing formats.</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                        <i class="bi bi-archive text-2xl text-amber-700 block"></i>
                        <h4 class="font-bold text-slate-900">Previous Approved Resolutions</h4>
                        <p class="text-slate-500">Archive repository of past approved municipal resolution papers for reference and legal precedent benchmarking.</p>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-end">
                <button onclick="closeKnowledgeBaseModal()" class="px-5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold rounded-xl text-xs transition">Close Hub</button>
            </div>
        </div>
    </div>

    <!-- JS GLOBAL DATA & WORKFLOW ENGINE -->
    <script>
    const CONSULTATIONS_DATA = <?php echo json_encode($consultations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const USER_ID = <?php echo (int)$user_id; ?>;
    let currentActiveConsultationId = null;

    document.addEventListener('DOMContentLoaded', function() {
        renderBoardData();
    });

    function toggleSidebar() {
        var sb = document.getElementById('sidebar');
        if (sb) sb.classList.toggle('-translate-x-full');
    }

    function toggleNotificationDrawer() {
        var dr = document.getElementById('notif-drawer');
        if (dr) dr.classList.toggle('translate-x-full');
    }

    function markAllNotificationsRead() {
        fetch('API/resource_person_api.php?action=mark_notif_read', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    function switchBoardView(viewMode) {
        document.getElementById('board-view-kanban').classList.add('hidden');
        document.getElementById('board-view-timeline').classList.add('hidden');
        document.getElementById('board-view-grid').classList.add('hidden');

        document.getElementById('view-btn-kanban').className = "px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition flex items-center gap-1";
        document.getElementById('view-btn-timeline').className = "px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition flex items-center gap-1";
        document.getElementById('view-btn-grid').className = "px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition flex items-center gap-1";

        if (viewMode === 'kanban') {
            document.getElementById('board-view-kanban').classList.remove('hidden');
            document.getElementById('view-btn-kanban').className = "px-3 py-1.5 rounded-lg bg-white shadow-xs text-red-800 font-extrabold transition flex items-center gap-1";
        } else if (viewMode === 'timeline') {
            document.getElementById('board-view-timeline').classList.remove('hidden');
            document.getElementById('view-btn-timeline').className = "px-3 py-1.5 rounded-lg bg-white shadow-xs text-red-800 font-extrabold transition flex items-center gap-1";
        } else if (viewMode === 'grid') {
            document.getElementById('board-view-grid').classList.remove('hidden');
            document.getElementById('view-btn-grid').className = "px-3 py-1.5 rounded-lg bg-white shadow-xs text-red-800 font-extrabold transition flex items-center gap-1";
        }
    }

    function renderBoardData() {
        const kanbanPending = document.getElementById('kanban-col-pending');
        const kanbanActive = document.getElementById('kanban-col-active');
        const kanbanCommittee = document.getElementById('kanban-col-committee');
        const kanbanFinalized = document.getElementById('kanban-col-finalized');

        const trackOverdue = document.getElementById('timeline-track-overdue');
        const trackDuesoon = document.getElementById('timeline-track-duesoon');
        const trackCompleted = document.getElementById('timeline-track-completed');

        const gridContainer = document.getElementById('board-view-grid');

        if (!kanbanPending) return;

        kanbanPending.innerHTML = '';
        kanbanActive.innerHTML = '';
        kanbanCommittee.innerHTML = '';
        kanbanFinalized.innerHTML = '';

        trackOverdue.innerHTML = '';
        trackDuesoon.innerHTML = '';
        trackCompleted.innerHTML = '';
        gridContainer.innerHTML = '';

        let countPending = 0, countActive = 0, countCommittee = 0, countFinalized = 0;

        CONSULTATIONS_DATA.forEach(c => {
            const card = createTaskCardHtml(c);

            // 1. Kanban stage assignment
            if (c.kanban_stage === 'pending_review') {
                kanbanPending.innerHTML += card;
                countPending++;
            } else if (c.kanban_stage === 'active_analysis') {
                kanbanActive.innerHTML += card;
                countActive++;
            } else if (c.kanban_stage === 'committee_review') {
                kanbanCommittee.innerHTML += card;
                countCommittee++;
            } else {
                kanbanFinalized.innerHTML += card;
                countFinalized++;
            }

            // 2. Timeline stage assignment
            if (c.deadline_status === 'overdue') {
                trackOverdue.innerHTML += card;
            } else if (c.deadline_status === 'due_soon') {
                trackDuesoon.innerHTML += card;
            } else {
                trackCompleted.innerHTML += card;
            }

            // 3. Grid container
            gridContainer.innerHTML += card;
        });

        document.getElementById('count-kanban-pending').textContent = countPending;
        document.getElementById('count-kanban-active').textContent = countActive;
        document.getElementById('count-kanban-committee').textContent = countCommittee;
        document.getElementById('count-kanban-finalized').textContent = countFinalized;

        if (trackOverdue.children.length === 0) trackOverdue.innerHTML = '<p class="text-xs text-slate-400 col-span-full">No overdue items.</p>';
        if (trackDuesoon.children.length === 0) trackDuesoon.innerHTML = '<p class="text-xs text-slate-400 col-span-full">No items due in next 3 days.</p>';
        if (trackCompleted.children.length === 0) trackCompleted.innerHTML = '<p class="text-xs text-slate-400 col-span-full">No finalized items.</p>';
    }

    function createTaskCardHtml(c) {
        const title = escapeHtml(c.title || 'Untitled Consultation');
        const category = escapeHtml(c.category || 'General');
        const desc = escapeHtml(c.description || 'No description available.');
        const isAssigned = (c.assigned_to == USER_ID);
        const isMatch = !!c.is_expertise_match;
        const reportsCount = parseInt(c.reports_count || 0);
        const approvedCount = parseInt(c.approved_reports_count || 0);
        const infoCount = parseInt(c.info_requests_count || 0);

        let deadlineBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700"><i class="bi bi-calendar3"></i> ${c.deadline_formatted}</span>`;
        if (c.deadline_status === 'overdue') {
            deadlineBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200"><i class="bi bi-exclamation-triangle-fill"></i> Overdue (${c.deadline_formatted})</span>`;
        } else if (c.deadline_status === 'due_soon') {
            deadlineBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200"><i class="bi bi-clock-fill"></i> Due Soon (${c.deadline_formatted})</span>`;
        }

        let tagBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-600">General</span>`;
        if (isAssigned) {
            tagBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800 border border-red-200 flex items-center gap-1"><i class="bi bi-star-fill text-red-600 text-[9px]"></i> Assigned to You</span>`;
        } else if (isMatch) {
            tagBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200 flex items-center gap-1"><i class="bi bi-award-fill text-amber-600 text-[9px]"></i> Expertise Match</span>`;
        }

        return `
            <div class="task-card bg-white rounded-2xl border border-slate-200 p-4 space-y-3 shadow-2xs hover:shadow-md transition flex flex-col justify-between"
                 data-title="${title.toLowerCase()}"
                 data-category="${category.toLowerCase()}"
                 data-assigned="${isAssigned ? 'assigned' : (isMatch ? 'expertise' : 'general')}"
                 data-overdue="${c.deadline_status}">

                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-1 flex-wrap">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-800 border border-slate-200">${category}</span>
                        ${tagBadge}
                    </div>

                    <h4 class="font-bold text-xs text-slate-900 leading-snug line-clamp-2">${title}</h4>
                    <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">${desc}</p>
                </div>

                <div class="space-y-2.5 pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between text-[11px] text-slate-500">
                        ${deadlineBadge}
                        <div class="flex items-center gap-2">
                            <span title="Reports uploaded" class="font-bold text-emerald-700 flex items-center gap-0.5"><i class="bi bi-file-earmark-check"></i> ${reportsCount}</span>
                            <span title="Info requests" class="font-bold text-amber-700 flex items-center gap-0.5"><i class="bi bi-question-circle"></i> ${infoCount}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-1.5 pt-1">
                        <button type="button" onclick="openExpertReviewModal(${c.id})" class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-1.5 px-2 rounded-lg text-[10px] transition text-center shadow-2xs">
                            <i class="bi bi-eye"></i> Review
                        </button>
                        <button type="button" onclick="openUploadModal(${c.id}, '${escapeQuotes(title)}')" class="bg-red-700 hover:bg-red-800 text-white font-bold py-1.5 px-2 rounded-lg text-[10px] transition text-center shadow-2xs">
                            <i class="bi bi-upload"></i> Report
                        </button>
                        <button type="button" onclick="openInfoRequestModal(${c.id}, '${escapeQuotes(title)}')" class="bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold py-1.5 px-2 rounded-lg text-[10px] transition border border-amber-200 text-center">
                            <i class="bi bi-question-circle"></i> Ask
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    function openExpertReviewModal(consultationId) {
        currentActiveConsultationId = consultationId;
        const c = CONSULTATIONS_DATA.find(x => x.id == consultationId);
        if (!c) return;

        document.getElementById('review-modal-category').textContent = c.category || 'Policy';
        document.getElementById('review-modal-title').textContent = c.title || 'Consultation Details';
        document.getElementById('review-modal-meta').textContent = `Assigned Deadline: ${c.deadline_formatted} | Reports Uploaded: ${c.reports_count || 0}`;
        document.getElementById('review-modal-description').textContent = c.description || 'No detailed background provided.';

        // Load Version History & Info Requests via API
        fetch(`API/resource_person_api.php?action=get_consultation_details&consultation_id=${consultationId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderReportsVersionHistory(data.reports);
                renderInquiriesList(data.info_requests);
            }
        });

        switchModalTab('overview');
        document.getElementById('expert-review-modal').classList.remove('hidden');
    }

    function renderReportsVersionHistory(reports) {
        const tbody = document.getElementById('review-modal-reports-list');
        if (!reports || reports.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="p-6 text-center text-slate-400"><i class="bi bi-file-earmark-x text-2xl block mb-1"></i> No resolution reports uploaded for this consultation yet.</td></tr>`;
            return;
        }

        tbody.innerHTML = reports.map(r => `
            <tr class="hover:bg-slate-50 transition">
                <td class="p-3 font-mono font-bold text-red-800">${escapeHtml(r.version_label || 'v1.0')}</td>
                <td class="p-3 font-semibold text-slate-900">${escapeHtml(r.uploader_name || 'Resource Person')}</td>
                <td class="p-3 text-slate-500">${r.created_at || 'Recently'}</td>
                <td class="p-3">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase ${r.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}">
                        ${r.status || 'Pending Review'}
                    </span>
                    ${r.committee_feedback ? `<p class="text-[10px] text-slate-600 mt-1 italic">"${escapeHtml(r.committee_feedback)}"</p>` : ''}
                </td>
                <td class="p-3 text-center">
                    <a href="uploads/resolution_reports/${r.file_path}" target="_blank" download class="px-2.5 py-1 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded text-[10px] inline-flex items-center gap-1">
                        <i class="bi bi-download"></i> Download
                    </a>
                </td>
            </tr>
        `).join('');
    }

    function renderInquiriesList(inquiries) {
        const container = document.getElementById('review-modal-inquiries-list');
        if (!inquiries || inquiries.length === 0) {
            container.innerHTML = `<p class="text-slate-400 text-center py-4">No clarification requests logged yet.</p>`;
            return;
        }

        container.innerHTML = inquiries.map(i => `
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-900">Target: ${escapeHtml(i.target_entity || 'Admin & Committee')}</span>
                    <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase ${i.priority === 'urgent' ? 'bg-rose-100 text-rose-800' : 'bg-slate-200 text-slate-800'}">${i.priority || 'normal'}</span>
                </div>
                <p class="text-slate-700 leading-relaxed">${escapeHtml(i.message)}</p>
                <div class="text-[10px] text-slate-400 flex items-center justify-between pt-1 border-t border-slate-100">
                    <span>Logged at ${i.created_at}</span>
                    <span class="font-bold ${i.status === 'responded' ? 'text-emerald-600' : 'text-amber-600'}">${i.status || 'pending'}</span>
                </div>
                ${i.response_notes ? `<div class="p-2 bg-emerald-50 text-emerald-900 rounded-lg text-[11px] font-semibold mt-1">Response: ${escapeHtml(i.response_notes)}</div>` : ''}
            </div>
        `).join('');
    }

    function switchModalTab(tabName) {
        document.getElementById('modal-tab-content-overview').classList.add('hidden');
        document.getElementById('modal-tab-content-history').classList.add('hidden');
        document.getElementById('modal-tab-content-inquiries').classList.add('hidden');

        document.getElementById('modal-tab-btn-overview').className = "py-3 border-b-2 border-transparent hover:text-slate-900";
        document.getElementById('modal-tab-btn-history').className = "py-3 border-b-2 border-transparent hover:text-slate-900";
        document.getElementById('modal-tab-btn-inquiries').className = "py-3 border-b-2 border-transparent hover:text-slate-900";

        if (tabName === 'overview') {
            document.getElementById('modal-tab-content-overview').classList.remove('hidden');
            document.getElementById('modal-tab-btn-overview').className = "py-3 border-b-2 border-red-700 text-red-800 font-extrabold";
        } else if (tabName === 'history') {
            document.getElementById('modal-tab-content-history').classList.remove('hidden');
            document.getElementById('modal-tab-btn-history').className = "py-3 border-b-2 border-red-700 text-red-800 font-extrabold";
        } else if (tabName === 'inquiries') {
            document.getElementById('modal-tab-content-inquiries').classList.remove('hidden');
            document.getElementById('modal-tab-btn-inquiries').className = "py-3 border-b-2 border-red-700 text-red-800 font-extrabold";
        }
    }

    function triggerUploadModalFromReview() {
        if (!currentActiveConsultationId) return;
        const c = CONSULTATIONS_DATA.find(x => x.id == currentActiveConsultationId);
        closeExpertReviewModal();
        openUploadModal(currentActiveConsultationId, c ? c.title : 'Consultation');
    }

    function triggerInfoModalFromReview() {
        if (!currentActiveConsultationId) return;
        const c = CONSULTATIONS_DATA.find(x => x.id == currentActiveConsultationId);
        closeExpertReviewModal();
        openInfoRequestModal(currentActiveConsultationId, c ? c.title : 'Consultation');
    }

    function closeExpertReviewModal() {
        document.getElementById('expert-review-modal').classList.add('hidden');
    }

    function filterTaskBoard() {
        const search = document.getElementById('search-task').value.toLowerCase().trim();
        const catFilter = document.getElementById('filter-category').value;
        const cards = document.querySelectorAll('.task-card');

        cards.forEach(card => {
            const title = card.getAttribute('data-title') || '';
            const category = card.getAttribute('data-category') || '';
            const assignedTag = card.getAttribute('data-assigned') || '';
            const overdueTag = card.getAttribute('data-overdue') || '';

            const matchesSearch = !search || title.includes(search) || category.includes(search);
            let matchesCategory = true;
            if (catFilter === 'assigned') matchesCategory = (assignedTag === 'assigned');
            else if (catFilter === 'expertise') matchesCategory = (assignedTag === 'expertise');
            else if (catFilter === 'overdue') matchesCategory = (overdueTag === 'overdue' || overdueTag === 'due_soon');

            if (matchesSearch && matchesCategory) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function filterByAssignment(type) {
        document.getElementById('filter-category').value = type;
        filterTaskBoard();
    }

    function filterByDeadline() {
        document.getElementById('filter-category').value = 'overdue';
        filterTaskBoard();
    }

    function openUploadModal(id, title) {
        document.getElementById('upload-consultation-id').value = id;
        document.getElementById('upload-modal-title').textContent = title;
        document.getElementById('upload-report-modal').classList.remove('hidden');
    }

    function closeUploadModal() {
        document.getElementById('upload-report-modal').classList.add('hidden');
    }

    function openInfoRequestModal(id, title) {
        document.getElementById('info-consultation-id').value = id;
        document.getElementById('info-modal-title').textContent = title;
        document.getElementById('info-request-modal').classList.remove('hidden');
    }

    function closeInfoRequestModal() {
        document.getElementById('info-request-modal').classList.add('hidden');
    }

    function openKnowledgeBaseModal() {
        document.getElementById('knowledge-base-modal').classList.remove('hidden');
    }

    function closeKnowledgeBaseModal() {
        document.getElementById('knowledge-base-modal').classList.add('hidden');
    }

    function switchMainTab(tabId) {
        if (tabId === 'analytics') {
            document.getElementById('analytics-section').scrollIntoView({ behavior: 'smooth' });
        } else if (tabId === 'assigned') {
            filterByAssignment('assigned');
        } else if (tabId === 'reports') {
            switchBoardView('kanban');
            document.getElementById('kanban-col-committee').scrollIntoView({ behavior: 'smooth' });
        } else if (tabId === 'inquiries') {
            filterByAssignment('all');
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function escapeQuotes(str) {
        if (!str) return '';
        return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    setInterval(function() {
        var clock = document.getElementById('live-clock');
        if (clock) {
            var now = new Date();
            clock.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
    }, 1000);
    </script>
</body>
</html>
"""

with open(r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Successfully written redesigned resource_person_dashboard.php!")
