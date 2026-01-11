<?php
session_start();
if (!isset($_SESSION['fullname'])) {
    header('Location: login.php');
    exit();
}
$fullname = $_SESSION['fullname'];
$user_id = $_SESSION['user_id'] ?? null;
require_once 'announcements.php';
require_once 'DATABASE/feedback.php';
require_once 'DATABASE/audit-log.php';
require_once 'DATABASE/posts.php';

// Redirect admins to the admin dashboard
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($current_role === 'admin') {
  header('Location: system-template-full.php');
  exit();
}

// Determine which section to display
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
$allowed_sections = ['dashboard', 'announcements', 'feedback', 'documents', 'audit-log', 'saved', 'settings'];
if (!in_array($section, $allowed_sections)) {
    $section = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PCMP – Citizen Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
    :root {
      --red-700: #b91c1c;
      --red-600: #dc2626;
      --red-500: #ef4444;
      --gray-100: #f3f4f6;
      --gray-200: #e5e7eb;
      --gray-600: #4b5563;
      --gray-700: #374151;
      --gray-800: #1f2937;
      --white: #ffffff;
      --text: #111827;
      --muted: #6b7280;
    }

    html.dark {
      --red-700: #7f1d1d;
      --red-600: #991b1b;
      --red-500: #b91c1c;
      --gray-100: #1f2937;
      --gray-200: #374151;
      --gray-600: #d1d5db;
      --gray-700: #e5e7eb;
      --gray-800: #f3f4f6;
      --white: #111827;
      --text: #f3f4f6;
      --muted: #9ca3af;
    }

    html.dark body { background: #111827; color: var(--text); }
    html.dark .top-bar,
    html.dark .quick-links,
    html.dark .suggestion-box,
    html.dark .announcement-card,
    html.dark .suggestion-card,
    html.dark .empty-state { background: #1f2937; color: var(--text); }
    html.dark .overlay-menu-panel { background: #1f2937; color: var(--text); border: 1px solid #374151; }
    html.dark .overlay-menu-panel a { color: var(--text); }
    html.dark .overlay-menu-panel a:hover { background: #374151; }
    html.dark .overlay-menu-panel a.active { background: rgba(220, 38, 38, 0.2); color: #fca5a5; }
    html.dark .overlay-menu-panel hr { border-color: #374151; }
    html.dark .suggestion-form textarea { background: #374151; color: var(--text); border-color: #4b5563; }
    html.dark .section-divider { background: linear-gradient(90deg, rgba(220, 38, 38, 0.6) 0%, transparent 100%); }

    /* Dark-mode global adjustments to improve contrast */
    html.dark .main-content, html.dark .main-content * { color: var(--text); }
    html.dark .quick-links,
    html.dark .suggestion-box,
    html.dark .stat-card,
    html.dark .announcement-card,
    html.dark .suggestion-card,
    html.dark .empty-state,
    html.dark .section-header,
    html.dark .hero-banner { background: #1f2937; border-color: #374151; color: var(--text); }
    html.dark .link-btn { background: #111827; color: var(--text); border-color: #374151; }
    html.dark input, html.dark textarea, html.dark select, html.dark .comment-input input, html.dark #post-input { background: #374151; color: var(--text); border: 1px solid #4b5563; }
    html.dark .post-actions .post-btn { background: #b91c1c; }

    /* Force-override any inline white backgrounds in dark mode for better readability */
    html.dark [style*="background: white"] {
      background: #0f1724 !important;
      color: var(--text) !important;
      border-color: #374151 !important;
      box-shadow: none !important;
    }
    html.dark [style*="background:white"] {
      background: #0f1724 !important;
      color: var(--text) !important;
      border-color: #374151 !important;
      box-shadow: none !important;
    }
    html.dark [style*="background: var(--white)"] {
      background: #0f1724 !important;
      color: var(--text) !important;
      border-color: #374151 !important;
      box-shadow: none !important;
    }
    html.dark .overlay-menu-panel { background: #0b1220 !important; color: var(--text) !important; border-color: #374151 !important; }
    html.dark .link-btn { background: #0b1220 !important; color: var(--text) !important; border-color: #374151 !important; }
    html.dark input, html.dark textarea, html.dark select, html.dark .comment-input input, html.dark #post-input { background: #0b1220 !important; color: var(--text) !important; border: 1px solid #374151 !important; }
    html.dark .empty-state { background: transparent !important; color: var(--muted) !important; }
    html.dark #post-input::placeholder { color: var(--muted) !important; opacity: 0.9 !important; }

    body { margin: 0; background: var(--gray-100); color: var(--text); }

    /* Layout: Sidebar + Main */
    .wrapper { display: flex; min-height: 100vh; }
    
    .sidebar {
      width: 280px;
      background: linear-gradient(180deg, var(--red-700) 0%, var(--red-600) 100%);
      color: var(--white);
      padding: 24px 20px;
      overflow-y: auto;
      position: fixed;
      height: 100vh;
      left: 0;
      top: 0;
      z-index: 50;
    }

    /* Hide desktop sidebar - replaced by top-right menu */
    .sidebar { display: none; }

    .sidebar-logo {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 32px;
      padding-bottom: 20px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .sidebar-logo img {
      width: 48px;
      height: 48px;
      background: white;
      padding: 4px;
      border-radius: 8px;
      object-fit: contain;
    }

    .sidebar-logo h2 { margin: 0; font-size: 18px; font-weight: 700; }
    .sidebar-logo small { color: rgba(255, 255, 255, 0.8); font-size: 11px; }

    .sidebar-nav { display: flex; flex-direction: column; gap: 8px; }
    .sidebar-nav h4 { font-size: 11px; opacity: 0.7; margin: 16px 0 8px; text-transform: uppercase; letter-spacing: 0.5px; }
    .sidebar-nav a {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      color: rgba(255, 255, 255, 0.9);
      text-decoration: none;
      border-radius: 8px;
      transition: all 0.2s;
      cursor: pointer;
    }
    .sidebar-nav a:hover { background: rgba(255, 255, 255, 0.15); transform: translateX(4px); }
    .sidebar-nav a.active { background: rgba(255, 255, 255, 0.2); color: white; font-weight: 600; }

    .main-content {
      margin-left: 0;
      flex: 1;
      padding: 24px;
      background: var(--gray-100);
    }

    /* Top-right menu button */
    #menu-btn {
      background: none;
      border: 1px solid var(--gray-200);
      padding: 8px 12px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 700;
      font-size: 16px;
      color: var(--muted);
      margin-right: 8px;
    }

    /* Overlay menu */
    #overlay-menu {
      position: fixed;
      top: 72px;
      left: 0;
      right: 0;
      bottom: 0;
      background: transparent;
      display: none;
      z-index: 60;
    }

    #overlay-menu.active { display: block; }

    #notifications-overlay {
      position: fixed;
      top: 72px;
      left: 0;
      right: 0;
      bottom: 0;
      background: transparent;
      display: none;
      z-index: 60;
    }

    #notifications-overlay.active { display: block; }

    .overlay-menu-panel {
      background: white;
      width: 320px;
      max-width: calc(100% - 32px);
      border-radius: 10px;
      padding: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.15);
      position: absolute;
      right: 16px;
      top: 8px;
    }

    /* no blur when menu opens (intentionally disabled) */

    .overlay-menu-panel a {
      display: flex;
      gap: 12px;
      align-items: center;
      padding: 12px 12px;
      text-decoration: none;
      color: var(--gray-800);
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      justify-content: flex-start;
    }

    .overlay-menu-panel a:hover {
      background: var(--gray-100);
    }

    .overlay-menu-panel a.active { background: rgba(237, 100, 100, 0.08); color: var(--red-600); }

    /* Header */
    .top-bar {
      display: flex;
      justify-content: flex-start;
      align-items: center;
      margin-bottom: 28px;
      background: var(--white);
      padding: 16px 24px;
      padding-right: 220px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      position: sticky;
      top: 0;
      z-index: 70;
    }

    .top-bar h1 { margin: 0; font-size: 24px; font-weight: 700; color: var(--gray-800); }

    .user-info {
      display: flex;
      align-items: center;
      gap: 14px;
      position: absolute;
      right: 18px;
      top: 50%;
      transform: translateY(-50%);
      z-index: 20;
    }

    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--red-700), var(--red-600));
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 20px;
    }

    .user-details { text-align: right; }
    .user-details .name { font-size: 14px; font-weight: 600; color: var(--gray-800); }
    .user-details .role { font-size: 12px; color: var(--muted); }

    /* Hero Banner */
    .hero-banner {
      background: linear-gradient(135deg, var(--red-700) 0%, var(--red-600) 100%);
      color: white;
      padding: 32px 28px;
      border-radius: 14px;
      margin-bottom: 28px;
      box-shadow: 0 8px 20px rgba(185, 28, 28, 0.15);
    }

    .hero-banner h1 { margin: 0 0 8px; font-size: 32px; font-weight: 700; }
    .hero-banner p { margin: 0; opacity: 0.95; font-size: 16px; }

    /* Grid: Stats & Quick Links */
    .grid-2 {
      display: grid;
      grid-template-columns: 280px 1fr 320px;
      gap: 24px;
      margin-bottom: 28px;
    }

    .stat-card {
      background: var(--white);
      padding: 20px;
      border-radius: 12px;
      border-top: 4px solid var(--red-600);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      text-align: center;
    }

    .stat-card .number { font-size: 32px; font-weight: 700; color: var(--red-600); margin: 8px 0; }
    .stat-card .label { font-size: 13px; color: var(--muted); }

    /* Quick Links */
    .quick-links {
      background: var(--white);
      padding: 24px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .quick-links h3 { margin: 0 0 16px; font-size: 16px; font-weight: 700; color: var(--gray-800); }

    .links-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
    }

    .link-btn {
      background: var(--gray-100);
      border: 1px solid var(--gray-200);
      padding: 14px;
      border-radius: 8px;
      cursor: pointer;
      text-align: center;
      text-decoration: none;
      color: var(--gray-800);
      font-size: 13px;
      font-weight: 600;
      transition: all 0.2s;
      font-family: inherit;
    }

    .link-btn:hover {
      background: var(--red-600);
      color: white;
      border-color: var(--red-600);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
    }

    .link-btn .icon { display: block; font-size: 24px; margin-bottom: 6px; }

    /* Announcements Section */
    .section {
      margin-bottom: 28px;
      display: none;
    }

    .section.active {
      display: block;
    }

    /* Two-column grid for side-by-side sections */
    .section-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
    }

    .section-grid .section {
      margin-bottom: 0;
    }

    .section-grid.active .section {
      display: block;
    }

    @media (max-width: 768px) {
      .section-grid {
        grid-template-columns: 1fr;
      }
    }

    /* Center Settings section */
    #settings {
      max-width: 700px;
      margin-left: auto;
      margin-right: auto;
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
    }

    .section h2 {
      margin: 0;
      font-size: 20px;
      font-weight: 700;
      color: var(--gray-800);
    }

    .section-divider {
      height: 2px;
      background: linear-gradient(90deg, var(--red-600) 0%, transparent 100%);
      margin-bottom: 16px;
    }

    .feed {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .announcement-card {
      background: var(--white);
      padding: 20px;
      border-radius: 12px;
      border-left: 4px solid var(--red-600);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      transition: all 0.2s;
    }

    .announcement-card:hover {
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
    }

    .announcement-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 12px;
    }

    .announcement-title {
      font-size: 16px;
      font-weight: 700;
      color: var(--gray-800);
      margin: 0 0 4px;
    }

    .announcement-meta {
      font-size: 12px;
      color: var(--muted);
    }

    .announcement-content {
      margin: 12px 0;
      line-height: 1.6;
      color: var(--gray-700);
    }

    .announcement-actions {
      display: flex;
      gap: 18px;
      margin-top: 14px;
      padding-top: 14px;
      border-top: 1px solid var(--gray-200);
      flex-wrap: wrap;
    }

    .action-btn {
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      font-size: 13px;
      padding: 0;
      display: flex;
      align-items: center;
      gap: 6px;
      font-weight: 500;
      transition: all 0.2s;
      font-family: inherit;
    }

    .action-btn:hover { color: var(--red-600); }
    .action-btn.liked, .action-btn.saved { color: var(--red-600); font-weight: 600; }

    /* Comments Section */
    .comments-section {
      margin-top: 14px;
      padding-top: 14px;
      border-top: 1px solid var(--gray-200);
      display: none;
    }

    .comment-input {
      display: flex;
      gap: 8px;
      margin-bottom: 12px;
    }

    .comment-input input {
      flex: 1;
      padding: 8px 12px;
      border: 1px solid var(--gray-200);
      border-radius: 6px;
      font-family: inherit;
      font-size: 13px;
    }

    .comment-input button {
      padding: 8px 14px;
      background: var(--red-600);
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      font-size: 13px;
      transition: all 0.2s;
      font-family: inherit;
    }

    .comment-input button:hover { background: var(--red-700); }

    .comment {
      padding: 10px;
      background: var(--gray-100);
      border-radius: 6px;
      margin-bottom: 8px;
      font-size: 13px;
    }

    .comment-author { font-weight: 600; color: var(--gray-800); }
    .comment-text { margin: 4px 0; color: var(--gray-700); }
    .comment-time { font-size: 11px; color: var(--muted); }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: var(--muted);
      background: var(--white);
      border-radius: 12px;
      font-size: 14px;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .wrapper { flex-direction: column; }
      .sidebar { width: 100%; height: auto; position: relative; }
      .main-content { margin-left: 0; padding: 16px; }
      .grid-2 { grid-template-columns: 1fr; }
      .top-bar { flex-direction: column; align-items: flex-start; gap: 12px; padding-right: 16px; }
      .user-info { position: static; transform: none; right: auto; top: auto; margin-left: auto; }
      .hero-banner { padding: 20px; }
      .hero-banner h1 { font-size: 24px; }
    }

    /* Suggestion Box Styles */
    .suggestion-box {
      background: white;
      padding: 20px;
      border-radius: 12px;
      border: 2px dashed var(--red-600);
      box-shadow: 0 2px 8px rgba(220, 38, 38, 0.1);
      margin-bottom: 28px;
    }

    .suggestion-box h3 { margin: 0 0 12px; color: var(--gray-800); }

    .suggestion-form {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .suggestion-form textarea {
      padding: 12px;
      border: 1px solid var(--gray-200);
      border-radius: 8px;
      font-family: inherit;
      font-size: 14px;
      resize: vertical;
      min-height: 100px;
    }

    .suggestion-form button {
      padding: 10px 16px;
      background: var(--red-600);
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.2s;
    }

    .suggestion-form button:hover { background: var(--red-700); }

    /* Post box (FB-like) */
    #post-input {
      padding: 12px 16px;
      border-radius: 12px;
      background: var(--white);
      border: 1px solid var(--gray-200);
      min-height: 56px;
      outline: none;
      font-family: inherit;
      font-size: 15px;
      box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    #post-input::placeholder { color: var(--gray-700); opacity: 0.95; }
    .icon-btn {
      background: transparent;
      border: none;
      cursor: pointer;
      font-size: 18px;
      padding: 6px;
      border-radius: 8px;
    }
    .icon-btn:hover { background: rgba(0,0,0,0.04); }
    .post-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
    .post-actions .post-btn { padding: 8px 16px; background: var(--red-600); color: white; border: none; border-radius: 20px; cursor: pointer; font-weight:600; }

    .suggestion-card {
      background: white;
      padding: 18px;
      border-radius: 12px;
      border-left: 4px solid var(--red-500);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      margin-bottom: 12px;
      transition: all 0.2s;
    }

    .suggestion-card:hover {
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
    }

    .suggestion-card .title {
      font-size: 14px;
      font-weight: 700;
      color: var(--gray-800);
      margin: 0 0 6px;
    }

    .suggestion-card .meta {
      font-size: 12px;
      color: var(--muted);
      margin-bottom: 10px;
    }

    .suggestion-card .content {
      font-size: 14px;
      color: var(--gray-700);
      line-height: 1.5;
      margin-bottom: 10px;
    }

    .suggestion-stats {
      display: flex;
      gap: 16px;
      font-size: 12px;
      color: var(--muted);
      padding-top: 10px;
      border-top: 1px solid var(--gray-200);
    }

    .suggestion-stats span {
      display: flex;
      align-items: center;
      gap: 4px;
    }
  </style>
</head>
<body>

  <div class="wrapper">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-logo">
        <img src="images/logo.webp" alt="Logo">
        <div>
          <h2>PCMP</h2>
          <small>Citizen Portal</small>
        </div>
      </div>

      <nav class="sidebar-nav">
        <h4 data-i18n="main">Main</h4>
        <a class="active" onclick="scrollToSection('dashboard'); return false;">
          <span style="font-size: 18px;">⌂</span>
          <span data-i18n="dashboard">Dashboard</span>
        </a>
        <a onclick="scrollToSection('announcements'); return false;">
          <span style="font-size: 18px;">↓</span>
          <span data-i18n="announcements">Announcements</span>
        </a>
        <a onclick="scrollToSection('consultations'); return false;">
          <span style="font-size: 18px;">≡</span>
          <span data-i18n="consultations">Consultations</span>
        </a>
        <a onclick="scrollToSection('submissions'); return false;">
          <span style="font-size: 18px;">◈</span>
          <span data-i18n="mySubmissions">My Submissions</span>
        </a>

        <h4 data-i18n="resources">Resources</h4>
        <a onclick="scrollToSection('documents'); return false;">
          <span style="font-size: 18px;">▦</span>
          <span data-i18n="documents">Documents</span>
        </a>
        <a onclick="alert(t('helpComingSoon')); return false;">
          <span style="font-size: 18px;">?</span>
          <span data-i18n="helpFaq">Help & FAQ</span>
        </a>

        <h4 data-i18n="account">Account</h4>
        <a onclick="alert(t('profileComingSoon')); return false;">
          <span style="font-size: 18px;">⊙</span>
          <span data-i18n="profile">Profile</span>
        </a>
        <a onclick="alert(t('settingsComingSoon')); return false;">
          <span style="font-size: 18px;">⚙</span>
          <span data-i18n="settings">Settings</span>
        </a>
      </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
      <!-- Top Bar -->
      <div class="top-bar">
        <img src="images/logo.webp" alt="Valenzuela Logo" style="width:44px;height:44px;border-radius:8px;margin-right:12px;object-fit:contain;">
        <h1 style="margin:0;">Welcome Back</h1>
        <div class="user-info">
          <button onclick="toggleLanguage()" style="background: none; border: 1px solid var(--gray-200); padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 12px; color: var(--muted); margin-right: 12px; font-family: inherit; transition: all 0.2s;" id="language-btn">EN</button>
          <button onclick="openNotifications()" style="background: none; border: 1px solid var(--gray-200); padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 14px; color: var(--muted); margin-right: 12px; font-family: inherit; transition: all 0.2s; position: relative;" id="notification-btn" title="Notifications">✦ Notify</button>
          <button onclick="toggleTheme()" style="background: none; border: 1px solid var(--gray-200); padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 14px; color: var(--muted); margin-right: 12px; font-family: inherit; transition: all 0.2s;" id="theme-btn" title="Toggle dark mode"><i class="dark-mode-icon">★</i><i class="light-mode-icon" style="display:none;">☀</i></button>
          <button id="menu-btn" onclick="toggleMenu()" aria-label="Open menu" aria-expanded="false">☰</button>
        </div>
      </div>

      <!-- Overlay Menu for Top-right button -->
      <div id="overlay-menu" onclick="if(event.target.id === 'overlay-menu') toggleMenu()">
        <div class="overlay-menu-panel" role="dialog" aria-modal="true">
          <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px; padding-bottom:12px; border-bottom: 1px solid var(--gray-200);">
            <div class="user-avatar" style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;background: linear-gradient(135deg, var(--red-600) 0%, var(--red-700) 100%); border-radius: 50%; color: white;">U</div>
            <div style="flex:1; min-width:0;">
              <div style="font-weight:700; font-size:14px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo htmlspecialchars($fullname); ?></div>
              <div style="font-size:12px;color:var(--muted)">Resident</div>
            </div>
          </div>
          <nav style="display:flex; flex-direction:column; gap:4px;">
            <a class="menu-link" href="user-portal.php?section=dashboard" data-section="dashboard">⌂ Dashboard</a>
            <a class="menu-link" href="user-portal.php?section=announcements" data-section="announcements">↓ Announcements</a>
            <a class="menu-link" href="user-portal.php?section=feedback" data-section="feedback">≣ Feedback</a>
            <a class="menu-link" href="user-portal.php?section=audit-log" data-section="audit-log">◊ Activity Log</a>
            <hr />
            <a class="menu-link" href="user-portal.php?section=settings" data-section="settings">⚙ Settings</a>
            <hr />
            <a class="menu-link" onclick="window.location.href='logout.php';" style="color: var(--red-600); font-weight: 600;">✕ Logout</a>
          </nav>
        </div>
      </div>

      <!-- Dashboard Section -->
      <?php if ($section === 'dashboard'): ?>
      <div id="dashboard" class="section active" style="background: linear-gradient(135deg, rgba(220, 38, 38, 0.05) 0%, rgba(239, 68, 68, 0.05) 100%); padding: 28px; border-radius: 14px; border: 2px solid var(--red-300, #fecaca); margin: -24px -24px 28px -24px;">
        <!-- Hero Banner -->
        <div class="hero-banner">
          <h1 id="dashboard-title" data-i18n="yourDashboard">◈ Your Dashboard</h1>
          <p id="dashboard-desc" data-i18n="welcome">Welcome! Track consultations, view announcements, and make your voice heard in city governance.</p>
        </div>

        <!-- 3-Column Layout: Sidebar | Feed | Announcements -->
        <div class="grid-2">
          <!-- LEFT: Quick Actions Sidebar -->
          <div class="quick-links">
            <h3>Quick Actions</h3>
            <div class="links-grid">
              <a href="user-portal.php?section=saved" class="link-btn" style="text-decoration: none; color: inherit;">
                <div class="icon">♥</div>
                Saved Items
              </a>
              <a href="user-portal.php?section=audit-log" class="link-btn" style="text-decoration: none; color: inherit;">
                <div class="icon">◈</div>
                My Activity
              </a>
              <a href="user-portal.php?section=documents" class="link-btn" style="text-decoration: none; color: inherit;">
                <div class="icon">◊</div>
                Citizen Charter
              </a>
            </div>
          </div>

          <!-- CENTER: Citizen Concerns Feed -->
          <div id="feed" style="margin: 0; padding: 0; background: transparent; box-shadow: none;">
            <!-- Facebook-style Post Box -->
            <div class="suggestion-box" style="margin-bottom: 20px; padding: 18px 20px;">
              <div style="display:flex; gap:12px; align-items:flex-start;">
                <div style="width:48px; height:48px; background: linear-gradient(135deg, var(--red-600) 0%, var(--red-700) 100%); border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-weight:700; flex-shrink:0; font-size:18px;">
                  <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                </div>
                <div style="flex:1;">
                  <textarea id="post-input" placeholder="<?php echo htmlspecialchars("What's on your mind, " . (explode(' ', trim($fullname))[0] ?: $fullname) . '?'); ?>" style="width:100%; min-height:56px; resize:none; padding:12px 16px; outline:none; font-family:inherit; font-size:15px;"></textarea>
                  <div class="post-actions">
                    <div style="display:flex; gap:8px;">
                      <button class="icon-btn" title="Video">◊</button>
                      <button class="icon-btn" title="Photo">◬</button>
                      <button class="icon-btn" title="Feeling">◐</button>
                    </div>
                    <div>
                      <button onclick="resetPostForm()" style="padding:8px 12px; background:var(--gray-200); color:var(--text); border:none; border-radius:16px; cursor:pointer; margin-right:8px;">Cancel</button>
                      <button class="post-btn" onclick="postSuggestion()">Post</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Feed (Scrollable) -->
            <div id="suggestions-feed" style="max-height: 600px; overflow-y: auto; padding-right: 8px;">
              <div class="empty-state">No posts yet. Be the first to share!</div>
            </div>
          </div>

          <!-- RIGHT: Announcements Sidebar -->
          <div id="announcements" style="margin: 0; padding: 0; background: transparent; box-shadow: none;">
            <div class="section-header">
              <h2 style="font-size: 18px; cursor: pointer;" onclick="window.location.href='user-portal.php?section=announcements'">↓ Announcements</h2>
            </div>
            <div class="section-divider"></div>
            <div id="announcements-feed" class="feed" style="max-height: 600px; overflow-y: auto;">
              <?php
                $latestAnns = getLatestAnnouncements(10);
                if (empty($latestAnns)) {
                    echo '<div class="empty-state">No announcements at the moment.</div>';
                } else {
                    foreach ($latestAnns as $ann) {
                        echo "<div class=\"announcement-card\" style=\"background:white;padding:12px;border-radius:8px;margin-bottom:10px;box-shadow:0 1px 4px rgba(0,0,0,0.04); cursor: pointer;\" onclick=\"window.location.href='user-portal.php?section=announcements'\">";
                        
                        // Display image if available
                        if (!empty($ann['image_path']) && file_exists($ann['image_path'])) {
                            echo '<div style="margin-bottom:8px; border-radius:6px; overflow:hidden;"><img src="' . htmlspecialchars($ann['image_path']) . '" alt="' . htmlspecialchars($ann['title']) . '" style="width:100%; max-height:200px; object-fit:cover; display:block;"></div>';
                        }
                        
                        echo '<div style="font-weight:700;margin-bottom:6px">' . htmlspecialchars($ann['title']) . '</div>';
                        echo '<div style="color:#6b7280;font-size:13px;margin-bottom:8px">' . nl2br(htmlspecialchars(substr($ann['content'],0,400))) . '</div>';
                        echo '<div style="font-size:12px;color:#9ca3af">' . date('M d, Y H:i', strtotime($ann['created_at'])) . '</div>';
                        echo '</div>';
                    }
                }
              ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Announcements Page -->
      <?php if ($section === 'announcements'): ?>
      <div class="section-header" style="margin-bottom: 20px;">
        <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: var(--gray-800);">↓ Announcements</h1>
        <p style="margin: 8px 0 0; color: var(--muted); font-size: 15px;">Stay updated with the latest announcements from the City of Valenzuela</p>
      </div>
      <div class="section-divider"></div>
      <div id="full-announcements-feed" class="feed" style="margin-top: 24px;">
        <?php
          $allAnns = getLatestAnnouncements(100);
          if (empty($allAnns)) {
              echo '<div class="empty-state">No announcements at the moment. Check back soon!</div>';
          } else {
              foreach ($allAnns as $ann) {
                  $ann_id = $ann['id'] ?? 'ann_' . time() . rand(1000, 9999);
                  $allow_comments = $ann['allow_comments'] ?? true;
                  echo "<div class=\"announcement-card\" data-ann-id=\"$ann_id\">";
                  
                  // Display image if available
                  if (!empty($ann['image_path']) && file_exists($ann['image_path'])) {
                      echo '<div style="margin-bottom:12px; border-radius:8px; overflow:hidden;"><img src="' . htmlspecialchars($ann['image_path']) . '" alt="' . htmlspecialchars($ann['title']) . '" style="width:100%; max-height:300px; object-fit:cover; display:block;"></div>';
                  }
                  
                  echo '<div class="announcement-header">';
                  echo '<div>';
                  echo '<h3 class="announcement-title">' . htmlspecialchars($ann['title']) . '</h3>';
                  echo '<p class="announcement-meta">' . date('F d, Y • H:i', strtotime($ann['created_at'])) . '</p>';
                  echo '</div>';
                  echo '</div>';
                  echo '<div class="announcement-content">' . nl2br(htmlspecialchars($ann['content'])) . '</div>';
                  echo '<div class="announcement-actions">';
                  echo '<button class="action-btn like-btn" data-ann-id="' . $ann_id . '" onclick="toggleLikeAnnouncement(this, \'' . $ann_id . '\')"><i style="font-size:16px;">▲</i> <span class="like-count">0</span> Like</button>';
                  
                  if ($allow_comments) {
                    echo '<button class="action-btn" onclick="toggleCommentForm(\'ann_' . $ann_id . '\')"><i style="font-size:16px;">≡</i> Comment</button>';
                  } else {
                    echo '<button class="action-btn" style="opacity: 0.5; cursor: not-allowed;" title="Comments disabled"><i style="font-size:16px;">✗</i> Comments Off</button>';
                  }
                  
                  echo '<button class="action-btn save-btn" data-ann-id="' . $ann_id . '" onclick="toggleSaveAnnouncement(this, \'' . $ann_id . '\')"><i style="font-size:16px;">♥</i> Save</button>';
                  echo '</div>';
                  
                  // Comments section (only if comments allowed)
                  if ($allow_comments) {
                    echo '<div id="ann_' . $ann_id . '-comments" style="display: none; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--gray-200);">';
                    echo '<div style="display: flex; gap: 8px; margin-bottom: 12px;">';
                    echo '<input type="text" id="comment-ann-' . $ann_id . '" placeholder="Add a comment..." style="flex: 1; padding: 8px 12px; border: 1px solid var(--gray-200); border-radius: 6px; font-family: inherit; font-size: 13px;">';
                    echo '<button onclick="addAnnouncementComment(\'' . $ann_id . '\')" style="padding: 8px 14px; background: var(--red-600); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.2s;">Post</button>';
                    echo '</div>';
                    echo '<div id="comments-list-ann-' . $ann_id . '" style="display: flex; flex-direction: column; gap: 8px;"></div>';
                    echo '</div>';
                  }
                  
                  echo "</div>";
              }
          }
        ?>
      </div>
      <?php endif; ?>

      <!-- Feedback Page -->
      <?php if ($section === 'feedback'): ?>
      <div class="section-header" style="margin-bottom: 20px;">
        <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: var(--gray-800);">≣ Feedback & Suggestions</h1>
        <p style="margin: 8px 0 0; color: var(--muted); font-size: 15px;">Share your feedback and suggestions to help us improve</p>
      </div>
      <div class="section-divider"></div>
      
      <!-- Feedback Submission Form -->
      <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 28px;">
        <h3 style="margin: 0 0 16px; font-size: 18px; font-weight: 700; color: var(--gray-800);">Submit Your Feedback</h3>
        <form id="feedback-form" style="display: flex; flex-direction: column; gap: 16px;">
          <div>
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--gray-800);">Category</label>
            <select name="category" required style="width: 100%; padding: 10px; border: 1px solid var(--gray-200); border-radius: 8px; font-family: inherit;">
              <option value="">Select a category...</option>
              <option value="suggestion">Suggestion</option>
              <option value="complaint">Complaint</option>
              <option value="compliment">Compliment</option>
              <option value="request">Service Request</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div>
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--gray-800);">Rating (Optional)</label>
            <div style="display: flex; gap: 8px;">
              <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                <input type="radio" name="rating" value="5"> ⭐⭐⭐⭐⭐ Excellent
              </label>
              <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                <input type="radio" name="rating" value="4"> ⭐⭐⭐⭐ Good
              </label>
              <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                <input type="radio" name="rating" value="3"> ⭐⭐⭐ Average
              </label>
            </div>
          </div>
          <div>
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--gray-800);">Your Feedback</label>
            <textarea name="message" placeholder="Tell us what you think..." required style="width: 100%; min-height: 120px; padding: 12px; border: 1px solid var(--gray-200); border-radius: 8px; font-family: inherit; resize: vertical;"></textarea>
          </div>
          <button type="submit" style="padding: 12px 24px; background: var(--red-600); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 15px; transition: all 0.2s;">Submit Feedback</button>
        </form>
      </div>

      <!-- Your Feedback History -->
      <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
        <h3 style="margin: 0 0 16px; font-size: 18px; font-weight: 700; color: var(--gray-800);">Your Feedback History</h3>
        <div id="feedback-history" style="display: flex; flex-direction: column; gap: 12px;">
          <div class="empty-state" style="background: transparent; padding: 40px 20px;">No feedback submitted yet. Start sharing your thoughts!</div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Documents Page -->
      <?php if ($section === 'documents'): ?>
      <div class="section-header" style="margin-bottom: 20px;">
        <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: var(--gray-800);">▦ Important Documents</h1>
        <p style="margin: 8px 0 0; color: var(--muted); font-size: 15px;">Access important documents and resources</p>
      </div>
      <div class="section-divider"></div>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 24px;">
        <!-- Valenzuela Citizen Charter -->
        <div style="background: white; padding: 24px; border-radius: 12px; border-left: 4px solid var(--red-600); box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all 0.2s;">
          <div style="font-size: 40px; margin-bottom: 12px;">◊</div>
          <h3 style="margin: 0 0 12px; font-size: 18px; font-weight: 700; color: var(--gray-800);">Valenzuela Citizen Charter</h3>
          <p style="margin: 12px 0; font-size: 14px; color: var(--muted); line-height: 1.6;">
            A comprehensive charter establishing the rights, responsibilities, and commitments of the City Government towards its citizens.
          </p>
          <div style="display: flex; gap: 8px; margin-top: 16px;">
            <button onclick="viewDocument('valenzuela-citizen-charter.html', 'Valenzuela Citizen Charter')" style="flex: 1; padding: 10px 12px; background: var(--red-600); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s;">View</button>
            <button onclick="downloadDocument('valenzuela-citizen-charter.html')" style="flex: 1; padding: 10px 12px; background: var(--gray-200); color: var(--gray-800); border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s;">📥 Download</button>
          </div>
        </div>

        <!-- Placeholder: City Ordinance -->
        <div style="background: white; padding: 24px; border-radius: 12px; border-left: 4px solid var(--gray-300); box-shadow: 0 2px 8px rgba(0,0,0,0.06); opacity: 0.6;">
          <div style="font-size: 40px; margin-bottom: 12px;">※</div>
          <h3 style="margin: 0 0 12px; font-size: 18px; font-weight: 700; color: var(--gray-800);">City Ordinances</h3>
          <p style="margin: 12px 0; font-size: 14px; color: var(--muted); line-height: 1.6;">
            Local laws and regulations governing city operations.
          </p>
          <button onclick="alert('Coming soon')" style="width: 100%; padding: 10px 12px; background: var(--gray-300); color: var(--muted); border: none; border-radius: 6px; cursor: not-allowed; font-weight: 600; font-size: 14px; margin-top: 16px;">Coming Soon</button>
        </div>

        <!-- Placeholder: Budget & Finance -->
        <div style="background: white; padding: 24px; border-radius: 12px; border-left: 4px solid var(--gray-300); box-shadow: 0 2px 8px rgba(0,0,0,0.06); opacity: 0.6;">
          <div style="font-size: 40px; margin-bottom: 12px;">◄</div>
          <h3 style="margin: 0 0 12px; font-size: 18px; font-weight: 700; color: var(--gray-800);">Annual Budget Report</h3>
          <p style="margin: 12px 0; font-size: 14px; color: var(--muted); line-height: 1.6;">
            Transparent information on city government spending and financial plans.
          </p>
          <button onclick="alert('Coming soon')" style="width: 100%; padding: 10px 12px; background: var(--gray-300); color: var(--muted); border: none; border-radius: 6px; cursor: not-allowed; font-weight: 600; font-size: 14px; margin-top: 16px;">Coming Soon</button>
        </div>
      </div>
      <?php endif; ?>

      <!-- Activity Log Page -->
      <?php if ($section === 'audit-log'): ?>
      <div class="section-header" style="margin-bottom: 20px;">
        <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: var(--gray-800);">◊ Your Activity Log</h1>
        <p style="margin: 8px 0 0; color: var(--muted); font-size: 15px;">View your interaction history with the portal</p>
      </div>
      <div class="section-divider"></div>
      
      <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
        <div id="activity-log-container" style="display: flex; flex-direction: column; gap: 12px;">
          <?php
            if ($user_id) {
              $userLogs = getUserLogs(50, 0, ['user_id' => $user_id]);
              if (empty($userLogs)) {
                echo '<div class="empty-state" style="background: transparent;">No activity recorded yet.</div>';
              } else {
                foreach ($userLogs as $log) {
                  echo '<div style="padding: 14px; background: var(--gray-100); border-radius: 8px; border-left: 3px solid var(--red-600);">';
                  echo '<div style="font-weight: 600; color: var(--gray-800); margin-bottom: 4px;">' . htmlspecialchars($log['action'] ?? '') . '</div>';
                  echo '<div style="font-size: 13px; color: var(--muted);">' . date('F d, Y • H:i', strtotime($log['timestamp'] ?? 'now')) . '</div>';
                  if (!empty($log['details'])) {
                    echo '<div style="font-size: 13px; color: var(--gray-700); margin-top: 6px;">' . htmlspecialchars($log['details']) . '</div>';
                  }
                  echo '</div>';
                }
              }
            } else {
              echo '<div class="empty-state" style="background: transparent;">Unable to load activity log.</div>';
            }
          ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Saved Items Page -->
      <?php if ($section === 'saved'): ?>
      <div class="section-header" style="margin-bottom: 20px;">
        <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: var(--gray-800);">♥ Saved Items</h1>
        <p style="margin: 8px 0 0; color: var(--muted); font-size: 15px;">View all your saved announcements and items</p>
      </div>
      <div class="section-divider"></div>
      
      <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
        <div id="saved-items-container" style="display: flex; flex-direction: column; gap: 16px;">
          <?php
            if ($user_id) {
              // Get all announcements
              $allAnns = getLatestAnnouncements(100);
              $hasSaved = false;
              
              foreach ($allAnns as $ann) {
                $saved_by = json_decode($ann['saved_by'] ?? '[]', true) ?? [];
                
                // Check if current user saved this announcement
                if (in_array($user_id, $saved_by)) {
                  $hasSaved = true;
                  echo '<div style="padding: 16px; background: var(--gray-100); border-radius: 8px; border-left: 4px solid var(--red-600);">';
                  
                  // Display image if available
                  if (!empty($ann['image_path']) && file_exists($ann['image_path'])) {
                    echo '<div style="margin-bottom: 12px; border-radius: 6px; overflow: hidden;"><img src="' . htmlspecialchars($ann['image_path']) . '" alt="' . htmlspecialchars($ann['title']) . '" style="width: 100%; max-height: 250px; object-fit: cover; display: block;"></div>';
                  }
                  
                  echo '<div style="display: flex; justify-content: space-between; align-items: start;">';
                  echo '<div style="flex: 1;">';
                  echo '<h3 style="margin: 0 0 6px; font-size: 18px; font-weight: 700; color: var(--gray-800);">' . htmlspecialchars($ann['title']) . '</h3>';
                  echo '<p style="margin: 0 0 10px; font-size: 13px; color: var(--muted);">' . date('F d, Y • H:i', strtotime($ann['created_at'])) . '</p>';
                  echo '<div style="font-size: 14px; color: var(--gray-700); line-height: 1.6;">' . nl2br(htmlspecialchars(substr($ann['content'], 0, 500))) . (strlen($ann['content']) > 500 ? '...' : '') . '</div>';
                  echo '</div>';
                  
                  echo '<button onclick="removeSavedItem(' . $ann['id'] . ', this)" style="padding: 8px 12px; background: var(--gray-200); color: var(--gray-800); border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; margin-left: 12px; white-space: nowrap;">Remove</button>';
                  echo '</div>';
                  echo '</div>';
                }
              }
              
              if (!$hasSaved) {
                echo '<div class="empty-state" style="background: transparent; padding: 40px 20px;">No saved items yet. Save announcements from the announcements page!</div>';
              }
            } else {
              echo '<div class="empty-state" style="background: transparent;">Unable to load saved items.</div>';
            }
          ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Settings Page -->
      <?php if ($section === 'settings'): ?>
      <div class="section-header" style="margin-bottom: 20px;">
        <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: var(--gray-800);">⚙ Settings</h1>
        <p style="margin: 8px 0 0; color: var(--muted); font-size: 15px;">Manage your account and preferences</p>
      </div>
      <div class="section-divider"></div>

      <!-- Settings Tabs -->
      <div style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden;">
        <!-- Tab Navigation -->
        <div style="display: flex; gap: 0; border-bottom: 1px solid var(--gray-200); overflow-x: auto;">
          <button onclick="switchSettingsTab('profile')" class="settings-tab active" data-tab="profile" style="padding: 14px 20px; border: none; background: none; cursor: pointer; font-weight: 600; color: var(--muted); border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap;">Profile</button>
          <button onclick="switchSettingsTab('preferences')" class="settings-tab" data-tab="preferences" style="padding: 14px 20px; border: none; background: none; cursor: pointer; font-weight: 600; color: var(--muted); border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap;">⚙ Preferences</button>
          <button onclick="switchSettingsTab('faq')" class="settings-tab" data-tab="faq" style="padding: 14px 20px; border: none; background: none; cursor: pointer; font-weight: 600; color: var(--muted); border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap;">ℹ FAQs</button>
          <button onclick="switchSettingsTab('privacy')" class="settings-tab" data-tab="privacy" style="padding: 14px 20px; border: none; background: none; cursor: pointer; font-weight: 600; color: var(--muted); border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap;">Privacy</button>
        </div>

        <!-- Tab Content -->
        <div style="padding: 24px;">
          <!-- Profile Tab -->
          <div id="profile-tab" class="settings-tab-content" style="display: block;">
            <h3 style="margin: 0 0 20px; font-size: 16px; font-weight: 700; color: var(--gray-800);">Profile Information</h3>
            
            <!-- Profile Photo -->
            <div style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--gray-200);">
              <label style="display: block; margin-bottom: 12px; font-size: 14px; font-weight: 600; color: var(--gray-800);">Profile Photo</label>
              <div style="display: flex; gap: 16px; align-items: center;">
                <div id="profile-avatar" style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--red-600) 0%, var(--red-700) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 36px; flex-shrink: 0;">U</div>
                <div style="flex: 1;">
                  <input type="file" accept="image/*" id="photo-upload" style="padding: 8px 12px; border: 1px solid var(--gray-200); border-radius: 8px; cursor: pointer; font-size: 14px;">
                  <button onclick="uploadProfilePhoto()" style="width: 100%; padding: 8px; background: var(--red-600); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; margin-top: 8px;">Upload Photo</button>
                </div>
              </div>
              <p style="margin: 8px 0 0; font-size: 12px; color: var(--muted);">Upload a profile photo (JPG, PNG, max 5MB)</p>
            </div>

            <!-- Full Name -->
            <div style="margin-bottom: 16px;">
              <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: var(--gray-800);">Full Name</label>
              <input type="text" id="profile-fullname" value="<?php echo htmlspecialchars($fullname); ?>" style="width: 100%; padding: 10px; border: 1px solid var(--gray-200); border-radius: 8px; font-family: inherit; font-size: 14px; color: var(--text); background: var(--white);">
            </div>

            <!-- Email -->
            <div style="margin-bottom: 16px;">
              <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: var(--gray-800);">Email Address</label>
              <input type="email" id="profile-email" placeholder="your@email.com" style="width: 100%; padding: 10px; border: 1px solid var(--gray-200); border-radius: 8px; font-family: inherit; font-size: 14px; color: var(--text); background: var(--white);">
            </div>

            <!-- Username -->
            <div style="margin-bottom: 24px;">
              <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: var(--gray-800);">Username</label>
              <input type="text" id="profile-username" placeholder="Choose a username" style="width: 100%; padding: 10px; border: 1px solid var(--gray-200); border-radius: 8px; font-family: inherit; font-size: 14px; color: var(--text); background: var(--white);">
            </div>

            <!-- Change Password -->
            <div style="margin-bottom: 16px;">
              <button onclick="togglePasswordChange()" style="width: 100%; padding: 10px; background: var(--red-600); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s; margin-bottom: 12px;">Change Password</button>
              
              <div id="password-change-form" style="display: none; background: var(--gray-100); padding: 16px; border-radius: 8px;">
                <div style="margin-bottom: 12px;">
                  <label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600;">Current Password</label>
                  <input type="password" id="current-password" placeholder="Enter current password" style="width: 100%; padding: 8px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 14px;">
                </div>
                <div style="margin-bottom: 12px;">
                  <label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600;">New Password</label>
                  <input type="password" id="new-password" placeholder="Enter new password" style="width: 100%; padding: 8px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 14px;">
                </div>
                <div style="margin-bottom: 12px;">
                  <label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600;">Confirm New Password</label>
                  <input type="password" id="confirm-password" placeholder="Confirm new password" style="width: 100%; padding: 8px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 14px;">
                </div>
                <button onclick="updatePassword()" style="width: 100%; padding: 8px; background: var(--red-600); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; margin-bottom: 8px;">Update Password</button>
                <button onclick="togglePasswordChange()" style="width: 100%; padding: 8px; background: var(--gray-200); color: var(--gray-800); border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px;">Cancel</button>
              </div>
            </div>

            <button onclick="saveProfileChanges()" style="width: 100%; padding: 10px; background: var(--red-600); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s;">Save Profile Changes</button>
          </div>

          <!-- Preferences Tab -->
          <div id="preferences-tab" class="settings-tab-content" style="display: none;">
            <h3 style="margin: 0 0 20px; font-size: 16px; font-weight: 700; color: var(--gray-800);">Preferences</h3>

            <!-- Language Setting -->
            <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--gray-200);">
              <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: var(--gray-800);">Language</label>
              <select onchange="setLanguage(this.value)" style="width: 100%; padding: 10px; border: 1px solid var(--gray-200); border-radius: 8px; font-family: inherit; font-size: 14px; background: var(--white); color: var(--text); cursor: pointer;">
                <option value="en">English</option>
                <option value="fil">Filipino</option>
              </select>
            </div>

            <!-- Theme Setting -->
            <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--gray-200);">
              <label style="display: block; margin-bottom: 12px; font-size: 14px; font-weight: 600; color: var(--gray-800);">Theme</label>
              <div style="display: flex; flex-direction: column; gap: 10px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;">
                  <input type="radio" name="theme" value="light" onchange="setTheme('light')" style="cursor: pointer;"> Light Mode
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;">
                  <input type="radio" name="theme" value="dark" onchange="setTheme('dark')" style="cursor: pointer;"> Dark Mode
                </label>
              </div>
              <p style="margin: 8px 0 0; font-size: 12px; color: var(--muted);">Customize your visual experience</p>
            </div>

            <!-- Notifications Setting -->
            <div style="margin-bottom: 20px;">
              <label style="display: block; margin-bottom: 12px; font-size: 14px; font-weight: 600; color: var(--gray-800);">Notifications</label>
              <div style="display: flex; flex-direction: column; gap: 10px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                  <input type="checkbox" id="email-notif" checked style="cursor: pointer; width: 18px; height: 18px;"> Email Notifications
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                  <input type="checkbox" id="announcement-notif" checked style="cursor: pointer; width: 18px; height: 18px;"> Announcement Updates
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                  <input type="checkbox" id="feedback-notif" checked style="cursor: pointer; width: 18px; height: 18px;"> Feedback Response Alerts
                </label>
              </div>
              <p style="margin: 12px 0 0; font-size: 12px; color: var(--muted);">Control how and when you receive updates</p>
            </div>

            <button onclick="savePreferences()" style="width: 100%; padding: 10px; background: var(--red-600); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s; margin-bottom: 12px;">Save Preferences</button>
            <button onclick="clearLocalStorage()" style="width: 100%; padding: 10px; background: var(--gray-200); color: var(--gray-800); border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s;">Clear Saved Data</button>
            <p style="margin: 8px 0 0; font-size: 12px; color: var(--muted);">Clear cached suggestions and preferences (theme/language preserved)</p>
          </div>

          <!-- FAQs Tab -->
          <div id="faq-tab" class="settings-tab-content" style="display: none;">
            <h3 style="margin: 0 0 20px; font-size: 16px; font-weight: 700; color: var(--gray-800);">Frequently Asked Questions</h3>
            
            <div style="display: flex; flex-direction: column; gap: 12px;">
              <div style="background: var(--gray-100); padding: 16px; border-radius: 8px; border-left: 4px solid var(--red-600);">
                <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 700; color: var(--gray-800); cursor: pointer;">What is the PCMP Portal?</h4>
                <p style="margin: 0; font-size: 13px; color: var(--gray-700); line-height: 1.6;">The Public Consultation and Management Portal (PCMP) is an online platform that allows residents of Valenzuela to participate in government consultations, provide feedback on city initiatives, and stay informed about important announcements.</p>
              </div>

              <div style="background: var(--gray-100); padding: 16px; border-radius: 8px; border-left: 4px solid var(--red-600);">
                <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 700; color: var(--gray-800); cursor: pointer;">How do I submit feedback?</h4>
                <p style="margin: 0; font-size: 13px; color: var(--gray-700); line-height: 1.6;">Navigate to the Feedback section in your dashboard. You can share suggestions, report concerns, or provide comments on ongoing consultations. Your feedback helps shape the city's future!</p>
              </div>

              <div style="background: var(--gray-100); padding: 16px; border-radius: 8px; border-left: 4px solid var(--red-600);">
                <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 700; color: var(--gray-800); cursor: pointer;">Can I save announcements for later?</h4>
                <p style="margin: 0; font-size: 13px; color: var(--gray-700); line-height: 1.6;">Yes! Click the bookmark icon on any announcement to save it. You can view all saved items in the Saved Items section of your Quick Actions.</p>
              </div>

              <div style="background: var(--gray-100); padding: 16px; border-radius: 8px; border-left: 4px solid var(--red-600);">
                <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 700; color: var(--gray-800); cursor: pointer;">How do I change my password?</h4>
                <p style="margin: 0; font-size: 13px; color: var(--gray-700); line-height: 1.6;">Go to the Profile tab in Settings and click "Change Password". Enter your current password and your new password. Make sure to create a strong password for security.</p>
              </div>

              <div style="background: var(--gray-100); padding: 16px; border-radius: 8px; border-left: 4px solid var(--red-600);">
                <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 700; color: var(--gray-800); cursor: pointer;">What information do you collect?</h4>
                <p style="margin: 0; font-size: 13px; color: var(--gray-700); line-height: 1.6;">We collect basic profile information (name, email, address) and your feedback/submissions. We do not share your data with third parties without your consent. See our Privacy Policy for details.</p>
              </div>

              <div style="background: var(--gray-100); padding: 16px; border-radius: 8px; border-left: 4px solid var(--red-600);">
                <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 700; color: var(--gray-800); cursor: pointer;">How can I contact support?</h4>
                <p style="margin: 0; font-size: 13px; color: var(--gray-700); line-height: 1.6;">For technical issues or questions, please email support@valenzuela.gov.ph or call the city hotline. Our team is available Monday-Friday, 8 AM - 5 PM.</p>
              </div>
            </div>
          </div>

          <!-- Privacy Tab -->
          <div id="privacy-tab" class="settings-tab-content" style="display: none;">
            <h3 style="margin: 0 0 20px; font-size: 16px; font-weight: 700; color: var(--gray-800);">Privacy Policy & Data Protection</h3>

            <div style="font-size: 13px; line-height: 1.8; color: var(--gray-700);">
              <h4 style="margin: 16px 0 8px; font-weight: 700;">Your Privacy Matters</h4>
              <p style="margin: 0 0 16px;">We are committed to protecting your personal data. This portal collects only information necessary to provide services and improve your experience.</p>

              <h4 style="margin: 16px 0 8px; font-weight: 700;">Data We Collect</h4>
              <ul style="margin: 0 0 16px; padding-left: 20px;">
                <li>Name, email, and contact information</li>
                <li>Residential address and barangay</li>
                <li>Feedback, suggestions, and submitted forms</li>
                <li>Activity logs (for security purposes)</li>
                <li>Browser and device information</li>
              </ul>

              <h4 style="margin: 16px 0 8px; font-weight: 700;">How We Protect Your Data</h4>
              <ul style="margin: 0 0 16px; padding-left: 20px;">
                <li>All data is encrypted using industry-standard protocols</li>
                <li>Access is restricted to authorized personnel only</li>
                <li>Regular security audits and updates</li>
                <li>Compliance with Data Privacy Act of 2012</li>
              </ul>

              <h4 style="margin: 16px 0 8px; font-weight: 700;">Your Rights</h4>
              <ul style="margin: 0 0 16px; padding-left: 20px;">
                <li>Access your personal data anytime</li>
                <li>Request corrections to your information</li>
                <li>Request deletion of your account and data</li>
                <li>Opt-out of marketing communications</li>
              </ul>

              <h4 style="margin: 16px 0 8px; font-weight: 700;">Contact Us</h4>
              <p style="margin: 0;">If you have privacy concerns, contact our Data Protection Officer at: <strong>dpo@valenzuela.gov.ph</strong></p>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Document Viewer Modal -->
  <div id="document-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 100; overflow-y: auto;">
    <div style="background: white; margin: 20px auto; border-radius: 12px; max-width: 900px; max-height: 90vh; overflow-y: auto; position: relative;">
      <div style="position: sticky; top: 0; background: white; padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; z-index: 10;">
        <h2 id="modal-title" style="margin: 0; font-size: 18px; font-weight: 700;">Document Title</h2>
        <button onclick="closeDocument()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--muted);">×</button>
      </div>
      <iframe id="document-viewer" style="width: 100%; height: calc(90vh - 60px); border: none;"></iframe>
    </div>
  </div>

  <script src="announcements-user.js"></script>
  <script>
    const CURRENT_CITIZEN = 'Citizen_' + Math.random().toString(36).substr(2, 9);

    // ========================================
    // Language & Translation System
    // ========================================
    const translations = {
      en: {
        welcomeBack: 'Welcome Back',
        resident: 'Resident',
        dashboard: 'Dashboard',
        announcements: 'Announcements',
        consultations: 'Consultations',
        mySubmissions: 'My Submissions',
        documents: 'Documents',
        helpFaq: 'Help & FAQ',
        profile: 'Profile',
        settings: 'Settings',
        main: 'Main',
        resources: 'Resources',
        account: 'Account',
        yourDashboard: 'Your Dashboard',
        welcome: 'Welcome! Track consultations, view announcements, and make your voice heard in city governance.',
        activeConsultations: 'Active Consultations',
        latestAnnouncements: 'Latest Announcements',
        quickActions: 'Quick Actions',
        viewUpdates: 'View Updates',
        postSuggestion: 'Post Suggestion',
        savedItems: 'Saved Items',
        myActivity: 'My Activity',
        latestAnnouncementsHeader: 'Latest Announcements',
        activeConsultationsHeader: 'Active Consultations',
        noActive: 'No active consultations at the moment. Check back later or subscribe to updates.',
        mySubmissionsHeader: 'My Submissions',
        noSubmitted: "You haven't submitted any feedback yet. Navigate to an active consultation to share your views.",
        importantDocuments: 'Important Documents',
        citizenCharter: 'Valenzuela Citizen Charter',
        citizenCharterDesc: 'A comprehensive charter establishing the rights, responsibilities, and commitments of the City Government towards its citizens.',
        view: 'View',
        download: 'Download',
        comingSoon: 'Coming Soon',
        citizenSuggestions: 'Citizen Suggestions & Ideas',
        shareIdeas: 'Share Your Ideas for Change',
        shareIdeasDesc: "Have a suggestion for improving our city? Share it here and help shape Valenzuela's future.",
        placeholder: 'What changes would you like to see in our city? (e.g., Better roads, More parks, Improved public safety, etc.)',
        postMySuggestion: 'Post My Suggestion',
        noSuggestions: 'No suggestions yet. Be the first to share your ideas!',
        supports: 'supports',
        comments: 'comments',
        support: 'Support',
        comment: 'Comment',
        post: 'Post',
        addComment: 'Add a comment...',
        suggestionPosted: '✅ Your suggestion has been posted! Thank you for your input.',
        pleaseWrite: 'Please write a suggestion before posting.',
        loading: 'Loading announcements...',
        documentTitle: 'Document Title',
        profileComingSoon: 'Profile coming soon',
        settingsComingSoon: 'Settings coming soon',
        documentsComingSoon: 'Documents coming soon',
        helpComingSoon: 'Help & FAQ coming soon',
        consultationFormComingSoon: 'Consultation form coming soon',
        savedItemsComingSoon: 'Saved items coming soon',
        activityComingSoon: 'History coming soon',
      },
      fil: {
        welcomeBack: 'Maligayang Pagbalik',
        resident: 'Mamamayan',
        dashboard: 'Dashboard',
        announcements: 'Mga Abiso',
        consultations: 'Mga Konsultasyon',
        mySubmissions: 'Ang Aking Mga Isinumite',
        documents: 'Mga Dokumento',
        helpFaq: 'Tulong & FAQ',
        profile: 'Profil',
        settings: 'Mga Setting',
        main: 'Pangunahin',
        resources: 'Mga Mapagkukunan',
        account: 'Akawnt',
        yourDashboard: 'Ang Iyong Dashboard',
        welcome: 'Maligayang pagbalik! Subaybayan ang mga konsultasyon, tingnan ang mga abiso, at gawin naririnig ang iyong tinig sa pamamhalaan ng lungsod.',
        activeConsultations: 'Aktibong Mga Konsultasyon',
        latestAnnouncements: 'Pinakabagong Mga Abiso',
        quickActions: 'Mabilis na Aksyon',
        viewUpdates: 'Tingnan ang Mga Update',
        postSuggestion: 'Magbigay ng Mungkahi',
        savedItems: 'Nakaligtas na Mga Item',
        myActivity: 'Ang Aking Aktibidad',
        latestAnnouncementsHeader: 'Pinakabagong Mga Abiso',
        activeConsultationsHeader: 'Aktibong Mga Konsultasyon',
        noActive: 'Walang aktibong konsultasyon sa ngayon. Bumalik mamaya o mag-subscribe sa mga update.',
        mySubmissionsHeader: 'Ang Aking Mga Isinumite',
        noSubmitted: 'Hindi ka pa nagsumite ng anumang feedback. Pumunta sa aktibong konsultasyon upang ibahagi ang iyong mga pananaw.',
        importantDocuments: 'Mahalagang Mga Dokumento',
        citizenCharter: 'Valenzuela Citizen Charter',
        citizenCharterDesc: 'Isang komprehensibong charter na nagtatatag ng mga karapatan, responsibilidad, at pangako ng Lungsod ng Pamamhalaan sa pamamagitan ng Valenzuela sa mga mamamayan nito.',
        view: 'Tingnan',
        download: 'I-download',
        comingSoon: 'Paparating na',
        citizenSuggestions: 'Mga Mungkahi at Ideya ng Mamamayan',
        shareIdeas: 'Ibahagi ang Iyong Mga Ideya para sa Pagbabago',
        shareIdeasDesc: 'Mayroon ka bang mungkahi para mapabuti ang aming lungsod? Ibahagi ito dito at tumulong na bumuo ng kinabukasan ng Valenzuela.',
        placeholder: 'Anong mga pagbabago ang nais mong makita sa aming lungsod? (hal. Mas magandang kalsada, Maraming parke, Mapabuting kaligtasan ng publiko, atl.)',
        postMySuggestion: 'Ibigay ang Aking Mungkahi',
        noSuggestions: 'Walang mga mungkahi pa. Maging una na magbahagi ng iyong mga ideya!',
        supports: 'sumusuporta',
        comments: 'mga komento',
        support: 'Suportahan',
        comment: 'Magkomento',
        post: 'Ipost',
        addComment: 'Magdagdag ng komento...',
        suggestionPosted: '✅ Ang iyong mungkahi ay naipost na! Maraming salamat sa iyong input.',
        pleaseWrite: 'Mangyaring magsulat ng mungkahi bago magpost.',
        loading: 'Nagkukumposa ng mga abiso...',
        documentTitle: 'Pamagat ng Dokumento',
        profileComingSoon: 'Ang profil ay paparating na',
        settingsComingSoon: 'Ang mga setting ay paparating na',
        documentsComingSoon: 'Ang mga dokumento ay paparating na',
        helpComingSoon: 'Tulong & FAQ paparating na',
        consultationFormComingSoon: 'Ang form ng konsultasyon ay paparating na',
        savedItemsComingSoon: 'Ang mga nakaligtas na item ay paparating na',
        activityComingSoon: 'Ang kasaysayan ay paparating na',
      }
    };

    let currentLanguage = localStorage.getItem('language') || 'en';

    function t(key) {
      return translations[currentLanguage][key] || translations['en'][key];
    }

    function toggleLanguage() {
      currentLanguage = currentLanguage === 'en' ? 'fil' : 'en';
      localStorage.setItem('language', currentLanguage);
      updateLanguage();
    }

    function updateLanguage() {
      // Top bar
      document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        el.textContent = t(key);
      });

      // Update language button
      const langBtn = document.getElementById('language-btn');
      if (langBtn) {
        langBtn.textContent = currentLanguage === 'en' ? 'EN' : 'TL';
      }

      // Update specific elements that may not have data-i18n
      const updates = {
        'dashboard-title': 'yourDashboard',
        'dashboard-desc': 'welcome',
        'stat-label-1': 'activeConsultations',
        'stat-label-2': 'latestAnnouncements',
        'quick-title': 'quickActions',
        'announcements-header': 'latestAnnouncementsHeader',
        'consultations-header': 'activeConsultationsHeader',
        'submissions-header': 'mySubmissionsHeader',
        'documents-header': 'importantDocuments',
        'suggestions-header': 'citizenSuggestions',
        'share-title': 'shareIdeas',
        'share-desc': 'shareIdeasDesc',
      };

      Object.keys(updates).forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = t(updates[id]);
      });

      // Update placeholders
      const suggestionInput = document.getElementById('suggestion-input');
      if (suggestionInput) suggestionInput.placeholder = t('placeholder');

      // Update button texts
      document.querySelectorAll('[data-btn-i18n]').forEach(btn => {
        const key = btn.getAttribute('data-btn-i18n');
        btn.textContent = t(key);
      });
    }

    function scrollToSection(id) {
      // optional event param support
      const evt = arguments[1];
      if (evt && evt.preventDefault) evt.preventDefault();
      
      // Hide all sections
      document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
      
      // Show selected section
      const element = document.getElementById(id);
      if (element) {
        element.classList.add('active');

        // Update active link in any menu
        document.querySelectorAll('.sidebar-nav a, .overlay-menu-panel a').forEach(a => a.classList.remove('active'));
        const sel = `.overlay-menu-panel a[data-section="${id}"]`;
        const link = document.querySelector(sel);
        if (link) link.classList.add('active');

        // close overlay menu if open
        closeMenu();
      }
    }

    // ========================================
    // Citizen Suggestions Management
    // ========================================
    function getSuggestions() {
      return JSON.parse(localStorage.getItem('citizen_suggestions') || '[]');
    }

    function saveSuggestions(suggestions) {
      localStorage.setItem('citizen_suggestions', JSON.stringify(suggestions));
    }

    function getPostContent() {
      const postEl = document.getElementById('post-input');
      if (postEl) {
        if (postEl.tagName === 'TEXTAREA' || postEl.tagName === 'INPUT') return postEl.value.trim();
        return postEl.innerText.trim();
      }
      const textarea = document.getElementById('suggestion-input');
      return textarea ? textarea.value.trim() : '';
    }

    function clearPostInput() {
      const postEl = document.getElementById('post-input');
      if (postEl) {
        if (postEl.tagName === 'TEXTAREA' || postEl.tagName === 'INPUT') postEl.value = '';
        else postEl.innerText = '';
      }
      const textarea = document.getElementById('suggestion-input');
      if (textarea) textarea.value = '';
    }

    function postSuggestion() {
      const text = getPostContent();
      if (!text) { alert('Please write a post before posting.'); return; }

      // Save to server database
      const form = new FormData();
      form.append('content', text);
      fetch('create_post.php', { method: 'POST', body: form })
        .then(res => res.json())
        .then(json => {
          if (json && json.success) {
            // Post saved successfully to database
            alert('✅ Your suggestion has been posted! Thank you for your input.');
            clearPostInput();
            // Reload posts from server immediately
            setTimeout(loadServerPosts, 300);
          } else {
            alert('Error: ' + (json?.error || 'Could not post suggestion'));
            console.error('Post creation failed:', json);
          }
        }).catch(err => {
          alert('Error posting suggestion. Please try again.');
          console.error('Server post error:', err);
        });
    }

    function resetPostForm() { clearPostInput(); }

    function loadSuggestions() {
      const feed = document.getElementById('suggestions-feed');
      if (!feed) return;

      const suggestions = getSuggestions();

      if (suggestions.length === 0) {
        feed.innerHTML = '<div class="empty-state">No suggestions yet. Be the first to share your ideas!</div>';
        return;
      }

      feed.innerHTML = suggestions.reverse().map(sug => `
        <div class="suggestion-card">
          <div class="meta">${sug.author} • ${new Date(sug.timestamp).toLocaleDateString()}</div>
          <div class="content">${escapeHtml(sug.text)}</div>
          <div class="suggestion-stats">
            <span>▲ ${sug.supports.length} supports</span>
            <span>≡ ${sug.comments.length} comments</span>
          </div>
          <div style="display: flex; gap: 8px; margin-top: 10px;">
            <button onclick="supportSuggestion('${sug.id}')" style="padding: 6px 12px; background: ${sug.supports.includes(CURRENT_CITIZEN) ? 'var(--red-600)' : 'var(--gray-200)'}; color: ${sug.supports.includes(CURRENT_CITIZEN) ? 'white' : 'var(--gray-800)'}; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; font-family: inherit;">▲ Support</button>
            <button onclick="toggleCommentForm('sug_${sug.id}')" style="padding: 6px 12px; background: var(--gray-200); color: var(--gray-800); border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; font-family: inherit;">≡ Comment</button>
          </div>
          <div id="sug_${sug.id}-comments" style="display: none; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--gray-200);">
            <div style="display: flex; gap: 8px; margin-bottom: 10px;">
              <input type="text" id="comment-sug-${sug.id}" placeholder="Add a comment..." style="flex: 1; padding: 6px 10px; border: 1px solid var(--gray-200); border-radius: 6px; font-family: inherit; font-size: 12px;">
              <button onclick="addSuggestionComment('${sug.id}')" style="padding: 6px 12px; background: var(--red-600); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; font-family: inherit;">Post</button>
            </div>
            <div id="comments-list-sug-${sug.id}">
              ${sug.comments.map(c => `
                <div style="padding: 8px; background: var(--gray-100); border-radius: 6px; margin-bottom: 6px; font-size: 12px;">
                  <div style="font-weight: 600; color: var(--gray-800);">${escapeHtml(c.author)}</div>
                  <div style="color: var(--gray-700); margin: 2px 0;">${escapeHtml(c.text)}</div>
                  <div style="color: var(--muted); font-size: 11px;">${new Date(c.timestamp).toLocaleString()}</div>
                </div>
              `).join('')}
            </div>
          </div>
        </div>
      `).join('');
    }

    // Load posts from server if available
    function loadServerPosts() {
      const feed = document.getElementById('suggestions-feed');
      if (!feed) return;
      fetch('API/get_posts.php?limit=50')
        .then(res => res.json())
        .then(posts => {
          if (!posts || posts.length === 0) {
            feed.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--muted);">No suggestions yet. Be the first to share your ideas!</div>';
            return;
          }
          const html = posts.map(p => `
            <div class="suggestion-card">
              <div class="meta">${escapeHtml(p.author)} • ${new Date(p.created_at).toLocaleDateString()}</div>
              <div class="content">${escapeHtml(p.content)}</div>
            </div>
          `).join('');
          feed.innerHTML = html;
        }).catch(err => { 
          console.warn('Failed to load server posts', err); 
          feed.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--muted);">Unable to load suggestions. Please refresh the page.</div>';
        });
    }

    function addAnnouncementComment(annId) {
      const input = document.getElementById('comment-ann-' + annId);
      if (!input || !input.value.trim()) return;

      const commentsList = document.getElementById('comments-list-ann-' + annId);
      const comment = {
        author: 'You',
        text: input.value.trim(),
        timestamp: new Date().toLocaleString()
      };

      const commentEl = document.createElement('div');
      commentEl.style.cssText = 'padding: 8px; background: var(--gray-100); border-radius: 6px; font-size: 13px;';
      commentEl.innerHTML = `
        <div style="font-weight: 600; color: var(--gray-800);">${escapeHtml(comment.author)}</div>
        <div style="color: var(--gray-700); margin: 2px 0;">${escapeHtml(comment.text)}</div>
        <div style="color: var(--muted); font-size: 11px;">${comment.timestamp}</div>
      `;
      commentsList.appendChild(commentEl);
      input.value = '';
    }

    function toggleCommentForm(id) {
      const section = document.getElementById(id + '-comments');
      if (section) {
        section.style.display = section.style.display === 'none' ? 'block' : 'none';
        if (section.style.display === 'block') {
          const inputId = id.replace('ann_', 'comment-ann-');
          const input = document.getElementById(inputId);
          if (input) input.focus();
        }
      }
    }

    function toggleLikeAnnouncement(btn, annId) {
      btn.classList.toggle('liked');
      const likeCount = btn.querySelector('.like-count');
      let count = parseInt(likeCount.textContent) || 0;
      count = btn.classList.contains('liked') ? count + 1 : count - 1;
      likeCount.textContent = count;
    }

    function toggleSaveAnnouncement(btn, annId) {
      btn.classList.toggle('saved');
      if (btn.classList.contains('saved')) {
        btn.style.color = 'var(--red-600)';
        btn.style.fontWeight = '600';
      } else {
        btn.style.color = 'var(--muted)';
        btn.style.fontWeight = '500';
      }
    }

    function supportSuggestion(id) {
      const suggestions = getSuggestions();
      const sug = suggestions.find(s => s.id === id);
      if (!sug) return;

      if (sug.supports.includes(CURRENT_CITIZEN)) {
        sug.supports = sug.supports.filter(u => u !== CURRENT_CITIZEN);
      } else {
        sug.supports.push(CURRENT_CITIZEN);
      }

      saveSuggestions(suggestions);
      loadSuggestions();
    }

    function toggleCommentForm(id) {
      const section = document.getElementById(id + '-comments');
      if (section) {
        section.style.display = section.style.display === 'none' ? 'block' : 'none';
        if (section.style.display === 'block') {
          document.getElementById('comment-sug-' + id.replace('sug_', '')).focus();
        }
      }
    }

    function addSuggestionComment(id) {
      const input = document.getElementById('comment-sug-' + id);
      if (!input || !input.value.trim()) return;

      const suggestions = getSuggestions();
      const sug = suggestions.find(s => s.id === id);
      if (!sug) return;

      sug.comments.push({
        author: 'You',
        text: input.value.trim(),
        timestamp: Date.now()
      });

      saveSuggestions(suggestions);
      input.value = '';
      loadSuggestions();
      setTimeout(() => {
        const section = document.getElementById('sug_' + id + '-comments');
        if (section) section.style.display = 'block';
      }, 100);
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function viewDocument(url, title) {
      document.getElementById('modal-title').textContent = title;
      document.getElementById('document-viewer').src = url;
      document.getElementById('document-modal').style.display = 'block';
      document.body.style.overflow = 'hidden';
    }

    function closeDocument() {
      document.getElementById('document-modal').style.display = 'none';
      document.body.style.overflow = '';
    }

    function downloadDocument(url) {
      const link = document.createElement('a');
      link.href = url;
      link.download = url.split('/').pop();
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      alert('Document download started.');
    }

    // Close modal when clicking outside
    document.getElementById('document-modal').addEventListener('click', function(e) {
      if (e.target === this) closeDocument();
    });

    // Top-right menu controls (toggle, blur background, and aria updates)
    function toggleMenu(){
      const overlay = document.getElementById('overlay-menu');
      const main = document.querySelector('.main-content');
      const topbar = document.querySelector('.top-bar');
      const btn = document.getElementById('menu-btn');
      if(!overlay) return;

      const opening = !overlay.classList.contains('active');
      if(opening){
        overlay.classList.add('active');
        overlay.style.display = 'block';
        if(btn) btn.setAttribute('aria-expanded', 'true');
      } else {
        overlay.classList.remove('active');
        overlay.style.display = 'none';
        if(btn) btn.setAttribute('aria-expanded', 'false');
      }
    }

    function closeMenu() {
      const overlay = document.getElementById('overlay-menu');
      if (overlay) {
        overlay.classList.remove('active');
        overlay.style.display = 'none';
      }
      const btn = document.getElementById('menu-btn');
      if (btn) btn.setAttribute('aria-expanded', 'false');
    }

    function updateActiveMenuLink() {
      const params = new URLSearchParams(window.location.search);
      const currentSection = params.get('section') || 'dashboard';
      
      document.querySelectorAll('.overlay-menu-panel a[data-section]').forEach(link => {
        const dataSection = link.getAttribute('data-section');
        if (dataSection === currentSection) {
          link.classList.add('active');
        } else {
          link.classList.remove('active');
        }
      });
    }

    // Initialize active menu link on page load
    window.addEventListener('DOMContentLoaded', updateActiveMenuLink);
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ const overlay = document.getElementById('overlay-menu'); if(overlay && overlay.classList.contains('active')) toggleMenu(); } });

    // Theme Toggle (dark/light mode)
    function initThemeToggle() {
      const html = document.documentElement;
      const savedTheme = localStorage.getItem('theme');
      if (savedTheme === 'dark') {
        html.classList.add('dark');
        updateThemeIcon(true);
      } else {
        updateThemeIcon(false);
      }
    }

    function updateThemeIcon(isDark) {
      const darkIcon = document.querySelector('.dark-mode-icon');
      const lightIcon = document.querySelector('.light-mode-icon');
      if (darkIcon && lightIcon) {
        if (isDark) {
          darkIcon.style.display = 'none';
          lightIcon.style.display = 'inline';
        } else {
          darkIcon.style.display = 'inline';
          lightIcon.style.display = 'none';
        }
      }
    }

    function toggleTheme() {
      const html = document.documentElement;
      html.classList.toggle('dark');
      const isDark = html.classList.contains('dark');
      localStorage.setItem('theme', isDark ? 'dark' : 'light');
      updateThemeIcon(isDark);
    }

    // Initialize theme on page load

    // Update stat cards when announcements load
    function updateStats() {
      const announcements = JSON.parse(localStorage.getItem('announcements') || '[]');
      document.getElementById('stat-announcements').textContent = announcements.length;
    }

    // Initialize theme on page load
    initThemeToggle();

    // Settings Functions
    function setLanguage(lang) {
      currentLanguage = lang;
      localStorage.setItem('language', lang);
      updateLanguage();
      document.getElementById('language-btn').textContent = lang === 'en' ? 'EN' : 'TL';
    }

    function setTheme(theme) {
      const html = document.documentElement;
      if (theme === 'dark') {
        html.classList.add('dark');
        localStorage.setItem('theme', 'dark');
      } else {
        html.classList.remove('dark');
        localStorage.setItem('theme', 'light');
      }
      updateThemeIcon(theme === 'dark');
    }

    function clearLocalStorage() {
      if (confirm('Clear all saved data? (Theme and language preferences will be preserved)')) {
        localStorage.removeItem('citizen_suggestions');
        localStorage.removeItem('llrm_announcements');
        alert('Saved data has been cleared.');
      }
    }

    // Sync theme radio buttons on page load
    document.addEventListener('DOMContentLoaded', function() {
      const isDark = document.documentElement.classList.contains('dark');
      document.querySelector(`input[name="theme"][value="${isDark ? 'dark' : 'light'}"]`).checked = true;
    });

    // Initial stats update & load suggestions
    updateStats();
    loadServerPosts();  // Load from database first
    updateLanguage();  // Apply initial language

    // Update stats when announcements feed loads
    const originalLoadFeed = window.loadAnnouncementsFeed;
    if (originalLoadFeed) {
      window.loadAnnouncementsFeed = function() {
        originalLoadFeed();
        updateStats();
      };
    }

    // Auto-refresh suggestions every 5 seconds from server
    setInterval(loadServerPosts, 5000);

    // Saved Items Modal Functions
    function openSavedItemsModal() {
      const overlay = document.getElementById('saved-items-overlay');
      if (!overlay) return;
      overlay.classList.add('active');
      overlay.style.display = 'block';
      loadSavedItemsForModal();
    }

    function closeSavedItemsModal() {
      const overlay = document.getElementById('saved-items-overlay');
      if (overlay) {
        overlay.classList.remove('active');
        overlay.style.display = 'none';
      }
    }

    function loadSavedItemsForModal() {
      const container = document.getElementById('modal-saved-items-container');
      if (!container) return;
      
      // Get all announcements from the page or fetch them
      const allAnns = document.querySelectorAll('[data-announcement-id]');
      let hasSaved = false;
      let html = '';
      
      // If no announcements on page, fetch them
      if (allAnns.length === 0) {
        fetch('API/get_announcements_api.php')
          .then(r => r.json())
          .then(data => {
            if (data && data.announcements) {
              html = generateSavedItemsHtml(data.announcements);
              container.innerHTML = html || '<div style="text-align: center; padding: 30px 20px; color: var(--muted);">No saved items yet. Save announcements!</div>';
            }
          })
          .catch(err => {
            console.error('Error loading announcements:', err);
            container.innerHTML = '<div style="text-align: center; padding: 30px 20px; color: var(--muted);">Error loading saved items.</div>';
          });
      } else {
        // Use announcements already on page
        allAnns.forEach(ann => {
          const savesEl = ann.querySelector('[data-saves]');
          const saves = savesEl ? JSON.parse(savesEl.dataset.saves || '[]') : [];
          const userId = <?php echo json_encode($user_id); ?>;
          
          if (saves.includes(userId)) {
            hasSaved = true;
            html += generateSingleSavedItemHtml(ann);
          }
        });
        
        if (!hasSaved) {
          html = '<div style="text-align: center; padding: 30px 20px; color: var(--muted);">No saved items yet. Save announcements!</div>';
        }
        container.innerHTML = html;
      }
    }

    function generateSingleSavedItemHtml(annElement) {
      const id = annElement.getAttribute('data-announcement-id');
      const title = annElement.querySelector('[data-title]')?.textContent || 'Untitled';
      const content = annElement.querySelector('[data-content]')?.textContent || '';
      const date = annElement.querySelector('[data-date]')?.textContent || '';
      const img = annElement.querySelector('img')?.src || '';
      
      let html = '<div style="padding: 16px; background: var(--gray-100); border-radius: 8px; border-left: 4px solid var(--red-600); margin-bottom: 12px;">';
      if (img) {
        html += '<div style="margin-bottom: 12px; border-radius: 6px; overflow: hidden;"><img src="' + img + '" alt="' + title + '" style="width: 100%; max-height: 200px; object-fit: cover;"></div>';
      }
      html += '<div style="display: flex; justify-content: space-between; align-items: start;">';
      html += '<div style="flex: 1;"><h3 style="margin: 0 0 6px; font-size: 16px; font-weight: 700;">' + title + '</h3>';
      html += '<p style="margin: 0 0 10px; font-size: 13px; color: var(--muted);">' + date + '</p>';
      html += '<div style="font-size: 14px; color: var(--gray-700);">' + (content.substring(0, 300) + (content.length > 300 ? '...' : '')) + '</div></div>';
      html += '<button onclick="removeSavedItemFromModal(' + id + ', this)" style="padding: 8px 12px; background: var(--gray-200); color: var(--gray-800); border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; margin-left: 12px; white-space: nowrap;">Remove</button>';
      html += '</div></div>';
      return html;
    }

    function generateSavedItemsHtml(announcements) {
      const userId = <?php echo json_encode($user_id); ?>;
      let html = '';
      let hasSaved = false;
      
      announcements.forEach(ann => {
        const saved_by = JSON.parse(ann.saved_by || '[]');
        if (saved_by.includes(userId)) {
          hasSaved = true;
          html += '<div style="padding: 16px; background: var(--gray-100); border-radius: 8px; border-left: 4px solid var(--red-600); margin-bottom: 12px;" data-saved-item="' + ann.id + '">';
          if (ann.image_path) {
            html += '<div style="margin-bottom: 12px; border-radius: 6px; overflow: hidden;"><img src="' + ann.image_path + '" alt="' + ann.title + '" style="width: 100%; max-height: 200px; object-fit: cover;"></div>';
          }
          html += '<div style="display: flex; justify-content: space-between; align-items: start;">';
          html += '<div style="flex: 1;"><h3 style="margin: 0 0 6px; font-size: 16px; font-weight: 700;">' + ann.title + '</h3>';
          html += '<p style="margin: 0 0 10px; font-size: 13px; color: var(--muted);">| ' + new Date(ann.created_at).toLocaleDateString() + '</p>';
          html += '<div style="font-size: 14px; color: var(--gray-700);">' + (ann.content.substring(0, 300) + (ann.content.length > 300 ? '...' : '')) + '</div></div>';
          html += '<button onclick="removeSavedItemFromModal(' + ann.id + ', this)" style="padding: 8px 12px; background: var(--gray-200); color: var(--gray-800); border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; margin-left: 12px; white-space: nowrap;">Remove</button>';
          html += '</div></div>';
        }
      });
      
      return html;
    }

    function removeSavedItemFromModal(announcementId, button) {
      button.disabled = true;
      button.textContent = 'Removing...';
      
      toggleAnnouncementAction({
        target: button,
        stopPropagation: function() {}
      }, announcementId, 'save').then(() => {
        // Remove the item
        const itemContainer = button.closest('[data-saved-item], [style*="padding: 16px"]');
        if (itemContainer) {
          itemContainer.style.opacity = '0';
          itemContainer.style.transition = 'opacity 0.3s ease';
          setTimeout(() => {
            itemContainer.remove();
            
            // Check if no items left
            const container = document.getElementById('modal-saved-items-container');
            if (container && container.children.length === 0) {
              container.innerHTML = '<div style="text-align: center; padding: 30px 20px; color: var(--muted);">No saved items yet. Save announcements!</div>';
            }
          }, 300);
        }
      }).catch(err => {
        button.disabled = false;
        button.textContent = 'Remove';
        alert('Error removing saved item');
        console.error(err);
      });
    }

    function openNotifications() {
      const overlay = document.getElementById('notifications-overlay');
      if (!overlay) return;
      overlay.classList.add('active');
      overlay.style.display = 'block';
    }

    function closeNotificationsModal() {
      const overlay = document.getElementById('notifications-overlay');
      if (overlay) {
        overlay.classList.remove('active');
        overlay.style.display = 'none';
      }
    }

    // ========================================
    // Settings Functions
    // ========================================
    function saveProfileChanges() {
      const fullname = document.getElementById('profile-fullname')?.value;
      const email = document.getElementById('profile-email')?.value;
      const username = document.getElementById('profile-username')?.value;

      if (!fullname || !email) {
        alert('Please fill in name and email');
        return;
      }

      const formData = new FormData();
      formData.append('action', 'update_profile');
      formData.append('fullname', fullname);
      formData.append('email', email);
      formData.append('username', username || '');

      fetch('API/update_profile.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Profile updated successfully!');
          location.reload();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Failed to update profile');
      });
    }

    function uploadProfilePhoto() {
      const fileInput = document.getElementById('photo-upload');
      if (!fileInput || !fileInput.files[0]) {
        alert('Please select a file');
        return;
      }

      const formData = new FormData();
      formData.append('action', 'upload_photo');
      formData.append('photo', fileInput.files[0]);

      fetch('API/update_profile.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Photo uploaded successfully!');
          location.reload();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Failed to upload photo');
      });
    }

    function updatePassword() {
      const currentPassword = document.getElementById('current-password')?.value;
      const newPassword = document.getElementById('new-password')?.value;
      const confirmPassword = document.getElementById('confirm-password')?.value;

      if (!currentPassword || !newPassword || !confirmPassword) {
        alert('Please fill in all password fields');
        return;
      }

      if (newPassword !== confirmPassword) {
        alert('New passwords do not match');
        return;
      }

      if (newPassword.length < 6) {
        alert('New password must be at least 6 characters');
        return;
      }

      const formData = new FormData();
      formData.append('action', 'change_password');
      formData.append('current_password', currentPassword);
      formData.append('new_password', newPassword);
      formData.append('confirm_password', confirmPassword);

      fetch('API/update_profile.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Password changed successfully!');
          document.getElementById('current-password').value = '';
          document.getElementById('new-password').value = '';
          document.getElementById('confirm-password').value = '';
          togglePasswordChange();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Failed to change password');
      });
    }

    function savePreferences() {
      const language = document.querySelector('select')?.value || 'en';
      const themeRadio = document.querySelector('input[name="theme"]:checked');
      const theme = themeRadio?.value || 'light';
      const emailNotif = document.getElementById('email-notif')?.checked || false;
      const announcementNotif = document.getElementById('announcement-notif')?.checked || false;
      const feedbackNotif = document.getElementById('feedback-notif')?.checked || false;

      const formData = new FormData();
      formData.append('action', 'save_preferences');
      formData.append('language', language);
      formData.append('theme', theme);
      if (emailNotif) formData.append('email_notif', '1');
      if (announcementNotif) formData.append('announcement_notif', '1');
      if (feedbackNotif) formData.append('feedback_notif', '1');

      fetch('API/update_profile.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Preferences saved successfully!');
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Failed to save preferences');
      });
    }

    function clearLocalStorage() {
      if (confirm('Are you sure you want to clear all saved data? (Theme and language will be preserved)')) {
        localStorage.clear();
        alert('Saved data cleared successfully!');
        location.reload();
      }
    }
  

    function loadNotificationsForModal() {
      // Already rendered in HTML - no need to load dynamically
    }

    // Settings Tab Functions
    function switchSettingsTab(tabName) {
      // Hide all tabs
      document.querySelectorAll('.settings-tab-content').forEach(tab => {
        tab.style.display = 'none';
      });
      
      // Remove active state from all buttons
      document.querySelectorAll('.settings-tab').forEach(btn => {
        btn.classList.remove('active');
        btn.style.borderBottomColor = 'transparent';
        btn.style.color = 'var(--muted)';
      });
      
      // Show selected tab
      const selectedTab = document.getElementById(tabName + '-tab');
      if (selectedTab) {
        selectedTab.style.display = 'block';
      }
      
      // Mark button as active
      const activeBtn = document.querySelector(`.settings-tab[data-tab="${tabName}"]`);
      if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.style.borderBottomColor = 'var(--red-600)';
        activeBtn.style.color = 'var(--red-600)';
      }
    }

    function togglePasswordChange() {
      const form = document.getElementById('password-change-form');
      if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
      }
    }
  </script>

  <!-- Notifications Side Panel -->
  <div id="notifications-overlay" onclick="if(event.target.id === 'notifications-overlay') closeNotificationsModal()">
    <div class="overlay-menu-panel" style="right: 16px; top: 8px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; padding-bottom:12px; border-bottom: 1px solid var(--gray-200);">
        <h2 style="margin: 0; font-size: 18px; font-weight: 700;">● Notifications</h2>
        <button onclick="closeNotificationsModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; padding: 0; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 4px;">×</button>
      </div>
      <div style="padding: 16px; text-align: center; color: var(--muted);">
        <div style="font-size: 40px; margin-bottom: 12px;">◐</div>
        <p style="margin: 0; font-size: 14px;">You have no new notifications at this time.</p>
      </div>
    </div>
  </div>
