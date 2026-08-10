import os

with open(r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php', 'r', encoding='utf-8') as f:
    code = f.read()

# Replace paths for admin subdirectories
admin_code = code.replace("require_once 'db.php';", "require_once '../db.php';")
admin_code = admin_code.replace("require_once 'UTILS/session_check.php';", "require_once '../UTILS/session_check.php';")
admin_code = admin_code.replace('src="images/', 'src="../images/')
admin_code = admin_code.replace('href="ASSETS/', 'href="../ASSETS/')
admin_code = admin_code.replace('action="API/', 'action="../API/')
admin_code = admin_code.replace('fetch(`API/', 'fetch(`../API/')
admin_code = admin_code.replace("fetch('API/", "fetch('../API/")
admin_code = admin_code.replace('href="uploads/', 'href="../uploads/')
admin_code = admin_code.replace('href="login.php"', 'href="../login.php"')
admin_code = admin_code.replace('href="logout.php"', 'href="../logout.php"')
admin_code = admin_code.replace('href="index.php"', 'href="../index.php"')

with open(r'c:\xampp\htdocs\CAP101\PC\admin\resource_person_dashboard.php', 'w', encoding='utf-8') as f:
    f.write(admin_code)

with open(r'c:\xampp\htdocs\CAP101\PC\admin-side\resource_person_dashboard.php', 'w', encoding='utf-8') as f:
    f.write(admin_code)

print("Synchronized admin/resource_person_dashboard.php and admin-side/resource_person_dashboard.php successfully!")
