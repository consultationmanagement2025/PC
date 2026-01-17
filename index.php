<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="theme-color" content="#dc2626">
    <title>Public Consultation</title>

    <link rel="icon" type="image/png" href="images/logo.webp">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        * {
            margin: 0; padding: 0; box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            color: #333;
            background: #f3f4f6;
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
        }

        @media (max-width: 768px) {
            header {
                padding: 0.75rem 1rem;
            }
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo-section img {
            width: 50px; height: 50px;
            border-radius: 50%;
            background: white;
            padding: 2px;
        }

        nav a {
            text-decoration: none;
            color: #666;
            padding: 4px 0;
            margin: 0 15px;
            border-bottom: 2px solid transparent;
            font-weight: 500;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            nav {
                display: none;
            }

            nav a {
                margin: 0 8px;
                font-size: 12px;
            }
        }

        nav a:hover,
        nav a.active {
            color: #991b1b;
            border-bottom-color: #991b1b;
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
        }

        @media (max-width: 768px) {
            .signin-btn, .signup-btn {
                padding: 0.45rem 0.9rem;
                font-size: 11px;
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
                padding: 2rem 1rem;
                gap: 1.5rem;
            }

            .hero h2 {
                font-size: 2rem !important;
            }

            .hero p {
                font-size: 0.95rem !important;
            }
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
            border-radius: 12px;
            mix-blend-mode: screen;
            filter: drop-shadow(0 10px 30px rgba(0,0,0,0.2));
        }

        @media (max-width: 768px) {
            .illustration-img {
                max-width: 280px;
                height: auto;
            }
        }

        @media (max-width: 768px) {
            .hero {
                flex-direction: column;
                text-align: center;
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

    <div class="flex gap-3">
        <a href="login.php"><button class="signin-btn">LOG-IN</button></a>
        <a href="login.php?create=1" class="signup-btn">REGISTER</a>
    </div>
</header>

<!-- HERO SECTION -->
<section class="hero">
    <div class="hero-content fade-in">
        <h2 class="text-4xl font-bold mb-3">Tayo na, Valenzuela!</h2>
        <p class="text-md mb-4">Shape the Future of Legislation Through Public Participation</p>

        <p class="opacity-90 mb-6 leading-relaxed">
            Digital na Konsultasyon tungo sa Mas Bukas na Pamamahala, kung saan ang<br>
            Boses ng Valenzuelano ang Gabay ng Pamahalaan.
        </p>

        <div class="flex gap-3">

            <a href="login.php?create=1" class="border border-white px-6 py-3 rounded font-semibold hover:bg-white hover:text-red-900 inline-block">
                REGISTER NOW
            </a>
        </div>
    </div>

    <div class="hero-illustration fade-in-delay">
        <img src="images/public cons.JPG" class="illustration-img" alt="Consultation">
    </div>
</section>

<!-- ABOUT SECTION -->
<section id="about" class="py-20 px-8 bg-white">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-12">
            <h2 class="text-4xl font-bold text-red-900 mb-4">About the Public Consultation Portal</h2>
            <p class="text-gray-600 text-lg max-w-3xl mx-auto">
                Empowering Valenzuelanos to shape the future through meaningful participation in governance
            </p>
        </div>

        <!-- Main diba Description Card -->
        <div class="bg-gradient-to-r from-red-50 to-red-100 rounded-lg p-8 mb-12 border-l-4 border-red-900">
            <p class="text-gray-800 leading-relaxed text-lg mb-4">
                The Public Consultation Portal is the official digital platform of the Valenzuela City 
                Government designed to gather citizen insights, preferences, and concerns on proposed 
                ordinances, programs, and local policies.
            </p>
            <p class="text-gray-800 leading-relaxed text-lg">
                It promotes transparency, inclusion, and data-driven governance by allowing every 
                Valenzuelano to participate in shaping legislation anytime, anywhere.
            </p>
        </div>

        <!-- Features Grid -->

        <div class="mb-12">
            <h3 class="text-2xl font-bold text-gray-900 mb-8 text-center">Key Features</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-red-50 p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900">
                    <i class="bi bi-chat-left-dots text-3xl text-red-900 mb-3"></i>
                    <h4 class="font-bold text-gray-900 mb-2">Review & Comment</h4>
                    <p class="text-gray-700 text-sm">Review and comment on draft laws with detailed analysis and insights</p>
                </div>

                <div class="bg-red-50 p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900">
                    <i class="bi bi-graph-up text-3xl text-red-900 mb-3"></i>
                    <h4 class="font-bold text-gray-900 mb-2">Participate in Surveys</h4>
                    <p class="text-gray-700 text-sm">Take part in digital consultations and surveys shaping our city</p>
                </div>

                <div class="bg-red-50 p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900">
                    <i class="bi bi-file-earmark-check text-3xl text-red-900 mb-3"></i>
                    <h4 class="font-bold text-gray-900 mb-2">Track Progress</h4>
                    <p class="text-gray-700 text-sm">Track ordinance status and see how they develop over time</p>
                </div>

                <div class="bg-red-50 p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900">
                    <i class="bi bi-shield-check text-3xl text-red-900 mb-3"></i>
                    <h4 class="font-bold text-gray-900 mb-2">Verified Information</h4>
                    <p class="text-gray-700 text-sm">Access verified and official city government information</p>
                </div>

                <div class="bg-red-50 p-6 rounded-lg hover:shadow-lg transition duration-300 border-t-4 border-red-900">
                    <i class="bi bi-globe text-3xl text-red-900 mb-3"></i>
                    <h4 class="font-bold text-gray-900 mb-2">Multilingual</h4>
                    <p class="text-gray-700 text-sm">Supports multiple languages for inclusive participation</p>
                </div>
            </div>
        </div>

        <!-- Commitment Section  whpoooooxxcxoo-->
        <div class="bg-gradient-to-r from-red-900 to-red-800 text-white rounded-lg p-8 text-center">
            <h3 class="text-2xl font-bold mb-4">Our Commitment</h3>
            <p class="text-lg leading-relaxed">
                Guided by principles of transparency, accountability, and citizen empowerment, this 
                platform strengthens collaboration between the government and its people through secure 
                and modern digital participation.
            </p>
        </div>
    </div>
</section>

</body>
</html>
