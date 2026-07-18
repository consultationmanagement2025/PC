<?php
session_start();
require_once 'config/users.php';

$error = '';
$signup_success = isset($_GET['signup']) && $_GET['signup'] === 'success';

// Clear signup message after displaying once
if ($signup_success) {
    $redirect_url = str_replace('?signup=success', '', $_SERVER['REQUEST_URI']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if ($email && $password !== '') {
        $user = authenticateUser($email, $password);
        
        if ($user) {
            $_SESSION['user'] = $user;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            
            // Redirect based on role
            if ($user['role'] === 'superadmin' || $user['role'] === 'admin') {
                header('Location: admin/consultation-dashboard.php');
            } else {
                header('Location: index.php?login=success&name=' . urlencode($user['fullname']));
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Valenzuela PCMS</title>
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
                        <img src="https://placehold.co/100x100/ffffff/0033a0?text=Seal" alt="Seal" class="w-full h-full object-cover opacity-80">
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
                    <div class="text-center mb-8">
                        <h1 class="text-3xl font-bold text-slate-900 mb-2">Sign In</h1>
                        <p class="text-slate-500">Welcome back to your Citizen Dashboard</p>
                    </div>

                    <?php if ($signup_success) { ?>
                        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-start gap-3">
                            <i class="fa-solid fa-circle-check mt-0.5"></i>
                            <div>
                                <strong class="block text-sm font-bold">Account Created Successfully!</strong>
                                <span class="text-sm">You can now sign in with your credentials.</span>
                            </div>
                        </div>
                    <?php } ?>

                    <?php if ($error) { ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-start gap-3">
                            <i class="fa-solid fa-circle-xmark mt-0.5"></i>
                            <div>
                                <strong class="block text-sm font-bold">Sign In Failed</strong>
                                <span class="text-sm"><?php echo $error; ?></span>
                            </div>
                        </div>
                    <?php } ?>

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
