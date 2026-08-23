with open(r'c:\xampp\htdocs\CAP101\PC\app-features.js', 'r', encoding='utf-8', errors='ignore') as f:
    lines = f.readlines()

for idx, line in enumerate(lines):
    if any(k in line.lower() for k in ['notif', 'alert', 'toast', 'banner', 'message', 'pop']):
        if 'function' in line:
            print(f"Line {idx+1}: {line.strip()[:100]}")
