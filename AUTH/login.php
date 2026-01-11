<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/audit-log.php';
require_once __DIR__ . '/../DATABASE/user-logs.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, fullname, password, role FROM users WHERE email=?");
    if (!$stmt) {
        error_log("Login prepare error: " . $conn->error);
        $error = "An internal error occurred";
    } else {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'] ?? 'citizen'; // Default to citizen if role is NULL
                session_regenerate_id(true);
                
                // Log user login
                $userRole = $user['role'] ?? 'citizen';
                logUserAction($user['id'], $user['fullname'], 'login', 'authentication', 'user', $user['id'], 'User logged in', 'success');
                
                // Log admin login in audit log
                if ($userRole === 'admin') {
                    logAdminLogin($user['id'], $user['fullname']);
                }
                
                // Redirect based on user role
                $redirectUrl = ($userRole === 'admin') ? "system-template-full.php" : "user-portal.php";

                echo "<script>
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('role', '" . addslashes($userRole) . "');
                    window.location.href = '$redirectUrl';
                </script>";
                exit();
            } else {
                $error = "Invalid password";
                // Log failed login attempt
                logUserAction(null, $email, 'login', 'authentication', 'user', null, 'Failed login attempt', 'failure');
            }
        } else {
            $error = "Email not found";
            // Log failed login attempt
            logUserAction(null, $email, 'login', 'authentication', 'user', null, 'Login attempt with unknown email', 'failure');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#dc2626">
    <title>Login - PCMP | City of Valenzuela</title>
    <link rel="icon" type="image/webp" href="images/logo.webp">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fade-in-up { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fade-in 0.6s ease-out forwards; }
        .animate-fade-in-up { animation: fade-in-up 0.6s ease-out forwards; }
        .animation-delay-100 { animation-delay: 100ms; }
        .animation-delay-200 { animation-delay: 200ms; }
        .animation-delay-300 { animation-delay: 300ms; }
        @media screen and (max-width: 767px) {
            input, select, textarea { font-size: 16px !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-red-50 via-white to-red-50 min-h-screen flex items-center justify-center p-3 md:p-4">
    <div class="w-full max-w-md">
        <!-- Logo Section -->
        <div class="text-center mb-6 md:mb-8 animate-fade-in">
            <div class="inline-flex items-center justify-center mb-3 md:mb-4">
                <div class="bg-white rounded-full shadow-xl flex items-center justify-center overflow-hidden" style="width: 120px; height: 120px;">
                    <img src="images/logo.webp" alt="City Government of Valenzuela" class="w-full h-full object-contain p-2">
                </div>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 animate-fade-in-up animation-delay-100">PCMP</h1>
            <p class="text-sm md:text-base text-gray-600 mt-1 md:mt-2 animate-fade-in-up animation-delay-200">Public Consultation Management Portal</p>
            <p class="text-xs md:text-sm text-red-600 font-semibold mt-1 animate-fade-in-up animation-delay-300">City Government of Valenzuela</p>
        </div>
        
        <!-- Login Card -->
        <div class="bg-white rounded-xl md:rounded-2xl shadow-xl p-5 md:p-8 animate-fade-in-up transform hover:shadow-2xl transition-all duration-300">
            <div class="mb-4 md:mb-6 text-center">
                <h2 class="text-xl md:text-2xl font-bold text-gray-800">Welcome Back</h2>
                <p class="text-sm md:text-base text-gray-600 mt-1">Sign in to access your account</p>
            </div>
            
            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="mb-4 px-3 md:px-4 py-2 md:py-3 rounded-lg flex items-center text-sm bg-red-50 border border-red-200 text-red-700">
                    <i class="bi bi-exclamation-circle mr-2"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" action="login.php" class="space-y-4 md:space-y-5">
                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1 md:mb-2">
                        <i class="bi bi-envelope mr-1"></i>Email Address
                    </label>
                    <input type="email" id="email" name="email" required placeholder="your.email@lgu.gov.ph"
                           class="w-full px-3 md:px-4 py-2.5 md:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 transition text-base">
                </div>
                
                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1 md:mb-2">
                        <i class="bi bi-lock mr-1"></i>Password
                    </label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required placeholder="Enter your password"
                               class="w-full px-3 md:px-4 py-2.5 md:py-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 transition text-base">
                        <button type="button" id="toggle-password" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                            <i class="bi bi-eye text-lg" id="eye-icon"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="remember" class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-2 focus:ring-red-500 cursor-pointer">
                        <span class="ml-2 text-sm text-gray-700">Remember me</span>
                    </label>
                    <a href="#" class="text-sm text-red-600 hover:text-red-700 font-medium transition-colors">Forgot password?</a>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 md:py-3 rounded-lg transition duration-200 ease-in-out shadow-md hover:shadow-lg flex items-center justify-center">
                    <span>Sign In</span>
                    <i class="bi bi-arrow-right ml-2"></i>
                </button>
            </form>
            
            <!-- Divider -->
            <div class="relative my-5 md:my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white text-gray-500">Or continue with</span>
                </div>
            </div>
            
            <!-- Alternative Login -->
            <div class="grid grid-cols-2 gap-3">
        
                <button type="button" class="flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    <i class="bi bi-google text-lg mr-2 text-red-500"></i>
                    <span class="text-sm font-medium text-gray-700">Google</span>
                </button>
            </div>
            
            <!-- Register Link -->
            <div class="mt-5 md:mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Don't have an account? 
                    <a href="register.php" class="text-red-600 hover:text-red-700 font-semibold transition-colors">Create Account</a>
                </p>
            </div>
        </div>
        
        <!-- Footer Info -->
        <div class="mt-6 md:mt-8 text-center text-xs md:text-sm text-gray-600">
            <p>&copy; 2025 City Government of Valenzuela. All rights reserved.</p>
            <div class="mt-2 space-x-2 md:space-x-4">
                <a href="#" class="hover:text-red-600 transition-colors">Privacy Policy</a>
                <span>•</span>
                <a href="#" class="hover:text-red-600 transition-colors">Terms of Service</a>
                <span>•</span>
                <a href="#" class="hover:text-red-600 transition-colors">Help</a>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        document.getElementById('toggle-password')?.addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.classList.remove('bi-eye');
                eyeIcon.classList.add('bi-eye-slash');
            } else {
                passwordField.type = 'password';
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
            }
        });

        // Auto-focus email
        document.getElementById('email').focus();
    </script>
</body>
</html>
