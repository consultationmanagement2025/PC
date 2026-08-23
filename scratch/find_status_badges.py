with open(r'c:\xampp\htdocs\CAP101\PC\app-features.js', 'r', encoding='utf-8', errors='ignore') as f:
    lines = f.readlines()

for idx, line in enumerate(lines):
    if 'status' in line and ('border' in line or 'bg-' in line or 'rounded' in line):
        if 'span' in line or 'div' in line:
            print(f"Line {idx+1}: {line.strip()[:110]}")
