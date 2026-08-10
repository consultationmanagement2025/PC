import os

filepath = r'c:\xampp\htdocs\CAP101\PC\app-features.js'
with open(filepath, 'r', encoding='utf-8') as f:
    code = f.read()

idx = code.find("toggleNotification")
while idx != -1:
    print("=== APP-FEATURES MATCH AT", idx, "===")
    print(code[max(0, idx-100):min(len(code), idx+1000)])
    idx = code.find("toggleNotification", idx + 1)

rp_filepath = r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php'
with open(rp_filepath, 'r', encoding='utf-8') as f:
    rp_code = f.read()

rp_idx = rp_code.find("toggleNotification")
while rp_idx != -1:
    print("=== RESOURCE PERSON MATCH AT", rp_idx, "===")
    print(rp_code[max(0, rp_idx-100):min(len(rp_code), rp_idx+1000)])
    rp_idx = rp_code.find("toggleNotification", rp_idx + 1)
