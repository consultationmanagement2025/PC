import os

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

old_try_block = """    try {
        await loadDocumentsFromApi();
    } catch (err) {
        contentArea.innerHTML = `<div class="p-8 text-center text-red-600">Failed to load documents.<div class="text-sm text-gray-500 mt-2">${String(err && err.message ? err.message : err)}</div></div>`;
        return;
    }"""

new_try_block = """    try {
        await loadDocumentsFromApi();
    } catch (err) {
        console.warn("loadDocumentsFromApi failed:", err);
        if (!Array.isArray(AppData.documents)) {
            AppData.documents = [];
        }
        if (AppData.documents.length === 0) {
            contentArea.innerHTML = `
                <div class="p-8 text-center bg-white rounded-xl border border-red-200 shadow-sm max-w-lg mx-auto my-8">
                    <i class="bi bi-exclamation-triangle-fill text-red-500 text-3xl block mb-2"></i>
                    <h4 class="font-bold text-gray-900 text-base">Unable to Load Documents</h4>
                    <p class="text-xs text-gray-600 mt-1">${escapeHtml(String(err && err.message ? err.message : err))}</p>
                    <button onclick="pfpRenderDocumentManagement()" class="mt-4 px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-xs transition shadow-sm inline-flex items-center gap-1.5">
                        <i class="bi bi-arrow-clockwise"></i> Retry Loading
                    </button>
                </div>`;
            return;
        }
    }"""

for fpath in files_to_update:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        content = f.read()
    if old_try_block in content:
        content = content.replace(old_try_block, new_try_block)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Updated try block in {fpath}")
    else:
        print(f"Could not find exact try block in {fpath}")

print("Done updating app-features.js try blocks.")
