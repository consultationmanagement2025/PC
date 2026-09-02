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

new_render_func = """function pfpRenderNotificationItemHtml(n) {
    const isRead = Boolean(n.is_read && Number(n.is_read) === 1);
    const msgRaw = n.message || '';
    const msg = escapeHtml(msgRaw);
    const cleanType = escapeHtml(String(n.type || 'info').toLowerCase());
    const cleanMsgAttr = escapeHtml(msgRaw.replace(/\\s+/g, ' ').trim());
    const dateStr = n.created_at ? new Date(n.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Just now';

    let title = 'System Notification';
    let iconClass = 'bi-bell-fill text-blue-600 bg-blue-50 border-blue-100';

    if (cleanType === 'phms_feedback' || msgRaw.includes('PHMS')) {
        title = '🏢 PHMS Hearing Feedback';
        iconClass = 'bi-building-fill-gear text-emerald-600 bg-emerald-50 border-emerald-100';
    } else if (msgRaw.includes('AI') || cleanType === 'ai_brief') {
        title = '🤖 AI Committee Brief';
        iconClass = 'bi-robot text-purple-600 bg-purple-50 border-purple-100';
    } else if (msgRaw.includes('Feedback') || msgRaw.includes('Proposal') || cleanType === 'feedback') {
        title = '📩 Citizen Feedback';
        iconClass = 'bi-chat-left-text text-emerald-600 bg-emerald-50 border-emerald-100';
    } else if (cleanType === 'consultation' || msgRaw.includes('Survey')) {
        title = '📊 Community Poll Update';
        iconClass = 'bi-square-poll text-amber-600 bg-amber-50 border-amber-100';
    }

    return `
        <div data-id="${n.id}" data-type="${cleanType}" data-msg="${cleanMsgAttr}" onclick="pfpHandleNotifElementClick(this)" class="p-4 transition hover:bg-blue-50/70 flex items-start gap-3.5 relative cursor-pointer ${!isRead ? 'bg-white font-medium' : 'bg-gray-50/40 opacity-75'}">
            <div class="w-10 h-10 rounded-2xl border flex items-center justify-center shrink-0 mt-0.5 ${iconClass}">
                <i class="bi bi-bell text-base"></i>
            </div>
            <div class="flex-1 min-w-0 pr-3">
                <div class="font-bold text-gray-900 text-xs leading-snug">${title}</div>
                <div class="text-xs text-gray-500 mt-0.5 leading-relaxed font-normal">${msg}</div>
                <div class="text-[11px] text-gray-400 mt-1 font-medium">${dateStr}</div>
            </div>
            ${!isRead ? '<span class="w-2.5 h-2.5 rounded-full bg-red-500 shrink-0 mt-1.5 ring-4 ring-red-50"></span>' : ''}
        </div>
    `;
}"""

for js_path in js_files:
    if not os.path.exists(js_path):
        continue
    with open(js_path, 'r', encoding='utf-8') as f:
        c = f.read()

    # Replace pfpRenderNotificationItemHtml function definition
    old_func_pattern = r"function pfpRenderNotificationItemHtml\(n\)\s*\{[\s\S]*?return `[\s\S]*?`;\s*\}"
    c = re.sub(old_func_pattern, lambda m: new_render_func, c)

    with open(js_path, 'w', encoding='utf-8') as f:
        f.write(c)
    print(f"Updated pfpRenderNotificationItemHtml in {js_path}")

    res = subprocess.run(["node", "-c", js_path], capture_output=True, text=True)
    if res.returncode == 0:
        print(f"  Node syntax check PASSED: {os.path.basename(js_path)}")
    else:
        print(f"  Node syntax ERROR in {os.path.basename(js_path)}:\n{res.stderr}")

print("Done updating notification HTML escaping!")
