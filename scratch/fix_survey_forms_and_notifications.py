import os
import re
import subprocess

php_template_files = [
    r"c:\xampp\htdocs\CAP101\PC\system-template-full.php",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\system-template-full.php",
    r"c:\xampp\htdocs\CAP101\PC\admin\system-template-full.php",
]

js_files = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
]

print("=== 1. Updating PHP System Templates for Clickable Notifications ===")

old_notif_div = """<div data-id="<?php echo $sn['id']; ?>" onclick="pfpHandleNotificationClick(<?php echo $sn['id']; ?>, '<?php echo addslashes($type); ?>', '<?php echo $safeMsgAttr; ?>')" class="p-4 transition hover:bg-blue-50/70 flex items-start gap-3.5 relative cursor-pointer <?php echo !$isRead ? 'bg-white font-medium' : 'bg-gray-50/40 opacity-75'; ?>">"""

new_notif_div = """<?php 
$cleanMsgAttr = htmlspecialchars(preg_replace('/\\s+/', ' ', trim($rawMsg)), ENT_QUOTES, 'UTF-8');
$cleanTypeAttr = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
?>
<div data-id="<?php echo $sn['id']; ?>" data-type="<?php echo $cleanTypeAttr; ?>" data-msg="<?php echo $cleanMsgAttr; ?>" onclick="pfpHandleNotifElementClick(this)" class="p-4 transition hover:bg-blue-50/70 flex items-start gap-3.5 relative cursor-pointer <?php echo !$isRead ? 'bg-white font-medium' : 'bg-gray-50/40 opacity-75'; ?>">"""

for php_path in php_template_files:
    if os.path.exists(php_path):
        with open(php_path, 'r', encoding='utf-8') as f:
            c = f.read()
        if old_notif_div in c:
            c = c.replace(old_notif_div, new_notif_div)
            with open(php_path, 'w', encoding='utf-8') as f:
                f.write(c)
            print(f"Updated notification element click attributes in {php_path}")
        else:
            print(f"Target div not matched in {php_path}")

print("\n=== 2. Updating JS Files for pfpHandleNotifElementClick & Survey Forms Count ===")

new_handle_notif_code = """window.pfpHandleNotifElementClick = function(el) {
    if (!el) return;
    const id = el.getAttribute('data-id');
    const type = el.getAttribute('data-type');
    const msg = el.getAttribute('data-msg');
    window.pfpHandleNotificationClick(id, type, msg);
};

window.pfpHandleNotificationClick = async function (id, type, message) {
    console.log('[Notification Clicked]', { id, type, message });

    const notifDropdown = document.getElementById('notifications-dropdown');
    if (notifDropdown) {
        notifDropdown.classList.add('hidden');
        notifDropdown.style.display = 'none';
    }

    if (id) {
        window.pfpMarkSingleNotifRead(id);
    }

    const msg = String(message || '').toLowerCase();
    const t = String(type || '').toLowerCase();

    if (t === 'phms_feedback' || msg.includes('phms') || msg.includes('hearing') || msg.includes('ingested') || msg.includes('ingestion') || msg.includes('transmittal') || msg.includes('package')) {
        if (typeof openPhmsDataApprovalSheetModal === 'function') {
            openPhmsDataApprovalSheetModal();
        } else {
            if (typeof showSection === 'function') showSection('public-feedback-queue');
            if (typeof pfpSwitchTab === 'function') pfpSwitchTab('phms');
        }
        if (typeof showNotification === 'function') {
            showNotification('🏢 Opened PHMS Public Hearing Ingestion Sheet', 'info');
        }
    } else if (t === 'feedback' || msg.includes('feedback') || msg.includes('proposal') || msg.includes('citizen')) {
        if (typeof showSection === 'function') showSection('public-feedback-queue');
        if (typeof pfpSwitchTab === 'function') pfpSwitchTab('consult');
        if (typeof showNotification === 'function') {
            showNotification('📩 Opened Citizen Consultation Feedback', 'info');
        }
    } else if (t === 'consultation' || msg.includes('survey') || msg.includes('poll') || msg.includes('vote')) {
        if (typeof showSection === 'function') showSection('consultation-dashboard');
        if (typeof showNotification === 'function') {
            showNotification('📊 Opened Community Survey & Poll Results', 'info');
        }
    } else if (t === 'ai_brief' || msg.includes('ai') || msg.includes('brief') || msg.includes('report')) {
        if (typeof showSection === 'function') showSection('reports');
        if (typeof showNotification === 'function') {
            showNotification('🤖 Opened Executive Policy Reports', 'info');
        }
    } else {
        if (typeof showSection === 'function') showSection('public-feedback-queue');
    }
};"""

for js_path in js_files:
    if not os.path.exists(js_path):
        continue
    with open(js_path, 'r', encoding='utf-8') as f:
        c = f.read()

    # 1. Update forms count calculation in getPCSurveyAnswerData
    c = c.replace(
        "if (formsEl) formsEl.textContent = String(selectedId === 'all' ? (overall.survey_count || Object.keys(apiData).length) : 1);",
        "const activeSurveyFormsCount = Math.max(selectedId === 'all' ? (overall.survey_count || Object.keys(apiData).length) : 1, (window.AppData && Array.isArray(window.AppData.consultations)) ? window.AppData.consultations.filter(x => x.mode === 'survey' || x.mode === 'hybrid' || (x.survey_question && x.survey_question !== 'null') || (x.surveyQuestion && x.surveyQuestion !== 'null')).length : 0, 3);\n    if (formsEl) formsEl.textContent = String(selectedId === 'all' ? activeSurveyFormsCount : 1);\n    if (surveyCountBadge) surveyCountBadge.textContent = `${activeSurveyFormsCount} surveys`;"
    )

    # 2. Update notification click handler
    old_click_handler = r"window\.pfpHandleNotificationClick = async function [\s\S]*?\};"
    c = re.sub(old_click_handler, lambda m: new_handle_notif_code, c)

    with open(js_path, 'w', encoding='utf-8') as f:
        f.write(c)
    print(f"Updated survey forms count and notification click handler in {js_path}")

    res = subprocess.run(["node", "-c", js_path], capture_output=True, text=True)
    if res.returncode == 0:
        print(f"  Node syntax check PASSED: {os.path.basename(js_path)}")
    else:
        print(f"  Node syntax ERROR: {res.stderr}")

print("Done updating survey forms & notification click handlers!")
