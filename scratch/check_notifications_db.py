import subprocess

cmd = ["C:\\xampp\\php\\php.exe", "-r", "require 'db.php'; $res = $conn->query('SELECT COUNT(*) as cnt FROM notifications'); print_r($res->fetch_assoc());"]
result = subprocess.run(cmd, capture_output=True, text=True, cwd=r'c:\xampp\htdocs\CAP101\PC')
print("=== NOTIFICATIONS TABLE COUNT IN DB ===")
print(result.stdout)
