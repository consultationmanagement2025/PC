import os
import glob

php_files = glob.glob(r"c:\xampp\htdocs\CAP101\PC\**\system-template-full.php", recursive=True)
js_files = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
]

# 1. Update PHP files
for path in php_files:
    if not os.path.exists(path):
        continue
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()

    old_th = '<th class="px-6 py-3 text-center font-semibold text-gray-700">Status Tracker</th>'
    old_td_colspan = '<tr><td colspan="6" class="text-center text-gray-400 p-6">No documents in this group</td></tr>'
    new_td_colspan = '<tr><td colspan="5" class="text-center text-gray-400 p-6">No documents in this group</td></tr>'

    modified = False
    if old_th in content:
        content = content.replace(old_th, "")
        modified = True
    if old_td_colspan in content:
        content = content.replace(old_td_colspan, new_td_colspan)
        modified = True

    if modified:
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Updated PHP: {path}")

# 2. Update JS files
for path in js_files:
    if not os.path.exists(path):
        continue
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()

    old_js_thead = """                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Document Title</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Type</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Status Tracker</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Size</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Downloads</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Actions</th>"""

    new_js_thead = """                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Document Title</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Type</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Size</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Downloads</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Actions</th>"""

    old_colspan = '<tr><td colspan="6" class="text-center text-gray-400 p-6">No documents in this group</td></tr>'
    new_colspan = '<tr><td colspan="5" class="text-center text-gray-400 p-6">No documents in this group</td></tr>'

    old_row_block = """        const docDotsTrackerHtml = renderConnectingDotsTracker(doc.status, docIdClean, 'document');

        return `
            <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                <td class="px-6 py-4">
                    <div class="font-semibold text-gray-900">${doc.title || doc.reference || '-'}</div>
                    <div class="text-gray-600 text-xs mt-1">${doc.description || ''}</div>
                </td>
                <td class="px-6 py-4 text-gray-700">${doc.type || '-'}</td>
                <td class="px-6 py-4 text-center">${docDotsTrackerHtml}</td>
                <td class="px-6 py-4 text-center text-gray-600">${formatFileSize(doc.size || 0)}</td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-600 rounded-full font-semibold text-sm">
                        ${doc.downloads || 0}
                    </span>
                </td>
                <td class="px-4 py-3 text-center align-middle whitespace-nowrap">
                    <div class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap">
                        <button onclick="downloadDocument('${String(doc.uid || doc.id).replace(/'/g, "\\'")}')" class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 rounded-md text-xs font-semibold transition cursor-pointer shadow-2xs" title="Download Document">
                            <i class="bi bi-download text-blue-600"></i> Download
                        </button>
                        ${doc.downloadUrl && doc.downloadUrl !== '#' ? `
                        <button onclick="viewDocument('${String(doc.uid || doc.id).replace(/'/g, "\\'")}')" class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200 rounded-md text-xs font-semibold transition cursor-pointer shadow-2xs" title="View Document">
                            <i class="bi bi-eye text-slate-600"></i> View
                        </button>` : ''}
                        <button onclick="openLiveDocumentTrackerModal('${docIdClean}', '${docSource}', '${docRef}', '${docTitle}')" class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200/80 rounded-md text-xs font-semibold transition cursor-pointer shadow-2xs" title="View Detailed Audit Timeline">
                            <i class="bi bi-clock-history text-amber-600"></i> Event Audit Log
                        </button>
                        <button onclick="openForwardLRSModal('${doc.id}', '${docSource}', '${docRef}', '${docTitle}')" class="inline-flex items-center gap-1 px-2.5 py-1 bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 rounded-md text-xs font-bold transition cursor-pointer shadow-2xs" title="Forward to LRS">
                            <i class="bi bi-send-fill text-rose-600"></i> Forward to LRS
                        </button>
                    </div>
                </td>
            </tr>
        `;"""

    new_row_block = """        return `
            <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                <td class="px-6 py-4">
                    <div class="font-semibold text-gray-900">${doc.title || doc.reference || '-'}</div>
                    <div class="text-gray-600 text-xs mt-1">${doc.description || ''}</div>
                </td>
                <td class="px-6 py-4 text-gray-700">${doc.type || '-'}</td>
                <td class="px-6 py-4 text-center text-gray-600">${formatFileSize(doc.size || 0)}</td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-600 rounded-full font-semibold text-sm">
                        ${doc.downloads || 0}
                    </span>
                </td>
                <td class="px-4 py-3 text-center align-middle whitespace-nowrap">
                    <div class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap">
                        <button onclick="downloadDocument('${String(doc.uid || doc.id).replace(/'/g, "\\'")}')" class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 rounded-md text-xs font-semibold transition cursor-pointer shadow-2xs" title="Download Document">
                            <i class="bi bi-download text-blue-600"></i> Download
                        </button>
                        ${doc.downloadUrl && doc.downloadUrl !== '#' ? `
                        <button onclick="viewDocument('${String(doc.uid || doc.id).replace(/'/g, "\\'")}')" class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200 rounded-md text-xs font-semibold transition cursor-pointer shadow-2xs" title="View Document">
                            <i class="bi bi-eye text-slate-600"></i> View
                        </button>` : ''}
                        <button onclick="openForwardLRSModal('${doc.id}', '${docSource}', '${docRef}', '${docTitle}')" class="inline-flex items-center gap-1 px-2.5 py-1 bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 rounded-md text-xs font-bold transition cursor-pointer shadow-2xs" title="Forward to LRS">
                            <i class="bi bi-send-fill text-rose-600"></i> Forward to LRS
                        </button>
                    </div>
                </td>
            </tr>
        `;"""

    modified = False
    if old_js_thead in content:
        content = content.replace(old_js_thead, new_js_thead)
        modified = True
    if old_colspan in content:
        content = content.replace(old_colspan, new_colspan)
        modified = True
    if old_row_block in content:
        content = content.replace(old_row_block, new_row_block)
        modified = True

    if modified:
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Updated JS: {path}")
    else:
        print(f"JS pattern mismatch in {path}")

print("Document management tracker removal completed!")
