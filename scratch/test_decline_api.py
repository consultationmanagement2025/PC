import subprocess

cmd = ["C:\\xampp\\php\\php.exe", "-r", "
\$_POST = ['id' => 1, 'reason' => 'Testing decline action'];
\$_GET = ['action' => 'decline_submission'];
require 'API/consultations_api.php';
"]
result = subprocess.run(cmd, capture_output=True, text=True, cwd=r'c:\xampp\htdocs\CAP101\PC')
print("=== DECLINE API TEST ===")
print(result.stdout)
print(result.stderr)
