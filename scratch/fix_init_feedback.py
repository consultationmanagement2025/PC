import os

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\DATABASE\feedback.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\DATABASE\feedback.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\feedback.php'
]

old_block = """    if ($conn->query($sql) === TRUE) {
        return true;
    } else {
        
    $cols = [];"""

new_block = """    @$conn->query($sql);
    
    $cols = [];"""

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

print("Done updating initializeFeedbackTable.")
