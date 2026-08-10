import os

filepath = r'c:\xampp\htdocs\CAP101\PC\app-features.js'
with open(filepath, 'r', encoding='utf-8') as f:
    code = f.read()

idx = code.find("approveResourcePersonApp")
while idx != -1:
    print("--- MATCH AT", idx, "---")
    print(code[max(0, idx-100):min(len(code), idx+800)])
    idx = code.find("approveResourcePersonApp", idx + 1)
