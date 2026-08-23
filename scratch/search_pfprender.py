import sys
sys.stdout.reconfigure(encoding='utf-8')

with open(r'c:\xampp\htdocs\CAP101\PC\app-features.js', 'r', encoding='utf-8', errors='ignore') as f:
    for idx, line in enumerate(f):
        if 'pfprendercity' in line.lower() or 'pfprender' in line.lower():
            print(f"app-features.js:{idx+1}: {line.strip()[:100]}")
