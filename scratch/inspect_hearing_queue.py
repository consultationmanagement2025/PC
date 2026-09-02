import subprocess

php_code = """<?php
require_once 'db.php';
$conn = dbEnsureConnection();

echo "=== HEARING_QUEUE TABLE CONTENT ===\\n";
$res = $conn->query("SELECT * FROM hearing_queue");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "No rows found in hearing_queue or error: " . $conn->error . "\\n";
}
?>"""

with open(r"c:\xampp\htdocs\CAP101\PC\scratch\check_hq.php", "w") as f:
    f.write(php_code)

res = subprocess.run(["C:\\xampp\\php\\php.exe", r"c:\xampp\htdocs\CAP101\PC\scratch\check_hq.php"], capture_output=True, text=True)
print(res.stdout[:3000])
