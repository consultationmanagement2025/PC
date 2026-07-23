<?php
/**
 * Pending Approval Page
 * Shown to resource person applicants while their application is being reviewed
 */
session_start();

// Check if user has a pending application
if (!isset($_SESSION['temp_user_id'])) {
    header('Location: ../public/index.php');
    exit;
}

require_once 'db.php';

$userId = $_SESSION['temp_user_id'];

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || $user['approval_status'] !== 'pending') {
    // Redirect if not pending
    if ($user['approval_status'] === 'approved') {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'];
        unset($_SESSION['temp_user_id']);
        header('Location: resource_person_dashboard.php');
        exit;
    } elseif ($user['approval_status'] === 'rejected') {
        header('Location: ../public/index.php?error=account_rejected');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Pending Approval</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <meta http-equiv="refresh" content="30">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-8 text-center">
        <div class="w-24 h-24 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="bi bi-clock-history text-5xl text-yellow-600"></i>
        </div>
        
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Application Pending</h1>
        <p class="text-gray-600 mb-6">Your resource person application is currently under review by administrators.</p>
        
        <div class="bg-gray-50 p-4 rounded-lg mb-6">
            <h3 class="font-semibold text-gray-900 mb-2">Application Details</h3>
            <div class="space-y-2 text-left">
                <div class="flex justify-between">
                    <span class="text-gray-600">Name:</span>
                    <span class="font-medium"><?php echo htmlspecialchars($user['fullname']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Email:</span>
                    <span class="font-medium"><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Department:</span>
                    <span class="font-medium"><?php echo htmlspecialchars($user['department']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Status:</span>
                    <span class="font-medium text-yellow-600">Pending Review</span>
                </div>
            </div>
        </div>
        
        <div class="space-y-3">
            <div class="flex items-center justify-center gap-2 text-sm text-gray-500">
                <i class="bi bi-arrow-repeat animate-spin"></i>
                <span>This page will automatically refresh every 30 seconds</span>
            </div>
            
            <button onclick="location.reload()" class="w-full bg-red-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-red-700 transition">
                <i class="bi bi-arrow-clockwise mr-2"></i> Check Status Now
            </button>
            
            <a href="logout.php" class="block text-center text-gray-600 hover:text-gray-800 text-sm">
                Sign Out
            </a>
        </div>
        
        <p class="text-xs text-gray-500 mt-6">
            You will receive an email notification once your application is approved or rejected.
        </p>
    </div>
</body>
</html>
