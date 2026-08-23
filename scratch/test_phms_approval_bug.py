import subprocess

cmd = ["C:\\xampp\\php\\php.exe", "-r", "require 'DATABASE/feedback.php'; echo 'BEFORE PENDING: ' . count(getPendingPhmsApprovals()) . '\n'; \$ok = approvePhmsIngestion(1); echo 'APPROVE SINGLE 1 OK: ' . (\$ok ? 'YES' : 'NO') . '\n'; echo 'AFTER PENDING: ' . count(getPendingPhmsApprovals()) . '\n';"]
result = subprocess.run(cmd, capture_output=True, text=True, cwd=r'c:\xampp\htdocs\CAP101\PC')
print("=== SINGLE APPROVAL TEST ===")
print(result.stdout)
print(result.stderr)
