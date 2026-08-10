import os, re

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\resource_person_dashboard.php'
]

pattern = r'\s*<p class="text-\[11px\] text-red-200\/90 pt-1">\s*<i class="bi bi-check-circle-fill text-emerald-400 mr-1"><\/i>\s*Showing only consultations matching your registered expertise.*?<\/p>'

for filepath in files_to_update:
    if not os.path.exists(filepath):
        continue
    with open(filepath, 'r', encoding='utf-8') as f:
        code = f.read()

    new_code = re.sub(pattern, '', code, flags=re.DOTALL)
    if new_code != code:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_code)
        print("Removed text line from:", filepath)
    else:
        print("Pattern not found in:", filepath)
