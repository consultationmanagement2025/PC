<?php
/**
 * Resource Person Registration Page
 * Users who sign in with Google or apply as Resource Person are handled here.
 */
session_start();

$error = '';
$success = '';

// Check if user came from Google OAuth or is logged in
$googleUser = $_SESSION['google_user'] ?? null;

// Official PCMS Categories
$official_categories = [
    "Appropriations",
    "Ways & Means",
    "Women, Family & Gender Equality",
    "Justice & Human Rights",
    "Higher & Technical Education",
    "Cooperatives",
    "Health & Sanitation",
    "Social Services",
    "Livelihood, Trade, Commerce & Industry",
    "Food & Agriculture",
    "Urban Planning, Housing & Development",
    "Public Utilities & Facilities",
    "Market & Slaughterhouse",
    "Rules & Privileges"
];

// Official City Departments & Offices
$official_departments = [
    "City Planning & Development Office",
    "Health & Sanitation Department",
    "Social Welfare & Development Office (CSWDO)",
    "Business Permits & Licensing Office (BPLO)",
    "City Engineering & Infrastructure",
    "Agriculture & Food Security Office",
    "City Legal Office",
    "City Budget & Treasury Office",
    "Education & Technical Training Division",
    "Cooperative Development Office",
    "Market & Slaughterhouse Administration",
    "Disaster Risk Reduction & Management Office (DRRMO)",
    "Public Employment Service Office (PESO)",
    "Others"
];

