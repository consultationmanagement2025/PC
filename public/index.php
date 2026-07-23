<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Dashboard - Public Portal</title>
    <link rel="icon" type="image/png" href="/pcms/admin/ASSETS/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/js/tailwind.config.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/index.css">
</head>
<body class="text-slate-800 antialiased relative">

    <nav class="glass border-b border-gray-200 sticky top-0 z-50 shadow-sm transition-all duration-300" id="main-nav">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                
                <!-- Logo & Brand Area -->
                <div class="flex items-center gap-3 shrink-0">
                    <div class="w-12 h-12 rounded-full border-2 border-gray-100 shadow-inner flex items-center justify-center overflow-hidden bg-white">
                        <img src="/pcms/admin/ASSETS/images/logo.png" alt="Seal" class="w-full h-full object-cover opacity-80">
                    </div>
                    
                    <div class="flex flex-col justify-center">
                        <div class="flex items-baseline gap-2">
                            <h1 class="text-[22px] font-black tracking-tight flex items-baseline">
                                <span class="text-valenzuela-blue">VALENZUELA</span>
                                <span class="text-valenzuela-red ml-1">PCMS</span>
                            </h1>
                            <div class="leading-none text-[10px] font-bold text-valenzuela-red tracking-wider border-l border-gray-300 pl-2 ml-1 uppercase hidden sm:block">
                                Public<br>Portal
                            </div>
                        </div>
                        <span class="text-xs font-medium text-gray-400 tracking-[0.15em] mt-[-2px]"></span>
                    </div>
                </div>

                <!-- Desktop Navigation Links -->
                <div class="hidden md:flex space-x-8 items-center font-medium text-sm text-slate-600">
                    <a href="#active-consultations" class="hover:text-valenzuela-blue transition-colors px-2 py-2">Active Consultations</a>
                    <a href="#surveys" class="hover:text-valenzuela-blue transition-colors px-2 py-2">Surveys</a>
                    <a href="#submit-consultation" class="hover:text-valenzuela-blue transition-colors px-2 py-2">Submit Concern</a>
                </div>

                <!-- Right Actions -->
                <div class="hidden md:flex items-center gap-6">
                    <a href="sign-in.php" class="text-sm font-semibold text-slate-700 hover:text-valenzuela-blue transition-colors">Sign In</a>
                    <a href="#submit-consultation" class="bg-valenzuela-red hover:bg-red-700 text-white px-6 py-2.5 rounded-full font-bold text-sm transition-all shadow-[0_4px_14px_0_rgba(255,0,0,0.39)] hover:shadow-[0_6px_20px_rgba(255,0,0,0.23)] hover:-translate-y-0.5">
                        Get Started
                    </a>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-btn" class="text-gray-500 hover:text-gray-900 focus:outline-none p-2">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile Menu Dropdown -->
        <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-100 px-4 py-4 space-y-3 shadow-lg absolute w-full">
            <a href="#active-consultations" class="block font-medium text-slate-600 hover:text-valenzuela-blue">Active Consultations</a>
            <a href="#surveys" class="block font-medium text-slate-600 hover:text-valenzuela-blue">Surveys</a>
            <a href="#submit-consultation" class="block font-medium text-slate-600 hover:text-valenzuela-blue">Submit Concern</a>
            <div class="pt-4 border-t border-gray-100 flex flex-col gap-3">
                <a href="sign-in.php" class="block text-center font-semibold text-slate-700 hover:text-valenzuela-blue">Sign In</a>
                <a href="#submit-consultation" class="block text-center bg-valenzuela-red text-white px-6 py-2.5 rounded-full font-bold text-sm">Get Started</a>
            </div>
        </div>
    </nav>

    <main class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-12 mb-20">
        
        <!-- Welcome Section -->
        <header class="bg-white rounded-2xl p-6 md:p-8 shadow-sm border border-gray-100 flex flex-col md:flex-row items-center justify-between gap-8 relative overflow-hidden">
            <!-- Decorative background element -->
            <div class="absolute right-0 top-0 w-64 h-64 bg-blue-50 rounded-full blur-3xl -z-10 translate-x-1/2 -translate-y-1/2"></div>
            
            <div class="z-10 w-full md:w-auto">
                <?php if (isset($_GET['login']) && $_GET['login'] === 'success') { $userName = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : 'Citizen'; ?>
                    <div class="inline-flex items-center gap-2 bg-green-50 text-green-700 text-sm font-semibold px-3 py-1 rounded-full mb-3">
                        <i class="fa-solid fa-circle-check"></i> Signed in successfully
                    </div>
                <?php } ?>
                <h2 class="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Welcome to your Citizen Dashboard</h2>
                <p class="text-slate-500 max-w-2xl text-base md:text-lg">Participate in local governance by reviewing active consultations, taking community surveys, and submitting your own proposals.</p>
                <?php if (isset($_GET['login']) && $_GET['login'] === 'success') { ?>
                    <p class="text-sm text-slate-600 mt-3">Hello, <span class="font-semibold text-valenzuela-blue"><?php echo $userName; ?></span> — your account is ready.</p>
                <?php } ?>
            </div>
            <div class="flex gap-4 z-10 w-full md:w-auto overflow-x-auto pb-2 md:pb-0 shrink-0">
                <div class="text-center bg-gray-50 rounded-xl p-4 border border-gray-100 min-w-[120px] flex-1 md:flex-none">
                    <span class="block text-3xl font-black text-valenzuela-blue">5</span>
                    <span class="text-[11px] text-slate-500 uppercase font-bold tracking-wider mt-1 block">Active<br>Topics</span>
                </div>
                <div class="text-center bg-gray-50 rounded-xl p-4 border border-gray-100 min-w-[120px] flex-1 md:flex-none">
                    <span class="block text-3xl font-black text-valenzuela-red">2</span>
                    <span class="text-[11px] text-slate-500 uppercase font-bold tracking-wider mt-1 block">New<br>Surveys</span>
                </div>
            </div>
        </header>

        <!-- Active Consultations Section -->
        <section id="active-consultations" class="scroll-mt-24">
            <div class="flex justify-between items-end mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-comments text-valenzuela-blue"></i> Active Consultations
                    </h3>
                    <p class="text-slate-500 text-sm mt-1">Review and provide feedback on proposed ordinances.</p>
                </div>
                <a href="#" class="text-sm font-semibold text-valenzuela-blue hover:underline hidden sm:block">View All <i class="fa-solid fa-arrow-right ml-1"></i></a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Consultation Card 1 -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow flex flex-col group">
                    <div class="p-6 flex-grow">
                        <div class="flex justify-between items-start mb-4">
                            <span class="bg-blue-50 text-valenzuela-blue text-xs font-bold px-2.5 py-1 rounded-md uppercase tracking-wide">Environment</span>
                            <span class="text-xs text-gray-400 font-medium"><i class="fa-regular fa-clock"></i> Closes in 5 days</span>
                        </div>
                        <h4 class="text-lg font-bold text-slate-800 mb-2 group-hover:text-valenzuela-blue transition-colors">Proposed Ordinance on Single-Use Plastics Regulation</h4>
                        <p class="text-slate-600 text-sm mb-4 line-clamp-3">A proposal to regulate the use and distribution of single-use plastics within the city limits to promote environmental sustainability and reduce waste.</p>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50 flex justify-between items-center">
                        <div class="flex -space-x-2">
                            <div class="w-8 h-8 rounded-full bg-gray-300 border-2 border-white flex items-center justify-center text-xs font-bold text-gray-600">JD</div>
                            <div class="w-8 h-8 rounded-full bg-blue-200 border-2 border-white flex items-center justify-center text-xs font-bold text-blue-700">AR</div>
                            <div class="w-8 h-8 rounded-full bg-gray-100 border-2 border-white flex items-center justify-center text-xs font-bold text-gray-500">+42</div>
                        </div>
                        <button class="text-sm font-semibold text-valenzuela-blue hover:text-blue-800 bg-white border border-gray-200 px-4 py-2 rounded-lg shadow-sm hover:bg-gray-50 transition-colors">Participate</button>
                    </div>
                </div>

                <!-- Consultation Card 2 -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow flex flex-col group">
                    <div class="p-6 flex-grow">
                        <div class="flex justify-between items-start mb-4">
                            <span class="bg-red-50 text-valenzuela-red text-xs font-bold px-2.5 py-1 rounded-md uppercase tracking-wide">Infrastructure</span>
                            <span class="text-xs text-gray-400 font-medium"><i class="fa-regular fa-clock"></i> Closes in 12 days</span>
                        </div>
                        <h4 class="text-lg font-bold text-slate-800 mb-2 group-hover:text-valenzuela-blue transition-colors">Barangay Road Expansion Project Phase 2</h4>
                        <p class="text-slate-600 text-sm mb-4 line-clamp-3">Public consultation regarding the proposed road widening in District 1 to alleviate traffic congestion and improve pedestrian pathways.</p>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50 flex justify-between items-center">
                        <div class="flex -space-x-2">
                            <div class="w-8 h-8 rounded-full bg-green-200 border-2 border-white flex items-center justify-center text-xs font-bold text-green-700">MP</div>
                            <div class="w-8 h-8 rounded-full bg-gray-100 border-2 border-white flex items-center justify-center text-xs font-bold text-gray-500">+18</div>
                        </div>
                        <button class="text-sm font-semibold text-valenzuela-blue hover:text-blue-800 bg-white border border-gray-200 px-4 py-2 rounded-lg shadow-sm hover:bg-gray-50 transition-colors">Participate</button>
                    </div>
                </div>

                 <!-- Consultation Card 3 -->
                 <div class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow flex flex-col group hidden lg:flex">
                    <div class="p-6 flex-grow">
                        <div class="flex justify-between items-start mb-4">
                            <span class="bg-purple-50 text-purple-700 text-xs font-bold px-2.5 py-1 rounded-md uppercase tracking-wide">Health</span>
                            <span class="text-xs text-gray-400 font-medium"><i class="fa-regular fa-clock"></i> Closes in 20 days</span>
                        </div>
                        <h4 class="text-lg font-bold text-slate-800 mb-2 group-hover:text-valenzuela-blue transition-colors">Public Health Guidelines for Community Centers</h4>
                        <p class="text-slate-600 text-sm mb-4 line-clamp-3">Drafting new health and sanitation guidelines for all public community centers and sports complexes.</p>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50 flex justify-between items-center">
                        <div class="flex -space-x-2">
                            <div class="w-8 h-8 rounded-full bg-gray-100 border-2 border-white flex items-center justify-center text-xs font-bold text-gray-500">+5</div>
                        </div>
                        <button class="text-sm font-semibold text-valenzuela-blue hover:text-blue-800 bg-white border border-gray-200 px-4 py-2 rounded-lg shadow-sm hover:bg-gray-50 transition-colors">Participate</button>
                    </div>
                </div>
            </div>
            <div class="mt-4 text-center sm:hidden">
                <a href="#" class="text-sm font-semibold text-valenzuela-blue hover:underline">View All Consultations <i class="fa-solid fa-arrow-right ml-1"></i></a>
            </div>
        </section>

        <!-- Surveys Section -->
        <section id="surveys" class="scroll-mt-24 pt-4 border-t border-gray-200">
            <div class="flex justify-between items-end mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-square-poll-horizontal text-valenzuela-red"></i> Community Surveys
                    </h3>
                    <p class="text-slate-500 text-sm mt-1">Make your voice heard on local issues.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Survey 1 -->
                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col sm:flex-row gap-6 items-start sm:items-center">
                    <div class="w-16 h-16 rounded-full bg-red-50 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-tree text-2xl text-valenzuela-red"></i>
                    </div>
                    <div class="flex-grow">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-flex w-2 h-2 rounded-full bg-green-500"></span>
                            <span class="text-xs font-bold text-green-600 uppercase tracking-wide">Active</span>
                        </div>
                        <h4 class="text-lg font-bold text-slate-800 mb-1">Public Parks Utilization Survey</h4>
                        <p class="text-sm text-slate-500 mb-3">Help us understand how you use local parks and what amenities we should prioritize.</p>
                        <div class="w-full bg-gray-200 rounded-full h-1.5 mb-2">
                            <div class="bg-valenzuela-blue h-1.5 rounded-full" style="width: 45%"></div>
                        </div>
                        <p class="text-xs text-gray-400 font-medium">Est. time: 5 mins • 450+ responses</p>
                    </div>
                    <div class="shrink-0 w-full sm:w-auto">
                        <button class="w-full sm:w-auto bg-valenzuela-blue hover:bg-blue-800 text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition-colors shadow-sm">Take Survey</button>
                    </div>
                </div>

                <!-- Survey 2 -->
                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col sm:flex-row gap-6 items-start sm:items-center">
                    <div class="w-16 h-16 rounded-full bg-blue-50 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-shield-halved text-2xl text-valenzuela-blue"></i>
                    </div>
                    <div class="flex-grow">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-flex w-2 h-2 rounded-full bg-orange-400 animate-pulse"></span>
                            <span class="text-xs font-bold text-orange-500 uppercase tracking-wide">Closing Soon</span>
                        </div>
                        <h4 class="text-lg font-bold text-slate-800 mb-1">Neighborhood Safety Assessment</h4>
                        <p class="text-sm text-slate-500 mb-3">Share your thoughts on community safety and local policing initiatives.</p>
                        <div class="w-full bg-gray-200 rounded-full h-1.5 mb-2">
                            <div class="bg-valenzuela-blue h-1.5 rounded-full" style="width: 80%"></div>
                        </div>
                        <p class="text-xs text-gray-400 font-medium">Est. time: 3 mins • 1,200+ responses</p>
                    </div>
                    <div class="shrink-0 w-full sm:w-auto">
                        <button class="w-full sm:w-auto bg-valenzuela-blue hover:bg-blue-800 text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition-colors shadow-sm">Take Survey</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Submit Consultation Section -->
        <section id="submit-consultation" class="scroll-mt-24 pt-4 border-t border-gray-200">
            <div class="bg-slate-800 rounded-2xl overflow-hidden shadow-xl relative">
                <!-- Background Pattern -->
                <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 20px 20px;"></div>
                
                <div class="grid grid-cols-1 lg:grid-cols-5 relative z-10">
                    <!-- Informational Sidebar -->
                    <div class="lg:col-span-2 p-8 lg:p-10 text-white flex flex-col justify-between">
                        <div>
                            <span class="inline-block bg-white/20 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide mb-4">Citizen Proposal</span>
                            <h3 class="text-3xl font-bold mb-4">Submit a Consultation Topic</h3>
                            <p class="text-slate-300 text-sm leading-relaxed mb-8">
                                Have an idea to improve our city? Submit your proposal for a new ordinance or resolution. Your submission will be reviewed by the Legislative Office and may be opened for public consultation.
                            </p>
                            
                            <ul class="space-y-4">
                                <li class="flex gap-3">
                                    <i class="fa-solid fa-check-circle text-valenzuela-red mt-1"></i>
                                    <div>
                                        <strong class="block text-sm">Clear & Concise</strong>
                                        <span class="text-xs text-slate-400">Ensure your title and description clearly state the goal.</span>
                                    </div>
                                </li>
                                <li class="flex gap-3">
                                    <i class="fa-solid fa-check-circle text-valenzuela-red mt-1"></i>
                                    <div>
                                        <strong class="block text-sm">Attach Evidence</strong>
                                        <span class="text-xs text-slate-400">Include documents or photos to support your proposal.</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Form Area -->
                    <div class="lg:col-span-3 bg-white p-8 lg:p-10">
                        <div id="form-success-message" class="hidden bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-start gap-3">
                            <i class="fa-solid fa-circle-check mt-0.5"></i>
                            <div>
                                <strong class="block text-sm font-bold">Proposal Submitted Successfully!</strong>
                                <span class="text-sm">Thank you. The legislative office will review your submission shortly.</span>
                            </div>
                        </div>

                        <form id="consultation-form" class="space-y-6">
                            <!-- PHP: Form action would typically point to something like `submit_consultation.php` with method="POST" -->
                            
                            <div>
                                <label for="title" class="block text-sm font-semibold text-slate-700 mb-1">Proposal Title <span class="text-red-500">*</span></label>
                                <input type="text" id="title" name="title" required placeholder="e.g., Installation of Solar Lights in Public Parks" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none transition-all text-sm">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="category" class="block text-sm font-semibold text-slate-700 mb-1">Category <span class="text-red-500">*</span></label>
                                    <select id="category" name="category" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none transition-all text-sm bg-white">
                                        <option value="" disabled selected>Select a category</option>
                                        <option value="infrastructure">Infrastructure & Works</option>
                                        <option value="health">Public Health</option>
                                        <option value="environment">Environment & Sanitation</option>
                                        <option value="education">Education</option>
                                        <option value="transportation">Traffic & Transportation</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="target_area" class="block text-sm font-semibold text-slate-700 mb-1">Target Barangay / Area</label>
                                    <input type="text" id="target_area" name="target_area" placeholder="e.g., Brgy. Malinta or Citywide" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none transition-all text-sm">
                                </div>
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-semibold text-slate-700 mb-1">Detailed Description <span class="text-red-500">*</span></label>
                                <textarea id="description" name="description" rows="5" required placeholder="Explain why this proposal is needed and how it benefits the community..." class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none transition-all text-sm resize-none"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Supporting Documents (Optional)</label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:bg-gray-50 transition-colors cursor-pointer" onclick="document.getElementById('file-upload').click()">
                                    <div class="space-y-1 text-center">
                                        <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400 mb-2"></i>
                                        <div class="flex text-sm text-gray-600 justify-center">
                                            <span class="relative cursor-pointer bg-white rounded-md font-medium text-valenzuela-blue hover:text-blue-800 focus-within:outline-none">
                                                <span>Upload a file</span>
                                                <input id="file-upload" name="file-upload" type="file" class="sr-only" multiple accept=".pdf,.doc,.docx,.jpg,.png">
                                            </span>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500">PDF, DOC, PNG, JPG up to 10MB</p>
                                    </div>
                                </div>
                                <div id="file-name-display" class="text-xs text-gray-500 mt-2 font-medium hidden"></div>
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="w-full bg-valenzuela-red hover:bg-red-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition-colors flex justify-center items-center gap-2">
                                    <i class="fa-solid fa-paper-plane"></i> Submit Proposal
                                </button>
                                <p class="text-xs text-center text-gray-400 mt-3">
                                    By submitting, you agree to the <a href="#" class="underline">Terms of Use</a> and <a href="#" class="underline">Privacy Policy</a>.
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-300 py-10 border-t border-slate-800">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <div class="flex items-baseline gap-2 mb-4">
                        <h2 class="text-xl font-black tracking-tight flex items-baseline">
                            <span class="text-white">VALENZUELA</span>
                            <span class="text-valenzuela-red ml-1">PCMS</span>
                        </h2>
                    </div>
                    <p class="text-sm text-slate-400 mb-4 max-w-xs">Empowering citizens through transparent and participatory local governance.</p>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4 uppercase text-sm tracking-wider">Quick Links</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white transition-colors">Home</a></li>
                        <li><a href="#active-consultations" class="hover:text-white transition-colors">Active Consultations</a></li>
                        <li><a href="#surveys" class="hover:text-white transition-colors">Surveys</a></li>
                        <li><a href="#submit-consultation" class="hover:text-white transition-colors">Submit Proposal</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4 uppercase text-sm tracking-wider">Contact</h4>
                    <ul class="space-y-2 text-sm text-slate-400">
                        <li><i class="fa-solid fa-location-dot w-5"></i> Valenzuela City Hall</li>
                        <li><i class="fa-solid fa-envelope w-5"></i> pcms@valenzuela.gov.ph</li>
                        <li><i class="fa-solid fa-phone w-5"></i> (02) 8352-1000</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-slate-800 mt-8 pt-8 text-xs text-center text-slate-500">
                &copy; 2026 Local Legislative Records Management System, Valenzuela City. All rights reserved.
            </div>
        </div>
    </footer>

    <script src="../assets/js/index.js"></script>
</body>
</html>
