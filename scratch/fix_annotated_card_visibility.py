import os

rp_files = [
    r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\resource_person_dashboard.php'
]

print("=== UPDATING ANNOTATED CARD VISIBILITY IN RESOURCE PERSON DASHBOARD ===")

for fpath in rp_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    # 1. Update Card Container HTML rendering
    old_card_block = """                            <div class="task-card bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition flex flex-col justify-between p-6 space-y-4"
                                 data-assigned="<?php echo $cardTag; ?>"
                                 data-title="<?php echo htmlspecialchars(strtolower($c['title'])); ?>"
                                 data-category="<?php echo htmlspecialchars(strtolower($category)); ?>">

                                <div class="space-y-3">
                                    <!-- Top Header Badges -->
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                            <i class="bi bi-tag-fill text-red-600 mr-1"></i><?php echo $category; ?>
                                        </span>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-mono font-bold bg-slate-800 text-white flex items-center gap-1">
                                            <i class="bi bi-file-earmark-code text-amber-300"></i> Master Doc <?php echo $docVersion; ?>
                                        </span>
                                    </div>"""

    new_card_block = """                            <div class="task-card bg-white rounded-2xl border <?php echo $hasExpertNotes ? 'border-emerald-400 shadow-md ring-2 ring-emerald-400/20' : 'border-slate-200 shadow-sm'; ?> hover:shadow-md transition flex flex-col justify-between p-6 space-y-4"
                                 data-assigned="<?php echo $cardTag; ?>"
                                 data-title="<?php echo htmlspecialchars(strtolower($c['title'])); ?>"
                                 data-category="<?php echo htmlspecialchars(strtolower($category)); ?>">

                                <div class="space-y-3">
                                    <!-- Top Header Badges -->
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                            <i class="bi bi-tag-fill text-red-600 mr-1"></i><?php echo $category; ?>
                                        </span>
                                        <?php if ($hasExpertNotes): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-mono font-bold bg-emerald-700 text-white flex items-center gap-1 border border-emerald-500 shadow-2xs" title="Master Document Annotated">
                                                <i class="bi bi-check-circle-fill text-emerald-300"></i> Master Doc <?php echo $docVersion; ?> (Annotated)
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-mono font-bold bg-slate-800 text-white flex items-center gap-1">
                                                <i class="bi bi-file-earmark-code text-amber-300"></i> Master Doc <?php echo $docVersion; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>"""

    if old_card_block in code:
        code = code.replace(old_card_block, new_card_block)

    # 2. Update Status Pill
    old_status_pill = """                                        <?php if ($hasExpertNotes): ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 flex items-center gap-1">
                                                <i class="bi bi-check-circle-fill"></i> Notes Appended
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 flex items-center gap-1">
                                                <i class="bi bi-pencil"></i> Needs Input
                                            </span>
                                        <?php endif; ?>"""

    new_status_pill = """                                        <?php if ($hasExpertNotes): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1 shadow-2xs">
                                                <i class="bi bi-check-circle-fill text-emerald-600"></i> Expert Annotated
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300 flex items-center gap-1">
                                                <i class="bi bi-pencil-fill text-amber-600"></i> Needs Input
                                            </span>
                                        <?php endif; ?>"""

    if old_status_pill in code:
        code = code.replace(old_status_pill, new_status_pill)

    # 3. Update Action Button
    old_action_btn = """                                        <button onclick="openInlineInputModal(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['title'])); ?>', '<?php echo $docVersion; ?>')" 
                                                class="w-full bg-gradient-to-r from-red-800 to-red-900 hover:from-red-900 hover:to-black text-white font-extrabold py-2.5 px-3 rounded-xl text-xs transition flex items-center justify-center gap-2 shadow-sm cursor-pointer">
                                            <i class="bi bi-pencil-square text-amber-300"></i> Annotate Master Document
                                        </button>"""

    new_action_btn = """                                        <?php if ($hasExpertNotes): ?>
                                            <button onclick="openInlineInputModal(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['title'])); ?>', '<?php echo $docVersion; ?>')" 
                                                    class="w-full bg-gradient-to-r from-emerald-800 to-emerald-900 hover:from-emerald-900 hover:to-black text-white font-extrabold py-2.5 px-3 rounded-xl text-xs transition flex items-center justify-center gap-2 shadow-sm cursor-pointer border border-emerald-700">
                                                <i class="bi bi-pencil-square text-emerald-300"></i> Edit / Update Annotations (<?php echo $docVersion; ?>)
                                            </button>
                                        <?php else: ?>
                                            <button onclick="openInlineInputModal(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['title'])); ?>', '<?php echo $docVersion; ?>')" 
                                                    class="w-full bg-gradient-to-r from-red-800 to-red-900 hover:from-red-900 hover:to-black text-white font-extrabold py-2.5 px-3 rounded-xl text-xs transition flex items-center justify-center gap-2 shadow-sm cursor-pointer">
                                                <i class="bi bi-pencil-square text-amber-300"></i> Annotate Master Document
                                            </button>
                                        <?php endif; ?>"""

    if old_action_btn in code:
        code = code.replace(old_action_btn, new_action_btn)

    # 4. Update JS save handler to reload on success
    old_js_save = """            if (data.success) {
                closeInlineInputModal();
                showMasterDocSuccessModal(data.version, data.message);
            } else {"""

    new_js_save = """            if (data.success) {
                closeInlineInputModal();
                if (typeof showNotification === 'function') {
                    showNotification('Master Document annotated successfully (' + (data.version || 'v1.1') + ')', 'success');
                }
                setTimeout(function() { location.reload(); }, 600);
            } else {"""

    if old_js_save in code:
        code = code.replace(old_js_save, new_js_save)

    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated card visibility in:", fpath)

print("Finished updating Resource Person Dashboard files!")
