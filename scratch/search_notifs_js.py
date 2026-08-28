filepath = r'c:\xampp\htdocs\CAP101\PC\app-features.js'

with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
    lines = f.readlines()

for idx, line in enumerate(lines):
    if 'notif' in line.lower() or 'bell' in line.lower():
        print(f"L{idx+1}: {line.strip()[:100]}")
