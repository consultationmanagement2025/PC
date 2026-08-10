import os, re

filepath = r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php'
with open(filepath, 'r', encoding='utf-8') as f:
    code = f.read()

matches = [m.start() for m in re.finditer(r'alert\(', code)]
print("Total alert calls:", len(matches))
for i, idx in enumerate(matches):
    print(f"--- ALERT #{i+1} AT INDEX {idx} ---")
    snippet = code[max(0, idx-100):min(len(code), idx+300)]
    print(snippet.encode('ascii', 'ignore').decode('ascii'))
