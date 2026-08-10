<?php
/**
 * Resource Person Workspace - Clean Admin-Themed Dashboard
 * Strict Visibility Rules:
 * 1. Only consultations matching registered expertise area (or explicitly assigned)
 * 2. Only AFTER AI analysis is completed AND Admin has forwarded/assigned to Resource Person
 */
session_start();
require_once '../db.php';
require_once '../UTILS/session_check.php';

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

    // 2. Consultations table additions for Inline Master Document & Admin Forwarding Status
    $cCols = [];
    $cRes = $conn->query("SHOW COLUMNS FROM consultations");
    if ($cRes) {
        while ($r = $cRes->fetch_assoc()) { $cCols[] = $r['Field']; }
    }
    if (!in_array('assigned_to', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN assigned_to INT(11) DEFAULT NULL");
    if (!in_array('deadline', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN deadline DATETIME DEFAULT NULL");
    if (!in_array('expert_notes', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN expert_notes LONGTEXT DEFAULT NULL");
    if (!in_array('document_version', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN document_version VARCHAR(50) DEFAULT 'v1.0'");
    if (!in_array('document_status', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN document_status VARCHAR(50) DEFAULT 'draft'");
    if (!in_array('expert_last_updated_by', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN expert_last_updated_by INT(11) DEFAULT NULL");
    if (!in_array('expert_last_updated_at', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN expert_last_updated_at DATETIME DEFAULT NULL");
    if (!in_array('ai_analyzed', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN ai_analyzed TINYINT(1) DEFAULT 1");
    if (!in_array('forwarded_to_expert', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN forwarded_to_expert TINYINT(1) DEFAULT 1");

    // 3. Document Audit Trail Table
    @$conn->query("CREATE TABLE IF NOT EXISTS consultation_document_audit_trail (
        id INT(11) NOT NULL AUTO_INCREMENT,
        consultation_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        user_name VARCHAR(150) NOT NULL,
        user_role VARCHAR(50) NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        version_label VARCHAR(50) NOT NULL,
        changes_summary TEXT,
        snapshot_notes LONGTEXT,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_consultation (consultation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 4. Info Requests table
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
}

ensureResourcePersonSchema($conn);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$fullname = $_SESSION['fullname'] ?? 'Resource Person';
$email = $_SESSION['email'] ?? '';

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

$expertise_areas = $userProfile['expertise_areas'] ?? 'Health, Sanitation';
$department = $userProfile['department'] ?? 'Public Sector Advisory';

// Fetch raw consultations
$raw_consultations = [];
$cRes = $conn->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM consultation_document_audit_trail a WHERE a.consultation_id = c.id) as audit_count,
           (SELECT COUNT(*) FROM info_requests i WHERE i.consultation_id = c.id) as info_requests_count
    FROM consultations c 
    ORDER BY c.created_at DESC
");
if ($cRes) {
    while ($row = $cRes->fetch_assoc()) {
        $raw_consultations[] = $row;
    }
}

// STRICT VISIBILITY FILTERING ENGINE
function isConsultationVisibleToExpert($cRow, $user_id, $user_role, $expertise_areas_str) {
    // Admins see all
    if (in_array(strtolower($user_role), ['admin', 'administrator', 'super admin', 'superadmin'])) {
        return true;
    }

    // 1. MUST BE AI ANALYZED & FORWARDED BY ADMIN
    $status = strtolower(trim($cRow['status'] ?? ''));
    if ($status === 'draft') {
        return false; // AI analysis or intake not complete
    }

    $aiAnalyzed = isset($cRow['ai_analyzed']) ? (int)$cRow['ai_analyzed'] : 1;
    if ($aiAnalyzed === 0) {
        return false; // Waiting for AI analysis
    }

    $assignedTo = (int)($cRow['assigned_to'] ?? 0);
    $forwarded = isset($cRow['forwarded_to_expert']) ? (int)$cRow['forwarded_to_expert'] : 1;
    $docStatus = strtolower(trim($cRow['document_status'] ?? ''));

    // Check if admin has explicitly assigned or forwarded it
    $isForwardedByAdmin = ($assignedTo > 0 || $forwarded === 1 || in_array($docStatus, ['sent_to_expert', 'expert_annotated', 'admin_validated', 'forwarded_to_committee']));
    if (!$isForwardedByAdmin) {
        return false; // Admin has not forwarded this consultation to experts yet
    }

    // 2. MUST MATCH REGISTERED EXPERTISE AREA OR BE EXPLICITLY ASSIGNED
    if ($assignedTo === $user_id) {
        return true; // Explicitly assigned to this specific expert
    }

    $cCat = strtolower(trim($cRow['category'] ?? ''));
    if ($cCat === '') return false;

    // Parse expertise areas into list of terms
    $expList = array_values(array_filter(array_map('trim', explode(',', strtolower((string)$expertise_areas_str)))));
    if (empty($expList)) return false;

    foreach ($expList as $exp) {
        if ($exp === '') continue;
        if (strpos($cCat, $exp) !== false || strpos($exp, $cCat) !== false) {
            return true;
        }
        $expWords = preg_split('/[\s\/&,-]+/', $exp);
        foreach ($expWords as $w) {
            $w = trim($w);
            if (strlen($w) >= 3 && strpos($cCat, $w) !== false) {
                return true;
            }
        }
    }

    return false; // Category does not match expert's registered specializations
}

// Apply filtering
$consultations = [];
foreach ($raw_consultations as $cRow) {
    if (isConsultationVisibleToExpert($cRow, $user_id, $current_role, $expertise_areas)) {
        $consultations[] = $cRow;
    }
}

// Fetch unread notifications count
$unread_notif_count = 0;
$notifications_list = [];
$nStmt = $conn->prepare("SELECT * FROM expert_notifications WHERE user_id = ? ORDER BY id DESC LIMIT 15");
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

// Metrics calculation
$total_assigned = 0;
$annotated_master_docs_count = 0;
$pending_expert_input_count = 0;
$completed_count = 0;
$now_ts = time();

foreach ($consultations as &$c) {
    $isAssigned = ($c['assigned_to'] == $user_id);
    if ($isAssigned) {
        $total_assigned++;
    }

    // Determine Deadline
    $createdAtTs = !empty($c['created_at']) ? strtotime($c['created_at']) : $now_ts;
    $deadlineTs = !empty($c['deadline']) ? strtotime($c['deadline']) : ($createdAtTs + (7 * 86400));
    $c['deadline_formatted'] = date('M j, Y', $deadlineTs);
    $diffDays = ceil(($deadlineTs - $now_ts) / 86400);
    $c['deadline_days'] = $diffDays;

    if ($diffDays < 0 && !in_array($c['status'], ['completed', 'closed', 'endorsed'])) {
        $c['deadline_status'] = 'overdue';
    } elseif ($diffDays <= 3 && !in_array($c['status'], ['completed', 'closed', 'endorsed'])) {
        $c['deadline_status'] = 'due_soon';
    } else {
        $c['deadline_status'] = 'normal';
    }

    $hasExpertNotes = !empty($c['expert_notes']);
    if ($hasExpertNotes) {
        $annotated_master_docs_count++;
    }

    $status = strtolower(trim($c['status'] ?? 'active'));
    if (in_array($status, ['completed', 'closed'])) {
        $completed_count++;
    } elseif (!$hasExpertNotes) {
        $pending_expert_input_count++;
    }
}
unset($c);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Person Workspace - Valenzuela PCMS</title>
    <link rel="icon" type="image/png" href="images/logo.webp">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../ASSETS/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .bg-valenzuela-red { background-color: #800000; }
        .text-valenzuela-red { color: #800000; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen flex flex-col md:flex-row">

    <!-- Mobile Top Navigation Header Bar -->
    <header class="md:hidden bg-gradient-to-r from-red-800 to-red-900 text-white p-4 flex justify-between items-center sticky top-0 z-40 shadow-md">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="text-white text-xl p-1 focus:outline-none">
                <i class="bi bi-list"></i>
            </button>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center p-0.5 shadow">
                    <img src="../images/logo.webp" alt="Logo" class="w-full h-full object-contain" onerror="this.src='ASSETS/images/logo.png'">
                </div>
                <span class="font-bold text-sm">PCMS Expert Portal</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="toggleNotificationDrawer()" class="relative p-1.5 text-white">
                <i class="bi bi-bell text-lg"></i>
                <?php if ($unread_notif_count > 0): ?>
                    <span class="absolute top-0 right-0 w-4 h-4 bg-amber-400 text-slate-950 font-bold text-[9px] rounded-full flex items-center justify-center"><?php echo $unread_notif_count; ?></span>
                <?php endif; ?>
            </button>
            <a href="../logout.php" class="text-xs bg-red-950/60 px-3 py-1 rounded-lg border border-red-700/50 flex items-center gap-1">
                <i class="bi bi-box-arrow-right"></i> Exit
            </a>
        </div>
    </header>

    <!-- Admin-Style Collapsible Sidebar -->
    <aside id="sidebar" class="sidebar w-64 bg-gradient-to-b from-red-800 to-red-900 text-white flex-shrink-0 flex flex-col h-screen fixed md:sticky top-0 z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-300 shadow-xl">
        <!-- Logo Header Section -->
        <div class="p-6 border-b border-red-700/60 flex items-center gap-3">
            <div class="w-14 h-14 bg-white rounded-full flex items-center justify-center p-1 shadow-md shrink-0">
                <img src="../images/logo.webp" alt="Valenzuela Logo" class="w-full h-full object-contain" onerror="this.src='ASSETS/images/logo.png'">
            </div>
            <div>
                <h1 class="text-lg font-bold leading-tight">PCMS</h1>
                <p class="text-xs text-red-200 font-medium">City of Valenzuela</p>
            </div>
        </div>

        <!-- Sidebar Navigation -->
        <nav class="flex-1 py-4 px-3 overflow-y-auto space-y-6">
            <div>
                <p class="text-[11px] font-bold text-red-200/70 uppercase tracking-wider px-3 mb-2">Expert Workspace</p>
                <div class="space-y-1">
                    <a href="#overview" onclick="filterTaskCategory('all')" class="flex items-center px-4 py-3 text-white bg-red-700/90 rounded-xl font-semibold text-sm transition shadow-sm hover:bg-red-700 gap-3">
                        <i class="bi bi-file-earmark-diff text-lg"></i>
                        <span>Master Document Board</span>
                    </a>
                    <a href="#assigned" onclick="filterTaskCategory('assigned')" class="flex items-center px-4 py-3 text-red-100 hover:bg-red-700/60 hover:text-white rounded-xl text-sm transition gap-3">
                        <i class="bi bi-journal-check text-lg"></i>
                        <span>Assigned Tasks</span>
                        <?php if ($total_assigned > 0): ?>
                            <span class="ml-auto px-2 py-0.5 rounded-full text-xs bg-white text-red-900 font-bold"><?php echo $total_assigned; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="#annotated" onclick="filterTaskCategory('annotated')" class="flex items-center px-4 py-3 text-red-100 hover:bg-red-700/60 hover:text-white rounded-xl text-sm transition gap-3">
                        <i class="bi bi-pencil-square text-lg"></i>
                        <span>Annotated Documents</span>
                        <?php if ($annotated_master_docs_count > 0): ?>
                            <span class="ml-auto px-2 py-0.5 rounded-full text-xs bg-emerald-400 text-slate-950 font-bold"><?php echo $annotated_master_docs_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <div>
                <p class="text-[11px] font-bold text-red-200/70 uppercase tracking-wider px-3 mb-2">Resources</p>
                <div class="space-y-1">
                    <button onclick="openKnowledgeBaseModal()" class="w-full text-left flex items-center px-4 py-3 text-red-100 hover:bg-red-700/60 hover:text-white rounded-xl text-sm transition gap-3">
                        <i class="bi bi-book text-lg text-amber-300"></i>
                        <span>Guidelines & Standards</span>
                    </button>
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
            <a href="../logout.php" class="w-full bg-red-950 hover:bg-black text-white py-2.5 px-4 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 border border-red-700/50 shadow-sm no-underline">
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
                        <a href="../index.php" class="hover:text-red-600">Home</a>
                        <i class="bi bi-chevron-right text-[10px]"></i>
                        <span class="text-slate-800 font-medium">Expertise-Filtered Advisory Portal</span>
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

                <!-- Notifications Button -->
                <button onclick="toggleNotificationDrawer()" class="relative p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition" title="Notifications">
                    <i class="bi bi-bell text-base"></i>
                    <?php if ($unread_notif_count > 0): ?>
                        <span class="absolute -top-1 -right-1 w-4 h-4 bg-amber-500 text-white font-bold text-[9px] rounded-full flex items-center justify-center"><?php echo $unread_notif_count; ?></span>
                    <?php endif; ?>
                </button>

                <!-- Profile Avatar -->
                <div class="flex items-center gap-3 pl-2 border-l border-slate-200">
                    <div class="w-10 h-10 rounded-full bg-red-100 text-red-700 flex items-center justify-center font-bold text-sm shadow-sm border border-red-200">
                        <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                    </div>
                    <div class="text-xs">
                        <p class="font-bold text-slate-800 leading-tight"><?php echo htmlspecialchars($fullname); ?></p>
                        <p class="text-[10px] text-slate-500 font-medium">Resource Person</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6">

            <!-- Red Gradient Header Banner -->
            <div class="bg-gradient-to-r from-red-700 via-red-800 to-red-900 text-white p-6 sm:p-8 rounded-2xl shadow-lg relative overflow-hidden">
                <div class="relative z-10 space-y-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-red-100 text-xs font-medium backdrop-blur-sm">
                        <i class="bi bi-funnel-fill text-amber-300"></i> Expertise Access Control Active
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold">Welcome, <?php echo htmlspecialchars($fullname); ?></h1>
                    <p class="text-red-100 text-xs sm:text-sm max-w-3xl leading-relaxed">
                        Department: <strong class="text-white"><?php echo htmlspecialchars($department); ?></strong> &bull; 
                        Registered Expertise: <span class="text-amber-200 font-bold px-2 py-0.5 bg-black/30 rounded-lg"><?php echo htmlspecialchars($expertise_areas); ?></span>
                    </p>
                    <p class="text-[11px] text-red-200/90 pt-1">
                        <i class="bi bi-check-circle-fill text-emerald-400 mr-1"></i> Showing only consultations matching your registered expertise (e.g. <strong><?php echo htmlspecialchars($expertise_areas); ?></strong>) after AI analysis is completed and forwarded by the Admin.
                    </p>
                </div>
                <div class="absolute -right-6 -bottom-10 opacity-15 text-9xl text-white pointer-events-none">
                    <i class="bi bi-patch-check"></i>
                </div>
            </div>

            <!-- Clean KPI Metric Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Filtered Workload</p>
                        <p class="text-3xl font-bold text-slate-900 mt-1"><?php echo count($consultations); ?></p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Matching your expertise</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center text-2xl">
                        <i class="bi bi-funnel"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Awaiting Expert Input</p>
                        <p class="text-3xl font-bold text-amber-600 mt-1"><?php echo $pending_expert_input_count; ?></p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Needs inline notes</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Annotated Master Docs</p>
                        <p class="text-3xl font-bold text-emerald-600 mt-1"><?php echo $annotated_master_docs_count; ?></p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Notes appended</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
                        <i class="bi bi-file-earmark-diff-fill"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Completed Tasks</p>
                        <p class="text-3xl font-bold text-blue-600 mt-1"><?php echo $completed_count; ?></p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Forwarded to Committee</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>

            <!-- Toolbar Filters & Consultations Master Grid Section -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                            <i class="bi bi-file-earmark-word text-red-600"></i> Consultations Dispatched for Your Specialization
                        </h3>
                        <p class="text-xs text-slate-500">Filtered by <strong><?php echo htmlspecialchars($expertise_areas); ?></strong> &bull; AI Analyzed & Admin Dispatched</p>
                    </div>
                    
                    <!-- Search & Filter Controls -->
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <div class="relative flex-1 sm:w-64">
                            <i class="bi bi-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                            <input type="text" id="search-task" onkeyup="filterTasks()" placeholder="Search title or category..." 
                                   class="w-full pl-9 pr-4 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none">
                        </div>

                        <select id="filter-assignment" onchange="filterTasks()" class="px-3 py-2 text-xs border border-slate-300 rounded-xl outline-none bg-white font-medium">
                            <option value="all">All Dispatched (<?php echo count($consultations); ?>)</option>
                            <option value="annotated">Annotated Master Docs</option>
                            <option value="pending">Awaiting Expert Input</option>
                        </select>

                        <button onclick="openKnowledgeBaseModal()" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition border border-slate-200 flex items-center gap-1.5 shrink-0">
                            <i class="bi bi-book text-amber-600"></i> Guidelines
                        </button>
                    </div>
                </div>

                <!-- Consultation Task Cards Container -->
                <div id="tasks-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 pt-2">
                    <?php if (empty($consultations)): ?>
                        <div class="col-span-full bg-slate-50 border border-slate-200 rounded-2xl p-12 text-center text-slate-500 space-y-2">
                            <i class="bi bi-funnel text-4xl text-slate-300 mb-1 inline-block"></i>
                            <p class="font-semibold text-slate-700">No Matching Dispatched Consultations</p>
                            <p class="text-xs text-slate-500 max-w-md mx-auto">
                                Consultations will appear here once they are <strong>analyzed by AI</strong>, <strong>forwarded by Admin</strong>, and match your registered expertise category (<strong><?php echo htmlspecialchars($expertise_areas); ?></strong>).
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($consultations as $c): ?>
                            <?php 
                                $isAssignedToMe = ($c['assigned_to'] == $user_id);
                                $hasExpertNotes = !empty($c['expert_notes']);
                                $docVersion = htmlspecialchars($c['document_version'] ?? 'v1.0');
                                $category = htmlspecialchars($c['category'] ?? 'General');
                                $status = htmlspecialchars($c['status'] ?? 'active');

                                $cardTag = $isAssignedToMe ? 'assigned' : 'expertise';
                                if ($hasExpertNotes) $cardTag .= ' annotated';
                                else $cardTag .= ' pending';

                                $auditCount = (int)($c['audit_count'] ?? 0);
                                $infoRequestsCount = (int)($c['info_requests_count'] ?? 0);
                            ?>
                            <div class="task-card bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition flex flex-col justify-between p-6 space-y-4"
                                 data-assigned="<?php echo $cardTag; ?>"
                                 data-title="<?php echo htmlspecialchars(strtolower($c['title'])); ?>"
                                 data-category="<?php echo htmlspecialchars(strtolower($category)); ?>">

                                <div class="space-y-3">
                                    <!-- Top Header Badges -->
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                            <i class="bi bi-tag-fill text-red-600 mr-1"></i><?php echo $category; ?>
                                        </span>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-mono font-bold bg-slate-800 text-white flex items-center gap-1">
                                            <i class="bi bi-file-earmark-code text-amber-300"></i> Master Doc <?php echo $docVersion; ?>
                                        </span>
                                    </div>

                                    <!-- Title & Description -->
                                    <h4 class="text-base font-bold text-slate-900 leading-snug line-clamp-2">
                                        <?php echo htmlspecialchars($c['title']); ?>
                                    </h4>
                                    <p class="text-xs text-slate-500 line-clamp-3">
                                        <?php echo htmlspecialchars($c['description'] ?? 'No description provided.'); ?>
                                    </p>
                                </div>

                                <!-- Footer Metrics & Actions -->
                                <div class="space-y-3 pt-3 border-t border-slate-100">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-[11px] text-emerald-700 font-bold flex items-center gap-1">
                                            <i class="bi bi-robot text-xs"></i> AI Analyzed & Forwarded
                                        </span>
                                        <?php if ($hasExpertNotes): ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 flex items-center gap-1">
                                                <i class="bi bi-check-circle-fill"></i> Notes Appended
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 flex items-center gap-1">
                                                <i class="bi bi-pencil"></i> Needs Input
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="flex items-center justify-between text-xs text-slate-500">
                                        <span class="flex items-center gap-1 font-semibold text-slate-700"><i class="bi bi-clock-history text-blue-600"></i> <?php echo $auditCount; ?> Version Edits</span>
                                        <span class="flex items-center gap-1 font-semibold text-amber-700"><i class="bi bi-question-circle"></i> <?php echo $infoRequestsCount; ?> Inquiries</span>
                                    </div>

                                    <!-- Main Primary Action: Annotate Single Master Document -->
                                    <div class="grid grid-cols-1 gap-2 pt-1">
                                        <button onclick="openInlineInputModal(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['title'])); ?>', '<?php echo $docVersion; ?>')" 
                                                class="w-full bg-gradient-to-r from-red-800 to-red-900 hover:from-red-900 hover:to-black text-white font-extrabold py-2.5 px-3 rounded-xl text-xs transition flex items-center justify-center gap-2 shadow-sm cursor-pointer">
                                            <i class="bi bi-pencil-square text-amber-300"></i> Annotate & Append Expert Input
                                        </button>

                                        <div class="grid grid-cols-2 gap-2">
                                            <button onclick="openInfoRequestModal(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['title'])); ?>')" 
                                                    class="bg-amber-50 hover:bg-amber-100 text-amber-900 font-semibold py-2 px-2 rounded-xl text-xs transition border border-amber-200 flex items-center justify-center gap-1">
                                                <i class="bi bi-question-circle"></i> Request Info
                                            </button>
                                            <button onclick="openMasterDocumentAuditModal(<?php echo $c['id']; ?>)" 
                                                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2 px-2 rounded-xl text-xs transition border border-slate-200 flex items-center justify-center gap-1">
                                                <i class="bi bi-clock-history"></i> Audit Trail
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>

    <!-- Notification Drawer -->
    <div id="notif-drawer" class="fixed inset-y-0 right-0 w-full sm:w-96 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 flex flex-col border-l border-slate-200">
        <div class="bg-gradient-to-r from-red-800 to-red-900 text-white p-5 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-bell-fill text-amber-300"></i>
                <h3 class="font-bold text-sm">Notifications & Alerts</h3>
            </div>
            <button onclick="toggleNotificationDrawer()" class="text-white hover:text-red-200 text-xl leading-none">&times;</button>
        </div>
        <div class="p-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center text-xs">
            <span class="font-semibold text-slate-600"><?php echo $unread_notif_count; ?> unread alert(s)</span>
            <button onclick="markAllNotificationsRead()" class="text-red-700 font-bold hover:underline">Mark all as read</button>
        </div>
        <div class="flex-1 overflow-y-auto divide-y divide-slate-100 p-2 space-y-1">
            <?php if (empty($notifications_list)): ?>
                <div class="p-8 text-center text-slate-400 space-y-2">
                    <i class="bi bi-bell-slash text-3xl block"></i>
                    <p class="text-xs font-semibold">No Notifications Yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications_list as $n): ?>
                    <div class="p-3.5 rounded-xl hover:bg-slate-50 transition space-y-1 border border-transparent <?php echo !$n['is_read'] ? 'bg-amber-50/60 border-amber-200' : ''; ?>">
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

    <!-- WORKSTATION MODAL: ANNOTATE SINGLE MASTER CONSULTATION DOCUMENT -->
    <div id="inline-input-modal" class="fixed inset-0 bg-slate-950/75 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden border border-slate-200 flex flex-col animate-in fade-in duration-150">
            <!-- Workstation Modal Header -->
            <div class="bg-gradient-to-r from-red-900 via-slate-900 to-slate-900 text-white p-6 flex items-start justify-between border-b border-red-700/50">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-400 text-slate-950 uppercase tracking-wider">Single Master Document Workstation</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-white/20 text-white" id="inline-modal-version">v1.0</span>
                    </div>
                    <h3 class="text-xl font-extrabold text-white mt-1" id="inline-modal-title">Consultation Title</h3>
                    <p class="text-xs text-slate-300">Annotating master document directly. Contributions are logged into the audit trail.</p>
                </div>
                <button onclick="closeInlineInputModal()" class="text-white/80 hover:text-white text-2xl font-bold leading-none">&times;</button>
            </div>

            <!-- Modal Content Body -->
            <div class="p-6 overflow-y-auto flex-1 space-y-6 text-xs text-slate-700 scrollbar-thin">
                
                <!-- Section I: Existing AI Summary & Citizen Feedback Overview (Read Only) -->
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                        <h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider flex items-center gap-1.5">
                            <i class="bi bi-robot text-red-600"></i> Section I: AI Consultation Summary & Citizen Sentiment (Base Master Document)
                        </h4>
                        <span class="text-[10px] font-bold text-slate-400">Read-Only Base Layer</span>
                    </div>
                    <p class="text-slate-600 leading-relaxed" id="inline-modal-description">Loading base consultation summary and citizen sentiment data...</p>
                </div>

                <!-- Section II: Inline Expert Advisory Input (Interactive Form) -->
                <form id="inline-input-form" onsubmit="handleSaveInlineInput(event)" class="space-y-5">
                    <input type="hidden" name="consultation_id" id="inline-consultation-id">

                    <div class="border border-red-200 bg-red-50/40 rounded-2xl p-5 space-y-4">
                        <h4 class="font-extrabold text-red-950 text-xs uppercase tracking-wider flex items-center gap-1.5">
                            <i class="bi bi-pencil-square text-red-700"></i> Section II: Inline Expert Advisory & Technical Annotations
                        </h4>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">1. Executive Technical Summary *</label>
                            <textarea name="executive_summary" id="inline-exec-summary" rows="3" required
                                      class="w-full p-3 border border-slate-300 rounded-xl text-xs bg-white focus:ring-2 focus:ring-red-500 outline-none"
                                      placeholder="Provide high-level advisory summary of findings for the Secretariat..."></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">2. Technical Rationale & Policy Analysis *</label>
                            <textarea name="technical_rationale" id="inline-tech-rationale" rows="4" required
                                      class="w-full p-3 border border-slate-300 rounded-xl text-xs bg-white focus:ring-2 focus:ring-red-500 outline-none"
                                      placeholder="Detail engineering, health, social, or economic rationale supporting or modifying the policy..."></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">3. Legal & LGU Ordinance Alignment</label>
                            <textarea name="legal_alignment" id="inline-legal-alignment" rows="2"
                                      class="w-full p-3 border border-slate-300 rounded-xl text-xs bg-white focus:ring-2 focus:ring-red-500 outline-none"
                                      placeholder="Notes on compliance with national laws (RA 10173, etc.) or existing municipal ordinances..."></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">4. Proposed Ordinance Amendments & Specific Revisions</label>
                            <textarea name="proposed_revisions" id="inline-revisions" rows="3"
                                      class="w-full p-3 border border-slate-300 rounded-xl text-xs bg-white focus:ring-2 focus:ring-red-500 outline-none"
                                      placeholder="Specify clause-by-clause amendments or specific wording changes for the ordinance..."></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">5. Sign-off Readiness for LGU Committees</label>
                            <select name="signoff_status" id="inline-signoff-status" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs bg-white font-bold text-slate-800 outline-none">
                                <option value="ready_for_committee">Ready for LGU Committee Validation & Ordinance Drafting</option>
                                <option value="requires_info">Pending Additional Information / Clarification</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <span class="text-[11px] text-slate-500"><i class="bi bi-shield-check text-emerald-600"></i> Saves to single master document & updates version audit trail.</span>
                        <div class="flex gap-3">
                            <button type="button" onclick="closeInlineInputModal()" class="px-4 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold rounded-xl text-xs transition">Cancel</button>
                            <button type="submit" id="save-inline-btn" class="px-6 py-2.5 bg-red-700 hover:bg-red-800 text-white font-extrabold rounded-xl text-xs transition shadow-md flex items-center gap-1.5 cursor-pointer">
                                <i class="bi bi-check-circle-fill"></i> Save & Append to Master Document
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: MASTER DOCUMENT AUDIT TRAIL & CONSOLIDATED VIEW -->
    <div id="master-doc-audit-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-3xl w-full max-h-[85vh] overflow-hidden border border-slate-200 flex flex-col">
            <div class="bg-gradient-to-r from-slate-900 to-red-950 text-white p-6 flex justify-between items-center border-b border-red-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-amber-300 text-xl font-bold">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">Master Document Audit Trail & Version Control</h3>
                        <p class="text-xs text-slate-300">Complete traceability log of all expert annotations and admin updates</p>
                    </div>
                </div>
                <button onclick="closeMasterDocumentAuditModal()" class="text-white text-2xl font-bold leading-none">&times;</button>
            </div>

            <div class="p-6 overflow-y-auto space-y-4 text-xs text-slate-700 scrollbar-thin flex-1">
                <div class="border border-slate-200 rounded-2xl overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-100 font-bold text-[10px] text-slate-700 border-b border-slate-200 uppercase">
                            <tr>
                                <th class="p-3">Version</th>
                                <th class="p-3">Contributor</th>
                                <th class="p-3">Role</th>
                                <th class="p-3">Action / Changes Logged</th>
                                <th class="p-3">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody id="audit-trail-table-body" class="divide-y divide-slate-100 bg-white">
                            <tr><td colspan="5" class="p-6 text-center text-slate-400">Loading audit history...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-end">
                <button onclick="closeMasterDocumentAuditModal()" class="px-5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold rounded-xl text-xs transition">Close Audit Log</button>
            </div>
        </div>
    </div>

    <!-- Request Additional Info Modal -->
    <div id="info-request-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 relative border border-slate-200 space-y-5">
            <button onclick="closeInfoRequestModal()" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 text-lg">&times;</button>

            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center text-2xl">
                    <i class="bi bi-question-circle"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Request Information / Clarification</h3>
                    <p class="text-xs text-slate-500" id="info-modal-title">Consultation Title</p>
                </div>
            </div>

            <form action="../API/request_additional_info.php" method="POST" class="space-y-4">
                <input type="hidden" name="consultation_id" id="info-consultation-id">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Target Recipient</label>
                        <select name="target_entity" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl outline-none font-medium">
                            <option value="Admin & Committee">City Admin & Committee</option>
                            <option value="Committee Secretariat">Committee Secretariat</option>
                            <option value="Citizen Submitter">Citizen Submitter</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Priority</label>
                        <select name="priority" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl outline-none font-medium">
                            <option value="normal">Normal</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Inquiry / Information Requested *</label>
                    <textarea name="message" rows="4" required
                              class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none"
                              placeholder="Specify missing document details, technical queries, or further clarification needed..."></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeInfoRequestModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 rounded-2xl font-semibold text-xs transition">Cancel</button>
                    <button type="submit" class="flex-1 bg-amber-600 hover:bg-amber-700 text-white py-3 rounded-2xl font-semibold text-xs transition shadow-md flex items-center justify-center gap-1.5">
                        <i class="bi bi-send"></i> Send Inquiry
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Knowledge Base & Guidelines Modal -->
    <div id="knowledge-base-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-3xl w-full p-6 sm:p-8 relative border border-slate-200 space-y-5">
            <button onclick="closeKnowledgeBaseModal()" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 text-lg">&times;</button>

            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center text-2xl font-bold">
                    <i class="bi bi-book"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Guidelines & Template Center</h3>
                    <p class="text-xs text-slate-500">Official City of Valenzuela Advisory Protocols</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                    <h4 class="font-bold text-slate-900 flex items-center gap-1.5"><i class="bi bi-funnel text-red-600"></i> Expertise Access Control Rules</h4>
                    <p class="text-slate-600">Consultations are routed automatically to Resource Persons based on their registered expertise area after AI analysis is finalized and Admin dispatches the policy.</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                    <h4 class="font-bold text-slate-900 flex items-center gap-1.5"><i class="bi bi-shield-check text-emerald-600"></i> Audit Trail & Compliance</h4>
                    <p class="text-slate-600">All inline additions automatically generate a version tag and audit trail entry, tracking contributor identity, timestamp, and changes.</p>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="button" onclick="closeKnowledgeBaseModal()" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold rounded-xl text-xs transition">Close</button>
            </div>
        </div>
    </div>

    <!-- JS WORKFLOW & AUDIT TRAIL ENGINE -->
    <script>
    function toggleSidebar() {
        var sb = document.getElementById('sidebar');
        if (sb) sb.classList.toggle('-translate-x-full');
    }

    function toggleNotificationDrawer() {
        var dr = document.getElementById('notif-drawer');
        if (dr) dr.classList.toggle('translate-x-full');
    }

    function markAllNotificationsRead() {
        fetch('../API/resource_person_api.php?action=mark_notif_read', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    function filterTasks() {
        var query = document.getElementById('search-task').value.toLowerCase().trim();
        var assignment = document.getElementById('filter-assignment').value;
        var cards = document.querySelectorAll('.task-card');

        cards.forEach(function(card) {
            var title = card.getAttribute('data-title') || '';
            var category = card.getAttribute('data-category') || '';
            var cardAssigned = card.getAttribute('data-assigned') || '';

            var matchesSearch = !query || title.includes(query) || category.includes(query);
            var matchesAssignment = (assignment === 'all') || cardAssigned.includes(assignment);

            if (matchesSearch && matchesAssignment) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function filterTaskCategory(cat) {
        var sel = document.getElementById('filter-assignment');
        if (sel) {
            sel.value = cat;
            filterTasks();
        }
    }

    function openInlineInputModal(id, title, version) {
        document.getElementById('inline-consultation-id').value = id;
        document.getElementById('inline-modal-title').textContent = title;
        document.getElementById('inline-modal-version').textContent = version || 'v1.0';

        // Load consultation base description & existing expert notes
        fetch(`../API/resource_person_api.php?action=get_consultation_details&consultation_id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.consultation) {
                const c = data.consultation;
                document.getElementById('inline-modal-description').innerHTML = `
                    <strong class="text-slate-900 block mb-1">Policy Category: ${c.category || 'General'}</strong>
                    ${c.description || 'No description provided.'}
                `;

                if (data.parsed_expert_notes) {
                    const notes = data.parsed_expert_notes;
                    document.getElementById('inline-exec-summary').value = notes.executive_summary || '';
                    document.getElementById('inline-tech-rationale').value = notes.technical_rationale || '';
                    document.getElementById('inline-legal-alignment').value = notes.legal_alignment || '';
                    document.getElementById('inline-revisions').value = notes.proposed_revisions || '';
                    document.getElementById('inline-signoff-status').value = notes.signoff_status || 'ready_for_committee';
                } else {
                    document.getElementById('inline-exec-summary').value = '';
                    document.getElementById('inline-tech-rationale').value = '';
                    document.getElementById('inline-legal-alignment').value = '';
                    document.getElementById('inline-revisions').value = '';
                    document.getElementById('inline-signoff-status').value = 'ready_for_committee';
                }
            }
        });

        document.getElementById('inline-input-modal').classList.remove('hidden');
    }

    function closeInlineInputModal() {
        document.getElementById('inline-input-modal').classList.add('hidden');
    }

    function handleSaveInlineInput(e) {
        e.preventDefault();
        const form = document.getElementById('inline-input-form');
        const formData = new FormData(form);
        const btn = document.getElementById('save-inline-btn');

        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i> Saving to Master Document...';

        fetch('../API/save_inline_expert_input.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Save & Append to Master Document';
            if (data.success) {
                alert(data.message);
                closeInlineInputModal();
                location.reload();
            } else {
                alert('⚠️ ' + (data.message || 'Failed to save inline input'));
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Save & Append to Master Document';
            alert('❌ Error: ' + err.message);
        });
    }

    function openMasterDocumentAuditModal(id) {
        const tbody = document.getElementById('audit-trail-table-body');
        tbody.innerHTML = '<tr><td colspan="5" class="p-6 text-center text-slate-400"><i class="bi bi-arrow-repeat animate-spin block text-lg mb-1"></i> Loading document audit log...</td></tr>';

        fetch(`../API/resource_person_api.php?action=get_consultation_details&consultation_id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.audit_trail && data.audit_trail.length > 0) {
                tbody.innerHTML = data.audit_trail.map(a => `
                    <tr class="hover:bg-slate-50 transition text-xs">
                        <td class="p-3 font-mono font-bold text-red-800">${escapeHtml(a.version_label)}</td>
                        <td class="p-3 font-bold text-slate-900">${escapeHtml(a.user_name)}</td>
                        <td class="p-3"><span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-800">${escapeHtml(a.user_role)}</span></td>
                        <td class="p-3 font-medium text-slate-700">${escapeHtml(a.changes_summary)}</td>
                        <td class="p-3 text-slate-500">${a.created_at}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="p-6 text-center text-slate-400">No version audit records logged yet. Base document created.</td></tr>';
            }
        });

        document.getElementById('master-doc-audit-modal').classList.remove('hidden');
    }

    function closeMasterDocumentAuditModal() {
        document.getElementById('master-doc-audit-modal').classList.add('hidden');
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

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // Live Clock
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
