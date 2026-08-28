import os

app_features_files = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

print("=== UPDATING AWAITING VERIFICATION BUTTON IN APP-FEATURES.JS FILES ===")

old_footer_block = """                    ${Boolean(brief.is_expert_checked || brief.document_status === 'expert_annotated' || brief.document_status === 'approved' || brief.document_status === 'endorsed') ? `
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-[11px] font-bold shadow-2xs">
                                <i class="bi bi-patch-check-fill text-emerald-600"></i> Validated by Resource Person
                            </span>
                            <button onclick="pfpForwardBriefToOrts(${brief.consultation_id || 0}, '${escapeHtml(brief.title || '').replace(/'/g, "\\'")}')" class="px-5 py-2 bg-indigo-700 hover:bg-indigo-800 text-white font-extrabold rounded-xl text-xs transition shadow-md flex items-center gap-2 cursor-pointer" title="Transmit to ORTS (Ordinance Routing & Tracking System)">
                                <i class="bi bi-send-check-fill"></i> Forward to ORTS
                            </button>
                        </div>
                    ` : `
                        <button onclick="showAwaitingExpertReviewNotice(${brief.consultation_id || 0})" class="px-5 py-2 bg-slate-300 hover:bg-slate-400 text-slate-700 font-extrabold rounded-xl text-xs transition flex items-center gap-2 cursor-not-allowed" title="Requires Resource Person (Expert) annotation before forwarding">
                            <i class="bi bi-shield-lock-fill"></i> Awaiting Resource Person Verification
                        </button>
                    `}"""

new_footer_block = """                    ${Boolean(brief.is_expert_checked || brief.document_status === 'expert_annotated' || brief.document_status === 'approved' || brief.document_status === 'endorsed') ? `
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-[11px] font-bold shadow-2xs">
                                <i class="bi bi-patch-check-fill text-emerald-600"></i> Validated by Resource Person
                            </span>
                            <button onclick="pfpForwardBriefToOrts(${brief.consultation_id || 0}, '${escapeHtml(brief.title || '').replace(/'/g, "\\'")}')" class="px-5 py-2 bg-indigo-700 hover:bg-indigo-800 text-white font-extrabold rounded-xl text-xs transition shadow-md flex items-center gap-2 cursor-pointer" title="Transmit to ORTS (Ordinance Routing & Tracking System)">
                                <i class="bi bi-send-check-fill"></i> Forward to ORTS
                            </button>
                        </div>
                    ` : `
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 text-amber-900 border border-amber-200 rounded-xl text-[11px] font-bold shadow-2xs">
                                <i class="bi bi-clock-history text-amber-600"></i> Awaiting Resource Person Input
                            </span>
                            <button onclick="document.getElementById('pfq-ai-brief-modal')?.remove(); pfpShowForwardModal(${brief.consultation_id || 0});" class="px-4 py-2 bg-amber-700 hover:bg-amber-800 text-white font-extrabold rounded-xl text-xs transition shadow-md flex items-center gap-1.5 cursor-pointer" title="Dispatch consultation & AI summary to Resource Person">
                                <i class="bi bi-send-fill text-amber-200"></i> Dispatch to Resource Person
                            </button>
                        </div>
                    `}"""

for fpath in app_features_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    if old_footer_block in code:
        code = code.replace(old_footer_block, new_footer_block)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated awaiting verification button in:", fpath)
    else:
        print("Pattern not found or already updated in:", fpath)

print("Finished updating app-features.js files!")
