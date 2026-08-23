import sys
sys.stdout.reconfigure(encoding='utf-8')

with open(r'c:\xampp\htdocs\CAP101\PC\app-features.js', 'r', encoding='utf-8', errors='ignore') as f:
    for idx, line in enumerate(f):
        if 'consultations' in line.lower() and ('8' in line or 'aljan' in line.lower() or 'test' in line.lower() or 'mock' in line.lower() or 'id' in line.lower()):
            if 'appdata' in line.lower() or 'initial' in line.lower() or 'const ' in line.lower() or 'let ' in line.lower():
                print(f"app-features.js:{idx+1}: {line.strip()[:110]}")
