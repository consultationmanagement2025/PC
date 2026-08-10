import os

with open(r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php', 'r', encoding='utf-8') as f:
    code = f.read()

# Add status tracker inside inline-input-modal
old_modal_header = '<p class="text-xs text-slate-300">Annotating master document directly. Contributions are logged into the audit trail.</p>'
new_modal_header = """<p class="text-xs text-slate-300">Annotating master document directly. Contributions are logged into the audit trail.</p>
                </div>
                <button onclick="closeInlineInputModal()" class="text-white/80 hover:text-white text-2xl font-bold leading-none">&times;</button>
            </div>

            <!-- Unified Status Tracker Progress Bar -->
            <div class="bg-slate-100 px-6 py-3 border-b border-slate-200">
                <div class="flex items-center justify-between text-[11px] font-bold max-w-2xl mx-auto">
                    <div class="flex items-center gap-1.5 text-emerald-700">
                        <span class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px]"><i class="bi bi-check"></i></span>
                        <span>1. Intake Received</span>
                    </div>
                    <div class="h-0.5 flex-1 bg-emerald-500 mx-2"></div>
                    <div class="flex items-center gap-1.5 text-emerald-700">
                        <span class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px]"><i class="bi bi-check"></i></span>
                        <span>2. AI Analyzed</span>
                    </div>
                    <div class="h-0.5 flex-1 bg-amber-500 mx-2"></div>
                    <div class="flex items-center gap-1.5 text-amber-800">
                        <span class="w-5 h-5 rounded-full bg-amber-500 text-white flex items-center justify-center text-[10px]"><i class="bi bi-pencil-fill"></i></span>
                        <span>3. Expert Advisory Input</span>
                    </div>
                    <div class="h-0.5 flex-1 bg-slate-300 mx-2"></div>
                    <div class="flex items-center gap-1.5 text-slate-400">
                        <span class="w-5 h-5 rounded-full bg-slate-300 text-slate-600 flex items-center justify-center text-[10px]">4</span>
                        <span>4. Admin Validation</span>
                    </div>
                    <div class="h-0.5 flex-1 bg-slate-300 mx-2"></div>
                    <div class="flex items-center gap-1.5 text-slate-400">
                        <span class="w-5 h-5 rounded-full bg-slate-300 text-slate-600 flex items-center justify-center text-[10px]">5</span>
                        <span>5. Committee Forward</span>
                    </div>
                </div>
            </div>"""

if old_modal_header in code:
    code = code.replace(old_modal_header + '\n                </div>\n                <button onclick="closeInlineInputModal()" class="text-white/80 hover:text-white text-2xl font-bold leading-none">&times;</button>\n            </div>', new_modal_header)

with open(r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php', 'w', encoding='utf-8') as f:
    f.write(code)

print("Status tracker bar added to resource_person_dashboard.php!")
