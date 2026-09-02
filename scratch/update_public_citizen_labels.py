import os
import re
import subprocess

js_files = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
]

api_files = [
    r"c:\xampp\htdocs\CAP101\PC\API\citizens_api.php",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\API\citizens_api.php",
    r"c:\xampp\htdocs\CAP101\PC\admin\API\citizens_api.php",
]

print("=== 1. Updating JS files for Public Participant labels ===")
for js_path in js_files:
    if not os.path.exists(js_path):
        continue
    with open(js_path, 'r', encoding='utf-8') as f:
        c = f.read()

    # 1. Update subtitle
    c = c.replace(
        "Monitor, verify, and engage registered citizen submitters across Valenzuela City.",
        "Monitor, verify, and engage registered public citizen participants."
    )

    # 2. Update citizen submitter label
    c = c.replace(
        '<div class="text-[11px] text-gray-500">Valenzuela Citizen Submitter</div>',
        '<div class="text-[11px] text-gray-500">Verified Citizen Submitter</div>'
    )

    # 3. Update default barangay location fallback
    c = c.replace(
        "${escapeHtml(c.barangay || 'Valenzuela City')}",
        "${escapeHtml(c.barangay || 'Public Participant')}"
    )
    c = c.replace(
        "(c.barangay || 'Valenzuela City')",
        "(c.barangay || 'Public Participant')"
    )

    with open(js_path, 'w', encoding='utf-8') as f:
        f.write(c)
    print(f"Updated citizen labels in {js_path}")

    res = subprocess.run(["node", "-c", js_path], capture_output=True, text=True)
    if res.returncode == 0:
        print(f"  Node syntax check PASSED: {os.path.basename(js_path)}")
    else:
        print(f"  Node syntax ERROR in {os.path.basename(js_path)}:\n{res.stderr}")

print("\n=== 2. Updating API files for Public Participant location fallback ===")
for api_path in api_files:
    if not os.path.exists(api_path):
        continue
    with open(api_path, 'r', encoding='utf-8') as f:
        c = f.read()

    c = c.replace("'barangay' => 'Valenzuela City'", "'barangay' => 'Public Participant'")

    with open(api_path, 'w', encoding='utf-8') as f:
        f.write(c)
    print(f"Updated location fallback in {api_path}")

print("Done updating public citizen labels!")
