import os

paths = [
    r'c:\xampp\htdocs\shared\integration\common.php',
    r'c:\xampp\htdocs\CAP101\shared\integration\common.php',
    r'c:\xampp\htdocs\CAP101\PC\shared\integration\common.php',
    r'c:\xampp\htdocs\shared\common.php'
]

for p in paths:
    print(p, "=>", os.path.exists(p))
