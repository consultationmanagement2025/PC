import os

app_features_files = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

old_btn = '<button onclick="showAwaitingExpertReviewNotice(${brief.consultation_id || 0})" class="px-5 py-2 bg-slate-300 hover:bg-slate-400 text-slate-700 font-extrabold rounded-xl text-xs transition flex items-center gap-2 cursor-not-allowed" title="Requires Resource Person (Expert) annotation before forwarding">\n                            <i class="bi bi-shield-lock-fill"></i> Awaiting Resource Person Verification\n                        </button>'

new_btn = '<div class="flex items-center gap-2 flex-wrap">\n                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 text-amber-900 border border-amber-200 rounded-xl text-[11px] font-bold shadow-2xs">\n                                <i class="bi bi-clock-history text-amber-600"></i> Awaiting Resource Person Input\n                            </span>\n                            <button onclick="document.getElementById(\'pfq-ai-brief-modal\')?.remove(); pfpShowForwardModal(${brief.consultation_id || 0});" class="px-4 py-2 bg-amber-700 hover:bg-amber-800 text-white font-extrabold rounded-xl text-xs transition shadow-md flex items-center gap-1.5 cursor-pointer" title="Dispatch consultation & AI summary to Resource Person">\n                                <i class="bi bi-send-fill text-amber-200"></i> Dispatch to Resource Person\n                            </button>\n                        </div>'

print("=== REPLACING AWAITING BUTTON IN APP-FEATURES.JS FILES ===")

for fpath in app_features_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    if old_btn in code:
        code = code.replace(old_btn, new_btn)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Successfully replaced awaiting button in:", fpath)
    else:
        # Line-by-line fallback replacement
        target_str = "Awaiting Resource Person Verification"
        if target_str in code:
            start_idx = code.find('<button onclick="showAwaitingExpertReviewNotice')
            end_idx = code.find('</button>', start_idx) + 9
            if start_idx != -1 and end_idx != -1:
                code = code[:start_idx] + new_btn + code[end_idx:]
                with open(fpath, 'w', encoding='utf-8') as f:
                    f.write(code)
                print("Replaced awaiting button via slice index in:", fpath)

print("Finished replacing buttons!")
