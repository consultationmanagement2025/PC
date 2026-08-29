import os

print("=== DELEGATING OPENGENERATEREPORTMODAL DIRECTLY TO OPENCUSTOMREPORTEXPORTMODAL ===")

js_paths = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

for path in js_paths:
    if os.path.exists(path):
        with open(path, 'r', encoding='utf-8', errors='ignore') as f:
            code = f.read()

        # Re-route openGenerateReportModal to openCustomReportExportModal
        old_fn = "function openGenerateReportModal() {\n    document.getElementById('generate-report-modal').classList.remove('hidden');\n}"
        new_fn = "function openGenerateReportModal() {\n    if (typeof openCustomReportExportModal === 'function') {\n        openCustomReportExportModal('pdf');\n    }\n}"

        if old_fn in code:
            code = code.replace(old_fn, new_fn)

        # Also replace any standalone openGenerateReportModal calls
        code = code.replace("onclick=\"openGenerateReportModal()\"", "onclick=\"openCustomReportExportModal('pdf')\"")

        with open(path, 'w', encoding='utf-8') as f:
            f.write(code)
        print(f"Delegated openGenerateReportModal in {path}")

print("Modal trigger fix complete!")
