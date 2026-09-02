import subprocess

php_code = """<?php
$_GET['action'] = 'get_all_vote_stats';
require_once 'API/consultation_feedback.php';
?>"""

with open(r"c:\xampp\htdocs\CAP101\PC\scratch\call_vote_stats.php", "w") as f:
    f.write(php_code)

res = subprocess.run(["C:\\xampp\\php\\php.exe", r"c:\xampp\htdocs\CAP101\PC\scratch\call_vote_stats.php"], capture_output=True, text=True)
print(res.stdout[:2000])
