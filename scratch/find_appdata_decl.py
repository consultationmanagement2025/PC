import sys
sys.stdout.reconfigure(encoding='utf-8')

with open(r'c:\xampp\htdocs\CAP101\PC\app-features.js', 'r', encoding='utf-8', errors='ignore') as f:
    for idx, line in enumerate(f):
        if 'appdata =' in line.lower() or 'appdata=' in line.lower() or 'window.appdata' in line.lower() or 'var appdata' in line.lower() or 'let appdata' in line.lower() or 'const appdata' in line.lower():
            print(f"Line {idx+1}: {line.strip()[:120]}")
