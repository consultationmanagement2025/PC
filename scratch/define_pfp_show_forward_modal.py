import os
import subprocess

js_files = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
]

php_files = [
    r"c:\xampp\htdocs\CAP101\PC\system-template-full.php",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\system-template-full.php",
    r"c:\xampp\htdocs\CAP101\PC\admin\system-template-full.php",
]

pfp_show_forward_modal_code = """
window.pfpShowForwardModal = function(consultationId) {
    let title = 'Public Consultation File';
    let category = 'General Policy';
    const list = (window.AppData && Array.isArray(window.AppData.consultations)) ? window.AppData.consultations : [];
    const found = list.find(c => Number(c.id) === Number(consultationId));
    if (found) {
        title = found.title || title;
        category = found.category || category;
    }
    if (typeof openForwardToExpertModal === 'function') {
        openForwardToExpertModal(consultationId, title, category);
    } else {
        const modal = document.getElementById('forward-expert-modal');
        if (modal) {
            const idInput = document.getElementById('forward-consultation-id');
            if (idInput) idInput.value = consultationId;
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        } else {
            console.warn('[pfpShowForwardModal] forward-expert-modal not found');
        }
    }
};
"""

print("=== 1. Injecting window.pfpShowForwardModal into JS files ===")
for js_path in js_files:
    if not os.path.exists(js_path):
        continue
    with open(js_path, 'r', encoding='utf-8') as f:
        c = f.read()

    if "window.pfpShowForwardModal = function" not in c:
        c += "\n" + pfp_show_forward_modal_code + "\n"
        with open(js_path, 'w', encoding='utf-8') as f:
            f.write(c)
        print(f"Added window.pfpShowForwardModal to {js_path}")

    res = subprocess.run(["node", "-c", js_path], capture_output=True, text=True)
    if res.returncode == 0:
        print(f"  Node syntax check PASSED: {os.path.basename(js_path)}")
    else:
        print(f"  Node syntax ERROR in {os.path.basename(js_path)}:\n{res.stderr}")

print("\n=== 2. Injecting window.pfpShowForwardModal into PHP files ===")
for php_path in php_files:
    if not os.path.exists(php_path):
        continue
    with open(php_path, 'r', encoding='utf-8') as f:
        c = f.read()

    if "window.pfpShowForwardModal = function" not in c:
        c = c.replace(
            "function openForwardToExpertModal(id, title, category) {",
            pfp_show_forward_modal_code + "\n        function openForwardToExpertModal(id, title, category) {"
        )
        with open(php_path, 'w', encoding='utf-8') as f:
            f.write(c)
        print(f"Added window.pfpShowForwardModal to {php_path}")

print("Done defining window.pfpShowForwardModal!")
