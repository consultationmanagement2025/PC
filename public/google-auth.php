<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Absolute path resolution for db.php
$db_path = file_exists(__DIR__ . '/../db.php') ? (__DIR__ . '/../db.php') : (__DIR__ . '/db.php');
if (file_exists($db_path)) {
    require_once $db_path;
}

$email = '';
$fullname = '';
$otp_error = '';
$show_otp_form = false;

// Process POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Check if official Google GIS JWT token credential was posted
    if (!empty($_POST['credential'])) {
        $jwt = $_POST['credential'];
        $parts = explode('.', $jwt);
        if (count($parts) >= 2) {
            $payload_json = base64_decode(strtr($parts[1], '-_', '+/'));
            $google_data = json_decode($payload_json, true);
            if ($google_data && !empty($google_data['email'])) {
                $email = trim($google_data['email']);
                $fullname = trim($google_data['name'] ?? $google_data['given_name'] ?? '');
            }
        }
    }

    // 2. Check for 6-Digit OTP Verification Submission
    if (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
        $entered_code = trim($_POST['otp_code'] ?? '');
        $pending = $_SESSION['pending_google_otp'] ?? null;

        if ($pending && !empty($pending['code']) && time() <= $pending['expires']) {
            if ($entered_code === (string)$pending['code']) {
                $email = $pending['email'];
                $fullname = $pending['fullname'];
                unset($_SESSION['pending_google_otp']);
            } else {
                $otp_error = "Invalid 6-digit verification code. Please check your inbox and try again.";
                $show_otp_form = true;
            }
        } else {
            $otp_error = "Verification code expired. Please request a new code.";
            unset($_SESSION['pending_google_otp']);
        }
    }

    // 3. Check if custom email form was submitted (Send 6-digit OTP Code)
    if (!empty($_POST['google_email']) && empty($_POST['credential']) && empty($_POST['action']) && !$email) {
        $target_email = trim($_POST['google_email']);
        if (filter_var($target_email, FILTER_VALIDATE_EMAIL)) {
            $otp_code = sprintf("%06d", mt_rand(100000, 999999));
            $target_name = trim($_POST['google_name'] ?? '');
            if (empty($target_name)) {
                $parts = explode('@', $target_email);
                $target_name = ucwords(str_replace(['.', '_', '-'], ' ', $parts[0]));
            }

            $_SESSION['pending_google_otp'] = [
                'email' => $target_email,
                'fullname' => $target_name,
                'code' => $otp_code,
                'expires' => time() + 600
            ];

            // Send OTP email via Gmail SMTP
            $subject = "Your Valenzuela Portal 6-Digit Login Code: {$otp_code}";
            $body = "Dear Citizen,\n\n" .
                    "Your 6-digit verification code to complete sign-in to the Valenzuela City Public Consultation Portal is:\n\n" .
                    "   =========================\n" .
                    "        [ {$otp_code} ]     \n" .
                    "   =========================\n\n" .
                    "This code expires in 10 minutes.\n\n" .
                    "City Government of Valenzuela";

            $email_file = file_exists(__DIR__ . '/../email_config_simple.php') ? (__DIR__ . '/../email_config_simple.php') : (__DIR__ . '/email_config_simple.php');
            if (file_exists($email_file)) {
                try {
                    require_once $email_file;
                    if (function_exists('sendGmailEmailSimple')) {
                        @sendGmailEmailSimple($target_email, $subject, $body, false);
                    }
                } catch (Throwable $e) {
                    error_log("Google OTP Email Error: " . $e->getMessage());
                }
            }

            $show_otp_form = true;
        }
    }

    // 4. Authenticate & Provision User in Database if Email is Verified
    if (!$show_otp_form && !empty($email)) {
        if (empty($fullname)) {
            $parts = explode('@', $email);
            $fullname = ucwords(str_replace(['.', '_', '-'], ' ', $parts[0]));
        }

        $user_id = 0;
        $user_name = '';
        $user_role = 'citizen';
        $verification_status = 'approved';

        if (isset($conn) && $conn) {
            // Query database for existing user account
            $stmt = $conn->prepare("SELECT id, fullname, email, role, status, verification_status FROM users WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    $user_id = $user['id'];
                    $user_name = !empty($user['fullname']) ? $user['fullname'] : $fullname;
                    $user_role = !empty($user['role']) ? strtolower($user['role']) : 'citizen';
                    $verification_status = strtolower(trim($user['verification_status'] ?? 'approved'));
                }
                $stmt->close();
            }

            // Provision user if not found
            if (!$user_id) {
                $username = explode('@', $email)[0];
                $hashed_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $user_role = 'citizen';
                $verification_status = 'approved';

                $insert_stmt = $conn->prepare("INSERT INTO users (fullname, username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
                if ($insert_stmt) {
                    $insert_stmt->bind_param('sssss', $fullname, $username, $email, $hashed_password, $user_role);
                    if ($insert_stmt->execute()) {
                        $user_id = $insert_stmt->insert_id;
                        $user_name = $fullname;
                    }
                    $insert_stmt->close();
                }

                if (!$user_id) {
                    $insert_stmt2 = $conn->prepare("INSERT INTO users (fullname, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
                    if ($insert_stmt2) {
                        $insert_stmt2->bind_param('ssss', $fullname, $email, $hashed_password, $user_role);
                        if ($insert_stmt2->execute()) {
                            $user_id = $insert_stmt2->insert_id;
                            $user_name = $fullname;
                        }
                        $insert_stmt2->close();
                    }
                }
            }
        }

        if ($user_id) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['fullname'] = $user_name;
            $_SESSION['full_name'] = $user_name;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $user_role;
            $_SESSION['verification_status'] = $verification_status;
            $_SESSION['login_time'] = time();
            $_SESSION['portal'] = 'citizen'; // Isolate from admin portal

            // Audit log
            $audit_file = file_exists(__DIR__ . '/../DATABASE/audit-log.php') ? (__DIR__ . '/../DATABASE/audit-log.php') : (__DIR__ . '/DATABASE/audit-log.php');
            if (file_exists($audit_file)) {
                require_once $audit_file;
                if (function_exists('logAction')) {
                    logAction(
                        $user_id,
                        $user_name,
                        'google_sso_login',
                        'user',
                        $user_id,
                        null,
                        json_encode(['email' => $email, 'provider' => 'google_oauth2']),
                        'success',
                        "User logged in via Google OAuth 2.0 / Verified Email ({$email})"
                    );
                }
            }

            // Role-based smart redirection
            $roleNorm = strtolower(str_replace([' ', '_'], '', $user_role));

            if (in_array($roleNorm, ['admin', 'administrator', 'superadmin', 'staff', 'barangaystaff', 'barangay'], true)) {
                header('Location: ../system-template-full.php');
            } elseif (in_array($roleNorm, ['resourceperson', 'expert', 'speaker'], true)) {
                if ($verification_status === 'pending') {
                    header('Location: ../pending_approval.php');
                } elseif ($verification_status === 'rejected') {
                    header('Location: ../login.php?error=account_rejected');
                } else {
                    header('Location: ../resource_person_dashboard.php');
                }
            } else {
                header('Location: index.php?login=google_success');
            }
            exit;
        } else {
            header('Location: sign-in.php?error=' . urlencode('Failed to authenticate with Google.'));
            exit;
        }
    }
}

