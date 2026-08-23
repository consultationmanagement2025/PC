import subprocess

cmd = ["C:\\xampp\\php\\php.exe", "-r", "require 'db.php'; $res = $conn->query('SELECT id, title, type, status FROM consultations ORDER BY id DESC LIMIT 10'); while(\$r = \$res->fetch_assoc()) { print_r(\$r); }"]
result = subprocess.run(cmd, capture_output=True, text=True, cwd=r'c:\xampp\htdocs\CAP101\PC')
print("=== RECENT CONSULTATIONS IN DB ===")
print(result.stdout)
