import os

events_files = [
    r'c:\xampp\htdocs\CAP101\PC\API\v1\events.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\API\v1\events.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\API\v1\events.php'
]

for fpath in events_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    if 'lgu2_inbox_record(' in code:
        code = code.replace('lgu2_inbox_record(', 'lgu2_store_inbox(')
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Fixed lgu2_inbox_record() call in:", fpath)
    else:
        print("Function call already correct or not found in:", fpath)
