<?php
session_start();
require_once '../db.php';
require_once __DIR__ . '/../config/google_oauth_config.php';

$_citizenOAuthState = bin2hex(random_bytes(16));
$_SESSION['citizen_google_oauth_state'] = $_citizenOAuthState;
$googleOAuthUrl = getGoogleAuthUrl($_citizenOAuthState);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms = isset($_POST['terms']);
    
    // Validation
    if (empty($fullname)) {
        $errors[] = 'Full name is required.';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (!$terms) {
        $errors[] = 'You must agree to the terms and conditions.';
    }
    
    if (empty($errors)) {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = 'An account with this email already exists.';
        } else {
            // Create new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'citizen';
            $username = strtolower(explode('@', $email)[0]);
            
            $stmt = $conn->prepare("INSERT INTO users (fullname, name, username, email, password, role, district, barangay, status, verification_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 'verified', NOW())");
            if ($stmt) {
                $stmt->bind_param('ssssssss', $fullname, $fullname, $username, $email, $hashed_password, $role, $district, $barangay);
            } else {
                $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, role, district, barangay, status, verification_status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', 'verified', NOW())");
                if ($stmt) {
                    $stmt->bind_param('ssssss', $fullname, $email, $hashed_password, $role, $district, $barangay);
                }
            }
            
            if ($stmt && $stmt->execute()) {
                $user_id = $stmt->insert_id;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['fullname'] = $fullname;
                $_SESSION['full_name'] = $fullname;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = $role;
                $_SESSION['verification_status'] = 'verified';
                
                header('Location: sign-in.php?signup=success');
                exit;
            } else {
                $errors[] = 'Failed to create account: ' . ($conn->error ?: ($stmt ? $stmt->error : 'Database error'));
            }
            if ($stmt) $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Valenzuela PCMS</title>
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
                <div class="p-8 lg:p-10 max-h-screen overflow-y-auto form-container">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-slate-800">Create Account</h2>
                        <p class="text-sm text-slate-500 mt-1">Join with Google in 1-click or fill in your details below.</p>
                    </div>

                    <!-- Prominent 1-Click Google SSO -->
                    <div class="mb-6">
                        <a href="<?php echo htmlspecialchars($googleOAuthUrl); ?>" class="w-full bg-white hover:bg-slate-50 text-slate-700 font-bold py-3.5 px-4 rounded-xl border border-slate-300 shadow-sm transition-all flex justify-center items-center gap-3 hover:shadow-md hover:border-slate-400 group">
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
                            <span class="flex-shrink mx-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">or sign up with email</span>
                            <div class="flex-grow border-t border-slate-200"></div>
                        </div>
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

                        <!-- Valenzuela Residency & District Verification -->
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-map-location-dot text-valenzuela-blue"></i>
                                <h3 class="text-sm font-bold text-slate-800">Valenzuela District & Barangay Verification</h3>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label for="district" class="block text-xs font-semibold text-slate-700 mb-1">Legislative District</label>
                                    <select id="district" name="district" class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-valenzuela-blue outline-none text-sm bg-white" onchange="onDistrictSelectChange()">
                                        <option value="">-- Select District --</option>
                                        <option value="District 1" <?php echo (isset($_POST['district']) && $_POST['district'] === 'District 1') ? 'selected' : ''; ?>>District 1 (1st District)</option>
                                        <option value="District 2" <?php echo (isset($_POST['district']) && $_POST['district'] === 'District 2') ? 'selected' : ''; ?>>District 2 (2nd District)</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="barangay" class="block text-xs font-semibold text-slate-700 mb-1">Barangay</label>
                                    <select id="barangay" name="barangay" class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-valenzuela-blue outline-none text-sm bg-white" onchange="onBarangaySelectChange()">
                                        <option value="">-- Select Barangay --</option>
                                        <optgroup label="District 1 (24 Barangays)" id="optgroup-d1">
                                            <option value="Arkong Bato" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Arkong Bato') ? 'selected' : ''; ?>>Arkong Bato</option>
                                            <option value="Balangkas" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Balangkas') ? 'selected' : ''; ?>>Balangkas</option>
                                            <option value="Bignay" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Bignay') ? 'selected' : ''; ?>>Bignay</option>
                                            <option value="Bisig" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Bisig') ? 'selected' : ''; ?>>Bisig</option>
                                            <option value="Canumay East" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Canumay East') ? 'selected' : ''; ?>>Canumay East</option>
                                            <option value="Canumay West" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Canumay West') ? 'selected' : ''; ?>>Canumay West</option>
                                            <option value="Coloong" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Coloong') ? 'selected' : ''; ?>>Coloong</option>
                                            <option value="Dalandanan" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Dalandanan') ? 'selected' : ''; ?>>Dalandanan</option>
                                            <option value="Isla" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Isla') ? 'selected' : ''; ?>>Isla</option>
                                            <option value="Lawang Bato" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Lawang Bato') ? 'selected' : ''; ?>>Lawang Bato</option>
                                            <option value="Lingunan" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Lingunan') ? 'selected' : ''; ?>>Lingunan</option>
                                            <option value="Mabolo" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Mabolo') ? 'selected' : ''; ?>>Mabolo</option>
                                            <option value="Malanday" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Malanday') ? 'selected' : ''; ?>>Malanday</option>
                                            <option value="Malinta" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Malinta') ? 'selected' : ''; ?>>Malinta</option>
                                            <option value="Mapulang Lupa" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Mapulang Lupa') ? 'selected' : ''; ?>>Mapulang Lupa</option>
                                            <option value="Palasan" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Palasan') ? 'selected' : ''; ?>>Palasan</option>
                                            <option value="Pariancillo Villa" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Pariancillo Villa') ? 'selected' : ''; ?>>Pariancillo Villa</option>
                                            <option value="Pasolo" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Pasolo') ? 'selected' : ''; ?>>Pasolo</option>
                                            <option value="Poblacion" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Poblacion') ? 'selected' : ''; ?>>Poblacion</option>
                                            <option value="Punturin" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Punturin') ? 'selected' : ''; ?>>Punturin</option>
                                            <option value="Rincon" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Rincon') ? 'selected' : ''; ?>>Rincon</option>
                                            <option value="Tagalag" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Tagalag') ? 'selected' : ''; ?>>Tagalag</option>
                                            <option value="Veinte Reales" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Veinte Reales') ? 'selected' : ''; ?>>Veinte Reales</option>
                                            <option value="Wawang Pulo" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Wawang Pulo') ? 'selected' : ''; ?>>Wawang Pulo</option>
                                        </optgroup>
                                        <optgroup label="District 2 (9 Barangays)" id="optgroup-d2">
                                            <option value="Bagbaguin" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Bagbaguin') ? 'selected' : ''; ?>>Bagbaguin</option>
                                            <option value="Gen. T. de Leon" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Gen. T. de Leon') ? 'selected' : ''; ?>>Gen. T. de Leon</option>
                                            <option value="Karuhatan" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Karuhatan') ? 'selected' : ''; ?>>Karuhatan</option>
                                            <option value="Marulas" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Marulas') ? 'selected' : ''; ?>>Marulas</option>
                                            <option value="Maysan" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Maysan') ? 'selected' : ''; ?>>Maysan</option>
                                            <option value="Parada" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Parada') ? 'selected' : ''; ?>>Parada</option>
                                            <option value="Paso de Blas" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Paso de Blas') ? 'selected' : ''; ?>>Paso de Blas</option>
                                            <option value="Ugong" <?php echo (isset($_POST['barangay']) && $_POST['barangay'] === 'Ugong') ? 'selected' : ''; ?>>Ugong</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>

                            <!-- Live Verification Badge -->
                            <div id="district-verification-badge" class="hidden p-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-xs flex items-center gap-2">
                                <i class="fa-solid fa-circle-check text-green-600 text-sm"></i>
                                <span><strong id="verified-barangay-text">Barangay</strong> verified under <strong id="verified-district-text">District 1</strong> of Valenzuela City.</span>
                            </div>
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

            // District & Barangay Verification Logic
            window.DISTRICT_MAP = {
                'Arkong Bato': 'District 1', 'Balangkas': 'District 1', 'Bignay': 'District 1', 'Bisig': 'District 1',
                'Canumay East': 'District 1', 'Canumay West': 'District 1', 'Coloong': 'District 1', 'Dalandanan': 'District 1',
                'Isla': 'District 1', 'Lawang Bato': 'District 1', 'Lingunan': 'District 1', 'Mabolo': 'District 1',
                'Malanday': 'District 1', 'Malinta': 'District 1', 'Mapulang Lupa': 'District 1', 'Palasan': 'District 1',
                'Pariancillo Villa': 'District 1', 'Pasolo': 'District 1', 'Poblacion': 'District 1', 'Punturin': 'District 1',
                'Rincon': 'District 1', 'Tagalag': 'District 1', 'Veinte Reales': 'District 1', 'Wawang Pulo': 'District 1',
                'Bagbaguin': 'District 2', 'Gen. T. de Leon': 'District 2', 'Karuhatan': 'District 2', 'Marulas': 'District 2',
                'Maysan': 'District 2', 'Parada': 'District 2', 'Paso de Blas': 'District 2', 'Ugong': 'District 2'
            };

            window.onBarangaySelectChange = function() {
                const barangaySelect = document.getElementById('barangay');
                const districtSelect = document.getElementById('district');
                const badge = document.getElementById('district-verification-badge');
                const selectedBarangay = barangaySelect ? barangaySelect.value : '';

                if (selectedBarangay && window.DISTRICT_MAP[selectedBarangay]) {
                    const detectedDistrict = window.DISTRICT_MAP[selectedBarangay];
                    if (districtSelect) districtSelect.value = detectedDistrict;
                    const bEl = document.getElementById('verified-barangay-text');
                    const dEl = document.getElementById('verified-district-text');
                    if (bEl) bEl.textContent = selectedBarangay;
                    if (dEl) dEl.textContent = detectedDistrict;
                    if (badge) badge.classList.remove('hidden');
                } else if (badge) {
                    badge.classList.add('hidden');
                }
            };

            window.onDistrictSelectChange = function() {
                const districtSelect = document.getElementById('district');
                const barangaySelect = document.getElementById('barangay');
                const selectedDistrict = districtSelect ? districtSelect.value : '';
                
                if (barangaySelect && barangaySelect.value) {
                    if (window.DISTRICT_MAP[barangaySelect.value] !== selectedDistrict) {
                        barangaySelect.value = '';
                        const badge = document.getElementById('district-verification-badge');
                        if (badge) badge.classList.add('hidden');
                    }
                }
            };

            // Initial trigger if values pre-populated
            if (document.getElementById('barangay')?.value) {
                window.onBarangaySelectChange();
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
