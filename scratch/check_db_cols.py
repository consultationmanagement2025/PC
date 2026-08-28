import subprocess

php_code = """<?php
require 'db.php';

echo "=== DOCUMENTS TABLE COLUMNS ===\\n";
$r = $conn->query("SHOW COLUMNS FROM documents");
if ($r) {
    while($row = $r->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\\n";
    }
}

echo "\\n=== ADMIN_DOCUMENTS TABLE COLUMNS ===\\n";
$r2 = $conn->query("SHOW COLUMNS FROM admin_documents");
if ($r2) {
    while($row = $r2->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\\n";
    }
}
"""

with open(r'c:\xampp\htdocs\CAP101\PC\scratch\check_cols.php', 'w', encoding='utf-8') as f:
    f.write(php_code)

out = subprocess.check_output(r'C:\xampp\php\php.exe scratch\check_cols.php', shell=True).decode('utf-8', errors='ignore')
print(out)
