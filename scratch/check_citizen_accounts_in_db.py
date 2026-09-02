import subprocess

php_code = """<?php
require_once 'db.php';
$conn = dbEnsureConnection();

echo "=== CITIZEN USERS IN DATABASE (users table) ===\n";
$res1 = $conn->query("SELECT id, fullname, email, role, created_at FROM users WHERE LOWER(role) IN ('citizen', 'user') OR role IS NULL OR role = ''");
if ($res1 && $res1->num_rows > 0) {
    while ($r = $res1->fetch_assoc()) {
        echo "ID: {$r['id']} | Name: {$r['fullname']} | Email: {$r['email']} | Role: {$r['role']} | Created: {$r['created_at']}\n";
    }
} else {
    echo "No citizen rows found in users table.\n";
}

echo "\n=== CITIZENS FROM FEEDBACK TABLE ===\n";
$res2 = $conn->query("SELECT DISTINCT guest_name, guest_email FROM feedback");
if ($res2 && $res2->num_rows > 0) {
    while ($r = $res2->fetch_assoc()) {
        echo "Name: {$r['guest_name']} | Email: {$r['guest_email']}\n";
    }
}
?>"""

with open(r"c:\xampp\htdocs\CAP101\PC\scratch\check_citizens_db.php", "w") as f:
    f.write(php_code)

res = subprocess.run(["C:\\xampp\\php\\php.exe", r"c:\xampp\htdocs\CAP101\PC\scratch\check_citizens_db.php"], capture_output=True, text=True)
print(res.stdout[:3000])
