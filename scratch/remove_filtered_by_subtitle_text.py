import os

rp_files = [
    r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\resource_person_dashboard.php'
]

target_text = '<p class="text-xs text-slate-500">Filtered by <strong><?php echo htmlspecialchars($expertise_areas); ?></strong> &bull; AI Analyzed & Admin Dispatched</p>'

print("=== REMOVING FILTERED BY SUBTITLE TEXT FROM RESOURCE PERSON DASHBOARD FILES ===")

for fpath in rp_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    if target_text in code:
        code = code.replace(target_text, '')
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Removed subtitle text from:", fpath)
    else:
        print("Text not found or already removed in:", fpath)

print("Finished removing text!")
