import os, glob

print("=== BUMPING SCRIPT CACHE-BUSTER TO V1000 ACROSS ALL PHP FILES ===")

php_files = glob.glob(r'c:\xampp\htdocs\CAP101\PC\**\*.php', recursive=True)

for path in php_files:
    if os.path.exists(path):
        with open(path, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()

        new_content = content.replace('build=2026_PCMS_V999', 'build=2026_PCMS_V1000_REPORT_BUILDER')
        new_content = new_content.replace('src="app-features.js"', 'src="app-features.js?v=1000"')
        new_content = new_content.replace('src="script.js"', 'src="script.js?v=1000"')

        if new_content != content:
            with open(path, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print(f"Updated cache buster in {path}")

print("Cache buster update complete!")
