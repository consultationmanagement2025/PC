import re

filepath = r'c:\xampp\htdocs\CAP101\PC\app-features.js'

with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
    lines = f.readlines()

for idx, line in enumerate(lines):
    if 'showSection' in line or 'pfpRender' in line or 'Public Feedback' in line:
        print(f"L{idx+1}: {line.strip()[:100]}")
