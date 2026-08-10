import os

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\DATABASE\user-logs.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\DATABASE\user-logs.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\user-logs.php'
]

for fpath in files_to_update:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    if "$types = 'isssssssss';" in code:
        code = code.replace("$types = 'isssssssss';", "$types = 'issssisssss';")
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Fixed bind_param types in:", fpath)
    else:
        print("Target string not found in:", fpath)
