import os, re

filepath = r'c:\xampp\htdocs\CAP101\PC\app-features.js'
with open(filepath, 'r', encoding='utf-8') as f:
    code = f.read()

matches = [m.start() for m in re.finditer(r'Consultation created', code, re.IGNORECASE)]
print("Found 'Consultation created' in app-features.js at indices:", matches)

for idx in matches:
    print("--- MATCH AT", idx, "---")
    print(code[max(0, idx-200):min(len(code), idx+600)])
