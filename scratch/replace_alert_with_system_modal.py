import os, re

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\resource_person_dashboard.php'
]

modal_functions = """
    function showMasterDocSuccessModal(version, message) {
        let modal = document.getElementById('master-doc-success-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'master-doc-success-modal';
            modal.className = 'fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-[99999] flex items-center justify-center p-4 transition-all duration-200';
            document.body.appendChild(modal);
        }

        var safeMsg = message || 'Inline expert recommendations appended cleanly into single master document.';
        var verBadge = version ? `<div class="inline-block px-3 py-1 bg-red-50 text-red-800 font-extrabold text-xs rounded-xl border border-red-100"><i class="bi bi-file-earmark-code mr-1 text-red-600"></i>Version ${version} Appended</div>` : '';

        modal.innerHTML = `
            <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-8 relative border border-slate-200 space-y-5 text-center animate-in fade-in zoom-in duration-150">
                <div class="w-16 h-16 rounded-3xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-3xl font-bold mx-auto border border-emerald-200 shadow-sm">
                    <i class="bi bi-file-earmark-check-fill"></i>
                </div>

                <div class="space-y-2">
                    <h3 class="text-xl font-black text-slate-900 leading-tight">Master Document Updated!</h3>
                    ${verBadge}
                    <p class="text-xs text-slate-600 leading-relaxed pt-2">${safeMsg}</p>
                </div>

                <div class="pt-2">
                    <button type="button" onclick="location.reload()" class="w-full bg-gradient-to-r from-red-700 via-red-800 to-slate-900 hover:from-red-800 hover:to-black text-white font-extrabold py-3.5 px-6 rounded-2xl text-xs transition shadow-md flex items-center justify-center gap-2 cursor-pointer">
                        <i class="bi bi-check-lg text-sm"></i> Done & Refresh Workspace
                    </button>
                </div>
            </div>
        `;
        modal.classList.remove('hidden');
    }

    function showSystemErrorModal(message) {
        let modal = document.getElementById('system-error-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'system-error-modal';
            modal.className = 'fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-[99999] flex items-center justify-center p-4 transition-all duration-200';
            document.body.appendChild(modal);
        }

        modal.innerHTML = `
            <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-8 relative border border-slate-200 space-y-5 text-center animate-in fade-in zoom-in duration-150">
                <div class="w-16 h-16 rounded-3xl bg-rose-100 text-rose-600 flex items-center justify-center text-3xl font-bold mx-auto border border-rose-200 shadow-sm">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>

                <div class="space-y-2">
                    <h3 class="text-xl font-black text-slate-900 leading-tight">Action Error</h3>
                    <p class="text-xs text-slate-600 leading-relaxed pt-1">${message || 'An error occurred while processing your request.'}</p>
                </div>

                <div class="pt-2">
                    <button type="button" onclick="document.getElementById('system-error-modal').remove()" class="w-full bg-slate-900 hover:bg-black text-white font-extrabold py-3.5 px-6 rounded-2xl text-xs transition shadow-md flex items-center justify-center gap-2 cursor-pointer">
                        <i class="bi bi-x-circle text-sm"></i> Close & Retry
                    </button>
                </div>
            </div>
        `;
        modal.classList.remove('hidden');
    }
"""

for fpath in files_to_update:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    # Replace alert calls inside submitInlineExpertInput
    old_success_block = """            if (data.success) {
                alert(data.message);
                closeInlineInputModal();
                location.reload();
            } else {
                alert('⚠️ ' + (data.message || 'Failed to save inline input'));
            }"""

    new_success_block = """            if (data.success) {
                closeInlineInputModal();
                showMasterDocSuccessModal(data.version, data.message);
            } else {
                showSystemErrorModal(data.message || 'Failed to save inline input');
            }"""

    old_catch = "alert('❌ Error: ' + err.message);"
    new_catch = "showSystemErrorModal('Error: ' + err.message);"

    if old_success_block in code:
        code = code.replace(old_success_block, new_success_block)

    if old_catch in code:
        code = code.replace(old_catch, new_catch)

    # Append modal helper functions if not present
    if 'showMasterDocSuccessModal' not in code:
        code = code.replace('function submitInlineExpertInput()', modal_functions + '\n    function submitInlineExpertInput()')

    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(code)

    print("Replaced browser alert with system modal in:", fpath)
