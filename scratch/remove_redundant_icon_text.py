import os

index_file = r'c:\xampp\htdocs\CAP101\PC\public\index.php'

with open(index_file, 'r', encoding='utf-8') as f:
    code = f.read()

# Replace top stats bar in public/index.php to remove redundant truncated text next to like/dislike icons
old_stats_bar = """                                        <div class="grid grid-cols-2 gap-2 text-xs font-bold">
                                            <div class="flex items-center justify-between min-w-0 bg-emerald-50/90 px-2.5 py-1.5 rounded-lg border border-emerald-200/70 shadow-2xs">
                                                <span class="text-emerald-800 truncate pr-1 flex items-center gap-1 font-extrabold" title="<?php echo htmlspecialchars($optA); ?>">
                                                    <i class="fa-solid fa-thumbs-up text-emerald-600 text-[11px] shrink-0"></i>
                                                    <span class="truncate"><?php echo htmlspecialchars($optA); ?></span>
                                                </span>
                                                <span class="text-emerald-700 bg-emerald-100/90 px-1.5 py-0.5 rounded text-[10px] font-black shrink-0" id="survey-pct-a-<?php echo $s['id']; ?>"><?php echo $pctA; ?>%</span>
                                            </div>

                                            <div class="flex items-center justify-between min-w-0 bg-rose-50/90 px-2.5 py-1.5 rounded-lg border border-rose-200/70 shadow-2xs">
                                                <span class="text-rose-800 truncate pr-1 flex items-center gap-1 font-extrabold" title="<?php echo htmlspecialchars($optB); ?>">
                                                    <i class="fa-solid fa-thumbs-down text-rose-600 text-[11px] shrink-0"></i>
                                                    <span class="truncate"><?php echo htmlspecialchars($optB); ?></span>
                                                </span>
                                                <span class="text-rose-700 bg-rose-100/90 px-1.5 py-0.5 rounded text-[10px] font-black shrink-0" id="survey-pct-b-<?php echo $s['id']; ?>"><?php echo $pctB; ?>%</span>
                                            </div>
                                        </div>"""

new_stats_bar = """                                        <div class="grid grid-cols-2 gap-2 text-xs font-bold">
                                            <div class="flex items-center justify-between min-w-0 bg-emerald-50/90 px-3 py-1.5 rounded-lg border border-emerald-200/70 shadow-2xs">
                                                <span class="text-emerald-800 flex items-center gap-1.5 font-extrabold" title="Support Rate">
                                                    <i class="fa-solid fa-thumbs-up text-emerald-600 text-xs shrink-0"></i>
                                                </span>
                                                <span class="text-emerald-700 bg-emerald-100/90 px-2 py-0.5 rounded-md text-[11px] font-black shrink-0" id="survey-pct-a-<?php echo $s['id']; ?>"><?php echo $pctA; ?>%</span>
                                            </div>

                                            <div class="flex items-center justify-between min-w-0 bg-rose-50/90 px-3 py-1.5 rounded-lg border border-rose-200/70 shadow-2xs">
                                                <span class="text-rose-800 flex items-center gap-1.5 font-extrabold" title="Oppose Rate">
                                                    <i class="fa-solid fa-thumbs-down text-rose-600 text-xs shrink-0"></i>
                                                </span>
                                                <span class="text-rose-700 bg-rose-100/90 px-2 py-0.5 rounded-md text-[11px] font-black shrink-0" id="survey-pct-b-<?php echo $s['id']; ?>"><?php echo $pctB; ?>%</span>
                                            </div>
                                        </div>"""

if old_stats_bar in code:
    code = code.replace(old_stats_bar, new_stats_bar)
    print("Cleaned top stats bar (removed redundant text next to like/dislike icons).")

# Also simplify bottom voting button text if needed
old_btn_a_text = '<span class="truncate"><?php echo $isA ? \'Voted: \' . htmlspecialchars($optA) : htmlspecialchars($optA); ?></span>'
new_btn_a_text = '<span class="truncate"><?php echo htmlspecialchars($optA); ?></span>'

old_btn_b_text = '<span class="truncate"><?php echo $isB ? \'Voted: \' . htmlspecialchars($optB) : htmlspecialchars($optB); ?></span>'
new_btn_b_text = '<span class="truncate"><?php echo htmlspecialchars($optB); ?></span>'

if old_btn_a_text in code:
    code = code.replace(old_btn_a_text, new_btn_a_text)
if old_btn_b_text in code:
    code = code.replace(old_btn_b_text, new_btn_b_text)

with open(index_file, 'w', encoding='utf-8') as f:
    f.write(code)

print("Finished cleaning up redundant icon text in public/index.php!")
