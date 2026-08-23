import subprocess

cmd = ["C:\\xampp\\php\\php.exe", "-r", "
require 'db.php';
echo '=== CONSULTATIONS TABLE SEARCH ===\n';
$res = $conn->query(\"SELECT * FROM consultations WHERE id = 8 OR user_name LIKE '%Aljan%' OR title LIKE '%test%'\");
while(\$r = \$res->fetch_assoc()) { print_r(\$r); }

echo '=== HEARING_QUEUE TABLE SEARCH ===\n';
$res2 = $conn->query(\"SELECT * FROM hearing_queue WHERE queue_id = 8 OR phms_hearing_id = 8 OR full_name LIKE '%Aljan%'\");
while(\$r = \$res2->fetch_assoc()) { print_r(\$r); }

echo '=== FEEDBACK TABLE SEARCH ===\n';
$res3 = $conn->query(\"SELECT * FROM feedback WHERE id = 8 OR author LIKE '%Aljan%' OR guest_name LIKE '%Aljan%'\");
while(\$r = \$res3->fetch_assoc()) { print_r(\$r); }
"]

result = subprocess.run(cmd, capture_output=True, text=True, cwd=r'c:\xampp\htdocs\CAP101\PC')
print(result.stdout)
