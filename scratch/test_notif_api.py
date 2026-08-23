import subprocess

cmd = ["C:\\xampp\\php\\php.exe", "-r", "require 'API/notifications_api.php';"]
result = subprocess.run(cmd, capture_output=True, text=True, cwd=r'c:\xampp\htdocs\CAP101\PC')
print("=== NOTIFICATIONS API OUTPUT ===")
print(result.stdout)
