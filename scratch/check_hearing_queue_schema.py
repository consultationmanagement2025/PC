import subprocess

cmd = ["C:\\xampp\\php\\php.exe", "-r", "require 'db.php'; $res = $conn->query('DESCRIBE hearing_queue'); while($r = $res->fetch_assoc()) { print_r($r); }"]
result = subprocess.run(cmd, capture_output=True, text=True, cwd=r'c:\xampp\htdocs\CAP101\PC')
print("=== HEARING_QUEUE COLUMNS ===")
print(result.stdout)

cmd2 = ["C:\\xampp\\php\\php.exe", "-r", "require 'db.php'; $res = $conn->query('SELECT * FROM hearing_queue LIMIT 3'); while($r = $res->fetch_assoc()) { print_r($r); }"]
result2 = subprocess.run(cmd2, capture_output=True, text=True, cwd=r'c:\xampp\htdocs\CAP101\PC')
print("=== SAMPLE HEARING_QUEUE ROWS ===")
print(result2.stdout)
