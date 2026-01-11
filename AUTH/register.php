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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fade-in-up { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fade-in 0.6s ease-out forwards; }
        .animate-fade-in-up { animation: fade-in-up 0.6s ease-out forwards; }
    </style>
</head>
<body class="bg-gradient-to-br from-red-50 via-white to-red-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-6 animate-fade-in">
            <div class="inline-flex items-center justify-center mb-3">
                <div class="bg-white rounded-full shadow-xl flex items-center justify-center overflow-hidden" style="width: 100px; height: 100px;">
                    <img src="images/logo.webp" alt="Valenzuela" class="w-full h-full object-contain p-2">
                </div>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Create Account</h1>
            <p class="text-sm text-gray-600 mt-1">Public Consultation Management Portal — City of Valenzuela</p>
        </div>

        <div class="bg-white rounded-xl shadow-xl p-6 animate-fade-in-up">
            <?php if ($message): ?>
                <div class="mb-4 px-4 py-3 rounded-lg text-sm <?= strpos($message, 'created') !== false ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700' ?>">
                    <i class="bi <?= strpos($message, 'created') !== false ? 'bi-check-circle' : 'bi-exclamation-circle' ?> mr-2"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="register.php" class="space-y-4">
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" name="fullname" placeholder="Enter your full name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" required>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" placeholder="Enter your email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" required>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <input type="password" name="password" placeholder="Minimum 8 characters" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" required>
                        <button type="button" onclick="togglePasswordVisibility()" class="absolute right-3 top-2.5 text-gray-500 hover:text-gray-700">
                            <i class="bi bi-eye text-lg" id="eye-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <div class="relative">
                        <input type="password" id="confirmPassword" placeholder="Confirm your password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" required>
                        <button type="button" onclick="toggleConfirmPasswordVisibility()" class="absolute right-3 top-2.5 text-gray-500 hover:text-gray-700">
                            <i class="bi bi-eye text-lg" id="confirm-eye-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" class="w-4 h-4 text-red-600 border-gray-300 rounded" required>
                        <span class="ml-2 text-sm text-gray-600">I agree to the <span class="text-red-600 font-medium">Terms and Conditions</span></span>
                    </label>
                </div>
                <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 transition-colors font-medium mb-2">Create Account</button>
                <a href="login.php" class="block text-center text-sm text-gray-600 hover:text-red-600">Back to Sign In</a>
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
    </script>
</body>
</html>
