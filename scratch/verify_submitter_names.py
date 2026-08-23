import urllib.request
import json
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

# Query feedback API directly using PHP CLI
import subprocess

cmd = ["C:\\xampp\\php\\php.exe", "-r", "require 'db.php'; $res = $conn->query('SELECT f.id, f.consultation_id, f.guest_name, f.message FROM feedback f ORDER BY f.consultation_id, f.id LIMIT 10'); while($r = $res->fetch_assoc()) { print_r($r); }"]
result = subprocess.run(cmd, capture_output=True, text=True, cwd=r'c:\xampp\htdocs\CAP101\PC')

print("=== SAMPLE FEEDBACK SUBMITTER NAMES IN DB ===")
print(result.stdout)
