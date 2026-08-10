import os

files_to_fix = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

js_impl = """window.approveResourcePersonApp = function(id, fullname) {
    if (!confirm('Are you sure you want to approve this Resource Person application?')) return;
    const formData = new FormData();
    formData.append('user_id', id);
    formData.append('action', 'approve');

    fetch('API/resource_person_api.php?action=approve', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('⚠️ ' + (data.message || 'Approval failed'));
        }
    })
    .catch(err => alert('❌ Error: ' + err.message));
};

window.rejectResourcePersonApp = function(id, fullname) {
    if (!confirm('Are you sure you want to reject this Resource Person application?')) return;
    const formData = new FormData();
    formData.append('user_id', id);
    formData.append('action', 'reject');

    fetch('API/resource_person_api.php?action=reject', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('⚠️ ' + (data.message || 'Rejection failed'));
        }
    })
    .catch(err => alert('❌ Error: ' + err.message));
};"""

target = """window.approveResourcePersonApp = function(id, fullname) {
    if (typeof approveResourcePerson === 'function') {
        approveResourcePerson(id, fullname);
    }
};

window.rejectResourcePersonApp = function(id, fullname) {
    if (typeof rejectResourcePerson === 'function') {
        rejectResourcePerson(id, fullname);
    }
};"""

for fpath in files_to_fix:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    if target in code:
        code = code.replace(target, js_impl)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated approval handlers in:", fpath)
    else:
        print("Target not found in:", fpath)
