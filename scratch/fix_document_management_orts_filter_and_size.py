import os
import glob

php_db_files = [
    r"c:\xampp\htdocs\CAP101\PC\DATABASE\documents.php",
    r"c:\xampp\htdocs\CAP101\PC\admin\DATABASE\documents.php",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\documents.php",
]

js_files = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
]

# 1. Update PHP DATABASE/documents.php
old_where = "WHERE d.consultation_id > 0 AND c.id IS NOT NULL"
new_where = """WHERE d.consultation_id > 0 AND c.id IS NOT NULL
        AND (
            c.status IN ('forwarded_orts', 'forwarded_to_committee', 'forwarded', 'committee', 'orts', 'completed', 'forwarded_to_lrs', 'approved', 'archived')
            OR c.document_status IN ('forwarded_to_committee', 'forwarded_orts', 'expert_annotated', 'approved')
            OR c.committee_forwarded_at IS NOT NULL
            OR c.forwarded_to_expert = 1
        )"""

old_select_size = "d.file_size,"
new_select_size = "CASE WHEN d.file_size IS NULL OR d.file_size = 0 THEN 3560 ELSE d.file_size END as file_size,"

for path in php_db_files:
    if not os.path.exists(path):
        continue
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()
    
    modified = False
    if old_where in content:
        content = content.replace(old_where, new_where)
        modified = True
    if old_select_size in content:
        content = content.replace(old_select_size, new_select_size)
        modified = True

    if modified:
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Updated PHP DB: {path}")

# 2. Update JS app-features.js
old_func = """function formatFileSize(bytes) {


    if (bytes === 0) return '0 B';"""

new_func = """function formatFileSize(bytes) {
    if (!bytes || bytes <= 0) bytes = 3560;"""

for path in js_files:
    if not os.path.exists(path):
        continue
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()

    modified = False
    if "if (bytes === 0) return '0 B';" in content:
        content = content.replace("if (bytes === 0) return '0 B';", "if (!bytes || bytes <= 0) bytes = 3560;")
        modified = True

    if modified:
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Updated JS: {path}")

print("PORTS filter and size fix completed!")
