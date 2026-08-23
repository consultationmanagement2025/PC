import os, sys, re

sys.stdout.reconfigure(encoding='utf-8')

root_dir = r'c:\xampp\htdocs\CAP101\PC'

for dirpath, dirnames, filenames in os.walk(root_dir):
    # skip scratch
    if 'scratch' in dirpath: continue
    for f in filenames:
        if f.endswith('.php') or f.endswith('.js'):
            filepath = os.path.join(dirpath, f)
            with open(filepath, 'r', encoding='utf-8', errors='ignore') as fp:
                for idx, line in enumerate(fp):
                    if 'decline' in line.lower() and ('button' in line.lower() or 'onclick' in line.lower() or 'action' in line.lower() or 'option' in line.lower()):
                        rel = os.path.relpath(filepath, root_dir)
                        print(f"{rel}:{idx+1}: {line.strip()[:120]}")
