import os, re

filepath = r'c:\xampp\htdocs\CAP101\PC\app-features.js'
with open(filepath, 'r', encoding='utf-8') as f:
    code = f.read()

idx = code.find("async function saveConsultation")
if idx != -1:
    print("=== SAVE CONSULTATION FETCH ===")
    print(code[idx+2500:idx+4500])
