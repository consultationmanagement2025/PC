import os, re

files = [
    r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\API\resource_person_api.php'
]

for filepath in files:
    if not os.path.exists(filepath):
        continue
    with open(filepath, 'r', encoding='utf-8') as f:
        code = f.read()

    matches = [m.start() for m in re.finditer(r'Livelihood', code, re.IGNORECASE)]
    print(f"File {filepath} has matches:", matches)
    for idx in matches[:5]:
        print("--- SNIPPET ---")
        print(code[max(0, idx-100):min(len(code), idx+400)])
