<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/google_oauth_config.php';
$_citizenOAuthState = bin2hex(random_bytes(16));
$_SESSION['citizen_google_oauth_state'] = $_citizenOAuthState;
$googleOAuthUrl = getGoogleAuthUrl($_citizenOAuthState);
?>
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

            flex: 0 0 auto;

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



        html {
            scroll-behavior: smooth;
        }

        /* Scroll Reveal Animations */
        .scroll-reveal {
            opacity: 0;
            transform: translateY(35px) scale(0.97);
            transition: opacity 0.75s cubic-bezier(0.16, 1, 0.3, 1), transform 0.75s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: opacity, transform;
        }

        .scroll-reveal.revealed {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .delay-100 { transition-delay: 100ms; }
        .delay-200 { transition-delay: 200ms; }
        .delay-300 { transition-delay: 300ms; }
        .delay-400 { transition-delay: 400ms; }

        /* HERO STYLES - Split-screen and full-height (use image as background) */

        .hero.full-screen {

            /* darker on the left for text contrast, lighter on the right to show the photo */

            background: linear-gradient(90deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.6) 35%, rgba(0,0,0,0.55) 70%), url('images/val.jpg') center right / cover no-repeat;

            min-height: 100vh;

            display: grid;

            grid-template-columns: 0.8fr 1.2fr;

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

            display: inline-flex;

            align-items: center;

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
<header class="glass sticky top-0 z-50 px-6 py-4 border-b border-gray-200/80 shadow-xs flex items-center justify-between gap-4">
    <div class="logo-section flex items-center gap-3">
        <div class="w-11 h-11 rounded-full border-2 border-red-100 shadow-xs flex items-center justify-center overflow-hidden bg-white shrink-0">
            <img src="images/logo.webp" alt="Valenzuela Logo" class="w-full h-full object-cover">
        </div>
        <div>
            <h1 class="text-base font-black text-red-950 leading-snug flex items-center gap-1.5">
                <span>VALENZUELA</span> <span class="text-red-600">PCMS</span>
            </h1>
            <p class="text-[11px] text-slate-500 font-medium">City Legislative Public Consultations</p>
        </div>
    </div>

    <nav class="hidden md:flex flex-1 justify-center items-center gap-8 text-xs font-extrabold text-slate-700 uppercase tracking-wider">
        <a href="#" class="active text-red-700 border-b-2 border-red-700 pb-1">HOME</a>
        <a href="#offerings" class="hover:text-red-700 transition-colors pb-1">WHAT WE OFFER</a>
        <a href="#about" class="hover:text-red-700 transition-colors pb-1">ABOUT</a>
    </nav>

    <div class="header-buttons flex items-center gap-2">
        <a href="<?php echo htmlspecialchars($googleOAuthUrl); ?>" class="inline-flex flex-row items-center gap-2.5 bg-gradient-to-r from-red-700 to-red-900 hover:from-red-800 hover:to-black text-white font-extrabold text-xs px-5 py-2.5 rounded-xl shadow-md transition-all hover:scale-105 whitespace-nowrap">
            <div class="w-4 h-4 rounded-full bg-white p-0.5 flex items-center justify-center shrink-0">
                <svg class="w-full h-full" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.26v3.15C3.25 21.3 7.31 24 12 24z"/>
                    <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.26C.46 8.17 0 9.99 0 12s.46 3.83 1.26 5.42l4.02-3.15z"/>
                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.25 2.7 1.26 6.58l4.02 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                </svg>
            </div>
            <span>PARTICIPATE NOW</span>
        </a>
    </div>
</header>

<!-- HERO SECTION -->
<section class="hero full-screen relative overflow-hidden">
    <div class="hero-content fade-in backdrop-blur-md bg-slate-950/75 p-6 sm:p-8 rounded-3xl border border-white/20 shadow-2xl max-w-lg">
        <span class="px-3 py-0.5 rounded-full bg-red-600/30 text-red-200 text-[11px] font-extrabold uppercase tracking-wider border border-red-500/40 inline-block mb-2.5">
            <i class="bi bi-bank2 mr-1"></i> Valenzuela City Legislative Office
        </span>

        <h2 class="text-3xl sm:text-4xl font-black text-white leading-tight mb-2">
            Tayo na, Valenzuela!
        </h2>

        <p class="text-base sm:text-lg font-bold text-red-200 mb-2.5">
            Shape the Future of Legislation Through Public Participation
        </p>

        <p class="text-xs sm:text-sm text-slate-200 leading-relaxed opacity-95 mb-6">
            Digital na Konsultasyon tungo sa Mas Bukas na Pamamahala, kung saan ang Boses ng Valenzuelano ang Gabay ng Pamahalaan.
        </p>

        <div class="flex items-center">
            <a href="<?php echo htmlspecialchars($googleOAuthUrl); ?>" class="inline-flex flex-row items-center gap-3 bg-gradient-to-r from-red-600 to-red-800 hover:from-red-700 hover:to-red-950 text-white font-extrabold text-xs sm:text-sm px-6 py-3.5 rounded-2xl shadow-xl transition-all hover:scale-105 border border-red-500/30 whitespace-nowrap">
                <div class="w-6 h-6 rounded-full bg-white p-1 flex items-center justify-center shrink-0">
                    <svg class="w-full h-full" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.26v3.15C3.25 21.3 7.31 24 12 24z"/>
                        <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.26C.46 8.17 0 9.99 0 12s.46 3.83 1.26 5.42l4.02-3.15z"/>
                        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.25 2.7 1.26 6.58l4.02 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                    </svg>
                </div>
                <span>PARTICIPATE NOW</span>
            </a>
        </div>
    </div>

    <!-- Right Column: Dual-Layered Interactive PCMS Portal Preview (User's Actual System Screenshots) -->
    <div class="hidden lg:block relative z-10 w-full max-w-2xl mx-auto fade-in">
        <div class="relative min-h-[520px] flex items-center justify-center">
            <!-- Ambient Red/Gold Glow Accent -->
            <div class="absolute -inset-4 bg-gradient-to-tr from-red-600 via-amber-500 to-red-900 rounded-3xl blur-2xl opacity-40 animate-pulse"></div>

            <!-- CARD 1 (Back Layer / Top-Right Stack): Actual Submit Proposal Form Screenshot -->
            <div class="hero-scroll-card absolute top-0 right-0 w-[92%] transform translate-x-4 -translate-y-3 rotate-3 hover:rotate-0 hover:scale-105 hover:z-30 transition-all duration-500 shadow-2xl z-10 group">
                <div class="relative bg-slate-950/95 rounded-2xl border border-white/20 shadow-2xl overflow-hidden backdrop-blur-xl text-left">
                    <!-- Browser Header Bar -->
                    <div class="bg-slate-900 px-4 py-2.5 border-b border-slate-800 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>
                        </div>
                        <div class="flex-1 bg-slate-800/90 px-3 py-0.5 rounded text-[10px] font-mono text-slate-300 flex items-center gap-1.5 justify-center border border-slate-700">
                            <i class="bi bi-lock-fill text-emerald-400 text-[9px]"></i>
                            <span>consultation.spvalenzuela.com/public-portal#submit</span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[9px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">Proposal Form</span>
                    </div>

                    <!-- Actual Screenshot Image -->
                    <div class="relative aspect-[16/10] overflow-hidden bg-slate-900">
                        <img src="images/actual_citizen_proposal_form.png" alt="Submit Concern or Ordinance Proposal Form" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent opacity-50"></div>
                        <div class="absolute bottom-2.5 left-3 right-3 flex items-center justify-between text-[11px] text-white">
                            <span class="font-extrabold text-white flex items-center gap-1 backdrop-blur-xs bg-slate-950/80 px-2.5 py-1 rounded-md border border-white/10">
                                <i class="bi bi-file-earmark-plus text-emerald-400"></i> Ordinance Proposal Form
                            </span>
                            <span class="px-2 py-0.5 rounded bg-red-700 text-white text-[10px] font-extrabold shadow-sm">Valenzuela LGU</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARD 2 (Front Layer / Bottom-Left Stack): Actual Citizen Portal Dashboard Screenshot -->
            <div class="hero-scroll-card absolute bottom-0 left-0 w-[94%] transform -translate-x-3 translate-y-4 -rotate-2 hover:rotate-0 hover:scale-105 hover:z-30 transition-all duration-500 shadow-2xl z-20 group text-left">
                <div class="relative bg-slate-950/95 rounded-2xl border border-white/20 shadow-2xl overflow-hidden backdrop-blur-xl">
                    <!-- Browser Header Bar -->
                    <div class="bg-slate-900 px-4 py-2.5 border-b border-slate-800 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>
                        </div>
                        <div class="flex-1 bg-slate-800/90 px-3 py-0.5 rounded text-[10px] font-mono text-slate-300 flex items-center gap-1.5 justify-center border border-slate-700">
                            <i class="bi bi-lock-fill text-emerald-400 text-[9px]"></i>
                            <span>consultation.spvalenzuela.com/public-portal</span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[9px] font-black bg-red-600/30 text-red-200 border border-red-500/40">Live Portal</span>
                    </div>

                    <!-- Actual Screenshot Image -->
                    <div class="relative aspect-[16/9] overflow-hidden bg-slate-900">
                        <img src="images/actual_citizen_portal.png" alt="Valenzuela Citizen Portal Active Consultations" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent opacity-50"></div>
                        <div class="absolute bottom-2.5 left-3 right-3 flex items-center justify-between text-[11px] text-white">
                            <span class="font-extrabold text-white flex items-center gap-1 backdrop-blur-xs bg-slate-950/80 px-2.5 py-1 rounded-md border border-white/10">
                                <i class="bi bi-chat-left-quote-fill text-amber-400"></i> Active Public Consultations
                            </span>
                            <a href="<?php echo htmlspecialchars($googleOAuthUrl); ?>" class="px-3 py-1 rounded bg-red-700 hover:bg-red-800 text-white font-extrabold text-[10px] flex items-center gap-1 shadow-md">
                                <span>Participate Now</span> <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- WHAT PCMS OFFERS SECTION -->
<section id="offerings" class="py-16 px-4 md:px-8 bg-slate-900 text-white relative overflow-hidden">
    <div class="max-w-6xl mx-auto">
        <div class="text-center max-w-3xl mx-auto mb-12 scroll-reveal">
            <span class="text-xs font-extrabold text-red-400 uppercase tracking-widest bg-red-950/60 border border-red-800/60 px-3.5 py-1.5 rounded-full inline-block mb-3">
                <i class="bi bi-stars text-amber-400 mr-1.5"></i> Platform Capabilities
            </span>
            <h3 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                What the PCMS Portal Offers to Citizens
            </h3>
            <p class="text-slate-300 text-xs sm:text-sm mt-2 leading-relaxed">
                Empowering Valenzuela residents with direct legislative access, real-time tracking, and open municipal consultations.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Offering 1: Active Consultations -->
            <div class="bg-slate-800/80 rounded-2xl p-6 border border-slate-700 shadow-lg hover:border-red-500/50 transition-all duration-300 flex flex-col justify-between group scroll-reveal delay-100">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-red-600 text-white flex items-center justify-center text-xl shadow-md mb-4 group-hover:scale-110 transition-transform">
                        <i class="bi bi-chat-left-text-fill"></i>
                    </div>
                    <h4 class="text-base font-bold text-white mb-2">Ordinance Consultations</h4>
                    <p class="text-slate-300 text-xs leading-relaxed">
                        Review draft city ordinances, submit written testimonies, rate policy impact with 5-star feedback, and review official committee responses.
                    </p>
                </div>
                <div class="pt-4 mt-4 border-t border-slate-700/80 flex items-center justify-between text-[11px] font-bold text-red-400">
                    <span>Direct Feedback</span>
                    <i class="bi bi-arrow-right group-hover:translate-x-1 transition-transform"></i>
                </div>
            </div>

            <!-- Offering 2: 1-Click Community Surveys -->
            <div class="bg-slate-800/80 rounded-2xl p-6 border border-slate-700 shadow-lg hover:border-purple-500/50 transition-all duration-300 flex flex-col justify-between group">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-purple-600 text-white flex items-center justify-center text-xl shadow-md mb-4 group-hover:scale-110 transition-transform">
                        <i class="bi bi-square-poll-fill"></i>
                    </div>
                    <h4 class="text-base font-bold text-white mb-2">1-Click Opinion Polls</h4>
                    <p class="text-slate-300 text-xs leading-relaxed">
                        Cast your vote instantly on community survey initiatives (*Agree vs. Disagree*) and inspect live public sentiment and vote tallies across Valenzuela.
                    </p>
                </div>
                <div class="pt-4 mt-4 border-t border-slate-700/80 flex items-center justify-between text-[11px] font-bold text-purple-400">
                    <span>Live Poll Tally</span>
                    <i class="bi bi-arrow-right group-hover:translate-x-1 transition-transform"></i>
                </div>
            </div>

            <!-- Offering 3: Submit Citizen Proposals -->
            <div class="bg-slate-800/80 rounded-2xl p-6 border border-slate-700 shadow-lg hover:border-emerald-500/50 transition-all duration-300 flex flex-col justify-between group">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-xl shadow-md mb-4 group-hover:scale-110 transition-transform">
                        <i class="bi bi-send-fill"></i>
                    </div>
                    <h4 class="text-base font-bold text-white mb-2">Citizen Proposals</h4>
                    <p class="text-slate-300 text-xs leading-relaxed">
                        Have a policy idea for your barangay or district? Submit your proposal directly to the City Council for official moderation and public launching.
                    </p>
                </div>
                <div class="pt-4 mt-4 border-t border-slate-700/80 flex items-center justify-between text-[11px] font-bold text-emerald-400">
                    <span>Submit Policy Idea</span>
                    <i class="bi bi-arrow-right group-hover:translate-x-1 transition-transform"></i>
                </div>
            </div>

            <!-- Offering 4: Track Status & History -->
            <div class="bg-slate-800/80 rounded-2xl p-6 border border-slate-700 shadow-lg hover:border-amber-500/50 transition-all duration-300 flex flex-col justify-between group">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-amber-600 text-white flex items-center justify-center text-xl shadow-md mb-4 group-hover:scale-110 transition-transform">
                        <i class="bi bi-qr-code-scan"></i>
                    </div>
                    <h4 class="text-base font-bold text-white mb-2">Transparent Tracking</h4>
                    <p class="text-slate-300 text-xs leading-relaxed">
                        Track the status of your submitted concerns in real-time with unique tracking tokens and review your full participation history.
                    </p>
                </div>
                <div class="pt-4 mt-4 border-t border-slate-700/80 flex items-center justify-between text-[11px] font-bold text-amber-400">
                    <span>Real-Time Updates</span>
                    <i class="bi bi-arrow-right group-hover:translate-x-1 transition-transform"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ABOUT SECTION -->
<section id="about" class="py-16 px-4 md:px-8 bg-slate-950 text-white relative overflow-hidden border-t border-slate-800/80">
    <div class="max-w-6xl mx-auto">
        <!-- Section Header -->
        <div class="text-center max-w-2xl mx-auto mb-10 scroll-reveal">
            <span class="text-xs font-extrabold text-red-400 uppercase tracking-widest bg-red-950/60 border border-red-800/60 px-3.5 py-1.5 rounded-full inline-block mb-3">
                <i class="bi bi-info-circle-fill text-red-400 mr-1"></i> About Our Platform
            </span>
            <h2 class="text-3xl sm:text-4xl font-black text-white tracking-tight">About the Public Consultation Portal</h2>
            <p class="text-slate-300 text-xs sm:text-sm mt-2">Empowering Valenzuelanos to shape the future of local ordinances through transparent digital participation.</p>
        </div>
        <!-- Main Description Card -->
        <div class="bg-gradient-to-r from-red-950 via-slate-900 to-slate-900 p-8 sm:p-10 rounded-3xl text-white shadow-2xl border border-slate-800 relative overflow-hidden mb-12 flex flex-col md:flex-row items-center justify-between gap-6 scroll-reveal">
            <div class="z-10 max-w-2xl">
                <span class="px-3 py-1 rounded-full bg-red-600/30 text-red-200 text-xs font-extrabold uppercase tracking-wider border border-red-500/40 inline-block mb-3">
                    <i class="bi bi-bank2 mr-1"></i> Civic Governance Mission
                </span>
                <h3 class="text-2xl sm:text-3xl font-black text-white mb-2">Empowering Every Valenzuelano</h3>
                <p class="text-slate-300 text-xs sm:text-sm leading-relaxed">
                    The Public Consultation Portal is the official digital platform of the Valenzuela City Government. It promotes transparency, inclusion, and data-driven governance by allowing every resident to participate in shaping local legislation anytime, anywhere.
                </p>
            </div>
            <div class="z-10 shrink-0 grid grid-cols-2 gap-3 text-xs font-bold text-slate-200">
                <div class="p-3 bg-slate-800/80 rounded-xl backdrop-blur-xs flex items-center gap-2 border border-slate-700 text-slate-200">
                    <i class="bi bi-check-circle-fill text-emerald-400"></i> Transparent Governance
                </div>
                <div class="p-3 bg-slate-800/80 rounded-xl backdrop-blur-xs flex items-center gap-2 border border-slate-700 text-slate-200">
                    <i class="bi bi-check-circle-fill text-emerald-400"></i> Inclusive Participation
                </div>
                <div class="p-3 bg-slate-800/80 rounded-xl backdrop-blur-xs flex items-center gap-2 border border-slate-700 text-slate-200">
                    <i class="bi bi-check-circle-fill text-emerald-400"></i> Data-Driven Decisions
                </div>
                <div class="p-3 bg-slate-800/80 rounded-xl backdrop-blur-xs flex items-center gap-2 border border-slate-700 text-slate-200">
                    <i class="bi bi-check-circle-fill text-emerald-400"></i> Real-Time Engagement
                </div>
            </div>
        </div>

        <!-- Redesigned Privacy & Security Section (Aligned Dark Theme) -->
        <div id="security-privacy" class="pt-12 border-t border-slate-800/80">
            <div class="text-center max-w-2xl mx-auto mb-10 scroll-reveal">
                <span class="text-xs font-extrabold text-emerald-400 uppercase tracking-widest bg-emerald-950/60 border border-emerald-800/60 px-3.5 py-1.5 rounded-full inline-block mb-3">
                    <i class="bi bi-shield-check text-emerald-400 mr-1"></i> Data Privacy Act Compliant
                </span>
                <h3 class="text-2xl sm:text-3xl font-black text-white tracking-tight">Your Privacy & Data Security</h3>
                <p class="text-slate-300 text-xs sm:text-sm mt-1">We protect your citizen data with enterprise encryption standards and strict compliance with Republic Act 10173.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Security Card 1 -->
                <div class="bg-slate-900/90 p-6 rounded-3xl border border-slate-800 shadow-lg hover:border-blue-500/50 transition-all duration-300 card-hover flex flex-col justify-between group scroll-reveal delay-100">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-blue-950/60 text-blue-400 border border-blue-800/60 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <h4 class="text-base font-extrabold text-white mb-1.5">Secure Encrypted Connection</h4>
                        <p class="text-slate-300 text-xs leading-relaxed">All citizen interactions are transmitted using end-to-end HTTPS / TLS 1.3 256-bit SSL encryption.</p>
                    </div>
                    <span class="text-[10px] font-bold text-blue-400 uppercase tracking-wider mt-4 block">TLS 1.3 Encrypted</span>
                </div>

                <!-- Security Card 2 -->
                <div class="bg-slate-900/90 p-6 rounded-3xl border border-slate-800 shadow-lg hover:border-emerald-500/50 transition-all duration-300 card-hover flex flex-col justify-between group scroll-reveal delay-200">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-emerald-950/60 text-emerald-400 border border-emerald-800/60 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h4 class="text-base font-extrabold text-white mb-1.5">RA 10173 Data Privacy</h4>
                        <p class="text-slate-300 text-xs leading-relaxed">Fully compliant with the Data Privacy Act of 2012, protecting citizen rights and personal identifiers.</p>
                    </div>
                    <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider mt-4 block">RA 10173 Compliant</span>
                </div>

                <!-- Security Card 3 -->
                <div class="bg-slate-900/90 p-6 rounded-3xl border border-slate-800 shadow-lg hover:border-purple-500/50 transition-all duration-300 card-hover flex flex-col justify-between group scroll-reveal delay-300">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-purple-950/60 text-purple-400 border border-purple-800/60 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                            <i class="bi bi-lock-fill"></i>
                        </div>
                        <h4 class="text-base font-extrabold text-white mb-1.5">Protected Information</h4>
                        <p class="text-slate-300 text-xs leading-relaxed">Personal records and email addresses are securely isolated from public view and third-party trackers.</p>
                    </div>
                    <span class="text-[10px] font-bold text-purple-400 uppercase tracking-wider mt-4 block">Isolated Storage</span>
                </div>

                <!-- Security Card 4 -->
                <div class="bg-slate-900/90 p-6 rounded-3xl border border-slate-800 shadow-lg hover:border-red-500/50 transition-all duration-300 card-hover flex flex-col justify-between group scroll-reveal delay-100">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-red-950/60 text-red-400 border border-red-800/60 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                            <i class="bi bi-google"></i>
                        </div>
                        <h4 class="text-base font-extrabold text-white mb-1.5">Google OAuth SSO</h4>
                        <p class="text-slate-300 text-xs leading-relaxed">1-click authentication powered by Google OAuth 2.0 without storing raw passwords in system databases.</p>
                    </div>
                    <span class="text-[10px] font-bold text-red-400 uppercase tracking-wider mt-4 block">OAuth 2.0 Verified</span>
                </div>

                <!-- Security Card 5 -->
                <div class="bg-slate-900/90 p-6 rounded-3xl border border-slate-800 shadow-lg hover:border-amber-500/50 transition-all duration-300 card-hover flex flex-col justify-between group scroll-reveal delay-200">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-amber-950/60 text-amber-400 border border-amber-800/60 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                            <i class="bi bi-eye-slash-fill"></i>
                        </div>
                        <h4 class="text-base font-extrabold text-white mb-1.5">Privacy Options</h4>
                        <p class="text-slate-300 text-xs leading-relaxed">Flexible privacy options allowing feedback participation with full control over public identity disclosures.</p>
                    </div>
                    <span class="text-[10px] font-bold text-amber-400 uppercase tracking-wider mt-4 block">Citizen Control</span>
                </div>

                <!-- Security Card 6 -->
                <div class="bg-slate-900/90 p-6 rounded-3xl border border-slate-800 shadow-lg hover:border-indigo-500/50 transition-all duration-300 card-hover flex flex-col justify-between group scroll-reveal delay-300">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-indigo-950/60 text-indigo-400 border border-indigo-800/60 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                            <i class="bi bi-bank"></i>
                        </div>
                        <h4 class="text-base font-extrabold text-white mb-1.5">Open Governance</h4>
                        <p class="text-slate-300 text-xs leading-relaxed">All verified policy feedback is directly integrated into City Council committee reports and public records.</p>
                    </div>
                    <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mt-4 block">Council Verified</span>
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



    // Scroll Reveal Intersection Observer & Floating Parallax Script

    document.addEventListener('DOMContentLoaded', function() {

        // Observer for scroll-reveal elements

        const observerOptions = {

            root: null,

            threshold: 0.1,

            rootMargin: '0px 0px -40px 0px'

        };



        const revealObserver = new IntersectionObserver((entries, observer) => {

            entries.forEach(entry => {

                if (entry.isIntersecting) {

                    entry.target.classList.add('revealed');

                }

            });

        }, observerOptions);



        document.querySelectorAll('.scroll-reveal').forEach(el => {

            revealObserver.observe(el);

        });



        // Hero 3D Card Parallax Floating Shift on Scroll

        const heroCards = document.querySelectorAll('.hero-scroll-card');

        if (heroCards.length >= 2) {

            window.addEventListener('scroll', function() {

                const scrolled = window.pageYOffset;

                if (scrolled < 900) {

                    const topShift = scrolled * 0.04;

                    const botShift = scrolled * 0.03;

                    heroCards[0].style.transform = `translate(${16 + topShift}px, ${-12 - topShift}px) rotate(${3 + topShift * 0.05}deg)`;

                    heroCards[1].style.transform = `translate(${-12 - botShift}px, ${16 + botShift}px) rotate(${-2 - botShift * 0.05}deg)`;

                }

            }, { passive: true });

        }

    });

</script>



</body>

</html>
