import re

filepath = r'c:\xampp\htdocs\CAP101\PC\app-features.js'

with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
    lines = f.readlines()

for idx, line in enumerate(lines):
    if re.search(r'function\s+load\w+', line) or re.search(r'async\s+function\s+load\w+', line):
        print(f"L{idx+1}: {line.strip()[:100]}")
