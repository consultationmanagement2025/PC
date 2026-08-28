import os

app_features_files = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

old_card_block = """                <div class="bg-white rounded-xl border border-purple-200 p-4 shadow-sm bg-purple-50/30">
                    <div class="flex items-center justify-between text-purple-700 text-xs font-bold uppercase">
                        <span>Committee Forwarded</span>
                        <i class="bi bi-diagram-3 text-purple-500 text-lg"></i>
                    </div>
                    <p id="pfq-analytics-forwarded" class="text-3xl font-extrabold text-purple-600 mt-2">0</p>
                    <p class="text-[11px] text-purple-600/80 mt-1">Routed to LGU departments</p>
                </div>"""

new_card_block = """                <div class="bg-white rounded-xl border border-purple-200 p-4 shadow-sm bg-purple-50/30">
                    <div class="flex items-center justify-between text-purple-700 text-xs font-bold uppercase">
                        <span>ORTS Forwarded</span>
                        <i class="bi bi-box-arrow-up-right text-purple-500 text-lg"></i>
                    </div>
                    <p id="pfq-analytics-forwarded" class="text-3xl font-extrabold text-purple-600 mt-2">0</p>
                    <p class="text-[11px] text-purple-600/80 mt-1">Forwarded to ORTS System</p>
                </div>"""

print("=== REPLACING COMMITTEE FORWARDED CARD WITH ORTS FORWARDED CARD ===")
for fpath in app_features_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    if old_card_block in code:
        code = code.replace(old_card_block, new_card_block)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated card in:", fpath)
    else:
        print("Pattern not found or already updated in:", fpath)

print("Finished updating metric cards!")
