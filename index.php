<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="theme-color" content="#dc2626">
    <title>Public Consultation</title>

    <link rel="icon" type="image/png" href="images/logo.webp">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        * {
            margin: 0; padding: 0; box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #1f2937;
            background: #f3f4f6;
            font-weight: 400;
            letter-spacing: -0.3px;
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            font-weight: 700;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        p {
            font-weight: 400;
        }

        strong, .font-bold {
            font-weight: 600;
        }

        html {
            scroll-behavior: smooth; /* enables slow scroll */
        }

        header {
            background: #fff;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 50;
            flex-wrap: wrap;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            header {
                padding: 0.75rem 1rem;
                justify-content: space-between;
            }
        }

        @media (max-width: 480px) {
            header {
                padding: 0.5rem 0.75rem;
            }
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
            flex: 1;
        }

        .logo-section img {
            width: 45px; height: 45px;
            border-radius: 50%;
            background: white;
            padding: 2px;
            flex-shrink: 0;
        }

        .logo-section h1 {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 1rem;
        }

        @media (max-width: 480px) {
            .logo-section h1 {
                font-size: 0.875rem;
            }
            
            .logo-section p {
                font-size: 0.625rem;
            }
        }

        nav {
            display: flex;
            gap: 0.5rem;
        }

        nav a {
            text-decoration: none;
            color: #666;
            padding: 4px 0;
            margin: 0 12px;
            border-bottom: 2px solid transparent;
            font-weight: 500;
            font-size: 13px;
            white-space: nowrap;
        }

        @media (max-width: 1024px) {
            nav a {
                margin: 0 8px;
                font-size: 12px;
            }
        }

        @media (max-width: 768px) {
            nav {
                display: none;
            }

            nav a {
                margin: 0 4px;
                font-size: 11px;
            }
        }

        nav a:hover,
        nav a.active {
            color: #991b1b;
            border-bottom-color: #991b1b;
        }

        .header-buttons {
            display: flex;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .signin-btn, .signup-btn {
            background: #991b1b;
            color: white;
            border: none;
            padding: 0.55rem 1.4rem;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            white-space: nowrap;
        }

        @media (max-width: 1024px) {
            .signin-btn, .signup-btn {
                padding: 0.5rem 1rem;
                font-size: 12px;
            }
        }

        @media (max-width: 768px) {
            .signin-btn, .signup-btn {
                padding: 0.5rem 0.9rem;
                font-size: 11px;
            }
        }

        @media (max-width: 480px) {
            .signin-btn, .signup-btn {
                padding: 0.45rem 0.75rem;
                font-size: 10px;
            }
        }

        .signin-btn:hover, .signup-btn:hover {
            background: #7f1d1d;
        }

        /* HERO STYLES - Split-screen and full-height (use image as background) */
        .hero.full-screen {
            /* darker on the left for text contrast, lighter on the right to show the photo */
            background: linear-gradient(90deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.6) 35%, rgba(0,0,0,0.55) 70%), url('images/val.jpg') center right / cover no-repeat;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: center;
            color: white;
            gap: 2rem;
            padding: 3rem 2rem;
        }

        @media (max-width: 1024px) {
            .hero.full-screen {
                grid-template-columns: 1fr;
                min-height: auto;
                padding: 3rem 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .hero.full-screen {
                padding: 2.5rem 1.5rem;
                gap: 2rem;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .hero.full-screen {
                padding: 1.5rem 1rem;
                gap: 1.5rem;
            }
        }

        .hero-content {
            flex: 1;
            min-width: 0;
            text-shadow: 0 6px 18px rgba(0,0,0,0.45);
            font-family: 'Poppins', 'Inter', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            max-width: 640px;
            text-align: left;
            padding-left: 1rem;
            /* subtle translucent panel to help text stand out on busy photos */
            background: rgba(0,0,0,0.18);
            padding: 24px 20px;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .hero-content { text-align: center; padding-left: 0; background: transparent; padding: 0; }
            .hero-content h2 { margin-top: 0.25rem; }
        }

        /* center the participate button inside the hero content panel */
        .hero-content .hero-button-wrap { width: 100%; display: flex; justify-content: center; margin-top: 1.5rem; gap: 1rem; }

        .hero h2 {
            font-family: 'Poppins', 'Inter', system-ui;
            font-size: 3rem;
            line-height: 1.1;
            color: #ffffff;
            font-weight: 700;
        }

        @media (max-width: 1024px) {
            .hero h2 {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 768px) {
            .hero h2 {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .hero h2 {
                font-size: 1.5rem;
            }
        }

        .hero p {
            font-size: 1rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            color: rgba(255,255,255,0.95);
        }

        @media (max-width: 768px) {
            .hero p {
                font-size: 0.95rem;
                margin-bottom: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .hero p {
                font-size: 0.875rem;
                margin-bottom: 0.5rem;
                line-height: 1.4;
            }
        }

        .hero-button {
            border: 2px solid #ffffff;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            white-space: nowrap;
            background: transparent;
        }

        @media (max-width: 480px) {
            .hero-button {
                padding: 0.6rem 1.2rem;
                font-size: 0.8rem;
                border-width: 1.5px;
            }
        }

        .hero-button:hover {
            background: white;
            color: #991b1b;
        }

        .hero .flex {
            justify-content: center;
        }

        .hero a {
            display: inline-block;
        }

        /* Fade-in transitions */
        .fade-in {
            opacity: 0;
            animation: fadeIn 1.2s ease forwards;
        }

        .fade-in-delay {
            opacity: 0;
            animation: fadeIn 1.2s ease 0.4s forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Enhanced hero image with fade effect */
        /* hide the extra image element (we're using the background image instead) */
        .hero-illustration { display: none; }
        .illustration-img { display: none; }

        /* make hero text a bit more readable on top of the photo */
        .hero-content { text-shadow: 0 6px 18px rgba(0,0,0,0.45); }

        @media (max-width: 1024px) {
            .illustration-img {
                max-width: 400px;
                height: auto;
            }
            .hero-illustration {
                height: auto;
            }
        }

        @media (max-width: 768px) {
            .illustration-img {
                max-width: 320px;
                height: auto;
            }
            .hero-illustration {
                height: auto;
            }
        }

        @media (max-width: 480px) {
            .illustration-img {
                max-width: 240px;
            }
        }
    </style>
</head>

<body>

<!-- HEADER -->
<header>
    <div class="logo-section">
        <img src="images/logo.webp" alt="Logo">
        <div>
            <h1 class="text-lg font-bold text-red-900 leading-tight">Public Consultation</h1>
            <p class="text-xs text-gray-600">Valenzuela City Government</p>
        </div>
    </div>

    <nav>
        <a href="#" class="active">HOME</a>
        <a href="#about">ABOUT</a> <!-- smooth scroll -->
    </nav>

    <div class="header-buttons">
        <a href="login.php"><button class="signin-btn">ADMIN LOGIN</button></a>
        <a href="public-portal.php"><button class="signup-btn">PARTICIPATE</button></a>
    </div>
</header>

<!-- HERO SECTION -->
<section class="hero full-screen">
    <div class="hero-content fade-in">
        <h2 style="font-size: clamp(1.5rem, 8vw, 3rem); margin-bottom: 1rem;">Tayo na, Valenzuela!</h2>
        <p style="font-size: clamp(0.95rem, 2.5vw, 1rem); margin-bottom: 1rem;">Shape the Future of Legislation Through Public Participation</p>

        <p class="opacity-90 leading-relaxed">
            Digital na Konsultasyon tungo sa Mas Bukas na Pamamahala, kung saan ang Boses ng Valenzuelano ang Gabay ng Pamahalaan.
        </p>

        <div class="hero-button-wrap">
            <a href="public-portal.php" class="hero-button">
                PARTICIPATE NOW
            </a>
        </div>
    </div>

    <div class="hero-illustration fade-in-delay">
        <img src="images/val.jpg" class="illustration-img" alt="Valenzuela">
    </div>
</section>

<!-- ABOUT SECTION -->
<section id="about" class="py-12 md:py-20 px-4 md:px-8 bg-white full-screen">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8 md:mb-12">
            <h2 style="font-size: clamp(1.5rem, 6vw, 2.25rem); font-weight: 700; color: #991b1b; margin-bottom: 1rem;">About the Public Consultation Portal</h2>
            <p style="font-size: clamp(0.9rem, 2vw, 1.125rem); color: #4b5563; max-width: 48rem; margin-left: auto; margin-right: auto; padding: 0 0.5rem;">
                Empowering Valenzuelanos to shape the future through meaningful participation in governance
            </p>
        </div>

        <!-- Main Description Card -->
        <div class="bg-gradient-to-r from-red-50 to-red-100 rounded-lg p-6 md:p-8 mb-8 md:mb-12 shadow-sm">
            <p style="color: #374151; line-height: 1.6; margin-bottom: 1rem; font-size: clamp(0.9rem, 2vw, 1.125rem);">
                The Public Consultation Portal is the official digital platform of the Valenzuela City 
                Government designed to gather citizen insights, preferences, and concerns on proposed 
                ordinances, programs, and local policies.
            </p>
            <p style="color: #374151; line-height: 1.6; font-size: clamp(0.9rem, 2vw, 1.125rem);">
                It promotes transparency, inclusion, and data-driven governance by allowing every 
                Valenzuelano to participate in shaping legislation anytime, anywhere.
            </p>
        </div>

        <!-- Features Grid -->
        <div class="mb-8 md:mb-12">
            <h3 style="font-size: clamp(1.25rem, 5vw, 1.5rem); font-weight: 700; color: #1f2937; margin-bottom: 1.5rem; text-align: center;">Key Features</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                <div class="bg-red-50 p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900">
                    <i class="bi bi-chat-left-dots text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; font-size: clamp(0.95rem, 2vw, 1rem);">Review & Comment</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem);">Review and comment on draft laws with detailed analysis and insights</p>
                </div>

                <div class="bg-red-50 p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900">
                    <i class="bi bi-graph-up text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; font-size: clamp(0.95rem, 2vw, 1rem);">Participate in Surveys</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem);">Take part in digital consultations and surveys shaping our city</p>
                </div>

                <div class="bg-red-50 p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900">
                    <i class="bi bi-file-earmark-check text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; font-size: clamp(0.95rem, 2vw, 1rem);">Track Progress</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem);">Track ordinance status and see how they develop over time</p>
                </div>

                <div class="bg-red-50 p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900">
                    <i class="bi bi-shield-check text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; font-size: clamp(0.95rem, 2vw, 1rem);">Verified Information</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem);">Access verified and official city government information</p>
                </div>

                <div class="bg-red-50 p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900">
                    <i class="bi bi-globe text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; font-size: clamp(0.95rem, 2vw, 1rem);">Multilingual</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem);">Supports multiple languages for inclusive participation</p>
                </div>
            </div>
        </div>

        <!-- Commitment Section -->
        <div class="bg-gradient-to-r from-red-900 to-red-800 text-white rounded-lg p-6 md:p-8 text-center">
            <h3 style="font-size: clamp(1.25rem, 5vw, 1.5rem); font-weight: 700; margin-bottom: 1rem;">Our Commitment</h3>
            <p style="font-size: clamp(0.9rem, 2vw, 1.125rem); line-height: 1.6;">
                Guided by principles of transparency, accountability, and citizen empowerment, this 
                platform strengthens collaboration between the government and its people through secure 
                and modern digital participation.
            </p>
        </div>

        <!-- Security (planned / future use) -->
        <div class="mt-6 mb-12 rounded-lg p-4 bg-white/5" style="backdrop-filter: blur(4px);">
            <h4 style="font-weight:700; color:#1f2937; margin-bottom:0.5rem;">Security (planned / future use)</h4>
            <p style="color:#374151; margin-bottom:0.5rem;">Planned security and privacy features for the Public Consultation Portal include:</p>
            <ul style="color:#374151; line-height:1.6;">
                <li>• HTTPS / TLS for all traffic</li>
                <li>• Role-based access control for admin functions</li>
                <li>• Two-factor authentication / OTP for sensitive actions</li>
                <li>• Audit logging for content and user actions</li>
                <li>• Data protection and compliance with local privacy law</li>
                <li>• Encryption-at-rest for sensitive data (planned)</li>
            </ul>
        </div>

    </div>
</section>

<!-- Privacy Notice Modal -->
<div id="privacyModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-2xl max-w-md w-full max-h-[80vh] overflow-y-auto" style="animation: slideUp 0.3s ease-out;">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-red-900 to-red-800 text-white p-4 flex justify-between items-center sticky top-0">
            <h2 class="text-lg font-bold">Privacy Notice</h2>
            <button onclick="closePrivacyModal()" class="text-white hover:bg-red-700 p-1 rounded transition">
                <i class="bi bi-x-lg text-xl"></i>
            </button>
        </div>

        <!-- Modal Content -->
        <div class="p-5 text-sm text-gray-700">
            <div class="mb-4">
                <h3 class="font-bold text-red-900 mb-2">Personal Information Collection</h3>
                <p class="mb-3 leading-relaxed">
                    The CGOV collects personal information when you sign up, open an account, or electronically submit to us for any inquiries or requests to provide you with better service.
                </p>
            </div>

            <div class="mb-4">
                <h3 class="font-bold text-red-900 mb-2">Information Collected</h3>
                <ul class="space-y-1 ml-4">
                    <li class="flex items-start gap-2">
                        <span class="text-red-900 mt-1">•</span>
                        <span>Full name</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-red-900 mt-1">•</span>
                        <span>Complete address</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-red-900 mt-1">•</span>
                        <span>Contact number</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-red-900 mt-1">•</span>
                        <span>E-mail address</span>
                    </li>
                </ul>
            </div>

            <div class="mb-4">
                <h3 class="font-bold text-red-900 mb-2">Data Protection</h3>
                <p class="leading-relaxed">
                    Your personal information is protected under the Data Privacy Act of 2012 and handled with care. We ensure secure processing and storage of your data.
                </p>
            </div>

            <div class="mb-4 p-3 bg-red-50 rounded border-l-4 border-red-900">
                <p class="text-xs font-semibold text-red-900">
                    <i class="bi bi-shield-check mr-1"></i> DPO/DPS Registered
                </p>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="border-t p-4 flex gap-3 bg-gray-50 sticky bottom-0">
            <button onclick="closePrivacyModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 rounded transition">
                Close
            </button>
            <button onclick="closePrivacyModal(); window.location.href='#about'" class="flex-1 bg-red-900 hover:bg-red-800 text-white font-semibold py-2 rounded transition">
                Accept & Continue
            </button>
        </div>
    </div>
</div>

<style>
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
</style>

<script>
    // Show privacy modal on page load
    window.addEventListener('load', function() {
        const privacyDismissed = localStorage.getItem('privacyNoticeDismissed');
        if (!privacyDismissed) {
            document.getElementById('privacyModal').style.display = 'flex';
        } else {
            document.getElementById('privacyModal').style.display = 'none';
        }
    });

    function closePrivacyModal() {
        localStorage.setItem('privacyNoticeDismissed', 'true');
        document.getElementById('privacyModal').style.display = 'none';
    }

    // Close modal if clicking outside of it
    document.getElementById('privacyModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closePrivacyModal();
        }
    });
</script>

</body>
</html>
