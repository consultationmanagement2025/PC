with open(r'c:\xampp\htdocs\CAP101\PC\app-features.js', 'r', encoding='utf-8', errors='ignore') as f:
    lines = f.readlines()

for idx, line in enumerate(lines):
    if 'Valenzuela Citizen' in line or 'Citizen' in line or 'resp.' in line or 'feedback.' in line:
        if any(term in line for term in ['name', 'author', 'guest_name', 'fullName', 'citizen_name']):
            print(f"Line {idx+1}: {line.strip()[:110]}")
