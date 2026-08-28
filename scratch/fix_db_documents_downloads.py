import os

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\DATABASE\documents.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\DATABASE\documents.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\documents.php'
]

old_snippet = """            0 as views,
            0 as downloads,"""

new_snippet = """            COALESCE(d.views, 0) as views,
            COALESCE(d.downloads, 0) as downloads,"""

for fpath in files_to_update:
    if not os.path.exists(fpath):
        print(f"Missing: {fpath}")
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        content = f.read()
    if old_snippet in content:
        content = content.replace(old_snippet, new_snippet)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Updated {fpath}")
    else:
        print(f"Pattern not found in {fpath}")

print("Done updating DATABASE/documents.php files.")
