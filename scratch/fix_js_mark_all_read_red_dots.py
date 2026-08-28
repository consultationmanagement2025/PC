import os

app_features_files = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

new_mark_all_read_js = """window.pfpMarkAllNotificationsRead = async function () {
    console.log('[Notifications] Marking all as read across system...');

    // 1. Remove all red unread dots & styling from DOM immediately
    const listContainers = document.querySelectorAll('#notifications-list, #notif-drawer, body');
    listContainers.forEach(container => {
        if (!container) return;
        const items = container.querySelectorAll('[data-id]');
        items.forEach(el => {
            el.classList.remove('bg-white', 'font-medium', 'font-semibold');
            el.classList.add('bg-gray-50/40', 'opacity-75');
        });

        // Remove red unread dot spans
        const redDots = container.querySelectorAll('.bg-red-500, .bg-red-600, .bg-amber-400, .bg-amber-500, [title="Unread"]');
        redDots.forEach(dot => {
            if (dot && !dot.id && !dot.classList.contains('sidebar-badge')) {
                dot.remove();
            }
        });
    });

    // 2. Clear badge counts in header
    const badgeEls = document.querySelectorAll('#notification-badge, #unread-count, #notifications-btn .bg-red-500, #notifications-btn span');
    badgeEls.forEach(badge => {
        badge.innerText = '0';
        badge.classList.add('hidden');
        badge.style.display = 'none';
    });

    // 3. Update local AppData state
    if (window.AppData && Array.isArray(window.AppData.notifications)) {
        window.AppData.notifications.forEach(n => n.is_read = 1);
    }

    // 4. Dispatch background API sync requests
    try {
        await Promise.all([
            fetch('API/notifications_api.php?action=mark_all_read', { method: 'POST' }).catch(() => {}),
            fetch('API/resource_person_api.php?action=mark_notif_read', { method: 'POST' }).catch(() => {})
        ]);
        if (typeof showNotification === 'function') {
            showNotification('All notifications marked as read', 'success');
        }
    } catch (e) {
        console.warn('Failed to sync mark all read:', e);
    }
};"""

print("=== UPDATING pfpMarkAllNotificationsRead IN APP-FEATURES.JS FILES ===")
for fpath in app_features_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    old_func_start = "window.pfpMarkAllNotificationsRead = async function () {"
    if old_func_start in code:
        # Replace up to end of function
        start_idx = code.find(old_func_start)
        end_idx = code.find("};", start_idx) + 2
        code = code[:start_idx] + new_mark_all_read_js + code[end_idx:]
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated pfpMarkAllNotificationsRead in:", fpath)
    else:
        code += "\n" + new_mark_all_read_js
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Appended pfpMarkAllNotificationsRead to:", fpath)

print("Finished updating app-features.js!")