$hostDomain = $_SERVER['HTTP_HOST'] ?? 'spvalenzuela.com';
$googleClientId = getenv('GOOGLE_CLIENT_ID') ?: '483330697347-fgcj14cbh81uik2rbvpi8cj06vof643c.apps.googleusercontent.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in - Google Accounts</title>
    <link rel="icon" type="image/svg+xml" href="https://www.google.com/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', -apple-system, BlinkMacSystemFont, sans-serif; background-color: #f0f4f9; color: #1f1f1f; }
        .google-card { background: #ffffff; border-radius: 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        .account-row { transition: background-color 0.15s ease; border-bottom: 1px solid #f1f3f4; }
        .account-row:hover { background-color: #f8f9fa; }
        .account-row:last-child { border-bottom: none; }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between items-center p-4 sm:p-6">

    <!-- Top Spacer -->
    <div></div>

    <!-- Official Google Account Picker Card -->
    <div class="google-card max-w-[840px] w-full p-8 sm:p-12 my-auto">
        
        <!-- Google Header -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-2">
                <svg class="w-6 h-6" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.26v3.15C3.25 21.3 7.31 24 12 24z"/>
                    <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.26C.46 8.17 0 9.99 0 12s.46 3.83 1.26 5.42l4.02-3.15z"/>
                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.25 2.7 1.26 6.58l4.02 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                </svg>
                <span class="text-sm font-medium text-slate-600">Sign in with Google</span>
            </div>
            <span class="text-xs bg-emerald-100 text-emerald-800 px-2.5 py-1 rounded-full font-bold">
                <i class="fa-solid fa-shield-halved mr-1"></i> Verified Login
            </span>
        </div>

        <div class="grid md:grid-cols-2 gap-8 items-start">
            
            <!-- Left Header Banner -->
            <div>
                <h1 class="text-3xl sm:text-4xl font-normal tracking-tight text-slate-900 mb-3">Choose an account</h1>
                <p class="text-base text-slate-600">to continue to <span class="text-blue-600 font-medium"><?php echo htmlspecialchars($hostDomain); ?></span></p>
            </div>

            <!-- Right Column -->
            <div>
                <?php if ($show_otp_form): ?>
                    <!-- OTP 6-Digit Email Verification Form -->
                    <form method="POST" action="google-auth.php" class="bg-slate-50 p-6 rounded-2xl border border-slate-200 space-y-4">
                        <input type="hidden" name="action" value="verify_otp">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xl mx-auto mb-2 border border-blue-200">
                                <i class="fa-solid fa-envelope-circle-check"></i>
                            </div>
                            <h2 class="text-base font-bold text-slate-900">Check Your Email Inbox</h2>
                            <p class="text-xs text-slate-600 mt-1">We sent a 6-digit verification code to:<br><strong class="text-blue-600"><?php echo htmlspecialchars($_SESSION['pending_google_otp']['email'] ?? ''); ?></strong></p>
                        </div>

                        <?php if ($otp_error): ?>
                            <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700 font-bold text-center">
                                <?php echo htmlspecialchars($otp_error); ?>
                            </div>
                        <?php endif; ?>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 mb-1 text-center">Enter 6-Digit Code</label>
                            <input type="text" name="otp_code" maxlength="6" pattern="[0-9]{6}" required placeholder="000000" class="w-full text-center tracking-[0.5em] text-xl font-mono font-extrabold py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none bg-white">
                        </div>

                        <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow transition-colors">
                            Verify & Complete Sign In
                        </button>
                        <a href="google-auth.php" class="block text-center text-xs text-slate-500 hover:underline pt-1">Cancel / Choose another account</a>
                    </form>
                <?php else: ?>
                    <!-- Official Google GIS Button Container -->
                    <div id="g_id_onload"
                         data-client_id="<?php echo htmlspecialchars($googleClientId); ?>"
                         data-callback="handleGoogleCredentialResponse"
                         data-auto_prompt="false">
                    </div>
                    
                    <div class="mb-4">
                        <div class="g_id_signin" data-type="standard" data-shape="rectangular" data-theme="outline" data-text="signin_with" data-size="large" data-logo_alignment="left" data-width="100%"></div>
                    </div>

                    <div class="relative flex py-2 items-center">
                        <div class="flex-grow border-t border-slate-200"></div>
                        <span class="flex-shrink mx-3 text-xs text-slate-400 font-medium uppercase">Or Email 2FA Verification</span>
                        <div class="flex-grow border-t border-slate-200"></div>
                    </div>

                    <!-- Custom Gmail Verification Form -->
                    <form method="POST" action="google-auth.php" class="mt-2 space-y-3">
                        <label for="custom_google_email" class="block text-xs font-bold text-slate-700">Enter Gmail for 6-Digit Code Verification:</label>
                        <div class="flex gap-2">
                            <input type="email" id="custom_google_email" name="google_email" required placeholder="your.name@gmail.com" class="flex-1 px-3 py-2 text-xs rounded-xl border border-slate-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-600 outline-none">
                            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow">
                                Send Code
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

        </div>

    </div>>

    <!-- Google Footer -->
    <div class="max-w-[840px] w-full flex flex-col sm:flex-row justify-between items-center text-xs text-slate-500 py-4 gap-2">
        <div>English (United States)</div>
        <div class="flex space-x-6">
            <a href="https://support.google.com/accounts" target="_blank" class="hover:underline">Help</a>
            <a href="https://policies.google.com/privacy" target="_blank" class="hover:underline">Privacy</a>
            <a href="https://policies.google.com/terms" target="_blank" class="hover:underline">Terms</a>
        </div>
    </div>

    <script>
        function handleGoogleCredentialResponse(response) {
            if (response && response.credential) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'google-auth.php';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'credential';
                input.value = response.credential;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

</body>
</html>
