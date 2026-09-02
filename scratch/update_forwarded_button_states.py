import os
import re
import subprocess

js_files = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
]

new_brief_footer_code = """                    ${(function() {
                        const isEndorsed = Boolean(brief.is_expert_checked || brief.document_status === 'expert_annotated' || brief.document_status === 'approved' || brief.document_status === 'endorsed');
                        const isForwardedToRp = Boolean(brief.forwarded_to_expert || brief.document_status === 'sent_to_expert' || brief.assigned_to);

                        if (isEndorsed) {
                            return `
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-[11px] font-bold shadow-2xs">
                                        <i class="bi bi-patch-check-fill text-emerald-600"></i> Validated by Resource Person
                                    </span>
                                    <button onclick="pfpForwardBriefToOrts(${brief.consultation_id || 0}, '${escapeHtml(brief.title || '').replace(/'/g, "\\\\'")}')" class="px-5 py-2 bg-indigo-700 hover:bg-indigo-800 text-white font-extrabold rounded-xl text-xs transition shadow-md flex items-center gap-2 cursor-pointer" title="Transmit to ORTS (Ordinance Routing & Tracking System)">
                                        <i class="bi bi-send-check-fill"></i> Forward to ORTS
                                    </button>
                                </div>
                            `;
                        } else if (isForwardedToRp) {
                            return `
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-purple-50 text-purple-900 border border-purple-200 rounded-xl text-[11px] font-bold shadow-2xs">
                                        <i class="bi bi-check-circle-fill text-purple-600"></i> Dispatched to Resource Person
                                    </span>
                                    <button onclick="document.getElementById('pfq-ai-brief-modal')?.remove(); pfpShowForwardModal(${brief.consultation_id || 0});" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition border border-slate-300 flex items-center gap-1 cursor-pointer" title="Re-assign to another Resource Person">
                                        <i class="bi bi-arrow-repeat"></i> Re-Assign Expert
                                    </button>
                                    <button onclick="pfpForwardBriefToOrts(${brief.consultation_id || 0}, '${escapeHtml(brief.title || '').replace(/'/g, "\\\\'")}')" class="px-4 py-2 bg-indigo-700 hover:bg-indigo-800 text-white font-extrabold rounded-xl text-xs transition shadow-md flex items-center gap-2 cursor-pointer" title="Transmit directly to ORTS">
                                        <i class="bi bi-send-check-fill"></i> Forward to ORTS
                                    </button>
                                </div>
                            `;
                        } else {
                            return `
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 text-amber-900 border border-amber-200 rounded-xl text-[11px] font-bold shadow-2xs">
                                        <i class="bi bi-clock-history text-amber-600"></i> Awaiting Resource Person Assignment
                                    </span>
                                    <button onclick="document.getElementById('pfq-ai-brief-modal')?.remove(); pfpShowForwardModal(${brief.consultation_id || 0});" class="px-4 py-2 bg-amber-700 hover:bg-amber-800 text-white font-extrabold rounded-xl text-xs transition shadow-md flex items-center gap-1.5 cursor-pointer" title="Dispatch consultation & AI summary to Resource Person">
                                        <i class="bi bi-send-fill text-amber-200"></i> Dispatch to Resource Person
                                    </button>
                                </div>
                            `;
                        }
                    })()}"""

for js_path in js_files:
    if not os.path.exists(js_path):
        continue
    with open(js_path, 'r', encoding='utf-8') as f:
        c = f.read()

    old_footer_pattern = r"\$\{Boolean\(brief\.is_expert_checked[\s\S]*?\)\}\s*\}"
    c = re.sub(old_footer_pattern, lambda m: new_brief_footer_code, c)

    with open(js_path, 'w', encoding='utf-8') as f:
        f.write(c)
    print(f"Updated AI brief modal button states in {js_path}")

    res = subprocess.run(["node", "-c", js_path], capture_output=True, text=True)
    if res.returncode == 0:
        print(f"  Node syntax check PASSED: {os.path.basename(js_path)}")
    else:
        print(f"  Node syntax ERROR in {os.path.basename(js_path)}:\n{res.stderr}")

print("Done updating forward button states!")
