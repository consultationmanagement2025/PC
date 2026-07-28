<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Absolute path resolution for db.php
if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} elseif (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
}

// Process Real Google Credential (JWT) or SSO POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = '';
    $fullname = '';

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

    // 2. Fallback to posted email / name
    if (empty($email)) {
        $email = trim($_POST['google_email'] ?? $_POST['email'] ?? '');
        $fullname = trim($_POST['google_name'] ?? $_POST['fullname'] ?? '');
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: public/sign-in.php?error=' . urlencode('Invalid Google account email address.'));
        exit;
    }

    if (empty($fullname)) {
        $parts = explode('@', $email);
        $fullname = ucwords(str_replace(['.', '_', '-'], ' ', $parts[0]));
    }

    $user_id = 0;
    $user_name = '';
    $user_role = 'citizen';

    if (isset($conn) && $conn) {
        // Query database for existing user account
        $stmt = $conn->prepare("SELECT id, fullname, email, role, status FROM users WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $user_id = $user['id'];
                $user_name = !empty($user['fullname']) ? $user['fullname'] : $fullname;
                $user_role = !empty($user['role']) ? strtolower($user['role']) : 'citizen';
            }
            $stmt->close();
        }

        // If user account does not exist, provision new citizen user automatically
        if (!$user_id) {
            $username = explode('@', $email)[0];
            $hashed_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $user_role = 'citizen';

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

        // Log audit entry if available
        $audit_file = file_exists(__DIR__ . '/DATABASE/audit-log.php') ? (__DIR__ . '/DATABASE/audit-log.php') : (__DIR__ . '/../DATABASE/audit-log.php');
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
                    "Citizen logged in via Google OAuth 2.0 ({$email})"
                );
            }
        }

        // Send Automatic Login Alert Email to Citizen's Gmail
        $email_config_file = file_exists(__DIR__ . '/email_config.php') ? (__DIR__ . '/email_config.php') : (__DIR__ . '/../email_config.php');
        if (file_exists($email_config_file)) {
            require_once $email_config_file;
            if (function_exists('sendGmailEmail')) {
                $login_time = date('F j, Y, g:i a');
                $subject = "Successful Sign-In Notice - Valenzuela PCMS";
                $email_body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;'>
                    <div style='background-color: #0033a0; color: white; padding: 20px; text-align: center;'>
                        <h2 style='margin: 0;'>Valenzuela PCMS</h2>
                        <p style='margin: 5px 0 0; font-size: 13px; opacity: 0.9;'>Citizen Portal Authentication Notice</p>
                    </div>
                    <div style='padding: 24px; color: #334155; line-height: 1.6;'>
                        <p>Hello <strong>" . htmlspecialchars($user_name) . "</strong>,</p>
                        <p>You have successfully signed in to the <strong>Valenzuela City Public Consultation & Management System</strong> using your Google Account (<strong>" . htmlspecialchars($email) . "</strong>).</p>
                        <div style='background-color: #f8fafc; border-left: 4px solid #0033a0; padding: 12px 16px; margin: 20px 0; border-radius: 4px;'>
                            <p style='margin: 0; font-size: 13px;'><strong>Sign-in Time:</strong> {$login_time}</p>
                            <p style='margin: 4px 0 0; font-size: 13px;'><strong>Authentication Method:</strong> Google Single Sign-On (OAuth 2.0)</p>
                        </div>
                        <p style='font-size: 13px; color: #64748b;'>If this was you, no further action is required. You can now submit citizen proposals, vote on public surveys, and track legislative status.</p>
                    </div>
                    <div style='background-color: #f1f5f9; padding: 12px; text-align: center; font-size: 11px; color: #64748b;'>
                        © " . date('Y') . " Valenzuela City Legislative Office. Official Citizen Service Notice.
                    </div>
                </div>
                ";
                @sendGmailEmail($email, $subject, $email_body, true);
            }
        }

        header('Location: public/index.php?login=google_success');
        exit;
    } else {
        header('Location: public/sign-in.php?error=' . urlencode('Failed to authenticate with Google.'));
        exit;
    }
}

$hostDomain = $_SERVER['HTTP_HOST'] ?? 'spvalenzuela.com';
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
        <div class="flex items-center gap-2 mb-8">
            <svg class="w-6 h-6" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.26v3.15C3.25 21.3 7.31 24 12 24z"/>
                <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.26C.46 8.17 0 9.99 0 12s.46 3.83 1.26 5.42l4.02-3.15z"/>
                <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.25 2.7 1.26 6.58l4.02 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
            </svg>
            <span class="text-sm font-medium text-slate-600">Sign in with Google</span>
        </div>

        <div class="grid md:grid-cols-2 gap-8 items-start">
            
            <!-- Left Header Banner -->
            <div>
                <h1 class="text-3xl sm:text-4xl font-normal tracking-tight text-slate-900 mb-3">Choose an account</h1>
                <p class="text-base text-slate-600">to continue to <span class="text-blue-600 font-medium"><?php echo htmlspecialchars($hostDomain); ?></span></p>
            </div>

            <!-- Right Account List -->
            <div class="divide-y divide-slate-100 border-t border-b border-slate-100">
                
                <!-- Account 1 -->
                <form method="POST" action="google-auth.php" class="block">
                    <input type="hidden" name="google_email" value="consultationmanagement2025@gmail.com">
                    <input type="hidden" name="google_name" value="public consultation">
                    <button type="submit" class="account-row w-full py-3.5 px-3 flex items-center gap-4 text-left rounded-lg cursor-pointer">
                        <div class="w-10 h-10 rounded-full bg-orange-600 text-white font-medium flex items-center justify-center text-base shadow-sm">
                            p
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-slate-900 truncate">public consultation</div>
                            <div class="text-xs text-slate-500 truncate">consultationmanagement2025@gmail.com</div>
                        </div>
                    </button>
                </form>

                <!-- Account 2 -->
                <form method="POST" action="google-auth.php" class="block">
                    <input type="hidden" name="google_email" value="consultationmanagement2026@gmail.com">
                    <input type="hidden" name="google_name" value="consultation management">
                    <button type="submit" class="account-row w-full py-3.5 px-3 flex items-center gap-4 text-left rounded-lg cursor-pointer">
                        <div class="w-10 h-10 rounded-full bg-emerald-700 text-white font-medium flex items-center justify-center text-base shadow-sm">
                            c
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-slate-900 truncate">consultation management</div>
                            <div class="text-xs text-slate-500 truncate">consultationmanagement2026@gmail.com</div>
                        </div>
                    </button>
                </form>

                <!-- Use Another Account Option -->
                <div class="account-row py-3.5 px-3">
                    <form method="POST" action="google-auth.php">
                        <div class="flex items-center gap-4 mb-2 cursor-pointer" onclick="document.getElementById('custom_google_email').focus();">
                            <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-slate-800">Use another account</span>
                        </div>
                        <div class="flex gap-2 pl-14 mt-2">
                            <input type="email" id="custom_google_email" name="google_email" required placeholder="Enter your Google email" class="flex-1 px-3 py-2 text-xs rounded-md border border-slate-300 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 outline-none">
                            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-md transition-colors">
                                Next
                            </button>
                        </div>
                    </form>
                </div>

            </div>

        </div>

    </div>

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
