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
    <link rel="stylesheet" href="ASSETS/vendor/bootstrap-icons/font/bootstrap-icons.css">

    <style>
        * {
            margin: 0; padding: 0; box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #1f2937;
            background: #f3f4f6;
            font-weight: 400;
            letter-spacing: -0.3px;
            line-height: 1.6;
        }

        /* ensure bootstrap icons render correctly */
        .bi {
            font-family: 'bootstrap-icons' !important;
            speak: none;
            font-style: normal;
            font-weight: 400;
            font-variant: normal;
            text-transform: none;
            line-height: 1;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
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
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            /* subtle translucent panel to help text stand out on busy photos */
            background: rgba(0,0,0,0.18);
            padding: 24px 20px;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .hero-content { text-align: center; padding-left: 0; background: transparent; padding: 0; align-items: center; }
            .hero-content h2 { margin-top: 0.25rem; }
        }

        /* center the participate button inside the hero content panel */
        .hero-content .hero-button-wrap { width: 100%; display: flex; justify-content: flex-start; align-items: center; margin-top: 1.5rem; gap: 1rem; }
        
        @media (max-width: 768px) {
            .hero-content .hero-button-wrap { justify-content: center; }
        }

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
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            white-space: nowrap;
            background: transparent;
            font-family: 'Poppins', system-ui;
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
<section id="about" class="py-12 md:py-20 px-4 md:px-8 bg-gradient-to-b from-white to-gray-50 full-screen">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8 md:mb-16">
            <div style="display: inline-block; background: #fee2e2; padding: 0.5rem 1rem; border-radius: 6px; margin-bottom: 1rem;">
                <p style="color: #991b1b; font-weight: 700; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">About Our Platform</p>
            </div>
            <h2 style="font-size: clamp(1.5rem, 6vw, 2.5rem); font-weight: 800; color: #1f2937; margin-bottom: 1rem; line-height: 1.2;">About the Public Consultation Portal</h2>
            <div style="height: 4px; width: 60px; background: linear-gradient(90deg, #991b1b, #7f1d1d); margin: 1.5rem auto; border-radius: 2px;"></div>
            <p style="font-size: clamp(0.95rem, 2vw, 1.1rem); color: #4b5563; max-width: 48rem; margin-left: auto; margin-right: auto; padding: 0 0.5rem; line-height: 1.7;">
                Empowering Valenzuelanos to shape the future through meaningful participation in governance
            </p>
        </div>

        <!-- Main Description Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 3rem; align-items: center;">
            <div>
                <h3 style="font-size: clamp(1.1rem, 3vw, 1.3rem); font-weight: 800; color: #1f2937; margin-bottom: 1.5rem;">What We Do</h3>
                <p style="color: #374151; line-height: 1.8; margin-bottom: 1.5rem; font-size: clamp(0.9rem, 2vw, 1rem);">
                    The Public Consultation Portal is the official digital platform of the Valenzuela City Government designed to gather citizen insights, preferences, and concerns on proposed ordinances, programs, and local policies.
                </p>
                <ul style="color: #374151; line-height: 1.8; font-size: clamp(0.85rem, 2vw, 0.95rem); margin: 0; padding: 0; list-style: none;">
                    <li style="padding: 0.5rem 0;"><i class="bi bi-check-circle-fill" style="color: #991b1b; margin-right: 0.75rem;"></i>Transparent governance</li>
                    <li style="padding: 0.5rem 0;"><i class="bi bi-check-circle-fill" style="color: #991b1b; margin-right: 0.75rem;"></i>Inclusive participation</li>
                    <li style="padding: 0.5rem 0;"><i class="bi bi-check-circle-fill" style="color: #991b1b; margin-right: 0.75rem;"></i>Data-driven decision making</li>
                    <li style="padding: 0.5rem 0;"><i class="bi bi-check-circle-fill" style="color: #991b1b; margin-right: 0.75rem;"></i>Real-time engagement</li>
                </ul>
            </div>
            <div style="background: linear-gradient(135deg, rgba(153, 27, 27, 0.08), rgba(127, 29, 29, 0.08)); padding: 2.5rem; border-radius: 12px; border-left: 5px solid #991b1b;">
                <p style="color: #374151; line-height: 1.8; margin-bottom: 1rem; font-size: clamp(0.9rem, 2vw, 1rem);">
                    It promotes transparency, inclusion, and data-driven governance by allowing every Valenzuelano to participate in shaping legislation anytime, anywhere.
                </p>
                <p style="color: #374151; line-height: 1.8; font-size: clamp(0.9rem, 2vw, 1rem); margin: 0;">
                    Our platform strengthens collaboration between the government and its people through secure and modern digital participation.
                </p>
            </div>
        </div>

        <!-- Features Grid -->
        <div class="mb-8 md:mb-16">
            <h3 style="font-size: clamp(1.25rem, 5vw, 1.5rem); font-weight: 800; color: #1f2937; margin-bottom: 2rem; text-align: center;">Key Features</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                <div class="bg-white p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <i class="bi bi-chat-left-dots text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; font-size: clamp(0.95rem, 2vw, 1rem);">Review & Comment</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem); margin: 0;">Review and comment on draft laws with detailed analysis and insights</p>
                </div>

                <div class="bg-white p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <i class="bi bi-graph-up text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; font-size: clamp(0.95rem, 2vw, 1rem);">Participate in Surveys</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem); margin: 0;">Take part in digital consultations and surveys shaping our city</p>
                </div>

                <div class="bg-white p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <i class="bi bi-file-earmark-check text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; font-size: clamp(0.95rem, 2vw, 1rem);">Track Progress</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem); margin: 0;">Track ordinance status and see how they develop over time</p>
                </div>

                <div class="bg-white p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <i class="bi bi-shield-check text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; font-size: clamp(0.95rem, 2vw, 1rem);">Verified Information</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem); margin: 0;">Access verified and official city government information</p>
                </div>

                <div class="bg-white p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <i class="bi bi-globe text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; font-size: clamp(0.95rem, 2vw, 1rem);">Multilingual</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem); margin: 0;">Supports multiple languages for inclusive participation</p>
                </div>

                <div class="bg-white p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <i class="bi bi-lightning-fill text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; font-size: clamp(0.95rem, 2vw, 1rem);">Real-time Notifications</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem); margin: 0;">Stay updated with instant notifications on consultations</p>
                </div>
            </div>
        </div>

        <!-- Commitment Section -->
        <div style="background: linear-gradient(135deg, #991b1b, #7f1d1d); color: white; border-radius: 12px; padding: 3rem 2.5rem; text-align: center; margin-bottom: 3rem; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <h3 style="font-size: clamp(1.25rem, 5vw, 1.5rem); font-weight: 800; margin-bottom: 1rem;">Our Commitment to You</h3>
            <p style="font-size: clamp(0.9rem, 2vw, 1.1rem); line-height: 1.8; max-width: 48rem; margin-left: auto; margin-right: auto;">
                Guided by principles of transparency, accountability, and citizen empowerment, we are committed to ensuring everyone's voice is heard and valued.
            </p>
        </div>

        <!-- Security Features Section - User focused only -->
        <div>
            <div style="text-align: center; margin-bottom: 2rem;">
                <h3 style="font-size: clamp(1.25rem, 5vw, 1.5rem); font-weight: 800; color: #1f2937; margin-bottom: 0.5rem;">Your Privacy & Security</h3>
                <p style="color: #4b5563; font-size: clamp(0.9rem, 2vw, 1rem);">We protect your data with industry standards and comply with data protection laws</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                <div class="bg-white p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-l-4 border-red-900" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <i class="bi bi-shield-lock text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.75rem; font-size: clamp(0.95rem, 2vw, 1rem);">Secure Connection</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem); margin: 0; line-height: 1.6;">All data transmitted using encrypted HTTPS/TLS connection</p>
                </div>

                <div class="bg-white p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-l-4 border-red-900" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <i class="bi bi-file-earmark-shield text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.75rem; font-size: clamp(0.95rem, 2vw, 1rem);">Data Privacy</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem); margin: 0; line-height: 1.6;">Compliance with Republic Act 10173 (Data Privacy Act of 2012)</p>
                </div>

                <div class="bg-white p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-l-4 border-red-900" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <i class="bi bi-lock text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.75rem; font-size: clamp(0.95rem, 2vw, 1rem);">Protected Information</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem); margin: 0; line-height: 1.6;">Your personal data is securely stored and protected from unauthorized access</p>
                </div>

                <div class="bg-white p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-l-4 border-red-900" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <i class="bi bi-envelope-check text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.75rem; font-size: clamp(0.95rem, 2vw, 1rem);">Verified Submissions</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem); margin: 0; line-height: 1.6;">Email verification ensures authentic and legitimate public participation</p>
                </div>

                <div class="bg-white p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-l-4 border-red-900" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <i class="bi bi-eye-slash text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.75rem; font-size: clamp(0.95rem, 2vw, 1rem);">Anonymous Options</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem); margin: 0; line-height: 1.6;">Your feedback is valued regardless of optional personal details</p>
                </div>

                <div class="bg-white p-5 md:p-6 rounded-lg hover:shadow-lg transition duration-300 border-l-4 border-red-900" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <i class="bi bi-info-circle text-2xl md:text-3xl text-red-900 mb-3"></i>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.75rem; font-size: clamp(0.95rem, 2vw, 1rem);">Transparency</h4>
                    <p style="color: #4b5563; font-size: clamp(0.85rem, 1.8vw, 0.875rem); margin: 0; line-height: 1.6;">We're committed to open and honest government engagement</p>
                </div>
            </div>
        </div>
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
