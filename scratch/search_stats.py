import re

filepath = r'c:\xampp\htdocs\CAP101\PC\app-features.js'

with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
    lines = f.readlines()

print("File line count:", len(lines))

for idx, line in enumerate(lines):
    if 'pfq-stat-total' in line or 'pfpRenderTable' in line or 'loadPhmsFeedbackFromApi' in line or 'renderPublicConsultation' in line:
        print(f"L{idx+1}: {line.strip()[:100]}")
