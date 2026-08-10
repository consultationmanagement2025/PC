import os, re

files = [
    r'c:\xampp\htdocs\CAP101\PC\system-template-full.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\system-template-full.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\system-template-full.php'
]

modal_html = """
    <!-- FORWARD AI SUMMARY TO RESOURCE PERSON MODAL -->
    <div id="forward-expert-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 relative border border-slate-200 space-y-5">
            <button type="button" onclick="closeForwardToExpertModal()" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>

            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div class="w-12 h-12 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center text-2xl font-bold">
                    <i class="bi bi-send-check"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Forward AI Summary & Consultation</h3>
                    <p class="text-xs text-slate-500" id="forward-modal-consult-title">Consultation Title</p>
                </div>
            </div>

            <form id="forward-expert-form" onsubmit="handleForwardToExpertSubmit(event)" class="space-y-4">
                <input type="hidden" name="consultation_id" id="forward-consultation-id">

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Select Resource Person (Subject Matter Expert)</label>
                    <select name="resource_person_id" id="forward-expert-select" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs bg-white font-medium outline-none">
                        <option value="0">-- Auto-Dispatch to All Experts Matching Category --</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Review & Annotation Deadline</label>
                    <select name="deadline_days" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs bg-white font-medium outline-none">
                        <option value="3">3 Days (Urgent)</option>
                        <option value="7" selected>7 Days (Standard)</option>
                        <option value="14">14 Days (Extended Review)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Admin Instructions / Specific Focus Area</label>
                    <textarea name="instructions" rows="3" class="w-full p-3 border border-slate-300 rounded-xl text-xs bg-white focus:ring-2 focus:ring-red-500 outline-none" placeholder="Provide specific advisory instructions or policy questions for the expert..."></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeForwardToExpertModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 rounded-2xl font-bold text-xs transition">Cancel</button>
                    <button type="submit" id="forward-submit-btn" class="flex-1 bg-red-700 hover:bg-red-800 text-white py-3 rounded-2xl font-extrabold text-xs transition shadow-md flex items-center justify-center gap-1.5 cursor-pointer">
                        <i class="bi bi-send-fill"></i> Forward AI Summary
                    </button>
                </div>
            </form>
        </div>
    </div>
"""

js_code = """
        function openForwardToExpertModal(id, title, category) {
            document.getElementById('forward-consultation-id').value = id;
            document.getElementById('forward-modal-consult-title').textContent = title + ' (' + (category || 'General') + ')';
            
            const select = document.getElementById('forward-expert-select');
            select.innerHTML = '<option value="0">-- Auto-Dispatch to All Experts Matching Category (' + (category || 'General') + ') --</option>';

            fetch('API/resource_person_api.php?action=list_resource_persons')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data) {
                    data.data.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.fullname + ' (' + (p.expertise_areas || p.department || 'Expert') + ')';
                        select.appendChild(opt);
                    });
                }
            }).catch(e => console.error(e));

            document.getElementById('forward-expert-modal').classList.remove('hidden');
        }

        function closeForwardToExpertModal() {
            document.getElementById('forward-expert-modal').classList.add('hidden');
        }

        function handleForwardToExpertSubmit(e) {
            e.preventDefault();
            const form = document.getElementById('forward-expert-form');
            const formData = new FormData(form);
            const btn = document.getElementById('forward-submit-btn');

            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i> Forwarding...';

            fetch('API/forward_to_resource_person.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send-fill"></i> Forward AI Summary';
                if (data.success) {
                    alert('✅ ' + data.message);
                    closeForwardToExpertModal();
                    location.reload();
                } else {
                    alert('⚠️ ' + (data.message || 'Failed to forward to expert'));
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send-fill"></i> Forward AI Summary';
                alert('❌ Error: ' + err.message);
            });
        }
"""

for filepath in files:
    if not os.path.exists(filepath):
        continue
    with open(filepath, 'r', encoding='utf-8') as f:
        code = f.read()

    # Remove bad injection from PHP string area
    code = re.sub(r'\s*<!-- FORWARD AI SUMMARY TO RESOURCE PERSON MODAL -->.*?<\/script>\s*', '', code, flags=re.DOTALL)

    # Re-inject modal before </body> or near export modal
    if 'id="export-modal"' in code and 'id="forward-expert-modal"' not in code:
        code = code.replace('<div id="export-modal"', modal_html + '\n    <div id="export-modal"')

    # Inject JS functions into main script block
    if 'function openExportChooser' in code and 'function openForwardToExpertModal' not in code:
        code = code.replace('function openExportChooser', js_code + '\n        function openExportChooser')

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(code)

print("Cleanly fixed Forward to Expert modal and JS script placement!")
