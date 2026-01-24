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

        /* HERO STYLES */
        .hero {
            background: linear-gradient(135deg, #991b1b, #7f1d1d);
            padding: 4rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            gap: 3rem;
        }

        @media (max-width: 1024px) {
            .hero {
                padding: 3rem 1.5rem;
                gap: 2rem;
            }
        }

        @media (max-width: 768px) {
            .hero {
                flex-direction: column;
                text-align: center;
                padding: 2.5rem 1.5rem;
                gap: 2rem;
            }
        }

        @media (max-width: 480px) {
            .hero {
                padding: 1.5rem 1rem;
                gap: 1.5rem;
            }
        }

        .hero-content {
            flex: 1;
            min-width: 0;
        }

        .hero h2 {
            font-size: 3rem;
            line-height: 1.1;
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
            border: 2px solid white;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            white-space: nowrap;
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

        /* Red-to-white glow behind image */
        .hero-illustration {
            position: relative;
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            min-width: 0;
        }

        .hero-illustration::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom right, #991b1b 10%, #ffffff 100%);
            filter: blur(90px);
            opacity: 0.7;
            z-index: -1;
        }

        .illustration-img {
            width: 100%;
            max-width: 420px;
            height: auto;
            border-radius: 12px;
            mix-blend-mode: screen;
            filter: drop-shadow(0 10px 30px rgba(0,0,0,0.2));
        }

        @media (max-width: 1024px) {
            .illustration-img {
                max-width: 350px;
            }
        }

        @media (max-width: 768px) {
            .illustration-img {
                max-width: 280px;
                height: auto;
            }
        }

        @media (max-width: 480px) {
            .illustration-img {
                max-width: 220px;
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
        <a href="login.php"><button class="signin-btn">LOG-IN</button></a>
        <a href="login.php?create=1"><button class="signup-btn">REGISTER</button></a>
    </div>
</header>

<!-- HERO SECTION -->
<section class="hero">
    <div class="hero-content fade-in">
        <h2 style="font-size: clamp(1.5rem, 8vw, 3rem); margin-bottom: 1rem;">Tayo na, Valenzuela!</h2>
        <p style="font-size: clamp(0.95rem, 2.5vw, 1rem); margin-bottom: 1rem;">Shape the Future of Legislation Through Public Participation</p>

        <p class="opacity-90 leading-relaxed">
            Digital na Konsultasyon tungo sa Mas Bukas na Pamamahala, kung saan ang Boses ng Valenzuelano ang Gabay ng Pamahalaan.
        </p>

        <div style="margin-top: 1.5rem; display: flex; justify-content: center;">
            <a href="login.php?create=1" class="hero-button">
                REGISTER NOW
            </a>
        </div>
    </div>

    <div class="hero-illustration fade-in-delay">
        <img src="images/public cons.JPG" class="illustration-img" alt="Consultation">
    </div>
</section>

<!-- ABOUT SECTION -->
<section id="about" class="py-12 md:py-20 px-4 md:px-8 bg-white">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8 md:mb-12">
            <h2 style="font-size: clamp(1.5rem, 6vw, 2.25rem); font-weight: 700; color: #991b1b; margin-bottom: 1rem;">About the Public Consultation Portal</h2>
            <p style="font-size: clamp(0.9rem, 2vw, 1.125rem); color: #4b5563; max-width: 48rem; margin-left: auto; margin-right: auto; padding: 0 0.5rem;">
                Empowering Valenzuelanos to shape the future through meaningful participation in governance
            </p>
        </div>

        <!-- Main Description Card -->
        <div class="bg-gradient-to-r from-red-50 to-red-100 rounded-lg p-6 md:p-8 mb-8 md:mb-12 border-l-4 border-red-900">
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
    </div>
</section>

</body>
</html>
