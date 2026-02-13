<?php
session_start();
require 'DATABASE/audit-log.php';
require 'DATABASE/user-logs.php';
require 'announcements.php';
require 'DATABASE/posts.php';
require 'DATABASE/notifications.php';
require 'DATABASE/consultations.php';
require 'DATABASE/feedback.php';
// Use strtolower and trim to be safe
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($current_role !== 'admin') {
    header('Location: login.php');
    exit();
}

// --- Consultation & Feedback Management Dashboard Data ---
$consult_total = 0;
$consult_open = 0;
$consult_scheduled = 0;
$consultations = [];
$feedbackList = [];

if (file_exists('db.php')) {
    require_once 'db.php';

    // Ensure core tables exist
    if (function_exists('initializeConsultationsTable')) {
        initializeConsultationsTable();
    }
    if (function_exists('initializeFeedbackTable')) {
        initializeFeedbackTable();
    }

    // Load consultations using helper so stats align with public portal
    if (function_exists('getConsultations')) {
        $consultations = getConsultations(null, 100, 0);
        foreach ($consultations as $c) {
            $consult_total++;
            if (($c['status'] ?? '') === 'active') {
                $consult_open++;
            }
            if (($c['status'] ?? '') === 'closed') {
                $consult_scheduled++;
            }
        }
    }

    // Load latest feedback entries for Feedback Management
    if (function_exists('getFeedback')) {
        $feedbackList = getFeedback([], 50, 0);
    }
}

// Load audit logs for display
$auditLogs = [];
$pageSize = 50;
$page = isset($_GET['audit_page']) ? (int)$_GET['audit_page'] : 1;
$offset = ($page - 1) * $pageSize;

$filters = [];
if (!empty($_GET['filter_admin'])) $filters['admin_user'] = $_GET['filter_admin'];
if (!empty($_GET['filter_action'])) $filters['action'] = $_GET['filter_action'];
if (!empty($_GET['filter_type'])) $filters['entity_type'] = $_GET['filter_type'];

$auditLogs = getAuditLogs($pageSize, $offset, $filters);
$totalLogs = getAuditLogCount($filters);
$totalPages = ceil($totalLogs / $pageSize);

// Handle new announcement submission
// (now handled by AJAX in create_announcement.php)

