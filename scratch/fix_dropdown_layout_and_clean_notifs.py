import os

# 1. Clean DB notifications & remove sync notifications from DATABASE/feedback.php
db_feedback_files = [
    r'c:\xampp\htdocs\CAP101\PC\DATABASE\feedback.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\DATABASE\feedback.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\feedback.php'
]

print("=== REMOVING SYNC NOTIFICATIONS FROM DATABASE/FEEDBACK.PHP ===")
for fpath in db_feedback_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    sync_notif_code = """        if (function_exists('createNotification') && count($hearings) > 0) {
            $syncCount = count($hearings);
            createNotification(0, "🏢 PHMS Integration Sync: {$syncCount} Citizen Hearing Feedback items ingested into Public Feedback Queue.", "phms_feedback");
        }"""

    if sync_notif_code in code:
        code = code.replace(sync_notif_code, "        // Silent background sync - user requested no noisy sync notifications.")
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Removed sync notification from:", fpath)
    else:
        print("Sync notification already removed or not found in:", fpath)


# 2. Update system-template-full.php for fixed footer height & visible button
system_template_files = [
    r'c:\xampp\htdocs\CAP101\PC\system-template-full.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\system-template-full.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\system-template-full.php'
]

print("\n=== UPDATING NOTIFICATIONS DROPDOWN IN SYSTEM-TEMPLATE-FULL.PHP ===")

target_old = """<div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 md:w-[380px] bg-white rounded-2xl shadow-xl border border-gray-100 z-50 max-h-96 flex flex-col overflow-hidden transition-all duration-200" style="z-index: 9999 !important;">"""

target_new = """<div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 md:w-[380px] bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 flex flex-col overflow-hidden transition-all duration-200" style="z-index: 9999 !important; max-height: 480px;">"""

list_old = """<div id="notifications-list" class="overflow-y-auto max-h-80 divide-y divide-gray-100">"""
list_new = """<div id="notifications-list" class="overflow-y-auto flex-1 min-h-0 divide-y divide-gray-100 max-h-[330px]">"""

footer_old = """                                    <div class="px-4 py-3 bg-gray-50/90 border-t border-gray-100 text-center shrink-0">
                                        <button type="button" onclick="pfpOpenViewPreviousNotificationsModal()" class="text-xs font-bold text-red-600 hover:text-red-800 transition flex items-center justify-center gap-1.5 mx-auto cursor-pointer">
                                            <i class="bi bi-clock-history text-sm"></i> View Previous Notifications
                                        </button>
                                    </div>"""

footer_new = """                                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 text-center shrink-0">
                                        <button type="button" onclick="pfpOpenViewPreviousNotificationsModal()" class="w-full py-2 px-3 bg-white hover:bg-gray-100 text-red-600 border border-gray-200 rounded-xl text-xs font-extrabold transition shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                                            <i class="bi bi-clock-history text-sm"></i> View Previous Notifications
                                        </button>
                                    </div>"""

for fpath in system_template_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    code = code.replace(target_old, target_new)
    code = code.replace(list_old, list_new)
    code = code.replace(footer_old, footer_new)

    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated dropdown container height & footer button in:", fpath)

print("\n=== RUNNING DATABASE NOTIFICATIONS CLEANUP SCRIPT ===")
