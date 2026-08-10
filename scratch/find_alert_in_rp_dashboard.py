import os, re

filepath = r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php'
with open(filepath, 'r', encoding='utf-8') as f:
    code = f.read()

matches = [m.start() for m in re.finditer(r'alert\(', code)]
print("Found alert( in resource_person_dashboard.php at indices:", matches)

for idx in matches:
    print("=== MATCH AT", idx, "===")
    snippet = code[max(0, idx-200):min(len(code), idx+500)]
    print(snippet.encode('ascii', 'ignore').decode('ascii'))
