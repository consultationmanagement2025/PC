<?php
// Load security utilities
require __DIR__ . '/UTILS/security-headers.php';
require __DIR__ . '/UTILS/security.php';
require __DIR__ . '/UTILS/totp-2fa.php';
require __DIR__ . '/UTILS/session-timeout.php';

// Harden session cookie params where possible (respect HTTPS during local dev)
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session timeout (30 minutes)
if (!checkSessionTimeout(1800)) {
    session_unset();
    session_destroy();
}

require __DIR__ . '/db.php';
require __DIR__ . '/DATABASE/audit-log.php';
require_once __DIR__ . '/email_config.php';

$error = "";
$show_2fa_form = false;
$show_email_verification = false;
$otp_enabled = false; // Set to true to re-enable email/TOTP verification steps.

function normalizeUserRole($role) {
    $normalized = strtolower(trim((string)$role));
    $normalized = str_replace('_', ' ', $normalized);
    if ($normalized === 'administrator') {
        return 'admin';
    }
    return $normalized;
}

// Handle session timeout message
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $error = "Your session has expired due to inactivity. Please log in again.";
}

// Check if user confirmed password change
if (isset($_GET['change_password']) && !empty($_GET['change_password'])) {
    $show_password_reset_form = true;
    $confirmed_token = $_GET['change_password'];
    $_SESSION['password_change_token'] = $confirmed_token;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Debug logging disabled for production
    // error_log("POST request received. POST data: " . print_r($_POST, true));
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Security token invalid. Please try again.";
        // error_log("CSRF token validation failed");
    } else {
        // error_log("CSRF token validated successfully");
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $verification_code = $_POST['verification_code'] ?? '';
        $request_code = $_POST['request_code'] ?? '';
        
        // Handle email verification code submission
        error_log("Checking verify_email_code condition: " . (isset($_POST['verify_email_code']) ? 'SET' : 'NOT SET'));
        if (isset($_POST['verify_email_code'])) {
            $submitted_code = trim($_POST['email_verification_code'] ?? '');
            error_log("Verification code submitted: " . $submitted_code);
            error_log("Expected code: " . ($_SESSION['login_verification_code'] ?? 'none'));
            error_log("Code matches: " . ($submitted_code === $_SESSION['login_verification_code'] ? 'YES' : 'NO'));
            
            if (!isset($_SESSION['login_verification_code']) || !isset($_SESSION['login_verification_expiry'])) {
                error_log("No active verification session");
                $error = "No active email verification request.";
            } elseif (time() > $_SESSION['login_verification_expiry']) {
                error_log("Verification code expired");
                $error = "Verification code expired. Please request a new code.";
                unset($_SESSION['login_verification_code'], $_SESSION['login_verification_expiry'], $_SESSION['pending_login_user']);
            } elseif ($submitted_code !== $_SESSION['login_verification_code']) {
                error_log("Invalid verification code");
                $error = "Invalid verification code. Please try again.";
                $_SESSION['verification_attempts'] = ($_SESSION['verification_attempts'] ?? 0) + 1;
                if ($_SESSION['verification_attempts'] >= 3) {
                    unset($_SESSION['login_verification_code'], $_SESSION['login_verification_expiry'], $_SESSION['pending_login_user']);
                    $error = "Too many failed attempts. Please try logging in again.";
                }
            } else {
                // Verification successful - proceed with login
                error_log("Email verification successful for user: " . ($_SESSION['pending_login_user']['email'] ?? 'unknown'));
                $user = $_SESSION['pending_login_user'];
                error_log("User data before session create: " . print_r($user, true));
                
                unset($_SESSION['login_verification_code'], $_SESSION['login_verification_expiry'], $_SESSION['pending_login_user'], $_SESSION['verification_attempts']);
                
                // Create login session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = normalizeUserRole($user['role']);  // Normalize role for consistency
                $_SESSION['login_time'] = time();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                error_log("Session created successfully. User ID: " . $_SESSION['user_id'] . ", Role: " . $_SESSION['role']);
                
                // Log successful login
                logAction($user['id'], $user['fullname'], "User Login", "user", $user['id'], null, null, 'success', "Email verified login from IP: " . $_SERVER['REMOTE_ADDR']);
                
                // Redirect based on role (normalize role names to catch variants like 'super admin' or 'barangay staff')
                error_log("Redirecting user with role: " . ($user['role'] ?? 'unknown'));
                $roleNorm = strtolower(str_replace([' ', '_'], '', ($user['role'] ?? '')));
                // Admins and staff should land in the system template (app/dashboard area).
                // Citizens and public users go to the public landing page.
                if (in_array($roleNorm, ['admin', 'administrator', 'superadmin', 'staff', 'barangaystaff', 'barangay'], true)) {
                    error_log("Redirecting admin/staff to: system-template-full.php");
                    header("Location: system-template-full.php");
                } else {
                    error_log("Redirecting user to: index.php");
                    header("Location: index.php");
                }
                exit;
            }
        } elseif (isset($_POST['resend_code'])) {
            // Resend verification code with rate limiting
            if (isset($_SESSION['pending_login_user']) && isset($_SESSION['login_verification_expiry'])) {
                // Check rate limiting for code requests
                $current_time = time();
                $code_requests = $_SESSION['verification_code_requests'] ?? [];
                
                // Clean old requests (older than 30 minutes)
                $code_requests = array_filter($code_requests, function($timestamp) use ($current_time) {
                    return ($current_time - $timestamp) < 1800; // 30 minutes
                });
                
                // Check if exceeded limit (5 codes in 30 minutes)
                if (count($code_requests) >= 5) {
                    $oldest_request = min($code_requests);
                    $time_remaining = 1800 - ($current_time - $oldest_request);
                    $minutes = ceil($time_remaining / 60);
                    $error = "Too many verification code requests. Please wait $minutes minutes before requesting another code.";
                } else {
                    // Add current request to tracking
                    $code_requests[] = $current_time;
                    $_SESSION['verification_code_requests'] = $code_requests;
                    
                    if ($current_time < $_SESSION['login_verification_expiry']) {
                        $user = $_SESSION['pending_login_user'];
                        $verification_code = sprintf("%06d", rand(0, 999999));
                        $_SESSION['login_verification_code'] = $verification_code;
                        $_SESSION['login_verification_expiry'] = time() + 300;
                        
                        // Send verification email
                        $subject = "Login Verification Code - Valenzuela City System";
                        $body = "Your login verification code is: <h2 style='font-size: 24px; font-weight: bold; color: #007bff;'>$verification_code</h2>";
                        $body .= "<p>This code will expire in 5 minutes.</p>";
                        $body .= "<p>Requests remaining: " . (5 - count($code_requests)) . " in the next 30 minutes.</p>";
                        $body .= "<p>If you did not request this, please secure your account immediately.</p>";
                        
                        if (sendGmailEmail($user['email'], $subject, $body, true)) {
                            $requests_left = 5 - count($code_requests);
                            $success = "A new verification code has been sent to " . htmlspecialchars($user['email']) . ". ($requests_left requests remaining in 30 minutes)";
                        } else {
                            $error = "Failed to send verification email. Please try again.";
                        }
                    } else {
                        $error = "Previous code expired. Please log in again.";
                        unset($_SESSION['login_verification_code'], $_SESSION['login_verification_expiry'], $_SESSION['pending_login_user']);
                    }
                }
            } else {
                $error = "No active verification request. Please log in again.";
            }
        } else {
            // Regular login attempt
            $proceed_with_login = true;
        }
        
        // Validate input
        if (empty($email) || empty($password)) {
            $error = "Email and password are required";
        } elseif (isset($proceed_with_login) && $proceed_with_login) {
            // Check rate limiting
            $client_ip = $_SERVER['REMOTE_ADDR'];
            $rate_limit = checkRateLimit($email, 5, 900);
            
            if ($rate_limit['limited']) {
                $locked_until = isset($rate_limit['locked_until']) ? date('H:i:s', $rate_limit['locked_until']) : 'unknown';
                $error = "Account locked due to multiple failed attempts. Try again after " . $locked_until;
            } else {
                $stmt = $conn->prepare("SELECT id, fullname, password, role, email FROM users WHERE email=? OR username=?");
                if (!$stmt) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param("ss", $email, $email);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($user = $result->fetch_assoc()) {
                        if (password_verify($password, $user['password'])) {
                            $normalized_db_role = normalizeUserRole($user['role'] ?? '');
                            if (!in_array($normalized_db_role, ['admin', 'super admin'], true)) {
                                $error = "This login is for Admin or Super Admin accounts only.";
                            } else {
                                $user['role'] = $normalized_db_role;

                            if (!$otp_enabled) {
                                // OTP disabled: complete login immediately after password verification.
                                session_regenerate_id(true);
                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['fullname'] = $user['fullname'];
                                $_SESSION['email'] = $user['email'];
                                $_SESSION['role'] = $normalized_db_role;
                                $_SESSION['login_time'] = time();
                                $_SESSION['last_activity'] = time();
                                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                                clearRateLimit($email);
                                logAction($user['id'], $user['fullname'], "User Login", "user", $user['id'], null, null, 'success', "Password-only login from IP: " . ($_SERVER['REMOTE_ADDR'] ?? ''));

                                if (in_array($normalized_db_role, ['admin', 'super admin', 'administrator', 'staff', 'barangay staff', 'barangay'], true)) {
                                    header("Location: system-template-full.php");
                                } else {
                                    header("Location: index.php");
                                }
                                exit;
                            } else {
                                // Password correct - send email verification code
                                $verification_code = sprintf("%06d", rand(0, 999999));
                                $_SESSION['login_verification_code'] = $verification_code;
                                $_SESSION['login_verification_expiry'] = time() + 60; // 1 minute
                                $_SESSION['pending_login_user'] = $user;
                                $_SESSION['verification_attempts'] = 0;
                                
                                // Check rate limiting for code requests
                                $current_time = time();
                                $code_requests = $_SESSION['verification_code_requests'] ?? [];
                                
                                // Clean old requests (older than 30 minutes)
                                $code_requests = array_filter($code_requests, function($timestamp) use ($current_time) {
                                    return ($current_time - $timestamp) < 1800; // 30 minutes
                                });
                                
                                // Check if exceeded limit (5 codes in 30 minutes)
                                if (count($code_requests) >= 5) {
                                    $oldest_request = min($code_requests);
                                    $time_remaining = 1800 - ($current_time - $oldest_request);
                                    $minutes = ceil($time_remaining / 60);
                                    $error = "Too many verification code requests. Please wait $minutes minutes before requesting another code.";
                                    unset($_SESSION['login_verification_code'], $_SESSION['login_verification_expiry'], $_SESSION['pending_login_user']);
                                } else {
                                    // Add current request to tracking
                                    $code_requests[] = $current_time;
                                    $_SESSION['verification_code_requests'] = $code_requests;
                                    
                                    // Send verification email
                                    $subject = "Login Verification Code - Valenzuela City System";
                                    $body = "Your login verification code is: <h2 style='font-size: 24px; font-weight: bold; color: #007bff;'>$verification_code</h2>";
                                    $body .= "<p>This code will expire in 1 minute.</p>";
                                    $body .= "<p>Requests remaining: " . (5 - count($code_requests)) . " in the next 30 minutes.</p>";
                                    $body .= "<p>If you did not request this, please secure your account immediately.</p>";
                                    
                                    if (sendGmailEmail($user['email'], $subject, $body, true)) {
                                        $show_email_verification = true;
                                        $requests_left = 5 - count($code_requests);
                                        $verification_sent = "A verification code has been sent to " . htmlspecialchars($user['email']) . ". ($requests_left requests remaining in 30 minutes)";
                                        error_log("Email sent successfully, showing verification modal");
                                    } else {
                                        $error = "Failed to send verification email. Please try again.";
                                        unset($_SESSION['login_verification_code'], $_SESSION['login_verification_expiry'], $_SESSION['pending_login_user']);
                                        error_log("Failed to send verification email");
                                    }
                                }
                                
                                // Check if 2FA is also enabled for this admin
                                if (in_array($normalized_db_role, ['admin', 'super admin'], true)) {
                                    $twofa_status = get2FAStatus($user['id']);
                                    if ($twofa_status['enabled']) {
                                        // Store that 2FA will be needed after email verification
                                        $_SESSION['require_2fa_after_email'] = true;
                                    }
                                }
                                
                                // Clear rate limit on successful password entry
                                clearRateLimit($email);
                            }
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
    <title>Login - PCMS | City of Valenzuela</title>
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
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 animate-fade-in-up animation-delay-100">PCMS</h1>
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
                    <a href="#" onclick="openForgotPasswordModal(event)" class="text-red-600 hover:text-red-700 font-medium transition-colors">Forgot password?</a>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 md:py-3 rounded-lg transition duration-200 shadow-md hover:shadow-lg flex items-center justify-center">
                    <span>Sign In</span>
                    <i class="bi bi-arrow-right ml-2"></i>
                </button>
            </form>
            <?php endif; ?>
            
            <!-- Email Verification Modal -->
            <?php 
            error_log("Modal condition - show_email_verification: " . ($show_email_verification ? 'TRUE' : 'FALSE'));
            if ($show_email_verification): 
            ?>
            <div id="verification-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: flex !important;">
                <div class="bg-white rounded-xl shadow-2xl p-6 max-w-md w-full mx-4">
                    <!-- Modal Header -->
                    <div class="text-center mb-4">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-3">
                            <i class="bi bi-envelope-check text-blue-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Check Your Email</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            We've sent a verification code to:<br>
                            <strong><?php echo htmlspecialchars($_SESSION['pending_login_user']['email'] ?? ''); ?></strong>
                        </p>
                    </div>
                    
                    <!-- Verification Form -->
                    <form method="POST" action="login.php">
                        <?php outputCSRFField(); ?>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Enter 6-digit code:
                            </label>
                            <input type="text" 
                                   name="email_verification_code" 
                                   required 
                                   maxlength="6" 
                                   pattern="[0-9]{6}"
                                   placeholder="000000"
                                   class="w-full px-4 py-2 text-xl text-center font-mono border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="mt-2 text-xs text-gray-500 text-center">
                                <i class="bi bi-clock mr-1"></i>
                                Code expires in 5 minutes
                            </p>
                        </div>
                        
                        <div class="space-y-2">
                            <button type="submit" 
                                    name="verify_email_code" 
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg">
                                <i class="bi bi-check-circle mr-1"></i>
                                Verify & Login
                            </button>
                            
                            <div class="flex gap-2">
                                <button type="submit" 
                                        name="resend_code" 
                                        class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 rounded-lg text-sm">
                                    <i class="bi bi-arrow-clockwise mr-1"></i>
                                    Resend
                                </button>
                                <a href="login.php" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 rounded-lg text-sm text-center no-underline">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
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
            <!-- Feedback link removed as per request -->
        </div>
        
        <!-- Forgot Password Modal -->
        <div id="forgotPasswordModal" class="<?php echo $show_password_reset_form ? '' : 'hidden'; ?> fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 max-w-md w-full mx-4">
                <!-- Modal Header -->
                <div class="text-center mb-6">
                    <h3 id="modalTitle" class="text-2xl font-bold text-gray-900 mb-2">
                        <?php echo $show_password_reset_form ? 'Create New Password' : 'Forgot Password?'; ?>
                    </h3>
                    <p id="modalSubtitle" class="text-gray-600 text-sm">
                        <?php echo $show_password_reset_form ? 'Enter your new password below.' : 'Enter your email address and we\'ll send you a confirmation.'; ?>
                    </p>
                </div>
                
                <!-- Alert Messages -->
                <div id="forgotPasswordAlert" class="mb-4 px-4 py-3 rounded-lg hidden" role="alert">
                    <span id="forgotPasswordAlertText"></span>
                </div>
                
                <!-- Email Form (Initial) -->
                <form id="forgotPasswordForm" class="space-y-4" style="<?php echo $show_password_reset_form ? 'display: none;' : ''; ?>">
                    <div>
                        <label for="forgotEmail" class="block text-sm font-medium text-gray-700 mb-1.5">
                            <i class="bi bi-envelope mr-1"></i>Email Address
                        </label>
                        <input type="email" 
                               id="forgotEmail" 
                               name="email" 
                               required 
                               placeholder="your.email@lgu.gov.ph"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 transition text-base">
                    </div>
                    
                    <button type="submit" id="forgotPasswordBtn" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 rounded-lg transition duration-200 shadow-md hover:shadow-lg flex items-center justify-center">
                        <i class="bi bi-send mr-2"></i>
                        <span id="forgotPasswordBtnText">Send Confirmation</span>
                    </button>
                </form>

                <!-- Password Reset Form (After Confirmation) -->
                <form id="passwordResetForm" class="space-y-4" style="<?php echo $show_password_reset_form ? '' : 'display: none;'; ?>">
                    <input type="hidden" id="changePasswordToken" value="<?php echo htmlspecialchars($confirmed_token); ?>">
                    
                    <!-- New Password -->
                    <div>
                        <label for="newPassword" class="block text-sm font-medium text-gray-700 mb-1.5">
                            <i class="bi bi-lock mr-1"></i>New Password
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="newPassword" 
                                   name="password" 
                                   required
                                   minlength="6"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 transition"
                                   placeholder="Enter new password"
                                   oninput="checkModalPasswordStrength()">
                            <button type="button" 
                                    onclick="toggleModalPassword('newPassword', 'newPasswordEye')"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 transition">
                                <i id="newPasswordEye" class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <div id="modalPasswordStrength" class="h-1 rounded bg-gray-200"></div>
                            <p id="modalStrengthText" class="text-xs text-gray-500 mt-1"></p>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="confirmNewPassword" class="block text-sm font-medium text-gray-700 mb-1.5">
                            <i class="bi bi-lock-check mr-1"></i>Confirm Password
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="confirmNewPassword" 
                                   name="confirm_password" 
                                   required
                                   minlength="6"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 transition"
                                   placeholder="Confirm new password"
                                   oninput="checkModalPasswordMatch()">
                            <button type="button" 
                                    onclick="toggleModalPassword('confirmNewPassword', 'confirmNewPasswordEye')"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 transition">
                                <i id="confirmNewPasswordEye" class="bi bi-eye"></i>
                            </button>
                        </div>
                        <p id="modalMatchText" class="text-xs mt-1"></p>
                    </div>
                    
                    <button type="submit" id="resetPasswordBtn" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 rounded-lg transition duration-200 shadow-md hover:shadow-lg flex items-center justify-center">
                        <i class="bi bi-shield-check mr-2"></i>
                        <span id="resetPasswordBtnText">Update Password</span>
                    </button>
                </form>
                
                <!-- Divider -->
                <div class="relative my-4">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-xs">
                        <span class="px-2 bg-white text-gray-500">or</span>
                    </div>
                </div>
                
                <!-- Close Button -->
                <button type="button" 
                        onclick="closeForgotPasswordModal()" 
                        class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 rounded-lg transition">
                    Back to Login
                </button>
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
                    window.location.href = data.redirect || 'index.php';
                } else {
                    alert('Login failed: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred during login');
            });
        }

        // Session timeout warning system
        let warningShown = false;
        let countdownInterval;
        
        function showSessionWarning(minutesLeft) {
            if (warningShown) return;
            
            warningShown = true;
            let timeLeft = minutesLeft * 60; // Convert to seconds
            
            // Create warning modal
            const warningDiv = document.createElement('div');
            warningDiv.id = 'session-warning';
            warningDiv.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            warningDiv.innerHTML = `
                <div class='bg-white rounded-lg p-6 max-w-md mx-4'>
                    <div class='text-center'>
                        <i class='bi bi-exclamation-triangle text-yellow-500 text-4xl mb-4'></i>
                        <h3 class='text-lg font-bold text-gray-900 mb-2'>Session Expiring Soon</h3>
                        <p class='text-gray-600 mb-4'>Your session will expire in <span id='countdown'>${minutesLeft}:00</span> due to inactivity.</p>
                        <p class='text-sm text-gray-500 mb-4'>Click anywhere or continue working to extend your session.</p>
                        <button onclick='extendSession()' class='bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'>
                            Extend Session
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(warningDiv);
            
            // Start countdown
            countdownInterval = setInterval(() => {
                timeLeft--;
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                const countdownEl = document.getElementById('countdown');
                if (countdownEl) {
                    countdownEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                }
                
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = 'login.php?timeout=1';
                }
            }, 1000);
        }
        
        function extendSession() {
            // Remove warning
            const warningDiv = document.getElementById('session-warning');
            if (warningDiv) {
                warningDiv.remove();
            }
            
            // Reset warning flag
            warningShown = false;
            
            // Clear countdown
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
            
            // Make a request to extend session (ping server)
            fetch(window.location.href, {method: 'HEAD'});
        }
        
        // Monitor user activity
        let activityTimer;
        function resetActivityTimer() {
            clearTimeout(activityTimer);
            activityTimer = setTimeout(() => {
                // Show warning with a 2-minute countdown.
                showSessionWarning(2);
            }, 60 * 1000); // Trigger warning after 1 minute inactivity
        }
        
        // Activity events
        ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetActivityTimer, true);
        });
        
        // Start monitoring
        resetActivityTimer();

        // Forgot Password Modal Functions
        function openForgotPasswordModal(event) {
            if (event) event.preventDefault();
            document.getElementById('forgotPasswordModal').classList.remove('hidden');
            const emailField = document.getElementById('forgotEmail');
            const passwordField = document.getElementById('newPassword');
            
            // Focus on appropriate field based on which form is visible
            if (emailField && emailField.style.display !== 'none') {
                emailField.focus();
            } else if (passwordField && passwordField.style.display !== 'none') {
                passwordField.focus();
            }
        }

        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.add('hidden');
            document.getElementById('forgotPasswordForm').reset();
            document.getElementById('passwordResetForm').reset();
            document.getElementById('forgotPasswordAlert').classList.add('hidden');
            document.getElementById('forgotPasswordBtn').disabled = false;
            document.getElementById('forgotPasswordBtnText').textContent = 'Send Confirmation';
            document.getElementById('resetPasswordBtn').disabled = false;
            document.getElementById('resetPasswordBtnText').textContent = 'Update Password';
        }

        // Auto-open modal on page load if showing password reset form
        document.addEventListener('DOMContentLoaded', function() {
            const showPasswordForm = <?php echo $show_password_reset_form ? 'true' : 'false'; ?>;
            if (showPasswordForm) {
                openForgotPasswordModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('forgotPasswordModal')?.addEventListener('click', function(event) {
            if (event.target === this) {
                closeForgotPasswordModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('forgotPasswordModal');
                if (modal && !modal.classList.contains('hidden')) {
                    closeForgotPasswordModal();
                }
            }
        });

        // Handle Forgot Password Form Submission
        document.getElementById('forgotPasswordForm')?.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const email = document.getElementById('forgotEmail').value.trim();
            const btn = document.getElementById('forgotPasswordBtn');
            const btnText = document.getElementById('forgotPasswordBtnText');
            const alertDiv = document.getElementById('forgotPasswordAlert');
            const alertText = document.getElementById('forgotPasswordAlertText');
            
            // Validate email
            if (!email || !email.includes('@')) {
                alertDiv.className = 'mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 flex items-center';
                alertText.innerHTML = '<i class="bi bi-exclamation-circle mr-2"></i>Please enter a valid email address.';
                alertDiv.classList.remove('hidden');
                return;
            }
            
            // Disable button and show loading state
            btn.disabled = true;
            btnText.innerHTML = '<i class="bi bi-hourglass-split mr-2 animate-spin"></i>Sending...';
            
            // Send request to API
            fetch('./AUTH/forgot_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    'action': 'forgot_password',
                    'email': email
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertDiv.className = 'mb-4 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-700';
                    alertText.innerHTML = '<div><i class="bi bi-check-circle mr-2"></i><strong>Email sent!</strong></div><p style="margin: 0.5rem 0 0 0; font-size: 0.875rem;">Check your email and click the confirmation link to set your new password.</p>';
                    alertDiv.classList.remove('hidden');
                    
                    // Hide email form
                    document.getElementById('forgotPasswordForm').style.display = 'none';
                    document.getElementById('forgotPasswordBtn').style.display = 'none';
                    
                    // KEEP MODAL OPEN - user will click email link which redirects back here with token
                } else {
                    alertDiv.className = 'mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 flex items-center';
                    alertText.innerHTML = '<i class="bi bi-exclamation-circle mr-2"></i>' + (data.message || 'Failed to send confirmation email. Please try again.');
                    alertDiv.classList.remove('hidden');
                    
                    // Re-enable button
                    btn.disabled = false;
                    btnText.textContent = 'Send Confirmation';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alertDiv.className = 'mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 flex items-center';
                alertText.innerHTML = '<i class="bi bi-exclamation-circle mr-2"></i>An error occurred. Please try again later.';
                alertDiv.classList.remove('hidden');
                
                // Re-enable button
                btn.disabled = false;
                btnText.textContent = 'Send Confirmation';
            });
        });

        // Handle Password Reset Form Submission
        document.getElementById('passwordResetForm')?.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const password = document.getElementById('newPassword').value.trim();
            const confirmPassword = document.getElementById('confirmNewPassword').value.trim();
            const token = document.getElementById('changePasswordToken').value;
            const btn = document.getElementById('resetPasswordBtn');
            const btnText = document.getElementById('resetPasswordBtnText');
            const alertDiv = document.getElementById('forgotPasswordAlert');
            const alertText = document.getElementById('forgotPasswordAlertText');
            
            if (password.length < 6) {
                alertDiv.className = 'mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 flex items-center';
                alertText.innerHTML = '<i class="bi bi-exclamation-circle mr-2"></i>Password must be at least 6 characters.';
                alertDiv.classList.remove('hidden');
                return;
            }
            
            if (password !== confirmPassword) {
                alertDiv.className = 'mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 flex items-center';
                alertText.innerHTML = '<i class="bi bi-exclamation-circle mr-2"></i>Passwords do not match.';
                alertDiv.classList.remove('hidden');
                return;
            }
            
            btn.disabled = true;
            btnText.innerHTML = '<i class="bi bi-hourglass-split mr-2 animate-spin"></i>Updating...';
            
            fetch('./AUTH/update_password_after_confirmation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    'action': 'update_password',
                    'token': token,
                    'password': password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertDiv.className = 'mb-4 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-700 flex items-center';
                    alertText.innerHTML = '<i class="bi bi-check-circle mr-2"></i>Password updated successfully! Please login with your new password.';
                    alertDiv.classList.remove('hidden');
                    
                    setTimeout(function() {
                        closeForgotPasswordModal();
                        document.getElementById('email').focus();
                    }, 2000);
                } else {
                    alertDiv.className = 'mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 flex items-center';
                    alertText.innerHTML = '<i class="bi bi-exclamation-circle mr-2"></i>' + (data.message || 'Failed to update password. Please try again.');
                    alertDiv.classList.remove('hidden');
                    
                    btn.disabled = false;
                    btnText.textContent = 'Update Password';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alertDiv.className = 'mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 flex items-center';
                alertText.innerHTML = '<i class="bi bi-exclamation-circle mr-2"></i>An error occurred. Please try again later.';
                alertDiv.classList.remove('hidden');
                
                btn.disabled = false;
                btnText.textContent = 'Update Password';
            });
        });

        // Password Modal Functions
        function toggleModalPassword(fieldId, eyeId) {
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

        function checkModalPasswordStrength() {
            const password = document.getElementById('newPassword').value;
            const strengthEl = document.getElementById('modalPasswordStrength');
            const strengthText = document.getElementById('modalStrengthText');
            
            let strengthClass = '';
            let text = '';
            
            if (password.length === 0) {
                strengthEl.className = 'h-1 rounded bg-gray-200';
                strengthText.textContent = '';
            } else if (password.length < 6) {
                strengthEl.className = 'h-1 rounded bg-red-500';
                strengthText.textContent = 'Weak password';
            } else {
                strengthEl.className = 'h-1 rounded bg-green-500';
                strengthText.textContent = 'Strong password';
            }
        }

        function checkModalPasswordMatch() {
            const password = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmNewPassword').value;
            const matchText = document.getElementById('modalMatchText');
            
            if (confirmPassword.length === 0) {
                matchText.textContent = '';
            } else if (password === confirmPassword) {
                matchText.className = 'text-xs mt-1 text-green-600';
                matchText.innerHTML = '<i class="bi bi-check-circle mr-1"></i>Passwords match';
            } else {
                matchText.className = 'text-xs mt-1 text-red-600';
                matchText.innerHTML = '<i class="bi bi-exclamation-circle mr-1"></i>Passwords do not match';
            }
        }

            </script>

<script>
    // Setup password toggle at end of page when DOM is fully ready
    const togglePasswordBtn = document.getElementById('toggle-password');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eye-icon');
    
    if (togglePasswordBtn && passwordInput && eyeIcon) {
        togglePasswordBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('bi-eye');
                eyeIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
            }
        });
    }
    
    // Auto-focus email
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.focus();
    }
</script>
</body>
</html>
