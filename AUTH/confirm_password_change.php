<?php
require_once '../config.php';
require_once '../db.php';

session_start();

$error = '';
$success = false;
$confirmation_token = '';

// Get token from URL
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    $token_hash = hash('sha256', $token);

    error_log("Debug - Raw token: " . $token);
    error_log("Debug - Hashed token: " . $token_hash);

    try {
        $conn = dbConnect();
        if (!$conn) {
            throw new Exception('Database connection failed.');
        }

        $stmt = $conn->prepare("SELECT id, email FROM users WHERE reset_token = ?");
        if (!$stmt) {
            error_log("Debug - Prepare error: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }

        error_log("Debug - Prepared statement created successfully");
        $stmt->bind_param("s", $token_hash);
        error_log("Debug - Param bound");

        if (!$stmt->execute()) {
            error_log("Debug - Execute error: " . $stmt->error);
            throw new Exception('Execute error: ' . $stmt->error);
        }

        error_log("Debug - Query executed");
        $result = $stmt->get_result();
        error_log("Debug - Result obtained, rows: " . ($result ? $result->num_rows : 'NULL'));

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            error_log("Debug - User found: " . $user['email']);

            $check_stmt = $conn->prepare("SELECT reset_expires FROM users WHERE id = ? AND reset_expires > NOW()");
            if ($check_stmt) {
                $check_stmt->bind_param("i", $user['id']);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                error_log("Debug - Expiration check returned: " . $check_result->num_rows . " rows");

                if ($check_result->num_rows === 0) {
                    error_log('Token expired');
                    $error = 'Token has expired.';
                } else {
                    error_log('Token valid and not expired');
                    $success = true;
                    $confirmation_token = $token;

                    $_SESSION['password_change_confirmed'] = true;
                    $_SESSION['confirmed_user_id'] = $user['id'];
                    $_SESSION['confirmed_user_email'] = $user['email'];
                }

                $check_stmt->close();
            }
        } else {
            $error = 'Invalid or expired confirmation link.';
        }

        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        error_log("Confirmation error: " . $e->getMessage());
        $error = 'An error occurred. Please try again.';
    }
}

// If successful, redirect to login with confirmation token
if ($success) {
    header("Location: ../login.php?change_password=" . urlencode($confirmation_token));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Change Confirmation - Valenzuela City Government</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            min-height: 100vh;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-red-600 rounded-full mb-4">
                <i class="bi bi-exclamation-triangle text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-white mb-1">Valenzuela City Government</h1>
            <p class="text-gray-300 text-sm">Public Consultation Portal</p>
        </div>

        <div class="glass-effect rounded-xl shadow-2xl p-8">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-full bg-red-100 mb-4">
                    <i class="bi bi-exclamation-circle text-red-600 text-2xl"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Invalid Confirmation Link</h2>
                <p class="text-gray-600 text-sm mb-6"><?php echo htmlspecialchars($error); ?></p>
                <div class="space-y-2">
                    <a href="../login.php" class="block w-full px-4 py-2.5 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition text-center">
                        <i class="bi bi-box-arrow-in-right mr-2"></i>Back to Login
                    </a>
                    <a href="../login.php" class="block w-full px-4 py-2.5 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition text-center" onclick="openForgotPasswordModal(event)">
                        <i class="bi bi-arrow-counterclockwise mr-2"></i>Request New Link
                    </a>
                </div>
            </div>
        </div>

        <div class="mt-8 text-center text-xs text-gray-400">
            <p>&copy; 2025 City Government of Valenzuela. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
