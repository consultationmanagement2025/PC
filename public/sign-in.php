<?php
session_start();
require_once '../db.php';

$error = '';
$signup_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Check user credentials
        $stmt = $conn->prepare("SELECT id, fullname, email, password, role FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) {
            $stmt = $conn->prepare("SELECT id, fullname, email, password, role FROM users WHERE email = ? LIMIT 1");
        }
        
        if (!$stmt) {
            $error = 'Database error: ' . $conn->error;
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    $role = strtolower(trim($user['role'] ?? 'citizen'));
                    $admin_roles = ['admin', 'administrator', 'super admin', 'superadmin', 'staff'];
                    
                    if (in_array($role, $admin_roles, true)) {
                        $error = 'Admin and staff accounts must use the main login page at ../login.php';
                    } else {
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['fullname'] = $user['fullname'] ?? 'Citizen';
                        $_SESSION['full_name'] = $user['fullname'] ?? 'Citizen';
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $role;

                        // Redirect to public portal
                        header('Location: index.php?login=success&name=' . urlencode($user['fullname'] ?? 'Citizen'));
                        exit;
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                $error = 'Invalid email or password.';
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['signup']) && $_GET['signup'] === 'success') {
    $signup_success = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Valenzuela PCMS</title>
    <link rel="icon" type="image/png" href="../images/valenzuela-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        valenzuela: {
                            blue: '#0033a0',
                            red: '#ff0000'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; animation: fadeIn 0.6s ease-out; }
        html { scroll-behavior: smooth; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        nav { animation: slideUp 0.5s ease-out; }
        main { animation: fadeIn 0.6s ease-out 0.1s both; }
        
        button, a { transition: all 0.3s ease; }
        input, textarea, select { transition: all 0.3s ease; }
        
        nav {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        nav.scrolled {
            background: rgba(255, 255, 255, 0.98) !important;
            border-bottom-color: rgba(0, 51, 160, 0.1) !important;
            backdrop-filter: blur(15px) !important;
        }
    </style>
</head>
<body class="text-slate-800 antialiased relative">

    <nav class="glass border-b border-gray-200 sticky top-0 z-50 shadow-sm transition-all duration-300" id="main-nav">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center gap-3 shrink-0">
                    <div class="w-12 h-12 rounded-full border-2 border-gray-100 shadow-inner flex items-center justify-center overflow-hidden bg-white">
                        <img src="../images/valenzuela-logo.png" alt="Valenzuela Seal" class="w-full h-full object-cover opacity-80">
                    </div>
                    <div class="flex flex-col justify-center">
                        <div class="flex items-baseline gap-2">
                            <h1 class="text-[22px] font-black tracking-tight flex items-baseline">
                                <span class="text-valenzuela-blue">VALENZUELA</span>
                                <span class="text-valenzuela-red ml-1">PCMS</span>
                            </h1>
                            <div class="leading-none text-[10px] font-bold text-valenzuela-red tracking-wider border-l border-gray-300 pl-2 ml-1 uppercase hidden sm:block">
                                Public<br>Portal
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hidden md:flex items-center gap-6">
                    <a href="index.php" class="text-sm font-semibold text-slate-700 hover:text-valenzuela-blue transition-colors">Back to Home</a>
                    <a href="sign-up.php" class="bg-valenzuela-red hover:bg-red-700 text-white px-6 py-2.5 rounded-full font-bold text-sm transition-all shadow-[0_4px_14px_0_rgba(255,0,0,0.39)] hover:shadow-[0_6px_20px_rgba(255,0,0,0.23)] hover:-translate-y-0.5">
                        Sign Up
                    </a>
                </div>
                <div class="md:hidden flex items-center gap-4">
                    <a href="sign-up.php" class="bg-valenzuela-red hover:bg-red-700 text-white px-4 py-2 rounded-full font-bold text-xs">Sign Up</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-center justify-center min-h-[calc(100vh-120px)]">
            <div class="w-full max-w-md">
                <div class="bg-white rounded-2xl p-8 shadow-lg border border-gray-100">
                    <!-- Prominent 1-Click Google Sign-In -->
                    <div class="mb-6">
                        <a href="google-auth.php" class="w-full bg-white hover:bg-slate-50 text-slate-700 font-bold py-3.5 px-4 rounded-xl border border-slate-300 shadow-sm transition-all flex justify-center items-center gap-3 hover:shadow-md hover:border-slate-400 group">
                            <svg class="w-5 h-5 transition-transform group-hover:scale-110" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                                <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.26v3.15C3.25 21.3 7.31 24 12 24z"/>
                                <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.26C.46 8.17 0 9.99 0 12s.46 3.83 1.26 5.42l4.02-3.15z"/>
                                <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.25 2.7 1.26 6.58l4.02 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                            </svg>
                            <span class="text-sm">Continue with Google</span>
                        </a>

                        <div class="relative flex py-4 items-center">
                            <div class="flex-grow border-t border-slate-200"></div>
                            <span class="flex-shrink mx-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">or sign in with email</span>
                            <div class="flex-grow border-t border-slate-200"></div>
                        </div>
                    </div>

                    <form method="POST" class="space-y-5" id="signin-form" novalidate>
                        <div>
                            <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">Email Address</label>
                            <input type="email" id="email" name="email" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none transition-all" placeholder="name@example.com">
                            <div id="email-error" class="text-red-500 text-xs font-semibold mt-1 hidden flex items-center gap-1">
                                <i class="fa-solid fa-exclamation-circle"></i> <span id="email-error-text">Please enter a valid email address</span>
                            </div>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">Password</label>
                            <input type="password" id="password" name="password" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none transition-all" placeholder="Enter your password">
                            <div id="password-error" class="text-red-500 text-xs font-semibold mt-1 hidden flex items-center gap-1">
                                <i class="fa-solid fa-exclamation-circle"></i> <span id="password-error-text">Please enter your password</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <label class="inline-flex items-center gap-2 text-slate-600">
                                <input type="checkbox" class="rounded border-gray-300 text-valenzuela-blue focus:ring-valenzuela-blue">
                                Remember me
                            </label>
                            <a href="#" class="text-valenzuela-blue font-semibold hover:underline">Forgot password?</a>
                        </div>

                        <button type="submit" class="w-full bg-valenzuela-red hover:bg-red-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition-all flex justify-center items-center gap-2 hover:-translate-y-0.5">
                            <i class="fa-solid fa-right-to-bracket"></i> Sign In
                        </button>
                    </form>

                    <div class="mt-6 text-sm text-center text-slate-500">
                        Don't have an account? <a href="sign-up.php" class="text-valenzuela-blue font-semibold hover:underline">Sign Up</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <!-- Interactive Scripts -->
    <script>
        // Sticky header scroll effect
        const navBar = document.getElementById('main-nav');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navBar.classList.add('scrolled');
            } else {
                navBar.classList.remove('scrolled');
            }
        });

        // Custom Form Validation
        const signinForm = document.getElementById('signin-form');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const emailError = document.getElementById('email-error');
        const passwordError = document.getElementById('password-error');
        const emailErrorText = document.getElementById('email-error-text');
        const passwordErrorText = document.getElementById('password-error-text');

        function validateEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function validateForm() {
            let isValid = true;
            emailError.classList.add('hidden');
            passwordError.classList.add('hidden');
            emailInput.classList.remove('border-red-500');
            passwordInput.classList.remove('border-red-500');

            if (!emailInput.value.trim()) {
                emailError.classList.remove('hidden');
                emailErrorText.textContent = 'Please enter your email address';
                emailInput.classList.add('border-red-500');
                isValid = false;
            } else if (!validateEmail(emailInput.value.trim())) {
                emailError.classList.remove('hidden');
                emailErrorText.textContent = 'Please enter a valid email address';
                emailInput.classList.add('border-red-500');
                isValid = false;
            }

            if (!passwordInput.value.trim()) {
                passwordError.classList.remove('hidden');
                passwordErrorText.textContent = 'Please enter your password';
                passwordInput.classList.add('border-red-500');
                isValid = false;
            }

            return isValid;
        }

        signinForm.addEventListener('submit', (e) => {
            if (!validateForm()) {
                e.preventDefault();
            }
        });

        // Clear error on input
        emailInput.addEventListener('input', () => {
            if (emailInput.value.trim()) {
                emailError.classList.add('hidden');
                emailInput.classList.remove('border-red-500');
            }
        });

        passwordInput.addEventListener('input', () => {
            if (passwordInput.value.trim()) {
                passwordError.classList.add('hidden');
                passwordInput.classList.remove('border-red-500');
            }
        });

        // Add smooth page transition on navigation
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (link && link.href && !link.href.includes('#') && !link.target && link.origin === window.location.origin) {
                e.preventDefault();
                document.body.style.opacity = '0';
                document.body.style.transition = 'opacity 0.3s ease-out';
                setTimeout(() => {
                    window.location.href = link.href;
                }, 300);
            }
        });

        // Fade in on page load
        window.addEventListener('pageshow', () => {
            document.body.style.opacity = '1';
        });

        document.body.style.opacity = '1';
    </script>
</body>
</html>
