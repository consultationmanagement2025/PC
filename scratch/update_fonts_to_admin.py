import os, re

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\resource_person_dashboard.php'
]

old_font_block = """    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .bg-valenzuela-red { background-color: #800000; }
        .text-valenzuela-red { color: #800000; }
    </style>"""

new_font_block = """    <!-- Google Fonts: Plus Jakarta Sans & Inter (Admin Side Font Family) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body, button, input, select, textarea, table, th, td {
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .bg-valenzuela-red { background-color: #800000; }
        .text-valenzuela-red { color: #800000; }
    </style>"""

for filepath in files_to_update:
    if not os.path.exists(filepath):
        continue
    with open(filepath, 'r', encoding='utf-8') as f:
        code = f.read()

    if old_font_block in code:
        code = code.replace(old_font_block, new_font_block)
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated fonts to Plus Jakarta Sans & Inter in:", filepath)
    else:
        # Generic regex replacement for font link and style
        code = re.sub(
            r'<link href="https:\/\/fonts\.googleapis\.com\/css2\?family=Poppins.*?<\/style>',
            new_font_block,
            code,
            flags=re.DOTALL
        )
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Regex updated fonts in:", filepath)
