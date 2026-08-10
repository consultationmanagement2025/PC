import os

filepath = r'c:\xampp\htdocs\CAP101\PC\app-features.js'
with open(filepath, 'r', encoding='utf-8') as f:
    code = f.read()

idx = code.find("fetch('API/resource_person_api.php?action=list_pending')")
if idx != -1:
    print(code[idx:idx+3500])
