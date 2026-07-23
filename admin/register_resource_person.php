<?php
/**
 * Resource Person Registration Page
 * Users who sign in with Google are redirected here to complete registration
 */
session_start();

// Check if user came from Google OAuth
if (!isset($_SESSION['google_user'])) {
    header('Location: ../public/index.php');
    exit;
}

$googleUser = $_SESSION['google_user'];
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db.php';
    
    $fullname = trim($_POST['fullname'] ?? $googleUser['name']);
    $email = $googleUser['email'];
    $google_id = $googleUser['google_id'];
    $expertise_areas = trim($_POST['expertise_areas'] ?? '');
    $qualifications = trim($_POST['qualifications'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validate required fields
    if (empty($expertise_areas) || empty($qualifications) || empty($department)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if email already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->bind_param('s', $email);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            // Insert new resource person with pending approval
            $insertStmt = $conn->prepare("INSERT INTO users (fullname, email, google_id, google_token, role, approval_status, expertise_areas, qualifications, department, phone, status) VALUES (?, ?, ?, ?, 'resource person', 'pending', ?, ?, ?, ?, 'active')");
            $insertStmt->bind_param('ssssssss', $fullname, $email, $google_id, json_encode($googleUser['token']), $expertise_areas, $qualifications, $department, $phone);
            
            if ($insertStmt->execute()) {
                $userId = $conn->insert_id;
                unset($_SESSION['google_user']);
                $_SESSION['temp_user_id'] = $userId;
                header('Location: pending_approval.php');
                exit;
            } else {
                $error = 'Registration failed. Please try again.';
            }
            
            $insertStmt->close();
        }
        
        $checkStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Person Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-8">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="bi bi-person-badge text-4xl text-red-600"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Resource Person Registration</h1>
            <p class="text-gray-600 mt-2">Complete your profile to apply as a Resource Person</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <!-- Google Account Info -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-semibold text-gray-900 mb-3">Google Account</h3>
                <div class="flex items-center gap-4">
                    <?php if ($googleUser['picture']): ?>
                        <img src="<?php echo htmlspecialchars($googleUser['picture']); ?>" alt="Profile" class="w-12 h-12 rounded-full">
                    <?php endif; ?>
                    <div>
                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($googleUser['name']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($googleUser['email']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Full Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                <input type="text" name="fullname" value="<?php echo htmlspecialchars($googleUser['name']); ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
            </div>

            <!-- Expertise Areas -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Expertise Areas *</label>
                <textarea name="expertise_areas" rows="3" required
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                          placeholder="e.g., Urban Planning, Environmental Policy, Public Health, Education"></textarea>
                <p class="text-xs text-gray-500 mt-1">List your areas of expertise (comma-separated)</p>
            </div>

            <!-- Qualifications -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Qualifications *</label>
                <textarea name="qualifications" rows="3" required
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                          placeholder="e.g., Master's in Urban Planning, 10 years experience in local government"></textarea>
            </div>

            <!-- Department -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Department/Organization *</label>
                <input type="text" name="department" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                       placeholder="e.g., City Planning Office, University Department">
            </div>

            <!-- Phone -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                <input type="tel" name="phone"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                       placeholder="e.g., +63 912 345 6789">
            </div>

            <!-- Submit -->
            <div class="flex gap-4">
                <button type="submit" 
                        class="flex-1 bg-red-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-red-700 transition">
                    Submit Application
                </button>
                <a href="../public/index.php" class="flex-1 bg-gray-200 text-gray-700 py-3 px-6 rounded-lg font-medium hover:bg-gray-300 transition text-center">
                    Cancel
                </a>
            </div>
        </form>

        <p class="text-center text-sm text-gray-500 mt-6">
            <i class="bi bi-info-circle"></i> Your application will be reviewed by administrators. You will receive notification once approved.
        </p>
    </div>
</body>
</html>
