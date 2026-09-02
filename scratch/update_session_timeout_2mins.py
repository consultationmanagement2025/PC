import os
import glob

php_timeout_files = [
    r"c:\xampp\htdocs\CAP101\PC\UTILS\session-timeout.php",
    r"c:\xampp\htdocs\CAP101\PC\admin\UTILS\session-timeout.php",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\UTILS\session-timeout.php",
]

php_dashboard_files = [
    r"c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php",
    r"c:\xampp\htdocs\CAP101\PC\admin\resource_person_dashboard.php",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\resource_person_dashboard.php",
]

js_files = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
]

# 1. Update UTILS/session-timeout.php
for path in php_timeout_files:
    if not os.path.exists(path):
        continue
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()

    old_php_line = "$timeout_duration = ($isAdminRole || $isAdminPath) ? 300 : 600;"
    new_php_line = "$timeout_duration = ($isAdminRole || $isAdminPath) ? 120 : 600; // 2 minutes (120s) for Admin/Superadmin"

    if old_php_line in content:
        content = content.replace(old_php_line, new_php_line)
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Updated PHP Timeout: {path}")

# 2. Update resource_person_dashboard.php
for path in php_dashboard_files:
    if not os.path.exists(path):
        continue
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()

    old_dash_line = "if (Date.now() - lastAct >= 300000)"
    new_dash_line = "if (Date.now() - lastAct >= 120000)"

    if old_dash_line in content:
        content = content.replace(old_dash_line, new_dash_line)
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Updated Dashboard Timeout: {path}")

# 3. Update app-features.js
for path in js_files:
    if not os.path.exists(path):
        continue
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()

    old_js_line = "const idleTimeoutMs = isAdminSide ? 300000 : 600000;"
    new_js_line = "const idleTimeoutMs = isAdminSide ? 120000 : 600000; // 2 minutes (120,000ms) for Admin/Superadmin"

    if old_js_line in content:
        content = content.replace(old_js_line, new_js_line)
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Updated JS Timeout: {path}")

print("Session timeout set to 2 minutes for Admin and Superadmin!")
