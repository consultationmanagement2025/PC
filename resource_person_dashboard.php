<?php
/**
 * Resource Person Dashboard (Admin-Matching UI Layout + Automatic Expertise Categorization)
 * Interactive workspace for approved resource persons / subject matter experts.
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
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM users");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $cols[] = $r['Field'];
        }
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

    $cCols = [];
    $cRes = $conn->query("SHOW COLUMNS FROM consultations");
    if ($cRes) {
        while ($r = $cRes->fetch_assoc()) {
            $cCols[] = $r['Field'];
        }
    }
    if (!in_array('assigned_to', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN assigned_to INT(11) DEFAULT NULL");

    @$conn->query("CREATE TABLE IF NOT EXISTS resolution_reports (
        id INT(11) NOT NULL AUTO_INCREMENT,
        consultation_id INT(11) NOT NULL,
        uploaded_by INT(11) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @$conn->query("CREATE TABLE IF NOT EXISTS info_requests (
        id INT(11) NOT NULL AUTO_INCREMENT,
        consultation_id INT(11) NOT NULL,
        requested_by INT(11) NOT NULL,
        user_email VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('pending', 'responded', 'closed') DEFAULT 'pending',
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

$expertise_areas = $userProfile['expertise_areas'] ?? 'General Consultation';
$department = $userProfile['department'] ?? 'Public Sector';

// Convert expertise_areas string to array of trimmed categories
$my_expertise_raw = strtolower($expertise_areas);
$my_expertise_list = array_values(array_filter(array_map('trim', explode(',', $my_expertise_raw))));

// Get consultations
$consultations = [];
$consultations_stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM resolution_reports r WHERE r.consultation_id = c.id) as reports_count,
           (SELECT COUNT(*) FROM info_requests i WHERE i.consultation_id = c.id) as info_requests_count
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
    // Fallback query
    $cRes = $conn->query("SELECT c.*, 0 as reports_count, 0 as info_requests_count FROM consultations c ORDER BY c.created_at DESC");
    if ($cRes) {
        while ($row = $cRes->fetch_assoc()) {
            $consultations[] = $row;
        }
    }
}

// Stats & Automatic Expertise Matching calculation
$total_assigned = 0;
$expertise_match_count = 0;
$active_count = 0;
$completed_count = 0;
$total_reports = 0;

foreach ($consultations as &$c) {
    $cCat = strtolower(trim($c['category'] ?? ''));
    $c['is_expertise_match'] = false;
    if (!empty($cCat) && !empty($my_expertise_list)) {
        foreach ($my_expertise_list as $exp) {
            if (!empty($exp) && (strpos($cCat, $exp) !== false || strpos($exp, $cCat) !== false)) {
                $c['is_expertise_match'] = true;
                $expertise_match_count++;
                break;
            }
        }
    }
    if ($c['assigned_to'] == $user_id) {
        $total_assigned++;
    }
    if (in_array($c['status'], ['active', 'scheduled', 'pending'])) {
        $active_count++;
    } elseif (in_array($c['status'], ['completed', 'closed'])) {
        $completed_count++;
    }
    $total_reports += (int)($c['reports_count'] ?? 0);
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
    <link rel="stylesheet" href="ASSETS/vendor/bootstrap-icons/font/bootstrap-icons.css">
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
                    <img src="images/logo.webp" alt="Logo" class="w-full h-full object-contain" onerror="this.src='ASSETS/images/logo.png'">
                </div>
                <span class="font-bold text-sm">PCMS Expert Portal</span>
            </div>
        </div>
        <a href="logout.php" class="text-xs bg-red-950/60 px-3 py-1.5 rounded-lg border border-red-700/50 flex items-center gap-1">
            <i class="bi bi-box-arrow-right"></i> Exit
        </a>
    </header>

    <!-- Admin-Style Collapsible Sidebar -->
    <aside id="sidebar" class="sidebar w-64 bg-gradient-to-b from-red-800 to-red-900 text-white flex-shrink-0 flex flex-col h-screen fixed md:sticky top-0 z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-300 shadow-xl">
        <!-- Logo Header Section -->
        <div class="p-6 border-b border-red-700/60 flex items-center gap-3">
            <div class="w-14 h-14 bg-white rounded-full flex items-center justify-center p-1 shadow-md shrink-0">
                <img src="images/logo.webp" alt="Valenzuela Logo" class="w-full h-full object-contain" onerror="this.src='ASSETS/images/logo.png'">
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
                        <i class="bi bi-speedometer2 text-lg"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="#assigned" onclick="filterTaskCategory('assigned')" class="flex items-center px-4 py-3 text-red-100 hover:bg-red-700/60 hover:text-white rounded-xl text-sm transition gap-3">
                        <i class="bi bi-journal-check text-lg"></i>
                        <span>Assigned Tasks</span>
                        <?php if ($total_assigned > 0): ?>
                            <span class="ml-auto px-2 py-0.5 rounded-full text-xs bg-white text-red-900 font-bold"><?php echo $total_assigned; ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="#reports" onclick="filterTaskCategory('all')" class="flex items-center px-4 py-3 text-red-100 hover:bg-red-700/60 hover:text-white rounded-xl text-sm transition gap-3">
                        <i class="bi bi-file-earmark-text text-lg"></i>
                        <span>Resolution Reports</span>
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
                        <span class="text-slate-800 font-medium">Resource Person Workspace</span>
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

                <!-- Notifications & Profile Avatar -->
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

            <!-- Red Gradient Header Banner (Matching Admin Panel) -->
            <div class="bg-gradient-to-r from-red-700 via-red-800 to-red-900 text-white p-6 sm:p-8 rounded-2xl shadow-lg relative overflow-hidden">
                <div class="relative z-10 space-y-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-red-100 text-xs font-medium backdrop-blur-sm">
                        <i class="bi bi-patch-check-fill text-emerald-400"></i> Verified Subject Matter Expert
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold">Welcome, <?php echo htmlspecialchars($fullname); ?></h1>
                    <p class="text-red-100 text-xs sm:text-sm max-w-2xl">
                        Department: <strong class="text-white"><?php echo htmlspecialchars($department); ?></strong> &bull; Registered Specializations: <span class="text-amber-200 font-semibold"><?php echo htmlspecialchars($expertise_areas); ?></span>
                    </p>
                </div>
                <div class="absolute -right-6 -bottom-10 opacity-15 text-9xl text-white pointer-events-none">
                    <i class="bi bi-award"></i>
                </div>
            </div>

            <!-- KPI Metric Summary Cards (Admin Matching Grid) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Assigned to You</p>
                        <p class="text-3xl font-bold text-slate-900 mt-1"><?php echo $total_assigned; ?></p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Assigned by City Admin</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center text-2xl">
                        <i class="bi bi-journal-check"></i>
                    </div>
                </div>



                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Reports Uploaded</p>
                        <p class="text-3xl font-bold text-emerald-600 mt-1"><?php echo $total_reports; ?></p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Resolution papers</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
                        <i class="bi bi-file-earmark-check"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Completed Tasks</p>
                        <p class="text-3xl font-bold text-blue-600 mt-1"><?php echo $completed_count; ?></p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Closed consultations</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>

            <!-- Toolbar Filters Section -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <i class="bi bi-list-task text-red-600"></i> Public Consultations & Policy Reviews
                    </h3>
                    
                    <!-- Search & Filter Controls -->
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <div class="relative flex-1 sm:w-64">
                            <i class="bi bi-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                            <input type="text" id="search-task" onkeyup="filterTasks()" placeholder="Search title or category..." 
                                   class="w-full pl-9 pr-4 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none">
                        </div>

                        <select id="filter-assignment" onchange="filterTasks()" class="px-3 py-2 text-xs border border-slate-300 rounded-xl outline-none bg-white font-medium">
                            <option value="all">All Consultations</option>
                            <option value="assigned">Assigned to Me (<?php echo $total_assigned; ?>)</option>

                            <option value="general">General Consultations</option>
                        </select>
                    </div>
                </div>

                <!-- Consultation Task Cards Container -->
                <div id="tasks-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 pt-2">
                    <?php if (empty($consultations)): ?>
                        <div class="col-span-full bg-slate-50 border border-slate-200 rounded-2xl p-12 text-center text-slate-500">
                            <i class="bi bi-inbox text-4xl text-slate-300 mb-3 inline-block"></i>
                            <p class="font-semibold text-slate-700">No Consultations Available</p>
                            <p class="text-xs text-slate-400 mt-1">Check back later or contact your City Administrator for assignments.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($consultations as $c): ?>
                            <?php 
                                $isAssignedToMe = ($c['assigned_to'] == $user_id);
                                $isExpertiseMatch = !empty($c['is_expertise_match']);
                                $category = htmlspecialchars($c['category'] ?? 'General');
                                $status = htmlspecialchars($c['status'] ?? 'active');
                                $statusBg = 'bg-emerald-100 text-emerald-800';
                                if ($status === 'completed' || $status === 'closed') $statusBg = 'bg-blue-100 text-blue-800';
                                if ($status === 'pending' || $status === 'scheduled') $statusBg = 'bg-amber-100 text-amber-800';

                                $cardTag = $isAssignedToMe ? 'assigned' : ($isExpertiseMatch ? 'expertise' : 'general');
                            ?>
                            <div class="task-card bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition flex flex-col justify-between p-6 space-y-4"
                                 data-assigned="<?php echo $cardTag; ?>"
                                 data-title="<?php echo htmlspecialchars(strtolower($c['title'])); ?>"
                                 data-category="<?php echo htmlspecialchars(strtolower($category)); ?>">

                                <div class="space-y-3">
                                    <!-- Top Badges -->
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                            <?php echo $category; ?>
                                        </span>
                                        <?php if ($isAssignedToMe): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-red-100 text-red-800 border border-red-200 flex items-center gap-1">
                                                <i class="bi bi-star-fill text-red-600 text-[10px]"></i> Assigned to You
                                            </span>
                                        <?php elseif ($isExpertiseMatch): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200 flex items-center gap-1">
                                                <i class="bi bi-award-fill text-amber-600 text-[10px]"></i> Expertise Match
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600">
                                                General
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Consultation Title & Description -->
                                    <h4 class="text-base font-bold text-slate-900 leading-snug line-clamp-2">
                                        <?php echo htmlspecialchars($c['title']); ?>
                                    </h4>
                                    <p class="text-xs text-slate-500 line-clamp-3">
                                        <?php echo htmlspecialchars($c['description'] ?? 'No description provided.'); ?>
                                    </p>
                                </div>

                                <!-- Footer Metrics & Actions -->
                                <div class="space-y-4 pt-3 border-t border-slate-100">
                                    <div class="flex items-center justify-between text-xs text-slate-500">
                                        <div class="flex items-center gap-3">
                                            <span class="flex items-center gap-1 font-medium"><i class="bi bi-file-earmark-check text-emerald-600"></i> <?php echo (int)$c['reports_count']; ?> Reports</span>
                                            <span class="flex items-center gap-1 font-medium"><i class="bi bi-question-circle text-amber-600"></i> <?php echo (int)$c['info_requests_count']; ?> Info Req</span>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?php echo $statusBg; ?>">
                                            <?php echo $status; ?>
                                        </span>
                                    </div>

                                    <div class="flex gap-2">
                                        <button onclick="openUploadModal(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['title'])); ?>')" 
                                                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-3 rounded-xl text-xs transition flex items-center justify-center gap-1.5 shadow-sm">
                                            <i class="bi bi-upload"></i> Report
                                        </button>
                                        <button onclick="openInfoRequestModal(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['title'])); ?>')" 
                                                class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 px-3 rounded-xl text-xs transition border border-slate-200 flex items-center justify-center gap-1.5">
                                            <i class="bi bi-question-circle"></i> Ask Info
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>

    <!-- Upload Resolution Report Modal (Admin Styled) -->
    <div id="upload-report-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 relative border border-slate-200 space-y-5">
            <button onclick="closeUploadModal()" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 text-lg">
                <i class="bi bi-x-lg"></i>
            </button>

            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div class="w-12 h-12 rounded-2xl bg-red-100 text-red-600 flex items-center justify-center text-2xl">
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
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Select Document File (.pdf, .doc, .docx) *</label>
                    <input type="file" name="report_file" accept=".pdf,.doc,.docx" required
                           class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Executive Summary / Recommendation Notes *</label>
                    <textarea name="notes" rows="4" required
                              class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none"
                              placeholder="Provide technical rationale, committee recommendations, or policy findings..."></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeUploadModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 rounded-2xl font-semibold text-xs transition">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3 rounded-2xl font-semibold text-xs transition shadow-md flex items-center justify-center gap-1.5">
                        <i class="bi bi-upload"></i> Submit Resolution Paper
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Request Additional Info Modal -->
    <div id="info-request-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 relative border border-slate-200 space-y-5">
            <button onclick="closeInfoRequestModal()" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 text-lg">
                <i class="bi bi-x-lg"></i>
            </button>

            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center text-2xl">
                    <i class="bi bi-question-circle"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Request Information / Clarification</h3>
                    <p class="text-xs text-slate-500" id="info-modal-title">Consultation Title</p>
                </div>
            </div>

            <form action="API/request_additional_info.php" method="POST" class="space-y-4">
                <input type="hidden" name="consultation_id" id="info-consultation-id">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Target Citizen Email (Optional)</label>
                    <input type="email" name="user_email" placeholder="citizen@email.com (leave blank for public request)"
                           class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Inquiry / Information Requested *</label>
                    <textarea name="message" rows="4" required
                              class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none"
                              placeholder="Specify missing document details, technical queries, or further clarification needed from submitter..."></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeInfoRequestModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 rounded-2xl font-semibold text-xs transition">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 bg-amber-600 hover:bg-amber-700 text-white py-3 rounded-2xl font-semibold text-xs transition shadow-md flex items-center justify-center gap-1.5">
                        <i class="bi bi-send"></i> Send Inquiry
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function toggleSidebar() {
        var sb = document.getElementById('sidebar');
        if (sb) sb.classList.toggle('-translate-x-full');
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
            var matchesAssignment = (assignment === 'all') || (assignment === cardAssigned);

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

    // Update Live Clock
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
