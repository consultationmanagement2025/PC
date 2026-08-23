import os, sys

sys.stdout.reconfigure(encoding='utf-8')

root_dir = r'c:\xampp\htdocs\CAP101\PC'

print("Searching whole directory for 'aljan'...")
found = 0
for dirpath, dirnames, filenames in os.walk(root_dir):
    for f in filenames:
        filepath = os.path.join(dirpath, f)
        try:
            with open(filepath, 'r', encoding='utf-8', errors='ignore') as fp:
                content = fp.read()
                if 'aljan' in content.lower():
                    print(f"FOUND IN FILE: {filepath}")
                    found += 1
        except Exception as e:
            pass

print(f"Total files containing 'aljan': {found}")
