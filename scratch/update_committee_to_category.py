import os

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

for fpath in files_to_update:
    if not os.path.exists(fpath):
        print(f"Missing: {fpath}")
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Table header: Assigned Committee -> Category
    content = content.replace('<th class="px-4 py-3 text-gray-900">Assigned Committee</th>', '<th class="px-4 py-3 text-gray-900">Category</th>')
    
    # 2. Table row category display
    old_row_snippet = '''<div class="text-[11px] text-gray-500 font-medium">Category: ${escapeHtml(c.category || 'General Policy')}</div>
                    </td>
                    <td class="px-4 py-3.5 font-semibold text-purple-900">
                        <span class="inline-block px-2 py-0.5 ${c.committee_assigned ? 'bg-purple-50 text-purple-900 border border-purple-200' : 'bg-gray-100 text-gray-600 border border-gray-200'} rounded text-xs">
                            <i class="bi bi-diagram-3 mr-1"></i>${committeeName}
                        </span>
                    </td>'''

    new_row_snippet = '''<div class="text-[11px] text-gray-500 font-medium">Type: ${escapeHtml(c.type || 'Public Consultation')}</div>
                    </td>
                    <td class="px-4 py-3.5 font-semibold text-purple-900">
                        <span class="inline-block px-2.5 py-1 bg-purple-50 text-purple-900 border border-purple-200 rounded text-xs font-semibold">
                            <i class="bi bi-tag-fill mr-1 text-purple-600"></i>${escapeHtml(c.category || 'General Policy')}
                        </span>
                    </td>'''

    if old_row_snippet in content:
        content = content.replace(old_row_snippet, new_row_snippet)
        print(f"Replaced table row snippet in {fpath}")
    else:
        print(f"Table row snippet not exact match in {fpath}")

    # 3. AI Brief modal header & card
    content = content.replace(
        'Assigned LGU Committee: <strong class="text-white font-bold">${escapeHtml(brief.committee_assigned || brief.assigned_committee || \'Rules & Governance Committee\')}</strong>',
        'Category: <strong class="text-white font-bold">${escapeHtml(c?.category || brief.category || brief.committee_assigned || \'General Policy\')}</strong>'
    )
    
    # 4. Transmittal Target card label in modal
    content = content.replace(
        '<span class="text-slate-500 font-bold uppercase text-[10px] tracking-wider block mb-1">Transmittal Target</span>\n                        <span class="text-xs font-extrabold text-purple-900 block truncate px-2 py-1 bg-purple-50 rounded-lg border border-purple-100" title="${escapeHtml(brief.committee_assigned || brief.assigned_committee || \'Rules & Governance Committee\')}">${escapeHtml(brief.committee_assigned || brief.assigned_committee || \'Rules & Governance Committee\')}</span>',
        '<span class="text-slate-500 font-bold uppercase text-[10px] tracking-wider block mb-1">Category</span>\n                        <span class="text-xs font-extrabold text-purple-900 block truncate px-2 py-1 bg-purple-50 rounded-lg border border-purple-100" title="${escapeHtml(c?.category || brief.category || \'General Policy\')}">${escapeHtml(c?.category || brief.category || \'General Policy\')}</span>'
    )

    # 5. Approve modal label
    content = content.replace(
        '<label class="block font-bold text-slate-700 mb-1">Assigned LGU Committee <span class="text-red-500">*</span></label>',
        '<label class="block font-bold text-slate-700 mb-1">Category / Committee <span class="text-red-500">*</span></label>'
    )

    # Write back
    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(content)

print("Finished updating app-features.js files.")
