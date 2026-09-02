import os
import glob

php_files = glob.glob(r"c:\xampp\htdocs\CAP101\PC\**\system-template-full.php", recursive=True)
js_files = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
]

# 1. Update PHP files
for path in php_files:
    if not os.path.exists(path):
        continue
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()
    
    modified = False
    if "$is_read_only_super_admin = $is_super_admin;" in content:
        content = content.replace("$is_read_only_super_admin = $is_super_admin;", "$is_read_only_super_admin = false;")
        modified = True
    
    css_hide = """        /* Hide action buttons in dashboard for super admin read-only */
        #dashboard-section .btn { display: none !important; }
        #dashboard-section button:not(.btn):not([onclick*='openModuleReportModal']) { display: none !important; }
        #dashboard-section [onclick*='manage'] { display: none !important; }"""
    
    if css_hide in content:
        content = content.replace(css_hide, "")
        modified = True

    if modified:
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Updated PHP: {path}")

# 2. Update JS files
for path in js_files:
    if not os.path.exists(path):
        continue
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()

    if "const isReadOnlySuperAdmin = currentUserIsSuperAdmin();" in content:
        content = content.replace(
            "const isReadOnlySuperAdmin = currentUserIsSuperAdmin();",
            "const isReadOnlySuperAdmin = false;"
        )
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Updated JS: {path}")

print("Superadmin full access update completed!")
