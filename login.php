<?php
// Load security utilities
require __DIR__ . '/UTILS/security-headers.php';
require __DIR__ . '/UTILS/security.php';
require __DIR__ . '/UTILS/totp-2fa.php';

// Harden session cookie params where possible (respect HTTPS during local dev)
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Check session timeout (30 minutes)
if (!checkSessionTimeout(1800)) {
    session_unset();
    session_destroy();
}

require __DIR__ . '/db.php';
require __DIR__ . '/database/audit-log.php';

$error = "";
$show_2fa_form = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Security token invalid. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate input
        if (empty($email) || empty($password)) {
            $error = "Email and password are required";
        } else {
            // Check rate limiting
            $client_ip = $_SERVER['REMOTE_ADDR'];
            $rate_limit = checkRateLimit($email, 5, 900);
            
            if ($rate_limit['limited']) {
                $locked_until = isset($rate_limit['locked_until']) ? date('H:i:s', $rate_limit['locked_until']) : 'unknown';
                $error = "Account locked due to multiple failed attempts. Try again after " . $locked_until;
            } else {
                $stmt = $conn->prepare("SELECT id, fullname, password, role, email FROM users WHERE email=?");
                if (!$stmt) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($user = $result->fetch_assoc()) {
                        if (password_verify($password, $user['password'])) {
                            // Check if 2FA is enabled for this admin
                            if ($user['role'] === 'admin') {
                                $twofa_status = get2FAStatus($user['id']);
                                if ($twofa_status['enabled']) {
                                    // Require 2FA verification
                                    $_SESSION['pending_2fa_user_id'] = $user['id'];
                                    $_SESSION['pending_2fa_email'] = $user['email'];
                                    $_SESSION['pending_2fa_fullname'] = $user['fullname'];
                                    $_SESSION['pending_2fa_role'] = $user['role'];
                                    $show_2fa_form = true;
                                    // Clear rate limit on successful password entry
                                    clearRateLimit($email);
                                } else {
                                    // 2FA not enabled, proceed with login
                                    $_SESSION['user_id'] = $user['id'];
                                    $_SESSION['fullname'] = $user['fullname'];
                                    $_SESSION['role'] = $user['role'];
                                    $_SESSION['last_activity'] = time();
                                    
                                    // Prevent session fixation attacks
                                    session_regenerate_id(true);
                                    
                                    // Log admin login
                                    logAdminLogin($user['id'], $user['fullname']);
                                    
                                    // Clear rate limit
                                    clearRateLimit($email);
                                    
                                    // Redirect
                                    echo "<script>
                                        localStorage.setItem('isLoggedIn', 'true');
                                        localStorage.setItem('role', 'admin');
                                        window.location.href = 'system-template-full.php';
                                    </script>";
                                    exit();
                                }
                            } else {
                                // Regular user login (no 2FA)
                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['fullname'] = $user['fullname'];
                                $_SESSION['role'] = $user['role'] ?? 'citizen';
                                $_SESSION['last_activity'] = time();
                                
                                session_regenerate_id(true);
                                clearRateLimit($email);
                                
                                echo "<script>
                                    localStorage.setItem('isLoggedIn', 'true');
                                    localStorage.setItem('role', '" . addslashes($user['role'] ?? 'citizen') . "');
                                    window.location.href = 'user-portal.php';
                                </script>";
                                exit();
                            }
                        } else {
                            // Invalid password - record failed attempt
                            recordFailedAttempt($email, 900);
                            $remaining = checkRateLimit($email, 5, 900)['remaining'];
                            $error = "Invalid password. Attempts remaining: $remaining";
                        }
                    } else {
                        // Email not found - still record to prevent enumeration attacks
                        recordFailedAttempt($email, 900);
                        $error = "Email or password incorrect";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Handle 2FA verification
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['verify_2fa_code'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Security token invalid. Please try again.";
    } elseif (!isset($_SESSION['pending_2fa_user_id'])) {
        $error = "2FA session expired. Please login again.";
    } else {
        $code = preg_replace('/[^0-9A-Z]/', '', strtoupper($_POST['2fa_code'] ?? ''));
        
        if (empty($code)) {
            $error = "2FA code is required";
        } elseif (verify2FACode($_SESSION['pending_2fa_user_id'], $code)) {
            // 2FA verified, complete login
            $_SESSION['user_id'] = $_SESSION['pending_2fa_user_id'];
            $_SESSION['fullname'] = $_SESSION['pending_2fa_fullname'];
            $_SESSION['role'] = $_SESSION['pending_2fa_role'];
            $_SESSION['last_activity'] = time();
            
            // Clean up 2FA session vars
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_fullname'], $_SESSION['pending_2fa_role']);
            
            session_regenerate_id(true);
            logAdminLogin($_SESSION['user_id'], $_SESSION['fullname']);
            clearRateLimit($_SESSION['pending_2fa_email'] ?? '');
            
            echo "<script>
                localStorage.setItem('isLoggedIn', 'true');
                localStorage.setItem('role', 'admin');
                window.location.href = 'system-template-full.php';
            </script>";
            exit();
        } else {
            $error = "Invalid 2FA code. Please try again.";
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="ASSETS/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        body {
            letter-spacing: -0.3px;
        }
        h1, h2, h3 {
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fade-in-up { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fade-in 0.6s ease-out forwards; }
        .animate-fade-in-up { animation: fade-in-up 0.6s ease-out forwards; }
        .animation-delay-100 { animation-delay: 100ms; }
        .animation-delay-200 { animation-delay: 200ms; }
        .animation-delay-300 { animation-delay: 300ms; }
        @media screen and (max-width: 640px) {
            input, select, textarea { font-size: 16px !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-red-50 via-white to-red-50 min-h-screen flex items-center justify-center p-3 md:p-4">
    <div class="w-full max-w-md">
        <!-- Logo Section -->
        <div class="text-center mb-6 md:mb-8 animate-fade-in">
            <div class="inline-flex items-center justify-center mb-3 md:mb-4">
                <div class="bg-white rounded-full shadow-xl flex items-center justify-center overflow-hidden" style="width: 100px; height: 100px;">
                    <img src="images/logo.webp" alt="City Government of Valenzuela" class="w-full h-full object-contain p-2">
                </div>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 animate-fade-in-up animation-delay-100">PCMP</h1>
            <p class="text-sm md:text-base text-gray-600 mt-2 animate-fade-in-up animation-delay-200">Public Consultation Management Portal</p>
            <p class="text-xs md:text-sm text-red-600 font-semibold mt-1 animate-fade-in-up animation-delay-300">City Government of Valenzuela</p>
        </div>
        
        <!-- Login Card -->
        <div class="bg-white rounded-xl md:rounded-2xl shadow-xl p-5 md:p-8 animate-fade-in-up">
            <div class="mb-4 md:mb-6 text-center">
                <h2 class="text-xl md:text-2xl font-bold text-gray-800">Welcome Back</h2>
                <p class="text-sm md:text-base text-gray-600 mt-1">Sign in to access your account</p>
            </div>
            
            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="mb-4 px-3 md:px-4 py-2.5 md:py-3 rounded-lg flex items-center text-sm bg-red-50 border border-red-200 text-red-700">
                    <i class="bi bi-exclamation-circle mr-2 flex-shrink-0"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <?php if (!$show_2fa_form): ?>
            <form method="POST" action="login.php" class="space-y-4 md:space-y-5">
                <!-- CSRF Token -->
                <?php outputCSRFField(); ?>
                
                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                        <i class="bi bi-envelope mr-1.5"></i>Email Address
                    </label>
                    <input type="email" id="email" name="email" required placeholder="your.email@lgu.gov.ph"
                           class="w-full px-3 md:px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 transition text-base">
                </div>
                
                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">
                        <i class="bi bi-lock mr-1.5"></i>Password
                    </label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required placeholder="Enter your password"
                               class="w-full px-3 md:px-4 py-2.5 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 transition text-base">
                        <button type="button" id="toggle-password" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 transition">
                            <i class="bi bi-eye text-lg" id="eye-icon"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="remember" class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-2 focus:ring-red-500 cursor-pointer">
                        <span class="ml-2 text-gray-700 font-medium">Remember me</span>
                    </label>
                    <a href="#" class="text-red-600 hover:text-red-700 font-medium transition-colors">Forgot password?</a>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 md:py-3 rounded-lg transition duration-200 shadow-md hover:shadow-lg flex items-center justify-center">
                    <span>Sign In</span>
                    <i class="bi bi-arrow-right ml-2"></i>
                </button>
            </form>
            <?php else: ?>
            <!-- 2FA Verification Form -->
            <form method="POST" action="login.php" class="space-y-4 md:space-y-5">
                <!-- CSRF Token -->
                <?php outputCSRFField(); ?>
                <input type="hidden" name="verify_2fa_code" value="1">
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 md:p-4 mb-4">
                    <div class="flex items-start">
                        <i class="bi bi-shield-check text-blue-600 text-lg mt-0.5 mr-3 flex-shrink-0"></i>
                        <div>
                            <h3 class="text-sm font-semibold text-blue-900">Two-Factor Authentication</h3>
                            <p class="text-xs text-blue-700 mt-1">Enter the 6-digit code from your authenticator app or use a backup code.</p>
                        </div>
                    </div>
                </div>
                
                <!-- 2FA Code Input -->
                <div>
                    <label for="2fa_code" class="block text-sm font-medium text-gray-700 mb-1.5">
                        <i class="bi bi-phone mr-1.5"></i>Authenticator Code
                    </label>
                    <input type="text" id="2fa_code" name="2fa_code" required placeholder="000000"
                           maxlength="8" pattern="[0-9A-Z]{6,8}"
                           class="w-full px-3 md:px-4 py-2.5 text-center text-lg tracking-widest border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 transition text-base font-mono"
                           autocomplete="off">
                    <p class="text-xs text-gray-500 mt-1">Backup codes also accepted (8 characters)</p>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 md:py-3 rounded-lg transition duration-200 shadow-md hover:shadow-lg flex items-center justify-center">
                    <span>Verify Code</span>
                    <i class="bi bi-check-circle ml-2"></i>
                </button>
                
                <!-- Back to Login -->
                <button type="button" onclick="window.location.href='login.php'" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2.5 md:py-3 rounded-lg transition duration-200">
                    <i class="bi bi-arrow-left mr-2"></i>Back to Login
                </button>
            </form>
            <?php endif; ?>
            
            <!-- Divider -->
            <div class="relative my-5 md:my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white text-gray-500 font-medium">Or continue with</span>
                </div>
            </div>
            
            <!-- Guest Access Button -->
            <a href="public-portal.php" class="w-full flex items-center justify-center px-4 py-2.5 border-2 border-red-600 rounded-lg hover:bg-red-50 transition font-medium text-red-600 mb-3">
                <i class="bi bi-globe text-lg mr-2"></i>
                <span class="text-sm">View Public Consultations</span>
            </a>
            
            <!-- Alternative Login -->
            <button type="button" class="w-full flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 transition font-medium">
                <i class="bi bi-google text-lg mr-2 text-red-500"></i>
                <span class="text-sm text-gray-700">Google</span>
            </button>
            
            <!-- Register Link -->
            <div class="mt-5 md:mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Looking to submit feedback? 
                    <a href="public-portal.php" class="text-red-600 hover:text-red-700 font-semibold transition-colors">Use Public Portal</a>
                </p>
            </div>
        </div>
        
        <!-- Footer Info -->
        <div class="mt-6 md:mt-8 text-center text-xs md:text-sm text-gray-600">
            <p>&copy; 2025 City Government of Valenzuela. All rights reserved.</p>
            <div class="mt-2 space-x-2 md:space-x-4 text-xs md:text-sm">
                <a href="#" class="hover:text-red-600 transition-colors">Privacy Policy</a>
                <span>•</span>
                <a href="#" class="hover:text-red-600 transition-colors">Terms of Service</a>
                <span>•</span>
                <a href="#" class="hover:text-red-600 transition-colors">Help</a>
            </div>
        </div>
    </div>
    
    <script>
        // Handle Google Login
        function handleGoogleLogin(response) {
            const credential = response.credential;
            
            // Send token to backend
            const formData = new FormData();
            formData.append('action', 'google_login');
            formData.append('token', credential);
            
            fetch('AUTH/google_auth.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('role', data.role || 'citizen');
                    window.location.href = data.redirect || 'user-portal.php';
                } else {
                    alert('Login failed: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred during login');
            });
        }

        window.onload = function() {
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
        };
    </script>
</body>
</html>
