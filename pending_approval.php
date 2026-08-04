<?php
/**
 * Pending Approval Page
 * Shown to resource person applicants while their application is being reviewed
 */
session_start();

require_once 'db.php';
require_once 'config/redirects.php';

// Check if user has a pending application
$userId = $_SESSION['temp_user_id'] ?? ($_SESSION['user_id'] ?? null);
if (!$userId) {
    header('Location: login.php');
    exit;
}

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || $user['verification_status'] !== 'pending') {
    // Redirect if not pending
    if ($user && $user['verification_status'] === 'verified') {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'];
        unset($_SESSION['temp_user_id']);
        header('Location: resource_person_dashboard.php');
        exit;
    } elseif ($user && $user['verification_status'] === 'rejected') {
        header('Location: login.php?error=account_rejected');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Pending Approval - PCMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="ASSETS/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <meta http-equiv="refresh" content="30">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-8 text-center border border-slate-200">
        <div class="w-20 h-20 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-6 text-amber-600">
            <i class="bi bi-clock-history text-4xl"></i>
        </div>
        
        <h1 class="text-2xl font-bold text-slate-800 mb-2">Application Under Review</h1>
        <p class="text-slate-500 text-sm mb-6">Your resource person application is currently under review by administrators.</p>
        
        <div class="bg-slate-50 p-4 rounded-xl mb-6 border border-slate-200">
            <h3 class="font-semibold text-slate-700 text-xs uppercase tracking-wider mb-3">Application Details</h3>
            <div class="space-y-2 text-left text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-500">Applicant:</span>
                    <span class="font-medium text-slate-800"><?php echo htmlspecialchars($user['fullname'] ?? 'N/A'); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Email:</span>
                    <span class="font-medium text-slate-800"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Department:</span>
                    <span class="font-medium text-slate-800"><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Status:</span>
                    <span class="font-semibold text-amber-600 inline-flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span> Pending Review
                    </span>
                </div>
            </div>
        </div>
        
        <div class="space-y-3">
            <div class="flex items-center justify-center gap-2 text-xs text-slate-400">
                <i class="bi bi-arrow-repeat animate-spin text-red-600"></i>
                <span>Auto-refreshing status every 30 seconds</span>
            </div>
            
            <button onclick="location.reload()" class="w-full bg-red-600 text-white py-3 px-6 rounded-xl font-semibold text-sm hover:bg-red-700 transition shadow-sm">
                <i class="bi bi-arrow-clockwise mr-1"></i> Check Status Now
            </button>
            
            <a href="logout.php" class="block text-center text-slate-500 hover:text-slate-800 text-xs py-1">
                <i class="bi bi-box-arrow-right mr-1"></i> Sign Out
            </a>
        </div>
    </div>
</body>
</html>
