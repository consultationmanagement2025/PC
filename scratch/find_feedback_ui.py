with open(r'c:\xampp\htdocs\CAP101\PC\app-features.js', 'r', encoding='utf-8', errors='ignore') as f:
    lines = f.readlines()

for idx, line in enumerate(lines):
    if 'feedback' in line.lower() and ('function' in line or 'modal' in line or 'button' in line):
        print(f"Line {idx+1}: {line.strip()[:100]}")
