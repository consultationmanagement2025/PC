import shutil
import os

main_file = r'c:\xampp\htdocs\CAP101\PC\app-features.js'
target_files = [
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

print(f"Syncing main app-features.js ({os.path.getsize(main_file)} bytes)...")
for target in target_files:
    if os.path.exists(os.path.dirname(target)):
        shutil.copy2(main_file, target)
        print(f"  OK: Copied to {target}")

print("All copies of app-features.js synchronized successfully!")