// Auto-heal schema helper to ensure all resource person columns exist in the users table
function ensureResourcePersonSchema($conn) {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM users");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $cols[] = $r['Field'];
        }
    }
    if (!in_array('google_id', $cols)) {
        @$conn->query("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) DEFAULT NULL AFTER email");
    }
    if (!in_array('google_token', $cols)) {
        @$conn->query("ALTER TABLE users ADD COLUMN google_token TEXT DEFAULT NULL");
    }
    if (!in_array('expertise_areas', $cols)) {
        @$conn->query("ALTER TABLE users ADD COLUMN expertise_areas TEXT DEFAULT NULL");
    }
    if (!in_array('qualifications', $cols)) {
        @$conn->query("ALTER TABLE users ADD COLUMN qualifications TEXT DEFAULT NULL");
    }
    if (!in_array('department', $cols)) {
        @$conn->query("ALTER TABLE users ADD COLUMN department VARCHAR(255) DEFAULT NULL");
    }
    if (!in_array('phone', $cols)) {
        @$conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(50) DEFAULT NULL");
    }
    if (!in_array('approved_by', $cols)) {
        @$conn->query("ALTER TABLE users ADD COLUMN approved_by INT DEFAULT NULL");
    }
    if (!in_array('approved_at', $cols)) {
        @$conn->query("ALTER TABLE users ADD COLUMN approved_at DATETIME DEFAULT NULL");
    }
    if (!in_array('verification_status', $cols)) {
        @$conn->query("ALTER TABLE users ADD COLUMN verification_status VARCHAR(50) NOT NULL DEFAULT 'pending'");
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db.php';
    ensureResourcePersonSchema($conn);
    
    $fullname = trim($_POST['fullname'] ?? ($googleUser['name'] ?? ''));
    $email = $googleUser['email'] ?? trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $google_id = $googleUser['google_id'] ?? null;
    $google_token = isset($googleUser['token']) ? json_encode($googleUser['token']) : null;
    
    // Process Expertise Categories
    $selected_cats = isset($_POST['expertise_categories']) && is_array($_POST['expertise_categories']) ? $_POST['expertise_categories'] : [];
    $custom_cat = trim($_POST['expertise_custom'] ?? '');
    if (!empty($custom_cat)) {
        $selected_cats[] = $custom_cat;
    }
    $expertise_areas = implode(', ', array_filter(array_map('trim', $selected_cats)));
    if (empty($expertise_areas)) {
        $expertise_areas = trim($_POST['expertise_areas_legacy'] ?? '');
    }

    // Process Department / Office
    $department = trim($_POST['department'] ?? '');
    if ($department === 'Others' || !empty($_POST['department_custom'])) {
        $department_custom = trim($_POST['department_custom'] ?? '');
        if (!empty($department_custom)) {
            $department = $department_custom;
        }
    }

    $qualifications = trim($_POST['qualifications'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($fullname) || empty($email) || empty($expertise_areas) || empty($qualifications) || empty($department)) {
        $error = 'Please fill in all required fields and select at least one area of expertise.';
    } else {
        // Check if email already exists
        $checkStmt = $conn->prepare("SELECT id, role, verification_status FROM users WHERE email = ?");
        $checkStmt->bind_param('s', $email);
        $checkStmt->execute();
        $res = $checkStmt->get_result();
        $existing = $res->fetch_assoc();
        $checkStmt->close();
        
        if ($existing) {
            // User exists: update user profile to apply as resource person
            $userId = $existing['id'];
            $updateStmt = $conn->prepare("UPDATE users SET fullname = ?, role = 'resource person', verification_status = 'pending', expertise_areas = ?, qualifications = ?, department = ?, phone = ? WHERE id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param('sssssi', $fullname, $expertise_areas, $qualifications, $department, $phone, $userId);
                if ($updateStmt->execute()) {
                    $_SESSION['temp_user_id'] = $userId;
                    unset($_SESSION['google_user']);
                    
                    // Create notification for Admin
                    if (file_exists('DATABASE/notifications.php')) {
                        require_once 'DATABASE/notifications.php';
                        if (function_exists('createNotification')) {
                            createNotification(0, "👤 New Resource Person Application: $fullname ($department) applied for expert role. Please review in User Management.", "user_registration");
                        }
                    } else {
                        @$conn->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES (0, '👤 New Resource Person Application: " . $conn->real_escape_string($fullname) . " (" . $conn->real_escape_string($department) . ") applied for expert role. Please review in User Management.', 'user_registration', NOW())");
                    }

                    header('Location: pending_approval.php');
                    exit;
                } else {
                    $error = 'Failed to update application: ' . $updateStmt->error;
                }
                $updateStmt->close();
            } else {
                $error = 'Database query error: ' . $conn->error;
            }
        } else {
            $hashed_pass = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            
            // Check existing columns to handle database variation safely
            $userCols = [];
            $colRes = $conn->query("SHOW COLUMNS FROM users");
            if ($colRes) {
                while ($cr = $colRes->fetch_assoc()) {
                    $userCols[] = $cr['Field'];
                }
            }
            $hasGoogleId = in_array('google_id', $userCols);

            if ($hasGoogleId) {
                $insertStmt = $conn->prepare("INSERT INTO users (fullname, email, password, google_id, google_token, role, verification_status, expertise_areas, qualifications, department, phone, status) VALUES (?, ?, ?, ?, ?, 'resource person', 'pending', ?, ?, ?, ?, 'active')");
                if ($insertStmt) {
                    $insertStmt->bind_param('sssssssss', $fullname, $email, $hashed_pass, $google_id, $google_token, $expertise_areas, $qualifications, $department, $phone);
                    if ($insertStmt->execute()) {
                        $userId = $conn->insert_id;
                        unset($_SESSION['google_user']);
                        $_SESSION['temp_user_id'] = $userId;

                        // Create notification for Admin
                        if (file_exists('DATABASE/notifications.php')) {
                            require_once 'DATABASE/notifications.php';
                            if (function_exists('createNotification')) {
                                createNotification(0, "👤 New Resource Person Application: $fullname ($department) applied for expert role. Please review in User Management.", "user_registration");
                            }
                        } else {
                            @$conn->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES (0, '👤 New Resource Person Application: " . $conn->real_escape_string($fullname) . " (" . $conn->real_escape_string($department) . ") applied for expert role. Please review in User Management.', 'user_registration', NOW())");
                        }

                        header('Location: pending_approval.php');
                        exit;
                    } else {
                        $error = 'Registration failed: ' . $insertStmt->error;
                    }
                    $insertStmt->close();
                } else {
                    $error = 'Database query error: ' . $conn->error;
                }
            } else {
                $insertStmt = $conn->prepare("INSERT INTO users (fullname, email, password, role, verification_status, expertise_areas, qualifications, department, phone, status) VALUES (?, ?, ?, 'resource person', 'pending', ?, ?, ?, ?, 'active')");
                if ($insertStmt) {
                    $insertStmt->bind_param('sssssss', $fullname, $email, $hashed_pass, $expertise_areas, $qualifications, $department, $phone);
                    if ($insertStmt->execute()) {
                        $userId = $conn->insert_id;
                        unset($_SESSION['google_user']);
                        $_SESSION['temp_user_id'] = $userId;
                        header('Location: pending_approval.php');
                        exit;
                    } else {
                        $error = 'Registration failed: ' . $insertStmt->error;
                    }
                    $insertStmt->close();
                } else {
                    $error = 'Database query error: ' . $conn->error;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Person Registration - PCMS</title>
    <link rel="icon" type="image/png" href="images/logo.webp">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="ASSETS/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4 py-8">
    <div class="bg-white rounded-3xl shadow-xl max-w-3xl w-full p-6 sm:p-10 border border-slate-200">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 text-red-600">
                <i class="bi bi-person-badge text-4xl"></i>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-800">Resource Person Application</h1>
            <p class="text-slate-500 text-sm mt-1">Select your areas of expertise matching Valenzuela PCMS consultation categories</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl mb-6 text-sm flex items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <?php if ($googleUser): ?>
                <!-- Google Account Info -->
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 flex items-center gap-4">
                    <?php if (!empty($googleUser['picture'])): ?>
                        <img src="<?php echo htmlspecialchars($googleUser['picture']); ?>" alt="Profile" class="w-12 h-12 rounded-full border border-slate-300">
                    <?php endif; ?>
                    <div>
                        <p class="font-semibold text-slate-800 text-sm"><?php echo htmlspecialchars($googleUser['name']); ?></p>
                        <p class="text-xs text-slate-500"><?php echo htmlspecialchars($googleUser['email']); ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Email Address *</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required
                               class="w-full px-4 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Account Password *</label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none"
                               placeholder="Create account password">
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Full Name -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Full Name *</label>
                    <input type="text" name="fullname" value="<?php echo htmlspecialchars($googleUser['name'] ?? ($_SESSION['fullname'] ?? '')); ?>" required
                           class="w-full px-4 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none"
                           placeholder="e.g. Dr. Juan Dela Cruz">
                </div>

                <!-- Phone Number -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Contact Phone Number</label>
                    <input type="tel" name="phone"
                           class="w-full px-4 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none"
                           placeholder="e.g. +63 917 123 4567">
                </div>
            </div>

            <!-- Department / Office / Organization -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Department / Office / Organization *</label>
                <select name="department" id="department-select" onchange="toggleCustomDepartment()" required
                        class="w-full px-4 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none bg-white">
                    <option value="">-- Select Department / Office --</option>
                    <?php foreach ($official_departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                    <?php endforeach; ?>
                </select>

                <div id="custom-department-container" class="mt-3 hidden">
                    <input type="text" name="department_custom" id="department-custom"
                           class="w-full px-4 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none"
                           placeholder="Specify your Department, University, or Organization name...">
                </div>
            </div>

            <!-- Areas of Expertise (Official Categories + Custom) -->
            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-3">
                <div class="flex justify-between items-center">
                    <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">
                        <i class="bi bi-award text-red-600 mr-1"></i> Areas of Expertise (Select all that apply) *
                    </label>
                    <span class="text-[11px] text-slate-400">Aligned with PCMS Categories</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-60 overflow-y-auto pr-1">
                    <?php foreach ($official_categories as $index => $cat): ?>
                        <label class="flex items-center gap-2.5 bg-white p-2.5 rounded-xl border border-slate-200 hover:border-red-300 transition cursor-pointer text-xs text-slate-700 font-medium select-none">
                            <input type="checkbox" name="expertise_categories[]" value="<?php echo htmlspecialchars($cat); ?>"
                                   class="w-4 h-4 text-red-600 rounded border-slate-300 focus:ring-red-500">
                            <span><?php echo htmlspecialchars($cat); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <!-- Custom / Additional Expertise Field -->
                <div class="pt-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        <i class="bi bi-plus-circle text-slate-500 mr-1"></i> Others / Specific Specializations
                    </label>
                    <input type="text" name="expertise_custom" 
                           class="w-full px-4 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none bg-white"
                           placeholder="e.g. AI & Technology Governance, Disaster Risk Reduction, Traffic Management...">
                    <p class="text-[11px] text-slate-400 mt-1">If your field of expertise isn't listed above, type it here (comma-separated).</p>
                </div>
            </div>

            <!-- Qualifications & Background -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Qualifications & Background *</label>
                <textarea name="qualifications" rows="3" required
                          class="w-full px-4 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none"
                          placeholder="e.g. Master of Civil Engineering, 10 years experience in City Urban Planning & Infrastructure Management"></textarea>
            </div>

            <!-- Submit Buttons -->
            <div class="flex gap-4 pt-2">
                <button type="submit" 
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3.5 px-6 rounded-2xl font-semibold text-sm transition shadow-md flex items-center justify-center gap-2">
                    <i class="bi bi-send"></i> Submit Application
                </button>
                <a href="index.php" class="flex-1 bg-slate-200 hover:bg-slate-300 text-slate-700 py-3.5 px-6 rounded-2xl font-semibold text-sm transition text-center no-underline">
                    Cancel
                </a>
            </div>
        </form>

        <p class="text-center text-xs text-slate-400 mt-6 flex items-center justify-center gap-1.5">
            <i class="bi bi-shield-check text-red-600"></i> Applications are reviewed by City Administrators prior to granting expert role access.
        </p>
    </div>

    <script>
    function toggleCustomDepartment() {
        var select = document.getElementById('department-select');
        var container = document.getElementById('custom-department-container');
        var input = document.getElementById('department-custom');
        if (select.value === 'Others') {
            container.classList.remove('hidden');
            input.setAttribute('required', 'required');
        } else {
            container.classList.add('hidden');
            input.removeAttribute('required');
        }
    }
    </script>
</body>
</html>
