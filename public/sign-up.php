<?php
session_start();

// Redirect to admin login for unified authentication/registration
header('Location: ../admin/login.php');
exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Valenzuela PCMS</title>
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
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        body {
            animation: fadeIn 0.6s ease-out;
        }
        
        .page-container {
            animation: fadeIn 0.6s ease-out;
        }
        
        .sidebar {
            animation: slideInLeft 0.7s ease-out;
        }
        
        .form-container {
            animation: slideInRight 0.7s ease-out;
        }
        
        button {
            transition: all 0.3s ease;
        }
        
        a {
            transition: all 0.3s ease;
        }
        
        input, textarea, select {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 font-sans">
    <div class="min-h-screen flex items-center justify-center px-4 py-10 page-container">
        <div class="w-full max-w-5xl bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-200">
            <div class="grid md:grid-cols-2">
                <div class="bg-gradient-to-br from-valenzuela-blue to-blue-900 text-white p-8 lg:p-10 flex flex-col justify-between sidebar">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-blue-200">Public Portal</p>
                        <h1 class="text-3xl font-bold mt-3">Join the Citizen Dashboard</h1>
                        <p class="text-blue-100 mt-4 leading-relaxed">Create an account to participate in local governance, review consultations, and submit your proposals for the community.</p>
                    </div>
                    <div class="mt-8 space-y-3">
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-circle-check text-blue-300 mt-1"></i>
                            <p class="text-sm text-blue-100">Review active consultations</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-circle-check text-blue-300 mt-1"></i>
                            <p class="text-sm text-blue-100">Participate in community surveys</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-circle-check text-blue-300 mt-1"></i>
                            <p class="text-sm text-blue-100">Submit your own proposals</p>
                        </div>
                    </div>
                </div>

                <div class="p-8 lg:p-10 max-h-screen overflow-y-auto form-container">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-slate-800">Create Account</h2>
                        <p class="text-sm text-slate-500 mt-2">Fill in your details below to get started.</p>
                    </div>

                    <?php if (!empty($errors)) { ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                            <p class="font-semibold text-sm mb-2">Please fix the following:</p>
                            <ul class="text-sm space-y-1">
                                <?php foreach ($errors as $error) { ?>
                                    <li><i class="fa-solid fa-circle-xmark mr-2"></i><?php echo $error; ?></li>
                                <?php } ?>
                            </ul>
                        </div>
                    <?php } ?>

                    <form method="POST" class="space-y-4" id="signup-form" novalidate>
                        <div>
                            <label for="fullname" class="block text-sm font-semibold text-slate-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" id="fullname" name="fullname" value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none transition-all text-sm" placeholder="Juan Dela Cruz">
                            <div id="fullname-error" class="text-red-500 text-xs font-semibold mt-1 hidden flex items-center gap-1">
                                <i class="fa-solid fa-exclamation-circle"></i> Please enter your full name
                            </div>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                            <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none transition-all text-sm" placeholder="juan@example.com">
                            <div id="email-error" class="text-red-500 text-xs font-semibold mt-1 hidden flex items-center gap-1">
                                <i class="fa-solid fa-exclamation-circle"></i> <span id="email-error-text">Please enter a valid email address</span>
                            </div>
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-semibold text-slate-700 mb-1">Phone Number (Optional)</label>
                            <input type="tel" id="phone" name="phone" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none transition-all text-sm" placeholder="+63 912 345 6789" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>

                        <div>
                            <label for="address" class="block text-sm font-semibold text-slate-700 mb-1">Address (Optional)</label>
                            <textarea id="address" name="address" rows="2" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none transition-all text-sm resize-none" placeholder="Your address in Valenzuela"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">Password <span class="text-red-500">*</span></label>
                                <input type="password" id="password" name="password" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none transition-all text-sm" placeholder="Min. 6 characters">
                                <div id="password-error" class="text-red-500 text-xs font-semibold mt-1 hidden flex items-center gap-1">
                                    <i class="fa-solid fa-exclamation-circle"></i> <span id="password-error-text">Password must be at least 6 characters</span>
                                </div>
                            </div>
                            <div>
                                <label for="confirm_password" class="block text-sm font-semibold text-slate-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
                                <input type="password" id="confirm_password" name="confirm_password" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none transition-all text-sm" placeholder="Re-enter password">
                                <div id="confirm_password-error" class="text-red-500 text-xs font-semibold mt-1 hidden flex items-center gap-1">
                                    <i class="fa-solid fa-exclamation-circle"></i> Passwords do not match
                                </div>
                            </div>
                        </div>

                        <div class="flex items-start gap-2 text-sm">
                            <input type="checkbox" id="terms" name="terms" class="mt-1">
                            <label for="terms" class="text-slate-600">I agree to the <a href="#" class="text-valenzuela-blue font-semibold hover:underline">Terms of Use</a> and <a href="#" class="text-valenzuela-blue font-semibold hover:underline">Privacy Policy</a></label>
                        </div>
                        <div id="terms-error" class="text-red-500 text-xs font-semibold hidden flex items-center gap-1">
                            <i class="fa-solid fa-exclamation-circle"></i> Please agree to the terms
                        </div>

                        <button type="submit" class="w-full bg-valenzuela-red hover:bg-red-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition-colors flex justify-center items-center gap-2 mt-6">
                            <i class="fa-solid fa-user-plus"></i> Create Account
                        </button>
                    </form>

                    <div class="mt-6 text-sm text-center text-slate-500">
                        Already have an account? <a href="sign-in.php" class="text-valenzuela-blue font-semibold hover:underline">Sign In</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Interactive Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Mobile Menu Toggle
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');

            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', () => {
                    mobileMenu.classList.toggle('hidden');
                });

                // Close mobile menu on link click
                mobileMenu.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', () => {
                        mobileMenu.classList.add('hidden');
                    });
                });
            }

            // File Upload Display Name
            const fileInput = document.getElementById('file-upload');
            const fileNameDisplay = document.getElementById('file-name-display');

            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    if(this.files && this.files.length > 0) {
                        const fileNames = Array.from(this.files).map(f => f.name).join(', ');
                        fileNameDisplay.textContent = 'Selected: ' + fileNames;
                        fileNameDisplay.classList.remove('hidden');
                    } else {
                        fileNameDisplay.classList.add('hidden');
                    }
                });
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
