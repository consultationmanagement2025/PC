<?php
require_once '../config.php';
require_once '../db.php';

// Start session
session_start();

$error = '';
$success = '';
$token_valid = false;

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $error = 'Invalid or missing reset token.';
} else {
    $token = $_GET['token'];
    $token_hash = hash('sha256', $token);
    
    try {
        // Connect to database using the working connection
        $conn = dbConnect();
        if (!$conn) {
            throw new Exception('Database connection failed.');
        }
        
        // Check if token exists and is not expired
        $stmt = $conn->prepare("SELECT id, email, reset_expires FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = 'Invalid or expired reset token. Please request a new password reset.';
        } else {
            $user = $result->fetch_assoc();
            $token_valid = true;
            
            // Handle form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
                $new_password = trim($_POST['password'] ?? '');
                $confirm_password = trim($_POST['confirm_password'] ?? '');
                
                // Validate passwords
                if (empty($new_password)) {
                    $error = 'Please enter a new password.';
                } elseif (strlen($new_password) < 6) {
                    $error = 'Password must be at least 6 characters long.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'Passwords do not match.';
                } else {
                    // Hash new password
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password and clear reset token
                    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
                    $stmt->bind_param("si", $password_hash, $user['id']);
                    
                    if ($stmt->execute()) {
                        $success = 'Password has been reset successfully. You can now login with your new password.';
                        $token_valid = false; // Hide form after success
                    } else {
                        $error = 'Failed to reset password. Please try again.';
                    }
                }
            }
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        $error = 'An error occurred. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Valenzuela City Government</title>
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
        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #10b981; width: 100%; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md">
        <!-- Logo and Title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-red-600 rounded-full mb-4">
                <i class="bi bi-lock text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-white mb-1">Valenzuela City Government</h1>
            <p class="text-gray-300 text-sm">Public Consultation Portal</p>
        </div>

        <!-- Reset Password Card -->
        <div class="glass-effect rounded-xl shadow-2xl p-8">
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-center">
                    <i class="bi bi-exclamation-circle text-red-600 mr-3 flex-shrink-0"></i>
                    <p class="text-red-800 text-sm"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-full bg-green-100 mb-4">
                        <i class="bi bi-check-circle text-green-600 text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Password Reset Successful!</h2>
                    <p class="text-gray-600 mb-6">Your password has been reset successfully. You can now login with your new password.</p>
                    <a href="login.php" class="inline-flex items-center px-6 py-2.5 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition-colors">
                        <i class="bi bi-box-arrow-in-right mr-2"></i>
                        Back to Login
                    </a>
                </div>
            <?php elseif ($token_valid): ?>
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-1">Create New Password</h2>
                    <p class="text-gray-600 text-sm">Enter a new password for your account.</p>
                </div>

                <form method="POST" id="resetPasswordForm" onsubmit="return validatePasswordForm()">
                    <input type="hidden" name="action" value="reset_password">
                    
                    <!-- New Password -->
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">
                            <i class="bi bi-lock mr-1.5"></i>New Password
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   required
                                   minlength="6"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                                   placeholder="Enter new password"
                                   oninput="checkPasswordStrength()">
                            <button type="button" 
                                    onclick="togglePassword('password', 'passwordEye')"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 transition">
                                <i id="passwordEye" class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <div id="passwordStrength" class="password-strength"></div>
                            <p id="strengthText" class="text-xs text-gray-500 mt-1"></p>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-6">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1.5">
                            <i class="bi bi-lock-check mr-1.5"></i>Confirm Password
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required
                                   minlength="6"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                                   placeholder="Confirm new password"
                                   oninput="checkPasswordMatch()">
                            <button type="button" 
                                    onclick="togglePassword('confirm_password', 'confirmEye')"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 transition">
                                <i id="confirmEye" class="bi bi-eye"></i>
                            </button>
                        </div>
                        <p id="matchText" class="text-xs mt-1"></p>
                    </div>

                    <!-- Password Requirements -->
                    <div class="mb-6 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-xs font-medium text-blue-900 mb-2"><i class="bi bi-info-circle mr-1"></i>Password Requirements:</p>
                        <ul class="text-xs text-blue-800 space-y-1">
                            <li><i id="req-length" class="bi bi-circle text-gray-400 mr-1"></i>At least 6 characters</li>
                        </ul>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" 
                            class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 rounded-lg transition duration-200 shadow-md hover:shadow-lg flex items-center justify-center">
                        <i class="bi bi-shield-check mr-2"></i>Reset Password
                    </button>

                    <!-- Back to Login -->
                    <a href="login.php" class="block text-center mt-3 text-gray-600 hover:text-red-600 text-sm font-medium transition">
                        ← Back to Login
                    </a>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-full bg-red-100 mb-4">
                        <i class="bi bi-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 mb-2">Invalid Reset Link</h2>
                    <p class="text-gray-600 text-sm mb-6"><?php echo htmlspecialchars($error); ?></p>
                    <div class="space-y-2">
                        <a href="login.php" class="block w-full px-4 py-2.5 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition text-center">
                            <i class="bi bi-box-arrow-in-right mr-2"></i>Back to Login
                        </a>
                        <a href="login.php?forgot=true" class="block w-full px-4 py-2.5 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition text-center">
                            <i class="bi bi-arrow-counterclockwise mr-2"></i>Request New Link
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-xs text-gray-400">
            <p>&copy; 2025 City Government of Valenzuela. All rights reserved.</p>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, eyeId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(eyeId);
            
            if (field.type === 'password') {
                field.type = 'text';
                eye.classList.remove('bi-eye');
                eye.classList.add('bi-eye-slash');
            } else {
                field.type = 'password';
                eye.classList.remove('bi-eye-slash');
                eye.classList.add('bi-eye');
            }
        }

        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthEl = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('strengthText');
            const lengthReq = document.getElementById('req-length');
            
            let strength = 0;
            let text = '';
            let strengthClass = '';

            // Check length
            if (password.length >= 6) {
                strength += 1;
                lengthReq.classList.remove('bi-circle');
                lengthReq.classList.add('bi-check-circle', 'text-green-600');
            } else {
                lengthReq.classList.remove('bi-check-circle', 'text-green-600');
                lengthReq.classList.add('bi-circle', 'text-gray-400');
            }

            if (password.length > 0) {
                if (strength === 1) {
                    strengthClass = 'strength-weak';
                    text = 'Weak password';
                } else {
                    strengthClass = 'strength-strong';
                    text = 'Strong password';
                }
            }

            strengthEl.className = 'password-strength ' + strengthClass;
            strengthText.textContent = text;
        }

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('matchText');
            const submitBtn = document.querySelector('button[type="submit"]');

            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    matchText.className = 'text-xs mt-1 text-green-600 flex items-center';
                    matchText.innerHTML = '<i class="bi bi-check-circle mr-1"></i>Passwords match';
                    submitBtn.disabled = false;
                } else {
                    matchText.className = 'text-xs mt-1 text-red-600 flex items-center';
                    matchText.innerHTML = '<i class="bi bi-exclamation-circle mr-1"></i>Passwords do not match';
                    submitBtn.disabled = true;
                }
            } else {
                matchText.textContent = '';
                submitBtn.disabled = false;
            }
        }

        function validatePasswordForm() {
            const password = document.getElementById('password').value.trim();
            const confirmPassword = document.getElementById('confirm_password').value.trim();

            if (password.length < 6) {
                alert('Password must be at least 6 characters long.');
                return false;
            }

            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                return false;
            }

            return true;
        }

        // Auto focus
        window.addEventListener('load', function() {
            document.getElementById('password')?.focus();
        });
    </script>
</body>
</html>
