import subprocess

cmd = ["C:\\xampp\\php\\php.exe", "-r", "require 'db.php'; $res = $conn->query('SELECT COUNT(*) as cnt FROM hearing_queue WHERE approval_status = \"pending\" OR approval_status IS NULL'); print_r($res->fetch_assoc());"]
result = subprocess.run(cmd, capture_output=True, text=True, cwd=r'c:\xampp\htdocs\CAP101\PC')
print("=== PENDING HEARING QUEUE ROWS COUNT ===")
print(result.stdout)
