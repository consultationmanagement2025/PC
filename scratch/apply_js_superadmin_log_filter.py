import os

js_files = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
]

old_pfp = "function pfpRenderAuditLogsRows(logs) {"
new_pfp = """function pfpRenderAuditLogsRows(logs) {
    if (typeof currentUserIsSuperAdmin === 'function' && !currentUserIsSuperAdmin()) {
        logs = (logs || []).filter(l => {
            const u = String(l.admin_user || '').toLowerCase();
            return !u.includes('superadmin') && !u.includes('super administrator') && !u.includes('super admin');
        });
    }"""

for path in js_files:
    if not os.path.exists(path):
        continue
    with open(path, "r", encoding="utf-8") as f:
        content = f.read()

    if old_pfp in content and "!currentUserIsSuperAdmin()" not in content:
        content = content.replace(old_pfp, new_pfp)
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Updated JS: {path}")

print("JS superadmin log filter updated successfully!")
