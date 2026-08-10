import os

with open(r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php', 'r', encoding='utf-8') as f:
    code = f.read()

# Replace the 5-stage queue tracker in modal with LRMS Document Archival Tracker
old_tracker = """            <!-- Unified Status Tracker Progress Bar -->
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

new_tracker = """            <!-- LRMS Master Document Archival Tracker Bar -->
            <div class="bg-slate-900 text-white px-6 py-3 border-b border-red-800">
                <div class="flex items-center justify-between text-[11px] font-bold max-w-3xl mx-auto">
                    <div class="flex items-center gap-1.5 text-emerald-400">
                        <span class="w-5 h-5 rounded-full bg-emerald-500 text-slate-950 flex items-center justify-center text-[10px]"><i class="bi bi-check-lg"></i></span>
                        <span>1. Master Draft (v1.0)</span>
                    </div>
                    <div class="h-0.5 flex-1 bg-emerald-500 mx-2"></div>
                    <div class="flex items-center gap-1.5 text-amber-300">
                        <span class="w-5 h-5 rounded-full bg-amber-400 text-slate-950 flex items-center justify-center text-[10px]"><i class="bi bi-pencil-fill"></i></span>
                        <span>2. Expert Annotated (v1.1)</span>
                    </div>
                    <div class="h-0.5 flex-1 bg-slate-700 mx-2"></div>
                    <div class="flex items-center gap-1.5 text-slate-400">
                        <span class="w-5 h-5 rounded-full bg-slate-700 text-slate-300 flex items-center justify-center text-[10px]">3</span>
                        <span>3. Secretariat Validated (v2.0)</span>
                    </div>
                    <div class="h-0.5 flex-1 bg-slate-700 mx-2"></div>
                    <div class="flex items-center gap-1.5 text-slate-400">
                        <span class="w-5 h-5 rounded-full bg-slate-700 text-slate-300 flex items-center justify-center text-[10px]"><i class="bi bi-archive"></i></span>
                        <span>4. LRMS Transmitted & Archived</span>
                    </div>
                </div>
            </div>"""

if old_tracker in code:
    code = code.replace(old_tracker, new_tracker)

with open(r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php', 'w', encoding='utf-8') as f:
    f.write(code)

print("LRMS Document Archival Tracker added to resource_person_dashboard.php!")
