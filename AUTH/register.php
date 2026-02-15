<?php
require_once __DIR__ . '/../db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    if (!$fullname || !$email || !$password) {
        $message = "All fields required";
    } else {
        // Check duplicate email
        $check = $conn->prepare("SELECT id FROM users WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "Email already exists";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'citizen'; // Default role for all new users

            $stmt = $conn->prepare(
                "INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("ssss", $fullname, $email, $hash, $role);

            $message = $stmt->execute()
                ? "Account created. You may login."
                : "Registration failed";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#dc2626">
    <title>Create Account - PCMP | City of Valenzuela</title>
    <link rel="icon" type="image/webp" href="images/logo.webp">
    <link rel="apple-touch-icon" href="images/logo.webp">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../ASSETS/vendor/bootstrap-icons/font/bootstrap-icons.css">
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
        @media (max-width: 640px) {
            input, button, select, textarea {
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-red-50 via-white to-red-50 min-h-screen flex items-center justify-center p-3 md:p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-6 md:mb-8 animate-fade-in">
            <div class="inline-flex items-center justify-center mb-3 md:mb-4">
                <div class="bg-white rounded-full shadow-xl flex items-center justify-center overflow-hidden" style="width: 90px; height: 90px;">
                    <img src="../images/logo.webp" alt="Valenzuela" class="w-full h-full object-contain p-2">
                </div>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Create Account</h1>
            <p class="text-xs md:text-sm text-gray-600 mt-2">Public Consultation Management Portal</p>
            <p class="text-xs md:text-sm text-red-600 font-medium mt-1">City of Valenzuela</p>
        </div>

        <div class="bg-white rounded-xl md:rounded-2xl shadow-xl p-5 md:p-8 animate-fade-in-up">
            <?php if ($message): ?>
                <div class="mb-4 px-4 py-3 rounded-lg text-sm <?= strpos($message, 'created') !== false ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700' ?>">
                    <i class="bi <?= strpos($message, 'created') !== false ? 'bi-check-circle' : 'bi-exclamation-circle' ?> mr-2"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="register.php" class="space-y-4 md:space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name</label>
                    <input type="text" name="fullname" placeholder="Enter your full name" class="w-full px-3 md:px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 transition text-base" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input type="email" name="email" placeholder="your.email@lgu.gov.ph" class="w-full px-3 md:px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 transition text-base" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                    <div class="relative">
                        <input type="password" name="password" placeholder="Minimum 8 characters" class="w-full px-3 md:px-4 py-2.5 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 transition text-base" required>
                        <button type="button" onclick="togglePasswordVisibility()" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 transition">
                            <i class="bi bi-eye text-lg" id="eye-icon"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
                    <div class="relative">
                        <input type="password" id="confirmPassword" placeholder="Confirm your password" class="w-full px-3 md:px-4 py-2.5 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 transition text-base" required>
                        <button type="button" onclick="toggleConfirmPasswordVisibility()" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 transition">
                            <i class="bi bi-eye text-lg" id="confirm-eye-icon"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="agreeCheckbox" class="w-4 h-4 text-red-600 border-gray-300 rounded" required>
                        <span class="ml-2 text-sm text-gray-600">I agree to the <button type="button" onclick="openTermsModal()" class="text-red-600 font-medium hover:underline transition">Terms and Conditions</button></span>
                    </label>
                </div>
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-2.5 md:py-3 rounded-lg transition duration-200 font-medium shadow-md hover:shadow-lg">Create Account</button>
                <a href="login.php" class="block text-center text-sm text-gray-600 hover:text-red-600 transition font-medium">Back to Sign In</a>
            </form>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const password = document.querySelector('input[name="password"]');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (password.type === 'password') {
                password.type = 'text';
                eyeIcon.classList.remove('bi-eye');
                eyeIcon.classList.add('bi-eye-slash');
            } else {
                password.type = 'password';
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
            }
        }

        function toggleConfirmPasswordVisibility() {
            const confirmPassword = document.getElementById('confirmPassword');
            const confirmEyeIcon = document.getElementById('confirm-eye-icon');
            
            if (confirmPassword.type === 'password') {
                confirmPassword.type = 'text';
                confirmEyeIcon.classList.remove('bi-eye');
                confirmEyeIcon.classList.add('bi-eye-slash');
            } else {
                confirmPassword.type = 'password';
                confirmEyeIcon.classList.remove('bi-eye-slash');
                confirmEyeIcon.classList.add('bi-eye');
            }
        }

        function openTermsModal() {
            document.getElementById('termsModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeTermsModal() {
            document.getElementById('termsModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function agreeToTerms() {
            document.getElementById('agreeCheckbox').checked = true;
            closeTermsModal();
        }

        // Close modal when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('termsModal');
            modal?.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeTermsModal();
                }
            });
        });
    </script>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-xl max-w-2xl w-full my-8 max-h-[80vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="sticky top-0 bg-red-600 text-white px-6 py-4 flex items-center justify-between border-b">
                <h2 class="text-xl md:text-2xl font-bold">Terms and Conditions</h2>
                <button type="button" onclick="closeTermsModal()" class="text-2xl hover:opacity-80 transition">&times;</button>
            </div>

            <!-- Modal Content -->
            <div class="p-6 md:p-8 text-gray-700 text-sm md:text-base leading-relaxed space-y-4">
                <h3 class="text-lg font-bold text-gray-800">Public Consultation Management Portal</h3>
                <p class="text-xs md:text-sm text-gray-500">City Government of Valenzuela</p>

                <hr class="my-4">

                <h4 class="font-bold text-gray-800">1. Acceptance of Terms</h4>
                <p>By registering for and using the Public Consultation Management Portal (PCMP), you agree to comply with and be bound by these Terms and Conditions. If you do not agree to these terms, please do not use the platform.</p>

                <h4 class="font-bold text-gray-800">2. User Responsibilities</h4>
                <p>As a registered user, you agree to:</p>
                <ul class="list-disc list-inside space-y-1 ml-2">
                    <li>Provide accurate and complete information during registration</li>
                    <li>Maintain the confidentiality of your login credentials</li>
                    <li>Use the platform only for lawful purposes</li>
                    <li>Not engage in any form of harassment, abuse, or discriminatory behavior</li>
                    <li>Not attempt to gain unauthorized access to the system</li>
                </ul>

                <h4 class="font-bold text-gray-800">3. Acceptable Use Policy</h4>
                <p>You agree not to:</p>
                <ul class="list-disc list-inside space-y-1 ml-2">
                    <li>Post or transmit any illegal, threatening, abusive, or defamatory content</li>
                    <li>Spam or flood the platform with repetitive messages</li>
                    <li>Attempt to disrupt or interfere with the platform's normal operation</li>
                    <li>Use automated tools to access or interact with the system without authorization</li>
                    <li>Share personal information of other users without consent</li>
                </ul>

                <h4 class="font-bold text-gray-800">4. Intellectual Property Rights</h4>
                <p>All content provided on the PCMP, including documents, ordinances, and consultation materials, remains the property of the City Government of Valenzuela. You may view and download content for personal, non-commercial use only.</p>

                <h4 class="font-bold text-gray-800">5. User-Generated Content</h4>
                <p>By submitting comments, feedback, or any content to the platform, you grant the City Government of Valenzuela the right to use, modify, and distribute your submissions in accordance with local laws and policies.</p>

                <h4 class="font-bold text-gray-800">6. Privacy and Data Protection</h4>
                <p>Your personal information is collected and processed in accordance with the Data Privacy Act of 2012 (RA 10173). The City Government of Valenzuela is committed to protecting your privacy and ensuring the security of your data.</p>

                <h4 class="font-bold text-gray-800">7. Limitation of Liability</h4>
                <p>The City Government of Valenzuela shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use of the PCMP or inability to access the platform.</p>

                <h4 class="font-bold text-gray-800">8. Modifications to Terms</h4>
                <p>The City Government of Valenzuela reserves the right to modify these Terms and Conditions at any time. Continued use of the platform following any modifications constitutes your acceptance of the updated terms.</p>

                <h4 class="font-bold text-gray-800">9. Termination of Account</h4>
                <p>The City Government of Valenzuela reserves the right to suspend or terminate any user account that violates these terms or engages in prohibited activities.</p>

                <h4 class="font-bold text-gray-800">10. Governing Law</h4>
                <p>These Terms and Conditions shall be governed by and construed in accordance with the laws of the Republic of the Philippines.</p>

                <hr class="my-4">

                <p class="text-xs text-gray-500">Last Updated: January 2026</p>
            </div>

            <!-- Modal Footer -->
            <div class="sticky bottom-0 bg-gray-50 border-t px-6 py-4 flex gap-3">
                <button type="button" onclick="closeTermsModal()" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg font-medium text-gray-700 hover:bg-gray-100 transition">
                    Decline
                </button>
                <button type="button" onclick="agreeToTerms()" class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition">
                    I Agree
                </button>
            </div>
        </div>
    </div>
</body>
</html>
