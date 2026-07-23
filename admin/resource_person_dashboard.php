<?php
/**
 * Resource Person Dashboard
 * Dashboard for approved resource persons to view consultations and provide expert responses
 */
session_start();
require_once 'db.php';
require_once 'session_check.php';

// Check if user is logged in and is a resource person
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if (!in_array($current_role, ['resource person', 'resource_person'], true)) {
    header('Location: ../public/index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'] ?? 'Resource Person';
$email = $_SESSION['email'] ?? '';

// Get user's expertise areas
$stmt = $conn->prepare("SELECT expertise_areas, department FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$expertise_areas = $user['expertise_areas'] ?? '';
$department = $user['department'] ?? '';

// Get active consultations
$consultations_stmt = $conn->prepare("SELECT * FROM consultations WHERE status IN ('active', 'scheduled') ORDER BY created_at DESC LIMIT 10");
$consultations_stmt->execute();
$consultations_result = $consultations_stmt->get_result();
$consultations = [];
while ($row = $consultations_result->fetch_assoc()) {
    $consultations[] = $row;
}
$consultations_stmt->close();

// Get consultation stats
$stats_stmt = $conn->prepare("SELECT 
    COUNT(*) as total_consultations,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_consultations,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_consultations
    FROM consultations");
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Person Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-red-600 to-red-800 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <i class="bi bi-person-badge text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold">Resource Person Dashboard</h1>
                        <p class="text-red-100 text-sm"><?php echo htmlspecialchars($fullname); ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-sm text-red-100">Department</p>
                        <p class="font-medium"><?php echo htmlspecialchars($department); ?></p>
                    </div>
                    <a href="logout.php" class="bg-white bg-opacity-20 hover:bg-opacity-30 px-4 py-2 rounded-lg transition">
                        <i class="bi bi-box-arrow-right mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Consultations</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_consultations'] ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="bi bi-chat-dots text-2xl text-red-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Active Consultations</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['active_consultations'] ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="bi bi-lightning text-2xl text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Completed Consultations</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['completed_consultations'] ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="bi bi-check-circle text-2xl text-blue-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expertise Areas -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Your Expertise Areas</h2>
            <div class="flex flex-wrap gap-2">
                <?php 
                $areas = array_map('trim', explode(',', $expertise_areas));
                foreach ($areas as $area): 
                    if (!empty($area)):
                ?>
                    <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium">
                        <?php echo htmlspecialchars($area); ?>
                    </span>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
        </div>

        <!-- Recent Consultations -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Recent Consultations</h2>
                <a href="#" class="text-red-600 hover:text-red-700 text-sm font-medium">View All</a>
            </div>
            
            <?php if (empty($consultations)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="bi bi-inbox text-4xl mb-2"></i>
                    <p>No consultations available</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($consultations as $consultation): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($consultation['title']); ?></h3>
                                <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded">
                                    <?php echo htmlspecialchars($consultation['status']); ?>
                                </span>
                            </div>
                            <p class="text-gray-600 text-sm mb-3 line-clamp-2">
                                <?php echo htmlspecialchars(substr($consultation['description'], 0, 200)) . '...'; ?>
                            </p>
                            <div class="flex justify-between items-center text-sm text-gray-500">
                                <span>
                                    <i class="bi bi-calendar mr-1"></i>
                                    <?php echo date('M d, Y', strtotime($consultation['created_at'])); ?>
                                </span>
                                <button class="text-red-600 hover:text-red-700 font-medium">
                                    View Details →
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-400 text-sm">Public Consultation Management Portal - Resource Person Dashboard</p>
        </div>
    </footer>
</body>
</html>
