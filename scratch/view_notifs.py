import subprocess

cmd = ["C:\\xampp\\php\\php.exe", "-r", "require 'db.php'; $res = $conn->query('SELECT id, user_id, title, message, is_read, created_at FROM notifications'); while($r = $res->fetch_assoc()) { print_r($r); }"]
result = subprocess.run(cmd, capture_output=True, text=True, cwd=r'c:\xampp\htdocs\CAP101\PC')
print("=== NOTIFICATIONS TABLE DATA ===")
print(result.stdout)
