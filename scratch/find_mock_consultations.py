import sys
sys.stdout.reconfigure(encoding='utf-8')

with open(r'c:\xampp\htdocs\CAP101\PC\app-features.js', 'r', encoding='utf-8', errors='ignore') as f:
    lines = f.readlines()

for idx, line in enumerate(lines):
    if 'id: 8' in line or 'id:8' in line or 'CONSULT-000008' in line or 'CONSULT-8' in line or 'Aljan' in line:
        print(f"Line {idx+1}: {line.strip()}")
