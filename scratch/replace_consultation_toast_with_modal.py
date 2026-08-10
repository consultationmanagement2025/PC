import os

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

modal_code = """
window.showConsultationSuccessModal = function(isUpdate, title) {
    let modal = document.getElementById('consultation-success-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'consultation-success-modal';
        modal.className = 'fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-[99999] flex items-center justify-center p-4 transition-all duration-200';
        document.body.appendChild(modal);
    }

    const modalTitle = isUpdate ? 'Consultation Updated!' : 'Consultation Posted Successfully!';
    const modalSubtitle = isUpdate 
        ? 'The consultation details have been saved and updated.' 
        : 'Your public consultation has been published and is now active. Citizens can view details, submit feedback, and AI sentiment analysis will process incoming responses.';

    modal.innerHTML = `
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-8 relative border border-slate-200 space-y-5 text-center animate-in fade-in zoom-in duration-150">
            <div class="w-16 h-16 rounded-3xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-3xl font-bold mx-auto border border-emerald-200 shadow-sm">
                <i class="bi bi-check-circle-fill"></i>
            </div>

            <div class="space-y-2">
                <h3 class="text-xl font-black text-slate-900 leading-tight">${escapeHtml(modalTitle)}</h3>
                ${title ? `<p class="text-xs font-bold text-red-800 bg-red-50 py-1.5 px-3 rounded-xl border border-red-100 truncate">${escapeHtml(title)}</p>` : ''}
                <p class="text-xs text-slate-500 leading-relaxed pt-1">${escapeHtml(modalSubtitle)}</p>
            </div>

            <div class="pt-2">
                <button type="button" onclick="closeConsultationSuccessModal()" class="w-full bg-gradient-to-r from-red-700 via-red-800 to-slate-900 hover:from-red-800 hover:to-black text-white font-extrabold py-3.5 px-6 rounded-2xl text-xs transition shadow-md flex items-center justify-center gap-2 cursor-pointer">
                    <i class="bi bi-check-lg text-sm"></i> Done & View Active Consultations
                </button>
            </div>
        </div>
    `;
};

window.closeConsultationSuccessModal = function() {
    const modal = document.getElementById('consultation-success-modal');
    if (modal) modal.remove();
};
"""

target_toast = "showNotification(id ? 'Consultation updated successfully' : 'Consultation created successfully! It will now appear in Active Consultations.', 'success');"

replacement = "showConsultationSuccessModal(!!id, title);"

for fpath in files_to_update:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    if target_toast in code:
        code = code.replace(target_toast, replacement)
        if 'showConsultationSuccessModal' not in code:
            code += "\n\n" + modal_code
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Successfully replaced toast with system modal in:", fpath)
    else:
        print("Target toast not found in:", fpath)
