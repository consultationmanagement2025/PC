import os

rp_dashboard_files = [
    r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\resource_person_dashboard.php'
]

old_rp_js_mark = """    function markAllNotificationsRead() {
        fetch('API/resource_person_api.php?action=mark_notif_read', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }"""

new_rp_js_mark = """    function markAllNotificationsRead() {
        if (typeof window.pfpMarkAllNotificationsRead === 'function') {
            window.pfpMarkAllNotificationsRead();
        } else {
            const redDots = document.querySelectorAll('.bg-red-500, .bg-amber-400, .bg-amber-500, [title="Unread"]');
            redDots.forEach(d => d.remove());
            const badges = document.querySelectorAll('.bg-amber-400, .bg-amber-500, #notification-badge');
            badges.forEach(b => { b.innerText = '0'; b.style.display = 'none'; });
            fetch('API/resource_person_api.php?action=mark_notif_read', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                if (typeof showNotification === 'function') showNotification('All notifications marked as read', 'success');
            });
        }
    }"""

print("=== UPDATING RESOURCE PERSON DASHBOARD FILES ===")
for fpath in rp_dashboard_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    code = code.replace(old_rp_js_mark, new_rp_js_mark)

    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated notification mark read logic in:", fpath)

print("Finished updating Resource Person Dashboards!")
