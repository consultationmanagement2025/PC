import subprocess

cmd = ["C:\\xampp\\php\\php.exe", "-r", "require 'db.php'; $res = $conn->query('SELECT queue_id, phms_hearing_id, full_name, status, approval_status FROM hearing_queue'); while($r = $res->fetch_assoc()) { print_r($r); }"]
result = subprocess.run(cmd, capture_output=True, text=True, cwd=r'c:\xampp\htdocs\CAP101\PC')
print("=== ALL HEARING QUEUE ROWS IN DB ===")
print(result.stdout)
