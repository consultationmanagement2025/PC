import os
import glob

php_api_files = [
    r"c:\xampp\htdocs\CAP101\PC\API\get_audit_logs_api.php",
    r"c:\xampp\htdocs\CAP101\PC\admin\API\get_audit_logs_api.php",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\API\get_audit_logs_api.php",
]

php_db_files = [
    r"c:\xampp\htdocs\CAP101\PC\DATABASE\audit-log.php",
    r"c:\xampp\htdocs\CAP101\PC\admin\DATABASE\audit-log.php",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\audit-log.php",
]

js_files = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
]

# 1. Update API get_audit_logs_api.php
for path in php_api_files:
    if not os.path.exists(path):
        continue
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()

    old_code = """// Build filters
$filters = [];
if (!empty($_GET['filter_admin'])) $filters['admin_user'] = $_GET['filter_admin'];
if (!empty($_GET['filter_action'])) $filters['action'] = $_GET['filter_action'];
if (!empty($_GET['filter_type'])) $filters['entity_type'] = $_GET['filter_type'];"""

    new_code = """// Check if current user is superadmin
$is_super_admin = in_array(strtolower(trim($current_role)), ['super admin', 'superadmin', 'super_admin'], true);

// Build filters
$filters = [];
if (!empty($_GET['filter_admin'])) $filters['admin_user'] = $_GET['filter_admin'];
if (!empty($_GET['filter_action'])) $filters['action'] = $_GET['filter_action'];
if (!empty($_GET['filter_type'])) $filters['entity_type'] = $_GET['filter_type'];

if (!$is_super_admin) {
    $filters['exclude_superadmin'] = true;
}"""

    if old_code in content:
        content = content.replace(old_code, new_code)
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Updated API: {path}")

# 2. Update DATABASE audit-log.php
for path in php_db_files:
    if not os.path.exists(path):
        continue
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()

    old_filter = """    // Filter by entity type
    if (!empty($filters['entity_type'])) {
        $query .= " AND entity_type = ?";
        $params[] = $filters['entity_type'];
        $types .= "s";
    }"""

    new_filter = """    // Filter by entity type
    if (!empty($filters['entity_type'])) {
        $query .= " AND entity_type = ?";
        $params[] = $filters['entity_type'];
        $types .= "s";
    }

    // Exclude superadmin logs for regular admins
    if (!empty($filters['exclude_superadmin'])) {
        $query .= " AND (admin_id IS NULL OR admin_id NOT IN (SELECT id FROM users WHERE LOWER(role) IN ('super admin', 'superadmin', 'super_admin')))";
        $query .= " AND LOWER(admin_user) NOT LIKE '%superadmin%' AND LOWER(admin_user) NOT LIKE '%super administrator%' AND LOWER(admin_user) NOT LIKE '%super admin%'";
    }"""

    if old_filter in content and "exclude_superadmin" not in content:
        content = content.replace(old_filter, new_filter)
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Updated DB: {path}")

# 3. Update JS app-features.js
for path in js_files:
    if not os.path.exists(path):
        continue
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()

    old_pfp = """function pfpRenderAuditLogsRows(logs) {"""
    new_pfp = """function pfpRenderAuditLogsRows(logs) {
    if (typeof currentUserIsSuperAdmin === 'function' && !currentUserIsSuperAdmin()) {
        logs = (logs || []).filter(l => {
            const u = String(l.admin_user || '').toLowerCase();
            return !u.includes('superadmin') && !u.includes('super administrator') && !u.includes('super admin');
        });
    }"""

    if old_pfp in content and "!currentUserIsSuperAdmin()" not in content:
        content = content.replace(old_pfp, new_pfp)
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Updated JS: {path}")

print("Superadmin log hiding script execution finished!")
