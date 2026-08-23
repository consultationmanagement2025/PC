import os, sys

sys.stdout.reconfigure(encoding='utf-8')

root_dir = r'c:\xampp\htdocs\CAP101\PC'

for dirpath, dirnames, filenames in os.walk(root_dir):
    if 'scratch' in dirpath: continue
    for f in filenames:
        if f.endswith('.php') or f.endswith('.js') or f.endswith('.json') or f.endswith('.sql'):
            filepath = os.path.join(dirpath, f)
            with open(filepath, 'r', encoding='utf-8', errors='ignore') as fp:
                for idx, line in enumerate(fp):
                    if 'aljan' in line.lower() or '000008' in line.lower():
                        rel = os.path.relpath(filepath, root_dir)
                        print(f"{rel}:{idx+1}: {line.strip()[:120]}")
