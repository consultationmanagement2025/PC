<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#dc2626">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Login - PCMP | City of Valenzuela</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="images/logo.webp">
    <link rel="apple-touch-icon" href="images/logo.webp">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        /* Animation Keyframes */
        @keyframes fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes bounce-in {
            0% { opacity: 0; transform: scale(0.3); }
            50% { opacity: 1; transform: scale(1.05); }
            70% { opacity: 1; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        .animate-fade-in { 
            animation: fade-in 0.6s ease-out forwards; 
        }
        .animate-fade-in-up { 
            animation: fade-in-up 0.6s ease-out forwards; 
        }
        .animate-bounce-in { 
            animation: bounce-in 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards; 
        }
        .animate-shake {
            animation: shake 0.5s ease-in-out;
        }
        .animation-delay-100 { animation-delay: 100ms; }
        .animation-delay-200 { animation-delay: 200ms; }
        .animation-delay-300 { animation-delay: 300ms; }
        .animation-delay-400 { animation-delay: 400ms; }
        
        /* Prevent zoom on input focus in iOS */
        @media screen and (max-width: 767px) {
            input, select, textarea { font-size: 16px !important; }
        }
        
        /* Custom focus styles */
        .input-field:focus {
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }
        
        /* Loading spinner */
        .spinner {
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-red-50 via-white to-red-50 min-h-screen flex items-center justify-center p-3 md:p-4">
    <div class="w-full max-w-md">
        <!-- Logo Section -->
        <div class="text-center mb-6 md:mb-8 animate-fade-in">
            <div class="inline-flex items-center justify-center mb-3 md:mb-4 animate-bounce-in">
                <div class="bg-white rounded-full shadow-xl flex items-center justify-center overflow-hidden transform hover:scale-105 transition-all duration-300" style="width: 120px; height: 120px;">
                    <img src="images/logo.webp" alt="City Government of Valenzuela" class="w-full h-full object-contain p-2">
                </div>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 animate-fade-in-up animation-delay-100">PCMP</h1>
            <p class="text-sm md:text-base text-gray-600 mt-1 md:mt-2 animate-fade-in-up animation-delay-200">Public Consultation Management Portal</p>
            <p class="text-xs md:text-sm text-red-600 font-semibold mt-1 animate-fade-in-up animation-delay-300">City Government of Valenzuela</p>
            <p class="text-xs text-gray-500 animate-fade-in-up animation-delay-400">Metropolitan Manila</p>
        </div>
        
        <!-- Login Card -->
        <div class="bg-white rounded-xl md:rounded-2xl shadow-xl p-5 md:p-8 animate-fade-in-up animation-delay-300 transform hover:shadow-2xl transition-all duration-300">
            <div class="mb-4 md:mb-6 text-center">
                <h2 class="text-xl md:text-2xl font-bold text-gray-800">Welcome Back</h2>
                <p class="text-sm md:text-base text-gray-600 mt-1">Sign in to access your account</p>
            </div>
            
            <!-- Alert Messages -->
            <div id="alert-container" class="mb-4 hidden">
                <div id="alert-message" class="px-3 md:px-4 py-2 md:py-3 rounded-lg flex items-center text-sm">
                    <i class="bi mr-2" id="alert-icon"></i>
                    <span id="alert-text"></span>
                </div>
            </div>
            
            <!-- Login Form -->
            <form id="login-form" class="space-y-4 md:space-y-5" onsubmit="handleLogin(event)">
                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1 md:mb-2">
                        <i class="bi bi-envelope mr-1"></i>Email Address
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           required
                           placeholder="your.email@lgu.gov.ph"
                           class="input-field w-full px-3 md:px-4 py-2.5 md:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition text-base">
                    <span class="text-red-500 text-xs hidden mt-1" id="email-error"></span>
                </div>
                
                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1 md:mb-2">
                        <i class="bi bi-lock mr-1"></i>Password
                    </label>
                    <div class="relative">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required
                               placeholder="Enter your password"
                               class="input-field w-full px-3 md:px-4 py-2.5 md:py-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition text-base">
                        <button type="button" 
                                id="toggle-password" 
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 transition-colors">
                            <i class="bi bi-eye text-lg" id="eye-icon"></i>
                        </button>
                    </div>
                    <span class="text-red-500 text-xs hidden mt-1" id="password-error"></span>
                </div>
                
                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" 
                               name="remember" 
                               id="remember"
                               class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-2 focus:ring-red-500 cursor-pointer">
                        <span class="ml-2 text-sm text-gray-700">Remember me</span>
                    </label>
                    <button type="button" onclick="openForgotPasswordModal()" class="text-sm text-red-600 hover:text-red-700 font-medium transition-colors">
                        Forgot password?
                    </button>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" 
                        id="login-btn"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 md:py-3 rounded-lg transition duration-200 ease-in-out shadow-md hover:shadow-lg flex items-center justify-center">
                    <span id="login-btn-text">Sign In</span>
                    <i class="bi bi-arrow-right ml-2" id="login-btn-icon"></i>
                </button>
            </form>
            
            <!-- Quick Demo Login removed in production -->
            <!-- Divider -->
            <div class="relative my-5 md:my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white text-gray-500">Or continue with</span>
                </div>
            </div>
            
            <!-- Alternative Login Options -->
            <div class="grid grid-cols-2 gap-3">
                <button type="button" onclick="handleMicrosoftLogin()" class="flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all duration-200">
                    <i class="bi bi-microsoft text-lg mr-2 text-blue-600"></i>
                    <span class="text-sm font-medium text-gray-700">Microsoft</span>
                </button>
                <button type="button" onclick="handleGoogleLogin()" class="flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all duration-200">
                    <i class="bi bi-google text-lg mr-2 text-red-500"></i>
                    <span class="text-sm font-medium text-gray-700">Google</span>
                </button>
            </div>
            
            <!-- Register Link -->
            <div class="mt-5 md:mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Don't have an account? 
                    <button type="button" onclick="openCreateAccountModal()" class="text-red-600 hover:text-red-700 font-semibold transition-colors">
                        Create Account
                    </button>
                </p>
            </div>
        </div>
        
        <!-- Footer Info -->
        <div class="mt-6 md:mt-8 text-center text-xs md:text-sm text-gray-600">
            <p>&copy; 2025 City Government of Valenzuela. All rights reserved.</p>
            <div class="mt-2 space-x-2 md:space-x-4">
                <a href="#" onclick="openPrivacyPolicyModal(event)" class="hover:text-red-600 transition-colors">Privacy Policy</a>
                <span>•</span>
                <a href="#" onclick="openTermsOfServiceModal(event)" class="hover:text-red-600 transition-colors">Terms of Service</a>
                <span>•</span>
                <a href="#" onclick="openHelpModal(event)" class="hover:text-red-600 transition-colors">Help</a>
            </div>
        </div>
    </div>
    
    <script>
        // Google OAuth client ID for your app (set this to your registered Web client ID)
        // Example: window.GOOGLE_CLIENT_ID = '12345-abc.apps.googleusercontent.com';
        // Read from `window` or `localStorage` so you can configure at runtime without editing the file.
        window.GOOGLE_CLIENT_ID = window.GOOGLE_CLIENT_ID || localStorage.getItem('GOOGLE_CLIENT_ID') || '';

        // PKCE helpers for Google OAuth (used when GOOGLE_CLIENT_ID is provided)
        async function sha256(message) {
            const msgUint8 = new TextEncoder().encode(message);
            const hashBuffer = await crypto.subtle.digest('SHA-256', msgUint8);
            return new Uint8Array(hashBuffer);
        }

        function base64UrlEncode(arrayBuffer) {
            let str = '';
            const bytes = new Uint8Array(arrayBuffer);
            const len = bytes.byteLength;
            for (let i = 0; i < len; i++) {
                str += String.fromCharCode(bytes[i]);
            }
            return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        }

        async function pkceChallengeFromVerifier(verifier) {
            const hashed = await sha256(verifier);
            return base64UrlEncode(hashed);
        }

        function generateRandomString(length = 64) {
            const array = new Uint8Array(length);
            crypto.getRandomValues(array);
            return Array.from(array).map(b => ('0' + (b & 0xff).toString(16)).slice(-2)).join('');
        }

        // Exchange authorization code for tokens (PKCE)
        async function exchangeCodeForToken(code, codeVerifier, redirectUri, clientId) {
            const tokenEndpoint = 'https://oauth2.googleapis.com/token';
            const body = new URLSearchParams({
                grant_type: 'authorization_code',
                code: code,
                client_id: clientId,
                redirect_uri: redirectUri,
                code_verifier: codeVerifier
            });

            const res = await fetch(tokenEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            });
            if (!res.ok) throw new Error('Token exchange failed: ' + res.status + ' ' + res.statusText);
            return res.json();
        }

        function parseJwt (token) {
            try {
                const base64Url = token.split('.')[1];
                const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
                const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
                    return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                }).join(''));
                return JSON.parse(jsonPayload);
            } catch (e) { return null; }
        }

        // UI to configure Google Client ID in-browser for local testing
        function openGoogleClientModal() {
            const existing = localStorage.getItem('GOOGLE_CLIENT_ID') || '';
            const modal = `
                <div id="googleClientModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-lg shadow-2xl max-w-lg w-full">
                        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-3 flex items-center justify-between">
                            <h3 class="text-white font-semibold">Configure Google Sign-in</h3>
                            <button onclick="closeGoogleClientModal()" class="text-white text-xl">×</button>
                        </div>
                        <div class="p-6">
                            <p class="text-sm text-gray-700 mb-3">Enter your Google OAuth Client ID (Web application). Example: <span class="font-mono">12345-abc.apps.googleusercontent.com</span></p>
                            <input id="googleClientInput" type="text" value="${existing}" placeholder="Enter GOOGLE_CLIENT_ID" class="w-full px-3 py-2 border border-gray-300 rounded mb-3">
                            <div class="flex gap-2">
                                <button onclick="saveGoogleClientId()" class="bg-red-600 text-white px-4 py-2 rounded">Save & Continue</button>
                                <button onclick="closeGoogleClientModal()" class="border border-gray-300 px-4 py-2 rounded">Cancel</button>
                                <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="ml-auto text-sm text-blue-600 underline">Open Google Console</a>
                            </div>
                            <p class="text-xs text-gray-500 mt-3">Make sure your OAuth consent and redirect URI (this page URL) are configured in Google Cloud Console.</p>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modal);
            document.getElementById('googleClientInput').focus();
        }

        function closeGoogleClientModal() {
            const modal = document.getElementById('googleClientModal');
            if (modal) modal.remove();
        }

        function saveGoogleClientId() {
            const el = document.getElementById('googleClientInput');
            if (!el) return;
            const val = el.value.trim();
            if (!val) { showAlert('Please enter a Google Client ID.', 'error'); return; }
            if (!val.endsWith('.apps.googleusercontent.com')) {
                showAlert('This does not look like a valid Google Client ID. It should end with .apps.googleusercontent.com', 'error');
                return;
            }
            localStorage.setItem('GOOGLE_CLIENT_ID', val);
            window.GOOGLE_CLIENT_ID = val;
            showAlert('Google Client ID saved. Starting sign-in...', 'success');
            closeGoogleClientModal();
            // continue sign-in flow
            setTimeout(() => handleGoogleLogin(), 300);
        }

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

        // Receive OAuth result from popup
        window.addEventListener('message', function(ev) {
            try {
                if (ev.origin !== window.location.origin) return;
                const data = ev.data || {};
                if (data.type === 'google_oauth' && data.user) {
                    const user = data.user;
                    // Ensure user stored in llrm_users
                    try {
                        const users = JSON.parse(localStorage.getItem('llrm_users') || '[]');
                        if (!users.some(u => u.email === user.email)) {
                            users.push(user);
                            localStorage.setItem('llrm_users', JSON.stringify(users));
                        }
                    } catch (e) { console.warn('Error saving google user', e); }
                    // Add audit log entry
                    try {
                        const logs = JSON.parse(localStorage.getItem('llrm_auditLogs') || '[]');
                        logs.push({ id: 'audit_' + Date.now(), user: user.email, action: 'Google Sign-In', details: 'User signed in via Google popup', date: new Date().toISOString() });
                        localStorage.setItem('llrm_auditLogs', JSON.stringify(logs));
                    } catch (e) { console.warn('Error writing audit log', e); }
                    // Persist session and redirect
                    persistAndRedirect(user, 'user-portal.php');
                }
            } catch (e) { console.error('message handler error', e); }
        }, false);
        
        // Show alert message
        function showAlert(message, type = 'error') {
            const container = document.getElementById('alert-container');
            const alertMessage = document.getElementById('alert-message');
            const alertIcon = document.getElementById('alert-icon');
            const alertText = document.getElementById('alert-text');
            
            container.classList.remove('hidden');
            alertText.textContent = message;
            
            // Reset classes
            alertMessage.className = 'px-3 md:px-4 py-2 md:py-3 rounded-lg flex items-center text-sm';
            alertIcon.className = 'bi mr-2';
            
            if (type === 'success') {
                alertMessage.classList.add('bg-green-50', 'border', 'border-green-200', 'text-green-700');
                alertIcon.classList.add('bi-check-circle');
            } else if (type === 'error') {
                alertMessage.classList.add('bg-red-50', 'border', 'border-red-200', 'text-red-700');
                alertIcon.classList.add('bi-exclamation-circle');
            } else if (type === 'warning') {
                alertMessage.classList.add('bg-yellow-50', 'border', 'border-yellow-200', 'text-yellow-700');
                alertIcon.classList.add('bi-exclamation-triangle');
            }
        }
        
        // Hide alert
        function hideAlert() {
            document.getElementById('alert-container').classList.add('hidden');
        }
        
        // Handle login form submission (with extra debug logs)
        function handleLogin(event) {
            if (event && event.preventDefault) event.preventDefault();

            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const loginBtn = document.getElementById('login-btn');
            const loginBtnText = document.getElementById('login-btn-text');
            const loginBtnIcon = document.getElementById('login-btn-icon');
            const form = document.getElementById('login-form');

            console.log('[login] attempt', { email });

            // Clear previous errors
            hideAlert();
            document.getElementById('email-error').classList.add('hidden');
            document.getElementById('password-error').classList.add('hidden');

            // Basic validation
            if (!email) {
                showAlert('Please enter your email address', 'error');
                document.getElementById('email').focus();
                form.classList.add('animate-shake');
                setTimeout(() => form.classList.remove('animate-shake'), 500);
                return;
            }

            if (!password) {
                showAlert('Please enter your password', 'error');
                document.getElementById('password').focus();
                form.classList.add('animate-shake');
                setTimeout(() => form.classList.remove('animate-shake'), 500);
                return;
            }

            // Email format validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showAlert('Please enter a valid email address', 'error');
                document.getElementById('email').focus();
                return;
            }

            // Show loading state
            loginBtn.disabled = true;
            loginBtnText.textContent = 'Signing in...';
            loginBtnIcon.classList.remove('bi-arrow-right');
            loginBtnIcon.classList.add('spinner');
            loginBtnIcon.innerHTML = '';

            // Simulate login (check registered users first, then demo credentials)
            setTimeout(() => {
                let userObj = null;

                // Check registered users stored in localStorage
                let storedUsers = [];
                try { storedUsers = JSON.parse(localStorage.getItem('llrm_users') || '[]'); } catch (e) { storedUsers = []; }
                const matched = storedUsers.find(u => u.email === email && u.password === password);
                if (matched) {
                    userObj = matched;
                } else if (email === 'admin@lgu.gov.ph' && password === 'admin123') {
                    const existing = getExistingStoredUser(email);
                    userObj = existing || { email, name: 'Admin User', role: 'Administrator' };
                } else if (email === 'user101@lgu.gov.ph' && password === 'citizen2025') {
                    const existing = getExistingStoredUser(email);
                    userObj = existing || { email, name: 'Demo Citizen', role: 'Citizen' };
                } else if (email === 'user102@lgu.gov.ph' && password === 'citizen2026') {
                    const existing = getExistingStoredUser(email);
                    userObj = existing || { email, name: 'Demo Citizen 2', role: 'Citizen' };
                }

                if (userObj) {
                    // Decide redirect: only actual admins (or flagged isAdmin) go to admin dashboard.
                    const role = (userObj.role || '').toLowerCase();
                    const isAdmin = !!userObj.isAdmin;
                    if (isAdmin || role.includes('admin') || email === 'admin@lgu.gov.ph') {
                        persistAndRedirect(userObj, 'system-template-full.php');
                    } else {
                        // Citizens and other non-admin roles go to the citizen portal
                        persistAndRedirect(userObj, 'user-portal.php');
                    }
                    return;
                }

                // Reset button and show error
                loginBtn.disabled = false;
                loginBtnText.textContent = 'Sign In';
                loginBtnIcon.classList.remove('spinner');
                loginBtnIcon.classList.add('bi-arrow-right');
                loginBtnIcon.innerHTML = '';
                showAlert('Invalid email or password. Try: admin@lgu.gov.ph / admin123 or user101@lgu.gov.ph / citizen2025', 'error');
                form.classList.add('animate-shake');
                setTimeout(() => form.classList.remove('animate-shake'), 500);
            }, 800);
        }

        // Helper to persist user to storage and redirect, with logs
        function persistAndRedirect(userObj, target) {
            try {
                const remember = !!document.getElementById('remember').checked;
                if (remember) {
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('currentUser', JSON.stringify(userObj));
                    console.log('[login] stored in localStorage', userObj);
                } else {
                    sessionStorage.setItem('isLoggedIn', 'true');
                    sessionStorage.setItem('currentUser', JSON.stringify(userObj));
                    console.log('[login] stored in sessionStorage', userObj);
                }
                showAlert('Login successful! Redirecting...', 'success');
                setTimeout(() => { window.location.href = target; }, 300);
            } catch (err) {
                console.error('[login] error storing user', err);
                showAlert('Login failed: unable to save session. Check browser storage settings.', 'error');
            }
        }

        // Try to retrieve an existing stored user (localStorage first, then sessionStorage)
        function getExistingStoredUser(email) {
            try {
                const local = localStorage.getItem('currentUser');
                if (local) {
                    const parsed = JSON.parse(local);
                    if (parsed && parsed.email === email) return parsed;
                }
            } catch (e) { console.warn('[login] error reading localStorage currentUser', e); }
            try {
                const sess = sessionStorage.getItem('currentUser');
                if (sess) {
                    const parsed = JSON.parse(sess);
                    if (parsed && parsed.email === email) return parsed;
                }
            } catch (e) { console.warn('[login] error reading sessionStorage currentUser', e); }
            return null;
        }

        // Quick demo helpers

        // Forgot Password Modal Functions
        function openForgotPasswordModal() {
            const modal = `
                <div id="forgotPasswordModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-lg shadow-2xl max-w-md w-full">
                        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 flex items-center justify-between">
                            <h2 class="text-white text-lg font-bold">Reset Password</h2>
                            <button onclick="closeForgotPasswordModal()" class="text-white hover:text-gray-200 text-xl">×</button>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-600 text-sm mb-4">Enter your email address and we'll send you a link to reset your password.</p>
                            <input type="email" id="forgotEmail" placeholder="Enter your email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 mb-4">
                            <button onclick="sendPasswordReset()" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 transition-colors font-medium mb-3">Send Reset Link</button>
                            <button onclick="closeForgotPasswordModal()" class="w-full border border-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-50 transition-colors font-medium">Cancel</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modal);
        }

        function closeForgotPasswordModal() {
            const modal = document.getElementById('forgotPasswordModal');
            if (modal) modal.remove();
        }

        function sendPasswordReset() {
            const email = document.getElementById('forgotEmail').value.trim();
            if (!email || !email.includes('@')) {
                showAlert('Please enter a valid email address.', 'error');
                return;
            }

            // Check if email exists in user list (localStorage `llrm_users` or demo list)
            let users = [];
            try { users = JSON.parse(localStorage.getItem('llrm_users') || '[]'); } catch(e) { users = []; }
            const demo = [
                { email: 'admin@lgu.gov.ph' },
                { email: 'user101@lgu.gov.ph' },
                { email: 'user102@lgu.gov.ph' }
            ];
            const combined = users.concat(demo);
            const userExists = combined.some(u => u.email === email);
            if (!userExists) {
                showAlert('No account found with this email. Please check and try again.', 'warning');
                return;
            }

            // Simulate sending reset link
            showAlert('A password reset link has been sent to ' + email + '. Check your email for instructions.', 'success');
            closeForgotPasswordModal();
        }

        // Create Account Modal Functions
        function openCreateAccountModal() {
            const modal = `
                <div id="createAccountModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 overflow-y-auto">
                    <div class="bg-white rounded-lg shadow-2xl max-w-md w-full my-8">
                        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 flex items-center justify-between">
                            <h2 class="text-white text-lg font-bold">Create Account</h2>
                            <button onclick="closeCreateAccountModal()" class="text-white hover:text-gray-200 text-xl">×</button>
                        </div>
                        <div class="p-6">
                            <form id="createAccountForm" onsubmit="handleCreateAccount(event)">
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                    <input type="text" id="createName" placeholder="Enter your full name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" required>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input type="email" id="createEmail" placeholder="Enter your email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" required>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Organization/Role</label>
                                        <select id="createRole" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" required>
                                            <option value="">Select your role</option>
                                            <option value="citizen">Citizen</option>
                                            <option value="admin">Admin</option>
                                            <option value="organization">Organization</option>
                                        </select>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                    <div class="relative">
                                        <input type="password" id="createPassword" placeholder="Minimum 8 characters" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" required>
                                        <button type="button" onclick="toggleCreatePasswordVisibility()" class="absolute right-3 top-2.5 text-gray-500 hover:text-gray-700">
                                            <i class="bi bi-eye text-lg"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                                    <input type="password" id="createConfirmPassword" placeholder="Confirm your password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" required>
                                </div>
                                <div class="mb-4">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="checkbox" id="agreeTerms" class="w-4 h-4 text-red-600 border-gray-300 rounded" required>
                                        <span class="ml-2 text-sm text-gray-600">I agree to the <span class="text-red-600 font-medium">Terms and Conditions</span></span>
                                    </label>
                                </div>
                                <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 transition-colors font-medium mb-2">Create Account</button>
                                <button type="button" onclick="closeCreateAccountModal()" class="w-full border border-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-50 transition-colors font-medium">Cancel</button>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modal);
        }

        function closeCreateAccountModal() {
            const modal = document.getElementById('createAccountModal');
            if (modal) modal.remove();
        }

        function toggleCreatePasswordVisibility() {
            const password = document.getElementById('createPassword');
            if (password.type === 'password') {
                password.type = 'text';
            } else {
                password.type = 'password';
            }
        }

        function handleCreateAccount(event) {
            event.preventDefault();

            const name = document.getElementById('createName').value.trim();
            const email = document.getElementById('createEmail').value.trim();
            const role = document.getElementById('createRole').value;
            const password = document.getElementById('createPassword').value;
            const confirmPassword = document.getElementById('createConfirmPassword').value;

            // Validation
            if (!name || name.length < 3) {
                showAlert('Please enter a valid name (at least 3 characters).', 'error');
                return;
            }
            if (!email || !email.includes('@')) {
                showAlert('Please enter a valid email address.', 'error');
                return;
            }
            if (password.length < 8) {
                showAlert('Password must be at least 8 characters long.', 'error');
                return;
            }
            if (password !== confirmPassword) {
                showAlert('Passwords do not match. Please try again.', 'error');
                return;
            }

            // Check if email already exists
            let users = [];
            try { users = JSON.parse(localStorage.getItem('llrm_users') || '[]'); } catch(e) { users = []; }
            if (users.some(u => u.email === email) || ['admin@lgu.gov.ph','user101@lgu.gov.ph','user102@lgu.gov.ph'].includes(email)) {
                showAlert('An account with this email already exists. Please use a different email.', 'error');
                return;
            }

            // Create new user object
            const newUser = {
                id: 'user' + Date.now(),
                name: name,
                email: email,
                password: password,
                role: role,
                status: 'Active',
                createdDate: new Date().toLocaleDateString(),
                department: role === 'Citizen' ? 'General Public' : role,
                contactNumber: '09XX-XXX-XXXX'
            };

            // Save user (in real app, would save to backend)
            users.push(newUser);
            localStorage.setItem('llrm_users', JSON.stringify(users));

            // Success: persist user and redirect to appropriate dashboard
            showAlert('Account created successfully! Redirecting...', 'success');
            closeCreateAccountModal();
            // Redirect: citizens should go to the user portal; other roles may go to admin
            setTimeout(() => {
                if ((newUser.role || '').toLowerCase() === 'citizen') {
                    persistAndRedirect(newUser, 'user-portal.php');
                } else {
                    persistAndRedirect(newUser, 'system-template-full.php');
                }
            }, 700);
        }

        // OAuth Handler Functions
        function handleGoogleLogin() {
            // If a real GOOGLE_CLIENT_ID is configured, start PKCE + OAuth redirect flow.
            if (window.GOOGLE_CLIENT_ID && window.GOOGLE_CLIENT_ID.length > 8) {
                showAlert('Redirecting to Google sign-in...', 'info');
                const clientId = window.GOOGLE_CLIENT_ID;
                const redirectUri = window.location.origin + window.location.pathname;
                const state = generateRandomString(16);
                const codeVerifier = generateRandomString(64);
                pkceChallengeFromVerifier(codeVerifier).then(codeChallenge => {
                    // store verifier and state for callback
                    localStorage.setItem('google_oauth_state', state);
                    localStorage.setItem('google_oauth_code_verifier', codeVerifier);
                    const authUrl = new URL('https://accounts.google.com/o/oauth2/v2/auth');
                    authUrl.searchParams.set('client_id', clientId);
                    authUrl.searchParams.set('redirect_uri', redirectUri);
                    authUrl.searchParams.set('response_type', 'code');
                    authUrl.searchParams.set('scope', 'openid email profile');
                    authUrl.searchParams.set('state', state);
                    authUrl.searchParams.set('code_challenge', codeChallenge);
                    authUrl.searchParams.set('code_challenge_method', 'S256');
                    authUrl.searchParams.set('prompt', 'select_account');
                    // Open OAuth flow in a popup so user can choose their Google account
                    const popup = window.open(authUrl.toString(), 'google_oauth', 'width=520,height=700');
                    if (popup) popup.focus();
                }).catch(err => {
                    console.error('PKCE error', err);
                    showAlert('Unable to start Google sign-in (PKCE). See console for details.', 'error');
                });
                return;
            }

            // Fallback: allow user to configure a client id in-browser
            openGoogleClientModal();
        }

        function handleMicrosoftLogin() {
            showAlert('Microsoft Login: Redirecting to Microsoft authentication...', 'info');
            // Simulate Microsoft OAuth flow (in production, would use Microsoft OAuth API)
            setTimeout(() => {
                showAlert('Simulating Microsoft authentication. Signing in demo user...', 'success');
                const demoUser = { email: 'user102@lgu.gov.ph', name: 'Demo Citizen 2', role: 'Citizen' };
                persistAndRedirect(demoUser, 'user-portal.php');
            }, 1500);
        }

        // quickLoginUser helpers removed; demo OAuth now signs in demo users directly
        
        // Check for logout parameter
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('logout') === 'success') {
            showAlert('You have been logged out successfully.', 'success');
        }
        
        // If redirected back from Google OAuth with an authorization code, handle token exchange.
        (async function handleOAuthCallback() {
            try {
                const params = new URLSearchParams(window.location.search);
                const code = params.get('code');
                const state = params.get('state');
                if (code) {
                    const storedState = localStorage.getItem('google_oauth_state');
                    const codeVerifier = localStorage.getItem('google_oauth_code_verifier');
                    // remove stored values ASAP
                    localStorage.removeItem('google_oauth_state');
                    localStorage.removeItem('google_oauth_code_verifier');

                    if (!storedState || !codeVerifier || storedState !== state) {
                        console.warn('OAuth state mismatch or missing PKCE verifier');
                        showAlert('Google sign-in failed (state mismatch).', 'error');
                        return;
                    }
                    if (!window.GOOGLE_CLIENT_ID || window.GOOGLE_CLIENT_ID.length < 8) {
                        showAlert('Google client ID not configured. Cannot complete sign-in.', 'error');
                        return;
                    }
                    showAlert('Completing Google sign-in...', 'info');
                    const redirectUri = window.location.origin + window.location.pathname;
                    try {
                        const tokenResp = await exchangeCodeForToken(code, codeVerifier, redirectUri, window.GOOGLE_CLIENT_ID);
                        // tokenResp should contain id_token
                        const idToken = tokenResp.id_token;
                        const parsed = parseJwt(idToken) || {};
                        const email = parsed.email || '';
                        const name = parsed.name || parsed.email || 'Google User';
                        // create user object with advanced defaults for the system
                        const demoUser = {
                            id: 'user' + Date.now(),
                            email,
                            name,
                            role: 'Citizen',
                            createdDate: new Date().toLocaleDateString(),
                            createdVia: 'google',
                            preferences: { notifications: true, subscribedConsultations: true },
                            onboardingComplete: false,
                            google_token: tokenResp
                        };

                        // Persist to users store if not exists
                        try {
                            const users = JSON.parse(localStorage.getItem('llrm_users') || '[]');
                            if (!users.some(u => u.email === demoUser.email)) {
                                users.push(demoUser);
                                localStorage.setItem('llrm_users', JSON.stringify(users));
                            }
                        } catch (e) { console.warn('Error saving google user', e); }

                        // Add an audit log for the signup/sign-in
                        try {
                            const logs = JSON.parse(localStorage.getItem('llrm_auditLogs') || '[]');
                            logs.push({ id: 'audit_' + Date.now(), user: demoUser.email, action: 'Google Sign-In', details: 'User signed in/registered via Google', date: new Date().toISOString() });
                            localStorage.setItem('llrm_auditLogs', JSON.stringify(logs));
                        } catch (e) { console.warn('Error appending audit log', e); }

                        // If this page is opened in a popup (oauth popup), send result to opener and close
                        if (window.opener && window.opener !== window) {
                            try {
                                window.opener.postMessage({ type: 'google_oauth', user: demoUser }, window.location.origin);
                            } catch (e) { console.warn('postMessage failed', e); }
                            // Close popup
                            window.close();
                            return;
                        }

                        showAlert('Google sign-in successful. Redirecting...', 'success');
                        // clean URL
                        window.history.replaceState({}, document.title, redirectUri);
                        persistAndRedirect(demoUser, 'user-portal.php');
                    } catch (err) {
                        console.error('Token exchange error', err);
                        showAlert('Google token exchange failed. Check console for details.', 'error');
                    }
                }
            } catch (e) {
                console.error('OAuth callback handler error', e);
            }
        })();

        // Auto-focus email field
        document.getElementById('email').focus();

        // If page was opened with ?create=1 or ?create=true, auto-open the Create Account modal
        try {
            const params = new URLSearchParams(window.location.search);
            const createParam = (params.get('create') || '').toLowerCase();
            if (createParam === '1' || createParam === 'true') {
                // Slight delay to ensure modal functions are defined and DOM ready
                setTimeout(() => {
                    if (typeof openCreateAccountModal === 'function') openCreateAccountModal();
                }, 150);
            }
        } catch (e) { console.warn('URL param check failed', e); }

        // Privacy Policy Modal
        function openPrivacyPolicyModal(e) {
            e.preventDefault();
            const modal = `
                <div id="privacyModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 overflow-y-auto">
                    <div class="bg-white rounded-lg shadow-2xl max-w-2xl w-full my-8">
                        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 flex items-center justify-between sticky top-0">
                            <h2 class="text-white text-lg font-bold">Privacy Policy</h2>
                            <button onclick="document.getElementById('privacyModal').remove()" class="text-white hover:text-gray-200 text-xl">×</button>
                        </div>
                        <div class="p-6 space-y-4 max-h-96 overflow-y-auto">
                            <h3 class="font-bold text-gray-800">1. Introduction</h3>
                            <p class="text-sm text-gray-700">The City Government of Valenzuela ("we", "us", "our") operates the Public Consultation Management Portal. This page informs you of our policies regarding the collection, use, and disclosure of personal data when you use our Service.</p>
                            
                            <h3 class="font-bold text-gray-800">2. Information Collection and Use</h3>
                            <p class="text-sm text-gray-700">We collect several different types of information for various purposes to provide and improve our Service to you.</p>
                            <ul class="text-sm text-gray-700 list-disc list-inside space-y-1">
                                <li>Personal Data: Email address, name, phone number, address</li>
                                <li>Usage Data: Browser type, pages visited, time and date of visits, time spent on pages</li>
                                <li>Survey Data: Your responses to consultations and polls</li>
                            </ul>
                            
                            <h3 class="font-bold text-gray-800">3. Security of Data</h3>
                            <p class="text-sm text-gray-700">The security of your data is important to us, but remember that no method of transmission over the Internet or method of electronic storage is 100% secure. While we strive to use commercially acceptable means to protect your Personal Data, we cannot guarantee its absolute security.</p>
                            
                            <h3 class="font-bold text-gray-800">4. Contact Us</h3>
                            <p class="text-sm text-gray-700">If you have any questions about this Privacy Policy, please contact us at <strong>privacy@valenzuela.gov.ph</strong></p>
                        </div>
                        <div class="px-6 py-4 border-t text-center">
                            <button onclick="document.getElementById('privacyModal').remove()" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">Close</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modal);
        }

        // Terms of Service Modal
        function openTermsOfServiceModal(e) {
            e.preventDefault();
            const modal = `
                <div id="termsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 overflow-y-auto">
                    <div class="bg-white rounded-lg shadow-2xl max-w-2xl w-full my-8">
                        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 flex items-center justify-between sticky top-0">
                            <h2 class="text-white text-lg font-bold">Terms of Service</h2>
                            <button onclick="document.getElementById('termsModal').remove()" class="text-white hover:text-gray-200 text-xl">×</button>
                        </div>
                        <div class="p-6 space-y-4 max-h-96 overflow-y-auto">
                            <h3 class="font-bold text-gray-800">1. Agreement to Terms</h3>
                            <p class="text-sm text-gray-700">By accessing and using this Public Consultation Management Portal, you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.</p>
                            
                            <h3 class="font-bold text-gray-800">2. Use License</h3>
                            <p class="text-sm text-gray-700">Permission is granted to temporarily download one copy of the materials (information or software) on the Portal for personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer of title, and under this license you may not:</p>
                            <ul class="text-sm text-gray-700 list-disc list-inside space-y-1">
                                <li>Modify or copy the materials</li>
                                <li>Use the materials for any commercial purpose or for any public display</li>
                                <li>Attempt to decompile or reverse engineer any software</li>
                                <li>Remove any copyright or other proprietary notations</li>
                                <li>Transfer the materials to another person or "mirror" the materials on any other server</li>
                            </ul>
                            
                            <h3 class="font-bold text-gray-800">3. Disclaimer</h3>
                            <p class="text-sm text-gray-700">The materials on the Portal are provided on an 'as is' basis. The City Government of Valenzuela makes no warranties, expressed or implied, and hereby disclaims and negates all other warranties including, without limitation, implied warranties or conditions of merchantability, fitness for a particular purpose, or non-infringement of intellectual property or other violation of rights.</p>
                            
                            <h3 class="font-bold text-gray-800">4. Limitations</h3>
                            <p class="text-sm text-gray-700">In no event shall the City Government of Valenzuela or its suppliers be liable for any damages (including, without limitation, damages for loss of data or profit, or due to business interruption) arising out of the use or inability to use the materials on the Portal.</p>
                        </div>
                        <div class="px-6 py-4 border-t text-center">
                            <button onclick="document.getElementById('termsModal').remove()" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">Close</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modal);
        }

        // Help Modal
        function openHelpModal(e) {
            e.preventDefault();
            const modal = `
                <div id="helpModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 overflow-y-auto">
                    <div class="bg-white rounded-lg shadow-2xl max-w-2xl w-full my-8">
                        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 flex items-center justify-between sticky top-0">
                            <h2 class="text-white text-lg font-bold">Help & Support</h2>
                            <button onclick="document.getElementById('helpModal').remove()" class="text-white hover:text-gray-200 text-xl">×</button>
                        </div>
                        <div class="p-6 space-y-4 max-h-96 overflow-y-auto">
                            <h3 class="font-bold text-gray-800">Frequently Asked Questions</h3>
                            
                            <div>
                                <p class="font-semibold text-gray-800">Q: How do I create an account?</p>
                                <p class="text-sm text-gray-700 mt-1">A: Click the "Create Account" button on the login page, fill in your details, and select your role (Citizen, Admin, or Organization). Verify your email and you're ready to go!</p>
                            </div>
                            
                            <div>
                                <p class="font-semibold text-gray-800">Q: Forgot my password. What should I do?</p>
                                <p class="text-sm text-gray-700 mt-1">A: Click "Forgot password?" on the login page. Enter your email and we'll send you a password reset link.</p>
                            </div>
                            
                            <div>
                                <p class="font-semibold text-gray-800">Q: Can I use Google or Microsoft to sign in?</p>
                                <p class="text-sm text-gray-700 mt-1">A: Yes! Click the Google or Microsoft button on the login page to sign in with your existing account.</p>
                            </div>
                            
                            <div>
                                <p class="font-semibold text-gray-800">Q: How do I participate in consultations?</p>
                                <p class="text-sm text-gray-700 mt-1">A: After logging in, navigate to the Consultations section to view active consultations, leave comments, and participate in surveys.</p>
                            </div>
                            
                            <div>
                                <p class="font-semibold text-gray-800">Q: Who do I contact for technical issues?</p>
                                <p class="text-sm text-gray-700 mt-1">A: Please email us at <strong>support@valenzuela.gov.ph</strong> or call <strong>(02) 1234-5678</strong> during business hours.</p>
                            </div>
                        </div>
                        <div class="px-6 py-4 border-t text-center">
                            <button onclick="document.getElementById('helpModal').remove()" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">Close</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modal);
        }
    </script>
</body>
</html>
