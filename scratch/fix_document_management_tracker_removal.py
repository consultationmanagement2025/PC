import os

js_files = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
]

for path in js_files:
    if not os.path.exists(path):
        print(f"Skipping: {path}")
        continue
    with open(path, "r", encoding="utf-8") as f:
        content = f.read()

    # Target 1: const docDotsTrackerHtml
    t1 = "const docDotsTrackerHtml = renderConnectingDotsTracker(doc.status, docIdClean, 'document');"
    if t1 in content:
        content = content.replace(t1, "// Tracker removed for Document Management")

    # Target 2: table cell with docDotsTrackerHtml
    t2 = '<td class="px-6 py-4 text-center">${docDotsTrackerHtml}</td>'
    if t2 in content:
        content = content.replace(t2, "")

    # Target 3: Event Audit Log button
    t3 = """                        <button onclick="openLiveDocumentTrackerModal('${docIdClean}', '${docSource}', '${docRef}', '${docTitle}')" class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200/80 rounded-md text-xs font-semibold transition cursor-pointer shadow-2xs" title="View Detailed Audit Timeline">
                            <i class="bi bi-clock-history text-amber-600"></i> Event Audit Log
                        </button>"""
    if t3 in content:
        content = content.replace(t3, "")

    with open(path, "w", encoding="utf-8") as f:
        f.write(content)
    print(f"Successfully cleaned JS: {path}")

print("Fix completed!")