// Mark post as reviewed by admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mark_reviewed_post_id'])) {
    $postId = (int)$_POST['mark_reviewed_post_id'];
    $post = getPostById($postId);
    $admin_id = $_SESSION['user_id'] ?? null;
    $admin_user = $_SESSION['fullname'] ?? 'Admin';
    if ($post) {
        $user_id = $post['user_id'] ?? null;
        // Create notification to user
        if ($user_id) {
            createNotification($user_id, 'Your post has been reviewed by the administration.', 'notice');
        }
        if (function_exists('logAction')) {
            logAction($admin_id, $admin_user, "Marked post #$postId as reviewed", 'post', $postId, null, null, 'success', 'marked_reviewed');
        }
    }
    header('Location: system-template-full.php');
    exit();
}
$totalPages = ceil($totalLogs / $pageSize);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="theme-color" content="#dc2626">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=no">
    <title>PCMP - Public Consultation Management Portal | City of Valenzuela</title>
    <meta name="description" content="Legislative Records Management System - City Government of Valenzuela, Metropolitan Manila">
    <meta name="keywords" content="LRMS, Valenzuela, Legislative Records, Document Management">
    <link rel="icon" type="image/png" href="images/logo.webp">
    <link rel="apple-touch-icon" href="images/logo.webp">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Prevent dark mode flicker - must run before page renders -->
    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
        }
        
        // Check if user is logged in (for demo purposes)
        // In production, this would check actual session/token
        if (!localStorage.getItem('isLoggedIn') && !sessionStorage.getItem('isLoggedIn')) {
            // Redirect to login if not logged in
            window.location.href = 'login.php';
        }
        
        // Clear sidebar collapsed state for fresh start (can be removed after testing)
        // localStorage.removeItem('sidebarCollapsed');
    </script>
    
    <link rel="stylesheet" href="styles.css">
    
    <!-- Ensure sidebar is visible on desktop (when not collapsed) -->
    <style>
        @media (min-width: 768px) {
            #sidebar:not(.sidebar-collapsed) {
                display: flex !important;
                transform: translateX(0) !important;
                position: relative !important;
            }
            /* Desktop: show sidebar toggle, hide mobile elements */
            .desktop-toggle {
                display: flex !important;
            }
            .mobile-toggle,
            .mobile-only {
                display: none !important;
            }
        }
        /* Mobile: hide desktop sidebar and toggle */
        @media (max-width: 767px) {
            #sidebar {
                display: none !important;
            }
            .desktop-toggle {
                display: none !important;
            }
            .mobile-toggle {
                display: flex !important;
            }
            .mobile-only {
                display: flex !important;
            }
        }
    </style>
    
    <!-- Ensure sidebar is visible on desktop (when not collapsed) -->
    <style>
        @media (min-width: 768px) {
            #sidebar:not(.sidebar-collapsed) {
                display: flex !important;
                transform: translateX(0) !important;
                position: relative !important;
            }
            /* Desktop: show sidebar toggle, hide mobile elements */
            .desktop-toggle {
                display: flex !important;
            }
            .mobile-toggle,
            .mobile-only {
                display: none !important;
            }
        }
        /* Mobile: hide desktop sidebar and toggle */
        @media (max-width: 767px) {
            #sidebar {
                display: none !important;
            }
            .desktop-toggle {
                display: none !important;
            }
            .mobile-toggle {
                display: flex !important;
            }
            .mobile-only {
                display: flex !important;
            }
        }
    </style>
    <style>
        /* Make wide tables scrollable on small screens */
        .responsive-table { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <!-- Mobile open sidebar button -->
    <button id="open-mobile-sidebar" class="md:hidden fixed top-4 left-4 z-50 p-2 bg-red-700 text-white rounded-lg shadow-lg">
        <i class="bi bi-list"></i>
    </button>
    <!-- Mobile Sidebar Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 md:hidden opacity-0 pointer-events-none transition-all duration-300 ease-out"></div>
    
    <!-- Mobile Sidebar -->
    <div id="mobile-sidebar" class="fixed inset-y-0 left-0 transform -translate-x-full md:hidden w-72 bg-gradient-to-b from-red-800 to-red-900 text-white z-50 transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] overflow-hidden flex flex-col shadow-2xl">
        <!-- Mobile sidebar header -->
        <div class="p-4 border-b border-red-700/50 sidebar-header">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3 sidebar-logo">
                    <div class="bg-white rounded-full p-1.5 shadow-lg">
                        <img src="images/logo.webp" alt="Valenzuela Logo" class="w-9 h-9 object-contain">
                    </div>
                    <div>
                        <h1 class="text-lg font-bold tracking-tight">PCMP</h1>
                        <p class="text-xs text-red-200">Consultation Management</p>
                    </div>
                </div>
                <button id="close-mobile-sidebar" class="text-white/80 p-2 hover:bg-red-700/50 hover:text-white rounded-lg transition-all duration-200 hover:rotate-90">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Mobile Navigation Menu (trimmed) -->
        <nav class="flex-1 py-4 px-3 overflow-y-auto">
            <!-- Public Consultation -->
            <div class="mt-2 mb-2 px-4">
                <p class="text-xs font-semibold text-red-300/80 uppercase tracking-wider">Public Consultation</p>
            </div>

            <a href="#" onclick="showSection('public-consultation')" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                <i class="bi bi-people-fill mr-3 text-lg"></i>
                <span>Consultation Dashboard</span>
            </a>
            <a href="#" onclick="showSection('consultation-management')" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-journal-text mr-3 text-lg"></i>
                <span>Consultation Management</span>
            </a>
            <a href="#" onclick="showSection('feedback')" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-chat-dots mr-3 text-lg"></i>
                <span>Feedback Collection</span>
            </a>
            <a href="#" onclick="showSection('pc-documents')" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-folder2-open mr-3 text-lg"></i>
                <span>Document Management</span>
            </a>

            <!-- Administration (keep) -->
            <div class="mt-4 mb-2 px-4">
                <p class="text-xs font-semibold text-red-300/80 uppercase tracking-wider">Administration</p>
            </div>
            <a href="#" onclick="showSection('users')" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-people mr-3 text-lg"></i>
                <span>User Management</span>
            </a>
            <a href="#" onclick="showSection('announcements')" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-megaphone mr-3 text-lg"></i>
                <span>Announcements</span>
            </a>
            <a href="#" onclick="showSection('audit')" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-shield-check mr-3 text-lg"></i>
                <span>Audit Log</span>
            </a>
        </nav>
        
        <!-- Mobile User Profile Section - Fixed at Bottom -->
        <div class="p-3 mt-auto border-t border-red-700/40">
            <!-- User Info -->
            <div class="flex items-center space-x-2.5 mb-2.5">
                <div class="w-9 h-9 rounded-full bg-red-700 flex items-center justify-center">
                    <i class="bi bi-person-fill text-white text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">Admin User</p>
                    <p class="text-xs text-red-300 truncate">Administrator</p>
                </div>
            </div>
            
            <!-- Action Buttons - Side by Side -->
            <div class="flex gap-2">
                <a href="#" onclick="showSection('profile')" class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-medium bg-red-700 hover:bg-red-600 text-white rounded-lg transition-colors">
                    <i class="bi bi-person-gear"></i>
                    <span>Profile</span>
                </a>
                <a href="#" onclick="logout()" class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-medium bg-red-950 hover:bg-red-900 text-red-200 rounded-lg transition-colors">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
    
    <div class="flex h-screen overflow-hidden">
        <!-- Desktop Sidebar -->
        <aside id="sidebar" class="sidebar sidebar-expanded w-64 bg-gradient-to-b from-red-800 to-red-900 text-white flex-shrink-0 flex flex-col transition-all duration-300 ease-in-out animate-slide-in-left h-screen fixed md:relative z-30 -translate-x-full md:translate-x-0">
            <!-- Logo Section -->
            <div class="p-6 border-b border-red-700 animate-fade-in sidebar-logo">
                <a href="#" onclick="showSection('public-consultation')" class="flex items-center space-x-3 hover:opacity-80 transition-all duration-300 transform hover:scale-105 group">
                    <div class="bg-white rounded-full shadow-md flex items-center justify-center overflow-hidden transform transition-all duration-300 group-hover:scale-110 group-hover:rotate-6" style="width: 70px; height: 70px;">
                        <img src="images/logo.webp" alt="Valenzuela Logo" style="width: 100%; height: 100%;" class="object-contain">
                    </div>
                    <div class="transform transition-all duration-300 group-hover:translate-x-1 sidebar-text">
                        <h1 class="text-lg font-bold">PCMP</h1>
                        <p class="text-xs text-red-200">City of Valenzuela</p>
                    </div>
                </a>
            </div>
            
            <!-- Navigation Menu (trimmed) -->
            <nav class="flex-1 overflow-y-auto py-4">
                <div class="px-4 space-y-1">
                    <!-- Public Consultation -->
                    <div class="pt-2 pb-2 sidebar-text">
                        <p class="px-4 text-xs font-semibold text-red-300 uppercase tracking-wider">Public Consultation</p>
                    </div>
                    <a href="#" onclick="showSection('public-consultation')" class="nav-item" data-section="public-consultation">
                        <i class="bi bi-people-fill"></i>
                        <span class="sidebar-text">Consultation Dashboard</span>
                    </a>
                    <a href="#" onclick="showSection('consultation-management')" class="nav-item" data-section="consultation-management">
                        <i class="bi bi-journal-text"></i>
                        <span class="sidebar-text">Consultation Management</span>
                    </a>
                    <a href="#" onclick="showSection('feedback')" class="nav-item" data-section="feedback">
                        <i class="bi bi-chat-dots"></i>
                        <span class="sidebar-text">Feedback Collection</span>
                    </a>
                    <a href="#" onclick="showSection('pc-documents')" class="nav-item" data-section="pc-documents">
                        <i class="bi bi-folder2-open"></i>
                        <span class="sidebar-text">Document Management</span>
                    </a>

                    <!-- Administration (keep user management) -->
                    <div class="pt-4 pb-2 sidebar-text">
                        <p class="px-4 text-xs font-semibold text-red-300 uppercase tracking-wider">Administration</p>
                    </div>
                    <a href="#" onclick="showSection('users')" class="nav-item" data-section="users">
                        <i class="bi bi-people"></i>
                        <span class="sidebar-text">User Management</span>
                    </a>
                    <a href="#" onclick="showSection('announcements')" class="nav-item" data-section="announcements">
                        <i class="bi bi-megaphone"></i>
                        <span class="sidebar-text">Announcements</span>
                    </a>
                    <a href="#" onclick="showSection('audit')" class="nav-item" data-section="audit">
                        <i class="bi bi-shield-check"></i>
                        <span class="sidebar-text">Audit Log</span>
                    </a>
                </div>
            </nav>
            
            <!-- User Info -->
            <div class="p-4 border-t border-red-700 sidebar-user">
                <div class="flex items-center space-x-3">
                    <div id="sidebar-profile-pic" class="bg-red-600 rounded-full w-10 h-10 flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-person-fill text-white"></i>
                    </div>
                    <div class="flex-1 min-w-0 sidebar-text">
                        <p class="text-sm font-semibold truncate">Admin User</p>
                        <p class="text-xs text-red-200 truncate">Administrator</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header / Navbar -->
            <nav class="bg-white shadow-md border-b border-gray-200 sticky top-0 z-40">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-16">
                        <!-- Left Side: Toggle buttons and Logo -->
                        <div class="flex items-center">
                            <!-- Sidebar Toggle Button (Desktop) - Always visible on md+ screens -->
                            <button id="sidebar-toggle" class="desktop-toggle items-center justify-center w-10 h-10 rounded-lg text-gray-600 bg-gray-50 hover:bg-gray-100 hover:text-red-600 focus:outline-none transition-all duration-200 border border-gray-200" title="Toggle Sidebar">
                                <i class="bi bi-layout-sidebar-inset text-xl"></i>
                            </button>
                            
                            <!-- Mobile Menu Button -->
                            <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 hover:text-gray-900 focus:outline-none p-2 hover:bg-gray-100 rounded-lg transition-all duration-200">
                                <i class="bi bi-list text-2xl"></i>
                            </button>
                            
                            <!-- Logo (Mobile) -->
                            <div class="mobile-only flex items-center ml-2">
                                <img src="images/logo.webp" alt="Valenzuela" class="w-10 h-10 object-contain">
                            </div>
                        </div>
                        
                        <!-- Page Title & Breadcrumb -->
                        <div class="flex-1 flex items-center justify-center md:justify-start min-w-0">
                            <div class="ml-2 md:ml-4 min-w-0">
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800">Dashboard</h2>
                                <nav class="hidden md:flex text-sm text-gray-600 mt-1" aria-label="Breadcrumb">
                                    <a href="#" onclick="showSection('public-consultation')" class="hover:text-red-600">Home</a>
                                    <i class="bi bi-chevron-right mx-2 text-xs"></i>
                                    <span id="breadcrumb-current" class="text-gray-800 font-medium">Dashboard</span>
                                </nav>
                            </div>
                        </div>
                        
                        <!-- Right Side Actions hayss -->
                        <div class="flex items-center space-x-1 md:space-x-4">
                            <!-- Search Bar (Hidden on mobile) -->
                            <div class="hidden lg:block">
                                <div class="relative group">
                                    <input type="text" 
                                           id="quick-search"
                                           placeholder="Quick search documents... (Ctrl+K)"
                                           class="w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all duration-200">
                                    <i class="bi bi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 transition-all group-focus-within:text-red-600 group-focus-within:scale-110"></i>
                                </div>
                            </div>
                            
                            <!-- Dark Mode Toggle -->
                            <button id="theme-toggle" onclick="toggleTheme()" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition" title="Toggle dark mode">
                                <i class="bi bi-moon-fill text-lg md:text-xl dark-mode-icon"></i>
                                <i class="bi bi-sun-fill text-xl light-mode-icon hidden"></i>
                            </button>
                        
                            <!-- Notifications Bell -->
                            <div class="relative">
                                <button id="notifications-btn" onclick="toggleNotifications()" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition relative" title="Notifications">
                                    <i class="bi bi-bell text-lg md:text-xl"></i>
                                    <span id="notif-badge" class="hidden absolute top-1 right-1 bg-red-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">0</span>
                                </button>
                                <!-- Notifications Dropdown -->
                                <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 z-50 max-h-96 flex flex-col">
                                    <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                                        <h3 class="font-semibold text-gray-900">Notifications</h3>
                                        <button onclick="toggleNotifications()" class="text-gray-400 hover:text-gray-600">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                    <div id="notifications-list" class="overflow-y-auto flex-1 space-y-2 p-3">
                                        <div class="text-sm text-gray-500 text-center py-4">No notifications yet</div>
                                    </div>
                                </div>
                            </div>
                        
                            <!-- User Profile Dropdown -->
                            <div class="relative">
                                <button id="profile-btn" class="flex items-center space-x-3 p-2 hover:bg-gray-100 rounded-lg transition">
                                    <div class="bg-red-600 rounded-full w-8 h-8 flex items-center justify-center text-white">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <div class="hidden sm:block text-left">
                                        <p class="text-sm font-medium text-gray-800 truncate max-w-[120px] md:max-w-none">Admin User</p>
                                        <p class="text-xs text-gray-500">Administrator</p>
                                    </div>
                                    <i class="bi bi-chevron-down text-gray-600 text-xs hidden sm:inline"></i>
                                </button>
                            
                                <!-- Profile Dropdown -->
                                <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 z-50 animate-fade-in-up" style="background-color: white;">
                                    <div class="p-4 border-b border-gray-200">
                                        <p class="text-sm font-medium text-gray-800">admin@lgu.gov.ph</p>
                                        <p class="text-xs text-gray-500 mt-1">Legislative Office</p>
                                    </div>
                                    <div class="py-2">
                                        <a href="#" onclick="showSection('profile'); return false;" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="bi bi-person mr-2"></i>My Profile
                                        </a>
                                        <a href="#" onclick="showSection('settings'); return false;" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="bi bi-gear mr-2"></i>Settings
                                        </a>
                                        <a href="#" onclick="showSection('help'); return false;" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="bi bi-question-circle mr-2"></i>Help & Support
                                        </a>
                                    </div>
                                    <div class="border-t border-gray-200 py-2">
                                        <a href="javascript:void(0);" onclick="logout(); return false;" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 cursor-pointer">
                                            <i class="bi bi-box-arrow-right mr-2"></i>Logout
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto bg-gray-100 p-3 sm:p-4 lg:p-6">
                <!-- Content sections will be loaded here -->
                <div id="content-area">
                    <!-- ANNOUNCEMENTS (Admin) -->
                    <section id="announcements-section" class="mb-6">
                        <div class="flex gap-6 h-[70vh] items-start">
                            <!-- Left: Announcements (Posting & Recent) -->
                            <div class="w-1/2 min-w-0 flex flex-col gap-4">
                                <!-- Publisher Card (Compact Modern Style) -->
                                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                                    <form id="announcement-form" onsubmit="submitAnnouncement(event)" class="space-y-3">
                                        <input type="text" id="announcement_title" name="announcement_title" required class="input-field w-full text-sm font-medium border-0 border-b border-gray-300 focus:border-red-500 focus:ring-0 p-0 mb-2" placeholder="Announcement title...">
                                        <textarea id="announcement_content" name="announcement_content" rows="3" required class="input-field w-full text-sm border-0 focus:ring-0 p-0 resize-none" placeholder="Write your announcement message..."></textarea>
                                        <div class="flex justify-end gap-2 pt-2">
                                            <button type="button" onclick="document.getElementById('announcement-form').reset()" class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 rounded transition">Clear</button>
                                            <button type="submit" class="btn-primary px-4 py-1.5 text-sm">Publish</button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Recent Announcements List -->
                                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 flex-1 flex flex-col">
                                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Recent Announcements</h3>
                                    <div id="admin-announcements-list" class="space-y-2 overflow-auto flex-1">
                                        <?php
                                            $adminAnns = getAnnouncements(6, 0);
                                            if (empty($adminAnns)) {
                                                echo '<div class="text-xs text-gray-400 text-center py-4">No announcements yet.</div>';
                                            } else {
                                                foreach ($adminAnns as $a) {
                                                    $annId = (int)$a['id'];
                                                    $likes = json_decode($a['liked_by'] ?? '[]', true) ?? [];
                                                    $saves = json_decode($a['saved_by'] ?? '[]', true) ?? [];
                                                    $allow_comments = $a['allow_comments'] ?? true;
                                                    $userLiked = in_array($_SESSION['user_id'] ?? null, $likes) ? 'text-red-600' : 'text-gray-400';
                                                    $userSaved = in_array($_SESSION['user_id'] ?? null, $saves) ? 'text-blue-600' : 'text-gray-400';
                                                    echo '<div class="p-2.5 border border-gray-200 rounded hover:bg-gray-50 transition text-xs">';
                                                    echo '<div class="font-semibold text-gray-800 text-sm">' . htmlspecialchars(substr($a['title'], 0, 60)) . '</div>';
                                                    echo '<div class="text-gray-500 text-xs mt-0.5">' . date('M d, H:i', strtotime($a['created_at'])) . '</div>';
                                                    echo '<div class="mt-1.5 flex gap-2 text-xs flex-wrap">';
                                                    echo '<button type="button" onclick="toggleAnnouncementAction(event, ' . $annId . ', \'like\')" class="flex items-center gap-0.5 ' . $userLiked . ' hover:text-red-600 transition">';
                                                    echo '<i class="bi bi-heart-fill text-xs"></i><span>' . count($likes) . '</span></button>';
                                                    echo '<button type="button" onclick="toggleAnnouncementAction(event, ' . $annId . ', \'save\')" class="flex items-center gap-0.5 ' . $userSaved . ' hover:text-blue-600 transition">';
                                                    echo '<i class="bi bi-bookmark-fill text-xs"></i><span>' . count($saves) . '</span></button>';
                                                    echo '<button type="button" onclick="toggleAllowComments(event, ' . $annId . ')" class="flex items-center gap-0.5 ' . ($allow_comments ? 'text-green-600' : 'text-gray-400') . ' hover:text-green-700 transition" title="' . ($allow_comments ? 'Comments Allowed' : 'Comments Disabled') . '">';
                                                    echo '<i class="bi bi-chat-dots text-xs"></i><span>' . ($allow_comments ? 'On' : 'Off') . '</span></button>';
                                                    echo '</div>';
                                                    echo '</div>';
                                                }
                                            }
                                        ?>
                                    </div>
                                </div>
                            </div>


                        </div>
                    </section>

                    <!-- AUDIT LOG SECTION -->
                    <section id="audit-section" class="audit-section">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-900">Audit Logs</h2>
                                    <p class="text-gray-600 text-sm mt-1">Track all administrative actions and system activities</p>
                                </div>
                                <div class="flex gap-2 w-full sm:w-auto">
                                    <button onclick="exportAuditLogs()" class="btn-secondary flex items-center justify-center gap-2 px-4 py-2 text-sm">
                                        <i class="bi bi-download"></i>
                                        <span class="hidden sm:inline">Export</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Filters -->
                            <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
                                <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                    <i class="bi bi-funnel"></i> Filters
                                </h3>
                                <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin User</label>
                                        <input type="text" name="filter_admin" placeholder="Filter by admin name" value="<?php echo htmlspecialchars($_GET['filter_admin'] ?? ''); ?>" class="input-field w-full">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Action</label>
                                        <select name="filter_action" class="input-field w-full">
                                            <option value="">All Actions</option>
                                            <option value="login" <?php echo ($_GET['filter_action'] ?? '') === 'login' ? 'selected' : ''; ?>>Login</option>
                                            <option value="logout" <?php echo ($_GET['filter_action'] ?? '') === 'logout' ? 'selected' : ''; ?>>Logout</option>
                                            <option value="created" <?php echo ($_GET['filter_action'] ?? '') === 'created' ? 'selected' : ''; ?>>Created</option>
                                            <option value="updated" <?php echo ($_GET['filter_action'] ?? '') === 'updated' ? 'selected' : ''; ?>>Updated</option>
                                            <option value="deleted" <?php echo ($_GET['filter_action'] ?? '') === 'deleted' ? 'selected' : ''; ?>>Deleted</option>
                                            <option value="uploaded" <?php echo ($_GET['filter_action'] ?? '') === 'uploaded' ? 'selected' : ''; ?>>Uploaded</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Entity Type</label>
                                        <select name="filter_type" class="input-field w-full">
                                            <option value="">All Types</option>
                                            <option value="user" <?php echo ($_GET['filter_type'] ?? '') === 'user' ? 'selected' : ''; ?>>User</option>
                                            <option value="document" <?php echo ($_GET['filter_type'] ?? '') === 'document' ? 'selected' : ''; ?>>Document</option>
                                            <option value="consultation" <?php echo ($_GET['filter_type'] ?? '') === 'consultation' ? 'selected' : ''; ?>>Consultation</option>
                                            <option value="system" <?php echo ($_GET['filter_type'] ?? '') === 'system' ? 'selected' : ''; ?>>System</option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-3 flex gap-2">
                                        <button type="submit" class="btn-primary flex items-center gap-2 px-4 py-2">
                                            <i class="bi bi-search"></i> Apply Filters
                                        </button>
                                        <a href="?audit_page=1" class="btn-secondary flex items-center gap-2 px-4 py-2">
                                            <i class="bi bi-arrow-clockwise"></i> Reset
                                        </a>
                                    </div>
                                </form>
                            </div>

                            <!-- Tabs for Admin/User Logs -->
                            <div class="mb-6 border-b border-gray-200">
                                <div class="flex gap-0">
                                    <button onclick="switchAuditTab('admin')" id="admin-tab-btn" class="px-6 py-3 font-medium text-gray-900 border-b-2 border-red-600 cursor-pointer hover:text-red-600">
                                        <i class="bi bi-shield-lock-fill mr-2"></i>Admin Actions <span class="bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded-full ml-2"><?php echo $totalAdminLogs; ?></span>
                                    </button>
                                    <button onclick="switchAuditTab('user')" id="user-tab-btn" class="px-6 py-3 font-medium text-gray-600 border-b-2 border-transparent cursor-pointer hover:text-gray-900">
                                        <i class="bi bi-people-fill mr-2"></i>User Activity <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full ml-2"><?php echo $totalUserLogs; ?></span>
                                    </button>
                                </div>
                            </div>

                            <!-- Admin Actions Table -->
                            <div id="admin-logs-section" class="overflow-x-auto">
                                <?php if (empty($adminLogs)): ?>
                                    <div class="text-center py-12">
                                        <i class="bi bi-inbox text-5xl text-gray-300 block mb-3"></i>
                                        <p class="text-gray-500 text-lg">No admin actions found</p>
                                    </div>
                                <?php else: ?>
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 border-b border-gray-200">
                                            <tr>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Timestamp</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Admin User</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Action</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Entity Type</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Entity ID</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">IP Address</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Status</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Details</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <?php foreach ($adminLogs as $log): ?>
                                                <tr class="hover:bg-gray-50 transition-colors">
                                                    <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium"><?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            <i class="bi bi-shield-fill"></i>
                                                            <?php echo htmlspecialchars($log['admin_user']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                            $actionColor = 'gray';
                                                            if (strpos(strtolower($log['action']), 'delete') !== false) $actionColor = 'red';
                                                            elseif (strpos(strtolower($log['action']), 'create') !== false || strpos(strtolower($log['action']), 'post') !== false) $actionColor = 'green';
                                                            elseif (strpos(strtolower($log['action']), 'update') !== false) $actionColor = 'blue';
                                                            elseif (strpos(strtolower($log['action']), 'login') !== false) $actionColor = 'indigo';
                                                        ?>
                                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-<?php echo $actionColor; ?>-100 text-<?php echo $actionColor; ?>-800">
                                                            <?php echo htmlspecialchars($log['action']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            <?php echo htmlspecialchars(ucfirst($log['entity_type'] ?? 'N/A')); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($log['entity_id'] ?? '-'); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-mono text-xs"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                            $statusColor = $log['status'] === 'success' ? 'green' : 'red';
                                                            $statusIcon = $log['status'] === 'success' ? 'check-circle-fill' : 'x-circle-fill';
                                                        ?>
                                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                                                            <i class="bi bi-<?php echo $statusIcon; ?>"></i>
                                                            <?php echo ucfirst($log['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <button onclick="showAuditDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)" class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                                                            View
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>

                            <!-- User Activity Table -->
                            <div id="user-logs-section" class="overflow-x-auto" style="display: none;">
                                <?php if (empty($userLogs)): ?>
                                    <div class="text-center py-12">
                                        <i class="bi bi-inbox text-5xl text-gray-300 block mb-3"></i>
                                        <p class="text-gray-500 text-lg">No user activities found</p>
                                    </div>
                                <?php else: ?>
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 border-b border-gray-200">
                                            <tr>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Timestamp</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">User</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Activity</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Type</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Entity ID</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">IP Address</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Status</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Details</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <?php foreach ($userLogs as $log): ?>
                                                <tr class="hover:bg-gray-50 transition-colors">
                                                    <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium"><?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            <i class="bi bi-person-fill"></i>
                                                            <?php echo htmlspecialchars($log['admin_user']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                            $actionColor = 'gray';
                                                            if (strpos(strtolower($log['action']), 'post') !== false) $actionColor = 'purple';
                                                            elseif (strpos(strtolower($log['action']), 'suggestion') !== false) $actionColor = 'indigo';
                                                        ?>
                                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-<?php echo $actionColor; ?>-100 text-<?php echo $actionColor; ?>-800">
                                                            <?php echo htmlspecialchars($log['action']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            <?php echo htmlspecialchars(ucfirst($log['entity_type'] ?? 'N/A')); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($log['entity_id'] ?? '-'); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-mono text-xs"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                            $statusColor = $log['status'] === 'success' ? 'green' : 'red';
                                                            $statusIcon = $log['status'] === 'success' ? 'check-circle-fill' : 'x-circle-fill';
                                                        ?>
                                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                                                            <i class="bi bi-<?php echo $statusIcon; ?>"></i>
                                                            <?php echo ucfirst($log['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <button onclick="showAuditDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)" class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                                                            View
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>

                                    <!-- Pagination -->
                                    <div class="mt-6 flex items-center justify-between">
                                        <div class="text-sm text-gray-600">
                                            Showing <span class="font-medium"><?php echo (($page - 1) * $pageSize) + 1; ?></span> to 
                                            <span class="font-medium"><?php echo min($page * $pageSize, $totalLogs); ?></span> of 
                                            <span class="font-medium"><?php echo $totalLogs; ?></span> logs
                                        </div>
                                        <div class="flex gap-2">
                                            <?php if ($page > 1): ?>
                                                <a href="?audit_page=<?php echo $page - 1; ?><?php echo isset($_GET['filter_admin']) ? '&filter_admin=' . urlencode($_GET['filter_admin']) : ''; ?><?php echo isset($_GET['filter_action']) ? '&filter_action=' . urlencode($_GET['filter_action']) : ''; ?><?php echo isset($_GET['filter_type']) ? '&filter_type=' . urlencode($_GET['filter_type']) : ''; ?>" class="btn-secondary px-4 py-2">
                                                    <i class="bi bi-chevron-left"></i> Previous
                                                </a>
                                            <?php endif; ?>
                                            
                                            <div class="flex items-center gap-1">
                                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                    <a href="?audit_page=<?php echo $i; ?><?php echo isset($_GET['filter_admin']) ? '&filter_admin=' . urlencode($_GET['filter_admin']) : ''; ?><?php echo isset($_GET['filter_action']) ? '&filter_action=' . urlencode($_GET['filter_action']) : ''; ?><?php echo isset($_GET['filter_type']) ? '&filter_type=' . urlencode($_GET['filter_type']) : ''; ?>" class="px-3 py-1 rounded-lg text-sm font-medium transition-colors <?php echo $i === $page ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                <?php endfor; ?>
                                            </div>

                                            <?php if ($page < $totalPages): ?>
                                                <a href="?audit_page=<?php echo $page + 1; ?><?php echo isset($_GET['filter_admin']) ? '&filter_admin=' . urlencode($_GET['filter_admin']) : ''; ?><?php echo isset($_GET['filter_action']) ? '&filter_action=' . urlencode($_GET['filter_action']) : ''; ?><?php echo isset($_GET['filter_type']) ? '&filter_type=' . urlencode($_GET['filter_type']) : ''; ?>" class="btn-secondary px-4 py-2">
                                                    Next <i class="bi bi-chevron-right"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- DASHBOARD SECTION -->
                    <section id="dashboard-section" class="mb-6" style="display: none;">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <!-- Stats Cards -->
                            <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-red-600">
                                <div class="text-gray-600 text-sm font-medium">Total Users</div>
                                <div class="text-3xl font-bold text-gray-900 mt-2"><?php echo $totalUsers ?? 0; ?></div>
                                <div class="text-gray-500 text-xs mt-2">Registered citizens</div>
                            </div>
                            <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-blue-600">
                                <div class="text-gray-600 text-sm font-medium">User Posts</div>
                                <div class="text-3xl font-bold text-gray-900 mt-2"><?php echo isset($allPosts) ? count($allPosts) : 0; ?></div>
                                <div class="text-gray-500 text-xs mt-2">Total concerns submitted</div>
                            </div>
                            <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-green-600">
                                <div class="text-gray-600 text-sm font-medium">Announcements</div>
                                <div class="text-3xl font-bold text-gray-900 mt-2"><?php echo isset($allAnnouncements) ? count($allAnnouncements) : 0; ?></div>
                                <div class="text-gray-500 text-xs mt-2">Active announcements</div>
                            </div>
                            <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-yellow-600">
                                <div class="text-gray-600 text-sm font-medium">Audit Logs</div>
                                <div class="text-3xl font-bold text-gray-900 mt-2"><?php echo isset($totalLogs) ? $totalLogs : 0; ?></div>
                                <div class="text-gray-500 text-xs mt-2">System activities tracked</div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Dashboard Overview</h3>
                            <p class="text-gray-600">Welcome to the admin dashboard. Use the menu on the left to manage different aspects of the system.</p>
                        </div>
                    </section>

                    <!-- USER MANAGEMENT SECTION -->
                    <section id="user-management-section" class="mb-6" style="display: none;">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-900">User Management</h2>
                                    <p class="text-gray-600 text-sm mt-1">Manage registered citizens and their accounts</p>
                                </div>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 border-b border-gray-200">
                                        <tr>
                                            <th class="px-6 py-3 text-left font-semibold text-gray-900">Name</th>
                                            <th class="px-6 py-3 text-left font-semibold text-gray-900">Email</th>
                                            <th class="px-6 py-3 text-left font-semibold text-gray-900">Role</th>
                                            <th class="px-6 py-3 text-left font-semibold text-gray-900">Status</th>
                                            <th class="px-6 py-3 text-left font-semibold text-gray-900">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php
                                            $allUsers = isset($users) ? $users : [];
                                            if (empty($allUsers)) {
                                                echo '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No users found</td></tr>';
                                            } else {
                                                foreach ($allUsers as $u) {
                                                    echo '<tr class="hover:bg-gray-50">';
                                                    echo '<td class="px-6 py-4">' . htmlspecialchars($u['fullname'] ?? 'N/A') . '</td>';
                                                    echo '<td class="px-6 py-4">' . htmlspecialchars($u['email'] ?? 'N/A') . '</td>';
                                                    echo '<td class="px-6 py-4"><span class="px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">' . htmlspecialchars($u['role'] ?? 'User') . '</span></td>';
                                                    echo '<td class="px-6 py-4"><span class="px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span></td>';
                                                    echo '<td class="px-6 py-4"><button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View</button></td>';
                                                    echo '</tr>';
                                                }
                                            }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <!-- DOCUMENT MANAGEMENT SECTION -->
                    <section id="document-management-section" class="mb-6" style="display: none;">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-900">Document Management</h2>
                                    <p class="text-gray-600 text-sm mt-1">Manage official documents and charters</p>
                                </div>
                                <button class="btn-primary px-4 py-2">Upload Document</button>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="text-2xl mb-2"><i class="bi bi-file-earmark-text-fill text-gray-700"></i></div>
                                    <h3 class="font-semibold text-gray-900">Valenzuela Citizen Charter</h3>
                                    <p class="text-sm text-gray-600 mt-1">A comprehensive charter establishing the rights, responsibilities, and commitments of the City Government towards its citizens.</p>
                                    <div class="mt-4 flex gap-2">
                                        <button class="text-sm text-red-600 hover:text-red-800">View</button>
                                        <button class="text-sm text-gray-600 hover:text-blue-800">Download</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- CONSULTATION MANAGEMENT SECTION -->
                    <section id="consultation-management-section" class="mb-6" style="display: none;">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6 gap-4">
                                <div>
                                    <h2 class="text-2xl font-bold text-red-800 mb-2">Consultation Management</h2>
                                    <p class="text-gray-600">Manage all public consultations, track feedback, and monitor engagement</p>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4 md:mt-0">
                                    <div class="bg-red-100 rounded-lg p-4 text-center">
                                        <div class="text-xs text-gray-600">Total Consultations</div>
                                        <div class="text-2xl font-bold text-red-800 mt-1"><?= (int)$consult_total ?></div>
                                    </div>
                                    <div class="bg-red-100 rounded-lg p-4 text-center">
                                        <div class="text-xs text-gray-600">Active Consultations</div>
                                        <div class="text-2xl font-bold text-red-800 mt-1"><?= (int)$consult_open ?></div>
                                    </div>
                                    <div class="bg-red-100 rounded-lg p-4 text-center">
                                        <div class="text-xs text-gray-600">Closed Consultations</div>
                                        <div class="text-2xl font-bold text-red-800 mt-1"><?= (int)$consult_scheduled ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="overflow-x-auto mt-6">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="p-2 text-left">Title</th>
                                            <th class="p-2 text-left">Category</th>
                                            <th class="p-2 text-left">Start</th>
                                            <th class="p-2 text-left">End</th>
                                            <th class="p-2 text-left">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($consultations)): ?>
                                            <tr><td colspan="5" class="text-center text-gray-400">No consultations found.</td></tr>
                                        <?php else: foreach ($consultations as $c): ?>
                                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                                <td class="p-2"><?= htmlspecialchars($c['title'] ?? '-') ?></td>
                                                <td class="p-2"><?= htmlspecialchars($c['category'] ?? '-') ?></td>
                                                <td class="p-2">
                                                    <?= !empty($c['start_date']) ? htmlspecialchars(date('M d, Y H:i', strtotime($c['start_date']))) : '-' ?>
                                                </td>
                                                <td class="p-2">
                                                    <?= !empty($c['end_date']) ? htmlspecialchars(date('M d, Y H:i', strtotime($c['end_date']))) : '-' ?>
                                                </td>
                                                <td class="p-2">
                                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium
                                                        <?= ($c['status'] ?? '') === 'active' ? 'bg-green-100 text-green-800' : ((($c['status'] ?? '') === 'closed') ? 'bg-gray-200 text-gray-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                                        <?= htmlspecialchars(ucfirst($c['status'] ?? 'draft')) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <!-- FEEDBACK MANAGEMENT SECTION -->
                    <section id="feedback-management-section" class="mb-6" style="display: none;">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 gap-3">
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-900">Feedback Management</h2>
                                    <p class="text-gray-600 text-sm mt-1">Review and respond to user feedback</p>
                                </div>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <?php if (empty($feedbackList)): ?>
                                    <div class="text-center text-gray-400 py-8 text-sm">
                                        No feedback has been submitted yet.
                                    </div>
                                <?php else: ?>
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 border-b border-gray-200">
                                            <tr>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-900">Guest</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-900">Consultation ID</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-900">Rating</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-900">Category</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-900">Message</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-900">Status</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-900">Submitted</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <?php foreach ($feedbackList as $f): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-2">
                                                        <div class="font-medium text-gray-900">
                                                            <?= htmlspecialchars($f['guest_name'] ?? 'Guest') ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            <?= htmlspecialchars($f['guest_email'] ?? '') ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-2 text-gray-700">
                                                        <?= htmlspecialchars($f['consultation_id'] ?? '-') ?>
                                                    </td>
                                                    <td class="px-4 py-2 text-gray-700">
                                                        <?= htmlspecialchars($f['rating'] !== null ? $f['rating'] . '/5' : '-') ?>
                                                    </td>
                                                    <td class="px-4 py-2 text-gray-700">
                                                        <?= htmlspecialchars($f['category'] ?? '-') ?>
                                                    </td>
                                                    <td class="px-4 py-2 text-gray-700 max-w-xs">
                                                        <span class="line-clamp-2">
                                                            <?= htmlspecialchars($f['message'] ?? '') ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium
                                                            <?= ($f['status'] ?? 'new') === 'new' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?>">
                                                            <?= htmlspecialchars(ucfirst($f['status'] ?? 'new')) ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-2 text-gray-700 text-xs">
                                                        <?= !empty($f['created_at']) ? htmlspecialchars(date('M d, Y H:i', strtotime($f['created_at']))) : '-' ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
            
            <!-- Footer -->
            <footer class="bg-white border-t border-gray-200">
                <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-3 md:py-4">
                    <!-- Desktop Layout -->
                    <div class="hidden md:flex justify-between items-center">
                        <div class="flex items-center space-x-3">
                            <img src="images/logo.webp" alt="Valenzuela" class="w-10 h-10 object-contain">
                            <div class="text-sm text-gray-600">
                                &copy; 2025 City Government of Valenzuela - LRMS. All rights reserved.
                            </div>
                        </div>
                        <div class="flex items-center space-x-6">
                            <a href="#" class="text-sm text-gray-600 hover:text-red-600">Privacy</a>
                            <a href="#" class="text-sm text-gray-600 hover:text-red-600">Terms</a>
                            <a href="#" class="text-sm text-gray-600 hover:text-red-600">Support</a>
                        </div>
                    </div>
                    
                    <!-- Mobile Layout -->
                    <div class="md:hidden text-center">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 6px;">
                            <img src="images/logo.webp" alt="Valenzuela" style="width: 24px; height: 24px; object-fit: contain;">
                            <span class="text-xs text-gray-600">&copy; 2025 LRMS</span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <a href="#" class="text-xs text-gray-500 hover:text-red-600">Privacy</a>
                            <span class="text-gray-300">•</span>
                            <a href="#" class="text-xs text-gray-500 hover:text-red-600">Terms</a>
                            <span class="text-gray-300">•</span>
                            <a href="#" class="text-xs text-gray-500 hover:text-red-600">Support</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

    <!-- Upload Document Modal -->
    <div id="upload-modal" class="modal">
        <div class="modal-content p-6 max-w-2xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-900">Upload Document</h3>
                <button onclick="closeModal('upload-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            <form id="upload-form" onsubmit="handleDocumentUpload(event)">
                <div class="space-y-4">
                    <!-- File Upload Area -->
                    <div id="dropzone" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-red-500 transition cursor-pointer">
                        <i class="bi bi-cloud-upload text-5xl text-gray-400 mb-2"></i>
                        <p class="text-gray-600 mb-2">Drag and drop your file here or click to browse</p>
                        <input type="file" id="file-input" class="hidden" accept=".pdf,.doc,.docx" onchange="handleFileSelect(event)">
                        <button type="button" onclick="document.getElementById('file-input').click()" class="btn-outline mt-2">
                            <i class="bi bi-folder2-open mr-2"></i>Select File
                        </button>
                        <p id="file-name" class="text-sm text-gray-500 mt-2"></p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Reference Number</label>
                            <input type="text" name="reference" class="input-field" placeholder="ORD-2025-001" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Document Type</label>
                            <select name="type" class="input-field" required>
                                <option value="">Select type</option>
                                <option value="ordinance">Ordinance</option>
                                <option value="resolution">Resolution</option>
                                <option value="session">Session Minutes</option>
                                <option value="agenda">Agenda</option>
                                <option value="committee">Committee Report</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                        <input type="text" name="title" class="input-field" placeholder="Enter document title" required>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Document Date</label>
                            <input type="date" name="date" class="input-field" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="input-field" required>
                                <option value="draft">Draft</option>
                                <option value="pending">Pending Review</option>
                                <option value="approved">Approved</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" class="input-field" rows="3" placeholder="Enter document description"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tags (comma separated)</label>
                            <input type="text" name="tags" class="input-field" placeholder="budget, finance, 2025">
                        </div>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('upload-modal')" class="btn-outline">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <i class="bi bi-upload mr-2"></i>Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Document View Modal -->
    <div id="view-modal" class="modal">
        <div class="modal-content p-6 max-w-4xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-900">Document Details</h3>
                <button onclick="closeModal('view-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            <div id="document-details"></div>
        </div>
    </div>

    <!-- Audit Log Details Modal -->
    <div id="audit-modal" class="modal">
        <div class="modal-content p-6 max-w-2xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-900">Audit Log Details</h3>
                <button onclick="closeModal('audit-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            <div id="audit-details" class="space-y-4">
                <!-- Details will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Announcement Detail Modal -->
    <div id="announcement-detail-modal" class="modal" style="display: none; align-items: center; justify-content: center;">
        <div class="modal-content p-6 max-w-4xl w-full max-h-screen overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 id="ann-detail-title" class="text-2xl font-bold text-gray-900"></h3>
                <button onclick="closeModal('announcement-detail-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Announcement Detail (Left) -->
                <div>
                    <div id="ann-detail-content" class="text-gray-800 mb-4"></div>
                    <div id="ann-detail-meta" class="text-sm text-gray-600 mb-4"></div>
                    <div class="flex gap-3 text-sm">
                        <button type="button" id="ann-like-btn" onclick="toggleAnnouncementAction(event, null, 'like')" class="flex items-center gap-1 text-gray-600 hover:text-red-600">
                            <i class="bi bi-heart-fill"></i><span id="ann-like-count">0</span>
                        </button>
                        <button type="button" id="ann-save-btn" onclick="toggleAnnouncementAction(event, null, 'save')" class="flex items-center gap-1 text-gray-600 hover:text-blue-600">
                            <i class="bi bi-bookmark-fill"></i><span id="ann-save-count">0</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script src="app-features.js"></script>
    
    <!-- Desktop Sidebar Toggle Functionality - Must run after DOM is ready -->
    <script>
        // ========================================
        // Desktop Sidebar Toggle Functionality
        // ========================================
        (function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = sidebar?.nextElementSibling;
            
            if (!sidebarToggle || !sidebar) {
                console.log('Sidebar toggle or sidebar not found');
                return;
            }
            
            // Ensure sidebar has proper initial classes
            if (!sidebar.classList.contains('sidebar-collapsed')) {
                sidebar.classList.add('sidebar-expanded');
            }
            
            // Check for saved sidebar state - apply immediately without animation
            const sidebarState = localStorage.getItem('sidebarCollapsed');
            if (sidebarState === 'true') {
                // Apply collapsed state immediately (no animation on page load)
                sidebar.style.transition = 'none';
                sidebar.classList.remove('sidebar-expanded', 'w-64');
                sidebar.classList.add('sidebar-collapsed');
                sidebarToggle.classList.add('sidebar-hidden');
                
                // Re-enable transitions after a frame
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        sidebar.style.transition = '';
                    });
                });
            }
            
            // Toggle sidebar on button click
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const isExpanded = sidebar.classList.contains('sidebar-expanded');
                
                // Add a subtle scale animation to the button
                this.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
                
                if (isExpanded) {
                    // Collapse sidebar with smooth animation
                    sidebar.classList.remove('sidebar-expanded', 'w-64');
                    sidebar.classList.add('sidebar-collapsed');
                    this.classList.add('sidebar-hidden');
                    localStorage.setItem('sidebarCollapsed', 'true');
                } else {
                    // Expand sidebar with smooth animation
                    sidebar.classList.remove('sidebar-collapsed');
                    sidebar.classList.add('sidebar-expanded', 'w-64');
                    this.classList.remove('sidebar-hidden');
                    localStorage.setItem('sidebarCollapsed', 'false');
                } 
            });
            
            console.log('Desktop sidebar toggle initialized');
        })();
        
        // ========================================
        // Logout Function - Defined globally
        // ========================================
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                // Clear any stored session data
                localStorage.removeItem('isLoggedIn');
                localStorage.removeItem('currentUser');
                sessionStorage.removeItem('isLoggedIn');
                sessionStorage.removeItem('currentUser');
                
                // Redirect to login page with logout success message
                window.location.href = 'login.php?logout=success';
            }
            return false;
        }
        
        // ========================================
        // Mobile Sidebar Toggle - Inline backup
        // ========================================
        (function() {
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileSidebar = document.getElementById('mobile-sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const closeMobileSidebarBtn = document.getElementById('close-mobile-sidebar');
            
            function openMobileSidebar() {
                if (!mobileSidebar || !sidebarOverlay) return;
                
                // Show overlay
                sidebarOverlay.classList.remove('opacity-0', 'pointer-events-none');
                sidebarOverlay.classList.add('opacity-100', 'pointer-events-auto');
                
                // Slide in sidebar
                mobileSidebar.classList.remove('-translate-x-full');
                mobileSidebar.classList.add('translate-x-0');
                
                // Prevent body scroll
                document.body.style.overflow = 'hidden';
            }
            
            function closeMobileSidebar() {
                if (!mobileSidebar || !sidebarOverlay) return;
                
                // Hide overlay
                sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
                sidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto');
                
                // Slide out sidebar
                mobileSidebar.classList.add('-translate-x-full');
                mobileSidebar.classList.remove('translate-x-0');
                
                // Restore body scroll
                document.body.style.overflow = '';
            }
            
            // Event listeners
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openMobileSidebar();
                });
            }
            
            if (closeMobileSidebarBtn) {
                closeMobileSidebarBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeMobileSidebar();
                });
            }
            
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeMobileSidebar);
            }
            
            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && mobileSidebar && mobileSidebar.classList.contains('translate-x-0')) {
                    closeMobileSidebar();
                }
            });
            
            // Close sidebar when clicking navigation links
            const mobileNavLinks = mobileSidebar?.querySelectorAll('nav a');
            mobileNavLinks?.forEach(function(link) {
                link.addEventListener('click', function() {
                    setTimeout(closeMobileSidebar, 200);
                });
            });
            
            console.log('Mobile sidebar toggle initialized');
        })();

        // ========================================
        // Section Switching Functionality
        // ========================================
        function switchSection(sectionId) {
            // Hide all sections
            const sections = document.querySelectorAll('section');
            sections.forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            const selectedSection = document.getElementById(sectionId);
            if (selectedSection) {
                selectedSection.style.display = 'block';
            }
            
            // Close mobile sidebar if open
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const mobileNav = document.getElementById('mobile-nav');
            if (sidebarOverlay && mobileNav) {
                sidebarOverlay.classList.remove('active');
                mobileNav.classList.remove('active');
            }
        }

        // ========================================
        // Audit Log Tabs Functionality
        // ========================================
        function switchAuditTab(tab) {
            const adminBtn = document.getElementById('admin-tab-btn');
            const userBtn = document.getElementById('user-tab-btn');
            const adminSection = document.getElementById('admin-logs-section');
            const userSection = document.getElementById('user-logs-section');

            if (tab === 'admin') {
                // Show admin logs
                adminSection.style.display = 'block';
                userSection.style.display = 'none';
                
                // Update button styles
                adminBtn.classList.add('text-gray-900', 'border-red-600');
                adminBtn.classList.remove('text-gray-600', 'border-transparent');
                userBtn.classList.add('text-gray-600', 'border-transparent');
                userBtn.classList.remove('text-gray-900', 'border-red-600');
            } else if (tab === 'user') {
                // Show user logs
                adminSection.style.display = 'none';
                userSection.style.display = 'block';
                
                // Update button styles
                userBtn.classList.add('text-gray-900', 'border-red-600');
                userBtn.classList.remove('text-gray-600', 'border-transparent');
                adminBtn.classList.add('text-gray-600', 'border-transparent');
                adminBtn.classList.remove('text-gray-900', 'border-red-600');
            }
        }

        // ========================================
        // Show Audit Log Details Modal
        // ========================================
        function showAuditDetails(log) {
            const modal = document.getElementById('audit-modal');
            const detailsDiv = document.getElementById('audit-details');
            
            if (!modal || !detailsDiv) return;

            // Build HTML for the details
            const detailsHTML = `
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Timestamp</label>
                        <p class="text-gray-900">${new Date(log.timestamp).toLocaleString()}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Status</label>
                        <p class="text-gray-900">
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium ${log.status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                ${log.status || 'Unknown'}
                            </span>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Admin/User</label>
                        <p class="text-gray-900">${log.admin_user || 'System'}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Action</label>
                        <p class="text-gray-900">${log.action || 'N/A'}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Entity Type</label>
                        <p class="text-gray-900">${log.entity_type || 'N/A'}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Entity ID</label>
                        <p class="text-gray-900">${log.entity_id || 'N/A'}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">IP Address</label>
                        <p class="text-gray-900 font-mono text-sm">${log.ip_address || 'N/A'}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">User Agent</label>
                        <p class="text-gray-900 text-sm break-words">${log.user_agent || 'N/A'}</p>
                    </div>
                </div>
                ${log.details ? `<div class="mt-4"><label class="text-sm font-semibold text-gray-600">Details</label><p class="text-gray-900 mt-2 p-3 bg-gray-50 rounded">${log.details}</p></div>` : ''}
            `;

            detailsDiv.innerHTML = detailsHTML;

            // Show modal
            modal.style.display = 'flex';
            modal.style.alignItems = 'center';
            modal.style.justifyContent = 'center';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
        }
    </script>
    <script>
    // Mobile sidebar open/close handlers
    (function(){
      var openBtn = document.getElementById('open-mobile-sidebar');
      var closeBtn = document.getElementById('close-mobile-sidebar');
      var mobileSidebar = document.getElementById('mobile-sidebar');
      var overlay = document.getElementById('sidebar-overlay');
      if (openBtn) openBtn.addEventListener('click', function(){
          mobileSidebar.classList.remove('-translate-x-full');
          overlay.classList.remove('opacity-0','pointer-events-none');
      });
      if (closeBtn) closeBtn.addEventListener('click', function(){
          mobileSidebar.classList.add('-translate-x-full');
          overlay.classList.add('opacity-0','pointer-events-none');
      });
      if (overlay) overlay.addEventListener('click', function(){
          mobileSidebar.classList.add('-translate-x-full');
          overlay.classList.add('opacity-0','pointer-events-none');
      });
    })();
    </script>
</body>
</html>
