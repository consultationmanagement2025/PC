import os, re

filepath = r'c:\xampp\htdocs\CAP101\PC\system-template-full.php'
with open(filepath, 'r', encoding='utf-8') as f:
    code = f.read()

matches = [m.start() for m in re.finditer(r'Pending Applications', code, re.IGNORECASE)]
print("Found 'Pending Applications' in system-template-full.php at indices:", matches)

for idx in matches:
    print("--- SNIPPET ---")
    print(code[max(0, idx-200):min(len(code), idx+500)])

js_filepath = r'c:\xampp\htdocs\CAP101\PC\app-features.js'
with open(js_filepath, 'r', encoding='utf-8') as f:
    js_code = f.read()

js_matches = [m.start() for m in re.finditer(r'Pending Applications', js_code, re.IGNORECASE)]
print("Found 'Pending Applications' in app-features.js at indices:", js_matches)

for idx in js_matches:
    print("--- JS SNIPPET ---")
    print(js_code[max(0, idx-200):min(len(js_code), idx+500)])
