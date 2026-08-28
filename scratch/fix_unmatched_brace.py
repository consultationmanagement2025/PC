import os

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\DATABASE\feedback.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\DATABASE\feedback.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\feedback.php'
]

old_block = """    if (!in_array('tracking_token', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN tracking_token VARCHAR(64) DEFAULT NULL");

        return true;
    }
}"""

new_block = """    if (!in_array('tracking_token', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN tracking_token VARCHAR(64) DEFAULT NULL");

    return true;
}"""

for fpath in files_to_update:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        content = f.read()
    if old_block in content:
        content = content.replace(old_block, new_block)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Updated {fpath}")
    else:
        print(f"Pattern not found in {fpath}")

print("Done fixing extra closing brace in DATABASE/feedback.php.")
