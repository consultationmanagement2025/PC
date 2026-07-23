<?php
require_once __DIR__ . '/menu.php';

function renderAdminHead(string $title, string $activePage): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - PCMS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-body<?php echo ''; ?>">
<div class="admin-layout">
    <?php renderAdminSidebar($activePage); ?>
    <div class="admin-main">
        <?php renderAdminTopbar(); ?>
        <div class="admin-content">
    <?php
}

function renderAdminSidebar(string $activePage): void
{
    global $user;
    $menuItems = getAdminMenuItems();
    ?>
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="admin-sidebar-header">
            <div class="admin-sidebar-logo">
                <img src="https://placehold.co/100x100/ffffff/0033a0?text=Seal" alt="City Seal">
            </div>
            <div class="admin-sidebar-brand">
                <h1>PCMS</h1>
                <p>City of Valenzuela</p>
            </div>
        </div>

        <nav class="admin-sidebar-nav">
            <?php foreach ($menuItems as $section): ?>
                <div class="admin-nav-section">
                    <div class="admin-nav-section-title"><?php echo htmlspecialchars($section['label']); ?></div>
                    <?php foreach ($section['items'] as $item): ?>
                        <a href="<?php echo htmlspecialchars($item['url']); ?>"
                           class="admin-nav-link<?php echo $activePage === $item['id'] ? ' active' : ''; ?>">
                            <i class="fa-solid <?php echo htmlspecialchars($item['icon']); ?>"></i>
                            <span><?php echo htmlspecialchars($item['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar-footer">
            <div class="admin-user-profile">
                <div class="admin-user-avatar">
                    <i class="fa-solid fa-user text-sm"></i>
                </div>
                <div>
                    <div class="admin-user-name"><?php echo htmlspecialchars($user['fullname']); ?></div>
                    <div class="admin-user-role"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></div>
                </div>
            </div>
        </div>
    </aside>
    <?php
}

function renderAdminTopbar(): void
{
    global $user;
    ?>
    <header class="admin-topbar">
        <div class="admin-topbar-left">
            <button class="admin-sidebar-toggle" id="sidebar-toggle" type="button" aria-label="Toggle sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
        <div class="admin-topbar-right">
            <div class="admin-datetime" id="admin-datetime">
                <span class="admin-datetime-time" id="admin-clock">00:00:00</span>
                <span class="admin-datetime-date" id="admin-date">Sat, Jul 18, 2026</span>
            </div>

            <div class="admin-topbar-dropdown">
                <button class="admin-topbar-btn" id="notification-toggle" type="button" aria-label="Notifications">
                    <i class="fa-solid fa-bell text-sm"></i>
                </button>
                <div class="admin-dropdown-panel" id="notification-panel">
                    <div class="admin-dropdown-header">Notifications</div>
                    <div class="admin-dropdown-empty">No new notifications</div>
                </div>
            </div>

            <div class="admin-topbar-dropdown">
                <button class="admin-topbar-btn" id="profile-toggle" type="button" aria-label="Profile">
                    <i class="fa-solid fa-user text-sm"></i>
                </button>
                <div class="admin-dropdown-panel admin-profile-panel" id="profile-panel">
                    <div class="admin-profile-info">
                        <div class="admin-profile-avatar">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div>
                            <div class="admin-profile-name"><?php echo htmlspecialchars($user['fullname']); ?></div>
                            <div class="admin-profile-role"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></div>
                        </div>
                    </div>
                    <a href="../public/sign-out.php" class="admin-dropdown-link">
                        <i class="fa-solid fa-right-from-bracket"></i> Sign Out
                    </a>
                </div>
            </div>
        </div>
    </header>
    <?php
}

function renderAdminFoot(): void
{
    ?>
        </div>
    </div>
</div>
<div id="admin-toast-container" class="admin-toast-container" aria-live="polite" aria-atomic="true"></div>
<script src="assets/js/admin.js"></script>
</body>
</html>
    <?php
}
